<?php

declare(strict_types=1);

namespace App\Enums;

use Attribute;

/**
 * Attribute for storing MIME type information on enum cases.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class MimeTypeInfo
{
    /**
     * Initialize a new MimeTypeInfo attribute instance.
     *
     * @param  string  $mimeType  The MIME type string (e.g., 'application/pdf')
     * @param  array<int,string>  $extensions  Array of file extensions (e.g., ['pdf'])
     * @param  string  $category  The MIME type category (e.g., 'application', 'image')
     */
    public function __construct(
        /**
         * The MIME type string identifier.
         */
        public string $mimeType,
        /**
         * Array of file extensions associated with this MIME type.
         */
        public array $extensions = [],
        /**
         * The MIME type category classification.
         */
        public string $category = ''
    ) {}
}
