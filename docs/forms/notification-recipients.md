# Form notification recipients

Form configurations in [`resources/forms/`](../../resources/forms/) live in the private [`UAMS-Web/uams-statamic-forms`](https://github.com/UAMS-Web/uams-statamic-forms) repository, checked out as a submodule at that path. An editor sets a notification's recipients on the form itself, in the Control Panel, and may enter a real staff address: the address is written to the form's YAML inside the submodule, never to this repository.

Settles [#2818](https://github.com/UAMS-Web/uams-statamic-archive/issues/2818), which supersedes the rule from [#2745](https://github.com/UAMS-Web/uams-statamic-archive/issues/2745) (following [#2740](https://github.com/UAMS-Web/uams-statamic-archive/issues/2740)) that form YAML must not name an individual address.

## How a form names a recipient

In Control Panel → Forms → the form → Email, enter the address (or a comma-separated list) in To, CC or BCC:

```yaml
to: 'office-mailbox@example.test'
```

A form may instead name a **role key** in [`config/forms.php`](../../config/forms.php):

```yaml
to: '{{ config:forms.recipients.institutional_relations }}'
```

`Statamic\Forms\Email::parseConfig()` resolves `config:` and Antlers against Laravel config before sending, so a form that still names a key sends to that key's address. The seven forms moved into the submodule all name a key today; each keeps working until an editor replaces the key with an address.

## Giving a site its own recipients

A form shared by several sites can send a site's submissions somewhere else. Since [#3066](https://github.com/UAMS-Web/uams-statamic-archive/issues/3066) the site runs [`uams-web/aether-multisite-forms`](https://github.com/UAMS-Web/statamic-multisite-forms), whose Sites section on a form's settings holds one override row per site. A row's Email replaces the form's whole email list for submissions made on that site; a site with no row, or a row whose Email is empty, uses the form's own list. A site that should also reach the form's own recipients lists them in its row too, because the two lists are not merged.

Name a row's recipients with a role key, as the form's own rows do (the decision is recorded in [`docs/DECISIONS.md`](../DECISIONS.md) under UAMS-Web/uams-statamic-archive#3066):

1. Add the key to `recipients` in [`config/forms.php`](../../config/forms.php), reading its own variable, with the site first in the name:

   ```php
   'health_institutional_relations' => env(
       'FORM_RECIPIENT_HEALTH_INSTITUTIONAL_RELATIONS'
   ),
   ```

2. Add the variable to [`.env.example`](../../.env.example) with a placeholder address (the commented example there shows the form), and set the real address in each environment.
3. In Control Panel, open Forms, then the form, then the Sites section. Add a row for the site and enter the key in its To:

   ```yaml
   site_overrides:
     -
       site: health
       email:
         -
           to: '{{ config:forms.recipients.health_institutional_relations }}'
   ```

The submission's site is the one whose URL the submitting page's address falls under; a request with no referrer counts as the default site. The notification job treats an override row exactly as it treats a form's own row: the Active toggle, conditional logic, and the field and routing recipient sources all apply when the row sets them. Since [#3164](https://github.com/UAMS-Web/uams-statamic-archive/issues/3164) the override row's email grid in the Control Panel offers the same columns, in the same order, as the form's own email grid, so the Active toggle, the recipient sources, the message and the conditional logic are set on an override row there, and validated as on a base row.

An unset or empty per-site variable fails the submit as described below for any key.

## Where an edit is written

Statamic's Git Automation tracks `resource_path('forms')` in [`config/statamic/git.php`](../../config/statamic/git.php) and commits each tracked path to the repository it sits in, so a form edit is committed to `uams-statamic-forms`, not to this repository. When production commits and pushes is [#253](https://github.com/UAMS-Web/uams-statamic-archive/issues/2815).

The field definitions in `resources/blueprints/forms/` stay in this repository: the same split as collection blueprints here and their entries in the `content` submodule.

## Getting the submodule

Both gates check it out (`.github/workflows/ci.yml`'s Pest job and `scripts/local-ci.mjs`, which reads the list `GATE_SUBMODULES` in `scripts/gate-slot.mjs`), and `node scripts/worktree-slots.mjs provision` initializes it in a slot. An existing checkout or slot needs it once:

```bash
git submodule update --init resources/forms
```

Without it (a public clone, or a checkout that never ran that command) `resources/forms` is empty, no form loads, and the tests that read the real forms skip through `Tests\Support\FormsSubmodule` rather than failing.

## Role keys and environment variables

These remain while any form names them. Removing the keys, `config/forms.php`'s `recipients`, and the `FORM_RECIPIENT_*` settings is a follow-up once every form names an address.

| Config key | Environment variable | Forms that use it today |
| --- | --- | --- |
| `forms.recipients.institutional_relations` | `FORM_RECIPIENT_INSTITUTIONAL_RELATIONS` | The four `institutional_relations_*` forms |
| `forms.recipients.mail_processing_center` | `FORM_RECIPIENT_MAIL_PROCESSING_CENTER` | `mail_processing_center_bulk_mail_request` |
| `forms.recipients.spread_kindness` | `FORM_RECIPIENT_SPREAD_KINDNESS` | `spread_kindness` |

`.env.example` lists each variable with a placeholder address, never a real one.

## What happens if a key's value is unset or empty

`config/forms.php` reads each key with `env()` and **no default**. An unset or empty `FORM_RECIPIENT_*` leaves the config value null or `''`. A real submission whose `to:` names that key then returns **HTTP 500** and sends **no** message, so a submitter is not left believing a lost notification went through. Every environment must carry real values for the keys its forms still name.
