<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Terminate the session of a user that was deactivated while signed in.
     *
     * Login itself is guarded in FortifyServiceProvider, but that only covers the
     * password flow: this catches passkey logins, "remember me" cookies and sessions
     * that were already open when the admin deactivated the account.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('This account has been deactivated.'),
            ]);
        }

        return $next($request);
    }
}
