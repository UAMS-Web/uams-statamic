import { registerCustomCondition } from '../registerCustomCondition.js';
import { evaluateParentOverride } from './evaluateParentOverride.js';

/**
 * `clinical_locations` per-child override visibility (UAMS-Web/uams-statamic-archive#1269).
 *
 * Usage:
 * ```yaml
 * if:
 *   parent: 'custom parentOverride:address'
 * ```
 *
 * Roles: `address`, `parking`, and the address groups `street`, `facility`, `unit` and
 * `map` (UAMS-Web/uams-statamic-archive#2608). See `evaluateParentOverride.js` for the per-role logic.
 */
registerCustomCondition('parentOverride', evaluateParentOverride);
