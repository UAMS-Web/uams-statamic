<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\Mergers\CmsCertificationNumberMerger;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for CmsCertificationNumberMerger (builds the CMS Certification Number
 * PropertyValue identifier fragment).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/Mergers/CmsCertificationNumberMergerTest.php
 */
it('reports the hospital_ccn_id source key', function (): void {
    expect(app(CmsCertificationNumberMerger::class)->sourceKey())->toBe('hospital_ccn_id');
});

it('builds a CMS Certification Number PropertyValue identifier', function (): void {
    $fragment = app(CmsCertificationNumberMerger::class)->buildFragment('044001');

    /** @var array<string, mixed> $identifier */
    $identifier = $fragment['identifier'];
    expect($identifier['@type'])->toBe('PropertyValue');
    expect($identifier['name'])->toBe('CMS Certification Number');
    expect($identifier['value'])->toBe('044001');
    expect($identifier['alternateName'])->toContain('CCN');
    expect($identifier['alternateName'])->toContain('CMS Certification Number');
});

it('omits propertyID (no authoritative registry URI for the CCN)', function (): void {
    $identifier = app(CmsCertificationNumberMerger::class)->buildFragment('044001')['identifier'];

    expect($identifier)->not->toHaveKey('propertyID');
});
