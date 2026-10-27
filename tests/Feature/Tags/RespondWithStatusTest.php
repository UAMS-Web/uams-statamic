<?php

declare(strict_types=1);

use App\Exceptions\SiteHttpException;
use App\Tags\RespondWithStatus;
use Illuminate\Support\Facades\File;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Tags\Parameters;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Coverage for the {{ respond_with_status }} tag (UAMS-Web/uams-statamic-archive#1641).
 *
 * The stock {{ redirect }} tag cannot emit a targetless error status, so the
 * migration "410 Gone" disposition for retired content needs this tag. It aborts
 * with the rule's response code — but only for the small set of allowed targetless
 * error codes, so a misconfigured rule can never abort with an arbitrary status.
 *
 * Mutation: 100% on the killable set (13 mutants, `--covered-only`). Four
 * survivors are equivalent and are left alone rather than chased: the
 * `IncrementInteger`/`DecrementInteger` pair on the `0` fallback for a
 * non-numeric `code` (`-1` and `1` are just as absent from `ALLOWED_CODES`), and
 * the two `TrueToFalse` flips of `in_array()`'s strict flag — both arrays hold
 * ints and both subjects are cast to int, so loose comparison decides the same
 * way. A test that pinned any of them would assert an implementation detail that
 * cannot change behavior.
 *
 * @command  php artisan test --compact tests/Feature/Tags/RespondWithStatusTest.php
 */

/**
 * Build a RespondWithStatus tag whose params return the given values.
 *
 * @param  array<string, mixed>  $params
 */
function respondWithStatusTag(array $params): RespondWithStatus
{
    $parameters = Mockery::mock(Parameters::class);
    $parameters->shouldReceive('get')
        ->andReturnUsing(fn (string $key, mixed $default = null) => $params[$key] ?? $default);

    $tag = new RespondWithStatus;

    // The params property is private on Statamic\Tags\Tags — set it via reflection.
    $reflection = new ReflectionClass($tag);
    $property = $reflection->getProperty('params');
    $property->setValue($tag, $parameters);

    return $tag;
}

it('aborts with the 410 status for a gone rule', function (): void {
    try {
        respondWithStatusTag(['code' => '410', 'message' => 'Gone'])->index();
        $this->fail('Expected the tag to abort with a 410.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(410)
            ->and($e->getMessage())->toBe('Gone');
    }
});

it('aborts with the 403 status for a forbidden rule', function (): void {
    try {
        respondWithStatusTag(['code' => '403'])->index();
        $this->fail('Expected the tag to abort with a 403.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('raises a site-rendered exception for the targetless codes', function (string $code, int $status): void {
    // The status alone was never the defect (UAMS-Web/uams-statamic-archive#1641 has been serving those
    // correctly all along) — it is WHICH exception carries it. A bare
    // `HttpException` has no `render()`, so Laravel answers from
    // `convertExceptionToResponse()`: the generic page, message discarded.
    // `SiteHttpException` carries Statamic's renderer, which is what puts the
    // message on the site's own page (UAMS-Web/uams-statamic-archive#1942).
    try {
        respondWithStatusTag(['code' => $code, 'message' => 'Because.'])->index();
        $this->fail('Expected the tag to raise a '.$status.'.');
    } catch (HttpException $e) {
        expect($e)->toBeInstanceOf(SiteHttpException::class)
            ->and($e->getStatusCode())->toBe($status)
            ->and($e->getMessage())->toBe('Because.');
    }
})->with([
    'forbidden' => ['403', 403],
    'gone' => ['410', 410],
    'legal reasons' => ['451', 451],
]);

it('does not raise a site-rendered exception for a 404', function (): void {
    // Deliberate, and about recursion rather than taste: `errors/404.antlers.html`
    // includes `errors/_redirects`, so rendering it through Statamic's view would
    // re-run the rule that raised the error, with nothing to bottom out on. A bare
    // `abort(404)` stops at depth two inside Laravel's try/catch. The partial's
    // `404` branch is inert for the same reason (UAMS-Web/uams-statamic-archive#1934); this keeps the exclusion
    // true for any future caller rather than resting on that branch staying inert.
    try {
        respondWithStatusTag(['code' => '404', 'message' => 'Retired.'])->index();
        $this->fail('Expected the tag to abort with a 404.');
    } catch (HttpException $e) {
        expect($e)->toBeInstanceOf(NotFoundHttpException::class)
            ->and($e)->not->toBeInstanceOf(SiteHttpException::class)
            ->and($e->getStatusCode())->toBe(404);
    }
});

it('carries an empty message when the rule has none, rather than inventing one', function (string|int|null $message): void {
    // The message is the whole point of UAMS-Web/uams-statamic-archive#1942, so its default is load-bearing: a
    // rule with no message must reach the error page with an empty string, which
    // is what makes the page fall back to its own sentence. A non-string param
    // (an integer typed into the CP, a null) resolves the same way rather than
    // reaching the exception as a non-string.
    $params = ['code' => '410'];

    if ($message !== null) {
        $params['message'] = $message;
    }

    try {
        respondWithStatusTag($params)->index();
        $this->fail('Expected the tag to raise a 410.');
    } catch (HttpException $e) {
        expect($e->getMessage())->toBe('');
    }
})->with([
    'absent' => [null],
    'empty string' => [''],
    'a non-string value' => [404],
]);

it('does not abort for a redirect (non-error) response code', function (): void {
    // A 301 is the {{ redirect }} tag's job — this tag must leave it alone. Asserted
    // through throwableFrom() rather than `->not->toThrow(HttpException::class)`,
    // which passes on any throwable that is not an HttpException and so would stay
    // green on a TypeError the bare call would have surfaced (UAMS-Web/uams-statamic-archive#1759).
    expect(throwableFrom(fn () => respondWithStatusTag(['code' => '301'])->index()))->toBeNull();
});

it('does not abort for a non-numeric or missing code', function (): void {
    expect(throwableFrom(fn () => respondWithStatusTag(['code' => 'nonsense'])->index()))->toBeNull()
        ->and(throwableFrom(fn () => respondWithStatusTag([])->index()))->toBeNull();
});

it('wires the targetless error branch ahead of the move branches in the 404 partial', function (): void {
    $view = File::get(resource_path('views/errors/_redirects.antlers.html'));

    expect($view)
        ->toContain('respond_with_status')
        ->toContain("response == '410'");

    // The error branch must be evaluated before the entry/url move branches, so a
    // 410 aborts rather than falling through to a (targetless) redirect no-op.
    /** @var int<0, max> $errorBranch */
    $errorBranch = strpos($view, 'respond_with_status');
    /** @var int<0, max> $moveBranch */
    $moveBranch = strpos($view, 'target_type ==');

    expect($errorBranch)->toBeLessThan($moveBranch);
});
