<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2291. This file was gated to the serial tail on
// "the shared, paratest-non-isolated compiled-view cache" (the UAMS-Web/uams-statamic-archive#1300 / UAMS-Web/uams-statamic-archive#1310 / UAMS-Web/uams-statamic-archive#1447 /
// UAMS-Web/uams-statamic-archive#1452 render-flake class). That directory is per worker under Laravel's `TestViews`
// -- `storage/framework/views/test_<token>`, the one UAMS-Web/uams-statamic-archive#2442 patched for concurrent bands
// -- so the hazard the gate named does not exist. Three parity-matched parallel bands
// with this file in them produced zero failures (macOS, 2026-09-16); runs refute and
// never establish, and tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * The empty-relation fall-through on the shared post card's primary-category
 * label (UAMS-Web/uams-statamic-archive#2026). A plain-array fixture omits `post_categories` when a post has
 * none — but a real augmented entry NEVER omits it: `AugmentedEntry::keys()`
 * merges every blueprint handle, so an empty relation arrives as a `Value`
 * wrapping an empty query builder. Looping that builder emits the body ONCE
 * against the PARENT scope, where `url`/`title` are the post's own — printing
 * the headline as its category, linked to itself.
 *
 * Same trap the provider ontology sections carry, and the same gate:
 * `| count`, not a bare field.
 *
 * @command  php artisan test --compact tests/Feature/Views/PostCardPrimaryCategoryTest.php
 */
beforeEach(fn () => Blink::flush());

/**
 * Render the shared post card with the primary-category label opted in.
 *
 * Named for the opt-in rather than the partial: Pest declares these helpers in the
 * GLOBAL namespace, so a bare `renderPostCard()` collides fatally with the one
 * PostsIndexListingTest already declares (which renders the same partial WITHOUT
 * `show_primary_category`). That fatal happens at load time and takes down the whole
 * suite, and no targeted run catches it — the two files are only ever loaded into one
 * process by a full-suite run.
 *
 * @param  array<string, mixed>  $data
 */
function renderPostCardWithPrimaryCategory(array $data): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse(
        '{{ partial:posts/card show_primary_category="true" }}'."\n{{# r-{$render} #}}",
        $data
    );
}

/**
 * The parent scope a card sees in the news-home lead band: the post's own
 * identity plus the `first` the enclosing loop injects.
 *
 * @return array<string, mixed>
 */
function postCardParentScope(): array
{
    return [
        'title' => 'UAMS Health Talk Episode 12',
        'url' => '/2026/08/14/uams-health-talk-episode-12',
        'first' => true,
    ];
}

it('omits the primary-category label when the augmented relation resolves to nothing', function (string $blueprint): void {
    // Resolved inline rather than in a helper: `markTestSkipped()` is a method on the
    // test case, so it must be reached through `$this` from inside the closure.
    // `test()->markTestSkipped()` from a plain function works at runtime (Pest's
    // `__call` proxies it) but statically resolves to TestCall|HigherOrderTapProxy,
    // which declares no such method — green suite, red larastan.
    $field = Blueprint::find("collections/posts/{$blueprint}")?->field('post_categories');

    if (! $field instanceof Field) {
        $this->markTestSkipped("Requires the {$blueprint} post blueprint with a `post_categories` field.");
    }

    // An empty relation gives the empty-builder production shape.
    $field->setValue([]);
    $augmented = $field->augment();

    if (! $augmented instanceof Field) {
        $this->markTestSkipped("The {$blueprint} `post_categories` field did not augment.");
    }

    $html = renderPostCardWithPrimaryCategory(postCardParentScope() + [
        'post_categories' => $augmented->value(),
    ]);

    expect($html)->not->toContain('post-card-category')
        // The self-link is the exact symptom: the loop emitting once against the parent.
        ->and($html)->not->toContain('<a href="/2026/08/14/uams-health-talk-episode-12">UAMS Health Talk Episode 12</a>');
})->with(['post', 'news_release', 'audio', 'video']);

it('still renders the primary-category label from a populated relation', function (): void {
    // Bounds the gate on the other side: fixing the fall-through must not
    // suppress a category a post genuinely has. Plain-array fixture, which is
    // what the sibling card render tests use.
    $html = renderPostCardWithPrimaryCategory(postCardParentScope() + [
        'post_categories' => [
            ['title' => 'UAMS News', 'url' => '/news/category/uams-news'],
        ],
    ]);

    expect($html)->toContain('post-card-category')
        ->and($html)->toContain('UAMS News')
        ->and($html)->toContain('/news/category/uams-news');
});
