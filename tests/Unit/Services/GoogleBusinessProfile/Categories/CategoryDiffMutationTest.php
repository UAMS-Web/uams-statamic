<?php

declare(strict_types=1);

use App\Services\GoogleBusinessProfile\Categories\CategoryDiff;
use App\Services\GoogleBusinessProfile\Categories\CategoryList;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\TermCollection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(CategoryDiff::class);

/**
 * Narrow mutate coverage for App\Services\GoogleBusinessProfile\Categories\CategoryDiff.
 *
 * @command  php artisan test --compact tests/Unit/Services/GoogleBusinessProfile/Categories/CategoryDiffMutationTest.php
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
 * A LocalizedTerm stub with the given slug, title, and GCID values.
 */
function categoryDiffTerm(
    mixed $slug,
    mixed $title,
    mixed $gcid,
): LocalizedTerm {
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('slug')->andReturn($slug);
    $term->shouldReceive('value')->with('title')->andReturn($title);
    $term->shouldReceive('value')->with('gcid')->andReturn($gcid);

    return $term;
}

it('classifies new, renamed, removed, without-gcid, and passes malformed through', function (): void {
    Term::shouldReceive('whereTaxonomy')
        ->once()
        ->with(CategoryDiff::TAXONOMY)
        ->andReturn(TermCollection::make([
            categoryDiffTerm('keep', 'Keep', 'keep'),
            // Trailing space on the title is trimmed before compare.
            categoryDiffTerm('same', 'Same ', 'same'),
            categoryDiffTerm('renamed', 'Old Title', 'renamed'),
            categoryDiffTerm('gone', 'Gone', 'gone'),
            // Empty / non-scalar GCID → withoutGcid (stringValue).
            categoryDiffTerm('no-gcid', 'No GCID', ''),
            categoryDiffTerm('null-gcid', 'Null GCID', null),
            // Non-scalar slug/title become empty strings in the withoutGcid row.
            categoryDiffTerm(['x'], ['y'], ''),
        ]));

    $malformed = [
        ['line' => 4, 'content' => 'bad', 'reason' => 'missing tab'],
    ];

    $report = (new CategoryDiff)->compare(new CategoryList(
        [
            'keep' => 'Keep',
            'same' => 'Same',
            'renamed' => 'New Title',
            // Sorted keys prove ksort() on $new.
            'zoo' => 'Zoo',
            'aquarium' => 'Aquarium',
        ],
        $malformed,
    ));

    expect($report->new)->toBe([
        'aquarium' => 'Aquarium',
        'zoo' => 'Zoo',
    ])
        ->and($report->renamed)->toBe([
            [
                'gcid' => 'renamed',
                'slug' => 'renamed',
                'title' => 'Old Title',
                'name' => 'New Title',
            ],
        ])
        ->and($report->removed)->toBe([
            [
                'gcid' => 'gone',
                'slug' => 'gone',
                'title' => 'Gone',
            ],
        ])
        ->and($report->withoutGcid)->toBe([
            ['slug' => 'no-gcid', 'title' => 'No GCID'],
            ['slug' => 'null-gcid', 'title' => 'Null GCID'],
            ['slug' => '', 'title' => ''],
        ])
        ->and($report->malformed)->toBe($malformed);
});

it('casts a scalar non-string title through stringValue before comparing', function (): void {
    Term::shouldReceive('whereTaxonomy')
        ->once()
        ->with(CategoryDiff::TAXONOMY)
        ->andReturn(TermCollection::make([
            categoryDiffTerm('n911', 911, 'n911'),
        ]));

    $report = (new CategoryDiff)->compare(new CategoryList([
        'n911' => '911',
    ]));

    expect($report->renamed)->toBe([])
        ->and($report->removed)->toBe([])
        ->and($report->new)->toBe([]);
});

it('reports a term whose GCID the list carries only on an unreadable line as unreadable, not removed', function (): void {
    Term::shouldReceive('whereTaxonomy')
        ->once()
        ->with(CategoryDiff::TAXONOMY)
        ->andReturn(TermCollection::make([
            categoryDiffTerm('gone', 'Gone', 'gone'),
            categoryDiffTerm('unread', 'Unread', 'unread'),
            // Read once and rejected once: the read line decides, so it is compared as usual.
            categoryDiffTerm('read-twice', 'Read twice', 'read_twice'),
        ]));

    // UAMS-Web/uams-statamic-archive#2944: `unread` and `read_twice` are GCIDs read from rejected lines; `orphan` has no term
    $report = (new CategoryDiff)->compare(new CategoryList(
        ['read_twice' => 'Read twice'],
        [],
        ['unread', 'read_twice', 'orphan'],
    ));

    expect($report->removed)->toBe([['gcid' => 'gone', 'slug' => 'gone', 'title' => 'Gone']])
        ->and($report->unreadable)->toBe([['gcid' => 'unread', 'slug' => 'unread', 'title' => 'Unread']])
        ->and($report->renamed)->toBe([])
        ->and($report->new)->toBe([])
        ->and($report->counts())->toBe(['new' => 0, 'renamed' => 0, 'removed' => 1, 'unreadable' => 1, 'without_gcid' => 0, 'malformed' => 0])
        ->and($report->toArray()['unreadable'])->toBe([['gcid' => 'unread', 'slug' => 'unread', 'title' => 'Unread']]);
});
