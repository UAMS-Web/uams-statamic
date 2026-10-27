<?php

declare(strict_types=1);

use App\Services\Posts\NewsSyndicationQueryService;
use App\Tags\UamsNews;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Checkboxes;
use Statamic\Fieldtypes\Entries;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Regression coverage for UAMS-Web/uams-statamic-archive#76 against the value shapes Statamic **augmentation**
 * actually delivers to the {{ uams_news }} tag — the blind spot the original
 * UamsNewsTest missed by injecting hand-built shapes.
 *
 * On a rendered page-builder block, Antlers passes each field's AUGMENTED value:
 *   - a `checkboxes` field (`source_sites`) → a list of
 *     `['key' => …, 'value' => …, 'label' => …]` rows, NOT bare handles;
 *   - a multi-item entries field (`category`) → an UNEXECUTED query builder, NOT a
 *     collection of entries.
 * The tag must unwrap both or the editor's source/category selection is silently
 * dropped. These tests build the genuine augmented values via the real fieldtypes
 * and assert the selection survives to the query service.
 *
 * @covers \App\Tags\UamsNews
 *
 * @command  php artisan test --compact tests/Feature/Tags/UamsNewsAugmentedValuesTest.php
 */

/**
 * Bind a mock query service so the delegated arguments can be asserted.
 *
 * @return NewsSyndicationQueryService&MockInterface
 */
function newsAugService(): NewsSyndicationQueryService
{
    /** @var NewsSyndicationQueryService&MockInterface $service */
    $service = Mockery::mock(NewsSyndicationQueryService::class);
    app()->instance(NewsSyndicationQueryService::class, $service);

    return $service;
}

/**
 * Build the tag with the given parameters and an empty context.
 *
 * @param  array<string, mixed>  $params
 */
function newsAugTag(array $params): UamsNews
{
    $tag = app(UamsNews::class);
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag;
}

it('reads the augmented checkboxes rows for source_sites, not just bare handles', function (): void {
    $service = newsAugService();

    // The real defect: with the augmented row shape, resolveSiteHandles must still
    // receive ['news', 'current'] — not [] (which would fall back to news only and
    // make the "Current site" option non-functional).
    $service->shouldReceive('resolveSiteHandles')
        ->with(['news', 'current'], 'default')
        ->once()
        ->andReturn(['news', 'default']);
    $service->shouldReceive('resolveCategoryLink')->andReturn(['name' => null, 'url' => null]);
    $service->shouldReceive('query')->once()->andReturn(collect([]));

    // The exact shape a rendered block delivers: Checkboxes augments to rows.
    $checkboxesType = new Checkboxes;
    $checkboxesType->setField(new Field('source_sites', [
        'type' => 'checkboxes',
        'options' => ['news' => 'News site', 'current' => 'Current site'],
    ]));

    /** @var array<int, array<string, mixed>> $augmented */
    $augmented = $checkboxesType->augment(['news', 'current']);

    // Sanity-check the fixture really is the augmented row shape, not bare handles.
    expect($augmented[0])->toHaveKey('value');

    newsAugTag(['format' => 'grid', 'source_sites' => $augmented])->index();
});

it('executes the augmented multi-entries query builder for category', function (): void {
    $postCategories = Collection::make('post_categories');
    $postCategories->title('Post Categories');
    $postCategories->sites(['default']);
    $postCategories->save();

    /** @var Statamic\Entries\Entry $categoryEntry */
    $categoryEntry = Entry::make();
    $categoryEntry->collection('post_categories');
    $categoryEntry->id('cat-cardiology');
    $categoryEntry->slug('cardiology');
    $categoryEntry->published(true);
    $categoryEntry->data(['title' => 'Cardiology']);
    $categoryEntry->save();

    $service = newsAugService();
    $service->shouldReceive('resolveSiteHandles')->andReturn(['news']);
    $service->shouldReceive('resolveCategoryLink')->andReturn(['name' => 'Cardiology', 'url' => null]);

    // The real defect: with the augmented builder shape, the category entry id must
    // reach query() — not [] (which would show every post, ignoring the filter).
    // The trailing require_featured_image (false, unset) and format ('grid') args
    // are the require-featured-image / format-aware query the news-marketing work added.
    $service->shouldReceive('query')
        ->with(['news'], ['cat-cardiology'], 3, 0, false, 'grid')
        ->once()
        ->andReturn(collect([]));

    // The exact shape a rendered block delivers: a multi-item entries field augments
    // to an unexecuted query builder over the referenced category entries.
    $entriesType = new Entries;
    $entriesType->setField(new Field('category', [
        'type' => 'entries',
        'collections' => ['post_categories'],
    ]));
    $augmented = $entriesType->augment(['cat-cardiology']);

    // Sanity-check the fixture really is an unexecuted query builder.
    expect($augmented)->toBeInstanceOf(Builder::class);

    newsAugTag(['format' => 'grid', 'source_sites' => ['news'], 'category' => $augmented])->index();
});
