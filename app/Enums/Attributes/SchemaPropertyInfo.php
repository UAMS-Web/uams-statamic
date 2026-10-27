<?php

declare(strict_types=1);

namespace App\Enums\Attributes;

use Attribute;

/**
 * Attribute carrying Schema.org property metadata for a SchemaProperty enum case.
 *
 * Property names align with schema-jsonld-normalization.md translation table
 * (source key → enum/attribute name). Descriptions and domain/range are
 * type-agnostic: one description per property.
 *
 * @see app/Services/EnumGeneration/Schema/schema-jsonld-normalization.md
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class SchemaPropertyInfo
{
    /**
     * @param  string|null  $rdfs_comment  Property description (rdfs:comment), HTML with links
     * @param  list<string>  $schema_domain_includes  Type names this property applies to (schema:domainIncludes)
     * @param  list<string>  $range_includes  Effective value types for tooling: vocabulary
     *                                        schema:rangeIncludes plus, when applicable, OWL-implied
     *                                        Role, Text, and URL (see schema-jsonld-normalization.md;
     *                                        mirrors schema.org software/util/sdoowl.py DEFAULTRANGES
     *                                        and https://schema.org/docs/datamodel.html conformance)
     * @param  string|null  $superseded_by  Local name of the property that supersedes this property
     *                                      (schema:supersededBy), or null if not superseded
     */
    public function __construct(
        public ?string $rdfs_comment = null,
        public array $schema_domain_includes = [],
        public array $range_includes = [],
        public ?string $superseded_by = null,
    ) {}
}
