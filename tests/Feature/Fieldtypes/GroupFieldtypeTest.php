<?php

declare(strict_types=1);

use Statamic\Fieldtypes\Group;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The `group` handle is served by Statamic's own Group fieldtype. The
 * application override that passed the entry's validation replacements to a
 * group's fields was retired once the installed Statamic release applied them
 * itself (statamic/cms#15513, UAMS-Web/uams-statamic#736). Whether those
 * replacements still reach a grouped uniqueness rule is guarded by
 * `tests/Feature/Rules/UniqueIdentifierGroupedSelfExclusionTest.php`.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/GroupFieldtypeTest.php
 */
it('registers Statamic\'s own Group fieldtype under the group handle', function (): void {
    expect(app('statamic.fieldtypes')->get('group'))->toBe(Group::class)
        ->and(class_exists('App\\Fieldtypes\\Group'))->toBeFalse();
});
