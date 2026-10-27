<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Clinical Resource — shared bard presets (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| The rich-text content fields reference shared `uamsweb_common` bard presets
| instead of redefining `buttons` inline: the media intros use `bard_simple`,
| the accessibility transcripts use `bard_transcript`, and the long-form
| bodies keep the full `article` preset. Asserting each field resolves to the
| SAME buttons as its preset proves the inline overrides are gone and the
| field follows the shared preset.
*/

/**
 * Buttons declared by a field in the shared `uamsweb_common` fieldset.
 *
 * @return list<string>
 */
function uamswebCommonPresetButtons(string $handle): array
{
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_common');
    /** @var array<int, array{handle?: string, field?: array<string, mixed>}> $fields */
    $fields = $fieldset->contents()['fields'];

    foreach ($fields as $entry) {
        if (($entry['handle'] ?? null) === $handle) {
            /** @var list<string> $buttons */
            $buttons = $entry['field']['buttons'] ?? [];

            return $buttons;
        }
    }

    return [];
}

/**
 * Resolved buttons for a field on a clinical_resources blueprint.
 *
 * @return list<string>
 */
function clinicalResourceFieldButtons(string $blueprint, string $handle): array
{
    /** @var Statamic\Fields\Blueprint $resolvedBlueprint */
    $resolvedBlueprint = Blueprint::in('collections/clinical_resources')->get($blueprint);
    /** @var Field $field */
    $field = $resolvedBlueprint->field($handle);
    /** @var list<string> $buttons */
    $buttons = $field->get('buttons') ?? [];

    return $buttons;
}

// Both helpers fall back to [] when a handle/preset goes missing, so a broken
// preset reference could pass vacuously as [] === []. Guard every comparison by
// first asserting the preset side is non-empty.
it('inherits the bard_simple buttons for the media intro fields', function (string $blueprint, string $handle) {
    expect(uamswebCommonPresetButtons('bard_simple'))->not->toBeEmpty();
    expect(clinicalResourceFieldButtons($blueprint, $handle))->toBe(uamswebCommonPresetButtons('bard_simple'));
})->with([
    ['infographic', 'infographic_description'],
    ['document', 'documents_description'],
]);

it('inherits the bard_transcript buttons for the transcript field', function () {
    expect(uamswebCommonPresetButtons('bard_transcript'))->not->toBeEmpty();
    expect(clinicalResourceFieldButtons('infographic', 'infographic_transcript'))
        ->toBe(uamswebCommonPresetButtons('bard_transcript'));
});

it('keeps the full article preset for the long-form body fields', function (string $blueprint, string $handle) {
    expect(uamswebCommonPresetButtons('article'))->not->toBeEmpty();
    expect(clinicalResourceFieldButtons($blueprint, $handle))->toBe(uamswebCommonPresetButtons('article'));
})->with([
    ['article', 'article'],
    ['video', 'intro'],
]);
