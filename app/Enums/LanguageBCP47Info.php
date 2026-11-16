<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing BCP 47 language information on enum cases.
 *
 * @param  string  $type  The type of the language tag
 * @param  string  $description  The description of the language
 * @param  string  $added  The date when the language was added
 * @param  ?string  $scope  The scope of the language
 * @param  ?string  $comments  Additional comments about the language
 * @param  ?string  $macrolanguage  The macrolanguage code
 * @param  ?string  $deprecated  The deprecation date if deprecated
 * @param  ?string  $preferredvalue  The preferred value if deprecated
 * @param  ?string  $suppressscript  The suppress script code
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class LanguageBCP47Info
{
    /**
     * Initializes a LanguageBCP47Info attribute instance with BCP 47 language
     * registry information.
     *
     * @param  string  $type  The type of the language tag
     * @param  string  $description  The description of the language
     * @param  string  $added  The date when the language was added
     * @param  ?string  $scope  The scope of the language
     * @param  ?string  $comments  Additional comments about the language
     * @param  ?string  $macrolanguage  The macrolanguage code
     * @param  ?string  $deprecated  The deprecation date if deprecated
     * @param  ?string  $preferredvalue  The preferred value if deprecated
     * @param  ?string  $suppressscript  The suppress script code
     */
    public function __construct(
        public string $type,
        public string $description,
        public string $added,
        public ?string $scope = null,
        public ?string $comments = null,
        public ?string $macrolanguage = null,
        public ?string $deprecated = null,
        public ?string $preferredvalue = null,
        public ?string $suppressscript = null,
    ) {}
}
