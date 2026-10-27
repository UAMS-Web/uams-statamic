<?php

declare(strict_types=1);

use App\Services\Redirects\StructuralRedirectSynthesizer;
use App\Support\Redirects\MigrationRedirect;

/**
 * Unit coverage for {@see StructuralRedirectSynthesizer} (UAMS-Web/uams-statamic-archive#1657).
 *
 * The synthesizer is deliberately free of Statamic, so every URL-shape rule is
 * exercised here against synthetic inputs — the primitives the generator reads off
 * an entity carrying the importer-stamped metadata (`wordpress_post_id`,
 * `wordpress_author_login`, `wordpress_attachment_page_url`, the entry date).
 * The catch-all patterns are asserted by matching them against real request paths,
 * so a pattern that over- or under-matches fails here rather than in production.
 *
 * @command  php artisan test --compact tests/Unit/Services/Redirects/StructuralRedirectSynthesizerTest.php
 */

/**
 * A disposition that records the path and 410s it, standing in for a site with no
 * posts index to land archives on.
 */
function goneDisposition(): Closure
{
    return static fn (string $path, string $notes): MigrationRedirect => MigrationRedirect::gone($path, $notes);
}

/**
 * A disposition that 301s every archive path to a posts index.
 */
function indexDisposition(string $index = '/news'): Closure
{
    return static fn (string $path, string $notes): MigrationRedirect => MigrationRedirect::moved($path, $index, $notes);
}

/**
 * Unwrap a planned redirect, failing loudly when the synthesizer planned nothing.
 *
 * The "did it plan anything?" cases assert `toBeNull()` directly; this is for the
 * cases that go on to inspect the plan, so a null there fails as a missing plan
 * rather than as a confusing property access.
 */
function planned(?MigrationRedirect $plan): MigrationRedirect
{
    if (! $plan instanceof MigrationRedirect) {
        throw new RuntimeException('Expected the synthesizer to plan a redirect, but it planned none.');
    }

    return $plan;
}

it('plans a 301 from the ugly permalink to the entry URL', function (): void {
    $plan = planned((new StructuralRedirectSynthesizer)->queryPermalink(4207, '/news/2019/05/06/a-post'));

    expect($plan->source)->toBe('/?p=4207')
        ->and($plan->target)->toBe('/news/2019/05/06/a-post')
        ->and($plan->response)->toBe('301')
        ->and($plan->useRegex)->toBeFalse();
});

it('plans no ugly permalink without a usable id or a URL to land on', function (int $postId, string $url): void {
    expect((new StructuralRedirectSynthesizer)->queryPermalink($postId, $url))->toBeNull();
})->with([
    'zero id' => [0, '/a-post'],
    'negative id' => [-1, '/a-post'],
    'no URL' => [4207, ''],
    'blank URL' => [4207, '   '],
]);

it('exposes the ugly-permalink source the middleware looks rules up under', function (): void {
    // The generator writes this key and the middleware reads it; they must agree.
    expect(StructuralRedirectSynthesizer::queryPermalinkSource(4207))->toBe('/?p=4207');
});

it('plans a 301 from an attachment page to the migrated asset', function (): void {
    $plan = planned((new StructuralRedirectSynthesizer)->attachmentPage(
        'https://old.uams.test/2019/05/a-post/brochure/',
        '/assets/2019/brochure.pdf',
    ));

    expect($plan->source)->toBe('/2019/05/a-post/brochure')
        ->and($plan->target)->toBe('/assets/2019/brochure.pdf')
        ->and($plan->response)->toBe('301');
});

it('plans no attachment redirect that would hijack the root or redirect to itself', function (string $pageUrl, string $assetUrl): void {
    expect((new StructuralRedirectSynthesizer)->attachmentPage($pageUrl, $assetUrl))->toBeNull();
})->with([
    'bare root' => ['https://old.uams.test/', '/assets/doc.pdf'],
    'self-redirect' => ['https://old.uams.test/assets/doc.pdf', '/assets/doc.pdf'],
    'self-redirect, trailing slash' => ['https://old.uams.test/assets/doc.pdf/', '/assets/doc.pdf'],
    // A container with a public URL yields an absolute asset URL; the same path
    // behind a different host is still a self-redirect.
    'self-redirect, absolute asset URL' => ['https://old.uams.test/assets/doc.pdf', 'http://localhost/assets/doc.pdf'],
    'no asset URL' => ['https://old.uams.test/a-post/brochure/', '   '],
]);

it('plans a year and month archive for every distinct entry date', function (): void {
    $plans = (new StructuralRedirectSynthesizer)->dateArchives(
        [
            new DateTimeImmutable('2019-05-06'),
            // A second post in the same month must not plan the same archive twice.
            new DateTimeImmutable('2019-05-28'),
            new DateTimeImmutable('2020-11-02'),
        ],
        goneDisposition(),
    );

    expect(array_map(static fn (MigrationRedirect $r): string => $r->source, $plans))
        ->toBe(['/2019', '/2019/05', '/2020', '/2020/11']);
});

it('301s date archives to the posts index when the site has one', function (): void {
    $plans = (new StructuralRedirectSynthesizer)->dateArchives(
        [new DateTimeImmutable('2019-05-06')],
        indexDisposition(),
    );

    expect($plans)->toHaveCount(2)
        ->and($plans[0]->response)->toBe('301')
        ->and($plans[0]->target)->toBe('/news');
});

it('plans one author archive per distinct login, slugified as WordPress would', function (): void {
    $plans = (new StructuralRedirectSynthesizer)->authorArchives(
        // `dc:creator` carries the user_login; the archive URL uses the nicename slug.
        ['Ashley', 'ashley', 'Jean-Luc Picard', 'Montgomery Scott'],
        goneDisposition(),
    );

    expect(array_map(static fn (MigrationRedirect $r): string => $r->source, $plans))
        ->toBe(['/author/ashley', '/author/jean-luc-picard', '/author/montgomery-scott']);
});

it('plans no author archive for a login whose slug comes out empty', function (string $login): void {
    // A bare `/author` rule would swallow the archive index, not one author's page.
    expect((new StructuralRedirectSynthesizer)->authorArchives([$login], goneDisposition()))->toBe([]);
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'punctuation only' => ['---'],
]);

it('plans the site feeds as exact sources and nested feeds as one pattern', function (): void {
    $plans = (new StructuralRedirectSynthesizer)->feeds('/feed.xml');

    $literal = array_values(array_filter($plans, static fn (MigrationRedirect $r): bool => ! $r->useRegex));
    $patterns = array_values(array_filter($plans, static fn (MigrationRedirect $r): bool => $r->useRegex));

    expect(array_map(static fn (MigrationRedirect $r): string => $r->source, $literal))
        ->toBe(['/feed', '/feed/atom', '/feed/rdf', '/feed/rss', '/feed/rss2', '/comments/feed'])
        ->and($patterns)->toHaveCount(1)
        ->and($patterns[0]->source)->toBe(StructuralRedirectSynthesizer::NESTED_FEED_PATTERN)
        ->and($patterns[0]->target)->toBe('/feed.xml');

    // Every planned feed rule moves to the site feed.
    foreach ($plans as $plan) {
        expect($plan->target)->toBe('/feed.xml')->and($plan->response)->toBe('301');
    }
});

it('plans no feed redirects when the install has no feed route', function (): void {
    expect((new StructuralRedirectSynthesizer)->feeds('   '))->toBe([]);
});

it('matches every nested feed shape and nothing else', function (string $path, bool $expected): void {
    expect(preg_match(StructuralRedirectSynthesizer::NESTED_FEED_PATTERN, $path) === 1)->toBe($expected);
})->with([
    'per-section feed' => ['/news/feed/', true],
    'per-section feed, no trailing slash' => ['/news/feed', true],
    'per-post comment feed' => ['/2019/05/06/a-post/feed/', true],
    'atom' => ['/news/feed/atom/', true],
    'rdf' => ['/news/feed/rdf', true],
    'rss2' => ['/news/feed/rss2/', true],
    // The site-level feed is an exact source, not a nested one.
    'root feed' => ['/feed/', false],
    // A real page that merely contains the word must not be swallowed.
    'page named feed-the-need' => ['/news/feed-the-need', false],
    'feed in the middle' => ['/news/feed/story', false],
    'unknown feed format' => ['/news/feed/json', false],
]);

it('301s pagination to the posts index when the site has one', function (): void {
    $plans = (new StructuralRedirectSynthesizer)->pagination('/news');

    expect($plans)->toHaveCount(1)
        ->and($plans[0]->useRegex)->toBeTrue()
        ->and($plans[0]->source)->toBe(StructuralRedirectSynthesizer::PAGINATION_PATTERN)
        ->and($plans[0]->target)->toBe('/news')
        ->and($plans[0]->response)->toBe('301');
});

it('410s pagination when there is no posts index to land on', function (?string $archiveUrl): void {
    $plans = (new StructuralRedirectSynthesizer)->pagination($archiveUrl);

    expect($plans)->toHaveCount(1)
        ->and($plans[0]->useRegex)->toBeTrue()
        ->and($plans[0]->target)->toBeNull()
        ->and($plans[0]->response)->toBe('410');
})->with([
    'unmounted' => [null],
    'blank' => ['   '],
]);

it('matches every pagination shape and nothing else', function (string $path, bool $expected): void {
    expect(preg_match(StructuralRedirectSynthesizer::PAGINATION_PATTERN, $path) === 1)->toBe($expected);
})->with([
    'blog index pagination' => ['/page/2/', true],
    'blog index pagination, no trailing slash' => ['/page/2', true],
    'archive pagination' => ['/news/page/3/', true],
    'deep archive pagination' => ['/news/category/research/page/12', true],
    // A real page whose last segment merely starts with `page` must survive.
    'page named page' => ['/page', false],
    'pages listing' => ['/pages/2', false],
    'non-numeric' => ['/news/page/two', false],
    'page not last' => ['/news/page/2/extra', false],
]);
