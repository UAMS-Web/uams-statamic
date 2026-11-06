<?php

declare(strict_types=1);

use App\Modifiers\IndefiniteArticle;
use Tests\TestCase;

/**
 * Pin the modifier's public input conversion and spoken article output.
 *
 * Linux / PHP 8.4.27 / PCOV: 2/2 statements covered; 2/2 covered mutants
 * killed after one equivalent EmptyStringToNotEmpty exclusion. Its fixed
 * "PEST Mutator was here!" fallback and the original empty fallback both
 * produce "a" for every non-scalar input; scalar inputs never use that branch.
 * The breadth check measures exactly one excluded mutant. Every killable
 * mutant is dead.
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/IndefiniteArticleTest.php
 */
uses(TestCase::class);

covers(IndefiniteArticle::class);

it('chooses an article from the spoken phrase while rejecting non-scalar inputs', function (
    mixed $value,
    string $expected
): void {
    expect((new IndefiniteArticle)->index($value, [], []))->toBe($expected);
})->with([
    'consonant' => ['doctor', 'a'],
    'vowel' => ['oncologist', 'an'],
    'silent consonant' => ['hour', 'an'],
    'spoken consonant at vowel' => ['university physician', 'a'],
    'spoken initial' => ['MRI specialist', 'an'],
    'spoken consonant initial' => ['CRNA', 'a'],
    'empty string' => ['', 'a'],
    'integer' => [17, 'a'],
    'float' => [1.5, 'a'],
    'boolean' => [true, 'a'],
    'null' => [null, 'a'],
    'array' => [['MRI'], 'a'],
    'object' => [new stdClass, 'a'],
]);
