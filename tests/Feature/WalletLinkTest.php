<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WalletSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLinkTest extends TestCase
{
    use RefreshDatabase;

    // Signed with Hardhat's public test account #1 (not a real key).
    protected const ADDRESS = '0x70997970C51812dc3A010C7d01b50e0d17dc79C8';

    protected const MESSAGE = "Link this wallet to Nexgrow account NX123.\n\nNonce: abc123";

    protected const SIGNATURE = '0x16361e7f07a1211af599cb81cb31eadbf47ab92a2332a7014dd5c6955582d4b1394bfb96d050b34745202f7462bb0bfd109ef238273a1402e6d09ca448c5da031b';

    public function test_it_recovers_the_signer_of_a_message(): void
    {
        $this->assertSame(self::ADDRESS, WalletSignature::recover(self::MESSAGE, self::SIGNATURE));
        $this->assertNotSame(self::ADDRESS, WalletSignature::recover(self::MESSAGE.'x', self::SIGNATURE));
        $this->assertNull(WalletSignature::recover(self::MESSAGE, '0x1234'));
    }

    public function test_it_gives_a_one_time_message_naming_the_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('wallet.link.message'))->assertOk();

        $this->assertStringContainsString($user->user_id, $response->json('message'));
        $this->assertSame($response->json('message'), session('wallet_link_message'));
    }

    public function test_it_links_a_wallet_proven_by_signature(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['wallet_link_message' => self::MESSAGE])
            ->postJson(route('wallet.link'), ['address' => self::ADDRESS, 'signature' => self::SIGNATURE])
            ->assertOk()
            ->assertJson(['wallet_address' => strtolower(self::ADDRESS)]);

        $user->refresh();
        $this->assertSame(strtolower(self::ADDRESS), $user->wallet_address);
        $this->assertNotNull($user->wallet_linked_at);
        $this->assertNull(session('wallet_link_message'));
    }

    public function test_it_rejects_a_signature_from_another_wallet(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['wallet_link_message' => self::MESSAGE])
            ->postJson(route('wallet.link'), [
                'address' => '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
                'signature' => self::SIGNATURE,
            ])
            ->assertUnprocessable()
            ->assertJson(['message' => 'The signature does not match this wallet.']);

        $this->assertNull($user->refresh()->wallet_address);
    }

    public function test_it_rejects_a_request_without_a_fresh_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('wallet.link'), ['address' => self::ADDRESS, 'signature' => self::SIGNATURE])
            ->assertUnprocessable()
            ->assertJson(['message' => 'The link request expired. Please try again.']);
    }

    public function test_it_rejects_a_wallet_linked_to_another_account(): void
    {
        User::factory()->create()->forceFill(['wallet_address' => strtolower(self::ADDRESS)])->save();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['wallet_link_message' => self::MESSAGE])
            ->postJson(route('wallet.link'), ['address' => self::ADDRESS, 'signature' => self::SIGNATURE])
            ->assertUnprocessable()
            ->assertJson(['message' => 'This wallet is already linked to another account.']);
    }

    public function test_it_does_not_replace_an_already_linked_wallet(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['wallet_address' => '0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc'])->save();

        $this->actingAs($user)
            ->withSession(['wallet_link_message' => self::MESSAGE])
            ->postJson(route('wallet.link'), ['address' => self::ADDRESS, 'signature' => self::SIGNATURE])
            ->assertUnprocessable()
            ->assertJson(['message' => 'A wallet is already linked to this account.']);
    }

    public function test_the_wallet_page_shows_the_link_panel_and_chain_config(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('wallet'))
            ->assertOk()
            ->assertSee('data-nx-chain="wallet-link"', false)
            ->assertSee('id="nx-chain-config"', false)
            ->assertSee('Connect &amp; link wallet', false);
    }
}
