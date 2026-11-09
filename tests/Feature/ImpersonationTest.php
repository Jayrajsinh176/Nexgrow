<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->member = User::factory()->create(['name' => 'Jayraj Ten', 'user_id' => 'NEX00091']);
    }

    public function test_admin_views_the_app_as_a_member_and_returns(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->member))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->member);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('you are viewing as')
            ->assertSee('NEX00091')
            ->assertSee('Return to admin');

        $this->post(route('impersonate.stop'))
            ->assertRedirect(route('admin.users.show', $this->member));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertFalse(session()->has(ImpersonationController::SESSION_KEY));
    }

    public function test_the_user_pages_offer_view_for_members_only(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.impersonate', $this->member))
            ->assertDontSee(route('admin.users.impersonate', $this->admin));

        $this->get(route('admin.users.show', $this->member))
            ->assertOk()
            ->assertSee('View as member');
    }

    public function test_viewing_keeps_the_csrf_token_so_other_tabs_still_work(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['_token' => 'token-of-open-tabs'])
            ->post(route('admin.users.impersonate', $this->member));
        $this->assertSame('token-of-open-tabs', session()->token());

        $this->actingAs($this->member)
            ->withSession(['_token' => 'token-of-open-tabs', ImpersonationController::SESSION_KEY => $this->admin->id])
            ->post(route('impersonate.stop'));
        $this->assertSame('token-of-open-tabs', session()->token());
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_admin_pages_open_the_member_view_while_viewing(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.impersonate', $this->member));

        $this->get(route('admin.users.index'))->assertRedirect(route('dashboard'));
    }

    public function test_view_buttons_open_a_new_tab(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertSee('action="'.route('admin.users.impersonate', $this->member).'" target="_blank"', false);
    }

    public function test_admins_cannot_be_viewed_as_members(): void
    {
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $otherAdmin))
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_members_cannot_view_as_other_members(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->member)
            ->post(route('admin.users.impersonate', $other))
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->member);
    }

    public function test_account_changes_are_blocked_while_viewing(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.impersonate', $this->member));

        $this->getJson(route('wallet.link.message'))->assertForbidden();

        $this->postJson(route('wallet.link'), ['address' => '0x0', 'signature' => '0x0'])
            ->assertForbidden()
            ->assertJson(['message' => 'You are viewing as this member. Return to admin to make changes.']);

        $this->from(route('nft.index'))
            ->post(route('nft.purchase'), ['nft_package_id' => 1])
            ->assertRedirect(route('nft.index'))
            ->assertSessionHasErrors('impersonation');

        $this->assertNull($this->member->fresh()->wallet_address);
    }

    public function test_return_without_an_admin_in_the_session_signs_out(): void
    {
        $this->actingAs($this->member)
            ->withSession([ImpersonationController::SESSION_KEY => $this->member->id])
            ->post(route('impersonate.stop'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }
}
