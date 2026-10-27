<?php

declare(strict_types=1);

namespace App\Enums\Attributes;

use Attribute;

/**
 * Attribute for storing ISCO-08 occupation group metadata on enum cases.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Isco08Info
{
    /**
     * Holds metadata attached to each `Isco08` enum case via `#[Isco08Info(...)]`.
     *
     * @param  int  $level  Hierarchy level (1–4)
     * @param  string  $code  ISCO-08 code (1–4 digit string); same as the enum case backing value
     * @param  string  $major_group  Major group code (1 digit)
     * @param  ?string  $sub_major_group  Sub-major group code (2 digits)
     * @param  ?string  $minor_group  Minor group code (3 digits)
     * @param  ?string  $unit_group  First four characters of `code`, or null when the code is shorter than four characters
     * @param  string  $title  English title
     * @param  string  $definition  Group definition text
     * @param  string  $tasks_include  Tasks include text (may contain newlines)
     * @param  ?string  $excluded_occupations  Excluded occupations text, or null when empty
     * @param  ?string  $notes  Notes text, or null when empty
     * @param  array<int, string>  $occupational_titles  Index occupational titles for this code
     * @param  ?string  $parent  Immediate parent code, or null at major group
     * @param  array<int, string>  $ancestors  Ordered ancestor codes from major to parent
     * @param  array<int, string>  $children  Direct child codes present in the structure
     * @param  array<int, string>  $descendants  All descendant codes (transitive)
     */
    public function __construct(
        public int $level,
        public string $code,
        public string $major_group,
        public ?string $sub_major_group,
        public ?string $minor_group,
        public ?string $unit_group,
        public string $title,
        public string $definition,
        public string $tasks_include,
        public ?string $excluded_occupations,
        public ?string $notes,
        public array $occupational_titles,
        public ?string $parent,
        public array $ancestors,
        public array $children,
        public array $descendants,
    ) {}
}
