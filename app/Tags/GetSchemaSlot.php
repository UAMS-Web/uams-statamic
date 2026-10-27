<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaOutputService;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Returns the schema_objects entry @id reference for a slot (subject, website,
 * webpage, main_content) for the current webpage context.
 *
 * Returns a string (#entry-id) so Antlers can render it without "Cannot render
 * an array variable" when the value is used as schema output (e.g. about,
 * hasPart). Tag handle is get_schema_slot to avoid being shadowed by context.
 *
 * Usage:
 *   {{ get_schema_slot slot="subject" }}
 *   {{ get_schema_slot:subject }}
 *   {{ get_schema_slot slot="main_content" full_id="true" }}
 *
 * Output is "#" + entry id (e.g. "#uuid") for JSON-LD @id references.
 * With full_id="true", returns the canonical full @id (e.g. "/#uuid") for
 * schema_objects entries so you can set microdata itemid to match the JSON-LD @id.
 */
class GetSchemaSlot extends Tags
{
    /**
     * Handle for the tag.
     *
     * @var string
     */
    protected static $handle = 'get_schema_slot';

    /**
     * Return the @id reference for the requested slot's entry.
     *
     * @return string|null "#entry-id" or null
     */
    public function index(): ?string
    {
        // Get the slot from the parameters
        /** @var Parameters $params */
        $params = $this->params;
        $slot = $params['slot'] ?? null;

        // Check if the slot is not a string or is empty
        if (
            ! \is_string($slot)
            || $slot === ''
        ) {
            // Free local variables
            unset(
                $slot
            );

            // Return null
            return null;
        }

        return $this->slotReference($slot, $params['full_id'] ?? null);
    }

    /**
     * Return the @id reference for the subject slot.
     *
     * @return string|null "#entry-id" or null
     */
    public function subject(): ?string
    {
        /** @var Parameters $params */
        $params = $this->params;

        return $this->slotReference('subject', $params['full_id'] ?? null);
    }

    /**
     * Return the @id reference for the website slot.
     *
     * @return string|null "#entry-id" or full @id when full_id="true"
     */
    public function website(): ?string
    {
        /** @var Parameters $params */
        $params = $this->params;

        return $this->slotReference('website', $params['full_id'] ?? null);
    }

    /**
     * Return the @id reference for the webpage slot.
     *
     * @return string|null "#entry-id" or full @id when full_id="true"
     */
    public function webpage(): ?string
    {
        /** @var Parameters $params */
        $params = $this->params;

        return $this->slotReference('webpage', $params['full_id'] ?? null);
    }

    /**
     * Return the @id reference for the main_content slot.
     *
     * @return string|null "#entry-id" or full @id when full_id="true"
     */
    public function mainContent(): ?string
    {
        /** @var Parameters $params */
        $params = $this->params;

        return $this->slotReference('main_content', $params['full_id'] ?? null);
    }

    /**
     * Resolve slot entry and return its @id reference string.
     *
     * @param  string  $slot  The slot for which to get the entry @id reference
     * @param  mixed  $fullId  If truthy, return canonical full @id for schema_objects (for itemid)
     * @return string|null "#entry-id", or full @id (e.g. "/#uuid"), or null
     */
    private function slotReference(
        string $slot,
        mixed $fullId = null
    ): ?string {
        // Get the slot entry for the current context
        $entry = app(SchemaOutputService::class)->getSlotEntryForCurrentContext($slot);

        // Check if the entry is not found
        if (! $entry instanceof EntryModel) {
            // Free local variables
            unset(
                $entry
            );

            // Return null
            return null;
        }

        // Return canonical full @id when requested and entry is from schema_objects (for microdata itemid)
        if ($fullId && $entry->collectionHandle() === 'schema_objects') {
            $atId = app(SchemaOutputService::class)->getCanonicalAtIdForSchemaEntry($entry);
            unset($entry);

            return $atId !== '' ? $atId : null;
        }

        $id = $entry->id();

        // Free local variables
        unset(
            $entry
        );

        // Return the @id reference for the entry
        return \is_string($id) && $id !== '' ? '#'.$id : null;
    }
}
