<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Relationships\BoundedRelationshipProcessor;
use Illuminate\Support\ServiceProvider;
use Stillat\Relationships\Processors\RelationshipProcessor;
use Stillat\Relationships\Support\Facades\Relate;

/**
 * Service provider for Stillat Entry Relationships configuration.
 *
 * Centralizes all Stillat Relationships wiring so relationship sync behavior is
 * not mixed into the application bootstrap provider.
 *
 * ## How to use
 *
 * Define relationships in `boot()` using the `Relate` facade. The addon listens
 * to Statamic events and keeps both sides in sync when editors add/remove
 * related items, or when related items are deleted.
 *
 * Supported relationship types include `manyToMany`, `oneToMany`, `manyToOne`,
 * and `oneToOne`. Choose the relationship method that matches your field
 * configuration (e.g. `max_items: 1` implies one-to-one semantics).
 *
 * ## Relationship types (definitions)
 *
 * Many-to-many (both sides allow many):
 * - `Relate::manyToMany('entry:conferences.sponsored_by', 'entry:sponsors.sponsoring');`
 * - `Relate::manyToMany('term:topics.posts', 'entry:posts.topics');`
 *
 * Many-to-one (many items relate to one; the “one” side is `max_items: 1`):
 * - `Relate::manyToOne('entry:authors.books', 'entry:books.author');`
 *
 * One-to-many (inverse of many-to-one; the “many” side allows many):
 * - `Relate::oneToMany('entry:books.author', 'entry:authors.books');`
 *
 * One-to-one (both sides are `max_items: 1`):
 * - `Relate::oneToOne('entry:employees.position', 'entry:positions.filled_by');`
 * - `Relate::oneToOne('term:certifications.credentials_term', 'term:credentials.certifications_term');`
 *
 * ## Entity / field reference syntax
 *
 * Stillat Relationships supports entries, terms, and users:
 * - Entries: `entry:{collectionHandle}.{fieldHandle}`
 * - Terms: `term:{taxonomyHandle}.{fieldHandle}`
 * - Users: `user:{fieldHandle}`
 *
 * Example (terms):
 * - `Relate::oneToOne('term:certifications.credentials_term',
 *   'term:credentials.certifications_term');`
 *
 * ## Helper commands
 *
 * - `php please relate:list` lists configured relationships.
 * - `php please relate:fill` backfills/repairs relationships after adding new
 *   fields or mappings.
 *
 * Add new relationship mappings here so the site has a single source of truth
 * for bidirectional sync behavior.
 */
class StillatRelationshipsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[\Override]
    public function register(): void
    {
        // Bind a processor that clears its per-operation working sets before each
        // relationship, so the manager's singleton processor cannot accumulate
        // effected entities across the importer + `relate:fill` bulk-save paths
        // (unbounded memory + stale-snapshot clobber). The vendor manager resolves
        // its processor via `$app->make(RelationshipProcessor::class)`, and this
        // bind runs before the manager is first built in `boot()`. See UAMS-Web/uams-statamic-archive#1406.
        $this->app->singleton(
            RelationshipProcessor::class,
            BoundedRelationshipProcessor::class
        );
    }

    /**
     * Bootstrap Stillat Relationships mappings.
     */
    public function boot(): void
    {
        $this->registerCertificationCredentialSync();
        $this->registerPeopleOntologySync();
        $this->registerCrossOntologyRelatedSync();
        $this->registerSchemaSemanticSync();
        $this->registerRecognitionListMembershipSync();
    }

    /**
     * One-to-one sync between the certifications and credentials taxonomies.
     */
    private function registerCertificationCredentialSync(): void
    {
        Relate::oneToOne(
            'term:certifications.credentials_term',
            'term:credentials.certifications_term'
        );
    }

    /**
     * Family 1 (UAMS-Web/uams-statamic-archive#1360 / UAMS-Web/uams-statamic-archive#1361) — stored bidirectional sync between the `people`
     * collection and the clinical ontology's five "Related Clinical Providers"
     * fields, so relating a provider to a condition/service/expertise/location/
     * resource writes the inverse onto that person, and the addon owns delete
     * cleanup.
     *
     * Every "Related Clinical Providers" field points at `people` (a provider
     * entry only wraps a person). The provider-side handle is `clinical_providers`
     * on every ontology collection EXCEPT `clinical_locations`, where it is
     * `physician_locations`. The `people.expertise` reciprocal is therefore
     * `clinical_expertise.clinical_providers` — the UAMS-Web/uams-statamic-archive#1360 table's
     * `clinical_expertise.physician_expertise` does not exist and would no-op.
     */
    private function registerPeopleOntologySync(): void
    {
        Relate::manyToMany('entry:people.clinical_locations', 'entry:clinical_locations.physician_locations');
        Relate::manyToMany('entry:people.expertise', 'entry:clinical_expertise.clinical_providers');
        Relate::manyToMany('entry:people.conditions', 'entry:clinical_conditions.clinical_providers');
        Relate::manyToMany('entry:people.treatments', 'entry:clinical_services.clinical_providers');
        Relate::manyToMany('entry:people.clinical_resources', 'entry:clinical_resources.clinical_providers');
    }

    /**
     * Family 2 (UAMS-Web/uams-statamic-archive#1360 / UAMS-Web/uams-statamic-archive#1362) — stored bidirectional sync for the "general
     * related" cross-ontology `entries` relationships among the clinical
     * collections, plus the three symmetric self-relationships.
     *
     * Every pair already has an `entries` field on both sides, so this is pure
     * wiring: adding a related entry on one side writes the inverse on the other,
     * and the addon owns the delete cleanup. Handles are verified against the
     * post-renormalization blueprints — the `clinical_expertise` side uses
     * `clinical_*`-prefixed handles (`clinical_conditions`, `clinical_services`,
     * `clinical_locations`, `clinical_expertise_related`), NOT the shared
     * fieldset's bare `conditions`/`treatments`/`related_locations`/`associated`,
     * which do not exist on that blueprint and would silently no-op.
     *
     * The two legacy `prohibited` migrate-then-clear import aides
     * (`clinical_services.clinical_conditions`, `clinical_conditions.clinical_services`)
     * are intentionally excluded — they are never synced.
     */
    private function registerCrossOntologyRelatedSync(): void
    {
        // Cross-collection related pairs
        Relate::manyToMany('entry:clinical_expertise.clinical_locations', 'entry:clinical_locations.expertise');
        Relate::manyToMany('entry:clinical_expertise.clinical_conditions', 'entry:clinical_conditions.clinical_expertise');
        Relate::manyToMany('entry:clinical_expertise.clinical_services', 'entry:clinical_services.clinical_expertise');
        Relate::manyToMany('entry:clinical_expertise.clinical_resources', 'entry:clinical_resources.clinical_expertise');
        Relate::manyToMany('entry:clinical_locations.conditions', 'entry:clinical_conditions.clinical_locations');
        Relate::manyToMany('entry:clinical_locations.treatments', 'entry:clinical_services.clinical_locations');
        Relate::manyToMany('entry:clinical_locations.clinical_resources', 'entry:clinical_resources.clinical_locations');
        Relate::manyToMany('entry:clinical_conditions.clinical_resources', 'entry:clinical_resources.clinical_conditions');
        Relate::manyToMany('entry:clinical_services.clinical_resources', 'entry:clinical_resources.clinical_services');

        // Symmetric self-relationships (the same reference on both sides)
        Relate::manyToMany('entry:clinical_expertise.clinical_expertise_related', 'entry:clinical_expertise.clinical_expertise_related');
        Relate::manyToMany('entry:clinical_resources.clinical_resources', 'entry:clinical_resources.clinical_resources');
        Relate::manyToMany('entry:knowledge_bases.related_articles', 'entry:knowledge_bases.related_articles');
    }

    /**
     * Family 3 (UAMS-Web/uams-statamic-archive#1360 / UAMS-Web/uams-statamic-archive#1363) — stored bidirectional sync for the schema-semantic
     * Condition ↔ Service relationships (and their self-relationships). Unlike
     * Families 1–2 these are specific Schema.org `inverseOf` claims, each verified
     * against the field's semantics before wiring:
     *
     *  - possible_treatment ↔ treated_medical_condition (therapy ⇄ condition treated)
     *  - identifying_exam ↔ sign_detected_physical_exam (exam ⇄ sign it detects)
     *  - identifying_test ↔ sign_detected_medical_test (test ⇄ sign it detects)
     *  - typical_test ↔ used_to_diagnose (test ⇄ condition it diagnoses)
     *  - primary/secondary_prevention are same-named inverses on each side.
     *
     * `has_sign_or_symptom` ↔ `is_sign_or_symptom_of` is a directed inverse WITHIN
     * clinical_conditions; `duplicate_therapy` is a symmetric self-relationship.
     *
     * `clinical_services.schema_subtest` (component tests of a panel) is intentionally
     * NOT synced: it is a hierarchical containment with no Schema.org `superTest`
     * inverse field, so it stays one-directional and is never self-mapped.
     */
    private function registerSchemaSemanticSync(): void
    {
        Relate::manyToMany('entry:clinical_conditions.schema_primary_prevention', 'entry:clinical_services.schema_primary_prevention');
        Relate::manyToMany('entry:clinical_conditions.schema_secondary_prevention', 'entry:clinical_services.schema_secondary_prevention');
        Relate::manyToMany('entry:clinical_conditions.schema_possible_treatment', 'entry:clinical_services.schema_treated_medical_condition');
        Relate::manyToMany('entry:clinical_conditions.schema_identifying_exam', 'entry:clinical_services.schema_sign_detected_physical_exam');
        Relate::manyToMany('entry:clinical_conditions.schema_identifying_test', 'entry:clinical_services.schema_sign_detected_medical_test');
        Relate::manyToMany('entry:clinical_conditions.schema_typical_test', 'entry:clinical_services.schema_used_to_diagnose');

        // Self-relationships within a single collection
        Relate::manyToMany('entry:clinical_conditions.schema_has_sign_or_symptom', 'entry:clinical_conditions.schema_is_sign_or_symptom_of');
        Relate::manyToMany('entry:clinical_services.schema_duplicate_therapy', 'entry:clinical_services.schema_duplicate_therapy');
    }

    /**
     * Two-way sync between a `people` provider and the `recognition_lists`
     * structured collection (UAMS-Web/uams-statamic-archive#1546). A person's `recognition_lists` entries field
     * points at whichever node applies — a list entry (whole-list membership) or a
     * category child entry (categorized membership) — and each list/category
     * entry's `members` field is the inverse.
     *
     * Holding membership as a top-level `entries` relationship on both sides (the
     * former per-person replicator could not be synced — stillat only hooks
     * top-level fields) lets editors author a roster from EITHER end, including
     * bulk-adding dozens of members from the list/category entry, and makes
     * membership queryable via `whereJsonOverlaps('recognition_lists', …)` for the
     * provider-block recognition filter (UAMS-Web/uams-statamic-archive#1176).
     *
     * The `members` handle is shared by both the `recognition_list` and
     * `recognition_list_category` blueprints, so a single mapping covers list- and
     * category-level membership.
     */
    private function registerRecognitionListMembershipSync(): void
    {
        Relate::manyToMany(
            'entry:recognition_lists.members',
            'entry:people.recognition_lists'
        );
    }
}
