<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Throwable;

/**
 * What the faked source serves. One stub reads it, because `Http::fake()` appends
 * stubs and the first one registered keeps answering.
 */
final class HealthTalkFakeSource
{
    public static string $feed = '';

    public static int $feedStatus = 200;

    public static ?Throwable $failure = null;

    /**
     * Item ids whose page returns a 500.
     *
     * @var list<string>
     */
    public static array $failingPages = [];

    /**
     * Item page bodies that replace the fixture, by item id.
     *
     * @var array<int|string, string>
     */
    public static array $pages = [];
}
