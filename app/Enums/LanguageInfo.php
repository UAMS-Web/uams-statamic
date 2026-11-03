<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing language information on enum cases.
 *
 * @param  string  $name  The English name of the language
 * @param  string  $scope  The scope of the language
 * @param  string  $type  The type of the language
 * @param  ?string  $iso639_1  The ISO 639-1 code
 * @param  ?string  $iso639_2_b  The ISO 639-2/B code
 * @param  ?string  $iso639_2_t  The ISO 639-2/T code
 * @param  ?string  $iso639_3  The ISO 639-3 code
 * @param  ?string  $iso639_5  The ISO 639-5 code
 * @param  list<string>  $alternate_names  The alternate names of the language
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class LanguageInfo
{
    /**
     * Initializes a LanguageInfo attribute instance with language ISO 639 codes
     * and comprehensive language information.
     *
     * @param  string  $name  The English name of the language
     * @param  string  $scope  The scope of the language
     * @param  string  $type  The type of the language
     * @param  ?string  $iso639_1  The ISO 639-1 code
     * @param  ?string  $iso639_2_b  The ISO 639-2/B code
     * @param  ?string  $iso639_2_t  The ISO 639-2/T code
     * @param  ?string  $iso639_3  The ISO 639-3 code
     * @param  ?string  $iso639_5  The ISO 639-5 code
     * @param  list<string>  $alternate_names  The alternate names of the language
     */
    public function __construct(
        public string $name,
        public string $scope,
        public string $type,
        public ?string $iso639_1,
        public ?string $iso639_2_b,
        public ?string $iso639_2_t,
        public ?string $iso639_3,
        public ?string $iso639_5,
        public array $alternate_names = [],
    ) {}
}
