import { registerCustomCondition } from '../registerCustomCondition.js';
import { evaluateNotContainsAny } from './evaluateNotContainsAny.js';

/**
 * Inverse of `contains_any` (see `passesContainsAnyCondition`).
 *
 * Usage:
 * ```yaml
 * if:
 *   ctdl.ceterms_type: 'custom notContainsAny:Occupation,WorkRole'
 * ```
 */
registerCustomCondition('notContainsAny', evaluateNotContainsAny);
