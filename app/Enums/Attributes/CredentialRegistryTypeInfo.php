<?php

declare(strict_types=1);

namespace App\Enums\Attributes;

use Attribute;

/**
 * Metadata for a Credential Engine / CTDL registry class term.
 *
 * Narrative fields (label, definition, comment, usage_note, status, same_as) and
 * canonical subclass_of are sourced from credreg.net CTDL JSON when available
 * (see CredentialRegistryEnumGenerator). Class hierarchy lists (children,
 * ancestors, descendants) come from CredentialRegistry fixtures; property lists
 * from description_set_item_maps.yml.
 *
 * @see https://github.com/CredentialEngine/CredentialRegistry
 * @see https://credreg.net/ctdl/terms/
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class CredentialRegistryTypeInfo
{
    /**
     * @param  list<string>  $children  Immediate child class terms (prefixed shorthand)
     * @param  list<string>  $ancestors  Transitive ancestor terms
     * @param  list<string>  $descendants  Transitive descendant terms
     * @param  list<string>  $property_names  Property shorthands (description-set domain hints)
     * @param  list<string>  $in_range_of  Property shorthands (description-set range hints)
     */
    public function __construct(
        public string $slug = '',
        public string $uri = '',
        public string $shorthand_uri = '',
        public string $label = '',
        public string $definition = '',
        public string $comment = '',
        public string $usage_note = '',
        public string $term_type = '',
        public string $status = '',
        public string $same_as = '',
        public string $subclass_of = '',
        public array $children = [],
        public array $ancestors = [],
        public array $descendants = [],
        public array $property_names = [],
        public array $in_range_of = [],
    ) {}
}
