<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks in valid Antlers colon syntax for custom modifiers that reject
 * `key="value"` pipe segments (see modifier class docblocks). This is an Antlers
 * pipe-integration characterization; each modifier's value logic is owned by its
 * unit test (tests/Unit/Modifiers/{FormatBitrate,FormatBytes,ReadTimeUams}Test),
 * so it is not separately mutation-tested here.
 *
 * @command  php artisan test --compact tests/Feature/Views/ModifierAntlersPipeSyntaxTest.php
 */
it('format_bitrate accepts positional colon segments', function () {
    expect(trim((string) Antlers::parse('{{ b | format_bitrate:binary }}', ['b' => 1536], true)))
        ->toBe('1.5 kibit/s');

    expect(trim((string) Antlers::parse('{{ b | format_bitrate:decimal:abbreviated_unit_with_space:-1:kb }}', ['b' => 1234], true)))
        ->toBe('1.234 kbit/s');
});

it('format_bytes accepts positional colon segments', function () {
    expect(trim((string) Antlers::parse('{{ x | format_bytes:binary }}', ['x' => 1536], true)))
        ->toBe('1.5 KiB');

    expect(trim((string) Antlers::parse('{{ x | format_bytes:decimal:abbreviated_unit_with_space:-1:kB }}', ['x' => 1234], true)))
        ->toBe('1.234 kB');
});

it('read_time_uams uses a positional unit, not a named pipe', function () {
    expect(trim((string) Antlers::parse('{{ t | read_time_uams:seconds }}', ['t' => 'one two three four five'], true)))
        ->toBe('2');

    expect(trim((string) Antlers::parse('{{ t | read_time_uams:150:seconds }}', ['t' => str_repeat('word ', 400)], true)))
        ->toBe('160');
});
