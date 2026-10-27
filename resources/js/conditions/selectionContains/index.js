import { registerCustomCondition } from '../registerCustomCondition.js';
import { evaluateSelectionContains } from './evaluateSelectionContains.js';

/**
 * Whether a field value (checkboxes array) includes a given option.
 *
 * Usage:
 * ```yaml
 * if:
 *   enabled_title_structures: 'custom selectionContains:structured'
 * ```
 */
registerCustomCondition('selectionContains', evaluateSelectionContains);
