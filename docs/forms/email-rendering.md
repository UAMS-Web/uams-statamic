# Form email rendering pipeline

How Statamic renders form-notification emails in this project, where Antlers is evaluated, and
how the request context that some custom merge tags depend on survives the path.

> Settles issues [#706](https://github.com/UAMS-Web/uams-statamic-archive/issues/706) (document + guard)
> and [#830](https://github.com/UAMS-Web/uams-statamic-archive/issues/830) (async-safe request context).
> Companion to the tag audit in [#199](https://github.com/UAMS-Web/uams-statamic-archive/issues/199) and the
> form-email epic [#3](https://github.com/UAMS-Web/uams-statamic-archive/issues/15).

## Rendering mode: synchronous (today)

Statamic's send-email job is queueable by design:

```php
// vendor/statamic/cms/src/Forms/SendEmail.php
class SendEmail implements ShouldQueue { /* ... */ }
```

But this project runs the queue on the **`sync`** connection:

```dotenv
# .env and .env.example
QUEUE_CONNECTION=sync
```

On the `sync` connection the job is executed **immediately, in the same PHP process and HTTP
request** that handled the form POST — it is never serialized to a worker. So the *effective*
rendering mode is **synchronous**, and the latency cost (rendering + SMTP handoff) is paid inside
the submit request. For our form volumes this is acceptable and is the deliberate default.

> **Async rendering is now safe.** Historically synchronous rendering was *required* for the
> request-context merge tags below to resolve. Since [#830](https://github.com/UAMS-Web/uams-statamic-archive/issues/830)
> that is no longer true: the submitter's request context is captured at submit time and carried
> across the queue boundary, so the tags resolve correctly even when a worker renders the email off
> a real (`database`/`redis`/`sqs`) connection. See [Async-safe request context](#async-safe-request-context-830).
> `sync` remains the default; switching to a real queue to take SMTP latency out of the submit
> request no longer breaks these tags.

### The submit → email path

1. [`FormController::submit()`](../../vendor/statamic/cms/src/Http/Controllers/FormController.php) — the web request. Validates, stores the submission, then **synchronously** dispatches `SendEmails` (it is *not* a `ShouldQueue` class).
2. [`SendEmails::handle()`](../../vendor/statamic/cms/src/Forms/SendEmails.php) — builds one send-email job per configured notification and dispatches them with `Bus::chain(...)`. The job class is `statamic.forms.send_email_job` — this project points it at [`UAMSWeb\FormSettings\Jobs\SendFormNotification`](../../vendor/uams-web/statamic-form-settings/src/Jobs/SendFormNotification.php) instead of the vendor default.
3. [`SendFormNotification::handle()`](../../vendor/uams-web/statamic-form-settings/src/Jobs/SendFormNotification.php): on `sync`, runs inline. It extends the vendor `SendEmail`, and before sending it skips a notification that is switched off or whose conditional logic does not match the submission, resolves the TO, CC, BCC and reply-to addresses (including recipient routing), and skips the send when no TO address resolves. It then sends `new FormNotificationEmail(...)` through `Mail::mailer(...)`. With the add-on's `notifications` feature switched off it sends exactly as the vendor job does.
4. [`FormNotificationEmail::build()`](../../vendor/uams-web/statamic-form-settings/src/Mail/FormNotificationEmail.php) — extends the vendor [`Email`](../../vendor/statamic/cms/src/Forms/Email.php) to assemble the message (next section), additionally honoring `from_name` in the `From:` header.

## Notification recipients

Form configurations live in the private `uams-statamic-forms` submodule at `resources/forms`, so a form's `to:` may carry a real address set in the Control Panel. A form may instead name a role key under `config/forms.php` (`{{ config:forms.recipients.<key> }}`), which each environment sets through its `FORM_RECIPIENT_*` variable. See [notification-recipients.md](notification-recipients.md) (UAMS-Web/uams-statamic-archive#2818, superseding UAMS-Web/uams-statamic-archive#2745's rule).

## Where Antlers is parsed

Two distinct evaluation points, both inside `Email`:

- **Config strings** — `subject`, `to`, `from`, `reply_to`, `cc`, `bcc` are run through
  `Antlers::parse()` in [`Email::parseConfig()`](../../vendor/statamic/cms/src/Forms/Email.php).
  The parse context is the submission's augmented array + global sets + `config`. This is where
  Gravity-Forms-style merge tags in a subject line resolve (e.g. `New report request from {{ name }}`),
  and where form recipient config keys resolve.
- **The body** — the message body is a *view* referenced by the email config's `html` / `text`
  keys (or the built-in `statamic::forms.automagic-email` fallback). An Antlers (`.antlers.html`)
  email view resolves custom tags at view-render time, where `Email::addData()` additionally
  exposes `fields`, `today`/`now`/`date`, `site`, and the global sets.

Our custom merge tags live in [`app/Tags/`](../../app/Tags) and are auto-registered by their
snake_case handle (`{{ ip }}` → `App\Tags\Ip`, `{{ get_param }}` → `App\Tags\GetParam`, …). They
resolve at both evaluation points.

> **Gotcha — context-only tags belong in the body.** `{{ all_fields }}` reads `fields` from the
> render context, which `parseConfig()` does *not* provide. Put `{{ all_fields }}` (and anything
> else that reads submitted-field data from context) in the **body view**, not the subject.

## The `from_name` display name

The CP email grid exposes a `from_name` column (added in [`EmailGridBlueprintCustomizer`](../../vendor/uams-web/statamic-form-settings/src/CpForms/EmailGridBlueprintCustomizer.php))
so editors can set the friendly sender name an inbox shows next to the address — Gravity Forms'
`fromName`, often a field reference like `{Name (First):1.3}{Name (Last):1.6}`. The vendor `Email`
mailable never reads it: its `addAddresses()` wires the `From:` header from the bare `from` address
only, so the name was silently dropped (issue [#213](https://github.com/UAMS-Web/uams-statamic-archive/issues/213)).

[`FormNotificationEmail`](../../vendor/uams-web/statamic-form-settings/src/Mail/FormNotificationEmail.php) layers the name on top of
the parent's address wiring:

- `from_name` is a config key, so it is Antlers / merge-tag resolved by `parseConfig()` (which maps
  over **every** config key) against the same submission context as `subject` / `from` — `{{ submission:name }}`,
  `{{ form:title }}`, etc. all resolve. No extra parse step is needed.
- After `parent::addAddresses()`, if the resolved `from_name` is non-empty the From header is rebuilt
  as `"<resolved_name>" <from@address>` (the resolved name is applied to every sender address). The
  explicit `from_name` wins over any name baked into the `from` string itself.
- If `from_name` is absent or resolves to empty, the parent's bare-email From header is left untouched
  — today's behavior.

> **Companion — importer side.** 44 Gravity Forms notifications in the export corpus populate the
> From-Name from a form field via the legacy `fromNameField` alias, which the importer currently
> drops (UAMS-Web/wordpress-importer#34). Until that lands, the imported `from_name` is empty for that
> subset and the bare-email fallback fires — render does the right thing, but there's nothing to render.

## How request context flows

Under `sync`, the job runs inside the submitter's own request, so global request state is still the
submitter's and the four request-context-dependent tags read it directly. Under an async connection
the worker has no submitter request, so each tag instead reads the **captured** context the job
carries (see [Async-safe request context](#async-safe-request-context-830)), falling back to live
request state only when no context was captured (normal page/template use):

| Tag | Reads (live, `sync`) | Captured fallback (async worker, UAMS-Web/uams-statamic-archive#830) |
| --- | --- | --- |
| `{{ ip }}` | `$_SERVER['REMOTE_ADDR']` | Submitter's IP, captured at submit time |
| `{{ referrer }}` | `$_SERVER['HTTP_REFERER']` | Submitter's `Referer` header, captured |
| `{{ get_param name="…" }}` | `request()->query(...)` | Submit request's query string, captured |
| `{{ global_origin handle="…" }}` | `Site::current()` | Origin site handle, captured |

The remaining tags do **not** depend on request context and resolve identically regardless of mode:
`{{ all_fields }}` (submission `fields` from context), `{{ today }}` (server clock),
`{{ admin_email }}` (`mail.from.address`), `{{ form_data }}` (form YAML),
`{{ quiz:* }}` / `{{ survey:* }}` (currently fixed placeholders — see [#199](https://github.com/UAMS-Web/uams-statamic-archive/issues/199)).

All ten tags are exercised through a real notification email in
[`tests/Feature/Forms/FormEmailMergeTagRenderingTest.php`](../../tests/Feature/Forms/FormEmailMergeTagRenderingTest.php),
the synchronous request-context end-to-end submit in
[`tests/Feature/Forms/FormEmailRequestContextTest.php`](../../tests/Feature/Forms/FormEmailRequestContextTest.php),
and the **async** counterpart — the same tags rendered by a real `queue:work` worker out of the
submit request — in
[`tests/Feature/Forms/FormEmailAsyncRequestContextTest.php`](../../tests/Feature/Forms/FormEmailAsyncRequestContextTest.php).

> **Validation finding — filed as [#828](https://github.com/UAMS-Web/uams-statamic-archive/issues/828)
> (follow-up to [#199](https://github.com/UAMS-Web/uams-statamic-archive/issues/199)):**
> `{{ global_origin handle="…" }}` in **non-recursive** mode returns `null` when the global set has
> no origin (e.g. a root brand), which violates its `string|array` return type and throws —
> crashing the whole email render. Recursive mode (`recursive="true"`) is safe (returns `[]`). This
> ticket leaves the tag untouched (document + guard scope); the crash is fixed under UAMS-Web/uams-statamic-archive#828 by
> returning `''` instead of `null`.

## Async-safe request context (UAMS-Web/uams-statamic-archive#830)

Form emails are safe to render off a real queue. The submitter's request context is captured while
the submit request is live and carried, as a plain serializable array, onto the queued job — so a
worker rendering the email later still resolves the four request-context tags to the submitter's
values. Three pieces cooperate:

1. **Capture at submit time** —
   [`app/Listeners/CaptureFormRequestContext`](../../app/Listeners/CaptureFormRequestContext.php)
   listens for `Statamic\Events\FormSubmitted` (fired in `FormController::submit()` while the request
   is live) and snapshots `request()->ip()`, `request()->header('Referer')`, `request()->query()`,
   and `Site::current()->handle()` into a
   [`App\Support\Forms\FormRequestContext`](../../app/Support/Forms/FormRequestContext.php) value
   object, stashed in a process-local map keyed by submission id. Like every listener, it is
   registered once in `EventServiceProvider::$listen` (UAMS-Web/uams-statamic-archive#3240). It returns `void`: `FormSubmitted` halts on
   the first non-null listener response, so returning nothing keeps the submission flowing.
2. **Carry it across the queue boundary** —
   [`SendFormNotification::__construct()`](../../vendor/uams-web/statamic-form-settings/src/Jobs/SendFormNotification.php) runs in the
   live submit request (before the job is serialized) and asks the bound `SubmissionContext` for the
   captured context. The site binds
   [`FormRequestSubmissionContext`](../../app/Support/Forms/FormRequestSubmissionContext.php) (#263),
   which reads the submission's stashed context with `FormRequestContext::peek()`, so every notification job of the submission gets it (the map holds only the latest submission); the job stores it as a plain array in a public `$requestContext`
   property that serializes cleanly onto the queued job. This sidesteps the
   [`Submission::toArray()`](../../vendor/statamic/cms/src/Forms/Submission.php) limitation — that it
   augments **only blueprint-defined fields**, so a raw extra key stashed on the submission would be
   dropped from the render context. The captured context rides on the *job*, never on the submission
   record, so it cannot leak into `{{ all_fields }}` or the stored YAML.
3. **Re-activate around the render** — `SendFormNotification::handle()` wraps `Mail::send()` in
   the binding's `during()`, which calls `FormRequestContext::during()`, which sets the captured context as "active" for the duration of
   the render (always restored afterwards, even on exception). Both Antlers contexts —
   `parseConfig()` (subject/addresses) and the body view — run inside that single `send()`, so one
   wrap covers both. Each of the four tags ([`Ip`](../../app/Tags/Ip.php),
   [`Referrer`](../../app/Tags/Referrer.php), [`GetParam`](../../app/Tags/GetParam.php),
   [`GlobalOrigin`](../../app/Tags/GlobalOrigin.php)) prefers `FormRequestContext::active()` when set
   and falls back to live `request()` / `Site::current()` otherwise — so they stay correct for
   ordinary page/template use and on the `sync` connection (where no context is captured: the job
   runs inline in the submitter's own request, so live state is already the submitter's).

### The residual guard

The capture only happens for jobs dispatched through the form-submit flow. A send-email job queued
async **without** captured context (dispatched outside that flow, or via a regression in the capture
step) would still render the four tags wrong.
[`app/Listeners/WarnWhenFormEmailQueued`](../../app/Listeners/WarnWhenFormEmailQueued.php) catches
exactly that: it listens for Laravel's `JobQueued` event — which fires **only** for a non-sync
connection (`Statamic\Queue\SyncQueue::push()` never raises it) — and logs a warning **only** when
the queued send-email job carries no captured context. A normally-submitted async job (the UAMS-Web/uams-statamic-archive#830 path)
carries context and is silent. The listener is registered once in `EventServiceProvider::$listen`
(UAMS-Web/uams-statamic-archive#3240).

## Adding new request-context-dependent tags going forward

A new tag that needs request data should:

1. Read its value from `FormRequestContext::active()` first when a captured context is present, then
   fall back to `request()` / `Site::current()` for normal (non-email) template use — mirroring the
   four existing tags.
2. Be captured: add the field to `FormRequestContext` (the value object, `capture()`, `toArray()`,
   `fromArray()`) so it rides across the queue boundary on the job.
3. Be exercised in both `FormEmailMergeTagRenderingTest.php` (renders the tag) and
   `FormEmailAsyncRequestContextTest.php` (proves it survives a real worker), and added to the table
   above.
