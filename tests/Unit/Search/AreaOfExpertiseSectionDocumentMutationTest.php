<?php

declare(strict_types=1);

use App\Search\AreaOfExpertiseSectionDocument;
use Statamic\Search\Result;
use Tests\TestCase;

uses(TestCase::class);

covers(AreaOfExpertiseSectionDocument::class);

/**
 * Narrow mutate coverage for App\Search\AreaOfExpertiseSectionDocument.
 *
 * @command  php artisan test --compact tests/Unit/Search/AreaOfExpertiseSectionDocumentMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * Build a document with the default fixture values.
 */
function aoeSectionDocument(
    string $aoeId = 'aoe-1',
    string $section = 'locations',
    string $title = 'Cancer Care: Locations',
    string $url = '/cancer-care/locations',
    string $site = 'default',
): AreaOfExpertiseSectionDocument {
    return new AreaOfExpertiseSectionDocument(
        $aoeId,
        $section,
        $title,
        $url,
        $site,
    );
}

it('builds a stable reference from the prefix, aoe id, and section', function (): void {
    // ConcatenateEqualToPeriod / similar would break the provider round-trip.
    expect(AreaOfExpertiseSectionDocument::reference('aoe-9', 'providers'))
        ->toBe('clinical_expertise_section::aoe-9::providers')
        ->and(aoeSectionDocument()->getSearchReference())
        ->toBe('clinical_expertise_section::aoe-1::locations');
});

it('exposes title and url via augmentedArrayData', function (): void {
    expect(aoeSectionDocument()->augmentedArrayData())->toBe([
        'title' => 'Cancer Care: Locations',
        'url' => '/cancer-care/locations',
    ]);
});

it('returns the title search value and null for unknown fields', function (): void {
    $doc = aoeSectionDocument();

    expect($doc->getSearchValue('title'))->toBe('Cancer Care: Locations')
        ->and($doc->getSearchValue('url'))->toBe('/cancer-care/locations')
        ->and($doc->getSearchValue('page_builder'))->toBeNull();
});

it('wraps itself in a Result typed by the reference prefix', function (): void {
    $doc = aoeSectionDocument();
    $result = $doc->toSearchResult();

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->getSearchable())->toBe($doc)
        ->and($result->getType())->toBe(AreaOfExpertiseSectionDocument::REFERENCE_PREFIX);
});

it('resolves queryable status, site, and area-of-expertise id', function (): void {
    $doc = aoeSectionDocument(
        aoeId: 'aoe-42',
        site: 'health',
    );

    expect($doc->getQueryableValue('status'))->toBe('published')
        ->and($doc->getQueryableValue('site'))->toBe('health')
        ->and($doc->getQueryableValue(AreaOfExpertiseSectionDocument::AREA_OF_EXPERTISE_ID_FIELD))
        ->toBe('aoe-42')
        ->and($doc->getQueryableValue('title'))->toBe('Cancer Care: Locations')
        ->and($doc->getQueryableValue('missing'))->toBeNull();
});
