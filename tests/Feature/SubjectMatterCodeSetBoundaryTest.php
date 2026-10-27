<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2823: a concept the NUCC Health Care Provider Taxonomy code set covers,
 * or a UAMS extension of it, lives only in `clinical_specializations`. `subject_matter`
 * keeps what the code set cannot express, so no subject may repeat a concept the
 * clinical specializations already hold, by name or by code.
 *
 * A name is compared after folding case, `&` to `and`, punctuation, a plural `s`, and
 * the words "service(s)", "provider(s)" and "physician", so "dental services" and the
 * NUCC grouping "Dental Providers" are one concept. A subject's alternate names count
 * as names. The code side is every clinical specialization's title, `code_name`,
 * `code_name_display`, alternate titles and `code`.
 *
 * It is a name test, so it catches a concept repeated under its own name, not one
 * renamed: measured over the 53 subjects before UAMS-Web/uams-statamic-archive#2823, it flags 35 of the 42 that
 * moved and none of the 11 that stay. The 7 it cannot see use other words for the
 * concept ("social work" for Social Worker, "medicine" for Allopathic & Osteopathic
 * Physicians); UAMS-Web/uams-statamic-archive#2823's classification table is the record for those. Subjects carry
 * no `code` field today, so the code arm guards data written by other means.
 *
 * @command  php artisan test --compact tests/Feature/SubjectMatterCodeSetBoundaryTest.php
 */

/**
 * A concept name folded for comparison.
 */
function codeSetBoundaryFold(string $name): string
{
    $name = mb_strtolower(str_replace('&', ' and ', $name));
    $name = (string) preg_replace('/[^a-z0-9]+/', ' ', $name);
    $words = array_filter(
        explode(' ', $name),
        static fn (string $word): bool => $word !== '' && ! in_array($word, ['service', 'services', 'provider', 'providers', 'physician', 'and', 'the', 'of'], true),
    );

    // Fold a plural `s` (not `ss`), so "assistants" meets "Assistant"
    $words = array_map(
        static fn (string $word): string => strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss') ? substr($word, 0, -1) : $word,
        $words,
    );

    return implode(' ', $words);
}

/**
 * The strings an entry is known by: its title and, for a subject, each alternate name;
 * for a clinical specialization, its NUCC name and display name.
 *
 * @return list<string>
 */
function codeSetBoundaryNames(StatamicEntry $entry): array
{
    $names = [$entry->get('title'), $entry->get('code_name'), $entry->get('code_name_display')];

    foreach (['alternate_name', 'title_specialization_patient_alt'] as $handle) {
        foreach ((array) $entry->get($handle) as $row) {
            $names[] = is_array($row) ? ($row['text'] ?? null) : null;
        }
    }

    return array_values(array_filter($names, static fn (mixed $name): bool => is_string($name) && trim($name) !== ''));
}

/**
 * Each subject that repeats a clinical specialization, as "subject: reason".
 *
 * @param  iterable<StatamicEntry>  $subjects
 * @param  iterable<StatamicEntry>  $specializations
 * @return list<string>
 */
function codeSetBoundaryDuplicates(iterable $subjects, iterable $specializations): array
{
    $byName = [];
    $byCode = [];

    foreach ($specializations as $specialization) {
        foreach (codeSetBoundaryNames($specialization) as $name) {
            $byName[codeSetBoundaryFold($name)] = mustBeString($specialization->slug(), 'a specialization slug');
        }

        $code = $specialization->get('code');

        if (is_string($code) && $code !== '') {
            $byCode[strtoupper(trim($code))] = mustBeString($specialization->slug(), 'a specialization slug');
        }
    }

    $duplicates = [];

    foreach ($subjects as $subject) {
        $slug = mustBeString($subject->slug(), 'a subject slug');

        foreach (codeSetBoundaryNames($subject) as $name) {
            $folded = codeSetBoundaryFold($name);

            if ($folded !== '' && isset($byName[$folded])) {
                $duplicates[] = "{$slug}: the name '{$name}' is clinical specialization {$byName[$folded]}";
            }
        }

        $code = $subject->get('code');

        if (is_string($code) && isset($byCode[strtoupper(trim($code))])) {
            $duplicates[] = "{$slug}: the code {$code} is clinical specialization {$byCode[strtoupper(trim($code))]}";
        }
    }

    return $duplicates;
}

/**
 * The entries of one collection on `default`.
 *
 * @return list<StatamicEntry>
 */
function codeSetBoundaryEntries(string $collection): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', $collection);

    /** @var Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    return array_values($entries->all());
}

/**
 * An unsaved entry in a collection, for planting a duplicate without writing content.
 *
 * @param  array<string, mixed>  $data
 */
function codeSetBoundaryEntry(string $collection, string $slug, array $data): StatamicEntry
{
    $entry = mustBe(StatamicEntry::class, Entry::make(), 'an entry');
    $entry->collection($collection);
    $entry->slug($slug);
    $entry->data($data);

    return $entry;
}

it('keeps every subject out of the concepts the clinical specializations hold', function (): void {
    $this->requireContentCollection('subject_matter');
    $this->requireContentCollection('clinical_specializations');

    $subjects = codeSetBoundaryEntries('subject_matter');
    $specializations = codeSetBoundaryEntries('clinical_specializations');

    // Both sides are populated, so an empty result is a comparison rather than a miss
    expect($subjects)->not->toBe([])
        ->and($specializations)->not->toBe([])
        ->and(codeSetBoundaryDuplicates($subjects, $specializations))->toBe([]);
});

it('fails for a subject planted beside the clinical specializations that repeats one', function (array $data, string $reason): void {
    $this->requireContentCollection('clinical_specializations');

    /** @var array<string, mixed> $data */
    $planted = codeSetBoundaryEntry('subject_matter', 'zz-planted', $data);

    expect(codeSetBoundaryDuplicates([$planted], codeSetBoundaryEntries('clinical_specializations')))
        ->toHaveCount(1)
        ->and(codeSetBoundaryDuplicates([$planted], codeSetBoundaryEntries('clinical_specializations'))[0])->toContain($reason);
})->with([
    'a NUCC grouping under a subject-style name' => [['title' => 'dental services'], 'clinical specialization dental-providers'],
    'a coded classification by its display name' => [['title' => 'physician assistant'], 'clinical specialization 363a00000x'],
    'a plural of a coded classification' => [['title' => 'physician assistants'], 'clinical specialization 363a00000x'],
    'an alternate title a specialization carries' => [['title' => 'orthopedic surgery'], 'clinical specialization 207x00000x'],
    'a UAMS grouping extension' => [['title' => 'Nursing Support'], 'clinical specialization nursing-support'],
    'an alternate name' => [['title' => 'zz probe', 'alternate_name' => [['text' => 'Pharmacy Service Providers']]], 'clinical specialization pharmacy-service-providers'],
    'a code' => [['title' => 'zz probe', 'code' => '207X00000X'], 'the code 207X00000X is clinical specialization 207x00000x'],
]);

it('passes a subject that names no clinical concept', function (string $title): void {
    $this->requireContentCollection('clinical_specializations');

    // Controls for the fold: a discipline sharing a word with a specialization is not one
    $planted = codeSetBoundaryEntry('subject_matter', 'zz-planted', ['title' => $title]);

    expect(codeSetBoundaryDuplicates([$planted], codeSetBoundaryEntries('clinical_specializations')))->toBe([]);
})->with([
    'biochemical genetics' => ['biochemical genetics'],
    'health sciences' => ['health sciences'],
    'UAMS employee' => ['UAMS employee'],
]);
