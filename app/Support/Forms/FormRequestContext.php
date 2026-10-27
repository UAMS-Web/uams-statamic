<?php

declare(strict_types=1);

namespace App\Support\Forms;

use App\Listeners\CaptureFormRequestContext;
use App\Tags\GetParam;
use App\Tags\GlobalOrigin;
use App\Tags\Ip;
use App\Tags\Referrer;
use UAMSWeb\FormSettings\Jobs\SendFormNotification;
use UAMSWeb\MultisiteForms\Sites\RequestSite;

/**
 * Carries the submitter's live request context across the form-email queue boundary.
 *
 * The four request-context merge tags ({@see Ip}, {@see Referrer}, {@see GetParam},
 * {@see GlobalOrigin}) read live request state ($_SERVER, request(), Site::current())
 * at Antlers-render time. That only resolves to the submitter's values while the email
 * renders inside the submit request — i.e. on the `sync` queue connection. On a real
 * queue (database/redis/sqs) a worker renders the email later with no submitter request,
 * so the tags would read the worker's (empty/default) state.
 *
 * This value object closes that gap. It is captured from the live request when the form
 * is submitted ({@see CaptureFormRequestContext}, listening for
 * `Statamic\Events\FormSubmitted`), threaded onto the queued send-email job as a plain
 * serializable array ({@see SendFormNotification}, which reaches it through
 * {@see FormRequestSubmissionContext}), and re-activated by that job around the
 * `Mail::send()` render so the tags can prefer the stored submitter value over
 * live worker state.
 *
 * The captured context lives *only* in the email render path. It is never written to the
 * submission record, so it cannot leak into `{{ all_fields }}` or the stored YAML.
 */
final class FormRequestContext
{
    /**
     * The context active for the email render currently in progress, if any.
     *
     * Set by the send-email job immediately before `Mail::send()` and cleared
     * immediately after, so the four request-context tags read the submitter's
     * captured values instead of live worker state during that render. `null`
     * means no captured render is in progress, so the tags fall back to live
     * request state (normal page/template use, or the `sync` connection).
     */
    private static ?self $active = null;

    /**
     * Captured contexts awaiting pickup by their send-email job, keyed by submission id.
     *
     * The FormSubmitted listener captures the live request context here while the
     * submit request is still in scope. Statamic then builds one send-email job per
     * notification, moments later in the same request and before any serialization,
     * and each job reads the entry by submission id with {@see self::peek()} and
     * threads it onto itself. Every notification of the submission therefore carries
     * the context. The map holds at most one submission: stashing the next submission
     * replaces it, so it cannot grow in a long-running process.
     *
     * @var array<string, self>
     */
    private static array $pending = [];

    /**
     * @param  string  $ip  The submitter's IP address (REMOTE_ADDR) at submit time.
     * @param  string  $referrer  The submitter's `Referer` header at submit time.
     * @param  array<string, mixed>  $query  The submit request's query parameters.
     * @param  string  $siteHandle  The handle of the site the form was submitted from.
     */
    public function __construct(
        public readonly string $ip,
        public readonly string $referrer,
        public readonly array $query,
        public readonly string $siteHandle,
    ) {}

    /**
     * Capture the current request context from live request and site state.
     *
     * Called while the submit request is still live (from the FormSubmitted
     * listener), so `request()` and the referrer reflect the submitter.
     */
    public static function capture(): self
    {
        // Read the submitter's IP, defaulting to '' when no request resolves an address
        $ip = request()->ip() ?? '';

        // Read the submitter's Referer header, defaulting to '' when absent
        $referrer = request()->header('Referer', '');

        // Snapshot the submit request's query parameters as a plain array
        /** @var array<string, mixed> $query */
        $query = request()->query();

        // Record the handle of the site the form was submitted from, which the
        // multisite forms add-on names from the referrer (the submit endpoint
        // itself is under the default site's prefix)
        $site = app(RequestSite::class)->forSubmission();
        $siteHandle = $site->handle();
        $siteHandle = \is_scalar($siteHandle) ? (string) $siteHandle : '';

        return new self($ip, $referrer, $query, $siteHandle);
    }

    /**
     * Stash a captured context for a submission so its send-email jobs can read it.
     *
     * Called from the FormSubmitted listener with the live-captured context. The
     * send-email jobs, built immediately afterwards in the same request, read it with
     * {@see self::peek()}. Any earlier submission's entry is dropped: its jobs were
     * built in its own submit request, before this submission arrived.
     *
     * @param  string  $submissionId  The submission the context was captured for.
     */
    public static function stash(string $submissionId, self $context): void
    {
        // Hold the captured context until the submission's jobs read it, replacing any
        // earlier submission's entry so the map holds one submission at most
        self::$pending = [$submissionId => $context];
    }

    /**
     * Read a submission's stashed context without removing it, or `null` if none
     * was stashed.
     *
     * Each send-email job of a submission reads the context this way, so a form
     * with several notifications hands the context to every one of them.
     *
     * @param  string  $submissionId  The submission whose context to read.
     */
    public static function peek(string $submissionId): ?self
    {
        return self::$pending[$submissionId] ?? null;
    }

    /**
     * Claim and remove a submission's stashed context, or `null` if none was stashed.
     *
     * Clears the entry once nothing more needs it, such as a test that has read
     * the context the submission captured.
     *
     * @param  string  $submissionId  The submission whose context to claim.
     */
    public static function pull(string $submissionId): ?self
    {
        // Take the stashed context, if any, leaving the map clean
        $context = self::$pending[$submissionId] ?? null;
        unset(self::$pending[$submissionId]);

        return $context;
    }

    /**
     * Rebuild a context instance from its serialized array form.
     *
     * The send-email job stores the captured context as a plain array (so it
     * serializes cleanly onto the queued job); this reconstitutes the object on
     * the worker before the render.
     *
     * @param  array<string, mixed>  $data  The array toArray() produced, as read back
     *                                      from a queued job; each key is narrowed.
     */
    public static function fromArray(array $data): self
    {
        // Narrow each key back to its declared type, tolerating a malformed payload
        $ip = \is_string($data['ip'] ?? null) ? $data['ip'] : '';
        $referrer = \is_string($data['referrer'] ?? null) ? $data['referrer'] : '';
        $query = [];

        // Copy the query parameters, keyed by name, when the payload carries them
        if (\is_array($data['query'] ?? null)) {
            foreach ($data['query'] as $name => $value) {
                $query[(string) $name] = $value;
            }
        }

        $siteHandle = \is_string($data['site'] ?? null) ? $data['site'] : '';

        return new self($ip, $referrer, $query, $siteHandle);
    }

    /**
     * Reduce the context to a plain, queue-serializable array.
     *
     * @return array{ip: string, referrer: string, query: array<string, mixed>, site: string}
     */
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'referrer' => $this->referrer,
            'query' => $this->query,
            'site' => $this->siteHandle,
        ];
    }

    /**
     * Run a callback with this context active for request-context tag resolution.
     *
     * Sets the active context (so the four tags prefer the captured submitter
     * values), runs the render, and always restores the previous active context —
     * even if the render throws — so an activation can never leak into an unrelated
     * later render in the same process.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback  The render to run with this context active.
     * @return TReturn
     */
    public function during(callable $callback): mixed
    {
        // Remember any context already active so nesting restores it (defensive)
        $previous = self::$active;

        // Make this captured context the one the request-context tags read
        self::$active = $this;

        try {
            // Run the email render while this context is active
            return $callback();
        } finally {
            // Always restore the prior active context, even on a render exception
            self::$active = $previous;
        }
    }

    /**
     * The context active for the in-progress email render, or `null` if none.
     *
     * Request-context tags call this first: a non-null result means a captured
     * render is in progress and the tag should prefer the stored value; `null`
     * means fall back to live request state.
     */
    public static function active(): ?self
    {
        return self::$active;
    }
}
