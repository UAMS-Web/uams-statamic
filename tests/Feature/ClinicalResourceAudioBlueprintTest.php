<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Replicator;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Clinical Resource — `audio` blueprint (UAMS-Web/uams-statamic-archive#1096)
|--------------------------------------------------------------------------
|
| clinical_resources gains an `audio` type mirroring the posts/audio audio
| model (MP3 primary + OGG alternate, per-segment overrides, preload/loop, an
| audio series), adapted to the UAMS-Web/uams-statamic-archive#799 clinical alignment: the media-blueprint
| Main-tab shape (Entry Header → Featured/Cover → Syndication → Text Content →
| Text Content Origin → Audio Content), a Related Items tab, and a lean
| slug + date sidebar — none of the posts Behavior/Scripts/Robots/status/
| password/category/tag/type chrome. The per-segment field composition is
| extracted to one shared fieldset so posts/audio and clinical audio resolve
| identically.
*/

it('models the audio segment like posts/audio (mp3 primary + ogg alternate, overrides, preload/loop)', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_resources')->get('audio');

    /** @var Field $segments */
    $segments = $blueprint->field('segments');

    /** @var Replicator $replicator */
    $replicator = $segments->fieldtype();

    /** @var Fields $localFileFields */
    $localFileFields = $replicator->fields('local_file');

    /** @var Collection<string, Field> $fields */
    $fields = $localFileFields->all();

    /** @var Field $fileField */
    $fileField = $fields->get('file');
    /** @var Field $fileAlternateField */
    $fileAlternateField = $fields->get('file_alternate');
    /** @var Field $preloadField */
    $preloadField = $fields->get('preload');
    /** @var Field $loopField */
    $loopField = $fields->get('loop');

    expect($fileField->get('mime_types'))->toBe(['audio/mp3']);
    expect($fileField->get('validate'))->toContain('required');
    expect($fileAlternateField->get('mime_types'))->toBe(['audio/ogg']);
    expect($preloadField->defaultValue())->toBe('metadata');
    expect($loopField->type())->toBe('toggle');
});

it('exposes the audio series taxonomy field', function () {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_resources')->get('audio');

    /** @var Field $seriesField */
    $seriesField = $bp->field('series');
    expect($seriesField->get('taxonomies'))->toBe(['audio_series']);
});

it('registers the audio schema-template defaults on the collection', function () {
    /** @var Store $collectionsStore */
    $collectionsStore = Stache::store('collections');
    /** @var string $directory */
    $directory = $collectionsStore->directory();
    $path = rtrim($directory, '/\\').'/clinical_resources.yaml';

    /** @var array{inject?: array{schema_templates?: array{blueprint_defaults?: array<string, array<string, mixed>>}}} $parsed */
    $parsed = YAML::parse(File::get($path));
    /** @var array<string, array<string, mixed>> $defaults */
    $defaults = $parsed['inject']['schema_templates']['blueprint_defaults'] ?? [];

    expect($defaults)->toHaveKey('audio');
    expect($defaults['audio']['webpage_entry'])->toBe($defaults['article']['webpage_entry']);
    expect($defaults['audio']['main_content_entry'])->toBe($defaults['article']['main_content_entry']);
});
