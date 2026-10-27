<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\Collections;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Statamic\Auth\Eloquent\User as AuthEloquentUser;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\User as UserFacade;
use Statamic\Http\Controllers\CP\Collections\LocalizeEntryController as BaseLocalizeEntryController;

/**
 * Controller for handling localization requests for collection entries in the
 * control panel.
 *
 * This controller extends the default Statamic `LocalizeEntryController` to
 * customize the localization process for entries. It ensures proper
 * authorization, validates the request, and creates a localized version of the
 * entry in the specified site. It also enhances error handling by providing
 * user-friendly toast notifications and logs the outcome of the localization
 * process.
 */
class LocalizeEntryController extends BaseLocalizeEntryController
{
    /**
     * Handle a request to localize a collection entry to a specified site.
     *
     * This method authorizes the request, validates the target site, and attempts to
     * create a localized version of the entry. On success, it logs the operation and
     * returns a JSON response with the localized entry's details. On failure, it logs
     * the error, displays a toast notification, and returns an error response.
     *
     * @param  Request  $request  The HTTP request containing the target site
     * @param  Collection  $collection  The collection the entry belongs to
     * @param  Entry  $entry  The entry to localize
     * @return JsonResponse A JSON response indicating success or failure
     *
     * @throws AuthorizationException If the user is not authorized to edit the entry
     * @throws ValidationException If the request validation fails
     */
    #[\Override]
    public function __invoke(
        Request $request,
        $collection,
        $entry
    ): JsonResponse {
        // Authorize the user to edit the entry, throwing an exception if unauthorized
        $this->authorize(
            'edit',
            $entry
        );

        // ---
        // Validate that 'site' is present and is one of the collection's
        // configured sites, rejecting arbitrary or unconfigured handles before
        // they reach localization creation
        // ---
        // ---
        // Narrow the fluently-returned sites value to the Laravel collection it
        // actually is so the configured site handles can be listed as an array
        // ---
        /** @var Collection $collection */
        /** @var \Illuminate\Support\Collection<int, string> $collectionSites */
        $collectionSites = $collection->sites();

        $sites = $collectionSites->all();

        $request->validate([
            'site' => [
                'required',
                Rule::in($sites),
            ],
        ]);

        // Attempt to create a localized version of the entry for the specified site
        try {
            // Create a localized version of the entry for the specified site
            /** @var Entry $localized */
            $localized = $entry->makeLocalization($site = $request->site);

            // ---
            // Set the updated_by and updated_at fields to track the user and timestamp of
            // the localization
            // ---

            // ---
            // Get the authenticated user from the request to track who created the
            // localization
            // ---
            /** @var AuthEloquentUser $user */
            $user = UserFacade::fromUser($request->user());

            // Apply the updated_by and updated_at values to the localized entry
            /** @var Entry $localized */
            $localized = $localized->set(
                'updated_by',
                $user->id()
            );
            $localized->set(
                'updated_at',
                now()->timestamp
            );

            // ---
            // Save the localized entry, triggering any custom save logic
            // (e.g., in KnowledgeBaseEntry)
            // ---
            $localized->save();

            // ---
            // Prepare the success response with the site handle and edit URL of the
            // localized entry
            // ---
            $result = [
                'success' => true,
                'handle' => $site,
                'url' => $localized->editUrl(),
            ];

            // Return a 200 JSON response with the success result
            return response()->json(
                $result,
                200
            );
        }

        // Catch any exceptions that occur during the localization process
        catch (Exception $exception) {
            // Log the exception for debugging and monitoring
            Log::error(
                'LocalizeEntryController::__invoke: Exception caught',
                [
                    'entry_id' => $entry->id(),
                    'message' => $exception->getMessage(),
                ]
            );

            // Display a toast notification to inform the user of the error
            Toast::error($exception->getMessage());

            // Return a 200 JSON response with failure details and toast data for the frontend
            return response()->json(
                [
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'toast' => [
                        'message' => $exception->getMessage(),
                        'type' => 'error',
                    ],
                ],
                200
            );
        }
    }
}
