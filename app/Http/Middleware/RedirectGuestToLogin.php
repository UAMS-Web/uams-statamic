<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a visitor who is not signed in to the front-end login page, and brings them
 * back to the page they asked for once they sign in (UAMS-Web/uams-statamic-archive#2519).
 *
 * The login page is the one the `logged_in` protection scheme in
 * `config/statamic/protect.php` uses. The way back is Laravel's intended URL:
 * `redirect()->guest()` stores it in the session, and Statamic's login handler
 * returns to it (`redirect()->intended()`). A `?redirect=` query would do nothing,
 * because the login form does not set `allow_request_redirect`. Laravel's own `auth`
 * middleware is not used, because it redirects to a named `login` route this
 * application does not define.
 */
class RedirectGuestToLogin
{
    /**
     * Let a signed-in user through, and redirect anyone else to the login page.
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure(Request): (Response)  $next  The rest of the pipeline.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Continue for a signed-in user
        if (User::current() !== null) {
            return $next($request);
        }

        // Resolve the login page the `logged_in` protection scheme uses
        $loginUrl = config('statamic.protect.schemes.logged_in.login_url', '/login');
        $loginUrl = is_string($loginUrl) ? $loginUrl : '/login';

        // Send the visitor there, remembering the page they asked for as the intended URL
        return redirect()->guest($loginUrl);
    }
}
