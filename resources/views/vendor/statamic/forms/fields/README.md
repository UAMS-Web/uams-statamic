# Statamic forms — public-form fieldtype overrides

This directory holds Bootstrap-styled overrides for Statamic's public form-rendering pipeline. Each file replaces the corresponding fieldtype template that Statamic core would emit when a `{{ form:create }}` (or `{{ form:render }}`) Antlers tag renders a field with that `type:`.

The parent wrapper for every field — labels, instructions, errors, the form-group div, and the field-conditions Alpine wrapper — lives one directory up, in [`../fields.antlers.html`](../fields.antlers.html). That parent is also where **`visibility: hidden`** is handled (the entire render is skipped).

This README documents the **`visibility: read_only`** contract, because that one lives per-fieldtype here.

---

## Blueprint `visibility` values

A blueprint field's `visibility:` key takes one of four values (default is `visible` when unset). Each is handled at a different layer.

| Value | Layer | What happens on the public form |
|---|---|---|
| `visible` (default) | normal | Renders normally. |
| `hidden` | parent wrapper (`../fields.antlers.html`) | Field is skipped entirely — never reaches the DOM. Use for admin-only / system-only fields (e.g., WordPress-imported Gravity Forms admin-context fields). |
| `read_only` | **per fieldtype, here** | Field renders with its value visible but cannot be edited by the user. See below. |
| `computed` | not currently handled | Behaves as `visible` today. If you need read-only-derived-from-elsewhere semantics, use `read_only` + a server-side default. |

See `vendor/statamic/cms/src/Fields/Field.php::visibility()` for the source-of-truth list.

---

## `read_only` contract

A blueprint field marked `visibility: read_only` must render on the public form with all of:

1. **All form controls inside the field are non-editable.**
2. **The user can still see the value** the controls hold.
3. **The label, instructions, validation hint, and error message render normally** (handled by the parent wrapper — your fieldtype template doesn't touch those).

The user's edits don't need to round-trip to the server — submitting a read-only field with no value, or a default the server provides, is acceptable. The acceptance criteria for issue UAMS-Web/uams-statamic-archive#726 explicitly allow `"field is absent from the submission and the server provides a default"`.

### Attribute choice: `readonly` vs `disabled`

HTML's two non-edit attributes are not interchangeable.

| Use `readonly` + `aria-readonly="true"` on | Use `disabled` on |
|---|---|
| `<input type="text">` | `<select>` (HTML does not support `<select readonly>`) |
| `<input type="email">` | `<input type="radio">` |
| `<input type="url">` | `<input type="checkbox">` |
| `<input type="tel">` | `<input type="file">` |
| `<input type="password">` | Composite buttons that mutate field shape (e.g., add/remove row buttons on `list` — prefer to hide the buttons entirely with `{{ unless visibility == 'read_only' }}`) |
| `<input type="number">` | |
| `<input type="date">` / `time` / `datetime-local` / `month` / `week` | |
| `<input type="search">` | |
| `<textarea>` | |

`readonly` keeps the control's value in the form submission; `disabled` drops it. Either is acceptable per the AC, but pick `readonly` when the choice is available because it produces a less surprising submission shape.

### Standard emission pattern

Inside the relevant `<input>`, `<select>`, or `<textarea>` opening tag, emit the attribute right after the existing `required` attribute pipe so the read-only block stays grouped with other state-driven attributes.

**For text-shaped inputs and textareas:**

```antlers
{{# `visibility: read_only` — see README.md in this directory. #}}
{{ if visibility == 'read_only' }}
    readonly
    aria-readonly="true"
{{ /if }}
```

**For selects, radios, checkboxes, file inputs, toggles:**

```antlers
{{# `visibility: read_only` — see README.md in this directory. #}}
{{ ( visibility == 'read_only' ) | attribute:disabled }}
```

**For inputs that already emit `readonly` from another flag (e.g., `calculation_query: true`):**

Guard the read-only block so the attribute isn't emitted twice (the second pattern below is what `text.antlers.html`, `textarea.antlers.html`, `integer.antlers.html`, and `default.antlers.html` use):

```antlers
{{ if visibility == 'read_only' && calculation_query !== true }}
    readonly
    aria-readonly="true"
{{ /if }}
```

### Composites and partials

If your fieldtype is a composite (multiple inputs per field — like `address`, `name`, `partial_date`, `person_name`, `text_phonetic_pair`, `list`), apply the appropriate attribute to **every** user-facing input/select/textarea inside the composite. The field-level `visibility` value is in scope inside the composite template and inside any partial called from it (Antlers cascades scope), so you don't need to thread `visibility` as an explicit partial parameter — just reference `{{ visibility }}` directly.

For composites with controls that **mutate the field's shape** (add-row / remove-row buttons on `list`), prefer to hide those controls entirely with `{{ unless visibility == 'read_only' }} … {{ /unless }}` rather than disabling them. A read-only `list` should show the existing rows without giving the user a way to grow or shrink the list.

---

## Per-fieldtype attribute matrix

Current state of the tree, kept in sync with the templates. If you change attribute treatment in a template, update this row.

| Fieldtype template | Delegates to | Attribute(s) for `read_only` |
|---|---|---|
| `assets.antlers.html` | `files.antlers.html` | `disabled` (transitively) |
| `address.antlers.html` | — | `readonly` on each text input; `disabled` on each `<select>` (state / country) |
| `checkboxes.antlers.html` | — | `disabled` on every option checkbox |
| `date.antlers.html` (single mode) | `default.antlers.html` | `readonly` (transitively) |
| `date.antlers.html` (range mode) | — | `readonly` on both start and end inputs |
| `default.antlers.html` | — | `readonly` |
| `dictionary.antlers.html` | `select.antlers.html` | `disabled` (transitively) |
| `email.antlers.html` | `default.antlers.html` | `readonly` (transitively) |
| `files.antlers.html` | — | `disabled` (`<input type=file readonly>` is invalid) |
| `float.antlers.html` | `default.antlers.html` | `readonly` (transitively) |
| `hidden.antlers.html` | — | none (`<input type=hidden>` is not user-editable by definition) |
| `html.antlers.html` | — | none (no form controls) |
| `integer.antlers.html` | — | `readonly` |
| `list.antlers.html` | — | `readonly` on each text input; add/remove buttons are hidden via `{{ unless visibility == 'read_only' }}` |
| `name.antlers.html` | — | `readonly` on each subfield text input |
| `option.antlers.html` | — | `disabled` on the drop-down `<select>`, every radio, and every checkbox. The hidden `[line_total]` input has no editable control. |
| `partial_date.antlers.html` | — | `readonly` on year/day number inputs; `disabled` on month `<select>` |
| `password.antlers.html` | `default.antlers.html` | `readonly` (transitively) |
| `person_name.antlers.html` | — | `disabled` on the no-* checkboxes and on the generational-suffix / display-format selects; text inputs are read-only via `_person_name_pair.antlers.html` |
| `_person_name_pair.antlers.html` | — | `readonly` on both text and phonetic-text inputs |
| `phone.antlers.html` | `default.antlers.html` | `readonly` (transitively) |
| `product.antlers.html` | — | `disabled` on the multiple-choice `<select>`; `readonly` on the quantity number input (single & multiple). The single base-price `<p>` and hidden `[line_total]` / `[quantity]` inputs have no editable control. |
| `radio.antlers.html` | — | `disabled` on every option radio |
| `section.antlers.html` | — | none (structural / no form controls) |
| `select.antlers.html` | — | `disabled` |
| `spacer.antlers.html` | — | none (structural / no form controls) |
| `text.antlers.html` | — | `readonly` |
| `text_phonetic_pair.antlers.html` | — | `readonly` on both inputs |
| `textarea.antlers.html` | — | `readonly` |
| `time.antlers.html` | `default.antlers.html` | `readonly` (transitively) |
| `toggle.antlers.html` | — | `disabled` on the visible checkbox (the hidden `value="0"` companion input is unaffected and submits `0`) |
| `url.antlers.html` | `default.antlers.html` | `readonly` (transitively) |
| `username.antlers.html` | `default.antlers.html` | `readonly` (transitively) |
| `_width.antlers.html` | — | none (utility partial that emits class names; no form controls) |

---

## Adding a new fieldtype override — checklist

When you add a new file to this directory:

1. **Decide which attribute applies** to each control inside your template using the table in the "Attribute choice" section above.
2. **Emit the attribute** using one of the standard patterns above. Place the read-only block right after the existing `required` attribute pipe so state attributes stay grouped.
3. **Update the per-fieldtype matrix** in the section above so future maintainers can see what's expected without re-reading every template.
4. **Add a test case** to [`tests/Feature/Views/FormsFieldsVisibilityReadOnlyTest.php`](../../../../../../tests/Feature/Views/FormsFieldsVisibilityReadOnlyTest.php) that asserts the new template emits the right attribute when `visibility: read_only` is set, and does **not** emit it when `visibility` is unset or `visible`.
5. **If the fieldtype delegates to another partial** (`{{ partial:vendor/statamic/forms/fields/<other> }}`), you do *not* need to repeat the read-only emission in your template — but mark it as "delegates" in the matrix above and trust that the partial's coverage extends to you.
6. **If the fieldtype is structural** (no editable form controls — like `section`, `spacer`, `html`), do nothing in the template and document it as `none` in the matrix.

---

## References

- Issue: [#726](https://github.com/UAMS-Web/uams-statamic-archive/issues/726) — *Render visibility: read_only form fields as disabled/readonly on public forms*
- Parent ticket: [#86](https://github.com/UAMS-Web/uams-statamic-archive/issues/86)
- Visibility-hidden precedent: [#725](https://github.com/UAMS-Web/uams-statamic-archive/pull/725) and [`../fields.antlers.html`](../fields.antlers.html)
- Statamic Field visibility source: `vendor/statamic/cms/src/Fields/Field.php::visibility()`
- Test that enforces this contract: [`tests/Feature/Views/FormsFieldsVisibilityReadOnlyTest.php`](../../../../../../tests/Feature/Views/FormsFieldsVisibilityReadOnlyTest.php)
- Hidden-visibility companion test: [`tests/Feature/Views/FormsFieldsVisibilityHiddenTest.php`](../../../../../../tests/Feature/Views/FormsFieldsVisibilityHiddenTest.php)
