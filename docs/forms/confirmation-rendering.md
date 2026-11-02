# Form confirmation rendering pipeline

How a form's **confirmation** (the post-submit message / redirect / page shown to the submitter) is resolved and applied, the companion to the form [`email-rendering.md`](email-rendering.md) pipeline.

> Settles issue [#203](https://github.com/UAMS-Web/uams-statamic-archive/issues/203). Reuses the conditional-logic engine from [#202](https://github.com/UAMS-Web/uams-statamic-archive/issues/202); confirmations are user-facing where emails are recipient-facing.

## Where confirmations are configured

The form-config blueprint (CP) carries, on each form:

- **A default confirmation** — `confirmation_settings_default`, a group with `confirmation_type` (`message` / `redirect` / `entry` / `replace`), plus the type's fields: `confirmation_message`, `confirmation_redirect_url`, `confirmation_page` (an entry), and `confirmation_query_string`.
- **Additional confirmations** — `confirmation_additional`, an ordered replicator of `{confirmation_settings, conditional_logic}` sets. Each carries the same settings group plus a `conditional_logic` tree (the same `form_conditional_logic` fieldtype the email notifications use).

## How a confirmation is selected

[`UAMSWeb\Aether\Forms\Settings\Support\FormConfirmationResolver`](../../vendor/uams-web/aether-form-settings/src/Support/FormConfirmationResolver.php) picks the confirmation for a submission:

1. It walks `confirmation_additional` in order and returns the **first** set whose `conditional_logic` passes against the submitted values — evaluated by [`ConditionalLogicEvaluator`](../../vendor/uams-web/aether-form-settings/src/Support/ConditionalLogicEvaluator.php), the shared UAMS-Web/uams-statamic-archive#202 engine (a set with empty conditional logic always matches).
2. If none match, it returns `confirmation_settings_default`.
3. If the form has no confirmation config at all, it returns `null` — and the pipeline leaves Statamic's built-in post-submit behavior untouched.

## How a confirmation is applied

Statamic's `FormController::submit()` flashes the submission and a generic success message, then redirects back. [`UAMSWeb\Aether\Forms\Settings\Http\Middleware\ApplyFormConfirmation`](../../vendor/uams-web/aether-form-settings/src/Http/Middleware/ApplyFormConfirmation.php) — pushed onto the `web` group by the form settings add-on's [service provider](../../vendor/uams-web/aether-form-settings/src/ServiceProvider.php) while its `confirmations` switch is on, so it wraps the `/!/forms/{form}` submit route after the session middleware — post-processes a **successful** submission (detected by the flashed `submission`) and applies the resolved confirmation:

| `confirmation_type` | Effect |
| --- | --- |
| `message` / `replace` | Override the flashed `form.{handle}.success` message with the configured, Antlers-rendered `confirmation_message` — the form template then shows it in place of the form. |
| `redirect` | Redirect to the Antlers-rendered `confirmation_redirect_url`. |
| `entry` | Redirect to the chosen `confirmation_page` entry's URL. |

For `redirect` / `entry`, an optional `confirmation_query_string` (Antlers-rendered) is appended. An ajax submit keeps Statamic's JSON shape, with its `redirect` key set to the confirmation URL.

## Antlers in confirmation strings

The message, redirect URL, and query string are Antlers-rendered against the submission's augmented data, so an editor can embed merge tags — `{{ name }}`, `{{ all_fields }}`, specific field values — exactly as in the form-email body. As elsewhere in Statamic, Antlers is raw-by-default: the confirmation strings are **admin-authored** config (not submitter input), so this is the intended templating surface, but an editor embedding a submitted value renders it unescaped (the same model as the email body).

## What is intentionally left to the template

The redirect **delay** and **new-tab target** options (`confirmation_redirect_delay_query` / `_time`, `confirmation_redirect_target`, `confirmation_redirect_message`) describe a *client-side* delayed/targeted redirect; they are surfaced in the config for the template/JS to honor rather than performed server-side by this middleware, which does an immediate redirect.
