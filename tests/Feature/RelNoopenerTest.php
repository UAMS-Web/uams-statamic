<?php

declare(strict_types=1);

use App\Modifiers\RelNoopener;
use App\Support\BardRelNoopenerBackfill;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#724: the render-time `rel_noopener` modifier and the one-shot
 * Bard backfill both inject rel="noopener noreferrer" into new-window links that
 * stored none, without disturbing anything else.
 *
 * @command  php artisan test --compact tests/Feature/RelNoopenerTest.php
 */

/** Run the modifier over an HTML string. */
function relNoopener(string $html): string
{
    /** @var string $result */
    $result = (new RelNoopener)->index($html, [], []);

    return $result;
}

it('injects rel into a target="_blank" anchor with no rel', function (): void {
    expect(relNoopener('<a href="https://x.test" target="_blank">X</a>'))
        ->toBe('<a href="https://x.test" target="_blank" rel="noopener noreferrer">X</a>');
});

it('preserves an author-set rel', function (): void {
    $html = '<a href="https://x.test" target="_blank" rel="nofollow">X</a>';

    expect(relNoopener($html))->toBe($html);
});

it('ignores same-tab anchors', function (): void {
    $html = '<p><a href="/internal">Internal</a></p>';

    expect(relNoopener($html))->toBe($html);
});

it('is not fooled by a data-rel attribute (still injects the real rel)', function (): void {
    expect(relNoopener('<a href="https://x.test" data-rel="x" target="_blank">X</a>'))
        ->toBe('<a href="https://x.test" data-rel="x" target="_blank" rel="noopener noreferrer">X</a>');
});

it('does not touch a data-target="_blank" that opens no new window', function (): void {
    $html = '<a href="/a" data-target="_blank">A</a>';

    expect(relNoopener($html))->toBe($html);
});

it('fixes only the new-window anchor among several', function (): void {
    $html = '<p><a href="/a">A</a> and <a href="https://b.test" target="_blank">B</a></p>';

    expect(relNoopener($html))
        ->toContain('<a href="/a">A</a>')
        ->toContain('<a href="https://b.test" target="_blank" rel="noopener noreferrer">B</a>');
});

it('is registered as the rel_noopener Antlers modifier', function (): void {
    $html = (string) Antlers::parse(
        '{{ value | rel_noopener }}',
        ['value' => '<a href="https://x.test" target="_blank">X</a>'],
    );

    expect($html)->toContain('noopener noreferrer');
});

it('backfills a stored Bard link mark with a null rel', function (): void {
    $data = ['content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'x', 'marks' => [
                ['type' => 'link', 'attrs' => ['href' => 'https://x.test', 'rel' => null, 'target' => '_blank']],
            ]],
        ]],
    ]];

    [$new, $count] = BardRelNoopenerBackfill::apply($data);
    /** @var array{content: list<array{content: list<array{marks: list<array{attrs: array{rel: string}}>}>}>} $new */
    expect($count)->toBe(1);
    expect($new['content'][0]['content'][0]['marks'][0]['attrs']['rel'])->toBe('noopener noreferrer');
});

it('leaves same-tab and already-rel Bard link marks alone in the backfill', function (): void {
    $data = ['content' => [
        ['type' => 'text', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/a', 'rel' => null, 'target' => null]]]],
        ['type' => 'text', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://b.test', 'rel' => 'nofollow', 'target' => '_blank']]]],
    ]];

    [$new, $count] = BardRelNoopenerBackfill::apply($data);

    expect($count)->toBe(0);
    expect($new)->toBe($data);
});

it('reads a Stringable value as its HTML', function (): void {
    $html = new class implements Stringable
    {
        public function __toString(): string
        {
            return '<a href="https://example.org" target="_blank">Example</a>';
        }
    };

    expect((new RelNoopener)->index($html, [], []))
        ->toBe('<a href="https://example.org" target="_blank" rel="noopener noreferrer">Example</a>');
});

it('reads a scalar value as text', function (): void {
    expect((new RelNoopener)->index(42, [], []))->toBe('42');
});

it('passes a value that is not text through unchanged', function (mixed $value): void {
    expect((new RelNoopener)->index($value, [], []))->toBe($value);
})->with([
    'null' => [null],
    'a list' => [['<a target="_blank">x</a>']],
]);
