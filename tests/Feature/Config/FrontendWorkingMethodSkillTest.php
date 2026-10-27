<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2710: the `tailwindcss-development` skill's "Working to the Existing Design" section keeps
 * front-end work matched to the existing UAMS design.
 *
 * The skill is read from `.claude/skills/`, the copy the shared sync from
 * `UAMS-Web/uams-claude-skills` writes (UAMS-Web/uams-statamic-archive#2839). The contrast rule may name the skill as a
 * plain code span or as a link to it; either form passes.
 *
 * The section harvests a working method from Anthropic's `frontend-design` skill, whose brief is
 * a distinctive visual identity. Two ways it could come apart are pinned here:
 *
 * - A later edit reintroduces an instruction to make the design distinctive, novel or
 *   unmistakable, which pushes parity tickets toward new visual choices.
 * - The contrast rule stops pointing at the `wcag-contrast` skill, or that skill goes away, so
 *   the quality floor loses the instrument it relies on.
 *
 * @command  php artisan test --compact tests/Feature/Config/FrontendWorkingMethodSkillTest.php
 */
$root = dirname(__DIR__, 3);

/**
 * The "Working to the Existing Design" section of a skill file, up to the next second-level
 * heading.
 *
 * @param  string  $skill  The skill file's contents.
 */
function frontendWorkingMethodSection(string $skill): string
{
    if (preg_match('/^## Working to the Existing Design\n(.*?)(?=^## )/ms', $skill, $match) !== 1) {
        throw new RuntimeException('No "Working to the Existing Design" section found.');
    }

    return $match[1];
}

/**
 * The lines of a text that ask for a distinctive or novel design.
 *
 * A line that states the design is "never" one of these is a guard, not a request, and is
 * left out.
 *
 * @param  string  $text  The text to scan.
 * @return list<string>
 */
function frontendNoveltyRequests(string $text): array
{
    return array_values(array_filter(
        explode("\n", $text),
        static fn (string $line): bool => preg_match('/\b(distinctive|novel|unique|unmistakable|point of view|aesthetic risk)\b/i', $line) === 1
            && preg_match('/\bnever\b/i', $line) !== 1,
    ));
}

it('detects a request for a distinctive design', function (): void {
    // Positive control: an empty result below must mean no requests, not a blind pattern
    $sample = "- Make deliberate choices that give the page a distinctive point of view.\n- It is never a new visual identity.";

    expect(frontendNoveltyRequests($sample))->toHaveCount(1);
});

it('asks for no distinctive or novel design', function () use ($root): void {
    $skill = file_get_contents($root.'/.claude/skills/tailwindcss-development/SKILL.md');

    expect($skill)->toBeString();

    expect(frontendNoveltyRequests(frontendWorkingMethodSection((string) $skill)))->toBe([]);
});

it('checks contrast with the wcag-contrast skill, which exists', function () use ($root): void {
    $skill = file_get_contents($root.'/.claude/skills/tailwindcss-development/SKILL.md');

    expect($skill)->toBeString()
        ->and(frontendWorkingMethodSection((string) $skill))->toMatch('/`wcag-contrast`(\\]\\([^)]*\\))? skill/')
        ->and(is_file($root.'/.claude/skills/wcag-contrast/SKILL.md'))->toBeTrue();
});
