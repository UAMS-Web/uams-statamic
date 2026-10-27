<!--
 * Custom fieldtype for structured address (street, locality, region, etc.).
 * Not a fork of a Statamic component; delegates to has-subfields with value,
 * meta, and handle.
 -->
<template>
	<has-subfields
		:value="value"
		:meta="meta"
		:handle="handle"
		@update:value="$emit('update:value', $event)"
	/>
</template>

<script>
// Kept in Options API: depends on FieldtypeMixin (this.update(), value/config/meta).
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {
	mixins: [Fieldtype],
	setup() {
		return {};
	}
};
</script>
