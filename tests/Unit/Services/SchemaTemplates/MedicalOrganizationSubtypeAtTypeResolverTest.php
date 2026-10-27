<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\MedicalOrganizationSubtypeAtTypeResolver;
use Tests\TestCase;

uses(TestCase::class);

covers(MedicalOrganizationSubtypeAtTypeResolver::class);

/**
 * Unit coverage for the content-independent multi-type `@type` rule
 * (App\Services\SchemaTemplates\MedicalOrganizationSubtypeAtTypeResolver, UAMS-Web/uams-statamic-archive#967).
 *
 * The rule keys off App\Enums\SchemaType's generated ancestor graph: the four
 * LocalBusiness-descendant subtypes (Hospital, MedicalClinic, Pharmacy,
 * Dentist) emit a single type, while DiagnosticLab — a MedicalOrganization that
 * is not a LocalBusiness — emits the multi-type [DiagnosticLab, LocalBusiness].
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/MedicalOrganizationSubtypeAtTypeResolverTest.php
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
 * @return list<string>
 */
function resolveSubtypeAtType(string $subtype): array
{
    return (new MedicalOrganizationSubtypeAtTypeResolver)->resolve($subtype);
}

it('emits a single type for LocalBusiness-descendant subtypes', function (string $subtype): void {
    expect(resolveSubtypeAtType($subtype))->toBe([$subtype]);
})->with([
    'Hospital' => ['Hospital'],
    'MedicalClinic' => ['MedicalClinic'],
    'Pharmacy' => ['Pharmacy'],
    'Dentist' => ['Dentist'],
]);

it('emits the multi-type [DiagnosticLab, LocalBusiness] for DiagnosticLab', function (): void {
    // DiagnosticLab is a MedicalOrganization but NOT a LocalBusiness, so it must
    // carry LocalBusiness explicitly (the central UAMS-Web/uams-statamic-archive#967 multi-type case).
    expect(resolveSubtypeAtType('DiagnosticLab'))->toBe(['DiagnosticLab', 'LocalBusiness']);
});

it('trims surrounding whitespace before resolving', function (): void {
    // UnwrapTrim would leave spaces and fail the ancestor family gate.
    expect(resolveSubtypeAtType('  Hospital  '))->toBe(['Hospital']);
    expect(resolveSubtypeAtType("DiagnosticLab\n"))->toBe(['DiagnosticLab', 'LocalBusiness']);
});

it('returns an empty list for an empty or whitespace-only subtype', function (string $subtype): void {
    expect(resolveSubtypeAtType($subtype))->toBe([]);
})->with([
    'empty string' => [''],
    'spaces' => ['   '],
    'newline' => ["\n"],
]);

it('returns an empty list for an unknown Schema.org type', function (): void {
    expect(resolveSubtypeAtType('NotARealSchemaType'))->toBe([]);
    expect(resolveSubtypeAtType('diagnosticlab'))->toBe([]); // case-sensitive enum value
});

it('returns an empty list for a type outside the MedicalOrganization family', function (string $subtype): void {
    // Real Schema.org types that are not MedicalOrganization descendants must
    // not be augmented — the caller keeps the entry's declared @type.
    expect(resolveSubtypeAtType($subtype))->toBe([]);
})->with([
    'Person' => ['Person'],
    'CreativeWork' => ['CreativeWork'],
    'Restaurant (a LocalBusiness, but not medical)' => ['Restaurant'],
    'Organization (ancestor, not MedicalOrganization)' => ['Organization'],
]);

it('emits the multi-type for a bare MedicalOrganization selection', function (): void {
    // MedicalOrganization itself is not a LocalBusiness, so it gains LocalBusiness.
    // Also pins ``$subtype !== MEDICAL_ORGANIZATION``: equality must take this arm.
    expect(resolveSubtypeAtType('MedicalOrganization'))->toBe(['MedicalOrganization', 'LocalBusiness']);
});
