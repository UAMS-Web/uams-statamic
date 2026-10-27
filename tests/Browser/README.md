# Control Panel browser-smoke harness

Headless Control Panel smoke tests (issue UAMS-Web/uams-statamic-archive#852). These boot the real CP in a
headless Chromium via [Pest browser testing](https://pestphp.com/docs/browser-testing)
(Playwright) and assert that a page — most usefully a **Vue fieldtype** — renders
with **no JavaScript console errors**. This automates the recurring *"renders in
the Control Panel with no console errors"* acceptance criterion that otherwise
can only be checked by hand.

This suite is **separate from the PHPUnit suite**. `vendor/bin/phpunit` runs the
`Unit` + `Feature` testsuites (see `phpunit.xml`) and never sees `tests/Browser`.
Run the browser suite explicitly:

```bash
vendor/bin/pest tests/Browser
```

## Requirements (one-time per checkout / worktree)

A fresh checkout or git worktree needs the usual app setup plus the Playwright
browser binary:

```bash
composer install
npm install                       # installs `playwright` (a dev dependency)
npx playwright install chromium   # downloads the browser binary
cp .env.example .env && php artisan key:generate   # APP_KEY for the HTTP layer
php artisan migrate --force       # a sqlite db so the app boots
npm run cp:build && npm run build # the CP/site assets the served app loads
```

The served app runs **in-process** (an amphp socket server inside the Pest
process), so it shares the test's Laravel application — including its Stache.

## Writing a fieldtype smoke

Copy `CpFieldtypeSmokeTest.php`. The shape is:

```php
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->cpEmail = 'smoke-'.uniqid().'@example.test';
    $this->cpPassword = 'smoke-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    Collection::make('fieldtype_smoke')->title('Fieldtype Smoke')->save();
    Blueprint::makeFromFields([
        'sample_field' => ['type' => 'your_fieldtype', 'display' => 'Sample Field'],
    ])->setHandle('fieldtype_smoke')->setNamespace('collections.fieldtype_smoke')->save();
    Stache::refresh();
});

afterEach(function () {
    Collection::find('fieldtype_smoke')?->delete();
    Blueprint::find('collections.fieldtype_smoke.fieldtype_smoke')?->delete();
    $this->cpUser?->delete();
    Stache::refresh();
});

it('renders your fieldtype in the CP without JavaScript errors', function () {
    $page = loginToCp($this->cpEmail, $this->cpPassword)
        ->navigate('/cp/collections/fieldtype_smoke/entries/create/default')
        ->assertSee('Sample Field');

    assertNoAppJavaScriptErrors($page);
});
```

The helpers live in [`tests/Support/CpSmoke.php`](../Support/CpSmoke.php):

- **`seedCpSuperUser($email, $password)`** — creates a flat-file super user and
  `Stache::refresh()`es so the in-process server resolves it. Caller deletes it
  in `afterEach`.
- **`loginToCp($email, $password)`** — drives the real login form (the submit
  button is **"Continue"**) and lands on `/cp/dashboard`.
- **`assertNoAppJavaScriptErrors($page)`** — asserts a clean console, first
  filtering known-benign browser noise (`benignConsolePatterns()`). Use this
  instead of the plugin's raw `assertNoJavaScriptErrors()`.

### Isolation

Every smoke seeds its own user / collection / blueprint and tears them down, so
the suite writes nothing durable into the repo. Use unique handles/emails.

## Gotchas

- **Benign console noise.** Chromium emits `ResizeObserver loop completed with
  undelivered notifications` for almost any app that observes element size (the
  CP does). It is *not* a real error. `assertNoAppJavaScriptErrors()` filters it
  via `benignConsolePatterns()` — extend that list (with a justifying comment)
  only for genuinely-benign messages, never to paper over a real error.
- **Stale Playwright server hang.** A killed/interrupted run can leave
  `vendor/pestphp/pest-plugin-browser/.temp/playwright-server.json` pointing at a
  dead port; the next run then hangs trying to reuse it. If a run hangs at start,
  delete that file:
  `rm -f vendor/pestphp/pest-plugin-browser/.temp/playwright-server.json`.
- **Failure screenshots** are written to `tests/Browser/Screenshots/` (gitignored)
  — handy for debugging a failed assertion.
- **Cross-process content.** The in-process server shares the test's app, but
  resolves content against a Stache snapshot. Always `Stache::refresh()` after
  creating users/collections/blueprints a navigation will depend on.
