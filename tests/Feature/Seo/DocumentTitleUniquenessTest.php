<?php

declare(strict_types=1);

use App\Services\Seo\DocumentTitle;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Sites\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * UAMS-Web/uams-statamic-archive#1330 "verified against the current content set": every rendered provider
 * `<title>` is unique within its site once App\Services\Seo\DocumentTitle has
 * disambiguated collisions. Skips cleanly without the content/ submodule.
 *
 * Titles need only be unique per site (canonical URLs are per-host, and the
 * disambiguator scopes its collision scan to the entry's site), so uniqueness is
 * asserted within each site rather than globally.
 *
 * @command  php artisan test --compact tests/Feature/Seo/DocumentTitleUniquenessTest.php
 */
it('renders a unique provider document_title within each site', function (): void {
    $this->requireContentCollection('people');

    $service = app(DocumentTitle::class);

    /** @var array<string, list<string>> $titlesBySite */
    $titlesBySite = [];

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();

    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'people');

    /** @var Collection<int, EntryContract> $people */
    $people = $query->get();

    $people->each(function (EntryContract $person) use ($service, &$titlesBySite): void {
        $person = mustBe(Statamic\Entries\Entry::class, $person, 'person');
        /** @var Site|null $personSite */
        $personSite = $person->site();
        /** @var string $site */
        $site = $personSite?->handle() ?? 'default';
        $titlesBySite[$site][] = $service->forPeople($person);
    });

    if ($titlesBySite === []) {
        ContentSubmodule::reportMissing('people entries to check titles across');
    }

    foreach ($titlesBySite as $site => $titles) {
        /** @var list<string> $duplicates */
        $duplicates = collect($titles)->duplicates()->values()->all();

        expect($duplicates)->toBe(
            [],
            "Duplicate provider titles within site [{$site}]: ".implode(' | ', $duplicates)
        );
    }
});
