<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

/**
 * One reading of a Google Business Profile category list (UAMS-Web/uams-statamic-archive#2615).
 */
final readonly class CategoryList
{
    /**
     * A GCID as the taxonomy stores it: lowercase letters, digits and underscores.
     */
    public const string GCID_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * @param  array<string, string>  $categories  Google's name for each category, keyed by GCID.
     * @param  list<array{line: int, content: string, reason: string}>  $malformed  Lines that could not be read.
     * @param  list<string>  $unreadable  GCIDs read from a line that was then rejected, so the list
     *                                    carries the category although its line could not be read (UAMS-Web/uams-statamic-archive#2944).
     */
    public function __construct(
        public array $categories,
        public array $malformed = [],
        public array $unreadable = [],
    ) {}
}
