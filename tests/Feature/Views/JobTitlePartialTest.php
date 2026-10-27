<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression coverage for UAMS-Web/uams-statamic-archive#2140: `people/components/_job_title` rendered its
 * wrapper markup unconditionally, so a row whose composed title is empty
 * produced an empty `<span class="job-title-name">` — and, with a department
 * present, a line opening on a bare em-dash. The partial now renders nothing
 * when the composed name is empty, mirroring the people block, which already
 * guards its whole output.
 *
 * Antlers template partial — no backing PHP class, so no mutable PHP surface.
 *
 * @command php artisan test --compact tests/Feature/Views/JobTitlePartialTest.php
 */
function jobTitle(?string $name, ?string $department = null): string
{
    return (string) Antlers::parse(
        '{{ partial:people/components/job_title :job_title_name="name" :job_title_department="department" }}',
        ['name' => $name, 'department' => $department],
        true
    );
}

it('renders the title and department when both are present', function () {
    $out = jobTitle('Chief Medical Officer', 'Radiology');

    expect($out)->toContain('job-title-name');
    expect($out)->toContain('Chief Medical Officer');
    expect($out)->toContain('department-name');
    expect($out)->toContain('Radiology');
    expect($out)->toContain('—');
});

it('renders the title alone when there is no department', function () {
    $out = jobTitle('Chief Medical Officer');

    expect($out)->toContain('job-title-name');
    // widont applies to the name in this branch only, so the last space is a &nbsp;
    expect($out)->toContain('Chief Medical&nbsp;Officer');
    expect($out)->not->toContain('department-name');
    expect($out)->not->toContain('—');
});

it('renders nothing when the composed title is empty and there is no department', function () {
    expect(trim(jobTitle('')))->toBe('');
});

it('renders nothing when the composed title is empty and a department is present', function () {
    $out = jobTitle('', 'Radiology');

    expect(trim($out))->toBe('');
    expect($out)->not->toContain('—');
    expect($out)->not->toContain('job-title-name');
});

it('renders nothing when the title is whitespace only', function () {
    expect(trim(jobTitle('   ', 'Radiology')))->toBe('');
});

it('leaves the populated output byte-identical to the pre-guard rendering', function () {
    // Captured from the template BEFORE the guard was added (UAMS-Web/uams-statamic-archive#2140 AC3), base64 so the
    // CRLF and &nbsp; bytes survive review and diffing intact.
    $withDepartment = base64_decode('DQoNCg0KPHNwYW4gY2xhc3M9InN1YnRpdGxlIGZvbnQtd2VpZ2h0LXRoaW4iPg0KCTxzcGFuIGNsYXNzPSJqb2ItdGl0bGUtbmFtZSBmb250LXdlaWdodC1ub3JtYWwiPkNoaWVmIE1lZGljYWwgT2ZmaWNlcjwvc3Bhbj7CoOKAlCA8c3BhbiBjbGFzcz0iZGVwYXJ0bWVudC1uYW1lIGZvbnQtd2VpZ2h0LXRoaW4gZm9udC1pdGFsaWMgbGV0dGVyLXNwYWNpbmctdGlnaHQiPlJhZGlvbG9neTwvc3Bhbj4NCjwvc3Bhbj4NCg0K');
    $withoutDepartment = base64_decode('DQoNCg0KPHNwYW4gY2xhc3M9InN1YnRpdGxlIGZvbnQtd2VpZ2h0LW5vcm1hbCI+DQoJPHNwYW4gY2xhc3M9ImpvYi10aXRsZS1uYW1lIj5DaGllZiBNZWRpY2FsJm5ic3A7T2ZmaWNlcjwvc3Bhbj4NCjwvc3Bhbj4NCg0K');

    expect(jobTitle('Chief Medical Officer', 'Radiology'))->toBe($withDepartment);
    expect(jobTitle('Chief Medical Officer'))->toBe($withoutDepartment);
});
