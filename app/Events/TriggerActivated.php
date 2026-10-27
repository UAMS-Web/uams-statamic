<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Statamic\Contracts\Entries\Entry;

/**
 * Event triggered when a trigger fieldtype action is activated in the Statamic
 * control panel.
 *
 * This event carries data for processing trigger actions, such as importing table
 * data from an asset. It supports both new and existing entries, with form values
 * provided for new entries where no entry ID exists.
 */
class TriggerActivated
{
    /**
     * Provides event dispatching functionality
     */
    use Dispatchable;

    /**
     * The entry associated with the trigger
     *
     * @var ?Entry The entry associated with the trigger, or null for new entries
     */
    public $entry;

    /**
     * The handle of the trigger field
     *
     * @var string The handle of the trigger field (e.g., 'table_data_importer_trigger')
     */
    public $triggerHandle;

    /**
     * The current form values from the control panel
     *
     * @var array<string, mixed> The current form values from the control panel
     */
    public $formValues;

    /**
     * Create a new TriggerActivated event instance.
     *
     * @param  ?Entry  $entry  The entry associated with the trigger, or null for new entries
     * @param  string  $triggerHandle  The handle of the trigger field
     * @param  array<string, mixed>  $formValues  The current form values from the control panel
     */
    public function __construct(
        ?Entry $entry,
        string $triggerHandle,
        array $formValues = []
    ) {
        // Assign the entry to the entry property
        $this->entry = $entry;

        // Assign the trigger handle to the triggerHandle property
        $this->triggerHandle = $triggerHandle;

        // Assign the form values to the formValues property
        $this->formValues = $formValues;
    }
}
