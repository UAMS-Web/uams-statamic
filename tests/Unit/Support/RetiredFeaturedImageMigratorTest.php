<?php

declare(strict_types=1);

use App\Support\RetiredFeaturedImageMigrator as Migrator;

/**
 * The pure transform behind `content:migrate-pages-featured-image` (UAMS-Web/uams-statamic-archive#2005).
 *
 * A unit test with no Statamic involved, so the branch table can be covered
 * exhaustively — the command's own test only proves scoping and writing.
 *
 * @command  php artisan test --compact tests/Unit/Support/RetiredFeaturedImageMigratorTest.php
 */

/**
 * A Statamic entry file with the given front matter lines and a markdown body.
 *
 * @param  array<int, string>  $frontMatter
 */
function migratorFixture(array $frontMatter, string $body = "Some page copy.\n"): string
{
    return "---\n".implode("\n", $frontMatter)."\n---\n".$body;
}

it('renames the key in place and changes nothing else', function (): void {
    $before = migratorFixture([
        'id: 42b53c79',
        'blueprint: landing_page',
        "title: 'About UAMS'",
        'featured_image: default/hospital-exterior-nite.jpg',
        'hide_from_menu: false',
    ]);

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_MOVED)
        ->and($after)->toBe(migratorFixture([
            'id: 42b53c79',
            'blueprint: landing_page',
            "title: 'About UAMS'",
            'featured_image_16_9: default/hospital-exterior-nite.jpg',
            'hide_from_menu: false',
        ]));

    // The regression that forced this rewrite: exactly ONE line may differ.
    $diff = array_diff(explode("\n", $after), explode("\n", $before));
    expect($diff)->toHaveCount(1);
});

it('leaves a nested block-style list untouched', function (): void {
    // Re-saving through Statamic's YAML dumper collapsed `-\n  id: x` to
    // `- id: x` across every page_builder in the corpus — 1,872 lines of
    // collateral reformatting. Renaming the key must never reflow anything.
    $before = migratorFixture([
        'featured_image: default/scotty.jpg',
        'page_builder:',
        '  -',
        '    id: m87s7y6t',
        "    heading: 'Chancellor Patterson'",
    ]);

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_MOVED)
        ->and($after)->toContain("  -\n    id: m87s7y6t")
        ->and($after)->not->toContain('- id: m87s7y6t');
});

it('ignores an indented key of the same name', function (): void {
    // A nested `featured_image` belongs to a block — only column 0 is the entry's.
    $before = migratorFixture([
        'page_builder:',
        '  -',
        '    featured_image: default/nested.jpg',
    ]);

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_UNCHANGED)
        ->and($after)->toBe($before);
});

it('ignores the handle when it appears in the markdown body', function (): void {
    // Prose, not data — the front matter bound is what makes this safe.
    $before = migratorFixture(
        ['title: Docs'],
        "The old key was:\n\nfeatured_image: something\n"
    );

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_UNCHANGED)
        ->and($after)->toBe($before);
});

it('drops the retired key instead of overwriting an existing replacement', function (): void {
    $before = migratorFixture([
        'featured_image: default/old-uhura.jpg',
        'featured_image_16_9: default/new-uhura.jpg',
    ]);

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_DROPPED)
        ->and($after)->toBe(migratorFixture(['featured_image_16_9: default/new-uhura.jpg']));
});

it('drops a null-literal value rather than carrying it across', function (string $literal): void {
    [$after, $outcome] = Migrator::apply(migratorFixture([
        'title: Sulu',
        "featured_image: {$literal}",
    ]));

    expect($outcome)->toBe(Migrator::OUTCOME_DROPPED)
        ->and($after)->toBe(migratorFixture(['title: Sulu']))
        // Emphatically not a null replacement handle — that is the same
        // unreachable-image bug wearing the new handle's name.
        ->and($after)->not->toContain(Migrator::REPLACEMENT_HANDLE);
})->with(['null', '~', "''", '""', 'NULL']);

it('refuses a key with no inline value rather than guessing', function (): void {
    // A list or map may hang beneath it; deleting would orphan the block and
    // renaming could collide. Report it for a human instead.
    $before = migratorFixture([
        'featured_image:',
        '  - default/chekov.jpg',
    ]);

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_NEEDS_REVIEW)
        ->and($after)->toBe($before);
});

it('leaves a file with no front matter alone', function (): void {
    $before = "featured_image: default/no-front-matter.jpg\n";

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_UNCHANGED)
        ->and($after)->toBe($before);
});

it('preserves CRLF line endings', function (): void {
    $before = "---\r\ntitle: Kirk\r\nfeatured_image: default/kirk.jpg\r\n---\r\nBody\r\n";

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_MOVED)
        ->and($after)->toBe("---\r\ntitle: Kirk\r\nfeatured_image_16_9: default/kirk.jpg\r\n---\r\nBody\r\n");
});

it('preserves multi-byte UTF-8 containing the 0x85 continuation byte', function (string $label, string $char): void {
    // `/\R/` uses PCRE's Unicode newline set, which matches the BARE byte 0x85 —
    // a legal UTF-8 continuation byte. That splits any character encoding it
    // mid-way, yielding invalid UTF-8 and a spurious extra front-matter line
    // while the command still reports `moved`. Silent content destruction.
    $title = "title: 'Uhura {$char} Memorial'";
    $before = migratorFixture([$title, 'featured_image: default/x.jpg']);

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_MOVED)
        ->and(mb_check_encoding($before, 'UTF-8'))->toBeTrue("fixture {$label} is not valid UTF-8")
        ->and(mb_check_encoding($after, 'UTF-8'))->toBeTrue("output for {$label} is not valid UTF-8")
        ->and($after)->toContain($title)
        // Line count must not grow: a mid-character split adds a line.
        ->and(substr_count($after, "\n"))->toBe(substr_count($before, "\n"));
})->with([
    'A-ring (C3 85)' => ['A-ring', "\u{00C5}"],
    'care-of (E2 84 85)' => ['care-of', "\u{2105}"],
    'subscript five (E2 82 85)' => ['subscript-five', "\u{2085}"],
]);

it('does not treat a vertical tab or form feed as a line break', function (string $byte): void {
    // The other two bytes `\R` over-matches. A form feed inside a value is not a
    // line terminator in a YAML entry file.
    $before = migratorFixture([
        "title: 'Before{$byte}After'",
        'featured_image: default/x.jpg',
    ]);

    [$after, $outcome] = Migrator::apply($before);

    expect($outcome)->toBe(Migrator::OUTCOME_MOVED)
        ->and($after)->toContain("title: 'Before{$byte}After'")
        ->and(substr_count($after, "\n"))->toBe(substr_count($before, "\n"));
})->with([
    'vertical tab' => ["\x0B"],
    'form feed' => ["\x0C"],
]);

it('preserves a quoted value verbatim', function (): void {
    [$after, $outcome] = Migrator::apply(migratorFixture([
        "featured_image: 'default/with spaces.jpg'",
    ]));

    expect($outcome)->toBe(Migrator::OUTCOME_MOVED)
        ->and($after)->toContain("featured_image_16_9: 'default/with spaces.jpg'");
});
