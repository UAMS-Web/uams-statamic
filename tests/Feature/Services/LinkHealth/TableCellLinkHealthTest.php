<?php

declare(strict_types=1);

use App\Services\LinkHealth\InternalResolver;
use App\Services\LinkHealth\LinkClassifier;
use App\Services\LinkHealth\LinkExtractor;
use App\Services\LinkHealth\LinkKind;
use App\Services\LinkHealth\LinkOccurrence;
use App\Services\LinkHealth\ResolutionState;
use Statamic\Entries\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\Redirects\Support\RedirectRuleMatcher;

/**
 * Link health over `table` field cells (#612).
 *
 * #582 resolves `statamic://entry::<id>` anchors in table cells at render time and drops the
 * `href` when the target is missing or unpublished. These cases run the extractor and the
 * resolver together, as `link-health:scan` does, so a dropped cell link is shown to reach a
 * report rather than vanish.
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/TableCellLinkHealthTest.php
 */
uses(StacheIsolatedTestCase::class);

const TC_COLLECTION = 'tc_pages';

/**
 * Persist a fixture entry, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function tcMakeEntry(string $id, array $data, bool $published = true): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(TC_COLLECTION);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published($published);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Extract every occurrence from the source entry, indexed by dotted location.
 *
 * @return array<string, LinkOccurrence>
 */
function tcExtract(): array
{
    $out = [];

    foreach (tcExtractList() as $occurrence) {
        $out[$occurrence->location()] = $occurrence;
    }

    return $out;
}

/**
 * Extract every occurrence from the source entry, in traversal order.
 *
 * Kept as a list where two occurrences share one location, as every link in one markup field does.
 *
 * @return list<LinkOccurrence>
 */
function tcExtractList(): array
{
    return (new LinkExtractor(new LinkClassifier(['www.uams.edu'], [])))->extractScoped(entryId: 'tc-source');
}

/**
 * The value and kind of each occurrence in one top-level field, in traversal order.
 *
 * @param  list<LinkOccurrence>  $found
 * @return list<array{0: string, 1: LinkKind}>
 */
function tcPairs(array $found, string $field): array
{
    $out = [];

    foreach ($found as $occurrence) {
        if ($occurrence->field === $field) {
            $out[] = [$occurrence->value, $occurrence->kind];
        }
    }

    return $out;
}

/**
 * Resolve one occurrence the way the scan does for an internal target.
 */
function tcState(LinkOccurrence $occurrence): ResolutionState
{
    return (new InternalResolver(new RedirectRuleMatcher))->resolve($occurrence)->state;
}

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make(TC_COLLECTION);
    /** @var Collection $collection */
    $collection = $collection->title('TC Pages');
    /** @var Collection $collection */
    $collection = $collection->routes('/tc/{slug}');
    $collection->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('tc_page');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setNamespace('collections.'.TC_COLLECTION);
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'table_data', 'field' => ['type' => 'table']],
        ['handle' => 'blurb', 'field' => ['type' => 'html']],
        ['handle' => 'intro', 'field' => ['type' => 'text']],
    ]]]]]]);
    $blueprint->save();

    tcMakeEntry('tc-live', ['title' => 'Kirk']);
    tcMakeEntry('tc-draft', ['title' => 'Spock'], published: false);
});

it('reports a cell link to a missing entry as missing, at a path naming the row and cell', function (): void {
    tcMakeEntry('tc-source', ['table_data' => [
        ['cells' => ['Name', 'Profile']],
        ['cells' => ['McCoy', '<a href="statamic://entry::tc-gone">Bones</a>']],
    ]]);

    $found = tcExtract();

    expect(array_keys($found))->toBe(['table_data.1.cells.1'])
        ->and($found['table_data.1.cells.1']->field)->toBe('table_data')
        ->and($found['table_data.1.cells.1']->path)->toBe([1, 'cells', 1])
        ->and($found['table_data.1.cells.1']->kind)->toBe(LinkKind::EntryReference)
        ->and(tcState($found['table_data.1.cells.1']))->toBe(ResolutionState::Missing);
});

it('reports an unpublished target as unpublished and a published one as healthy', function (): void {
    tcMakeEntry('tc-source', ['table_data' => [
        ['cells' => ['<a href="statamic://entry::tc-draft">Spock</a>', "<a href='statamic://entry::tc-live'>Kirk</a>"]],
    ]]);

    $found = tcExtract();

    expect(array_keys($found))->toBe(['table_data.0.cells.0', 'table_data.0.cells.1'])
        ->and(tcState($found['table_data.0.cells.0']))->toBe(ResolutionState::Unpublished)
        ->and(tcState($found['table_data.0.cells.1']))->toBe(ResolutionState::Ok);
});

// The render path keeps a query and fragment on the resolved URL (#582), so neither may make a
// live target read as missing.
it('reports a live target as healthy when the reference carries a query or fragment', function (): void {
    tcMakeEntry('tc-source', ['table_data' => [
        ['cells' => [
            '<a href="statamic://entry::tc-live#bridge">Kirk</a>',
            '<a href="statamic://entry::tc-live?deck=5">Kirk</a>',
            '<a href="statamic://entry::tc-gone#bridge">Gone</a>',
        ]],
    ]]);

    $found = tcExtract();

    expect(tcState($found['table_data.0.cells.0']))->toBe(ResolutionState::Ok)
        ->and(tcState($found['table_data.0.cells.1']))->toBe(ResolutionState::Ok)
        ->and(tcState($found['table_data.0.cells.2']))->toBe(ResolutionState::Missing);
});

it('extracts other cell links exactly as a markup field extracts the same markup', function (): void {
    $markup = '<a href="https://example.com/ext">e</a> <a href="/tc/kirk">r</a> <img src="https://www.uams.edu/i.png">';

    tcMakeEntry('tc-source', [
        'blurb' => $markup,
        'table_data' => [['cells' => [$markup]]],
    ]);

    $found = tcExtractList();

    expect(tcPairs($found, 'table_data'))->toBe(tcPairs($found, 'blurb'))
        ->and(tcPairs($found, 'blurb'))->toBe([
            ['https://example.com/ext', LinkKind::External],
            ['/tc/kirk', LinkKind::Internal],
            ['https://www.uams.edu/i.png', LinkKind::Internal],
        ]);
});

it('skips malformed rows and non-string cells without losing the cells around them', function (): void {
    tcMakeEntry('tc-source', [
        'intro' => 'https://example.com/text-field',
        'table_data' => [
            'not a row',
            ['no_cells' => ['<a href="https://example.com/ignored">x</a>']],
            ['cells' => 'not a list'],
            ['cells' => [null, 42, ['nested'], '<a href="https://example.com/kept">k</a>']],
        ],
    ]);

    $found = tcExtract();

    expect(array_keys($found))->toBe(['table_data.3.cells.3', 'intro'])
        ->and($found['table_data.3.cells.3']->value)->toBe('https://example.com/kept');
});

it('emits nothing for a table that is not a list', function (): void {
    tcMakeEntry('tc-source', ['table_data' => '<a href="https://example.com/x">x</a>']);

    expect(tcExtract())->toBe([]);
});
