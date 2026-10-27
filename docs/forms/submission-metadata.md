# Form submission metadata (`_*` system fields)

Every public-form submission is automatically stamped with contextual `_`-prefixed system fields so admins can tell *which form*, *where*, and *which site* a submission came from — plus spam-triage context — without editors adding hidden fields per form.

> Settles issue [#207](https://github.com/UAMS-Web/uams-statamic-archive/issues/207). Companion to the form email-rendering pipeline in [`email-rendering.md`](email-rendering.md).

## How it works

[`app/Listeners/AppendSubmissionMetadata`](../../app/Listeners/AppendSubmissionMetadata.php) listens for `Statamic\Events\SubmissionCreating`, which fires inside `Submission::save()` while the submit request is still live and **before** the YAML is persisted. The listener merges the metadata into the submission's data; because `Submission::fileData()` writes `data()->all()` (every key, not just blueprint fields), the `_*` fields land in the stored YAML alongside the editor fields.

This is the server-side "Option A" from the ticket: capture happens once, for every form, and cannot be omitted or spoofed per-form. The listener is registered once in `EventServiceProvider::$listen`, like every listener (UAMS-Web/uams-statamic-archive#3240). It returns `void`, so it never aborts the save (`SubmissionCreating` aborts only on a `false` return).

## The fields

| Field | Source | Notes |
| --- | --- | --- |
| `_form_handle` | `$form->handle()` | e.g. `proclamation_request` |
| `_form_title` | `$form->title()` | human-readable form title |
| `_submitted_url` | `Referer` header | full URL of the page the form was on |
| `_submitted_path` | path of the `Referer` | e.g. `/institutional-relations/resources/proclamation-request` |
| `_site_handle` | the submission's site (see [Which site](#which-site)) | the site of the page the form was on |
| `_site_name` | the submission's site | site display name |
| `_site_url` | the submission's site | site home URL |
| `_user_agent` | `request()->userAgent()` | submitter User-Agent (spam triage) |
| `_ip` | `request()->ip()` | submitter IP |
| `_referrer` | `Referer` header | raw Referer at submit time |
| `_submitted_at` | `now()->toIso8601String()` | server-side timestamp (ISO 8601 with offset) |

### Where vs. referrer

Statamic forms POST to the `/!/forms/<handle>` endpoint, so the request URL is the endpoint, not the page the form was rendered on. The page the form was on is therefore taken from the **Referer** header — which is why `_submitted_url` / `_submitted_path` (the form page) and `_referrer` (the Referer header) resolve to the same value for a normal submission. The Referer is used for `_submitted_url` / `_submitted_path` only when it is a **well-formed URL**; when it is absent or malformed (e.g. a direct API POST, or a garbage client-supplied header), they fall back to the submit request's own URL/path. `_referrer` always records the raw header verbatim (it is literally the Referer), so it may be empty or, for a malformed header, non-URL — it is a spam-triage value, not a trusted URL.

### Which site

The submit endpoint sits under the default site's `/` prefix, so `Site::current()` during the submit request is the default site whatever page the form was on. Before [#3068](https://github.com/UAMS-Web/uams-statamic-archive/issues/3068) the three `_site_*` fields read it, and a submission from a page on `/health` recorded `default`.

The site now comes from [`uams-web/statamic-multisite-forms`](https://github.com/UAMS-Web/statamic-multisite-forms), which records the site of the submitting page under `_site`: the site whose URL the Referer falls under, or the default site when the Referer is missing or matches no site. `_site_handle`, `_site_name` and `_site_url` describe the site `_site` names, so the two records always agree. A submission saved outside the submit route carries no `_site`; the listener then names the site the same way the add-on does for that request.

The request context the notification job carries to a queue worker (`CaptureFormRequestContext`, read by `{{ global_origin }}`) takes its site from the same place.

### Request context

`_ip`, `_referrer`, and `_user_agent` read live request state. Unlike the form-email merge tags (which render later, possibly on a queue worker — see [`email-rendering.md`](email-rendering.md)), submission save runs **synchronously inside the submit request** (`FormController::submit()` → `$submission->save()`), so live request state is always the submitter's here. No deferred capture is needed.

## Editor fields are never overwritten

The listener only sets a metadata key when the submission does not already carry it, so an (unusual) editor field whose handle collides with a `_*` name keeps its submitted value.

## CP visibility

The `_*` fields are present in the submission YAML and in the submission's `data()`. Surfacing them as a dedicated "Submission context" section in the Control Panel submission view (separate from editor fields) is a follow-up: the `_*` keys are not blueprint fields, so the default CP submission detail — which renders blueprint fields — does not display them without a CP view override or addon. Tracked as a refinement on [#207](https://github.com/UAMS-Web/uams-statamic-archive/issues/207); the data is captured and durable regardless.
