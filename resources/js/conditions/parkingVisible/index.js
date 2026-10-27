import { registerCustomCondition } from '../registerCustomCondition.js';
import { evaluateParkingVisible } from './evaluateParkingVisible.js';

/**
 * Location `parking_information` leaf-field visibility (nested AND/OR).
 *
 * Usage:
 * ```yaml
 * if:
 *   facility_query: 'custom parkingVisible:facility'
 * ```
 *
 * Roles: `facility`, `instructions`, `directions`. See
 * `evaluateParkingVisible.js` for the per-role logic.
 */
registerCustomCondition('parkingVisible', evaluateParkingVisible);
