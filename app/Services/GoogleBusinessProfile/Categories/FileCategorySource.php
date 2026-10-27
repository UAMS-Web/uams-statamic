<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

/**
 * Reads a category list pasted into a file, one `GCID<TAB>Name` line per category (UAMS-Web/uams-statamic-archive#2615).
 *
 * That is the format pleper.com's GBP categories tool hands a person to copy (UAMS-Web/uams-statamic-archive#2091). A
 * GCID may carry the `gcid:` prefix the Google Business Information API writes, which is
 * dropped, so both sources key categories the way the taxonomy's `gcid` field stores them.
 * Blank lines are skipped. Any other line that is not a GCID, a tab and a name, or that
 * repeats a GCID already read, is reported with its line number and the rest are read.
 */
final readonly class FileCategorySource implements CategorySource
{
    public function __construct(private string $path) {}

    #[\Override]
    public function label(): string
    {
        return 'file: '.$this->path;
    }

    #[\Override]
    public function read(): CategoryList
    {
        $lines = is_file($this->path) && is_readable($this->path)
            ? file($this->path, FILE_IGNORE_NEW_LINES)
            : false;

        if ($lines === false) {
            throw new CategorySourceUnavailable("Cannot read the category file at {$this->path}.");
        }

        $categories = [];
        $malformed = [];
        $unreadable = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $problem = $this->parse($line, $categories, $unreadable);

            if (\is_string($problem)) {
                $malformed[] = ['line' => $index + 1, 'content' => $line, 'reason' => $problem];
            }
        }

        return new CategoryList($categories, $malformed, array_values($unreadable));
    }

    /**
     * Add one line's category, or say why it cannot be added.
     *
     * A line rejected after its GCID was read adds that GCID to `$unreadable`, as
     * {@see ApiCategorySource} does, so its term is not reported removed (UAMS-Web/uams-statamic-archive#2944).
     *
     * @param  array<string, string>  $categories  The categories read so far.
     * @param  array<string, string>  $unreadable  GCIDs read from lines that were rejected, keyed by themselves.
     * @return string|null Why the line was rejected, or null when it was read.
     */
    private function parse(string $line, array &$categories, array &$unreadable): ?string
    {
        if (! str_contains($line, "\t")) {
            return 'no tab between the GCID and the name';
        }

        [$gcid, $name] = explode("\t", $line, 2);
        $gcid = trim($gcid);
        $name = trim($name);

        if (str_starts_with($gcid, 'gcid:')) {
            $gcid = substr($gcid, \strlen('gcid:'));
        }

        if (preg_match(CategoryList::GCID_PATTERN, $gcid) !== 1) {
            return 'the GCID is not lowercase letters, digits and underscores';
        }

        if ($name === '') {
            $unreadable[$gcid] = $gcid;

            return 'no name after the tab';
        }

        if (\array_key_exists($gcid, $categories)) {
            $unreadable[$gcid] = $gcid;

            return 'the GCID appears on an earlier line';
        }

        $categories[$gcid] = $name;

        return null;
    }
}
