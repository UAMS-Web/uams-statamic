<?php

declare(strict_types=1);

use App\Support\People\CertificationActivityValidity;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Professional memberships on the clinical provider lens (UAMS-Web/uams-statamic#307): the
 * Credentials section lists `member_of` after the certifications, with each level in words,
 * so a fellowship reads below the name rather than as letters after it. A provider with no
 * memberships shows no membership heading, and the certifications list is unchanged.
 *
 * Rendered as the lens partial with a synthetic scope, the rows augmented through the people
 * blueprint's own `member_of` grid and `certifications` replicator, and the organizations and
 * certifications are real terms in content.
 *
 * @command  php artisan test --compact tests/Feature/Views/ProviderLensMembershipsTest.php
 */

/**
 * A field on the people blueprint.
 */
function providerLensMembershipsField(string $handle): Field
{
    return mustBe(
        Field::class,
        mustBe(Statamic\Fields\Blueprint::class, Blueprint::find('collections.people.person'), 'the person blueprint')->field($handle),
        'the '.$handle.' field'
    );
}

/**
 * Render the provider lens body for a person with the given memberships and certifications.
 *
 * @param  list<array<string, mixed>>  $memberships
 * @param  list<array<string, mixed>>  $certifications
 */
function providerLensMembershipsRender(array $memberships, array $certifications = []): string
{
    $scope = [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'member_of' => new Value($memberships, 'member_of', providerLensMembershipsField('member_of')->fieldtype()),
        'certifications' => providerLensMembershipsCertifications($certifications),
    ];

    return (string) Antlers::parse('{{ partial:people/provider/profile }}', $scope);
}

/**
 * Certifications rows augmented the way a people entry hands them to a view.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function providerLensMembershipsCertifications(array $rows): Value
{
    $merged = (new CertificationActivityValidity)->mergeIntoCertificationsRows($rows);

    return new Value($merged, 'certifications', providerLensMembershipsField('certifications')->fieldtype());
}

/**
 * One board certification, as stored.
 *
 * @return list<array<string, mixed>>
 */
function providerLensMembershipsBoardCertification(): array
{
    return [[
        'id' => 'board',
        'type' => 'board_certification',
        'enabled' => true,
        'activity' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery',
        'activity_date_issued' => '1994-07-15',
    ]];
}

// Widont joins the last two words of a heading with &nbsp;, so the headings are matched as markup
const PROVIDER_LENS_MEMBERSHIPS_HEADING = '>Professional&nbsp;Memberships</h3>';
const PROVIDER_LENS_CERTIFICATIONS_HEADING = '>Certifications</h3>';
const PROVIDER_LENS_FELLOW = 'content="Fellow of the American Academy of Orthopaedic Surgeons"';
const PROVIDER_LENS_BOARD = 'content="Board Certification in Orthopaedic Surgery"';

/**
 * One fellowship of the American Academy of Orthopaedic Surgeons, as stored.
 *
 * @return list<array<string, mixed>>
 */
function providerLensMembershipsFellowship(): array
{
    return [['id' => 'fellowship', 'organization' => 'american-academy-of-orthopaedic-surgeons', 'membership_level' => 'fellow']];
}

it('lists a fellowship in words in the Credentials section', function (): void {
    $html = providerLensMembershipsRender(providerLensMembershipsFellowship());

    expect($html)->toContain('id="provider-credentials"')
        ->toContain(PROVIDER_LENS_MEMBERSHIPS_HEADING)
        ->toContain(PROVIDER_LENS_FELLOW)
        ->not->toContain(PROVIDER_LENS_CERTIFICATIONS_HEADING);
});

it('lists the memberships after the certifications, inside the same section', function (): void {
    $html = providerLensMembershipsRender(providerLensMembershipsFellowship(), providerLensMembershipsBoardCertification());

    // One Credentials section, holding the certifications and then the memberships
    expect(substr_count($html, 'id="provider-credentials"'))->toBe(1)
        ->and(positionOf($html, 'id="provider-credentials"'))->toBeLessThan(positionOf($html, PROVIDER_LENS_BOARD))
        ->and(positionOf($html, PROVIDER_LENS_BOARD))->toBeLessThan(positionOf($html, PROVIDER_LENS_MEMBERSHIPS_HEADING))
        ->and(positionOf($html, PROVIDER_LENS_MEMBERSHIPS_HEADING))->toBeLessThan(positionOf($html, PROVIDER_LENS_FELLOW));
});

it('shows no membership heading or list, and no Credentials section, for a provider with neither', function (): void {
    $html = providerLensMembershipsRender([]);

    expect($html)->not->toContain('Professional&nbsp;Memberships')
        ->not->toContain('id="provider-credentials"');
});

it('keeps the certifications list, with no membership heading, for a provider with no memberships', function (): void {
    $html = providerLensMembershipsRender([], providerLensMembershipsBoardCertification());

    expect($html)->toContain('id="provider-credentials"')
        ->toContain(PROVIDER_LENS_CERTIFICATIONS_HEADING)
        ->toContain(PROVIDER_LENS_BOARD)
        ->not->toContain('Professional&nbsp;Memberships');
});
