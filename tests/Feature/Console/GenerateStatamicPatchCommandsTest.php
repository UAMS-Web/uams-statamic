<?php

declare(strict_types=1);

use App\Console\Commands\GenerateStatamicAddonChangelogNullLicensePatch;
use App\Console\Commands\GenerateStatamicCollectionCreateLabelPatch;
use App\Console\Commands\GenerateStatamicStacheBasicVanishedFileGetItemPatch;
use App\Console\Commands\GenerateStatamicStacheStoreVanishedFileModifiedPatch;
use App\Console\Commands\GenerateStatamicStacheStoreVanishedFilePathsPatch;
use App\Console\Commands\GenerateStatamicStacheTermsVanishedFileGetItemPatch;
use App\Console\Commands\GenerateStatamicStacheTermsVanishedFileModifiedPatch;
use App\Console\Commands\GenerateStatamicStacheTermsVanishedFilePathsPatch;
use App\Console\Commands\GenerateStatamicStacheTraverserVanishedFilePatch;
use App\Console\Commands\GenerateStatamicTaxonomyCreateLabelPatch;
use App\Console\Commands\GenerateStatamicUpdatesOverviewBadgePatch;
use App\Services\GenerateStatamicPatch\StatamicPatchGenerator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Testing\PendingCommand;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

use function Pest\Laravel\artisan;

/**
 * Feature tests for the Artisan commands that regenerate a Statamic vendor patch.
 *
 * Each command is thin wiring around {@see StatamicPatchGenerator}: it passes its
 * own basename, target path and (where it has one) method name, then reports the
 * written path or the generator's error. The generator is mocked, so these tests
 * pin the values each command hands over and the output and exit code on either
 * side of the call, without writing a patch file.
 *
 * The two create-label commands pass no method name and rely on the generator's
 * `createLabel` default, so their expectation carries two arguments.
 *
 * @command  php artisan test --compact tests/Feature/Console/GenerateStatamicPatchCommandsTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(
    GenerateStatamicAddonChangelogNullLicensePatch::class,
    GenerateStatamicCollectionCreateLabelPatch::class,
    GenerateStatamicStacheBasicVanishedFileGetItemPatch::class,
    GenerateStatamicStacheStoreVanishedFileModifiedPatch::class,
    GenerateStatamicStacheStoreVanishedFilePathsPatch::class,
    GenerateStatamicStacheTermsVanishedFileGetItemPatch::class,
    GenerateStatamicStacheTermsVanishedFileModifiedPatch::class,
    GenerateStatamicStacheTermsVanishedFilePathsPatch::class,
    GenerateStatamicStacheTraverserVanishedFilePatch::class,
    GenerateStatamicTaxonomyCreateLabelPatch::class,
    GenerateStatamicUpdatesOverviewBadgePatch::class,
);

/**
 * Run an Artisan command and return its PendingCommand for fluent assertions.
 *
 * Pest\Laravel\artisan() is typed PendingCommand|int; with console-output
 * mocking on (the default) it always returns a PendingCommand.
 */
function statamicPatchCommandRun(string $class, string $signature): PendingCommand
{
    // Build the command now, after the generator is mocked, and register it over any
    // instance the console application already holds. Without this the first test in a
    // process can reach a command built before the mock and run the real generator,
    // which writes a patch file into the checkout.
    $command = app()->make($class);

    // Narrow the resolved instance so the registry receives a console command.
    Assert::assertInstanceOf(Command::class, $command);

    app(Kernel::class)->registerCommand($command);

    $pending = artisan($signature);

    // Narrow the PendingCommand|int union (output mocking guarantees a PendingCommand).
    Assert::assertInstanceOf(PendingCommand::class, $pending);

    return $pending;
}

/**
 * Each command's signature and the exact arguments it passes to the generator.
 *
 * @return array<string, array{0: class-string<Command>, 1: string, 2: string, 3: list<string>}>
 */
function statamicPatchCommandCases(): array
{
    return [
        'addon changelog null license' => [
            GenerateStatamicAddonChangelogNullLicensePatch::class,
            'app:generate-statamic-addon-changelog-null-license-patch',
            'statamic-addon-changelog-null-license',
            ['statamic-addon-changelog-null-license', 'src/Updater/AddonChangelog.php', 'isLicensed'],
        ],
        'collection create label' => [
            GenerateStatamicCollectionCreateLabelPatch::class,
            'app:generate-statamic-collection-create-label-patch',
            'statamic-collection-create-label-blueprint',
            ['statamic-collection-create-label-blueprint', 'src/Entries/Collection.php'],
        ],
        'stache basic vanished file get item' => [
            GenerateStatamicStacheBasicVanishedFileGetItemPatch::class,
            'app:generate-statamic-stache-basic-vanished-file-get-item-patch',
            'statamic-stache-basic-vanished-file-get-item',
            ['statamic-stache-basic-vanished-file-get-item', 'src/Stache/Stores/BasicStore.php', 'getItem'],
        ],
        'stache store vanished file modified' => [
            GenerateStatamicStacheStoreVanishedFileModifiedPatch::class,
            'app:generate-statamic-stache-store-vanished-file-modified-patch',
            'statamic-stache-store-vanished-file-modified',
            ['statamic-stache-store-vanished-file-modified', 'src/Stache/Stores/Store.php', 'getItemFromModifiedPath'],
        ],
        'stache store vanished file paths' => [
            GenerateStatamicStacheStoreVanishedFilePathsPatch::class,
            'app:generate-statamic-stache-store-vanished-file-paths-patch',
            'statamic-stache-store-vanished-file-paths',
            ['statamic-stache-store-vanished-file-paths', 'src/Stache/Stores/Store.php', 'paths'],
        ],
        'stache terms vanished file get item' => [
            GenerateStatamicStacheTermsVanishedFileGetItemPatch::class,
            'app:generate-statamic-stache-terms-vanished-file-get-item-patch',
            'statamic-stache-terms-vanished-file-get-item',
            ['statamic-stache-terms-vanished-file-get-item', 'src/Stache/Stores/TaxonomyTermsStore.php', 'getItem'],
        ],
        'stache terms vanished file modified' => [
            GenerateStatamicStacheTermsVanishedFileModifiedPatch::class,
            'app:generate-statamic-stache-terms-vanished-file-modified-patch',
            'statamic-stache-terms-vanished-file-modified',
            ['statamic-stache-terms-vanished-file-modified', 'src/Stache/Stores/TaxonomyTermsStore.php', 'getItemFromModifiedPath'],
        ],
        'stache terms vanished file paths' => [
            GenerateStatamicStacheTermsVanishedFilePathsPatch::class,
            'app:generate-statamic-stache-terms-vanished-file-paths-patch',
            'statamic-stache-terms-vanished-file-paths',
            ['statamic-stache-terms-vanished-file-paths', 'src/Stache/Stores/TaxonomyTermsStore.php', 'paths'],
        ],
        'stache traverser vanished file' => [
            GenerateStatamicStacheTraverserVanishedFilePatch::class,
            'app:generate-statamic-stache-traverser-vanished-file-patch',
            'statamic-stache-traverser-vanished-file',
            ['statamic-stache-traverser-vanished-file', 'src/Stache/Traverser.php', 'traverse'],
        ],
        'taxonomy create label' => [
            GenerateStatamicTaxonomyCreateLabelPatch::class,
            'app:generate-statamic-taxonomy-create-label-patch',
            'statamic-taxonomy-create-label-blueprint',
            ['statamic-taxonomy-create-label-blueprint', 'src/Taxonomies/Taxonomy.php'],
        ],
        'updates overview badge' => [
            GenerateStatamicUpdatesOverviewBadgePatch::class,
            'app:generate-statamic-updates-overview-badge-patch',
            'statamic-updates-overview-badge',
            ['statamic-updates-overview-badge', 'src/Updater/UpdatesOverview.php', 'checkForAddonUpdates'],
        ],
    ];
}

it('passes its patch values to the generator and reports the written path', function (string $class, string $signature, string $basename, array $arguments): void {
    // Expect exactly one call with this command's values, answered with a fake path
    $this->mock(StatamicPatchGenerator::class, function (MockInterface $mock) use ($arguments, $basename): void {
        $mock->shouldReceive('generatePatchFromValues')
            ->once()
            ->withArgs($arguments)
            ->andReturn('/tmp/'.$basename.'.patch');
    });

    // The written path is reported, followed by the relock reminder, and the run succeeds
    statamicPatchCommandRun($class, $signature)
        ->expectsOutput('Generated /tmp/'.$basename.'.patch')
        ->expectsOutput('Run: composer patches-relock --no-interaction')
        ->doesntExpectOutputToContain('generator failed')
        ->assertExitCode(0);
})->with(statamicPatchCommandCases());

it('reports the generator error and fails without the relock reminder', function (string $class, string $signature, string $basename, array $arguments): void {
    // The generator refuses, as it does for a missing snippet or an unchanged method
    $this->mock(StatamicPatchGenerator::class, function (MockInterface $mock) use ($arguments, $basename): void {
        $mock->shouldReceive('generatePatchFromValues')
            ->once()
            ->withArgs($arguments)
            ->andThrow(new RuntimeException('generator failed for '.$basename));
    });

    // The error text is surfaced, nothing claims success, and the exit code is 1
    statamicPatchCommandRun($class, $signature)
        ->expectsOutput('generator failed for '.$basename)
        ->doesntExpectOutputToContain('Generated ')
        ->doesntExpectOutputToContain('patches-relock')
        ->assertExitCode(1);
})->with(statamicPatchCommandCases());
