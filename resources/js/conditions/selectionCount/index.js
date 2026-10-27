import { registerCustomCondition } from '../registerCustomCondition.js';
import { evaluateSelectionCount } from './evaluateSelectionCount.js';

/**
 * Compare the number of selected options in the target field using an operator.
 *
 * Usage:
 * ```yaml
 * if:
 *   enabled_configurations: 'custom selectionCount:gt,1'
 * ```
 *
 * Operators: gt, gte, lt, lte, equals (equal, eq), not (notequal, neq), between.
 */
// Register the custom condition name expected after `custom` in blueprint strings
registerCustomCondition('selectionCount', evaluateSelectionCount);
