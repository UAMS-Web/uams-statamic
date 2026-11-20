<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Drift guard for the `CI` workflow's concurrency block (#530, #1153).
 *
 * Three kinds of run share the workflow, and each needs a different group:
 *
 * - A pull request's runs share one group keyed on its ref, with `cancel-in-progress`, so a new
 *   push cancels the pull request's stale run.
 * - Pushes to `main` share one group keyed on `refs/heads/main`, with `cancel-in-progress`, so a
 *   newer merge cancels the older `main` run and only the newest commit's run goes on (#1153).
 * - Any other run (a manual `workflow_dispatch` run) gets a group of its own, keyed on the commit,
 *   so nothing cancels it.
 *
 * The shape this guards against is a shared group WITHOUT `cancel-in-progress`. GitHub keeps one
 * running and one pending run per group and cancels the pending one when another is queued,
 * whatever `cancel-in-progress` says, so with one group per ref and no cancellation a merge
 * landing during a long run cancelled the previous merge's run before it started a job (#530).
 *
 * The expressions are evaluated here, for each kind of run, with GitHub's operator semantics:
 * `&&` and `||` return one of their operands rather than a boolean, which is what makes the
 * `a && b || c` form pick a value. A pure file-read check; it does not boot the framework.
 *
 * @command  php artisan test --compact tests/Feature/Config/CiConcurrencyGroupTest.php
 */
$concurrency = function (): array {
    $workflow = Yaml::parseFile(dirname(__DIR__, 3).'/.github/workflows/ci.yml');

    expect($workflow)->toBeArray()->toHaveKey('concurrency');

    $block = is_array($workflow) && is_array($workflow['concurrency'] ?? null) ? $workflow['concurrency'] : [];
    $group = $block['group'] ?? null;
    $cancel = $block['cancel-in-progress'] ?? null;

    expect($group)->toBeString()
        ->and($cancel)->toBeString();

    return ['group' => is_string($group) ? $group : '', 'cancel-in-progress' => is_string($cancel) ? $cancel : ''];
};

/**
 * Evaluate a `${{ ... }}` template the way GitHub Actions does, for the expressions this block
 * uses: `github.<name>` lookups, single-quoted strings, `==`, `&&`, `||` and parentheses.
 *
 * Text outside `${{ }}` is kept, and each expression's value is substituted as a string; a
 * template that is only an expression returns the expression's own value.
 *
 * @param  string  $template  The YAML value, such as `${{ github.workflow }}-${{ ... }}`
 * @param  array<string, string>  $github  The `github` context fields the expression reads
 */
function ciConcurrencyEvaluate(string $template, array $github): string|bool
{
    // A template that is one whole expression keeps the expression's type
    if (preg_match('/^\$\{\{(.*)\}\}$/s', $template, $whole) === 1 && ! str_contains($whole[1], '}}')) {
        return ciConcurrencyExpression($whole[1], $github);
    }

    return (string) preg_replace_callback(
        '/\$\{\{(.*?)\}\}/s',
        function (array $match) use ($github): string {
            $value = ciConcurrencyExpression($match[1], $github);

            return is_bool($value) ? ($value ? 'true' : 'false') : $value;
        },
        $template,
    );
}

/**
 * Evaluate one expression body with GitHub's precedence: `==` binds tighter than `&&`, which
 * binds tighter than `||`.
 *
 * @param  array<string, string>  $github
 */
function ciConcurrencyExpression(string $source, array $github): string|bool
{
    preg_match_all("/\\s*(\\(|\\)|==|&&|\\|\\||'[^']*'|github\\.[a-z_]+)/", $source, $matches);
    $tokens = $matches[1];

    // Every non-space character must belong to a token, or the expression uses syntax this does not read
    expect(preg_replace('/\s+/', '', implode('', $tokens)))->toBe(preg_replace('/\s+/', '', $source));

    $position = 0;
    $value = ciConcurrencyOr($tokens, $position, $github);

    expect($position)->toBe(count($tokens));

    return $value;
}

/**
 * `||`: the left operand when it is truthy, the right one otherwise.
 *
 * @param  list<string>  $tokens
 * @param  array<string, string>  $github
 */
function ciConcurrencyOr(array $tokens, int &$position, array $github): string|bool
{
    $left = ciConcurrencyAnd($tokens, $position, $github);
    while (($tokens[$position] ?? null) === '||') {
        $position++;
        $right = ciConcurrencyAnd($tokens, $position, $github);
        $left = $left === false || $left === '' ? $right : $left;
    }

    return $left;
}

/**
 * `&&`: the left operand when it is falsy, the right one otherwise.
 *
 * @param  list<string>  $tokens
 * @param  array<string, string>  $github
 */
function ciConcurrencyAnd(array $tokens, int &$position, array $github): string|bool
{
    $left = ciConcurrencyEquality($tokens, $position, $github);
    while (($tokens[$position] ?? null) === '&&') {
        $position++;
        $right = ciConcurrencyEquality($tokens, $position, $github);
        $left = $left === false || $left === '' ? $left : $right;
    }

    return $left;
}

/**
 * `==` between two operands, a boolean.
 *
 * @param  list<string>  $tokens
 * @param  array<string, string>  $github
 */
function ciConcurrencyEquality(array $tokens, int &$position, array $github): string|bool
{
    $left = ciConcurrencyPrimary($tokens, $position, $github);
    while (($tokens[$position] ?? null) === '==') {
        $position++;
        $left = $left === ciConcurrencyPrimary($tokens, $position, $github);
    }

    return $left;
}

/**
 * A parenthesized expression, a string literal or a `github.<name>` lookup.
 *
 * @param  list<string>  $tokens
 * @param  array<string, string>  $github
 */
function ciConcurrencyPrimary(array $tokens, int &$position, array $github): string|bool
{
    $token = $tokens[$position] ?? '';
    $position++;
    if ($token === '(') {
        $value = ciConcurrencyOr($tokens, $position, $github);
        $position++;

        return $value;
    }
    if (str_starts_with($token, "'")) {
        return substr($token, 1, -1);
    }

    return $github[substr($token, strlen('github.'))] ?? '';
}

/**
 * The `github` context fields for a kind of run.
 *
 * @return array<string, string>
 */
function ciConcurrencyRun(string $event, string $ref, string $sha): array
{
    return ['workflow' => 'CI', 'event_name' => $event, 'ref' => $ref, 'sha' => $sha];
}

it('evaluates GitHub operators to an operand, not a boolean', function (): void {
    // A control on the evaluator itself: `a && b || c` picks b or c, and `==` is a boolean
    $github = ciConcurrencyRun('push', 'refs/heads/main', 'abc');

    expect(ciConcurrencyEvaluate("\${{ github.event_name == 'push' && github.ref || github.sha }}", $github))->toBe('refs/heads/main')
        ->and(ciConcurrencyEvaluate("\${{ github.event_name == 'pull_request' && github.ref || github.sha }}", $github))->toBe('abc')
        ->and(ciConcurrencyEvaluate("\${{ github.event_name == 'push' }}", $github))->toBeTrue()
        ->and(ciConcurrencyEvaluate('${{ github.workflow }}-x', $github))->toBe('CI-x');
});

it('puts every push to main in one group that cancels the older run (#1153)', function () use ($concurrency) {
    $block = $concurrency();
    $older = ciConcurrencyRun('push', 'refs/heads/main', '1111111');
    $newer = ciConcurrencyRun('push', 'refs/heads/main', '2222222');

    expect(ciConcurrencyEvaluate($block['group'], $older))->toBe('CI-refs/heads/main')
        ->and(ciConcurrencyEvaluate($block['group'], $newer))->toBe('CI-refs/heads/main')
        ->and(ciConcurrencyEvaluate($block['cancel-in-progress'], $newer))->toBeTrue();
});

it('still cancels a pull request run that a newer push to it made stale', function () use ($concurrency) {
    $block = $concurrency();
    $run = ciConcurrencyRun('pull_request', 'refs/pull/42/merge', '3333333');

    expect(ciConcurrencyEvaluate($block['group'], $run))->toBe('CI-refs/pull/42/merge')
        ->and(ciConcurrencyEvaluate($block['cancel-in-progress'], $run))->toBeTrue();
});

it('keeps pull requests, main pushes and manual runs in groups that never meet', function () use ($concurrency) {
    $block = $concurrency();
    $sha = '4444444';
    $groups = [
        'pull request' => ciConcurrencyEvaluate($block['group'], ciConcurrencyRun('pull_request', 'refs/pull/42/merge', $sha)),
        'main push' => ciConcurrencyEvaluate($block['group'], ciConcurrencyRun('push', 'refs/heads/main', $sha)),
        'manual run on main' => ciConcurrencyEvaluate($block['group'], ciConcurrencyRun('workflow_dispatch', 'refs/heads/main', $sha)),
    ];

    // The same commit in all three, so only the event can tell them apart
    expect(array_unique($groups))->toHaveCount(3);
});

it('gives a manual run a group of its own that nothing cancels', function () use ($concurrency) {
    $block = $concurrency();
    $first = ciConcurrencyRun('workflow_dispatch', 'refs/heads/main', '5555555');
    $second = ciConcurrencyRun('workflow_dispatch', 'refs/heads/main', '6666666');

    expect(ciConcurrencyEvaluate($block['group'], $first))->toBe('CI-5555555')
        ->and(ciConcurrencyEvaluate($block['group'], $second))->toBe('CI-6666666')
        ->and(ciConcurrencyEvaluate($block['cancel-in-progress'], $first))->toBeFalse();
});

it('reports the #530 shape, a shared push group that does not cancel, so the cases above can fail', function () use ($concurrency) {
    // Fed through the same evaluation, main's group on #530's line is per commit and the
    // cancellation is off: the #1153 case's expectations would not hold
    $old = [
        'group' => "\${{ github.workflow }}-\${{ github.event_name == 'pull_request' && github.ref || github.sha }}",
        'cancel-in-progress' => "\${{ github.event_name == 'pull_request' }}",
    ];
    $newer = ciConcurrencyRun('push', 'refs/heads/main', '2222222');

    expect($old)->not->toBe($concurrency())
        ->and(ciConcurrencyEvaluate($old['group'], $newer))->toBe('CI-2222222')
        ->and(ciConcurrencyEvaluate($old['cancel-in-progress'], $newer))->toBeFalse();
});
