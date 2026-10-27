<!--
 * Control-panel component for App\Fieldtypes\Total — the Gravity Forms
 * `total`-field analog (UAMS-Web/uams-statamic-archive#488).
 *
 * The order total is a runtime concern of the public form (there is no live
 * order to sum inside the CP), so this component is a read-only display of the
 * stored/advisory total. The live sum on the public form is produced by
 * resources/js/total-pricing-engine.js. Built directly on FieldtypeMixin,
 * mirroring the sibling Product/Option components.
 -->
<template>
	<div class="total-fieldtype text-sm font-medium text-gray-900 dark:text-gray-200" data-total-summary>
		<span v-if="prefix" class="font-semibold">{{ prefix }}: </span>
		<span>{{ formattedValue }}</span>
	</div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {
	name: 'TotalFieldtype',

	mixins: [Fieldtype],

	computed: {
		/** @returns {string} The configured amount prefix (from Total::preload()). */
		prefix() {
			return this.meta?.prefix ?? '';
		},

		/** @returns {string} The stored total formatted as US currency. */
		formattedValue() {
			const parsed = parseFloat(this.value);
			const amount = Number.isFinite(parsed) ? parsed : 0;
			const sign = amount < 0 ? '-' : '';
			return `${sign}$${Math.abs(amount).toFixed(2)}`;
		},
	},
};
</script>
