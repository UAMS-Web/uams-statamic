<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2712: the `adversarial-review` rule carries the review questions harvested from Anthropic's
 * review plugins, so a later edit cannot drop one without a failure.
 *
 * Each case pins one acceptance criterion's checklist by the items a reviewer answers, not by
 * its prose, so rewording a question passes and removing one fails.
 *
 * @command  php artisan test --compact tests/Feature/Config/AdversarialReviewQuestionsTest.php
 */
$root = dirname(__DIR__, 3);

/**
 * One `###` subsection of the rule's "Review questions" section, up to the next heading.
 *
 * @param  string  $rule  The rule file's contents.
 * @param  string  $heading  The subsection heading's opening words.
 */
function adversarialReviewSubsection(string $rule, string $heading): string
{
    $pattern = '/^### '.preg_quote($heading, '/').'[^\n]*\n(.*?)(?=^##)/ms';

    if (preg_match($pattern, $rule, $match) !== 1) {
        throw new RuntimeException(sprintf('No "%s" subsection found.', $heading));
    }

    return $match[1];
}

$rule = static function () use ($root): string {
    $contents = file_get_contents($root.'/.claude/rules/adversarial-review.md');

    expect($contents)->toBeString();

    return (string) $contents;
};

it('asks the four error-handling questions', function () use ($rule): void {
    $section = adversarialReviewSubsection($rule(), 'Error handling');

    // The four failure classes UAMS-Web/uams-statamic-archive#2712 names, each as a bolded question lead
    expect($section)->toContain('**Broad catches.**', '**Silent fallbacks.**', '**Masking defaults.**', '**Log and continue.**');
});

it('rates a missing test by the regression it would catch', function () use ($rule): void {
    $section = adversarialReviewSubsection($rule(), 'Test gaps');

    expect($section)->toContain('name the regression a test would catch', '**Must add:**', '**Skip:**');
});

it('checks every claim in a comment against the code', function () use ($rule): void {
    $section = adversarialReviewSubsection($rule(), 'Comment accuracy');

    expect($section)->toContain('check each statement against the code it describes');
});

it('confirms a finding independently and drops what is not confirmed', function () use ($rule): void {
    $section = adversarialReviewSubsection($rule(), 'Confirm a finding');

    expect($section)->toContain('someone other than its finder', 'is dropped from the report');
});

it('points step 5 at the confirmation rule', function () use ($rule): void {
    expect($rule())->toContain('*Confirm a finding before reporting it*');
});
