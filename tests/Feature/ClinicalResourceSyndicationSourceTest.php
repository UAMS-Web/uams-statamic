<?php

declare(strict_types=1);

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
| Clinical Resource — Article syndication source selector (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| The `article` blueprint carries the full syndication model. The boolean
| `text_nci_query` ("is this NCI?") became `syndication_source`: a radio
| choosing the provider (NCI vs Other), shown only once the resource is
| marked syndicated. Its dependents (`nci_embed`, `syndication_url`) key on
| the chosen source rather than a boolean. With the per-type split there is
| no `type` discriminator, so the gates key purely on syndication_query.
| The media blueprints carry only a LEAN syndication (query + url) — the
| WordPress syndication URL drove the canonical + schema `sameAs` for every
| resource type, but NCI syndication was always article-only.
*/

function syndicationSourceBlueprint(): Statamic\Fields\Blueprint
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_resources')->get('article');

    return $blueprint;
}

it('asks the syndication question unconditionally on the article blueprint', function () {
    /** @var Field $field */
    $field = syndicationSourceBlueprint()->field('syndication_query');

    /** @var string $type */
    $type = $field->type();
    expect($type)->toBe('true_false');
    // No `type` gate survives the split — the question is always shown.
    /** @var array<string, string>|null $if */
    $if = $field->get('if');
    expect($if)->toBeNull();
});

it('renames text_nci_query to a syndication_source radio', function () {
    $blueprint = syndicationSourceBlueprint();

    expect($blueprint->hasField('text_nci_query'))->toBeFalse();
    expect($blueprint->hasField('syndication_source'))->toBeTrue();
    /** @var Field $sourceField */
    $sourceField = $blueprint->field('syndication_source');
    /** @var string $type */
    $type = $sourceField->type();
    expect($type)->toBe('radio');
});

it('offers exactly the NCI and Other source options', function () {
    /** @var Field $sourceField */
    $sourceField = syndicationSourceBlueprint()->field('syndication_source');
    /** @var array<int, array<string, string>> $options */
    $options = $sourceField->get('options');

    expect($options)->toBe([
        ['key' => 'nci', 'value' => 'National Cancer Institute (NCI)'],
        ['key' => 'other', 'value' => 'Other'],
    ]);
});

it('shows the source selector only for a syndicated resource', function () {
    /** @var Field $sourceField */
    $sourceField = syndicationSourceBlueprint()->field('syndication_source');
    /** @var array<string, string>|null $if */
    $if = $sourceField->get('if');
    expect($if)->toBe(['syndication_query' => 'equals true']);
});

it('drives the NCI embed off the nci option and the source URL off a selected source', function () {
    $blueprint = syndicationSourceBlueprint();

    /** @var Field $nciEmbedField */
    $nciEmbedField = $blueprint->field('nci_embed');
    /** @var array<string, string>|null $nciEmbedIf */
    $nciEmbedIf = $nciEmbedField->get('if');
    expect($nciEmbedIf)->toBe([
        'syndication_query' => 'equals true',
        'syndication_source' => 'equals nci',
    ]);

    /** @var Field $syndicationUrlField */
    $syndicationUrlField = $blueprint->field('syndication_url');
    /** @var array<string, string>|null $syndicationUrlIf */
    $syndicationUrlIf = $syndicationUrlField->get('if');
    expect($syndicationUrlIf)->toBe([
        'syndication_query' => 'equals true',
        'syndication_source' => 'not null',
    ]);
});

it('requires-when-shown every syndication field via [required, sometimes]', function (string $handle) {
    /** @var Field $field */
    $field = syndicationSourceBlueprint()->field($handle);
    /** @var array<int, string> $validate */
    $validate = $field->get('validate');
    expect($validate)->toBe(['required', 'sometimes']);
})->with(['syndication_query', 'syndication_source', 'nci_embed', 'syndication_url']);

it('carries a lean syndication (query + url) on the media blueprints, without the NCI fields', function (string $blueprint) {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_resources')->get($blueprint);

    // The syndication URL preserves the canonical + schema sameAs capability
    // for media types, so query + url are captured...
    expect($bp->hasField('syndication_query'))->toBeTrue();
    expect($bp->hasField('syndication_url'))->toBeTrue();

    // ...but NCI syndication (the source radio + embed) was always article-only.
    expect($bp->hasField('syndication_source'))->toBeFalse();
    expect($bp->hasField('nci_embed'))->toBeFalse();

    // The URL shows once the resource is marked syndicated; no source gate.
    /** @var Field $syndicationUrlField */
    $syndicationUrlField = $bp->field('syndication_url');
    /** @var array<string, string>|null $if */
    $if = $syndicationUrlField->get('if');
    expect($if)->toBe(['syndication_query' => 'equals true']);
})->with(['infographic', 'document', 'video', 'audio']);
