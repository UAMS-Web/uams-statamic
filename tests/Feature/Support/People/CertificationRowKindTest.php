<?php

declare(strict_types=1);

use App\Modifiers\CertificationHeading;
use App\Modifiers\CertificationKind;
use App\Modifiers\CertificationsByKind;
use App\Support\People\CertificationRowKind;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Term;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks how a `certifications` row is classified, ordered and labeled by kind
 * (UAMS-Web/uams-statamic-archive#2973), against one real `certifications` term of each kind in content.
 *
 * Mutation: with tests/Feature/Views/CertificationsListByKindTest.php and
 * tests/Feature/CertificationCertificateSchemaDatesTest.php, `CertificationRowKind` 98 of 98 killed with
 * two annotated equivalents, `CertificationKind` 3 of 3, `CertificationHeading` 21 of 21 and
 * `CertificationsByKind` 1 of 1 (UAMS-Web/uams-statamic-archive#2973), run serial, `--covered-only`, and scoped with `--path` and `--class`.
 *
 * @covers \App\Support\People\CertificationRowKind
 * @covers \App\Modifiers\CertificationHeading
 * @covers \App\Modifiers\CertificationKind
 * @covers \App\Modifiers\CertificationsByKind
 *
 * @command  php artisan test --compact tests/Feature/Support/People/CertificationRowKindTest.php
 */

/**
 * Slugs of real `certifications` terms, one per kind.
 *
 * @return array<string, string>
 */
function certificationRowKindSlugs(): array
{
    return [
        'board' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery',
        'fpd' => 'american-board-of-surgery-metabolic-and-bariatric-surgery-focused-practice-designation',
        'certification' => 'american-academy-of-nurse-practitioners-certification-board-psychiatric-mental-health-nurse-practitioner',
        'certificate' => 'american-society-for-metabolic-and-bariatric-surgery-fundamentals-for-the-metabolic-and-bariatric-surgery-patient-assessment-based-certificate',
    ];
}

/**
 * A stored certifications row for a term and set.
 *
 * @return array<string, mixed>
 */
function certificationRowKindRow(string $id, string $slugKey, string $type = 'other_certification'): array
{
    return [
        'id' => $id,
        'type' => $type,
        'enabled' => true,
        'activity' => certificationRowKindSlugs()[$slugKey],
    ];
}

/**
 * The people blueprint's `certifications` replicator field.
 */
function certificationRowKindField(): Field
{
    return mustBe(
        Field::class,
        mustBe(Statamic\Fields\Blueprint::class, Blueprint::find('collections.people.person'), 'the person blueprint')->field('certifications'),
        'the certifications field'
    );
}

it('classifies a row by its set and its term kind', function (string $type, mixed $activity, string $kind) {
    expect(CertificationRowKind::forRow(['type' => $type, 'activity' => $activity]))->toBe($kind);
})->with([
    'a board set' => ['board_certification', certificationRowKindSlugs()['board'], 'board_certification'],
    'a board set whatever its term says' => ['board_certification', certificationRowKindSlugs()['fpd'], 'board_certification'],
    'a designation' => ['other_certification', certificationRowKindSlugs()['fpd'], 'focused_practice_designation'],
    'a certification' => ['other_certification', certificationRowKindSlugs()['certification'], 'certification'],
    'a certificate' => ['other_certification', certificationRowKindSlugs()['certificate'], 'assessment_based_certificate'],
    'a canonical term id' => ['other_certification', 'certifications::'.certificationRowKindSlugs()['certificate'], 'assessment_based_certificate'],
    'a list holding the term' => ['other_certification', [certificationRowKindSlugs()['fpd']], 'focused_practice_designation'],
    'a collection holding the term' => ['other_certification', collect([certificationRowKindSlugs()['certificate']]), 'assessment_based_certificate'],
    'an unknown term' => ['other_certification', 'no-such-certification', 'certification'],
    'no term' => ['other_certification', null, 'certification'],
]);

it('classifies a board term in an other set as a certification', function () {
    expect(CertificationRowKind::forRow(certificationRowKindRow('x', 'board')))->toBe('certification');
});

it('reads a field value wrapping the term', function () {
    $term = Term::find('certifications::'.certificationRowKindSlugs()['fpd']);

    expect(CertificationRowKind::forActivity($term, 'other_certification'))->toBe('focused_practice_designation')
        ->and(CertificationRowKind::forActivity(new Value($term), 'other_certification'))->toBe('focused_practice_designation')
        ->and(CertificationRowKind::forActivity(new Value(null, 'activity', null, null), 'other_certification'))->toBe('certification');
});

it('classifies an augmented row from its raw term', function () {
    $rows = mustBeArray(
        (new Value([certificationRowKindRow('c', 'certificate')], 'certifications', certificationRowKindField()->fieldtype()))->value(),
        'the augmented rows'
    );

    expect($rows[0])->toBeInstanceOf(Values::class)
        ->and(CertificationRowKind::forRow($rows[0]))->toBe('assessment_based_certificate')
        ->and(CertificationRowKind::isCertificate($rows[0]))->toBeTrue()
        ->and(CertificationRowKind::forRow('not a row'))->toBe('certification')
        ->and(CertificationRowKind::isCertificate(certificationRowKindRow('o', 'certification')))->toBeFalse();
});

it('orders rows board, designation, certification, certificate, keeping entry order', function () {
    $rows = [
        certificationRowKindRow('certificate-1', 'certificate'),
        certificationRowKindRow('certification-1', 'certification'),
        certificationRowKindRow('fpd-1', 'fpd'),
        certificationRowKindRow('certification-2', 'certification'),
        certificationRowKindRow('board-1', 'board', 'board_certification'),
        certificationRowKindRow('certificate-2', 'certificate'),
        certificationRowKindRow('board-2', 'board', 'board_certification'),
    ];

    $expected = ['board-1', 'board-2', 'fpd-1', 'certification-1', 'certification-2', 'certificate-1', 'certificate-2'];

    expect(array_column(CertificationRowKind::sortRows($rows), 'id'))->toBe($expected)
        ->and(array_column(CertificationRowKind::sortRows(collect(array_combine(range(10, 16), $rows))), 'id'))->toBe($expected)
        ->and(CertificationRowKind::sortRows(null))->toBe([])
        ->and(CertificationRowKind::sortRows([]))->toBe([]);
});

it('labels a row by its kind', function (string $kind, string $name, string $heading) {
    expect(CertificationRowKind::heading($kind, $name))->toBe($heading);
})->with([
    'a board certification' => ['board_certification', 'Orthopaedic Surgery', 'Board Certification in Orthopaedic Surgery'],
    'a certification' => ['certification', 'Psychiatric Mental Health Nurse Practitioner', 'Psychiatric Mental Health Nurse Practitioner'],
    'a designation' => ['focused_practice_designation', 'Hospital Medicine', 'Focused Practice Designation in Hospital Medicine'],
    'a designation that names its kind' => ['focused_practice_designation', 'Metabolic and Bariatric Surgery Focused Practice Designation', 'Focused Practice Designation in Metabolic and Bariatric Surgery'],
    'a designation that names its kind in lowercase' => ['focused_practice_designation', 'Hospital Medicine focused practice designation', 'Focused Practice Designation in Hospital Medicine'],
    'a designation named only by its kind' => ['focused_practice_designation', ' Focused Practice Designation', 'Focused Practice Designation in  Focused Practice Designation'],
    'a certificate' => ['assessment_based_certificate', 'Obesity Medicine Fundamentals', 'Obesity Medicine Fundamentals (Assessment-Based Certificate)'],
    'a certificate that names its kind' => ['assessment_based_certificate', 'Fundamentals Assessment-Based Certificate', 'Fundamentals Assessment-Based Certificate'],
    'a certificate that names its kind in lowercase' => ['assessment_based_certificate', 'Fundamentals assessment-based certificate', 'Fundamentals assessment-based certificate'],
]);

it('reads the term from a collection or list of terms, as a schema template overlay holds it', function () {
    $certificate = Term::find('certifications::'.certificationRowKindSlugs()['certificate']);
    $designation = Term::find('certifications::'.certificationRowKindSlugs()['fpd']);

    expect(CertificationRowKind::forActivity(collect([$certificate]), 'other_certification'))->toBe('assessment_based_certificate')
        ->and(CertificationRowKind::forActivity(collect([$designation, $certificate]), 'other_certification'))->toBe('focused_practice_designation')
        ->and(CertificationRowKind::forActivity([$certificate], 'other_certification'))->toBe('assessment_based_certificate')
        ->and(CertificationRowKind::forActivity(collect(), 'other_certification'))->toBe('certification')
        ->and(CertificationRowKind::forActivity(collect([$certificate]), 'board_certification'))->toBe('board_certification');
});

it('names the kind through the modifier, with or without the set handle', function () {
    $modifier = new CertificationKind;
    $certificate = Term::find('certifications::'.certificationRowKindSlugs()['certificate']);

    expect($modifier->index(collect([$certificate]), ['other_certification'], []))->toBe('assessment_based_certificate')
        ->and($modifier->index(collect([$certificate]), [], []))->toBe('assessment_based_certificate')
        ->and($modifier->index(collect([$certificate]), ['board_certification'], []))->toBe('board_certification')
        ->and($modifier->index(certificationRowKindSlugs()['fpd'], [], []))->toBe('focused_practice_designation')
        ->and($modifier->index(null, [], []))->toBe('certification');
});

it('builds the heading from the augmented term and the set handle', function () {
    $modifier = new CertificationHeading;
    $unnamed = mustBe(Statamic\Taxonomies\Term::class, Term::make('unnamed'), 'an unnamed term');
    $unnamed->taxonomy('certifications');
    $term = Term::find('certifications::'.certificationRowKindSlugs()['fpd']);

    expect($modifier->index($term, ['other_certification'], []))->toBe('Focused Practice Designation in Metabolic and Bariatric Surgery')
        ->and($modifier->index(new Value($term), ['board_certification'], []))->toBe('Board Certification in Metabolic and Bariatric Surgery Focused Practice Designation')
        ->and($modifier->index($term, [], []))->toBe('Focused Practice Designation in Metabolic and Bariatric Surgery')
        ->and($modifier->index(['name' => 'Orthopaedic Surgery'], ['board_certification'], []))->toBe('Board Certification in Orthopaedic Surgery')
        ->and($modifier->index(['name' => 'Fundamentals Assessment-Based Certificate'], ['other_certification'], []))->toBe('Fundamentals Assessment-Based Certificate')
        ->and($modifier->index(['name' => 7], ['board_certification'], []))->toBe('')
        ->and($modifier->index(null, ['other_certification'], []))->toBe('')
        ->and($modifier->index('a slug', ['other_certification'], []))->toBe('')
        ->and($modifier->index($unnamed->in('default'), ['board_certification'], []))->toBe('Board Certification in ');
});

it('orders augmented rows through the modifier', function () {
    $rows = [
        certificationRowKindRow('certificate', 'certificate'),
        certificationRowKindRow('board', 'board', 'board_certification'),
    ];

    expect(array_column((new CertificationsByKind)->index($rows, [], []), 'id'))->toBe(['board', 'certificate']);
});
