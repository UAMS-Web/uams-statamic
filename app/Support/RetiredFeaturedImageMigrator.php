<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Moves a `pages` entry off the retired `featured_image` handle onto the
 * standard `featured_image_16_9` that replaced it (UAMS-Web/uams-statamic-archive#2005).
 *
 * UAMS-Web/uams-statamic-archive#1553 retired the single-image handle from `pages/{basic_page,landing_page}` in
 * favor of the 3-handle cover-image set, but entries written before that — and by
 * the importer, which kept writing it until `UAMS-Web/wordpress-importer-archive#433` —
 * still hold the old key. No blueprint reads it, so those pages resolve no
 * `og:image` and no card thumbnail.
 *
 * ## Why this rewrites the file text instead of re-saving the entry
 *
 * The obvious implementation — mutate `$entry->data()` and `$entry->save()` —
 * works, and produces a diff nobody can review. Saving re-serializes the WHOLE entry
 * through Statamic's YAML dumper, whose block style differs from however these
 * files were originally written, so unrelated `page_builder` list items collapse
 * from `-\n  id: x` to `- id: x` throughout. Measured on the real corpus: 29 files,
 * 1,930 changed lines, of which just 58 were the key move. The other 97% was
 * reformatting nobody asked for, sitting on top of editorial content.
 *
 * Renaming the key in place touches exactly one line per file and leaves every
 * other byte identical. The value is *moved* rather than dropped: the asset paths
 * are real and editorially chosen.
 */
final class RetiredFeaturedImageMigrator
{
    /** The retired handle being migrated away from. */
    public const string RETIRED_HANDLE = 'featured_image';

    /** The handle that replaced it. */
    public const string REPLACEMENT_HANDLE = 'featured_image_16_9';

    /** The file did not carry the retired handle in its front matter. */
    public const string OUTCOME_UNCHANGED = 'unchanged';

    /** The key was renamed in place, preserving its value. */
    public const string OUTCOME_MOVED = 'moved';

    /**
     * The retired key was removed without moving anything — either it held no
     * value, or the replacement handle was already present and must not be
     * overwritten.
     */
    public const string OUTCOME_DROPPED = 'dropped';

    /**
     * The key carries no inline value, so a block may hang beneath it. Left
     * untouched and reported for a human rather than guessed at.
     */
    public const string OUTCOME_NEEDS_REVIEW = 'needs-review';

    /**
     * Apply the migration to one entry file's contents.
     *
     * @return array{0: string, 1: string} the new contents, and one of the OUTCOME_* constants
     */
    public static function apply(string $contents): array
    {
        // Split on CRLF, LF, or CR only: never `/\R/`, whose Unicode newline set
        // matches 0x0B, 0x0C, and 0x85 (a legal UTF-8 continuation byte in
        // characters such as `Å`, `℅`, and `₅`), tearing UTF-8 apart.
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));

        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $retiredAt = null;
        $replacementPresent = false;

        // Column 0 only, and only inside the front matter: an indented key of the
        // same name belongs to a nested block, and a line in the markdown body is
        // prose, not data.
        foreach (self::frontMatterLines($lines) as $i => $line) {
            if (str_starts_with($line, self::RETIRED_HANDLE.':')) {
                $retiredAt ??= $i;
            }

            if (str_starts_with($line, self::REPLACEMENT_HANDLE.':')) {
                $replacementPresent = true;
            }
        }

        if ($retiredAt === null) {
            return [$contents, self::OUTCOME_UNCHANGED];
        }

        $value = trim(substr($lines[$retiredAt], \strlen(self::RETIRED_HANDLE) + 1));

        if ($value === '') {
            // No inline value: a list or map may hang beneath. Deleting the key
            // would orphan it and renaming could collide, so leave it for a human.
            return [$contents, self::OUTCOME_NEEDS_REVIEW];
        }

        if ($replacementPresent || self::isNullLiteral($value)) {
            unset($lines[$retiredAt]);

            return [implode($newline, $lines), self::OUTCOME_DROPPED];
        }

        $lines[$retiredAt] = self::REPLACEMENT_HANDLE.':'.substr($lines[$retiredAt], \strlen(self::RETIRED_HANDLE) + 1);

        return [implode($newline, $lines), self::OUTCOME_MOVED];
    }

    /**
     * Front-matter lines keyed by their original index, or empty when the file
     * has no valid front matter to work in.
     *
     * @param  non-empty-list<string>  $lines
     * @return array<int, string>
     */
    private static function frontMatterLines(array $lines): array
    {
        if ($lines[0] !== '---') {
            return [];
        }

        $frontMatter = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            if ($line === '---') {
                return $frontMatter;
            }

            $frontMatter[$index] = $line;
        }

        return [];
    }

    /**
     * Whether an inline YAML scalar means "no value" — worth deleting rather
     * than carrying onto the replacement handle.
     */
    private static function isNullLiteral(string $value): bool
    {
        return match (strtolower($value)) {
            'null', '~', "''", '""' => true,
            default => false,
        };
    }
}
