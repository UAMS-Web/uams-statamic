<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Group;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/**
 * Resolve a blueprint by namespace + handle, narrowed off the loosely-typed facade.
 */
function resolveBlueprint(string $namespace, string $handle): StatamicBlueprint
{
    $blueprint = Blueprint::in($namespace)->get($handle);

    return $blueprint instanceof StatamicBlueprint
        ? $blueprint
        : throw new RuntimeException("Missing `{$handle}` blueprint in `{$namespace}`");
}

/**
 * Resolve a field on a blueprint, narrowed off the mixed `Blueprint::field()`.
 */
function blueprintField(StatamicBlueprint $blueprint, string $handle): Field
{
    $field = $blueprint->field($handle);

    return $field instanceof Field
        ? $field
        : throw new RuntimeException("Missing field `{$handle}`");
}

/**
 * A field's `validate` config as a list, tolerating the mixed blueprint value.
 *
 * @return list<mixed>
 */
function fieldValidateRules(Field $field): array
{
    $rules = $field->get('validate');

    return is_array($rules) ? array_values($rules) : [];
}

/*
|--------------------------------------------------------------------------
| Podcast episode audio model — subtype of audio_series (UAMS-Web/uams-statamic-archive#1097, UAMS-Web/uams-statamic-archive#1611)
|--------------------------------------------------------------------------
|
| Podcast was folded from a parallel `podcast_series` taxonomy into a
| multi-select `subtype` on `audio_series` (UAMS-Web/uams-statamic-archive#1611). The shared audio model
| (posts/audio + clinical_resources/audio) now carries a single `series`
| (→ `audio_series`) association; the podcast-episode layer (episode/season
| numbers, show notes, transcript, host/guest credits, duration) is gated on
| that series being present. Every podcast field stays optional or
| conditionally-required, so a plain (non-podcast) audio entry still validates
| unchanged. Running every assertion over both collections is the posts <->
| clinical parity guarantee.
*/

dataset('audioBlueprints', [
    'posts/audio' => ['collections/posts'],
    'clinical_resources/audio' => ['collections/clinical_resources'],
]);

it('drops the retired podcast_series field; series (→ audio_series) is the sole series association', function (string $namespace) {
    $bp = resolveBlueprint($namespace, 'audio');

    // The parallel podcast_series taxonomy field is gone (UAMS-Web/uams-statamic-archive#1611).
    expect($bp->hasField('podcast_series'))->toBeFalse();

    // The single series association targets the audio_series taxonomy.
    $series = blueprintField($bp, 'series');
    expect($series->type())->toBe('terms');
    expect($series->get('taxonomies'))->toBe(['audio_series']);
    expect($series->get('max_items'))->toBe(1);
})->with('audioBlueprints');

it('requires an episode number only when a series is set', function (string $namespace) {
    $episode = blueprintField(resolveBlueprint($namespace, 'audio'), 'episode_number');

    expect($episode->type())->toBe('integer');
    expect($episode->get('if'))->toBe(['series' => 'not empty']);
    // required + sometimes => required only when submitted (i.e. when the series gate shows it).
    expect(fieldValidateRules($episode))->toContain('required')->toContain('sometimes');
})->with('audioBlueprints');

it('adds the optional podcast episode fields, each gated on the series', function (string $namespace) {
    $bp = resolveBlueprint($namespace, 'audio');

    foreach (['season_number', 'show_notes', 'episode_transcript', 'episode_duration', 'podcast_credits'] as $handle) {
        expect(blueprintField($bp, $handle)->get('if'))->toBe(['series' => 'not empty']);
    }
})->with('audioBlueprints');

it('credits the host and guest through the shared audio creator roles', function (string $namespace) {
    $credits = blueprintField(resolveBlueprint($namespace, 'audio'), 'podcast_credits');

    expect($credits->type())->toBe('group');

    // Narrow the group fieldtype's nested fields (both getters are untyped `mixed`).
    $fieldtype = $credits->fieldtype();
    $groupFields = $fieldtype instanceof Group ? $fieldtype->fields() : null;
    $roleHandles = $groupFields instanceof Fields ? $groupFields->all()->keys()->all() : [];

    expect($roleHandles)->toContain('host')->toContain('guest');
})->with('audioBlueprints');

it('keeps a plain (non-podcast) audio entry valid; no podcast field is unconditionally required', function (string $namespace) {
    $bp = resolveBlueprint($namespace, 'audio');

    foreach (['episode_number', 'season_number', 'show_notes', 'episode_transcript', 'episode_duration', 'podcast_credits'] as $handle) {
        $field = blueprintField($bp, $handle);
        $validate = fieldValidateRules($field);

        // "required" can arrive three ways: the framework's isRequired(), a literal
        // `required` in the validate array, or the `required: true` config shortcut.
        // Union all three so this can't pass vacuously if one detection path misses.
        $isRequired = $field->isRequired()
            || in_array('required', $validate, true)
            || $field->get('required') === true;

        // A required podcast field must also be conditional (`sometimes`) so that a
        // plain audio entry — which submits none of these — still validates.
        $unconditionallyRequired = $isRequired && ! in_array('sometimes', $validate, true);

        expect($unconditionallyRequired)->toBeFalse("`{$handle}` must not be unconditionally required on plain audio");
    }
})->with('audioBlueprints');

/*
|--------------------------------------------------------------------------
| audio_series term blueprint — the multi-select subtype discriminator (UAMS-Web/uams-statamic-archive#1611)
|--------------------------------------------------------------------------
*/

it('adds a multi-select subtype (podcast, radio) to the audio_series term blueprint', function () {
    $subtype = blueprintField(resolveBlueprint('taxonomies/audio_series', 'audio_series'), 'subtype');

    expect($subtype->type())->toBe('select');
    expect($subtype->get('multiple'))->toBeTrue();
    expect($subtype->get('options'))->toBe(['podcast' => 'Podcast', 'radio' => 'Radio']);
});

it('folds the podcast description onto the audio_series term, shown only for a podcast subtype', function () {
    $description = blueprintField(resolveBlueprint('taxonomies/audio_series', 'audio_series'), 'description');

    expect($description->type())->toBe('textarea');
    // The schema.org description is a podcast-series concept, revealed by the subtype.
    expect($description->get('if'))->toBe(['subtype' => 'contains podcast']);
});
