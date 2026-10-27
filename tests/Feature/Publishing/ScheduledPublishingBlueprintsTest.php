<?php

declare(strict_types=1);

use App\Jobs\ApplyScheduledPublishing;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as BlueprintInstance;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Pins the committed blueprints the scheduled publishing job reads (UAMS-Web/uams-statamic-archive#2552, UAMS-Web/uams-statamic-archive#2564).
 *
 * The job resolves `publish_at` / `unpublish_at` through the entry's blueprint and
 * skips an entry whose blueprint lacks the field, so a renamed or missing field would
 * leave every schedule unapplied without an error. The job's own test builds its
 * blueprints in memory, so these read the real files.
 *
 * @command  php artisan test --compact tests/Feature/Publishing/ScheduledPublishingBlueprintsTest.php
 */

/**
 * Load one committed blueprint by its namespaced handle.
 */
function scheduledPublishingBlueprint(string $handle): BlueprintInstance
{
    return mustBe(BlueprintInstance::class, Blueprint::find($handle), "the {$handle} blueprint");
}

it('gives each scheduled blueprint publish_at and unpublish_at, and no date field', function (string $handle): void {
    $blueprint = scheduledPublishingBlueprint($handle);

    foreach (['publish_at', 'unpublish_at'] as $fieldHandle) {
        $field = mustBe(Field::class, $blueprint->field($fieldHandle), "{$handle} {$fieldHandle}");

        expect($field->type())->toBe('date')
            ->and($field->get('time_enabled'))->toBeTrue()
            ->and($field->isLocalizable())->toBeFalse();
    }

    // The sidebar date the dated collections used is gone
    expect($blueprint->hasField('date'))->toBeFalse();
})->with([
    'person' => ['collections/people/person'],
    'basic page' => ['collections/pages/basic_page'],
    'landing page' => ['collections/pages/landing_page'],
]);

it('offers no Scheduled status on a people or pages blueprint', function (string $handle): void {
    $options = mustBe(Field::class, scheduledPublishingBlueprint($handle)->field('status_and_visibility'), "{$handle} status")->get('options');

    // The fieldset's own option list includes `scheduled`, so an override that failed
    // to apply would show it here.
    expect(array_column(mustBeArray($options, "{$handle} status options"), 'key'))
        ->toBe(['draft', 'pending', 'private', 'published']);
})->with([
    'person' => ['collections/people/person'],
    'basic page' => ['collections/pages/basic_page'],
    'landing page' => ['collections/pages/landing_page'],
    'child site link' => ['collections/pages/child_site_link'],
    'posts category link' => ['collections/pages/posts_category_link'],
]);

it('schedules exactly the collections these blueprints belong to', function (): void {
    expect(ApplyScheduledPublishing::collections())->toBe(['people', 'pages']);
});
