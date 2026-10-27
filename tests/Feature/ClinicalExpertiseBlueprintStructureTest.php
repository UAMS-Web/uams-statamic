<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Area of Expertise blueprint — behavioral field config (UAMS-Web/uams-statamic-archive#800)
|--------------------------------------------------------------------------
|
| The UAMS-Web/uams-statamic-archive#800 restructure reshaped the Area of Expertise blueprint. These tests
| pin the load-bearing field behavior that survives it: the renamed related-
| expertise picker's query scopes, the excerpt-override fieldset resolution,
| the hidden data-preservation fields, and the renamed excerpt-override gate.
| Tab / section / field arrangement is intentionally not pinned.
*/

it('keeps the legacy short-description toggle as a hidden excerpt_query', function () {
    // The WP page-header "use intro as short description" toggle
    // (expertise_short_desc_query) has no live source yet, so it rides along as
    // a hidden data-preservation field.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_expertise')->get('clinical_expertise');
    /** @var Field $field */
    $field = $blueprint->field('excerpt_query');
    expect($field->get('visibility'))
        ->toBe('hidden');
});

it('preserves the WP page-header fields as hidden, following the header-selector convention', function () {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_expertise')->get('clinical_expertise');

    // The dropped page-header block may be revisited, so the four WP page-header
    // fields ride along as hidden fields reusing the canonical
    // uamsweb_header_selector_landing.* definitions.
    $preserved = [
        'title_options',
        'header_graphic',
        'header_marketing_landing_page',
        'header_hero',
    ];

    foreach ($preserved as $handle) {
        /** @var Field $field */
        $field = $bp->field($handle);
        expect($field->get('visibility'))->toBe('hidden', "{$handle} must be hidden");
    }
});

it('gates the related-expertise excerpt override on its renamed query field', function () {
    // The excerpt override's visibility condition follows the renamed query field
    // (clinical_expertise_associated_* -> clinical_expertise_related_*).
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_expertise')->get('clinical_expertise');
    /** @var Field $field */
    $field = $blueprint->field('clinical_expertise_related_fpage_excerpt');
    expect($field->get('if'))
        ->toBe(['clinical_expertise_related_fpage_excerpt_query' => 'equals false']);
});

it('scopes the related areas-of-expertise picker to the expertise blueprint only', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_expertise')->get('clinical_expertise');
    /** @var Field $field */
    $field = $blueprint->field('clinical_expertise_related');

    expect($field->get('query_scopes'))->toBe([
        'exclude_self',
        'exclude_descendants',
        'entries_by_blueprint_clinical_expertise',
    ]);
});

it('resolves the excerpt-override fields through the renamed ontology fieldset handle', function () {
    // uamsweb_common_fad.ontology_fpage_short_desc -> ontology_fpage_excerpt (textarea).
    // A broken fieldset reference would fail to resolve this to its textarea type.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_expertise')->get('clinical_expertise');
    /** @var Field $field */
    $field = $blueprint->field('clinical_providers_fpage_excerpt');
    expect($field->type())
        ->toBe('textarea');
});
