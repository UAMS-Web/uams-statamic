# Control Panel Combobox smoke checklist

Run after upgrading `statamic/cms` when [Combobox 2.0](https://github.com/statamic/cms/issues/13843) (or follow-on combobox fixes) ships. Goal: confirm long labels, large lists, and keyboard use still behave acceptably in **custom** CP fieldtypes that use `Combobox` from `@statamic/cms/ui`.

## Preconditions

- `npm run cp:build` (or `npm run dev:with-cp`) succeeds.

- Log into the CP with a user that can edit entries or globals that expose the fieldtypes below (use real content with long labels where possible).

## Schema.org and taxonomy pickers

- **Schema type** (`SchemaTypeFieldtype`): single mode and chained mode; open each combobox, confirm long type names are readable (`adaptive-width` is on for `schema-org-type-combobox`).

- **Schema object** (`SchemaObjectFieldtype`): type selector with many types; scroll list (max height), select and clear.

- **CTDL class** (`CtdlClassFieldtype`): each tier combobox; “None” row if applicable; long class labels (`adaptive-width` on `<Combobox>`).

- **ISCO-08** (`Isco08Fieldtype`): same as CTDL where options are long (`adaptive-width` on `<Combobox>`).

## Dates, forms, and subfields

- **Partial date** (`PartialDateFieldtype`): month/day/year comboboxes, hierarchy-locked state if you use it.

- **Form email field select** (`FormEmailFieldSelectFieldtype`): pick from list, blur after close (`adaptive-width` on `<Combobox>`).

- **Has subfields** (`HasSubfields.vue`): combobox-driven subfields; search and select.

## Conditions and routing

- **Field conditions** (`field-conditions/Condition.vue`): field and value comboboxes (`adaptive-width` on both).

- **Email routing condition** (`EmailRoutingCondition.vue`): same pattern as field conditions (`adaptive-width` on both comboboxes).

## Person name

- **Generational suffix** (`PersonNameGenerationalSuffix.vue`): select and clear.

- **Display format** (`PersonNameDisplayFormatSelect.vue`): select option with long label if present (`adaptive-width` on `<Combobox>`).

## Regression spotlights (from upstream release notes)

- **Long options**: schema, CTDL, ISCO, conditions, email routing, form email field select, and display format comboboxes already use `adaptive-width`; elsewhere, add it if truncation is a problem.

- **Many options**: list scrolls inside ~300px panel; selected item scrolls into view when opened.

- **Search**: filtered options stay in stable order; closing clears search.

- **Keyboard**: open with keyboard, arrow keys, Enter to choose, Space in search input inserts a space.

- **Relationship-style flows** (if you embed similar patterns): click option after partial typeahead does not pick the wrong value.

Record pass/fail and Statamic version on the PR or release ticket.
