<?php

declare(strict_types=1);

namespace App\Support\Collections;

/**
 * The handle of the `people` collection, read from `people.collection` (UAMS-Web/uams-statamic-archive#3180).
 *
 * Site core code names the collection through here, so it does not depend on a literal the people
 * code will own once it moves into `uams-web/statamic-people` (#3181).
 */
final class PeopleCollection
{
    /**
     * The configured handle of the `people` collection.
     */
    public static function handle(): string
    {
        return config()->string('people.collection');
    }
}
