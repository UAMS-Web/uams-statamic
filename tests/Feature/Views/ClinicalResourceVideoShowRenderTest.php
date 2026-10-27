<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2511 — a video clinical resource must render its player on the show page.
 *
 * The show template hands a video resource to clinical_resources/_segments,
 * which reads `segments`. The video blueprint stored its replicator under
 * `video`, so an authored entry rendered no player at all.
 * ClinicalResourceVideoCaptionRenderTest could not see it: it passes its rows
 * as `segments` directly, which is the handle the partial reads rather than the
 * one the blueprint writes.
 *
 * This suite therefore never names the handle. It asks the blueprint which
 * field holds the video sets, stores the row under that handle on an in-memory
 * entry, and renders the show template from the entry's augmented data — so it
 * goes red whenever the blueprint and the template disagree again. Nothing is
 * saved. The collection config lives in the `content/` submodule, so the suite
 * self-skips without it.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalResourceVideoShowRenderTest.php
 */
beforeEach(function (): void {
    Blink::flush();

    if (! Collection::find('clinical_resources')) {
        ContentSubmodule::reportMissing('clinical_resources collection config');
    }
});

/**
 * The handle of the field in a clinical_resources blueprint that offers the
 * given replicator set — read from the blueprint, never assumed.
 */
function clinicalResourceFieldOfferingSet(string $blueprint, string $set): string
{
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::find('clinical_resources');
    /** @var Blueprint $resolved */
    $resolved = $collection->entryBlueprint($blueprint);

    /** @var Illuminate\Support\Collection<string, Field> $fields */
    $fields = $resolved->fields()->all();

    $handle = $fields
        ->filter(fn (Field $field): bool => $field->type() === 'replicator')
        ->filter(function (Field $field) use ($set): bool {
            /** @var array<array-key, array{sets?: array<string, mixed>}> $groups */
            $groups = $field->get('sets') ?? [];

            return collect($groups)->contains(fn (array $group): bool => array_key_exists($set, $group['sets'] ?? []));
        })
        ->keys()
        ->first();

    expect($handle)->toBeString();

    return (string) $handle;
}

/**
 * Render the clinical resource show template for an in-memory entry authored
 * through the named blueprint.
 *
 * @param  array<string, mixed>  $data
 */
function renderClinicalResourceShow(string $blueprint, array $data): string
{
    /** @var Entry $entry */
    $entry = EntryFacade::make();
    /** @var Entry $entry */
    $entry = $entry->collection('clinical_resources');
    /** @var Entry $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var Entry $entry */
    $entry = $entry->locale('health');
    /** @var Entry $entry */
    $entry = $entry->slug('clinical-resource-show-render');
    /** @var Entry $entry */
    $entry = $entry->data(['title' => 'Managing Diabetes', ...$data]);
    /** @var array<string, mixed> $scope */
    $scope = $entry->toAugmentedArray();

    return (string) Antlers::parse(
        (string) file_get_contents(resource_path('views/clinical_resources/show.antlers.html')),
        $scope,
        true,
    );
}

/**
 * A YouTube video segment row, shaped as the `youtube` set stores it.
 *
 * @return array<string, mixed>
 */
function clinicalYoutubeRow(string $title): array
{
    return [
        'id' => 'clinical-video-row',
        'type' => 'youtube',
        'enabled' => true,
        'title' => $title,
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'aspect_ratio' => '16:9',
    ];
}

it('renders the player for a video resource authored through its blueprint', function () {
    $handle = clinicalResourceFieldOfferingSet('video', 'youtube');

    $out = renderClinicalResourceShow('video', [$handle => [clinicalYoutubeRow('Checking your blood sugar')]]);

    expect(substr_count($out, 'entry-segment-video'))->toBe(1)
        ->and($out)->toContain('<iframe')
        ->toContain('youtube')
        ->toContain('Checking your blood sugar')
        ->not->toContain('entry-segment-audio');
});

it('renders no player for a video resource with no segment', function () {
    // The negative arm: the player above comes from the row, not from the template alone.
    $out = renderClinicalResourceShow('video', []);

    expect($out)->not->toContain('entry-segment-video')
        ->not->toContain('<iframe');
});

it('renders an audio resource\'s video rendition through the same handle', function () {
    // The audio blueprint already stores under the handle the template reads, and
    // UAMS-Web/uams-statamic-archive#2512 lets its replicator carry a video rendition. Rendered through the same
    // show path, it must reach the video player exactly as before this change.
    $handle = clinicalResourceFieldOfferingSet('audio', 'youtube');

    $out = renderClinicalResourceShow('audio', [$handle => [clinicalYoutubeRow('Video rendition')]]);

    expect(substr_count($out, 'entry-segment-video'))->toBe(1)
        ->and($out)->toContain('Video rendition');
});
