import { registerCustomCondition } from '../registerCustomCondition.js';
import { evaluateRoleTitleDefaultConfigurationVisible } from './evaluateRoleTitleDefaultConfigurationVisible.js';

/**
 * Usage:
 * ```yaml
 * if:
 *   enabled_title_structures: 'custom roleTitleDefaultConfigurationVisible'
 * ```
 */
registerCustomCondition(
	'roleTitleDefaultConfigurationVisible',
	evaluateRoleTitleDefaultConfigurationVisible
);
