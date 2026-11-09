<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks actions that change a member's account while an admin is viewing
 * the app as that member.
 */
class BlockWhileImpersonating
{
    public const MESSAGE = 'You are viewing as this member. Return to admin to make changes.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(ImpersonationController::SESSION_KEY)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 403);
        }

        return back()->withErrors(['impersonation' => self::MESSAGE]);
    }
}
