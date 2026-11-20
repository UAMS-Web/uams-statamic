/**
 * Public-form PersonName reactive engine.
 *
 * The `person_name` fieldtype, its public-form view and the display-format
 * reactivity live in `uams-web/aether-people` (#696). The add-on's own Vite
 * build loads only in the Control Panel, so this module registers the add-on's
 * public-form handler with the site bundle's engine lifecycle
 * (the fieldtype add-on's `alpine.js`) and hands it this bundle's Alpine instance, so the
 * add-on never brings a second copy of Alpine into the site bundle.
 *
 * The handler recomputes the display-format <select> options as the name parts
 * change, with the same `buildDisplayFormatOptions` module the Control Panel
 * component uses, so both surfaces offer the same set.
 */

// The CSP build, matching `site.js`: attribute expressions are AST-interpreted
// rather than compiled through `new Function()`, so `script-src` needs no
// `unsafe-eval` (UAMS-Web/uams-statamic-archive#1859). The constraint is on `x-*` attributes, not on modules.
import Alpine from '@alpinejs/csp';
import { registerAlpineEngine } from '../../vendor/uams-web/aether-fieldtypes/resources/js/alpine.js';
import {
	PERSON_NAME_BOUND_ATTR,
	PERSON_NAME_SELECTOR,
	initPersonNameDisplayFormat,
} from '../../vendor/uams-web/aether-people/resources/js/forms/personNameDisplayFormatField.js';

/**
 * Public entry. Lifecycle handled by the fieldtype add-on's `alpine.js`.
 */
export function registerPersonNameEngine() {
	registerAlpineEngine({
		selector: PERSON_NAME_SELECTOR,
		boundAttr: PERSON_NAME_BOUND_ATTR,
		init: (select) => initPersonNameDisplayFormat(select, Alpine),
	});
}
