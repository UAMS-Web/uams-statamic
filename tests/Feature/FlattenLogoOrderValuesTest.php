<?php

declare(strict_types=1);

use App\Listeners\FlattenLogoOrderValues;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#73: on save, a logo_list_items entry's nested `order_values`
 * pairs are mirrored to sortable top-level `order_value__<key>` fields, stale
 * mirrors are pruned, and the block sorts on the prefixed handle.
 *
 * @command  php artisan test --compact tests/Feature/FlattenLogoOrderValuesTest.php
 */
it('mirrors order_values to prefixed top-level fields and prunes stale ones', function (): void {
    $this->requireContentCollection('logo_list_items');

    /** @var Entry $entry */
    $entry = EntryFacade::make();
    $entry->collection('logo_list_items');
    $entry->set('order_values', ['priority' => '5', 'alpha' => '2']);
    // A leftover mirror from a since-removed order key.
    $entry->set('order_value__stale', '9');

    (new FlattenLogoOrderValues)->handle(new EntrySaving($entry));

    expect($entry->get('order_value__priority'))->toBe('5');
    expect($entry->get('order_value__alpha'))->toBe('2');
    // The stale mirror is pruned.
    expect($entry->get('order_value__stale'))->toBeNull();
    // The nested source is left intact.
    expect($entry->get('order_values'))->toBe(['priority' => '5', 'alpha' => '2']);
});

it('ignores entries in other collections', function (): void {
    $this->requireContentCollection('posts');

    /** @var Entry $entry */
    $entry = EntryFacade::make();
    $entry->collection('posts');
    $entry->set('order_values', ['priority' => '5']);

    (new FlattenLogoOrderValues)->handle(new EntrySaving($entry));

    expect($entry->get('order_value__priority'))->toBeNull();
});

it('sorts the Logo List block by the flattened order handle', function (): void {
    $block = File::get(resource_path('views/page_builder/_uamsweb_block_logo_list.antlers.html'));

    expect($block)->toContain("\$sort_by = 'order_value__' + sort_key");
});
