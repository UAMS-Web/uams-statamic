<?php

declare(strict_types=1);

namespace App\Enums\Attributes;

use Attribute;

/**
 * Attribute carrying Schema.org enumeration member metadata for a
 * SchemaEnumerationMember enum case.
 *
 * Each member belongs to one or more enumeration types (the schema.org
 * type(s) given in the member node's @type in the JSON-LD).
 *
 * @see app/Services/EnumGeneration/Schema/schema-jsonld-normalization.md
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class SchemaEnumerationMemberInfo
{
    /**
     * @param  list<string>  $type  Enumeration type name(s) for which this
     *                              object is an enumeration member (e.g.
     *                              ['IPTCDigitalSourceEnumeration'])
     * @param  string|null  $rdfs_comment  Member description (rdfs:comment), HTML with links
     */
    public function __construct(
        public array $type = [],
        public ?string $rdfs_comment = null,
    ) {}
}
