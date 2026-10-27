<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Cover-image field-set standardization (UAMS-Web/uams-statamic-archive#1553)
|--------------------------------------------------------------------------
|
| `pages` and `knowledge_bases` are consolidated off the legacy single
| `featured_image` (a `uamsweb_entry_meta` handle) onto the standard 3-handle
| cover-image set — `featured_image_16_9` / `_4_3` / `_1_1` — so every
| front-end-view collection speaks one featured-image convention (the
| `clinical_expertise` / posts / clinical_resources shape). The 16:9 handle is
| left OPTIONAL here (dimensions-only, no `required`): unlike editorial posts,
| pages and KB entries routinely carry no cover image, so a required hero would
| block saving them. `excerpt` (the other `uamsweb_entry_meta` handle) is
| retained. `clinical_locations` is deliberately left on its single
| `featured_image` (one-image card, no aspect-ratio need).
*/

/**
 * The collections consolidated onto the standard 3-handle cover-image set.
 *
 * @return array<string, array{string}>
 */
dataset('consolidated blueprints', [
    'pages/basic_page' => ['collections/pages.basic_page'],
    'pages/landing_page' => ['collections/pages.landing_page'],
    'knowledge_bases/knowledge_base' => ['collections/knowledge_bases.knowledge_base'],
    'knowledge_bases/article' => ['collections/knowledge_bases.article'],
    'knowledge_bases/topic' => ['collections/knowledge_bases.topic'],
]);

function coverImageBlueprint(string $ref): Statamic\Fields\Blueprint
{
    [$namespace, $handle] = explode('.', $ref);

    $blueprint = mustBe(Statamic\Fields\Blueprint::class, Blueprint::in($namespace)->get($handle), 'blueprint');

    return $blueprint;
}

it('carries the standard 3-handle cover-image set', function (string $ref) {
    $blueprint = coverImageBlueprint($ref);

    expect($blueprint->hasField('featured_image_16_9'))->toBeTrue()
        ->and($blueprint->hasField('featured_image_4_3'))->toBeTrue()
        ->and($blueprint->hasField('featured_image_1_1'))->toBeTrue();
})->with('consolidated blueprints');

it('drops the legacy single featured_image handle', function (string $ref) {
    expect(coverImageBlueprint($ref)->hasField('featured_image'))->toBeFalse();
})->with('consolidated blueprints');

it('keeps the 16:9 cover image optional (dimensions-only, not required)', function (string $ref) {
    $field = mustBe(Field::class, coverImageBlueprint($ref)->field('featured_image_16_9'), 'field');
    $validate = $field->get('validate');

    expect($validate)->toContain('dimensions:min_width=1200,min_height=675');
    expect($validate)->not->toContain('required');
})->with('consolidated blueprints');

it('retains the excerpt handle alongside the cover-image set', function (string $ref) {
    expect(coverImageBlueprint($ref)->hasField('excerpt'))->toBeTrue();
})->with('consolidated blueprints');

it('renders the KB article-card thumbnail from featured_image_16_9', function () {
    // Guards the _article_card template rename: with only featured_image_16_9 in
    // context, the card must still resolve its thumbnail (a revert to the legacy
    // `featured_image` handle would render no image and fail this test).
    $out = (string) Antlers::parse('{{ partial:knowledge_bases/_article_card }}', [
        'resolved_topic_image_query' => true,
        'featured_image_16_9' => ['url' => '/assets/kb-hero.jpg', 'alt' => 'KB hero', 'exempt_from_alt' => false, 'caption' => null],
        'id' => 'abc123',
        'title' => 'Sample Article',
        'url' => '/kb/sample-article',
        'topic_article_label_singular' => 'Article',
        'topic_article_label' => 'Articles',
    ], true);

    expect($out)->toContain('/assets/kb-hero.jpg');
});

it('leaves clinical_locations on its single featured_image (out of scope)', function () {
    $location = mustBe(Statamic\Fields\Blueprint::class, Blueprint::in('collections/clinical_locations')->get('location'), 'location');

    expect($location->hasField('featured_image'))->toBeTrue()
        ->and($location->hasField('featured_image_16_9'))->toBeFalse();
});
