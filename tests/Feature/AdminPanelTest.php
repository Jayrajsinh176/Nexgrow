<?php

namespace Tests\Feature;

use App\Models\NftPackage;
use App\Models\User;
use App\Models\UserNft;
use App\Models\WalletTransaction;
use App\Support\Chain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Nexgrow Admin',
            'user_id' => 'NEXGROW01',
            'is_admin' => true,
        ]);

        $this->regularUser = User::factory()->create([
            'name' => 'Regular Member',
            'is_admin' => false,
        ]);
    }

    public function test_non_admin_users_and_guests_are_forbidden(): void
    {
        // Unauthenticated guests get redirected to admin login
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        // Regular users get 403 Forbidden
        $this->actingAs($this->regularUser)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->regularUser)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->regularUser)->get(route('admin.incomes.index'))->assertForbidden();
        $this->actingAs($this->regularUser)->get(route('admin.blockchain.index'))->assertForbidden();
    }

    public function test_admin_login_portal_authenticates_admin_only(): void
    {
        // GET admin login page renders
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Administrator Portal')
            ->assertSee('Connect Admin Wallet');

        // Traditional POST without wallet connect is rejected
        $this->post(route('admin.login'), [
            'login' => $this->regularUser->user_id,
        ])->assertSessionHasErrors(['login']);

        $this->assertGuest();
    }

    public function test_admin_is_prevented_from_logging_in_on_member_login(): void
    {
        // Direct POST to member login without wallet connect is rejected
        $this->post(route('login'), [
            'login' => $this->admin->user_id,
        ])->assertSessionHasErrors(['login']);

        $this->assertGuest();
    }

    public function test_admin_sees_only_admin_navigation_and_not_member_pages(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        // Admin sees admin portal navigation
        $response->assertSee('Admin Console')
            ->assertSee('Admin Overview')
            ->assertSee('Manage Users')
            ->assertSee('All Incomes')
            ->assertSee('Blockchain Ops')
            ->assertSee('Owner Actions')
            ->assertDontSee('Manage Wallets');

        // Admin DOES NOT see member navigation
        $response->assertDontSee(route('team'))
            ->assertDontSee(route('nft.index'))
            ->assertDontSee(route('referral-income'))
            ->assertDontSee(route('profit-sharing-slot.index'))
            ->assertDontSee('Wallet &amp; Withdrawal', false);
    }

    public function test_admin_visiting_member_dashboard_is_redirected_to_admin_dashboard(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_member_sees_only_member_navigation_and_not_admin_console(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('dashboard'))->assertOk();

        // Member sees member navigation
        $response->assertSee('Dashboard')
            ->assertSee('My Team')
            ->assertSee('Token Wallet')
            ->assertSee('NFT Packages');

        // Member DOES NOT see admin navigation
        $response->assertDontSee('Admin Console')
            ->assertDontSee('Manage Users')
            ->assertDontSee('Blockchain Ops');
    }

    public function test_admin_logout_redirects_to_admin_login(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_admin_can_view_and_search_users(): void
    {
        config(['blockchain.mode' => 'database']);

        $linkedUser = User::factory()->create([
            'name' => 'Alice Nakamoto',
            'user_id' => 'NEX88888',
            'wallet_address' => '0x70997970c51812dc3a010c7d01b50e0d17dc79c8',
        ]);

        $package = NftPackage::create([
            'name' => '500 NXD Package',
            'price' => 500,
            'daily_bonus' => 0.60,
            'cap_multiplier' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        UserNft::create([
            'user_id' => $linkedUser->id,
            'nft_package_id' => $package->id,
            'purchase_price' => 500,
            'daily_bonus' => 0.60,
            'cap_amount' => 2000,
            'status' => 'active',
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();

        $response->assertSee('Alice Nakamoto')
            ->assertSee('NEX88888')
            ->assertSee('0x7099')
            ->assertSee('500.00 NXD');

        // Search test
        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => 'Alice']))
            ->assertOk()
            ->assertSee('Alice Nakamoto');

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => 'NonExistent']))
            ->assertOk()
            ->assertDontSee('Alice Nakamoto');
    }

    public function test_in_chain_mode_linked_wallets_show_on_chain_package_and_balance(): void
    {
        config(['blockchain.mode' => 'chain']);

        $linked = User::factory()->create([
            'wallet_address' => '0x70997970c51812dc3a010c7d01b50e0d17dc79c8',
            'token_balance' => 777.77,
        ]);
        User::factory()->create(['token_balance' => 123.45]);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-nx-chain="members"', false)
            ->assertSee('data-nx-member-wallet="0x70997970c51812dc3a010c7d01b50e0d17dc79c8"', false)
            ->assertSee('data-nx-field="package-loading"', false)
            ->assertDontSee('777.77')
            ->assertSee('123.45');

        $this->actingAs($this->admin)->get(route('admin.users.show', $linked))
            ->assertOk()
            ->assertSee('data-nx-member-wallet="0x70997970c51812dc3a010c7d01b50e0d17dc79c8"', false)
            ->assertSee('On-chain wallet balance')
            ->assertDontSee('777.77');
    }

    public function test_admin_can_view_user_profile_with_income_breakdown(): void
    {
        $user = User::factory()->create([
            'name' => 'Bob Builder',
            'user_id' => 'NEX77777',
            'token_balance' => 1250.50,
        ]);

        $user->walletTransactions()->create([
            'type' => 'credit',
            'amount' => 45.00,
            'balance_after' => 1250.50,
            'reason' => 'daily_nft_bonus',
            'note' => 'Daily ROI bonus',
        ]);

        $user->walletTransactions()->create([
            'type' => 'credit',
            'amount' => 12.50,
            'balance_after' => 1205.50,
            'reason' => 'referral_income',
            'note' => 'Level 1 direct referral bonus',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.users.show', $user))->assertOk();

        $response->assertSee('Bob Builder')
            ->assertSee('NEX77777')
            ->assertSee('1,250.50')
            ->assertSee('Daily NFT Bonus')
            ->assertSee('Referral Income')
            ->assertSee('+45.00')
            ->assertSee('+12.50');
    }

    public function test_admin_can_view_all_incomes_ledger(): void
    {
        WalletTransaction::create([
            'user_id' => $this->regularUser->id,
            'type' => 'credit',
            'amount' => 150.00,
            'balance_after' => 150.00,
            'reason' => 'bonus_booster',
            'note' => '15-Day Booster 5% tier',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.incomes.index'))->assertOk();

        $response->assertSee('Income Overview')
            ->assertSee('15-Day Bonus Booster')
            ->assertSee('150.00')
            // Sidebar dropdown links to every stream page
            ->assertSee(route('admin.incomes.index', ['stream' => 'royalty_bonus']));

        // Each stream has its own page
        $this->assertSame(url('admin/incomes/bonus_booster'), route('admin.incomes.index', ['stream' => 'bonus_booster']));

        $this->actingAs($this->admin)
            ->get(route('admin.incomes.index', ['stream' => 'bonus_booster']))
            ->assertOk()
            ->assertSee('15-Day Bonus Booster')
            ->assertSee('Top Earners')
            ->assertSee('Qualified Periods')
            ->assertSee('Regular Member')
            ->assertSee('150.00');

        // Other streams' credits are not on the stream page
        $this->actingAs($this->admin)
            ->get(route('admin.incomes.index', ['stream' => 'royalty_bonus']))
            ->assertOk()
            ->assertSee('Nobody has earned from this stream yet.')
            ->assertDontSee('15-Day Booster 5% tier');

        // Unknown streams are not found; old ?stream= links still work
        $this->actingAs($this->admin)->get('/admin/incomes/nope')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/incomes?stream=bonus_booster')->assertOk()->assertSee('Top Earners');
    }

    public function test_admin_can_view_blockchain_operations(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.blockchain.index'))->assertOk();

        $response->assertSee('Blockchain Operations')
            ->assertSee('Live On-Chain Core Pools')
            ->assertSee('Trigger Blockchain Keeper')
            // The sync panel had no script behind it and was removed.
            ->assertDontSee('Sync On-Chain Data')
            ->assertDontSee('Reward Pool (Available)');
    }

    public function test_admin_can_view_owner_actions_with_every_widget(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.owner-actions'))->assertOk();

        foreach (['admin-owner', 'admin-pause', 'admin-payouts', 'admin-treasury', 'admin-funding', 'admin-slot', 'admin-development'] as $widget) {
            $response->assertSee('data-nx-chain="'.$widget.'"', false);
        }

        // One pause row per pausable contract, one payout row per income module.
        $response->assertSee('data-nx-contract="profitSharingSlot" data-nx-label="Profit-Sharing Slot"', false)
            ->assertSee('data-nx-contract="nftIncome" data-nx-label="NFT Daily Bonus"', false)
            ->assertSee('id="nx-chain-config"', false)
            // Setup is folded away; Pause replaces "Turn off"; monthly pools moved to the income pages.
            ->assertSee('Setup: revenue sources')
            ->assertDontSee('data-nx-action="disable"', false)
            ->assertDontSee('data-nx-action="connect"', false)
            ->assertDontSee('data-nx-chain="admin-monthly"', false);
    }

    public function test_monthly_pool_income_pages_show_the_on_chain_pool(): void
    {
        $deployment = tempnam(sys_get_temp_dir(), 'nexgrow');
        file_put_contents($deployment, json_encode(['contracts' => ['nft' => '0x9fE46736679d2D9a65F0992F2272dE9f3c7fa6e0']]));
        config(['blockchain.mode' => 'chain', 'blockchain.deployment' => $deployment]);
        Chain::flush();

        try {
            $this->actingAs($this->admin)->get(route('admin.incomes.index', ['stream' => 'royalty_bonus']))
                ->assertOk()
                ->assertSee('data-nx-chain="admin-monthly"', false)
                ->assertSee('data-nx-contract="royaltyBonus" data-nx-count="eligibleCount"', false);

            $this->actingAs($this->admin)->get(route('admin.incomes.index', ['stream' => 'monthly_loyalty_bonus']))
                ->assertOk()
                ->assertSee('data-nx-contract="monthlyLoyaltyBonus" data-nx-count="qualifierCount"', false);

            $this->actingAs($this->admin)->get(route('admin.incomes.index', ['stream' => 'referral_income']))
                ->assertOk()
                ->assertDontSee('data-nx-chain="admin-monthly"', false);
        } finally {
            @unlink($deployment);
            Chain::flush();
        }
    }

    public function test_members_cannot_open_owner_actions(): void
    {
        $this->actingAs($this->regularUser)->get(route('admin.owner-actions'))->assertForbidden();
    }
}
