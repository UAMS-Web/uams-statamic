<?php

declare(strict_types=1);

use App\Rules\BaseRules\AbstractProfessionalAndLeadershipDevelopmentActivitiesTerm as ActivitiesRule;
use App\Rules\LeadershipDevelopmentActivitiesTerm;
use App\Rules\ProfessionalDevelopmentActivitiesTerm;
use App\Scopes\ProfessionalAndLeadershipDevelopmentActivitiesTermType as ActivitiesScope;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Behavior of the professional / leadership development activity term rules
 * (App\Rules\ProfessionalDevelopmentActivitiesTerm,
 * App\Rules\LeadershipDevelopmentActivitiesTerm, sharing
 * AbstractProfessionalAndLeadershipDevelopmentActivitiesTerm): each selected term
 * must resolve, belong to the activities taxonomy, and have a `type` matching the
 * rule variant. Empty values skip; not-found / wrong-taxonomy / wrong-type fail.
 *
 * @command  php artisan test --compact tests/Feature/Rules/ProfessionalAndLeadershipDevelopmentActivitiesTermRuleTest.php
 */

/**
 * Run the given activities-term rule and return its fail messages.
 *
 * @return list<string>
 */
function activitiesTermMessages(ActivitiesRule $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('field', $value, function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    return $messages;
}

it('skips when the value is empty', function () {
    mockTermRepository()->shouldReceive('find')->never();

    expect(activitiesTermMessages(new ProfessionalDevelopmentActivitiesTerm, ''))->toBeEmpty();
});

it('fails (professional) when the term is not found', function () {
    mockTermRepository()->shouldReceive('find')->once()
        ->with('professional_and_leadership_development_activities::missing')->andReturn(null);

    $messages = activitiesTermMessages(
        new ProfessionalDevelopmentActivitiesTerm,
        'professional_and_leadership_development_activities::missing',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('professional_and_leadership_development_activities::missing');
});

it('fails (professional) when the taxonomy mismatches, before reading type', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->never();

    mockTermRepository()->shouldReceive('find')->once()
        ->with('professional_and_leadership_development_activities::x')->andReturn($term);

    $messages = activitiesTermMessages(
        new ProfessionalDevelopmentActivitiesTerm,
        'professional_and_leadership_development_activities::x',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('certifications');
});

it('fails (professional) when the type mismatches', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(ActivitiesScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with(ActivitiesScope::TYPE_FIELD)
        ->andReturn(ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT);

    mockTermRepository()->shouldReceive('find')->once()->andReturn($term);

    $messages = activitiesTermMessages(
        new ProfessionalDevelopmentActivitiesTerm,
        'professional_and_leadership_development_activities::x',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain(ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT);
});

it('passes (professional) when taxonomy and type match', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(ActivitiesScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with(ActivitiesScope::TYPE_FIELD)
        ->andReturn(ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT);

    mockTermRepository()->shouldReceive('find')->once()->andReturn($term);

    expect(activitiesTermMessages(
        new ProfessionalDevelopmentActivitiesTerm,
        'professional_and_leadership_development_activities::pd',
    ))->toBeEmpty();
});

it('fails (leadership) when the type is professional development', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(ActivitiesScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with(ActivitiesScope::TYPE_FIELD)
        ->andReturn(ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT);

    mockTermRepository()->shouldReceive('find')->once()->andReturn($term);

    $messages = activitiesTermMessages(
        new LeadershipDevelopmentActivitiesTerm,
        'professional_and_leadership_development_activities::x',
    );

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain(ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT);
});

it('passes (leadership) when taxonomy and type match', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(ActivitiesScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with(ActivitiesScope::TYPE_FIELD)
        ->andReturn(ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT);

    mockTermRepository()->shouldReceive('find')->once()->andReturn($term);

    expect(activitiesTermMessages(
        new LeadershipDevelopmentActivitiesTerm,
        'professional_and_leadership_development_activities::ld',
    ))->toBeEmpty();
});

it('normalizes an array value, skipping non-string and empty entries', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(ActivitiesScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with(ActivitiesScope::TYPE_FIELD)
        ->andReturn(ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT);

    mockTermRepository()->shouldReceive('find')->once()
        ->with('professional_and_leadership_development_activities::pd')->andReturn($term);

    expect(activitiesTermMessages(
        new ProfessionalDevelopmentActivitiesTerm,
        ['professional_and_leadership_development_activities::pd', 123, ''],
    ))->toBeEmpty();
});

it('names the id and the found taxonomy in the wrong-taxonomy message', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');

    mockTermRepository()->shouldReceive('find')->once()->andReturn($term);

    expect(activitiesTermMessages(new ProfessionalDevelopmentActivitiesTerm, 'certifications::x'))->toBe([__(
        'statamic::validation.professional_development_activity_term.wrong_taxonomy',
        ['id' => 'certifications::x', 'taxonomy' => 'certifications'],
    )]);
});

it('names the id, the found type and the expected type in the wrong-type message', function (mixed $type, string $display) {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(ActivitiesScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with(ActivitiesScope::TYPE_FIELD)->andReturn($type);

    mockTermRepository()->shouldReceive('find')->once()->andReturn($term);

    expect(activitiesTermMessages(new LeadershipDevelopmentActivitiesTerm, 'activities::x'))->toBe([__(
        'statamic::validation.leadership_development_activity_term.wrong_type',
        [
            'id' => 'activities::x',
            'type' => $display,
            'expected' => ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT,
        ],
    )]);
})->with([
    'string' => ['other', 'other'],
    'integer' => [3, '3'],
    'null' => [null, ''],
    'array' => [['other'], ''],
]);

it('keeps reading the array after a skipped entry', function (mixed $skipped) {
    mockTermRepository()->shouldReceive('find')->once()->with('activities::missing')->andReturn(null);

    expect(activitiesTermMessages(new ProfessionalDevelopmentActivitiesTerm, [$skipped, 'activities::missing']))->toBe([__(
        'statamic::validation.professional_development_activity_term.not_found',
        ['id' => 'activities::missing'],
    )]);
})->with([
    'non-string' => [123],
    'empty string' => [''],
]);

it('accepts a localized term from the repository', function () {
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn(ActivitiesScope::TAXONOMY);
    $term->shouldReceive('get')->once()->with(ActivitiesScope::TYPE_FIELD)
        ->andReturn(ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT);

    mockTermRepository()->shouldReceive('find')->once()->andReturn($term);

    expect(activitiesTermMessages(new ProfessionalDevelopmentActivitiesTerm, 'activities::pd'))->toBe([]);
});

it('refuses a repository result that is not a term', function () {
    mockTermRepository()->shouldReceive('find')->once()->andReturn(new stdClass);

    activitiesTermMessages(new ProfessionalDevelopmentActivitiesTerm, 'activities::x');
})->throws(AssertionError::class, 'Term::find must return a LocalizedTerm or Term instance.');
