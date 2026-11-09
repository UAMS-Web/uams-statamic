<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Entries\PrivateEntryAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Statamic\Entries\Entry;
use Statamic\Facades\Data;
use Statamic\StaticCaching\Middleware\Cache;
use Statamic\Structures\Page;
use Symfony\Component\HttpFoundation\Response;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\PasswordVerifier;

/**
 * Middleware to protect Statamic entries by status/visibility (UAMS-Web/uams-statamic-archive#223).
 *
 * Enforces two `uamsweb_common_status` behaviors on the front end:
 *
 *   - **`private`** — an entry that is live (`published`) but restricted to
 *     privileged users; it 404s for guests and users without edit rights on its
 *     collection, so it never leaks to the public while staying reachable for
 *     admins/editors.
 *   - **`password_protected`** — verifies a user-entered password against the
 *     entry's stored password, allowing access only when correct and caching
 *     success in the session to avoid repeated prompts. Password submissions are
 *     throttled per client address and entry, so a password cannot be guessed
 *     by repeated posting.
 *
 * Every response for a gated entry carries the header Statamic's static cache
 * reads ({@see Cache}): `X-Statamic-Private` for a `private` entry and
 * `X-Statamic-Protected` for a password-protected one. The cache runs before this
 * middleware and keys on the URL alone, so without the header an editor's view of
 * a private entry, or one visitor's unlocked page, would be stored and served to
 * everyone at that URL.
 */
class ProtectEntry
{
    /**
     * Password submissions allowed per client address and entry within
     * {@see self::PASSWORD_ATTEMPT_DECAY_SECONDS}.
     */
    private const int MAX_PASSWORD_ATTEMPTS = 10;

    /**
     * How long a client's password-attempt count is kept, in seconds.
     */
    private const int PASSWORD_ATTEMPT_DECAY_SECONDS = 60;

    /**
     * Create a new middleware instance.
     *
     * @param  PasswordVerifier  $passwordVerifier  The password verifier service
     */
    public function __construct(
        /**
         * Password verifier service instance
         */
        protected PasswordVerifier $passwordVerifier,
        /**
         * The shared private-entry visibility rule (mirrored by the nav — UAMS-Web/uams-statamic-archive#1152)
         */
        protected PrivateEntryAccess $privateEntryAccess
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the chain
     * @return Response The response
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        // Resolve the entry being requested. The custom `entries/{entry}` route
        // binds it as a route parameter, but the data-driven frontend catch-all
        // (which serves entries at their canonical permalink) does not — there the
        // parameter is `segments` and `parameter('entry')` is null. Fall back to
        // resolving the entry from the request URL, the same way
        // FrontendController@index does, so protection applies on canonical URLs.
        $entry = $request->route()?->parameter('entry');

        if (! $entry instanceof Entry) {
            $resolved = Data::findByRequestUrl($request->url());

            // Structured collections (e.g. `pages`) resolve to a Page wrapper
            // rather than the Entry itself; unwrap it so the password check, the
            // session key (`$entry->id()`), and PasswordVerifier act on the entry.
            $entry = $resolved instanceof Page ? $resolved->entry() : $resolved;
        }

        // A `private` entry is live but restricted: hide it (404) from anyone who
        // cannot publish its collection, while leaving it reachable for
        // admins/editors. `value()` (not `get()`) so a secondary-site localization
        // resolves the origin's value — the status field is `localizable: false`,
        // so a localization has no own copy and `get()` would return null and leak.
        $private = false;

        if ($entry instanceof Entry && $entry->value('status_and_visibility') === 'private') {
            $private = true;

            if (! $this->privateEntryAccess->canView($entry)) {
                // The header keeps the half-measure cache, which stores 404s, from
                // pinning this refusal at a URL an editor may open.
                abort(404, '', ['X-Statamic-Private' => 'true']);
            }
        }

        // Check if the entry requires password protection (origin-fallback value,
        // like the private check above, so a localization is gated too).
        if (
            ! $entry instanceof Entry
            || ! $entry->value('password_protected')
        ) {
            // Allow access if protection is not needed
            /** @var Response $response */
            $response = $next($request);

            return $this->uncacheable($response, $private, false);
        }

        // Get the entry ID for session key generation
        $id = $entry->id();
        $entryId = is_scalar($id) ? (string) $id : '';

        // Generate the session key for this entry
        $sessionKey = 'entry_password_verified_'.$entryId;

        // Check if the entry is already verified in the session
        if (Session::get($sessionKey)) {
            // Allow access since password was already verified
            /** @var Response $response */
            $response = $next($request);

            return $this->uncacheable($response, $private, true);
        }

        // Check if this is a password submission request
        if (
            $request->isMethod('post')
            && $request->has('password')
        ) {
            // Count submissions per client address and entry, and refuse the
            // whole window once the cap is reached, before any password is
            // compared, so the stored password cannot be guessed by posting.
            $attemptKey = self::passwordAttemptKey($entryId, (string) $request->ip());

            if (RateLimiter::tooManyAttempts($attemptKey, self::MAX_PASSWORD_ATTEMPTS)) {
                $retryAfter = RateLimiter::availableIn($attemptKey);

                return $this->uncacheable(
                    $this->prompt($entry, sprintf('Too many password attempts. Try again in %d seconds.', $retryAfter))
                        ->setStatusCode(Response::HTTP_TOO_MANY_REQUESTS)
                        ->header('Retry-After', (string) $retryAfter),
                    $private,
                    true
                );
            }

            RateLimiter::hit($attemptKey, self::PASSWORD_ATTEMPT_DECAY_SECONDS);

            // Get the submitted password from the request
            $submittedPassword = $request->input('password');
            $inputPassword = is_scalar($submittedPassword) ? (string) $submittedPassword : '';

            // Verify the submitted password against the entry's password. The
            // verifier reads `visibility_password` / `is_password_hashed` with
            // `get()` (own data only), so it must be handed the entry that OWNS
            // them — see passwordFieldOwner() (UAMS-Web/uams-statamic-archive#1884).
            if (
                $this->passwordVerifier->verify(
                    $this->passwordFieldOwner($entry),
                    $inputPassword
                )
            ) {
                // A correct password ends the window for this client and entry
                RateLimiter::clear($attemptKey);

                // Store successful verification in the session
                Session::put(
                    $sessionKey,
                    true
                );

                // Redirect to clear the POST data and show the protected content
                return redirect($request->url());
            }

            // Redirect back with an error if password is invalid
            return redirect()->back()->withErrors(['password' => 'Invalid password']);
        }

        // Render password prompt view
        return $this->uncacheable($this->prompt($entry), $private, true);
    }

    /**
     * The rate-limiter key counting one client's password submissions for one entry.
     *
     * @param  string  $entryId  The gated entry's id
     * @param  string  $ip  The client address the submissions come from
     * @return string The key {@see RateLimiter} counts under
     */
    public static function passwordAttemptKey(string $entryId, string $ip): string
    {
        return 'entry_password_attempts|'.$entryId.'|'.$ip;
    }

    /**
     * Render the password prompt for an entry, with an error line when given one.
     *
     * The error is handed to the view directly rather than flashed to the session,
     * so a refused submission is answered in the same response and never redirects
     * into another counted attempt.
     *
     * @param  Entry  $entry  The password-protected entry
     * @param  string|null  $error  The message to show above the form, if any
     * @return HttpResponse The rendered prompt
     */
    protected function prompt(Entry $entry, ?string $error = null): HttpResponse
    {
        $data = ['entry' => $entry];

        if ($error !== null) {
            $errors = new ViewErrorBag;
            $errors->put('default', new MessageBag(['password' => [$error]]));
            $data['errors'] = $errors;
        }

        return response()->view('password_prompt', $data);
    }

    /**
     * Mark a gated entry's response so Statamic's static cache skips it.
     *
     * {@see Cache} stores any 200 (and, under the half measure, 404) that carries
     * none of its skip headers, and serves the stored copy before this middleware
     * runs. The headers are the ones Statamic's own responses set for the same
     * two cases, so every strategy already honors them.
     *
     * @param  Response  $response  The response about to be returned
     * @param  bool  $private  Whether the entry's status is `private`
     * @param  bool  $protected  Whether the entry is password-protected
     * @return Response The same response, with the matching headers set
     */
    protected function uncacheable(Response $response, bool $private, bool $protected): Response
    {
        if ($private) {
            $response->headers->set('X-Statamic-Private', 'true');
        }

        if ($protected) {
            $response->headers->set('X-Statamic-Protected', 'true');
        }

        return $response;
    }

    /**
     * Resolve the entry that owns the stored password fields — the root of the
     * localization chain.
     *
     * `visibility_password` and `is_password_hashed` are `localizable: false` in
     * `uamsweb_common_status`, so a secondary-site localization holds no own copy
     * of either. PasswordVerifier reads both with `get()`, which is own-data-only,
     * so handing it the localization returns null for the stored password, trips
     * the verifier's `is_string()` guard, and rejects every submission — including
     * the correct password (UAMS-Web/uams-statamic-archive#1884). The prompt still appears, because handle()
     * gates on `value()`, which does fall back to the origin; that asymmetry is
     * the defect. Passing the root origin gives verification the same origin
     * semantics the prompt decision already has.
     *
     * @param  Entry  $entry  The entry being served, possibly a localization
     * @return Entry The root origin, or the entry itself when it is already the root
     */
    protected function passwordFieldOwner(Entry $entry): Entry
    {
        $owner = $entry;

        // Track visited ids so a self-referencing or cyclic `origin` in stored
        // content cannot spin this loop forever on a public request.
        $visited = [];

        while (true) {
            $id = $owner->id();

            if (is_scalar($id)) {
                if (isset($visited[(string) $id])) {
                    break;
                }

                $visited[(string) $id] = true;
            }

            $origin = $owner->origin();

            if (! $origin instanceof Entry) {
                break;
            }

            $owner = $origin;
        }

        return $owner;
    }
}
