<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Chain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChainPagesTest extends TestCase
{
    use RefreshDatabase;

    protected string $deploymentFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deploymentFile = tempnam(sys_get_temp_dir(), 'nexgrow');
        file_put_contents($this->deploymentFile, json_encode([
            'startBlock' => 0,
            'contracts' => ['nft' => '0x9fE46736679d2D9a65F0992F2272dE9f3c7fa6e0'],
        ]));

        config(['blockchain.mode' => 'chain', 'blockchain.deployment' => $this->deploymentFile]);
        Chain::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->deploymentFile);
        Chain::flush();

        parent::tearDown();
    }

    protected function linkedUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['wallet_address' => '0x70997970c51812dc3a010c7d01b50e0d17dc79c8'])->save();

        return $user;
    }

    public function test_every_income_page_reads_its_contract_in_chain_mode(): void
    {
        $user = $this->linkedUser();

        foreach (Chain::INCOME as $route => $income) {
            $response = $this->actingAs($user)->get(route($route))->assertOk();

            // Hooks used by resources/js/nexgrow/income.js.
            $response->assertSee('data-nx-chain="income" data-nx-contract="'.$income['contract'].'"', false)
                ->assertSee($income['title'])
                ->assertSee($income['description']);

            foreach (['credited-hero', 'credited', 'paid', 'pending', 'status', 'credits', 'credits-empty', 'payouts', 'payouts-empty'] as $field) {
                $response->assertSee('data-nx-field="'.$field.'"', false);
            }

            foreach ($income['columns'] as $column) {
                $response->assertSee('<th class="px-6 py-3 font-semibold">'.e($column).'</th>', false);
            }
        }
    }

    public function test_income_pages_keep_their_database_view_in_database_mode(): void
    {
        config(['blockchain.mode' => 'database']);
        $user = $this->linkedUser();

        foreach (array_keys(Chain::INCOME) as $route) {
            $this->actingAs($user)->get(route($route))
                ->assertOk()
                ->assertDontSee('data-nx-chain="income"', false);
        }
    }

    public function test_the_slot_page_uses_the_wallet_in_chain_mode(): void
    {
        $response = $this->actingAs($this->linkedUser())->get(route('profit-sharing-slot.index'))->assertOk();

        // Hooks used by resources/js/nexgrow/slot.js.
        $response->assertSee('data-nx-chain="slot"', false)
            ->assertSee('data-nx-action="buy"', false)
            ->assertSee('data-nx-action="withdraw"', false);

        foreach (['remaining', 'buy-panel', 'slot-panel', 'slot-id', 'slot-status', 'purchased', 'days', 'profit', 'total', 'deduction', 'payout', 'message'] as $field) {
            $response->assertSee('data-nx-field="'.$field.'"', false);
        }
    }

    public function test_database_slot_actions_are_refused_in_chain_mode(): void
    {
        $user = $this->linkedUser();
        $user->forceFill(['token_balance' => 5000])->save();

        $this->actingAs($user)->post(route('profit-sharing-slot.purchase'))
            ->assertRedirect(route('profit-sharing-slot.index'))
            ->assertSessionHasErrors('slot');

        $this->assertSame(0, $user->profitSharingSlots()->count());
        $this->assertEquals(5000, $user->refresh()->token_balance);
    }

    public function test_the_dashboard_shows_on_chain_income_for_every_module(): void
    {
        $linked = $this->linkedUser();
        $response = $this->actingAs($linked)->get(route('dashboard'))->assertOk();

        // Hooks used by resources/js/nexgrow/income.js (widget "income-summary").
        $response->assertSee('data-nx-chain="income-summary"', false)
            ->assertSee('data-nx-row="nftIncome"', false);

        foreach (Chain::INCOME as $income) {
            $response->assertSee('data-nx-row="'.$income['contract'].'"', false);
        }

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-nx-chain="wallet-link"', false)
            ->assertDontSee('data-nx-chain="income-summary"', false);

        config(['blockchain.mode' => 'database']);

        $this->actingAs($linked)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-nx-chain="income-summary"', false);
    }

    public function test_database_payout_commands_do_nothing_in_chain_mode(): void
    {
        foreach ([
            'app:pay-daily-nft-bonus', 'app:process-bonus-boosters', 'app:process-team-performance-bonuses',
            'app:process-monthly-loyalty-bonus', 'app:process-royalty-bonus', 'app:process-team-development-bonus',
        ] as $command) {
            $this->artisan($command)
                ->expectsOutput('Skipped: income is paid on-chain (BLOCKCHAIN_MODE=chain).')
                ->assertSuccessful();
        }
    }

    public function test_the_team_page_shows_packages_and_chain_hooks(): void
    {
        $sponsor = $this->linkedUser();
        $member = User::factory()->create(['sponsor_id' => $sponsor->id]);
        $member->forceFill(['wallet_address' => '0x86451c43b722f4f49a1134c1263cd1e5ce70fb03'])->save();

        $response = $this->actingAs($sponsor)->get(route('team'))->assertOk();

        $response->assertSee('data-nx-chain="team"', false)
            ->assertSee('data-nx-member-wallet="0x86451c43b722f4f49a1134c1263cd1e5ce70fb03"', false)
            ->assertSee('data-nx-field="package"', false)
            ->assertSee('data-nx-field="active-members"', false)
            ->assertSee('Package');
    }
}
