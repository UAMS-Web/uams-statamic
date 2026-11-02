<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Auth\User;
use Statamic\Facades\Form;
use Statamic\Facades\Stache;

/**
 * Count the built JavaScript files under a directory that register the routing fieldtype.
 *
 * @param  string  $directory  Public directory holding a built bundle.
 */
function formSettingsAddOnRoutingRegistrations(string $directory): int
{
    // Read every built script under the directory, or none when it was never built
    $files = File::isDirectory($directory) ? File::allFiles($directory) : [];

    return count(array_filter(
        $files,
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'js'
            && str_contains((string) file_get_contents($file->getPathname()), 'form_email_routing-fieldtype'),
    ));
}

/*
 * Proves the Control Panel form settings screen renders the `uams-web/aether-form-settings`
 * add-on's own fieldtypes (#263).
 *
 * The site's CP bundle used to register those fieldtypes itself. They now ship in the add-on's
 * bundle, so the site's `resources/js/cp.js` registers none of them. A feature test cannot see
 * whether a Vue component registered; this page can. The routing rules list and its Add Rule
 * button are drawn inside the add-on's routing component, not by Statamic's field wrapper, so
 * seeing the button proves a bundle registered that component.
 *
 * The site's CP bundle once registered a component of the same name with the same button, so a
 * slot whose `public/vendor/app` predates #263 would pass on the site's copy. The test first
 * reads the built files and requires the registration in the add-on's bundle and nowhere in the
 * site's, so the button can only have come from the add-on.
 *
 * @command  vendor/bin/pest tests/Browser/CpFormSettingsAddOnFieldtypesTest.php
 */

$handle = 'form_settings_addon_browser';

beforeEach(function () use ($handle) {
    $this->cpEmail = 'form-settings-'.uniqid().'@example.test';
    $this->cpPassword = 'form-settings-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    // A form whose one notification routes its TO address by rule, so the routing
    // component is the field shown for it
    /** @var Statamic\Forms\Form $form */
    $form = Form::make($handle);
    $form->title('Form settings add-on');
    $form->email([[
        'name' => 'Routed notice',
        'to_type' => 'routing',
        'to_routing' => [],
        'subject' => 'Routed',
    ]]);
    $form->save();

    Stache::refresh();
});

afterEach(function () use ($handle) {
    /** @var Statamic\Forms\Form|null $form */
    $form = Form::find($handle);
    $form?->delete();

    /** @var User|null $cpUser */
    $cpUser = $this->cpUser;
    $cpUser?->delete();
    Stache::refresh();
});

it('renders the add-on fieldtypes on the form settings screen', function () use ($handle) {
    // Only the add-on's bundle may register the routing fieldtype, or the button proves nothing
    expect(formSettingsAddOnRoutingRegistrations(public_path('vendor/aether-form-settings/build')))->toBeGreaterThan(0)
        ->and(formSettingsAddOnRoutingRegistrations(public_path('vendor/app')))->toBe(0);

    $page = loginToCp($this->cpEmail, $this->cpPassword);

    $page->navigate('/cp/forms/'.$handle.'/edit');

    // Wait for the settings screen itself, so a lost session cannot pass as a missing component
    waitToSee($page, 'Default Confirmation');

    // The routing rules list, with its add button, is drawn by the add-on's routing fieldtype
    waitToSee($page, 'Add Rule');

    assertNoAppJavaScriptErrors($page);
});
