<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2855: the declined `infer-conventions` skill stays out of every harness skill tree.
 *
 * UAMS-Web/uams-statamic-archive#2319 declined `infer-conventions`. It is a Boost core skill that a plain `boost:update`
 * writes whether or not anyone asked for it, and this repository's rule corpus is written by
 * hand, with evidence. The decision is to run `boost:update` only as `--ignore-skills`, and this
 * test pins what that decision protects, so a plain run committed by mistake fails here rather
 * than landing quietly.
 *
 * The assertion used to live in `SkillMirrorsTest`, which UAMS-Web/uams-statamic-archive#2839 deleted along with the
 * `.ai/skills` mirror. Its other cases lost their subject with the mirror; this one did not,
 * because Boost writes a core skill whether or not `.ai/skills` exists. So the check needs no
 * source tree: it is an absence assertion over the directories that remain.
 *
 * The directories are where a skill reaches a harness: `.claude/skills` and `.cursor/skills`,
 * the `skillsPath()` of the two agents in `boost.json`, and `.agents/skills`, the tracked tree
 * other harnesses read. A directory that does not exist passes, so listing one costs nothing.
 *
 * Presence is `file_exists() || is_link()`: `file_exists()` follows a link, so a symlink to a
 * missing target reads as absent to it alone, and a tracked `120000` entry is exactly that on a
 * checkout where the target is gone.
 *
 * No `App\` class is under test, so there is nothing to target for mutation testing. The
 * fixture cases below are the control: they show the same function finding a planted
 * directory and a planted symlink, so a clean run on the repository is evidence of absence
 * rather than of a check that cannot see.
 *
 * @command  php artisan test --compact tests/Feature/Config/DeclinedBoostSkillsTest.php
 */

/**
 * The harness skill trees a declined skill must stay out of, relative to the checkout root.
 *
 * @return list<string>
 */
function declinedBoostSkillTrees(): array
{
    return ['.claude/skills', '.agents/skills', '.cursor/skills'];
}

/**
 * The paths under a root where the declined skill is present, as a directory, a file, or a link.
 *
 * @param  string  $root  The checkout to inspect.
 * @return list<string> The relative paths found, empty when the skill is absent everywhere.
 */
function declinedBoostSkillsPresent(string $root): array
{
    $present = [];

    foreach (declinedBoostSkillTrees() as $tree) {
        $path = "{$root}/{$tree}/infer-conventions";

        if (file_exists($path) || is_link($path)) {
            $present[] = "{$tree}/infer-conventions";
        }
    }

    return $present;
}

it('keeps the declined infer-conventions skill out of every harness skill tree (#2319)', function (): void {
    $present = declinedBoostSkillsPresent(base_path());

    expect($present)->toBe(
        [],
        implode(', ', $present).' present: delete it, and run boost:update only as boost:update --ignore-skills, as #2319 decided',
    );
});

describe('the presence check', function (): void {
    beforeEach(function (): void {
        $this->fixtureRoot = sys_get_temp_dir().'/declined-boost-skills-'.bin2hex(random_bytes(6));

        foreach (declinedBoostSkillTrees() as $tree) {
            File::ensureDirectoryExists("{$this->fixtureRoot}/{$tree}/pest-testing");
        }
    });

    afterEach(function (): void {
        File::deleteDirectory($this->fixtureRoot);
    });

    it('finds nothing in trees holding only other skills', function (): void {
        expect(declinedBoostSkillsPresent($this->fixtureRoot))->toBe([]);
    });

    it('finds a planted directory in each tree', function (string $tree): void {
        File::ensureDirectoryExists("{$this->fixtureRoot}/{$tree}/infer-conventions");

        expect(declinedBoostSkillsPresent($this->fixtureRoot))->toBe(["{$tree}/infer-conventions"]);
    })->with(declinedBoostSkillTrees());

    it('finds a symlink whose target is missing', function (): void {
        symlink("{$this->fixtureRoot}/nowhere", "{$this->fixtureRoot}/.claude/skills/infer-conventions");

        expect(declinedBoostSkillsPresent($this->fixtureRoot))->toBe(['.claude/skills/infer-conventions']);
    })->skipOnWindows();
});
