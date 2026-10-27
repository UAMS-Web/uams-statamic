<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Form as FormFacade;
use Statamic\Facades\Site;
use Statamic\Forms\Form;
use Tests\Support\FormsSubmodule;
use Tests\TestCase;
use UAMSWeb\MultisiteForms\Forms\Assignment;

uses(TestCase::class);

/**
 * Each form in the private `resources/forms` submodule renders on the sites its
 * `sites` list names and follows the add-on's `not_assigned` behavior elsewhere
 * (UAMS-Web/uams-statamic-archive#3069).
 *
 * The expectation is read from each form's own list rather than written here, so
 * an editor reassigning a form in the Control Panel does not turn this red; what
 * it checks is that the stored list is one the add-on honors. A form with no
 * list is global and is expected to render on every site.
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormSiteAssignmentTest.php
 */

/**
 * Render a form's `{{ form:create }}` tag on a site.
 *
 * @param  string  $handle  The form to render.
 * @param  string  $site  The handle of the site to render it on.
 * @return string The rendered markup, empty when the form is not offered there.
 */
function formSiteAssignmentRender(string $handle, string $site): string
{
    // Render as a page on the site would
    Site::setCurrent($site);

    return trim((string) Antlers::parse('{{ form:create in="'.$handle.'" }}<span>offered</span>{{ /form:create }}', [], true));
}

it('offers each form on the sites it is assigned to and nowhere else', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    // Read every stored form, which is what the submodule holds
    $forms = FormFacade::all();
    expect($forms)->not->toBeEmpty();

    foreach ($forms as $form) {
        $form = mustBe(Form::class, $form, 'form');
        $handle = mustBeString($form->handle(), 'form handle');
        $sites = $form->get(Assignment::KEY);

        // A form with no list is global, so it is offered on the default site
        if (! is_array($sites) || $sites === []) {
            expect(formSiteAssignmentRender($handle, 'default'))->toContain('offered');

            continue;
        }

        // Offered on the first site the list names
        $assigned = mustBeString($sites[0], 'assigned site');
        expect(formSiteAssignmentRender($handle, $assigned))->toContain('offered');

        // Not offered on a configured site outside the list
        $unassigned = mustBe(Collection::class, Site::all(), 'sites')->keys()->first(fn (string $site): bool => ! in_array($site, $sites, true));
        expect($unassigned)->toBeString()
            ->and(formSiteAssignmentRender($handle, mustBeString($unassigned, 'unassigned site')))->toBe('');
    }
});
