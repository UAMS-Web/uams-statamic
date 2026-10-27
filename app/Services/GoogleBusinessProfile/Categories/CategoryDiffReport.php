<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

/**
 * How a category list differs from the Google Business Profile category taxonomy (UAMS-Web/uams-statamic-archive#2615).
 *
 * The report is the whole output: acting on it is an editor's call.
 */
final readonly class CategoryDiffReport
{
    /**
     * @param  array<string, string>  $new  Google's name for each GCID no term carries, keyed by GCID.
     * @param  list<array{gcid: string, slug: string, title: string, name: string}>  $renamed  Terms whose title differs from Google's name.
     * @param  list<array{gcid: string, slug: string, title: string}>  $removed  Terms whose GCID the list does not carry.
     * @param  list<array{slug: string, title: string}>  $withoutGcid  Terms with no GCID, which cannot be compared.
     * @param  list<array{line: int, content: string, reason: string}>  $malformed  Lines of the list that could not be read.
     * @param  list<array{gcid: string, slug: string, title: string}>  $unreadable  Terms whose GCID the list carries only on a line it could not read (UAMS-Web/uams-statamic-archive#2944).
     */
    public function __construct(
        public array $new,
        public array $renamed,
        public array $removed,
        public array $withoutGcid,
        public array $malformed,
        public array $unreadable = [],
    ) {}

    /**
     * How many of each kind the report holds.
     *
     * @return array{new: int, renamed: int, removed: int, unreadable: int, without_gcid: int, malformed: int}
     */
    public function counts(): array
    {
        return [
            'new' => \count($this->new),
            'renamed' => \count($this->renamed),
            'removed' => \count($this->removed),
            'unreadable' => \count($this->unreadable),
            'without_gcid' => \count($this->withoutGcid),
            'malformed' => \count($this->malformed),
        ];
    }

    /**
     * The report as plain data, for the check record.
     *
     * @return array{new: array<string, string>, renamed: list<array{gcid: string, slug: string, title: string, name: string}>, removed: list<array{gcid: string, slug: string, title: string}>, unreadable: list<array{gcid: string, slug: string, title: string}>, without_gcid: list<array{slug: string, title: string}>, malformed: list<array{line: int, content: string, reason: string}>}
     */
    public function toArray(): array
    {
        return [
            'new' => $this->new,
            'renamed' => $this->renamed,
            'removed' => $this->removed,
            'unreadable' => $this->unreadable,
            'without_gcid' => $this->withoutGcid,
            'malformed' => $this->malformed,
        ];
    }
}
