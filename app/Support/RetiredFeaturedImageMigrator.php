<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Moves a `pages` entry off the retired `featured_image` handle onto the
 * standard `featured_image_16_9` that replaced it (UAMS-Web/uams-statamic-archive#2005).
 *
 * UAMS-Web/uams-statamic-archive#1553 retired the single-image handle from `pages/{basic_page,landing_page}` in
 * favor of the 3-handle cover-image set, but entries written before that — and by
 * the importer, which kept writing it until `UAMS-Web/wordpress-importer#433` —
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
    /**
     * The line terminators of a Statamic entry file, spelled out.
     *
     * Emphatically NOT `/\R/`. PCRE compiles `\R` to its Unicode newline set, so
     * in 8-bit non-UTF mode it also matches the bare bytes 0x0B, 0x0C and
     * **0x85** — and 0x85 is a legal UTF-8 *continuation* byte. Splitting on it
     * tears any character whose encoding contains it in half: `Å` (U+00C5 = C3
     * 85), `℅` (U+2105 = E2 84 85), `₅` (U+2085 = E2 82 85). Verified on PHP 8.4:
     * a title containing `Å` comes back as a dangling `C3` byte followed by a new
     * front-matter line — invalid UTF-8 and unparseable YAML, while the command
     * cheerfully reports `moved`.
     *
     * `/\R/u` is not the fix either: in UTF mode `\R` still matches U+0085,
     * U+2028 and U+2029, none of which is a line break in a YAML entry file.
     */
    private const string LINE_SPLIT = "/\r\n|\n|\r/";

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
        $bound = self::frontMatterBound($contents);

        if ($bound === null) {
            return [$contents, self::OUTCOME_UNCHANGED];
        }

        $lines = preg_split(self::LINE_SPLIT, $contents);

        if ($lines === false) {
            return [$contents, self::OUTCOME_UNCHANGED];
        }

        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $retiredAt = null;
        $replacementPresent = false;

        // Column 0 only, and only inside the front matter: an indented key of the
        // same name belongs to a nested block, and a line in the markdown body is
        // prose, not data.
        for ($i = 1; $i < $bound; $i++) {
            if (str_starts_with($lines[$i], self::RETIRED_HANDLE.':')) {
                $retiredAt ??= $i;
            }

            if (str_starts_with($lines[$i], self::REPLACEMENT_HANDLE.':')) {
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
     * The line index of the front matter's closing `---`, or null when the file
     * has no front matter to work in.
     */
    private static function frontMatterBound(string $contents): ?int
    {
        $lines = preg_split(self::LINE_SPLIT, $contents);

        if ($lines === false || ($lines[0] ?? null) !== '---') {
            return null;
        }

        $count = \count($lines);

        for ($i = 1; $i < $count; $i++) {
            if ($lines[$i] === '---') {
                return $i;
            }
        }

        return null;
    }

    /**
     * Whether an inline YAML scalar means "no value" — worth deleting rather
     * than carrying onto the replacement handle.
     */
    private static function isNullLiteral(string $value): bool
    {
        return \in_array(strtolower($value), ['null', '~', "''", '""'], true);
    }
}
