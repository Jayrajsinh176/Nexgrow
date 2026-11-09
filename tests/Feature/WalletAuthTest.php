<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WalletSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_message_generates_challenge_nonce(): void
    {
        $response = $this->get(route('wallet.auth.message'));

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $message = $response->json('message');
        $this->assertStringContainsString('Sign in to Nexgrow', $message);
        $this->assertStringContainsString('Nonce:', $message);
    }

    public function test_member_auth_fails_without_challenge(): void
    {
        $response = $this->postJson(route('wallet.auth'), [
            'address' => '0x1234567890123456789012345678901234567890',
            'signature' => '0x' . str_repeat('a', 130),
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'The sign-in request expired or is invalid. Please try connecting again.']);
    }

    public function test_profile_page_renders_and_updates_name(): void
    {
        $user = User::factory()->create([
            'name' => null,
            'wallet_address' => '0x71c6986a7fe3a483acfb77a8085a369722b4e9e6',
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));
        $response->assertOk()
            ->assertSee($user->user_id)
            ->assertSee('Web3 Account')
            ->assertSee('Profile Details');

        $updateResponse = $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Satoshi Nakamoto',
        ]);

        $updateResponse->assertRedirect(route('profile.edit'));
        $this->assertEquals('Satoshi Nakamoto', $user->fresh()->name);
    }

    public function test_admin_config_wallets_grant_admin_access(): void
    {
        $adminWallet = '0x9999999999999999999999999999999999999999';
        config(['admin.wallets' => [$adminWallet]]);

        $user = User::factory()->create([
            'wallet_address' => $adminWallet,
            'is_admin' => false,
        ]);

        $this->assertTrue($user->isAdmin());
    }
}

