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
| clinical_expertise content-page blueprints (UAMS-Web/uams-statamic-archive#1055, D2)
|--------------------------------------------------------------------------
|
| The WordPress "AoE Content Item" (expertise_type=0) becomes two distinct
| blueprints living in the clinical_expertise collection alongside `expertise`
| — exactly as knowledge_bases carries knowledge_base/topic/article together.
| Each reuses its pages sibling's fieldsets verbatim, differing only in the
| template default (the clinical_expertise namespace). The structure tree is
| the sole source of the parent hierarchy — the decorative `parent_page`
| reference field was removed in UAMS-Web/uams-statamic-archive#1371 (see docs/DECISIONS.md).
*/

it('builds basic_page from the pages/basic_page fieldsets, retargeted to clinical_expertise', function () {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_expertise')->get('basic_page');

    expect($bp)->not->toBeNull();
    /** @var Field $content */
    $content = $bp->field('content');
    /** @var Field $template */
    $template = $bp->field('template');
    // The content field is the shared article_with_sets fieldtype (bard).
    expect($content->type())->toBe('bard');
    // Template defaults to the clinical_expertise namespace, not pages/*.
    expect($template->defaultValue())->toBe('clinical_expertise/basic_page');
});

it('builds landing_page from the pages/landing_page fieldsets, retargeted to clinical_expertise', function () {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_expertise')->get('landing_page');

    expect($bp)->not->toBeNull();
    /** @var Field $pageBuilder */
    $pageBuilder = $bp->field('page_builder');
    /** @var Field $template */
    $template = $bp->field('template');
    expect($pageBuilder->type())->toBe('replicator');
    expect($template->defaultValue())->toBe('clinical_expertise/landing_page');
});

it('leaves the pages blueprints untouched (fieldsets reused by reference, not edited)', function () {
    // The pages originals still default to their own namespace — proof the
    // fieldsets were imported, not forked/edited.
    /** @var Statamic\Fields\Blueprint $basicPage */
    $basicPage = Blueprint::in('collections/pages')->get('basic_page');
    /** @var Field $basicTemplate */
    $basicTemplate = $basicPage->field('template');
    expect($basicTemplate->defaultValue())->toBe('pages/default');

    /** @var Statamic\Fields\Blueprint $landingPage */
    $landingPage = Blueprint::in('collections/pages')->get('landing_page');
    /** @var Field $landingTemplate */
    $landingTemplate = $landingPage->field('template');
    expect($landingTemplate->defaultValue())->toBe('pages/page_landing');
});
