<?php

declare(strict_types=1);

use App\Modifiers\UniqueTerms;

/**
 * Behavior of the `unique_terms` Antlers modifier (app/Modifiers/UniqueTerms.php):
 * normalize list input with NormalizeList, then deduplicate by a stable key,
 * preserving first-seen order. Objects exposing id() compare by that string,
 * scalars/null and arrays by a prefixed serialize(), id-less objects by
 * spl_object_id and resources by get_resource_id.
 * The normalization is covered by NormalizeListTest.
 *
 * Mutation: this is a thin dedup loop, so the residual survivors are equivalents: the
 * dedup-key prefixes and concat order ('scalar:', 'array:', 'obj:', 'resource:') are
 * cosmetic one at a time (each key is already unambiguous, so any consistent unique key
 * dedupes identically), the `(string) $id` cast is redundant with the string return
 * type, and the `$seen[$key] = true` marker is irrelevant to the `isset()` check.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/UniqueTermsTest.php
 */
$alpha = new class
{
    public function id(): string
    {
        return 'subject_matter::alpha';
    }
};
$beta = new class
{
    public function id(): string
    {
        return 'subject_matter::beta';
    }
};
$alphaAgain = new class
{
    public function id(): string
    {
        return 'subject_matter::alpha';
    }
};

$plainA = new stdClass;
$plainB = new stdClass;

$streamA = fopen('php://memory', 'r');
$streamB = fopen('php://memory', 'r');

it('removes duplicate items by stable key, preserving order', function (mixed $value, array $expected) {
    expect((new UniqueTerms)->index($value, [], []))->toBe($expected);
})->with([
    // id()-bearing objects dedupe by id string ($alphaAgain shares alpha's id)
    'deduplicates by term id, keeping the first occurrence' => [[$alpha, $beta, $alphaAgain], [$alpha, $beta]],
    'empty input returns an empty array' => [[], []],
    // items without id() fall back: scalars by serialize, objects by spl_object_id
    'scalars without id() deduplicate by value' => [['a', 'b', 'a'], ['a', 'b']],
    'id-less objects deduplicate by instance identity' => [[$plainA, $plainA, $plainB], [$plainA, $plainB]],
    // arrays have no identity of their own, so they dedupe by value like scalars (UAMS-Web/uams-statamic-archive#2916)
    'equal array items deduplicate by value' => [[[1], [2], [1]], [[1], [2]]],
    // a resource has no object id; it dedupes by its own resource id
    'resources deduplicate by instance identity' => [[$streamA, $streamA, $streamB], [$streamA, $streamB]],
]);
