<?php

declare(strict_types=1);

use App\Support\People\CertificationActivityValidity;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Renders the one "Certifications" list (UAMS-Web/uams-statamic-archive#2973) from augmented replicator rows:
 * rows ordered board certification, focused practice designation, other
 * certification, then assessment-based certificate, each labeled by kind, a
 * certificate showing only its issued date, and board and other-certification
 * rows byte-identical to the rendering before UAMS-Web/uams-statamic-archive#2973.
 *
 * Rows go through the people blueprint's `certifications` replicator, and the
 * computed validity merge, so the template sees augmented values rather than raw
 * arrays. Terms are real `certifications` terms in content, one of each kind.
 *
 * @command  php artisan test --compact tests/Feature/Views/CertificationsListByKindTest.php
 */

/**
 * The people blueprint's `certifications` replicator field.
 */
function certificationsListByKindField(): Field
{
    return mustBe(
        Field::class,
        mustBe(Statamic\Fields\Blueprint::class, Blueprint::find('collections.people.person'), 'the person blueprint')->field('certifications'),
        'the certifications field'
    );
}

/**
 * Augment stored certifications rows the way a people entry hands them to a view.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function certificationsListByKindAugment(array $rows): Value
{
    // Merge computed validity, then augment through the people blueprint's replicator
    $merged = (new CertificationActivityValidity)->mergeIntoCertificationsRows($rows);

    return new Value($merged, 'certifications', certificationsListByKindField()->fieldtype());
}

/**
 * Render the certifications list as both profiles call it.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function certificationsListByKindRender(array $rows): string
{
    return (string) Antlers::parse(
        '{{ partial:people/components/professional_background_list heading="Certifications" :activities_list="certifications | certifications_by_kind" variant="certifications" }}',
        ['certifications' => certificationsListByKindAugment($rows)],
        true
    );
}

/**
 * Headings in render order, read from the list's item names.
 *
 * @return list<string>
 */
function certificationsListByKindHeadings(string $html): array
{
    preg_match_all('/itemprop="https:\/\/schema.org\/name"\s+content="([^"]*)"/', $html, $matches);

    return $matches[1];
}

/**
 * One row of each kind, in an entry order unlike the render order.
 *
 * @return list<array<string, mixed>>
 */
function certificationsListByKindOneOfEach(): array
{
    return [
        [
            'id' => 'certificate',
            'type' => 'other_certification',
            'enabled' => true,
            'activity' => 'american-society-for-metabolic-and-bariatric-surgery-fundamentals-for-the-metabolic-and-bariatric-surgery-patient-assessment-based-certificate',
            'activity_date_issued' => '2021-03-04',
            'activity_date_audit' => '2022-05-06',
            'activity_date_expires' => '2023-07-08',
        ],
        [
            'id' => 'certification',
            'type' => 'other_certification',
            'enabled' => true,
            'activity' => 'american-academy-of-nurse-practitioners-certification-board-psychiatric-mental-health-nurse-practitioner',
            'activity_date_issued' => '2019-05-01',
        ],
        [
            'id' => 'fpd',
            'type' => 'other_certification',
            'enabled' => true,
            'activity' => 'american-board-of-surgery-metabolic-and-bariatric-surgery-focused-practice-designation',
            'activity_date_issued' => '2018-05-01',
        ],
        [
            'id' => 'board',
            'type' => 'board_certification',
            'enabled' => true,
            'activity' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery',
            'activity_date_issued' => '1994-07-15',
        ],
    ];
}

it('renders one list ordered and labeled by kind', function () {
    expect(certificationsListByKindHeadings(certificationsListByKindRender(certificationsListByKindOneOfEach())))->toBe([
        'Board Certification in Orthopaedic Surgery',
        'Focused Practice Designation in Metabolic and Bariatric Surgery',
        'Psychiatric Mental Health Nurse Practitioner',
        'Fundamentals for the Metabolic and Bariatric Surgery Patient Assessment-Based Certificate',
    ]);
});

/**
 * Pins the issued-only rendering a certificate row gets. The template renders
 * only the issued date for every kind, so this guards the template rather than
 * the certificate's kind; CertificationCertificateSchemaDatesTest covers the
 * JSON-LD, where the kind decides whether the dates are emitted.
 */
it('shows only a certificate\'s issued date', function () {
    $html = certificationsListByKindRender([certificationsListByKindOneOfEach()[0]]);

    expect($html)->toContain('Issued <time')
        ->and($html)->toContain('March 4, 2021')
        ->and($html)->not->toContain('2022')
        ->and($html)->not->toContain('2023');
});

it('reports a certificate past its stored expiration as active', function () {
    $rows = mustBeArray(certificationsListByKindAugment([certificationsListByKindOneOfEach()[0]])->value(), 'the augmented rows');

    expect(mustBe(Values::class, $rows[0], 'the certificate row')['activity_active_query'])->toBeTrue();
});

it('renders board and other-certification rows exactly as before', function () {
    // Captured from the template before UAMS-Web/uams-statamic-archive#2973, base64 so the CRLF and &nbsp; bytes survive review
    $before = base64_decode('DQoJDQoJCQ0KCQkNCgkJPGRpdj4NCgkJCTxoMyBjbGFzcz0ibW9kdWxlLWlubmVyLXRpdGxlIj5DZXJ0aWZpY2F0aW9uczwvaDM+DQoJCQkNCg0KDQoNCg0KDQoNCg0KDQoNCg0KDQo8ZGl2IGNsYXNzPSJjb2wtMTIiPg0KCTxkaXYNCgkJY2xhc3M9ImNhcmQtbGlzdCINCgkJDQoJCSBpdGVtc2NvcGUNCgkJIGl0ZW10eXBlPSJodHRwczovL3NjaGVtYS5vcmcvSXRlbUxpc3QiDQoJPg0KCQkNCgkJCQ0KCQkJDQoJCQkNCgkJCQk8bGluaw0KCQkJCQkgaXRlbXByb3A9Imh0dHBzOi8vc2NoZW1hLm9yZy9pdGVtTGlzdE9yZGVyIg0KCQkJCQkgaHJlZj0iaHR0cHM6Ly9zY2hlbWEub3JnL0l0ZW1MaXN0T3JkZXJBc2NlbmRpbmciDQoJCQkJLz4NCgkJCQ0KCQkNCgkJPGRpdg0KCWNsYXNzPSJpdGVtIg0KCSBpdGVtcHJvcD0iaHR0cHM6Ly9zY2hlbWEub3JnL2l0ZW1MaXN0RWxlbWVudCINCgkgaXRlbXNjb3BlDQoJIGl0ZW10eXBlPSJodHRwczovL3NjaGVtYS5vcmcvTGlzdEl0ZW0iDQo+DQoJPG1ldGENCgkJaXRlbXByb3A9Imh0dHA6Ly9zY2hlbWEub3JnL3Bvc2l0aW9uIg0KCQkgY29udGVudD0iMSINCgkvPg0KDQoJPGRpdg0KCQljbGFzcz0iY2FyZCINCgkJIGl0ZW1wcm9wPSJodHRwczovL3NjaGVtYS5vcmcvaXRlbSINCgkJIGl0ZW1zY29wZQ0KCQkgaXRlbXR5cGU9Imh0dHBzOi8vc2NoZW1hLm9yZy9XZWJQYWdlIg0KCQkNCgk+DQoJCTxkbCBjbGFzcz0idW5zdHlsZWQiPg0KCQk8ZGl2IGNsYXNzPSJjYXJkLWJvZHkiPg0KCTxkdA0KCWNsYXNzPSJjYXJkLXRpdGxlIGg1Ig0KCQ0KPg0KCQ0KCQk8c3BhbiAgaXRlbXByb3A9Imh0dHBzOi8vc2NoZW1hLm9yZy9uYW1lIiAgY29udGVudD0iQm9hcmQgQ2VydGlmaWNhdGlvbiBpbiBPcnRob3BhZWRpYyBTdXJnZXJ5Ij5Cb2FyZCBDZXJ0aWZpY2F0aW9uIGluIE9ydGhvcGFlZGljJm5ic3A7U3VyZ2VyeTwvc3Bhbj4NCgkNCjwvZHQ+DQoNCg0KCQkJPGRkPkFtZXJpY2FuIEJvYXJkIG9mIE9ydGhvcGFlZGljJm5ic3A7U3VyZ2VyeTwvZGQ+DQoJCQk8ZGQgY2xhc3M9InNtYWxsIG10LTUgcHQtNSBib3JkZXItdCBib3JkZXItci0wIGJvcmRlci1iLTAgYm9yZGVyLWwtMCBib3JkZXItc29saWQgYm9yZGVyLWdyYXktMjAwIj5SZW5ld2VkIGluJm5ic3A7MjAyNC48L2RkPg0KPC9kaXY+DQoNCgkJDQoJCQk8ZGl2IGNsYXNzPSJjYXJkLWZvb3RlciI+DQoJPGRkIGNsYXNzPSJzbWFsbCI+DQoJCQkJCQ0KCQkJCQkJSXNzdWVkIDx0aW1lIGRhdGV0aW1lPSIxOTk0LTA3LTE0VDE5OjAwOjAwLTA1OjAwIj5KdWx5IDE1LCAxOTk0PC90aW1lPg0KCQkJCQkNCgkJCQk8L2RkPg0KPC9kaXY+DQoNCgkJDQoJPC9kbD4NCgk8L2Rpdj4NCjwvZGl2Pg0KDQoNCgkJCQkNCgkJCQkJDQoJCQkJCQ0KCQkJCQkNCgkJCQkJDQoJCQkJCQ0KCQkJCQkNCg0KCQkJCQkNCg0KCQkJCQkJDQoJCQkJCQkNCgkJCQkJCQ0KCQkJCQkJCQ0KCQkJCQkJDQoJCQkJCQkNCgkJCQkJCQ0KCQkJCQkJDQoNCgkJCQkJDQoJCQkJCQ0KDQoNCg0KDQoNCg0KDQoNCg0KDQoNCg0KDQoNCjxkaXYNCgljbGFzcz0iaXRlbSINCgkgaXRlbXByb3A9Imh0dHBzOi8vc2NoZW1hLm9yZy9pdGVtTGlzdEVsZW1lbnQiDQoJIGl0ZW1zY29wZQ0KCSBpdGVtdHlwZT0iaHR0cHM6Ly9zY2hlbWEub3JnL0xpc3RJdGVtIg0KPg0KCTxtZXRhDQoJCWl0ZW1wcm9wPSJodHRwOi8vc2NoZW1hLm9yZy9wb3NpdGlvbiINCgkJIGNvbnRlbnQ9IjIiDQoJLz4NCg0KCTxkaXYNCgkJY2xhc3M9ImNhcmQiDQoJCSBpdGVtcHJvcD0iaHR0cHM6Ly9zY2hlbWEub3JnL2l0ZW0iDQoJCQ0KCQkgaXRlbXR5cGU9Imh0dHBzOi8vc2NoZW1hLm9yZy9XZWJQYWdlIg0KCQkNCgk+DQoJCTxkbCBjbGFzcz0idW5zdHlsZWQiPg0KCQk8ZGl2IGNsYXNzPSJjYXJkLWJvZHkiPg0KCTxkdA0KCWNsYXNzPSJjYXJkLXRpdGxlIGg1Ig0KCQ0KPg0KCQ0KCQk8c3BhbiAgaXRlbXByb3A9Imh0dHBzOi8vc2NoZW1hLm9yZy9uYW1lIiAgY29udGVudD0iUHN5Y2hpYXRyaWMgTWVudGFsIEhlYWx0aCBOdXJzZSBQcmFjdGl0aW9uZXIiPlBzeWNoaWF0cmljIE1lbnRhbCBIZWFsdGggTnVyc2UmbmJzcDtQcmFjdGl0aW9uZXI8L3NwYW4+DQoJDQo8L2R0Pg0KDQoNCgkJCTxkZD5BbWVyaWNhbiBBY2FkZW15IG9mIE51cnNlIFByYWN0aXRpb25lcnMgQ2VydGlmaWNhdGlvbiZuYnNwO0JvYXJkPC9kZD4NCjwvZGl2Pg0KDQoJCQ0KCQkJPGRpdiBjbGFzcz0iY2FyZC1mb290ZXIiPg0KCTxkZCBjbGFzcz0ic21hbGwiPg0KCQkJCQkNCgkJCQkJCUlzc3VlZCA8dGltZSBkYXRldGltZT0iMjAxOS0wNC0zMFQxOTowMDowMC0wNTowMCI+TWF5IDEsIDIwMTk8L3RpbWU+DQoJCQkJCQ0KCQkJCTwvZGQ+DQo8L2Rpdj4NCg0KCQkNCgk8L2RsPg0KCTwvZGl2Pg0KPC9kaXY+DQoJPC9kaXY+DQo8L2Rpdj4NCg0KCQk8L2Rpdj4NCgkNCg0K');

    $html = certificationsListByKindRender([
        [
            'id' => 'b',
            'type' => 'board_certification',
            'enabled' => true,
            'activity' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery',
            'activity_date_issued' => '1994-07-15',
            'activity_date_audit' => '2025-01-01',
            'activity_date_expires' => '2034-12-31',
            'notes' => 'Renewed in 2024.',
        ],
        [
            'id' => 'o',
            'type' => 'other_certification',
            'enabled' => true,
            'activity' => 'american-academy-of-nurse-practitioners-certification-board-psychiatric-mental-health-nurse-practitioner',
            'activity_date_issued' => '2019-05-01',
            'activity_date_expires' => '2029-04-30',
        ],
    ]);

    expect($html)->toBe($before);
});

it('orders certifications by kind on both profiles', function (string $template) {
    expect(file_get_contents(resource_path($template)))->toContain(':activities_list="certifications | certifications_by_kind"');
})->with([
    'the general people profile' => 'views/people/show.antlers.html',
    'the provider Credentials block' => 'views/people/provider/_profile.antlers.html',
]);
