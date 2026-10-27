import { registerCustomCondition } from '../registerCustomCondition.js';
import { evaluateNotContains } from './evaluateNotContains.js';

/**
 * Inverse of `contains` / `includes` (see Statamic `passesIncludesCondition`).
 *
 * Usage:
 * ```yaml
 * if:
 *   ctdl.ceterms_type: 'custom notContains:foo'
 * ```
 */
registerCustomCondition('notContains', evaluateNotContains);
