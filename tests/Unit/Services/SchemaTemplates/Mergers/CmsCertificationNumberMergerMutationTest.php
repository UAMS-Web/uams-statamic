<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\Mergers\CmsCertificationNumberMerger;
use Tests\TestCase;

uses(TestCase::class);

covers(CmsCertificationNumberMerger::class);

/**
 * Narrow mutate coverage for CmsCertificationNumberMerger.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/Mergers/CmsCertificationNumberMergerMutationTest.php
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

it('reports the hospital_ccn_id source key', function (): void {
    expect((new CmsCertificationNumberMerger)->sourceKey())->toBe('hospital_ccn_id');
});

it('builds the exact CMS Certification Number PropertyValue fragment', function (): void {
    expect((new CmsCertificationNumberMerger)->buildFragment('044001'))->toBe([
        'identifier' => [
            '@type' => 'PropertyValue',
            'name' => 'CMS Certification Number',
            'alternateName' => [
                'CCN',
                'CCN number',
                'CMS CCN',
                'CMS Certification Number',
                'CMS certification number',
                'Centers for Medicare & Medicaid Services Certification Number',
                'Centers for Medicare and Medicaid Services Certification Number',
                'Medicare provider number',
                'Medicare/Medicaid provider number',
                'OSCAR number',
                'Online Survey Certification and Reporting number',
                'provider number',
            ],
            'value' => '044001',
        ],
    ]);
});
