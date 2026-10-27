<?php

declare(strict_types=1);

use App\Events\TriggerActivated;
use Statamic\Contracts\Entries\Entry;

/**
 * Thin trigger-activated event DTO: constructor assignment only.
 *
 * @command  php artisan test --compact tests/Unit/Events/TriggerActivatedTest.php
 */
covers(TriggerActivated::class);

it('stores the entry, trigger handle, and form values', function (): void {
    $entry = Mockery::mock(Entry::class);
    $event = new TriggerActivated($entry, 'table_data_importer_trigger', ['title' => 'x']);

    expect($event->entry)->toBe($entry)
        ->and($event->triggerHandle)->toBe('table_data_importer_trigger')
        ->and($event->formValues)->toBe(['title' => 'x']);
});

it('allows a null entry for unsaved CP forms', function (): void {
    $event = new TriggerActivated(null, 'paste_table_data_trigger');

    expect($event->entry)->toBeNull()
        ->and($event->triggerHandle)->toBe('paste_table_data_trigger')
        ->and($event->formValues)->toBe([]);
});
