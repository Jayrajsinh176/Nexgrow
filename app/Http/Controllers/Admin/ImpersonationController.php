<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Lets an admin view the app as a member: the admin is signed in as that
 * member, and the admin's ID is kept in the session so they can return.
 * While viewing, actions that change the member's account are blocked
 * (see App\Http\Middleware\BlockWhileImpersonating).
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function start(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_admin, 403, 'Administrators cannot be viewed as members.');

        $admin = $request->user();

        Log::info('Admin started viewing as member', ['admin' => $admin->user_id, 'member' => $user->user_id]);

        $this->switchUser($request, $user);
        $request->session()->put(self::SESSION_KEY, $admin->id);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $member = $request->user();
        $admin = User::find($request->session()->pull(self::SESSION_KEY));

        if (! $admin?->is_admin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        Log::info('Admin stopped viewing as member', ['admin' => $admin->user_id, 'member' => $member->user_id]);

        $this->switchUser($request, $admin);

        return redirect()->route('admin.users.show', $member);
    }

    // Auth::login() gives the session a new ID and a new CSRF token. The token
    // is put back so forms in the admin's other open tabs (e.g. Log out) still
    // work instead of failing with 419 Page Expired.
    protected function switchUser(Request $request, User $user): void
    {
        $token = $request->session()->token();

        Auth::login($user);

        $request->session()->put('_token', $token);
    }
}
