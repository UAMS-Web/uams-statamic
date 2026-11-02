/**
 * Custom Statamic Field Conditions
 *
 * Condition implementations live under `resources/js/conditions/` (one concern per
 * file). This entry is included by `cp.js` and `site.js`.
 *
 * HOW TO ADD A NEW CONDITION
 *
 *   1. Add `resources/js/conditions/myCondition.js` (or a folder with `index.js`) that
 *      imports `registerCustomCondition` from `./registerCustomCondition.js` and calls
 *      it.
 *   2. Import that file below (side effect = registration).
 *   3. Use in blueprints: `if: { field_name: 'custom myCondition:param1,param2' }`
 *
 *   Built-in inverses implemented here: `notContains` (inverse of `contains`), and
 *   `notContainsAny` (inverse of `contains_any`).
 *
 * WHY registerCustomCondition() INSTEAD OF Statamic.$conditions.add() ONLY
 *
 * `Statamic.$conditions.add` exists only in the Control Panel, where the CP
 * bootstraps it over the Vuex store. Public pages have no `Statamic` global at
 * all since UAMS-Web/uams-statamic-archive#1956 dropped the vendor bundle, so registration also writes
 * `window.StatamicCustomConditions` — the registry our own evaluator reads.
 *
 * Until UAMS-Web/uams-statamic-archive#1956 a `frontendShowFieldPatch.js` shimmed `add`/`get` onto
 * `Statamic.$conditions` so the VENDOR Validator could dispatch to these. That
 * Validator no longer runs on public pages, and the CP supplies its own `add`,
 * so the shim served neither path and was removed with this change.
 *
 * @see vendor/uams-web/aether-alpine-csp-forms/resources/js/conditions/validator.js
 *      getCustomCondition(), which reads that registry
 * @see https://statamic.dev/conditional-fields#custom-logic
 */

// Load `isEmptyOrValue` (self-registers on import)
import './conditions/isEmptyOrValue.js';
// Load `isNotEmpty` (self-registers on import)
import './conditions/isNotEmpty.js';
// Load `selectionCount` and related helpers (self-registers on import)
import './conditions/selectionCount/index.js';
// Load `selectionContains` (self-registers on import)
import './conditions/selectionContains/index.js';
// Load `notContains` and `notContainsAny` (self-registers on import)
import './conditions/notContains/index.js';
import './conditions/notContainsAny/index.js';
// Load role_title blueprint editor helpers (self-registers on import)
import './conditions/roleTitleDefaultConfigurationVisible/index.js';
// Load `parkingVisible` (location parking leaf-field visibility; self-registers on import)
import './conditions/parkingVisible/index.js';
// Load `parentOverride` (clinical_locations per-child address/parking override; self-registers on import)
import './conditions/parentOverride/index.js';
// Load `articleVisible` (clinical resource Article Content visibility; self-registers on import)
import './conditions/articleVisible.js';
// Load `notSyndicated` (clinical resource Article local-authoring section visibility; self-registers on import)
import './conditions/notSyndicated.js';
