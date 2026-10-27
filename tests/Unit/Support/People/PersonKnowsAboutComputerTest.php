<?php

declare(strict_types=1);

use App\Support\People\PersonKnowsAboutComputer;
use Statamic\Fields\Value;

/**
 * Run the callback with PHP warnings thrown, so a warning from a normalizer fails the test.
 *
 * The handler is set inside the test body because one set in beforeEach() does not
 * catch warnings raised by the test.
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function knowsAboutWithWarningsThrown(callable $callback): mixed
{
    set_error_handler(static function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    }, E_WARNING);

    try {
        return $callback();
    } finally {
        restore_error_handler();
    }
}

/**
 * Behavior of PersonKnowsAboutComputer (app/Support/People/PersonKnowsAboutComputer.php):
 * normalize raw person "knows about" fields into clean lists — term fields to canonical
 * `taxonomy::slug` ids (defaulting bare slugs to a taxonomy, dropping orphans), entry-id
 * fields to de-duplicated trimmed string ids, and keyword lists (strings or value rows)
 * to trimmed strings.
 *
 * Mutation: with PersonKnowsAboutComputerGraphTest and the two knows-about feature tests,
 * every mutant is killed. The equivalent ones are annotated in the source with
 * `@pest-mutate-ignore` and the reason on the line above.
 *
 * @command  php artisan test --compact tests/Unit/Support/People/PersonKnowsAboutComputerTest.php
 */
it('normalizes a term field to canonical ids', function (mixed $raw, ?string $defaultTaxonomy, array $expected) {
    expect(knowsAboutWithWarningsThrown(fn (): array => PersonKnowsAboutComputer::normalizeTermFieldRaw($raw, $defaultTaxonomy)))->toBe($expected);
})->with([
    'null' => [null, 'credentials', []],
    'empty string' => ['', 'credentials', []],
    'bare slug with default taxonomy' => ['md', 'credentials', ['credentials::md']],
    'canonical id passes through' => ['subject_matter::cardiology', null, ['subject_matter::cardiology']],
    'list of bare and canonical' => [
        ['md', 'subject_matter::x', ['credentials::do']],
        'credentials',
        ['credentials::md', 'subject_matter::x', 'credentials::do'],
    ],
    'nested arrays flatten' => [
        [['a'], ['credentials::b']],
        'credentials',
        ['credentials::a', 'credentials::b'],
    ],
    'bare slug without a default is dropped' => ['orphan-slug', null, []],
    'a Value-wrapped raw unwraps' => [new Value('md'), 'credentials', ['credentials::md']],
    'a Value wrapping a Value unwraps both' => [new Value(new Value('md')), 'credentials', ['credentials::md']],
    'a Collection unwraps' => [collect(['md', 'subject_matter::x']), 'credentials', ['credentials::md', 'subject_matter::x']],
    'a nested Value item unwraps' => [[new Value('md')], 'credentials', ['credentials::md']],
    'a nested Collection unwraps' => [[collect(['md'])], 'credentials', ['credentials::md']],
    'a padded bare slug is trimmed' => ['  md  ', 'credentials', ['credentials::md']],
    'an uncanonicalizable token does not stop later tokens' => [['orphan', 'subject_matter::x'], null, ['subject_matter::x']],
    'a bare slug with an empty-string default is dropped' => ['md', '', []],
    'a whitespace-only string is dropped' => ['   ', 'credentials', []],
    'a non-string scalar is dropped' => [[5, 'md'], 'credentials', ['credentials::md']],
    'a top-level non-string scalar is dropped' => [5, 'credentials', []],
    'a duplicate mid-list does not stop later tokens' => [['md', 'md', 'do'], 'credentials', ['credentials::md', 'credentials::do']],
]);

it('normalizes entry ids to de-duplicated trimmed strings', function (mixed $raw, array $expected) {
    expect(knowsAboutWithWarningsThrown(fn (): array => PersonKnowsAboutComputer::normalizeEntryIdsRaw($raw)))->toBe($expected);
})->with([
    'null' => [null, []],
    'empty string' => ['', []],
    'single string' => ['entry-id-1', ['entry-id-1']],
    'array of strings de-duplicates' => [['a', 'b', 'a'], ['a', 'b']],
    'non-string elements are skipped, values trimmed' => [['ok', 12, null, '', ' ok2 '], ['ok', 'ok2']],
    'a Value-wrapped raw unwraps' => [new Value(['a', 'b']), ['a', 'b']],
    'a Collection unwraps' => [collect(['a', 'b']), ['a', 'b']],
    'a nested Value item unwraps' => [[new Value('a')], ['a']],
    'a duplicate mid-list does not stop later ids' => [['a', 'a', 'b'], ['a', 'b']],
    'a non-list scalar' => [5, []],
]);

it('flattens a keywords list (strings or value rows) to trimmed strings', function (mixed $raw, array $expected) {
    expect(knowsAboutWithWarningsThrown(fn (): array => PersonKnowsAboutComputer::flattenKeywordsListRaw($raw)))->toBe($expected);
})->with([
    'null' => [null, []],
    'single string is trimmed' => ['  alpha  ', ['alpha']],
    'list of strings (trimmed, dupes kept)' => [['a', ' b ', 'a'], ['a', 'b', 'a']],
    'list of value rows' => [[['value' => ' one '], ['value' => 'two']], ['one', 'two']],
    'mixed strings and value rows' => [['plain', ['value' => 'row']], ['plain', 'row']],
    'a Value-wrapped value unwraps' => [new Value(['a', ' b ']), ['a', 'b']],
    'a Collection unwraps' => [collect([' x ', 'y']), ['x', 'y']],
    'a whitespace-only string is empty' => ['   ', []],
    'a nested Value item unwraps' => [[new Value(' v ')], ['v']],
    'a whitespace-only list item is skipped' => [['keep', '   '], ['keep']],
    'a whitespace item before a valid one continues (not break)' => [['   ', 'keep'], ['keep']],
    'a value-row with a non-string value is skipped' => [[['value' => 123], ['value' => 'ok']], ['ok']],
    'a value-row with a whitespace value is skipped' => [[['value' => '   '], ['value' => 'ok']], ['ok']],
    'a row without a value key is skipped' => [[['other' => 'x'], 'plain'], ['plain']],
    'an array-like object with a value key is not a row' => [[new ArrayObject(['value' => 'obj'])], []],
    'a non-list scalar' => [5, []],
]);
