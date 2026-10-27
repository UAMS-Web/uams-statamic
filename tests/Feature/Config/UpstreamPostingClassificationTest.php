<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2772: a ticket whose work is posting to a third-party tracker is `afk`, with the post itself
 * approved by a human. The two skills that classify such tickets say so, so a later edit cannot
 * restore the old `hitl` classification without a failure.
 *
 * @command  php artisan test --compact tests/Feature/Config/UpstreamPostingClassificationTest.php
 */
$root = dirname(__DIR__, 3);

/**
 * Read a skill's `SKILL.md`, failing loudly when it cannot be read.
 *
 * @param  string  $root  The repository root.
 * @param  string  $skill  The skill's directory name under `.claude/skills/`.
 * @return string The skill's contents
 */
function upstreamPostingSkill(string $root, string $skill): string
{
    $contents = file_get_contents($root.'/.claude/skills/'.$skill.'/SKILL.md');

    if ($contents === false) {
        throw new RuntimeException(sprintf('The %s skill could not be read.', $skill));
    }

    return $contents;
}

it('lists the filing and pull request installments as afk with a human approving the post', function () use ($root): void {
    $skill = upstreamPostingSkill($root, 'upstream-patch-lifecycle');

    expect($skill)
        ->toContain('| 2 | File the bug upstream | `afk` (a human approves the post) | 1 |')
        ->toContain('| 3 | Open the upstream PR that closes that upstream issue | `afk` (a human approves the post) | 2 |')
        ->toContain('posts only after that approval');
});

it('no longer classifies an upstream installment as hitl', function () use ($root): void {
    $skill = upstreamPostingSkill($root, 'upstream-patch-lifecycle');

    // The chain table is the one place the skill assigns a mode to each installment
    preg_match_all('/^\| \d \| [^|]+ \| ([^|]+) \|/m', $skill, $modes);

    expect($modes[1])->toHaveCount(4);

    foreach ($modes[1] as $mode) {
        expect($mode)->toStartWith('`afk`');
    }
});

it('carves posting to a third-party tracker out of hitl in writing-issues', function () use ($root): void {
    // The synced skill wraps its prose (UAMS-Web/uams-statamic-archive#2833), so compare with whitespace collapsed (UAMS-Web/uams-statamic-archive#2846)
    $skill = (string) preg_replace('/\s+/', ' ', upstreamPostingSkill($root, 'writing-issues'));

    expect($skill)
        ->toContain('**Posting to a third-party tracker does not, on its own, make an issue `hitl`**')
        ->toContain('posting something an agent has prepared to a third-party tracker is not a real-world action in this sense');
});
