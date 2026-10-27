<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

use RuntimeException;

/**
 * A category source that could not be read at all, as opposed to one with bad lines (UAMS-Web/uams-statamic-archive#2615).
 */
final class CategorySourceUnavailable extends RuntimeException {}
