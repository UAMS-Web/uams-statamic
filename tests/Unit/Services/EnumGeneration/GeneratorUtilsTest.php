<?php

declare(strict_types=1);

use App\Services\EnumGeneration\GeneratorUtils;

/**
 * Unit tests for {@see GeneratorUtils::escapeSingleQuotes()}, the escaping the
 * state and International Code Designator generators use for single-quoted
 * literals (#717).
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/GeneratorUtilsTest.php
 */
it('escapes a value so the single-quoted literal evaluates back to it', function (string $value): void {
    $literal = "'".GeneratorUtils::escapeSingleQuotes($value)."'";

    // A literal that does not parse is a failure of this case, not of the run
    try {
        $evaluated = eval('return '.$literal.';');
    } catch (ParseError $error) {
        $evaluated = 'ParseError: '.$error->getMessage();
    }

    expect($evaluated)->toBe($value);
})->with([
    'empty' => [''],
    'plain' => ['Arkansas'],
    'apostrophe' => ["O'Hare"],
    'trailing backslash' => ['end\\'],
    'doubled backslash' => ['a\\\\b'],
    'backslash before apostrophe' => ['x\\\'y'],
    'lone backslash' => ['\\'],
    'escape-like sequence' => ['\\n\\t'],
]);

it('escapes backslashes before quotes, never double-escaping', function (): void {
    expect(GeneratorUtils::escapeSingleQuotes("a'b"))->toBe('a\\\'b')
        ->and(GeneratorUtils::escapeSingleQuotes('a\\b'))->toBe('a\\\\b')
        ->and(GeneratorUtils::escapeSingleQuotes('\\\''))->toBe('\\\\\\\'');
});
