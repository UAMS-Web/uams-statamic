<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

/**
 * Somewhere a current Google Business Profile category list can be read from (UAMS-Web/uams-statamic-archive#2615).
 *
 * UAMS-Web/uams-statamic-archive#2091 decided the diff runs from a pasted file until the Google Business Information
 * API is available, then from the API on a schedule. Each is one implementation of this
 * contract, so the diff and its report never know which one they were given.
 */
interface CategorySource
{
    /**
     * A short description of the source, recorded with each check (a file path, or the API).
     */
    public function label(): string;

    /**
     * Read the current list.
     *
     * A line or record that cannot be read is returned in {@see CategoryList::$malformed}
     * rather than thrown, so one bad line never stops a check.
     *
     * @throws CategorySourceUnavailable When the source cannot be read at all.
     */
    public function read(): CategoryList;
}
