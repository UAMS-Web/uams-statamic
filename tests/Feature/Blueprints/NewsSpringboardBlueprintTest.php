<?php

declare(strict_types=1);

use App\Tags\NewsSpringboard;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1158 — the `news_springboard` pages blueprint: a repeatable `sections` grid
 * of category slots (heading + `post_categories` category + item count) whose
 * template threads a cross-section dedup accumulator. Locks the field shape the
 * {@see NewsSpringboard} tag reads.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/NewsSpringboardBlueprintTest.php
 */
beforeEach(function () {
    $blueprint = Blueprint::find('collections.pages.news_springboard');
    expect($blueprint)->not->toBeNull();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $this->blueprint = $blueprint;
    /** @var Field $sections */
    $sections = $blueprint->fields()->all()->get('sections');
    $this->sections = $sections;
});

it('exposes a reorderable sections grid', function () {
    /** @var ?Field $sections */
    $sections = $this->sections;
    expect($sections)->not->toBeNull();

    /** @var Field $sections */
    expect($sections->type())->toBe('grid');
});

it('defines each section as a category slot (heading, category, count)', function () {
    /** @var Field $sections */
    $sections = $this->sections;

    /** @var array<int, array{handle: string, field: array<string, mixed>}> $gridFields */
    $gridFields = $sections->config()['fields'] ?? [];

    $byHandle = collect($gridFields)->keyBy('handle');

    expect($byHandle->has('section_heading'))->toBeTrue();
    expect($byHandle->has('category'))->toBeTrue();
    expect($byHandle->has('count'))->toBeTrue();

    // The category slot targets the post_categories collection, single-value.
    /** @var array{field: array<string, mixed>} $category */
    $category = $byHandle->get('category');
    expect($category['field']['type'] ?? null)->toBe('entries');
    expect($category['field']['collections'] ?? null)->toBe(['post_categories']);
    expect($category['field']['max_items'] ?? null)->toBe(1);
});

it('renders through the pages/news_springboard template', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $this->blueprint;

    /** @var ?Field $template */
    $template = $blueprint->fields()->all()->get('template');
    expect($template)->not->toBeNull();

    /** @var Field $template */
    expect($template->config()['default'] ?? null)->toBe('pages/news_springboard');
});
