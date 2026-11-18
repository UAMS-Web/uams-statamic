<?php

declare(strict_types=1);

use App\Providers\StillatRelationshipsServiceProvider;
use App\Support\Relationships\BoundedRelationshipProcessor;
use Stillat\Relationships\EntryRelationship;
use Stillat\Relationships\Processors\RelationshipProcessor;
use Stillat\Relationships\Support\Facades\Relate;
use Tests\TestCase;

uses(TestCase::class);

covers(StillatRelationshipsServiceProvider::class);

/**
 * Feature tests for `StillatRelationshipsServiceProvider`: the bounded processor it binds, and
 * the exact set of two-way relationships it declares.
 *
 * The declared set is read back from the relationship manager after a fresh `boot()`, keeping
 * only the relationships the provider declared itself; the package derives an inverse for each,
 * and those are its behavior, not this provider's.
 *
 * Mutation: 100% on App\Providers\StillatRelationshipsServiceProvider (33 mutants, all tested, with
 * BoundedRelationshipProcessorTest; Windows, #873).
 *
 * @command  php artisan test --compact tests/Feature/Providers/StillatRelationshipsServiceProviderTest.php
 */

/**
 * The relationships the manager holds that were declared directly, as
 * `<type> <left> <right>` with each side written as the provider writes it.
 *
 * @return list<string>
 */
function declaredStillatRelationships(): array
{
    /** @var list<EntryRelationship> $all */
    $all = Relate::getAllRelationships();

    $declared = array_filter(
        $all,
        static fn (EntryRelationship $relationship): bool => ! $relationship->isAutomaticInverse,
    );

    // The package declares these properties untyped; each holds a handle string, and anything
    // else renders empty, which no expected row contains.
    $text = static fn (mixed $value): string => is_string($value) ? $value : '';

    return array_values(array_map(static function (EntryRelationship $relationship) use ($text): string {
        $left = $relationship->leftType === 'term'
            ? 'term:'.$text($relationship->taxonomyName).'.'.$text($relationship->leftField)
            : 'entry:'.$text($relationship->leftCollection).'.'.$text($relationship->leftField);
        $right = $relationship->rightType === 'term'
            ? 'term:'.$text($relationship->rightCollection).'.'.$text($relationship->rightField)
            : 'entry:'.$text($relationship->rightCollection).'.'.$text($relationship->rightField);
        $type = $relationship->type === EntryRelationship::TYPE_ONE_TO_ONE ? 'one-to-one' : 'many-to-many';

        return $type.' '.$left.' '.$right;
    }, $declared));
}

it('binds the relationship processor to one shared bounded processor', function (): void {
    $processor = app(RelationshipProcessor::class);

    expect($processor::class)->toBe(BoundedRelationshipProcessor::class)
        ->and(app(RelationshipProcessor::class))->toBe($processor);
});

it('declares exactly these two-way relationships, in this order', function (): void {
    Relate::clear();

    (new StillatRelationshipsServiceProvider(app()))->boot();

    expect(declaredStillatRelationships())->toBe([
        'one-to-one term:certifications.credentials_term term:credentials.certifications_term',
        'many-to-many entry:people.clinical_locations entry:clinical_locations.physician_locations',
        'many-to-many entry:people.expertise entry:clinical_expertise.clinical_providers',
        'many-to-many entry:people.conditions entry:clinical_conditions.clinical_providers',
        'many-to-many entry:people.treatments entry:clinical_services.clinical_providers',
        'many-to-many entry:people.clinical_resources entry:clinical_resources.clinical_providers',
        'many-to-many entry:clinical_expertise.clinical_locations entry:clinical_locations.expertise',
        'many-to-many entry:clinical_expertise.clinical_conditions entry:clinical_conditions.clinical_expertise',
        'many-to-many entry:clinical_expertise.clinical_services entry:clinical_services.clinical_expertise',
        'many-to-many entry:clinical_expertise.clinical_resources entry:clinical_resources.clinical_expertise',
        'many-to-many entry:clinical_locations.conditions entry:clinical_conditions.clinical_locations',
        'many-to-many entry:clinical_locations.treatments entry:clinical_services.clinical_locations',
        'many-to-many entry:clinical_locations.clinical_resources entry:clinical_resources.clinical_locations',
        'many-to-many entry:clinical_conditions.clinical_resources entry:clinical_resources.clinical_conditions',
        'many-to-many entry:clinical_services.clinical_resources entry:clinical_resources.clinical_services',
        'many-to-many entry:clinical_expertise.clinical_expertise_related entry:clinical_expertise.clinical_expertise_related',
        'many-to-many entry:clinical_resources.clinical_resources entry:clinical_resources.clinical_resources',
        'many-to-many entry:knowledge_bases.related_articles entry:knowledge_bases.related_articles',
        'many-to-many entry:clinical_conditions.schema_primary_prevention entry:clinical_services.schema_primary_prevention',
        'many-to-many entry:clinical_conditions.schema_secondary_prevention entry:clinical_services.schema_secondary_prevention',
        'many-to-many entry:clinical_conditions.schema_possible_treatment entry:clinical_services.schema_treated_medical_condition',
        'many-to-many entry:clinical_conditions.schema_identifying_exam entry:clinical_services.schema_sign_detected_physical_exam',
        'many-to-many entry:clinical_conditions.schema_identifying_test entry:clinical_services.schema_sign_detected_medical_test',
        'many-to-many entry:clinical_conditions.schema_typical_test entry:clinical_services.schema_used_to_diagnose',
        'many-to-many entry:clinical_conditions.schema_has_sign_or_symptom entry:clinical_conditions.schema_is_sign_or_symptom_of',
        'many-to-many entry:clinical_services.schema_duplicate_therapy entry:clinical_services.schema_duplicate_therapy',
        'many-to-many entry:recognition_lists.members entry:people.recognition_lists',
    ]);
});
