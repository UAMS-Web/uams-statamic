<!--
 * Visual/layout shell matching the non-portal subtree of Statamic’s
 * `GroupFieldtype.vue`: `<section>` → bordered container → inner padding
 * (mirrors `Fields` with `pt-4` and conditional `px-4 py-4` when `border` is true).
 * Does not include portal, fullscreen header, or `FieldsProvider`.
 -->
<template>
	<section>
		<div :class="outerClass">
			<div :class="innerClass">
				<div
					class="min-w-0"
					:class="bodyClass"
				>
					<slot />
				</div>
			</div>
		</div>
	</section>
</template>

<script>
export default {
	name: 'GroupFieldtypeShell',
	props: {
		/**
		 * When true, apply the same bordered card as `config.border` in Group
		 * (white surface, rounded, border; dark mode variants).
		 */
		border: {
			type: Boolean,
			default: true
		},
		/**
		 * Classes for the slot wrapper (default vertical stack for subfields).
		 */
		bodyClass: {
			type: [String, Array, Object],
			default: 'space-y-6'
		}
	},
	computed: {
		outerClass() {
			return this.border
				? 'bg-white dark:bg-gray-800 dark:border-gray-900 rounded-lg border'
				: '';
		},
		innerClass() {
			return [
				'pt-4',
				this.border ? 'px-4 py-4' : ''
			].filter(Boolean).join(' ');
		}
	}
};
</script>
