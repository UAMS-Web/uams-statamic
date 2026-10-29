<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\Mergers\AhaidMerger;
use App\Services\SchemaTemplates\Mergers\CageMerger;
use App\Services\SchemaTemplates\Mergers\CmsCertificationNumberMerger;
use App\Services\SchemaTemplates\Mergers\CmsSpecialtyCodeMerger;
use App\Services\SchemaTemplates\Mergers\DunsMerger;
use App\Services\SchemaTemplates\Mergers\EinMerger;
use App\Services\SchemaTemplates\Mergers\GlnMerger;
use App\Services\SchemaTemplates\Mergers\GoogleCidMerger;
use App\Services\SchemaTemplates\Mergers\GoogleScholarAuthorIdMerger;
use App\Services\SchemaTemplates\Mergers\GoogleTagManagerContainerIdMerger;
use App\Services\SchemaTemplates\Mergers\HealthCareProviderTaxonomyCodeMerger;
use App\Services\SchemaTemplates\Mergers\IpedsMerger;
use App\Services\SchemaTemplates\Mergers\Isco08CodeMerger;
use App\Services\SchemaTemplates\Mergers\IsicRev4CodeMerger;
use App\Services\SchemaTemplates\Mergers\Iso6523CodeMerger;
use App\Services\SchemaTemplates\Mergers\LegalEntityIdentifierMerger;
use App\Services\SchemaTemplates\Mergers\NaicsMerger;
use App\Services\SchemaTemplates\Mergers\NpiMerger;
use App\Services\SchemaTemplates\Mergers\OpeIdMerger;
use App\Services\SchemaTemplates\Mergers\OrcidMerger;
use App\Services\SchemaTemplates\Mergers\PmidMerger;
use App\Services\SchemaTemplates\Mergers\PubmedAuthorIdMerger;
use App\Services\SchemaTemplates\Mergers\ResearcherIdMerger;
use App\Services\SchemaTemplates\Mergers\RxcuiMerger;
use App\Services\SchemaTemplates\Mergers\SchemaIdentifierMergerInterface;
use App\Services\SchemaTemplates\Mergers\ScopusAuthorIdMerger;
use App\Services\SchemaTemplates\Mergers\TickerSymbolMerger;
use App\Services\SchemaTemplates\Mergers\UeiMerger;
use App\Services\SchemaTemplates\Mergers\VaStationIdMerger;
use App\Services\SchemaTemplates\Mergers\VaSubstationIdMerger;

covers(
    AhaidMerger::class,
    CageMerger::class,
    CmsCertificationNumberMerger::class,
    CmsSpecialtyCodeMerger::class,
    DunsMerger::class,
    EinMerger::class,
    GlnMerger::class,
    GoogleCidMerger::class,
    GoogleScholarAuthorIdMerger::class,
    GoogleTagManagerContainerIdMerger::class,
    HealthCareProviderTaxonomyCodeMerger::class,
    IpedsMerger::class,
    Isco08CodeMerger::class,
    IsicRev4CodeMerger::class,
    Iso6523CodeMerger::class,
    LegalEntityIdentifierMerger::class,
    NaicsMerger::class,
    NpiMerger::class,
    OpeIdMerger::class,
    OrcidMerger::class,
    PmidMerger::class,
    PubmedAuthorIdMerger::class,
    ResearcherIdMerger::class,
    RxcuiMerger::class,
    ScopusAuthorIdMerger::class,
    TickerSymbolMerger::class,
    UeiMerger::class,
    VaStationIdMerger::class,
    VaSubstationIdMerger::class,
);

/**
 * Pins every dependency-free identifier merger's source key and fragment whole.
 *
 * Each merger returns a constant fragment with the replicator value passed
 * through, so the fixture holds each class's output for the value `VALUE-1`
 * exactly as `main` produced it at `a969e6227`. A strict comparison of the
 * whole array kills every removed or reordered alias, changed label, dropped
 * key and swapped concatenation. `OnetSocOccupationCodeMerger` is not here: it
 * reads the O*NET overview service and has its own test. No container is
 * booted, so each mutant runs in well under a second.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/Mergers/IdentifierMergerFragmentsTest.php
 */

/**
 * Read the pinned fragments, keyed by merger short class name.
 *
 * @return array<string, array{sourceKey: string, fragment: array<string, mixed>}> Pinned output per merger.
 */
function pinnedIdentifierMergerFragments(): array
{
    $path = __DIR__.'/../../../../Fixtures/SchemaTemplates/identifier-merger-fragments.json';
    $json = file_get_contents($path);

    if ($json === false) {
        throw new RuntimeException('Cannot read the pinned fixture: '.$path);
    }

    /** @var array<string, array{sourceKey: string, fragment: array<string, mixed>}> $decoded */
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

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

it('pins every merger in the directory except the O*NET one', function (): void {
    $onDisk = array_map(
        static fn (string $path): string => basename($path, '.php'),
        glob(__DIR__.'/../../../../../app/Services/SchemaTemplates/Mergers/*Merger.php') ?: [],
    );
    sort($onDisk);

    $pinned = array_keys(pinnedIdentifierMergerFragments());
    $pinned[] = 'OnetSocOccupationCodeMerger';
    sort($pinned);

    expect($pinned)->toHaveCount(30)
        ->and($onDisk)->toBe($pinned);
});

it('reports its pinned source key and builds its pinned fragment', function (string $short): void {
    $pinned = pinnedIdentifierMergerFragments()[$short];

    /** @var class-string<SchemaIdentifierMergerInterface> $class */
    $class = 'App\\Services\\SchemaTemplates\\Mergers\\'.$short;
    $merger = new $class;

    expect($merger->sourceKey())->toBe($pinned['sourceKey'])
        ->and($merger->buildFragment('VALUE-1'))->toBe($pinned['fragment']);
})->with(fn (): array => array_keys(pinnedIdentifierMergerFragments()));

it('passes the replicator value through unchanged', function (string $short): void {
    /** @var class-string<SchemaIdentifierMergerInterface> $class */
    $class = 'App\\Services\\SchemaTemplates\\Mergers\\'.$short;

    $encoded = json_encode((new $class)->buildFragment('other value'), JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('VALUE-1')
        ->and($encoded)->toContain('other value');
})->with(fn (): array => array_keys(pinnedIdentifierMergerFragments()));
