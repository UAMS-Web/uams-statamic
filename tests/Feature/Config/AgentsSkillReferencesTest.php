<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2708: every skill `AGENTS.md` tells an agent to activate or read is installed in this
 * repository.
 *
 * `laravel/boost` 2.10.0's guideline templates name two skills, `deploying-to-cloud` and
 * `testing-best-practices`, that ship inside the package but that `boost.json` does not
 * install. `boost:update --ignore-skills` writes no skill directories, so the generated
 * block pointed every agent at a skill it could not load. The two overrides under
 * `.ai/guidelines/` drop those lines; this test catches the next template that names an
 * uninstalled skill, and an override that stops applying.
 *
 * A reference is a backticked skill name followed by the word `skill`, which is how both
 * the generated block and the hand-written sections name one.
 *
 * @command  php artisan test --compact tests/Feature/Config/AgentsSkillReferencesTest.php
 */
$root = dirname(__DIR__, 3);

/**
 * The skill names a text refers to as "`name` skill".
 *
 * @param  string  $text  The text to scan.
 * @return list<string>
 */
function agentsSkillReferences(string $text): array
{
    preg_match_all('/`([a-z0-9][a-z0-9-]*)` skill\b/', $text, $matches);

    return array_values(array_unique($matches[1]));
}

it('finds a skill reference in the form the guidelines use', function (): void {
    // Positive control: an empty result below must mean no references, not a blind pattern
    $sample = "- Activate the `deploying-to-cloud` skill whenever deploying.\n- Read the `testing-best-practices` skill before writing tests.";

    expect(agentsSkillReferences($sample))->toBe(['deploying-to-cloud', 'testing-best-practices']);
});

it('names only skills that are installed', function () use ($root): void {
    $agents = file_get_contents($root.'/AGENTS.md');

    expect($agents)->toBeString();

    $absent = array_values(array_filter(
        agentsSkillReferences((string) $agents),
        static fn (string $skill): bool => ! is_file($root.'/.claude/skills/'.$skill.'/SKILL.md'),
    ));

    expect($absent)->toBe([]);
});
