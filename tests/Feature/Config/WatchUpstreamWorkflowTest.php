<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Pins the `pestphp/pest-plugin-mutate` watch in `.github/workflows/watch-upstream.yml` (UAMS-Web/uams-statamic-archive#2677).
 *
 * UAMS-Web/uams-statamic-archive#2576 decided to report the five local `pest-plugin-mutate` patches upstream once the plugin
 * shows activity, and relies on this workflow to notice it. The row in
 * `.github/upstream-watches/2676-pestphp-pest-plugin-mutate-43.json` watches two signals for
 * #2676: `pestphp/pest-plugin-mutate#43` being merged or closed, and a Packagist release past the
 * version `composer.lock` installs.
 *
 * The tests run the workflow's own step script, read from the YAML, in a scratch directory that
 * holds the real loader, the real row file and the real `composer.lock`. `gh`, `curl` and `date`
 * are stubs on `PATH`; the real `jq`, `node` and `sort` run, so a changed filter or comparison is
 * exercised rather than trusted. Each comment the script posts is appended to `posted.log`.
 *
 * The behavior tests skip on Windows. They need `bash` and `jq` on `PATH` and join it with `:`,
 * and the workflow itself only ever runs on `ubuntu-latest`. Verified on macOS.
 *
 * UAMS-Web/uams-statamic-archive#2727 adds a third signal for a `kind: pr` row carrying `released: true`: once the pull request
 * has merged, one comment when the newest tag on its base-branch line contains the merge commit.
 * Those cases stage a fixture copy of the retired `2441-laravel-framework-61786.json` row, from
 * `tests/Fixtures/upstream-watches/`, and the real `scripts/check-upstream-release.mjs`, with stub
 * `git ls-remote` and `gh api` answers. The live row was retired with its patch by UAMS-Web/uams-statamic-archive#2441.
 *
 * @command  php artisan test --compact tests/Feature/Config/WatchUpstreamWorkflowTest.php
 */

/**
 * The row file under test, relative to the repository root.
 */
const WATCH_UPSTREAM_MUTATE_ROW = '.github/upstream-watches/2676-pestphp-pest-plugin-mutate-43.json';

/**
 * The `released` row under test, as it is staged in the scratch directory (UAMS-Web/uams-statamic-archive#2727).
 */
const WATCH_UPSTREAM_LARAVEL_ROW = '.github/upstream-watches/2441-laravel-framework-61786.json';

/**
 * The fixture copy of the retired `released` row, relative to the repository root (UAMS-Web/uams-statamic-archive#2441).
 */
const WATCH_UPSTREAM_LARAVEL_FIXTURE = 'tests/Fixtures/upstream-watches/2441-laravel-framework-61786.json';

/**
 * `git ls-remote --tags --refs` output for laravel/framework: two lines, and a newer pre-release.
 */
const WATCH_UPSTREAM_LARAVEL_TAGS = "a1\trefs/tags/v12.69.3\nb2\trefs/tags/v13.34.0\nc3\trefs/tags/v13.35.0-beta.1\n";

/**
 * A merged `laravel/framework#61786` on the `13.x` line, as `gh pr view` reports it.
 */
const WATCH_UPSTREAM_LARAVEL_MERGED = '{"state":"MERGED","mergedAt":"2026-09-29T12:00:00Z","mergeCommit":{"oid":"6070a6d7e9804a490b12cb889173fbb926b8e3c8"},"baseRefName":"13.x"}';

/**
 * Separator the stub `gh` writes after each posted comment body.
 */
const WATCH_UPSTREAM_COMMENT_END = '<<END-OF-COMMENT>>';

/**
 * The step's shell script, read from the workflow.
 */
function watchUpstreamScript(): string
{
    $workflow = Yaml::parseFile(dirname(__DIR__, 3).'/.github/workflows/watch-upstream.yml');
    $jobs = is_array($workflow) ? ($workflow['jobs'] ?? null) : null;
    $job = is_array($jobs) ? reset($jobs) : null;
    $steps = is_array($job) ? ($job['steps'] ?? null) : null;

    // Collect the scripts of the steps that run one; the other step is the checkout
    $scripts = [];

    foreach (is_array($steps) ? $steps : [] as $step) {
        if (is_array($step) && is_string($step['run'] ?? null)) {
            $scripts[] = $step['run'];
        }
    }

    if (count($scripts) !== 1) {
        throw new RuntimeException('watch-upstream.yml must declare one step with a run script');
    }

    return $scripts[0];
}

/**
 * The `pestphp/pest-plugin-mutate` version `composer.lock` installs.
 */
function watchUpstreamInstalledMutateVersion(): string
{
    $lock = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/composer.lock'), true);
    $packages = [];

    // Merge both lock sections, as the workflow's own filter does
    foreach (['packages', 'packages-dev'] as $section) {
        $entries = is_array($lock) ? ($lock[$section] ?? null) : null;
        $packages = [...$packages, ...(is_array($entries) ? $entries : [])];
    }

    // Find the package's installed version
    foreach ($packages as $package) {
        if (is_array($package) && ($package['name'] ?? null) === 'pestphp/pest-plugin-mutate' && is_string($package['version'] ?? null)) {
            return $package['version'];
        }
    }

    throw new RuntimeException('composer.lock does not install pestphp/pest-plugin-mutate');
}

/**
 * The installed version with its patch number raised by one, for a release past it.
 */
function watchUpstreamNextMutateVersion(): string
{
    $installed = watchUpstreamInstalledMutateVersion();

    // Fail loudly rather than build a version the comparison was never meant to see
    if (preg_match('/^(v?)(\d+)\.(\d+)\.(\d+)$/', $installed, $parts) !== 1) {
        throw new RuntimeException('Unexpected pest-plugin-mutate version '.$installed);
    }

    return $parts[1].$parts[2].'.'.$parts[3].'.'.((int) $parts[4] + 1);
}

/**
 * A Packagist `p2` answer listing the given versions, newest first.
 *
 * @param  list<string>  $versions  The versions to list
 */
function watchUpstreamPackagist(array $versions): string
{
    return (string) json_encode(['packages' => ['pestphp/pest-plugin-mutate' => array_map(
        fn (string $version): array => ['version' => $version],
        $versions,
    )]]);
}

/**
 * Run the step's script with one row loaded, the `pest-plugin-mutate` row unless another is named.
 *
 * The stub `gh` answers the tracker's state from `tracker-state`, its existing comment bodies
 * from `comments`, and the upstream pull request from `pr.json`. The stub `curl` prints
 * `packagist.json`, and the stub `date` prints a Wednesday, so no weekly heartbeat is posted.
 * The stub `gh api` answers a compare call with `compare-status`, exiting with `compare-exit`,
 * and logs each path to `compare.log`; the stub `git` prints `tags` for `ls-remote`.
 *
 * @param  array<string, string>  $files  Stub control files overriding the defaults, by name
 * @param  string  $row  The row file to load, relative to the repository root
 * @param  string|null  $source  The file to stage as `$row`, relative to the repository root;
 *                               `$row` itself when null
 * @return array{exit: int|null, output: string, posted: list<string>, compared: list<string>} The exit code, output, posted comments and compare paths
 */
function runWatchUpstream(array $files = [], string $row = WATCH_UPSTREAM_MUTATE_ROW, ?string $source = null): array
{
    $root = dirname(__DIR__, 3);
    $directory = sys_get_temp_dir().'/watch-upstream-test-'.bin2hex(random_bytes(6));
    mkdir($directory.'/bin', 0777, true);
    mkdir($directory.'/scripts', 0777, true);
    mkdir($directory.'/.github/upstream-watches', 0777, true);

    // Stage the real loader, row file and lockfile where the script expects them
    copy($root.'/scripts/load-upstream-watches.mjs', $directory.'/scripts/load-upstream-watches.mjs');
    copy($root.'/scripts/check-upstream-release.mjs', $directory.'/scripts/check-upstream-release.mjs');
    $source ??= $row;
    copy($root.'/'.$source, $directory.'/'.$row);

    // The stub `gh issue comment` accepts only the row's own tracker
    $tracker = json_decode((string) file_get_contents($root.'/'.$source), true);
    file_put_contents($directory.'/tracker', is_array($tracker) && is_int($tracker['tracker'] ?? null) ? (string) $tracker['tracker'] : '');
    copy($root.'/composer.lock', $directory.'/composer.lock');

    // Write the stubs, which answer from the control files in the scratch directory
    file_put_contents($directory.'/bin/gh', <<<'SH'
        #!/bin/bash
        dir="$(cd "$(dirname "$0")/.." && pwd)"
        printf '%s\n' "$*" >> "$dir/calls.log"
        if [ "$1" = "api" ]; then
          printf '%s\n' "$2" >> "$dir/compare.log"
          cat "$dir/compare-status"
          exit "$(cat "$dir/compare-exit")"
        fi
        case "$1 $2" in
          "issue view")
            case "$*" in
              *"--json state"*) cat "$dir/tracker-state" ;;
              *"--json comments"*) cat "$dir/comments" ;;
              *) exit 1 ;;
            esac ;;
          "pr view") cat "$dir/pr.json" ;;
          "issue comment")
            [ "$3" = "$(cat "$dir/tracker")" ] || exit 1
            [ "$4" = "--body" ] || exit 1
            printf '%s\n%s\n' "$5" "<<END-OF-COMMENT>>" >> "$dir/posted.log" ;;
          *) exit 1 ;;
        esac
        SH);
    file_put_contents($directory.'/bin/curl', "#!/bin/bash\ndir=\"\$(cd \"\$(dirname \"\$0\")/..\" && pwd)\"\ncat \"\$dir/packagist.json\"\n");
    file_put_contents($directory.'/bin/date', "#!/bin/bash\necho 3\n");
    file_put_contents($directory.'/bin/git', "#!/bin/bash\ndir=\"\$(cd \"\$(dirname \"\$0\")/..\" && pwd)\"\n[ \"\$1\" = ls-remote ] || exit 1\ncat \"\$dir/tags\"\n");
    chmod($directory.'/bin/git', 0755);
    chmod($directory.'/bin/gh', 0755);
    chmod($directory.'/bin/curl', 0755);
    chmod($directory.'/bin/date', 0755);

    // Default to an open tracker with no comments, an open pull request and no newer release
    $files += [
        'tracker-state' => "OPEN\n",
        'comments' => '',
        'pr.json' => '{"state":"OPEN","mergedAt":null}',
        'packagist.json' => watchUpstreamPackagist([watchUpstreamInstalledMutateVersion()]),
        'tags' => WATCH_UPSTREAM_LARAVEL_TAGS,
        'compare-status' => "ahead\n",
        'compare-exit' => '0',
    ];

    // Create the control files this case asks for
    foreach ($files as $name => $contents) {
        file_put_contents($directory.'/'.$name, $contents);
    }

    // Run the script as the runner does, from the checkout root with the stubs first on PATH
    $process = new Process(['bash', '-e', '-c', watchUpstreamScript()], $directory, [
        'PATH' => $directory.'/bin:'.getenv('PATH'),
        'GH_TOKEN' => 'stub',
    ]);
    $process->run();

    // Read the posted comments before removing the scratch directory
    $log = is_file($directory.'/posted.log') ? (string) file_get_contents($directory.'/posted.log') : '';
    $compared = is_file($directory.'/compare.log') ? array_values(array_filter(explode("\n", (string) file_get_contents($directory.'/compare.log')))) : [];
    $posted = array_values(array_filter(
        array_map('trim', explode(WATCH_UPSTREAM_COMMENT_END, $log)),
        fn (string $body): bool => $body !== '',
    ));

    (new Process(['rm', '-rf', $directory]))->run();

    return [
        'exit' => $process->getExitCode(),
        'output' => $process->getOutput().$process->getErrorOutput(),
        'posted' => $posted,
        'compared' => $compared,
    ];
}

it('watches pest-plugin-mutate#43 and the plugin releases against #2676', function (): void {
    $row = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/'.WATCH_UPSTREAM_MUTATE_ROW), true);

    expect($row)->toBe([
        'tracker' => 2676,
        'repo' => 'pestphp/pest-plugin-mutate',
        'kind' => 'pr',
        'number' => 43,
        'composer' => 'pestphp/pest-plugin-mutate',
    ]);
});

it('stays silent while the pull request is open and no newer release exists', function (): void {
    $run = runWatchUpstream();

    expect($run['exit'])->toBe(0, $run['output'])
        ->and($run['posted'])->toBe([]);
})->skipOnWindows();

it('comments once on #2676 when a release past the installed version ships', function (): void {
    $next = watchUpstreamNextMutateVersion();
    $packagist = watchUpstreamPackagist([$next, watchUpstreamInstalledMutateVersion()]);

    $first = runWatchUpstream(['packagist.json' => $packagist]);

    expect($first['exit'])->toBe(0, $first['output'])
        ->and($first['posted'])->toHaveCount(1)
        ->and($first['posted'][0])->toContain('`pestphp/pest-plugin-mutate` **'.$next.'**')
        ->and($first['posted'][0])->toContain('<!-- watch-upstream:tripped:pestphp/pest-plugin-mutate#release-'.$next.' -->');

    // Replay the first run's comment as the tracker's history: the same release must not notify again
    $second = runWatchUpstream(['packagist.json' => $packagist, 'comments' => $first['posted'][0]]);

    expect($second['exit'])->toBe(0, $second['output'])
        ->and($second['posted'])->toBe([]);
})->skipOnWindows();

it('comments once on #2676 when #43 is merged', function (): void {
    $pr = '{"state":"MERGED","mergedAt":"2026-09-29T12:00:00Z"}';

    $first = runWatchUpstream(['pr.json' => $pr]);

    expect($first['exit'])->toBe(0, $first['output'])
        ->and($first['posted'])->toHaveCount(1)
        ->and($first['posted'][0])->toContain('Upstream PR `pestphp/pest-plugin-mutate`#43 was **MERGED**');

    $second = runWatchUpstream(['pr.json' => $pr, 'comments' => $first['posted'][0]]);

    // A crashed run posts nothing too, so its exit code is what makes the silence mean something
    expect($second['exit'])->toBe(0, $second['output'])
        ->and($second['posted'])->toBe([]);
})->skipOnWindows();

it('comments once on #2676 when #43 is closed without merging', function (): void {
    $pr = '{"state":"CLOSED","mergedAt":null}';

    $first = runWatchUpstream(['pr.json' => $pr]);

    expect($first['exit'])->toBe(0, $first['output'])
        ->and($first['posted'])->toHaveCount(1)
        ->and($first['posted'][0])->toContain('Upstream PR `pestphp/pest-plugin-mutate`#43 was **CLOSED without merging**');

    $second = runWatchUpstream(['pr.json' => $pr, 'comments' => $first['posted'][0]]);

    // A crashed run posts nothing too, so its exit code is what makes the silence mean something
    expect($second['exit'])->toBe(0, $second['output'])
        ->and($second['posted'])->toBe([]);
})->skipOnWindows();

it('stops watching once #2676 is closed', function (): void {
    $run = runWatchUpstream([
        'tracker-state' => "CLOSED\n",
        'pr.json' => '{"state":"MERGED","mergedAt":"2026-09-29T12:00:00Z"}',
    ]);

    expect($run['exit'])->toBe(0, $run['output'])
        ->and($run['posted'])->toBe([]);
})->skipOnWindows();

it('ignores a pre-release listed above the installed version', function (): void {
    $run = runWatchUpstream(['packagist.json' => watchUpstreamPackagist([
        watchUpstreamNextMutateVersion().'-beta.1',
        watchUpstreamInstalledMutateVersion(),
    ])]);

    expect($run['exit'])->toBe(0, $run['output'])
        ->and($run['posted'])->toBe([]);
})->skipOnWindows();

it('ignores a latest stable release older than the installed version', function (): void {
    $run = runWatchUpstream(['packagist.json' => watchUpstreamPackagist(['v1.0.0'])]);

    expect($run['exit'])->toBe(0, $run['output'])
        ->and($run['posted'])->toBe([]);
})->skipOnWindows();

it('comments once on #2441 when the newest 13.x release contains the merge', function (): void {
    $files = ['pr.json' => WATCH_UPSTREAM_LARAVEL_MERGED, 'compare-status' => "behind\n"];

    $first = runWatchUpstream($files, WATCH_UPSTREAM_LARAVEL_ROW, WATCH_UPSTREAM_LARAVEL_FIXTURE);

    // The merge and the release are two signals, so the one comment carries both
    expect($first['exit'])->toBe(0, $first['output'])
        ->and($first['posted'])->toHaveCount(1)
        ->and($first['posted'][0])->toContain('`laravel/framework` **v13.34.0**, the newest release on the `13.x` line')
        ->and($first['posted'][0])->toContain('<!-- watch-upstream:tripped:laravel/framework#61786-released -->')
        ->and($first['compared'])->toBe(['repos/laravel/framework/compare/v13.34.0...6070a6d7e9804a490b12cb889173fbb926b8e3c8']);

    $second = runWatchUpstream([...$files, 'comments' => $first['posted'][0]], WATCH_UPSTREAM_LARAVEL_ROW, WATCH_UPSTREAM_LARAVEL_FIXTURE);

    // A repeat run neither posts nor spends a compare call on a signal already reported
    expect($second['exit'])->toBe(0, $second['output'])
        ->and($second['posted'])->toBe([])
        ->and($second['compared'])->toBe([]);
})->skipOnWindows();

it('stays silent on the release while the newest 13.x tag lacks the merge', function (): void {
    $merged = runWatchUpstream(['pr.json' => WATCH_UPSTREAM_LARAVEL_MERGED, 'compare-status' => "ahead\n"], WATCH_UPSTREAM_LARAVEL_ROW, WATCH_UPSTREAM_LARAVEL_FIXTURE);

    // Replay the merge notification, so the only comment left to post would be the release
    $run = runWatchUpstream([
        'pr.json' => WATCH_UPSTREAM_LARAVEL_MERGED,
        'compare-status' => "ahead\n",
        'comments' => $merged['posted'][0] ?? '',
    ], WATCH_UPSTREAM_LARAVEL_ROW, WATCH_UPSTREAM_LARAVEL_FIXTURE);

    expect($merged['posted'])->toHaveCount(1)
        ->and($merged['posted'][0])->not->toContain('newest release')
        ->and($run['exit'])->toBe(0, $run['output'])
        ->and($run['posted'])->toBe([])
        ->and($run['compared'])->toBe(['repos/laravel/framework/compare/v13.34.0...6070a6d7e9804a490b12cb889173fbb926b8e3c8']);
})->skipOnWindows();

it('does not check for a release before the pull request merges', function (): void {
    $run = runWatchUpstream(['compare-status' => "behind\n"], WATCH_UPSTREAM_LARAVEL_ROW, WATCH_UPSTREAM_LARAVEL_FIXTURE);

    expect($run['exit'])->toBe(0, $run['output'])
        ->and($run['posted'])->toBe([])
        ->and($run['compared'])->toBe([]);
})->skipOnWindows();

it('fails the run when the release check fails, rather than reading it as not released', function (): void {
    $run = runWatchUpstream(['pr.json' => WATCH_UPSTREAM_LARAVEL_MERGED, 'compare-exit' => '1'], WATCH_UPSTREAM_LARAVEL_ROW, WATCH_UPSTREAM_LARAVEL_FIXTURE);

    expect($run['exit'])->not->toBe(0)
        ->and($run['posted'])->toBe([]);
})->skipOnWindows();

it('never checks a release for a row without the flag', function (): void {
    $run = runWatchUpstream(['pr.json' => '{"state":"MERGED","mergedAt":"2026-09-29T12:00:00Z","mergeCommit":{"oid":"abc1234"},"baseRefName":"5.x"}', 'compare-status' => "behind\n"]);

    expect($run['exit'])->toBe(0, $run['output'])
        ->and($run['compared'])->toBe([]);
})->skipOnWindows();
