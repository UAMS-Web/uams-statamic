<?php

declare(strict_types=1);

use App\Support\Statamic\Modifiers\WordMetricsModifierInput;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * The text the UAMS word-count and read-time modifiers count, normalized as Statamic core does
 * (#3343 coverage).
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/Modifiers/WordMetricsModifierInputTest.php
 */

/**
 * A Bard set as it augments: its fields wrapped in `Values`, the text in a `Value`.
 */
function wordMetricsBardNode(string $type, string $text): Values
{
    return new Values(['type' => $type, 'text' => new Value($text)]);
}

it('passes a string through for word counting, tags and all', function (): void {
    expect(WordMetricsModifierInput::normalizeForWordCount('<p>Two words</p>'))->toBe('<p>Two words</p>');
});

it('unwraps a field value before counting', function (): void {
    expect(WordMetricsModifierInput::normalizeForWordCount(new Value('Wrapped text')))->toBe('Wrapped text');
});

it('reads a non-string scalar, null or stringable value as its string form', function (mixed $value, string $expected): void {
    expect(WordMetricsModifierInput::normalizeForWordCount($value))->toBe($expected);
})->with([
    'an integer' => [42, '42'],
    'true' => [true, '1'],
    'null' => [null, ''],
    'a stringable' => [new class implements Stringable
    {
        public function __toString(): string
        {
            return 'from a stringable';
        }
    }, 'from a stringable'],
]);

it('reads a value with no string form as no text', function (): void {
    expect(WordMetricsModifierInput::normalizeForWordCount(new stdClass))->toBe('');
});

it('joins the text sets of Bard content with spaces, skipping every other set', function (): void {
    $bard = [
        wordMetricsBardNode('text', 'First paragraph.'),
        wordMetricsBardNode('image', 'not text'),
        ['type' => 'text', 'text' => 'a plain array is not an augmented set'],
        wordMetricsBardNode('text', 'Second paragraph.'),
    ];

    expect(WordMetricsModifierInput::normalizeForWordCount($bard))->toBe('First paragraph. Second paragraph.')
        ->and(WordMetricsModifierInput::normalizeForWordCount(new Value($bard)))->toBe('First paragraph. Second paragraph.');
});

it('strips tags from the text it reads for read time', function (): void {
    expect(WordMetricsModifierInput::normalizeForReadTime(new Value('<p>Two <strong>words</strong></p>')))->toBe('Two words')
        ->and(WordMetricsModifierInput::normalizeForReadTime([wordMetricsBardNode('text', '<em>Set</em> text')]))->toBe('Set text');
});
