<?php

declare(strict_types=1);

use App\Rules\SpecialtyTaxonomyTermType as SpecialtyTaxonomyTermTypeRule;
use App\Scopes\SpecialtyTaxonomyTermType as SpecialtyTaxonomyTermTypeScope;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Behavior of the App\Rules\SpecialtyTaxonomyTermType validation rule: parse the
 * scope-alias / comma-form rule arguments into an expectation (type, allowed
 * taxonomies, optional medical_query), then require each selected term to resolve,
 * sit in an allowed specialty taxonomy, carry the expected specialty_subspecialty_type,
 * and (for medical variants) match medical_query. Unknown / too-many arguments and
 * mismatches each fail with a distinct message; empty values skip.
 *
 * @command  php artisan test --compact tests/Feature/Rules/SpecialtyTaxonomyTermTypeRuleTest.php
 */

/**
 * Run the given rule and return its fail messages.
 *
 * @return list<string>
 */
function specialtyTermTypeMessages(SpecialtyTaxonomyTermTypeRule $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('field', $value, function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    return $messages;
}

it('fails when the scope alias is unknown', function () {
    $messages = specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('not_a_real_alias'),
        'specialties_acgme::anything',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('not_a_real_alias');
});

it('fails when given three parameters', function () {
    $messages = specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty', 'medical', 'extra'),
        'research_specialties_acgme::slug',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('specialty,medical,extra');
});

it('skips when the value is empty', function () {
    mockTermRepository()->shouldReceive('find')->never();

    expect(specialtyTermTypeMessages(new SpecialtyTaxonomyTermTypeRule('specialty'), ''))->toBeEmpty();
});

it('fails when the term is not found', function () {
    mockTermRepository()->shouldReceive('find')->once()->with('specialties_acgme::missing')->andReturn(null);

    $messages = specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty_acgme'),
        'specialties_acgme::missing',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('specialties_acgme::missing');
});

it('fails when the taxonomy mismatches a suffixed alias, before reading type', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('specialties_aoa');
    $term->shouldReceive('get')->never();

    mockTermRepository()->shouldReceive('find')->once()->with('specialties_aoa::slug')->andReturn($term);

    $messages = specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty_acgme'),
        'specialties_aoa::slug',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('specialties_aoa');
});

it('fails when a bare specialty sees a taxonomy outside the allowed set', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->never();

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty'),
        'certifications::slug',
    ))->not->toBeEmpty();
});

it('passes a type-only alias for any taxonomy when the type matches', function () {
    // The `*_type_only` alias imposes no taxonomy constraint (empty set) — an
    // `other_specialties` term of type=specialty must pass, though it is not one of
    // the three medical taxonomies the bare `specialty` alias would require.
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('other_specialties');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SPECIALTY);

    mockTermRepository()->shouldReceive('find')->once()->with('other_specialties::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty_type_only'),
        'other_specialties::slug',
    ))->toBeEmpty();
});

it('fails a type-only alias when the specialty_subspecialty_type still mismatches', function () {
    // Type is still enforced even with no taxonomy constraint.
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('other_specialties');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SUBSPECIALTY);

    mockTermRepository()->shouldReceive('find')->once()->with('other_specialties::slug')->andReturn($term);

    $messages = specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty_type_only'),
        'other_specialties::slug',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SUBSPECIALTY);
});

it('fails when the specialty_subspecialty_type mismatches', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('specialties_acgme');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SUBSPECIALTY);

    mockTermRepository()->shouldReceive('find')->once()->with('specialties_acgme::slug')->andReturn($term);

    $messages = specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty_acgme'),
        'specialties_acgme::slug',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SUBSPECIALTY);
});

it('passes for a matching suffixed term', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('specialties_acgme');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SPECIALTY);

    mockTermRepository()->shouldReceive('find')->once()->with('specialties_acgme::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty_acgme'),
        'specialties_acgme::slug',
    ))->toBeEmpty();
});

it('passes for a bare subspecialty in the AOA taxonomy', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('specialties_aoa');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SUBSPECIALTY);

    mockTermRepository()->shouldReceive('find')->once()->with('specialties_aoa::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('subspecialty'),
        'specialties_aoa::slug',
    ))->toBeEmpty();
});

it('passes a medical research specialty when medical_query is true', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('research_specialties_acgme');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SPECIALTY);
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::MEDICAL_QUERY_FIELD)
        ->andReturn(true);

    mockTermRepository()->shouldReceive('find')->once()->with('research_specialties_acgme::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('medical_research_specialty'),
        'research_specialties_acgme::slug',
    ))->toBeEmpty();
});

it('fails a medical research specialty when medical_query is false', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('research_specialties_acgme');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SPECIALTY);
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::MEDICAL_QUERY_FIELD)
        ->andReturn(false);

    mockTermRepository()->shouldReceive('find')->once()->with('research_specialties_acgme::slug')->andReturn($term);

    $messages = specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('medical_research_specialty'),
        'research_specialties_acgme::slug',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('research_specialties_acgme::slug');
});

it('passes the comma form specialty,medical', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('research_specialties_non_accredited');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SPECIALTY);
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::MEDICAL_QUERY_FIELD)
        ->andReturn(true);

    mockTermRepository()->shouldReceive('find')->once()
        ->with('research_specialties_non_accredited::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty', 'medical'),
        'research_specialties_non_accredited::slug',
    ))->toBeEmpty();
});

it('passes subspecialty + non-medical with reversed argument order', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('research_specialties_acgme');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SUBSPECIALTY);
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::MEDICAL_QUERY_FIELD)
        ->andReturn(false);

    mockTermRepository()->shouldReceive('find')->once()->with('research_specialties_acgme::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('non-medical', 'subspecialty'),
        'research_specialties_acgme::slug',
    ))->toBeEmpty();
});

it('passes the shorthand medical_specialty', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('research_specialties_acgme');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SPECIALTY);
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::MEDICAL_QUERY_FIELD)
        ->andReturn(true);

    mockTermRepository()->shouldReceive('find')->once()->with('research_specialties_acgme::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('medical_specialty'),
        'research_specialties_acgme::slug',
    ))->toBeEmpty();
});

it('normalizes an array value, skipping non-string and empty entries', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('specialties_acgme');
    $term->shouldReceive('get')->once()->with(SpecialtyTaxonomyTermTypeScope::TYPE_FIELD)
        ->andReturn(SpecialtyTaxonomyTermTypeScope::TYPE_VALUE_SPECIALTY);

    mockTermRepository()->shouldReceive('find')->once()->with('specialties_acgme::slug')->andReturn($term);

    expect(specialtyTermTypeMessages(
        new SpecialtyTaxonomyTermTypeRule('specialty_acgme'),
        ['specialties_acgme::slug', 123, ''],
    ))->toBeEmpty();
});
