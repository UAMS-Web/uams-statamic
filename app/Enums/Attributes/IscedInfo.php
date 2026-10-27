<?php

declare(strict_types=1);

namespace App\Enums\Attributes;

use Attribute;

/**
 * Attribute for storing ISCED classification metadata on enum cases.
 *
 * Used by `App\Enums\IscedA2011`, `App\Enums\IscedP2011`, and
 * `App\Enums\IscedF2013`. The seven fields below match the published code
 * lists plus derived hierarchy (parent/ancestors/children/descendants/tier).
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class IscedInfo
{
    /**
     * @param  string  $code  Canonical ISCED code (digits-only string); same as the enum case backing value
     * @param  string  $label  Official English label from the source classification
     * @param  ?string  $parent  Immediate parent code, or null at the top tier
     * @param  array<int, string>  $ancestors  Ordered ancestor codes from root to immediate parent
     * @param  array<int, string>  $children  Direct child codes present in the classification
     * @param  array<int, string>  $descendants  All transitive descendant codes
     * @param  int  $tier  Hierarchy depth, 1 for top-level concepts
     */
    public function __construct(
        public string $code,
        public string $label,
        public ?string $parent,
        public array $ancestors,
        public array $children,
        public array $descendants,
        public int $tier,
    ) {}
}
