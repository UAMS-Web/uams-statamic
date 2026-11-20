<?php

declare(strict_types=1);

use App\Modifiers\CertificationKind;
use App\Modifiers\CertificationsByKind;

covers(CertificationKind::class);
covers(CertificationsByKind::class);

/**
 * Behavior of the `certification_kind` and `certifications_by_kind` Antlers modifiers
 * (app/Modifiers/CertificationKind.php, app/Modifiers/CertificationsByKind.php): each
 * hands its input to `CertificationRowKind`. A board set names the kind whatever the
 * term says, so rows and activities without a term isolate the modifiers' own wiring
 * without booting the application. The classification and ordering against real terms
 * are covered by tests/Feature/Support/People/CertificationRowKindTest.php.
 *
 * Mutation: `CertificationKind` 3 of 3 and `CertificationsByKind` 1 of 1 tested without
 * `--covered-only` (#819). The Feature test kills the same mutants, but its children can
 * exceed the plugin's per-mutant time limit on a two-core Linux machine and are then
 * scored as timeouts.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/CertificationKindModifiersTest.php
 */
it('reads the set handle from its first parameter only', function () {
    $modifier = new CertificationKind;

    expect($modifier->index(null, ['board_certification'], []))->toBe('board_certification')
        ->and($modifier->index(null, ['other_certification', 'board_certification'], []))->toBe('certification')
        ->and($modifier->index(null, [], []))->toBe('certification');
});

it('returns the rows it orders', function () {
    $rows = [
        ['id' => 'first', 'type' => 'board_certification'],
        ['id' => 'second', 'type' => 'board_certification'],
    ];

    expect((new CertificationsByKind)->index($rows, [], []))->toBe($rows);
});
