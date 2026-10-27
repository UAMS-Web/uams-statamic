<?php

declare(strict_types=1);

use App\Rules\UniqueIdentifier;
use Illuminate\Support\Facades\Validator;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Records the cost of the naive scan on the largest affected collection (UAMS-Web/uams-statamic-archive#2152).
 *
 * The lookup approach is the decision recorded on UAMS-Web/uams-statamic-archive#2221: scan the collection and
 * compare in PHP, rather than build an index. The acceptance criterion attached
 * to that decision requires the cost to be measured rather than assumed, and
 * requires re-measuring to be the trigger for revisiting it -- so this is a
 * standing measurement rather than a one-off note in a pull request.
 *
 * `people` is the largest affected collection at 43 entries on disk;
 * `clinical_locations` and `clinical_providers` hold none. A person save
 * validates SIX wired fields, and each rule instance performs its own scan, so
 * the per-save figure is what matters rather than the per-rule one.
 *
 * The threshold is deliberately loose. It is a guard against an accidental
 * change in complexity -- an N+1 against the entry store, say -- and not a
 * benchmark; a tight bound here would be flaky on a busy machine and would fail
 * for reasons that have nothing to do with this code.
 *
 * @command  php artisan test --compact tests/Feature/Rules/UniqueIdentifierCostTest.php
 */
uses(StacheIsolatedTestCase::class);

it('scans a people-sized collection well inside a loose per-save bound', function (): void {
    $collection = Collection::make('people');
    $collection->title('People');
    $collection->save();

    // Match the largest affected collection as it stands on disk
    foreach (range(1, 43) as $n) {
        $entry = Entry::make();

        if (! $entry instanceof ConcreteEntry) {
            throw new RuntimeException('Entry::make() did not return an entry.');
        }

        $entry->collection('people');
        $entry->slug("person-{$n}");
        $entry->data([
            'title' => "Person {$n}",
            'uams_workday_id' => [
                'workday_universal_id' => "U-{$n}",
                'workday_employee_id' => "E-{$n}",
                'workday_contingent_worker_id' => "C-{$n}",
                'workday_student_id' => "S-{$n}",
            ],
            'npi' => (string) (1000000000 + $n),
            'epic_ser_id' => (string) (900000 + $n),
        ]);
        $entry->save();
    }

    $pool = [
        'uams_workday_id.workday_universal_id',
        'uams_workday_id.workday_employee_id',
        'uams_workday_id.workday_contingent_worker_id',
        'uams_workday_id.workday_student_id',
    ];

    // One save validates all six wired fields, each running its own scan
    $payload = [
        'uams_workday_id' => [
            'workday_universal_id' => 'U-NEW',
            'workday_employee_id' => 'E-NEW',
            'workday_contingent_worker_id' => 'C-NEW',
            'workday_student_id' => 'S-NEW',
        ],
        'npi' => '1999999999',
        'epic_ser_id' => '999999',
    ];

    $rules = [];

    foreach ($pool as $handle) {
        $rules[$handle] = [new UniqueIdentifier('people', null, ...$pool)];
    }

    $rules['npi'] = [new UniqueIdentifier('people', null, 'npi')];
    $rules['epic_ser_id'] = [new UniqueIdentifier('people', null, 'epic_ser_id')];

    // Warm the validator machinery before timing EITHER arm. Without this the
    // first `Validator::make` of the process pays one-off setup and lands in
    // whichever arm runs first -- which inverted this measurement once already:
    // run alone the before-arm read 5.0 ms and the delta looked like 3.3 ms,
    // run after other tests it read 0.1 ms and the same delta was 8.4 ms. The
    // second is the true figure; the first was warm-up subtracted from itself.
    Validator::make(['warm' => 'x'], ['warm' => ['nullable']])->passes();

    // BEFORE: the same six fields validated with no uniqueness rule attached,
    // which is what a save cost before this change
    $before = microtime(true);
    $baseline = Validator::make($payload, array_fill_keys(array_keys($payload), ['nullable']));
    expect($baseline->passes())->toBeTrue();
    $beforeMs = (microtime(true) - $before) * 1000;

    // AFTER: the same payload with all six rules wired. A clean payload is the
    // worst case -- every rule scans every entry with no early exit on a
    // collision -- so this is the ceiling rather than the typical figure.
    $after = microtime(true);
    $validator = Validator::make($payload, $rules);
    expect($validator->passes())->toBeTrue();
    $afterMs = (microtime(true) - $after) * 1000;

    // Printed so a run carries the numbers rather than only the verdict, which
    // is what makes re-measuring the trigger the criterion asks for
    fwrite(STDERR, sprintf(
        "\n  [#2152] six-field save against 43 entries -- before: %.1f ms, after: %.1f ms, added: %.1f ms\n",
        $beforeMs,
        $afterMs,
        $afterMs - $beforeMs
    ));

    expect($afterMs)->toBeLessThan(2000.0);
});
