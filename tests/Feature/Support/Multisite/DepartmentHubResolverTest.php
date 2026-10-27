<?php

declare(strict_types=1);

use App\Console\Commands\AuditDepartmentHubSites;
use App\Support\Multisite\DepartmentHubResolver;
use Illuminate\Testing\PendingCommand;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Fields\Field;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

use function Pest\Laravel\artisan;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

// Explicitly define the target class for this file
covers(DepartmentHubResolver::class);
covers(AuditDepartmentHubSites::class);

/**
 * Coverage for the site→`organizations` bridge (UAMS-Web/uams-statamic-archive#1556): the per-site
 * `department_organization` global field, the {@see DepartmentHubResolver} that
 * reads it, and the `schema_subject` population audit.
 *
 * The resolver cases are hermetic (Mockery over the `uams_multisite_settings`
 * global + sites), so they run without the `content/` submodule — mirroring
 * `ChildSitesResolverTest`'s defensive cases.
 *
 * @command  php artisan test --compact tests/Feature/Support/Multisite/DepartmentHubResolverTest.php
 */

/**
 * Mock the multisite global so `->in($handle)` returns a Variables mock whose
 * `department_organization` / `schema_subject` reads match the given per-site data.
 *
 * @param  array<string, array{department_organization?: mixed, schema_subject?: mixed}>  $sites
 */
function mockMultisiteGlobal(array $sites): void
{
    $set = Mockery::mock(GlobalSetContract::class);

    foreach ($sites as $handle => $data) {
        $variables = Mockery::mock(Variables::class);
        $variables->shouldReceive('get')->with('department_organization')->andReturn($data['department_organization'] ?? null);
        $variables->shouldReceive('get')->with('schema_subject')->andReturn($data['schema_subject'] ?? null);
        $set->shouldReceive('in')->with($handle)->andReturn($variables);
    }

    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);
}

/** Build a Site mock exposing the given handle. */
function mockSite(string $handle): SiteObject
{
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn($handle);

    return $site;
}

// -----------------------------------------------------------------------------
// Global blueprint field
// -----------------------------------------------------------------------------

it('exposes a single-value department_organization field scoped to department-unit roles', function () {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::find('globals.uams_multisite_settings');
    expect($bp)->not->toBeNull();

    /** @var ?Field $field */
    $field = $bp->fields()->all()->get('department_organization');
    expect($field)->not->toBeNull();

    /** @var Field $field */
    expect($field->type())->toBe('entries');

    $config = $field->config();
    expect($config['collections'] ?? null)->toBe(['organizations']);
    expect($config['max_items'] ?? null)->toBe(1);
    expect($config['query_scopes'] ?? [])->toContain('entries_by_organization_role_department_unit');
    // Localizable so each site sets its own hub organization.
    expect($config['localizable'] ?? null)->toBeTrue();
});

// -----------------------------------------------------------------------------
// Resolver: organizationId / organization / isHubSite
// -----------------------------------------------------------------------------

it('resolves a hub site to its stored organization id', function () {
    mockMultisiteGlobal([
        'medicine_anesthesiology' => ['department_organization' => ['org-anesthesiology']],
    ]);

    expect((new DepartmentHubResolver)->organizationId('medicine_anesthesiology'))
        ->toBe('org-anesthesiology');
});

it('returns null for a site with no department_organization (missing-link fallback)', function () {
    mockMultisiteGlobal([
        'plain_site' => ['department_organization' => []],
    ]);

    $resolver = new DepartmentHubResolver;
    expect($resolver->organizationId('plain_site'))->toBeNull();
    expect($resolver->isHubSite('plain_site'))->toBeFalse();
});

it('returns null when the multisite global set is missing', function () {
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturnNull();

    expect((new DepartmentHubResolver)->organizationId('medicine_anesthesiology'))->toBeNull();
});

it('resolves the organizations entry for a hub site', function () {
    mockMultisiteGlobal([
        'medicine_anesthesiology' => ['department_organization' => ['org-anesthesiology']],
    ]);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('organizations');
    EntryFacade::shouldReceive('find')->with('org-anesthesiology')->andReturn($entry);

    $resolver = new DepartmentHubResolver;
    expect($resolver->organization('medicine_anesthesiology'))->toBe($entry);
    expect($resolver->isHubSite('medicine_anesthesiology'))->toBeTrue();
});

it('returns null when the stored id points at a non-organizations entry', function () {
    mockMultisiteGlobal([
        'medicine_anesthesiology' => ['department_organization' => ['some-page']],
    ]);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('pages');
    EntryFacade::shouldReceive('find')->with('some-page')->andReturn($entry);

    expect((new DepartmentHubResolver)->organization('medicine_anesthesiology'))->toBeNull();
});

it('returns null when the stored organization id no longer resolves', function () {
    mockMultisiteGlobal([
        'medicine_anesthesiology' => ['department_organization' => ['org-deleted']],
    ]);

    EntryFacade::shouldReceive('find')->with('org-deleted')->andReturnNull();

    expect((new DepartmentHubResolver)->organization('medicine_anesthesiology'))->toBeNull();
});

// -----------------------------------------------------------------------------
// Resolver: schema_subject audit
// -----------------------------------------------------------------------------

it('flags only hub sites missing a schema_subject', function () {
    mockMultisiteGlobal([
        // Hub, schema-complete — not flagged.
        'complete_hub' => ['department_organization' => ['org-a'], 'schema_subject' => ['schema-a']],
        // Hub, no schema_subject — flagged.
        'broken_hub' => ['department_organization' => ['org-b'], 'schema_subject' => []],
        // Not a hub — not audited even though schema_subject is empty.
        'plain_site' => ['department_organization' => [], 'schema_subject' => []],
    ]);

    Site::shouldReceive('all')->andReturn(collect([
        mockSite('complete_hub'),
        mockSite('broken_hub'),
        mockSite('plain_site'),
    ]));

    expect((new DepartmentHubResolver)->hubSitesMissingSchemaSubject())->toBe(['broken_hub']);
});

// -----------------------------------------------------------------------------
// Audit command
// -----------------------------------------------------------------------------

/**
 * Bind a resolver stub returning the given missing-hub list, then run the audit
 * command and return its PendingCommand for fluent assertions (narrowing the
 * PendingCommand|int union output mocking guarantees).
 *
 * @param  array<int, string>  $missing
 */
function runHubAudit(array $missing): PendingCommand
{
    $resolver = Mockery::mock(DepartmentHubResolver::class);
    $resolver->shouldReceive('hubSitesMissingSchemaSubject')->andReturn($missing);
    app()->instance(DepartmentHubResolver::class, $resolver);

    $pending = artisan('multisite:audit-department-hubs');
    Assert::assertInstanceOf(PendingCommand::class, $pending);

    return $pending;
}

it('audit command succeeds when every hub site is schema-complete', function () {
    runHubAudit([])
        ->expectsOutputToContain('All Departmental News Hub sites have a populated schema_subject.')
        ->assertExitCode(0);
});

it('audit command fails and lists hub sites missing a schema_subject', function () {
    runHubAudit(['broken_hub'])
        ->expectsOutputToContain(
            '1 Departmental News Hub site(s) are missing a schema_subject and would emit a broken entity node:'
        )
        ->expectsOutputToContain('  - broken_hub')
        ->assertExitCode(1);
});
