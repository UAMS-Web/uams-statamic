<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use RuntimeException;

/**
 * The feed or an item page could not be fetched or read (UAMS-Web/uams-statamic-archive#1803).
 *
 * A sync that meets this for the feed makes no publication changes at all.
 */
final class HealthTalkSourceException extends RuntimeException {}
