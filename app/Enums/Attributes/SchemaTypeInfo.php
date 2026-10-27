<?php

declare(strict_types=1);

namespace App\Enums\Attributes;

use Attribute;

/**
 * Attribute carrying Schema.org type metadata for a SchemaType enum case.
 *
 * Property names align with schema-jsonld-normalization.md translation table
 * (source key → enum/attribute name). Hierarchy and property lists are
 * normalized per that document (e.g., rdfs:subClassOf → list of local names).
 *
 * @see app/Services/EnumGeneration/Schema/schema-jsonld-normalization.md
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class SchemaTypeInfo
{
    /**
     * @param  string|null  $rdfs_comment  Type description (rdfs:comment), HTML with links
     * @param  list<string>  $rdfs_sub_class_of  Immediate parent type names (rdfs:subClassOf)
     * @param  list<string>  $children  Immediate child type names
     * @param  list<string>  $ancestors  All ancestor type names (transitive)
     * @param  list<string>  $descendants  All descendant type names (transitive)
     * @param  list<string>  $property_names  Property names for this type (domainIncludes + inheritance)
     * @param  string  $slug  Sluggified type name (lowercase, spaces/hyphens → underscore) for scope handles, URLs
     * @param  string|null  $superseded_by  Local name of the type or property that supersedes this type
     *                                      (schema:supersededBy), or null if not superseded
     * @param  list<string>  $members  For enumeration types only: local names of enumeration
     *                                 member values (e.g. ['AlgorithmicMediaDigitalSource', ...])
     */
    public function __construct(
        public ?string $rdfs_comment = null,
        public array $rdfs_sub_class_of = [],
        public array $children = [],
        public array $ancestors = [],
        public array $descendants = [],
        public array $property_names = [],
        public string $slug = '',
        public ?string $superseded_by = null,
        public array $members = [],
    ) {}
}
