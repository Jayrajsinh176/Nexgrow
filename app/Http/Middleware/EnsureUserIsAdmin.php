<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('admin.login'));
        }

        // An admin viewing as a member (possibly in another tab) shares this
        // session; send them to the member view, whose banner returns to admin.
        if (! $request->user()->isAdmin() && $request->session()->has(ImpersonationController::SESSION_KEY)) {
            return redirect()->route('dashboard');
        }

        abort_unless($request->user()->isAdmin(), 403, 'Unauthorized. Administrator access required.');

        return $next($request);
    }
}
