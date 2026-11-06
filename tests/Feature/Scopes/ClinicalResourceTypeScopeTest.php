<?php

declare(strict_types=1);

use App\Scopes\ClinicalResourceType;
use Illuminate\Support\Collection;
use Statamic\Query\ItemQueryBuilder;
use Tests\TestCase;

/**
 * Pin resource-type filtering through real in-memory query results.
 *
 * Linux orb, PHP 8.4/PCOV: 5/5 covered statements and 9 killed mutants.
 * No accepted equivalent mutants; every killable mutant is dead.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/ClinicalResourceTypeScopeTest.php
 */
uses(TestCase::class);

covers(ClinicalResourceType::class);

it('filters only the requested blueprint while preserving existing query constraints', function (
    array $values,
    array $expected
): void {
    // Include every supported subtype, an absent blueprint, and an excluded site.
    $query = new ItemQueryBuilder;
    $query->withItems(collect([
        ['id' => 'article-main', 'blueprint' => 'article', 'site' => 'main'],
        ['id' => 'audio-main', 'blueprint' => 'audio', 'site' => 'main'],
        ['id' => 'document-main', 'blueprint' => 'document', 'site' => 'main'],
        ['id' => 'infographic-main', 'blueprint' => 'infographic', 'site' => 'main'],
        ['id' => 'video-main', 'blueprint' => 'video', 'site' => 'main'],
        ['id' => 'untyped-main', 'site' => 'main'],
        ['id' => 'article-other', 'blueprint' => 'article', 'site' => 'other'],
    ]));
    $query->where('site', 'main');

    // Apply the public scope and compare the selected records, not query internals.
    (new ClinicalResourceType)->apply($query, mustBeStringKeyed($values, 'scope values'));

    expect(mustBe(Collection::class, $query->get(), 'selected records')->pluck('id')->all())->toBe($expected);
})->with([
    'article' => [['type' => 'article'], ['article-main']],
    'trimmed audio' => [['type' => " \taudio\n"], ['audio-main']],
    'document' => [['type' => 'document'], ['document-main']],
    'infographic' => [['type' => 'infographic'], ['infographic-main']],
    'video' => [['type' => 'video'], ['video-main']],
    'unknown type' => [['type' => 'unknown'], []],
    'zero string is a filter' => [['type' => '0'], []],
    'missing type' => [[], ['article-main', 'audio-main', 'document-main', 'infographic-main', 'video-main', 'untyped-main']],
    'empty type' => [['type' => ''], ['article-main', 'audio-main', 'document-main', 'infographic-main', 'video-main', 'untyped-main']],
    'whitespace type' => [['type' => " \t\n"], ['article-main', 'audio-main', 'document-main', 'infographic-main', 'video-main', 'untyped-main']],
    'null type' => [['type' => null], ['article-main', 'audio-main', 'document-main', 'infographic-main', 'video-main', 'untyped-main']],
    'numeric type' => [['type' => 7], ['article-main', 'audio-main', 'document-main', 'infographic-main', 'video-main', 'untyped-main']],
    'array type' => [['type' => ['audio']], ['article-main', 'audio-main', 'document-main', 'infographic-main', 'video-main', 'untyped-main']],
]);
