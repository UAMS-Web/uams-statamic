<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the shared people-lens identity header
 * (`people/components/identity_header`, UAMS-Web/uams-statamic-archive#1636 / UAMS-Web/uams-statamic-archive#1667) — the ONE piece of chrome the
 * org-derived and researcher lenses share (name, job title, portrait, lens marker).
 * Each lens's own `_profile` body curates a DIFFERENT field selection around it
 * (covered by PeopleAudienceLensProfileTest), so the audience presentations diverge
 * rather than all delegating to one identical body (the UAMS-Web/uams-statamic-archive#1667 change).
 *
 * Rendered as a partial with a synthetic person scope (no resolvable portrait → the
 * glide/asset path is skipped), so it is hermetic and CI-safe.
 *
 * @command php artisan test --compact tests/Feature/Views/PeopleLensBodyTest.php
 */

/**
 * Render the shared identity header for a lens handle + person scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderPeopleIdentityHeader(string $lens, array $scope): string
{
    return (string) Antlers::parse('{{ partial:people/components/identity_header lens="'.$lens.'" }}', $scope);
}

it('renders the identity, job title, and the lens marker', function (): void {
    $html = renderPeopleIdentityHeader('academic', [
        'title' => 'Jane McCoy',
        'display_name_full_with_credentials' => ['text' => 'Jane McCoy, Ph.D.'],
        'job_title' => 'Professor of Xenobiology',
    ]);

    // The job-title component applies `| title | widont`, so match individual words
    // rather than the raw phrase (title-cases "of"→"Of", last space → `&nbsp;`).
    expect($html)->toContain('data-lens="academic"')
        ->toContain('Jane McCoy, Ph.D.')
        ->toContain('Professor')
        ->toContain('Xenobiology');
});

it('carries the requested lens marker for every lens', function (string $lens): void {
    $html = renderPeopleIdentityHeader($lens, [
        'title' => 'Bob Wesley',
        'display_name_full_with_credentials' => ['text' => 'Bob Wesley'],
    ]);

    expect($html)->toContain('data-lens="'.$lens.'"')
        ->toContain('Bob Wesley');
})->with(['academic', 'institutional', 'researcher']);

it('strip_tags-escapes the editor-controlled name (Antlers is raw by default)', function (): void {
    $html = renderPeopleIdentityHeader('academic', [
        'title' => 'Jane McCoy',
        'display_name_full_with_credentials' => ['text' => 'Jane <script>alert(1)</script>McCoy'],
    ]);

    expect($html)->not->toContain('<script>')
        ->toContain('Jane')
        ->toContain('McCoy');
});
