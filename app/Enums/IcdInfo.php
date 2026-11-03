<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing ISO 6523 ICD (International Code Designator)
 * information on enum cases.
 *
 * @param  string  $id  The 4-digit ICD code
 * @param  string  $name  The official name of the scheme
 * @param  string  $description  Notes on use and issuing agency
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class IcdInfo
{
    /**
     * @param  string  $id  The 4-digit ICD code
     * @param  string  $name  The official name of the scheme
     * @param  string  $description  Notes on use and issuing agency
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description = '',
    ) {}
}
