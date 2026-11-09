<?php

namespace Tests\Feature;

use App\Models\NftPackage;
use App\Models\User;
use App\Support\Chain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChainNftPageTest extends TestCase
{
    use RefreshDatabase;

    protected string $deploymentFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deploymentFile = tempnam(sys_get_temp_dir(), 'nexgrow');
        file_put_contents($this->deploymentFile, json_encode([
            'chainId' => '31337',
            'startBlock' => 7,
            'contracts' => [
                'paymentToken' => '0x5FbDB2315678afecb367f032d93F642f64180aa3',
                'nft' => '0x9fE46736679d2D9a65F0992F2272dE9f3c7fa6e0',
            ],
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

    public function test_chain_mode_shows_the_wallet_purchase_page_with_every_widget_hook(): void
    {
        $sponsor = User::factory()->create(['name' => 'Root Sponsor']);
        $sponsor->forceFill(['wallet_address' => '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266'])->save();
        $user = User::factory()->create(['sponsor_id' => $sponsor->id]);
        $user->forceFill(['wallet_address' => '0x70997970c51812dc3a010c7d01b50e0d17dc79c8'])->save();

        $response = $this->actingAs($user)->get(route('nft.index'))->assertOk();

        // Hooks used by resources/js/nexgrow/nft.js (and tests/js/chain-widgets.test.mjs).
        foreach ([
            'data-nx-chain="my-nfts"', 'data-nx-chain="packages"',
            'data-nx-field="balance"', 'data-nx-field="empty"', 'data-nx-field="list"', 'data-nx-field="item"',
            'data-nx-item="package"', 'data-nx-item="id"', 'data-nx-item="status"', 'data-nx-item="rate"',
            'data-nx-item="earned"', 'data-nx-item="paid"', 'data-nx-item="cap"', 'data-nx-item="bar"',
        ] as $hook) {
            $response->assertSee($hook, false);
        }

        foreach (array_keys(Chain::PACKAGES) as $price) {
            $response->assertSee('data-nx-action="buy" data-nx-package="'.$price.'"', false);
            $response->assertSee('data-nx-package-card="'.$price.'"', false);
        }

        $response->assertDontSee('data-nx-chain="sponsor"', false)
            ->assertDontSee('data-nx-chain="wallet-link"', false);

        // The browser gets the deployment and the sponsor's wallet.
        $config = Chain::browserConfig($user);
        $this->assertSame(7, $config['startBlock']);
        $this->assertSame('0x9fE46736679d2D9a65F0992F2272dE9f3c7fa6e0', $config['contracts']['nft']);
        $this->assertSame($sponsor->wallet_address, $config['user']['sponsorWallet']);
    }

    public function test_it_asks_to_link_a_wallet_and_hides_the_sponsor_widget_without_a_sponsor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('nft.index'))
            ->assertOk()
            ->assertSee('data-nx-chain="wallet-link"', false)
            ->assertDontSee('data-nx-chain="sponsor"', false);
    }

    public function test_it_warns_when_the_contracts_are_not_deployed(): void
    {
        config(['blockchain.deployment' => $this->deploymentFile.'.missing']);
        Chain::flush();

        $this->actingAs(User::factory()->create())->get(route('nft.index'))
            ->assertOk()
            ->assertSee('not deployed on this network yet');
    }

    public function test_database_purchases_are_refused_in_chain_mode(): void
    {
        $package = NftPackage::create([
            'name' => 'Test', 'price' => 100, 'daily_bonus' => 1, 'cap_multiplier' => 4,
            'is_active' => true, 'sort_order' => 1,
        ]);
        $user = User::factory()->create();
        $user->forceFill(['token_balance' => 1000])->save();

        $this->actingAs($user)
            ->post(route('nft.purchase'), ['nft_package_id' => $package->id])
            ->assertRedirect(route('nft.index'))
            ->assertSessionHasErrors('nft_package_id');

        $this->assertSame(0, $user->nfts()->count());
        $this->assertEquals(1000, $user->refresh()->token_balance);
    }

    public function test_database_mode_keeps_the_original_page(): void
    {
        config(['blockchain.mode' => 'database']);

        $this->actingAs(User::factory()->create())->get(route('nft.index'))
            ->assertOk()
            ->assertDontSee('data-nx-chain="packages"', false);
    }
}
