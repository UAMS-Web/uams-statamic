<?php

declare(strict_types=1);

use App\Tags\ReusableContentEntry;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Tests\TestCase;

/**
 * Pin both reusable-content keys, blueprint isolation, and empty-value handling.
 *
 * Linux, PHP 8.4.27, PCOV: 12/12 covered mutants killed with no survivors.
 *
 * @command  php artisan test --compact tests/Feature/Tags/ReusableContentEntryTagTest.php
 */
uses(TestCase::class);

covers(ReusableContentEntry::class);

it('keeps both keys null when the parameter is not an entry', function (mixed $entry): void {
    // Prevent nested reusable references from inheriting their parent's values.
    $tag = new ReusableContentEntry;
    $tag->setContext([]);
    $tag->setParameters(['entry' => $entry]);

    expect($tag->index())->toBe(['reusable_sets' => null, 'reusable_blocks' => null]);
})->with([
    'missing' => [null],
    'identifier only' => ['entry-id'],
    'data array' => [['content' => 'parent content']],
]);

it('exposes only fields declared by the referenced entry blueprint', function (
    bool $hasContent,
    bool $hasBlocks,
    mixed $content,
    mixed $blocks,
    array $expected
): void {
    // Keep blueprint membership independent of the values available for reading.
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('hasField')->with('content')->andReturn($hasContent);
    $blueprint->shouldReceive('hasField')->with('page_builder')->andReturn($hasBlocks);
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('augmentedValue')->with('content')->andReturn(new Value($content));
    $entry->shouldReceive('augmentedValue')->with('page_builder')->andReturn(new Value($blocks));

    // Read distinct fields through the tag's public interface.
    $tag = new ReusableContentEntry;
    $tag->setContext([]);
    $tag->setParameters(['entry' => $entry]);

    expect($tag->index())->toBe($expected);
})->with([
    'both declared' => [true, true, '<p>Bard</p>', [['type' => 'heading']], [
        'reusable_sets' => '<p>Bard</p>', 'reusable_blocks' => [['type' => 'heading']],
    ]],
    'Bard only' => [true, false, '<p>Bard</p>', ['parent blocks'], [
        'reusable_sets' => '<p>Bard</p>', 'reusable_blocks' => null,
    ]],
    'blocks only' => [false, true, 'parent Bard', [['type' => 'heading']], [
        'reusable_sets' => null, 'reusable_blocks' => [['type' => 'heading']],
    ]],
    'neither declared' => [false, false, 'parent Bard', ['parent blocks'], [
        'reusable_sets' => null, 'reusable_blocks' => null,
    ]],
    'empty fields' => [true, true, '', [], [
        'reusable_sets' => null, 'reusable_blocks' => null,
    ]],
]);

it('returns augmented Bard markup rather than the raw editor document', function (): void {
    // Use a real fieldtype so raw() and value() produce different results.
    $field = new Field('content', ['type' => 'bard']);
    $value = new Value(
        [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Reusable paragraph']]]],
        'content',
        $field->fieldtype()
    );
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('hasField')->with('content')->andReturnTrue();
    $blueprint->shouldReceive('hasField')->with('page_builder')->andReturnFalse();
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('augmentedValue')->with('content')->andReturn($value);

    // Require the content returned to templates to be ready for rendering.
    $tag = new ReusableContentEntry;
    $tag->setContext([]);
    $tag->setParameters(['entry' => $entry]);

    expect($tag->index())->toBe([
        'reusable_sets' => '<p>Reusable paragraph</p>',
        'reusable_blocks' => null,
    ]);
});
