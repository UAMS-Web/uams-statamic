<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Widgets\IncompletePublishedEntries;
use Illuminate\Support\MessageBag;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;

/**
 * Test double exposing the protected per-user read path so its defensive
 * "drop candidates that no longer resolve" branch can be exercised directly (UAMS-Web/uams-statamic-archive#860).
 */
class IncompletePublishedEntriesWidgetTestDouble extends IncompletePublishedEntries
{
    /**
     * @param  list<array{id: string, edit_url: string, title: string, collection_title: string, blueprint_title: string}>  $rows
     * @return array<int, array{edit_url: string, title: string, collection_title: string, blueprint_title: string}>
     */
    public function exposeVisibleFailingList(array $rows): array
    {
        return $this->visibleFailingList($rows);
    }

    /**
     * Expose the resolved scan cap so the UNCAPPED DEFAULT can be pinned directly (UAMS-Web/uams-statamic-archive#1905).
     *
     * Asserting it through a fixture cannot work: any fixture small enough to run
     * fits under any plausible cap, so `scanned === total` holds whether the
     * default is "no cap" or "500". Restoring `config('limit', 500)` would leave
     * the whole suite green — a false green on the one line the ticket is about.
     */
    public function exposeConfiguredScanLimit(): ?int
    {
        return $this->configuredScanLimit();
    }

    /**
     * Expose the display-text coercion every stored row field goes through.
     */
    public function exposeDisplayString(mixed $value): string
    {
        return $this->displayString($value);
    }

    /**
     * Expose the per-entry validation so its merge, visibility filter, and
     * replacement context can be pinned without a full scan (UAMS-Web/uams-statamic-archive#2544).
     *
     * @return array{errors: MessageBag, attributes: array<string, string>}
     */
    public function exposeValidateEntry(StatamicEntry $entry, Blueprint $blueprint): array
    {
        return $this->validateEntry($entry, $blueprint);
    }

    /**
     * Expose the malformed-replicator coercion that runs ahead of preProcess (UAMS-Web/uams-statamic-archive#2544).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function exposeSanitizeMalformedReplicatorValues(Blueprint $blueprint, array $values): array
    {
        return $this->sanitizeMalformedReplicatorValues($blueprint, $values);
    }

    /**
     * Expose a single display-condition evaluation (UAMS-Web/uams-statamic-archive#2544).
     *
     * @param  array<string, mixed>  $values
     */
    public function exposeEvaluateCondition(string $fieldHandle, string $rhs, array $values): bool
    {
        return $this->evaluateCondition($fieldHandle, $rhs, $values);
    }

    /**
     * Expose the string form of a field handle (UAMS-Web/uams-statamic-archive#2544).
     */
    public function exposeFieldHandle(Field $field): string
    {
        return $this->fieldHandle($field);
    }

    /**
     * Expose a field's display-condition check (UAMS-Web/uams-statamic-archive#2544).
     *
     * @param  array<string, mixed>  $values
     */
    public function exposeFieldPassesConditions(Field $field, array $values): bool
    {
        return $this->fieldPassesConditions($field, $values);
    }

    /**
     * Expose the per-row visibility filter for replicator values (UAMS-Web/uams-statamic-archive#2544).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function exposeFilterReplicatorRowsToVisibleFields(array $rows, Field $field): array
    {
        return $this->filterReplicatorRowsToVisibleFields($rows, $field);
    }

    /**
     * Expose the entry values gathered for validation (UAMS-Web/uams-statamic-archive#2544).
     *
     * @return array<string, mixed>
     */
    public function exposeExtractEntryValuesForValidation(StatamicEntry $entry): array
    {
        return $this->extractEntryValuesForValidation($entry);
    }
}
