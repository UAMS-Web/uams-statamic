<!--
 * Schema.org docs callout: wraps Statamic Alert for type/MTE summaries or a
 * property instruction + learn-more badge (callout variant).
 -->
<template>
	<Alert v-if="isShown" variant="default" :class="alertClass">
		<template v-if="variant === 'callout'">
			<p
				v-if="decoratedCalloutDescription"
				:class="calloutParagraphClass"
				v-html="decoratedCalloutDescription"
			></p>
			<Badge
				v-if="calloutBadgeText && docUrl"
				:text="calloutBadgeText"
				color="blue"
				:class="calloutBadgeClass"
				icon-append="external-link"
				:href="docUrl"
				target="_blank"
				pill
			/>
		</template>
		<template v-else-if="typesLayout === 'single'">
			<h2 class="text-lg">{{ normalizedItems[0] }}</h2>
			<p
				v-if="descriptionFor(normalizedItems[0])"
				class="schema-description mt-2 text-sm text-gray-600 dark:text-gray-400"
				v-html="descriptionDecoratedFor(normalizedItems[0])"
			></p>
			<Badge
				:text="learnAboutTypeLabel(normalizedItems[0])"
				color="blue"
				icon-append="external-link"
				:href="schemaOrgUrl(normalizedItems[0])"
				target="_blank"
				pill
			/>
		</template>
		<template v-else-if="typesLayout === 'multi'">
			<h2 class="text-lg">{{ multiEntityTitle }}</h2>
			<div
				v-for="t in normalizedItems"
				:key="t"
				class="mt-3"
			>
				<h3 class="text-base font-medium text-gray-925 dark:text-gray-300">{{ t }}</h3>
				<p
					v-if="descriptionFor(t)"
					class="schema-description text-sm text-gray-600 dark:text-gray-400"
					v-html="descriptionDecoratedFor(t)"
				></p>
				<Badge
					:text="learnAboutTypeLabel(t)"
					color="blue"
					icon-append="external-link"
					:href="schemaOrgUrl(t)"
					target="_blank"
					pill
				/>
			</div>
		</template>
		<template v-else-if="typesLayout === 'stacked'">
			<div
				v-for="(t, idx) in normalizedItems"
				:key="t"
				:class="idx === 0 ? '' : 'mt-3'"
			>
				<h3 class="text-base font-medium text-gray-925 dark:text-gray-300">{{ t }}</h3>
				<p
					v-if="descriptionFor(t)"
					class="schema-description text-sm text-gray-600 dark:text-gray-400"
					v-html="descriptionDecoratedFor(t)"
				></p>
				<Badge
					:text="learnAboutTypeLabel(t)"
					color="blue"
					icon-append="external-link"
					:href="schemaOrgUrl(t)"
					target="_blank"
					pill
				/>
			</div>
		</template>
	</Alert>
</template>

<script>
import { Alert, Badge } from '@statamic/cms/ui';

export default {
	name: 'SchemaOrgDocsAlert',

	components: { Alert, Badge },

	props: {
		/**
		 * `types`: one or more type/member names with optional MTE layout.
		 * `callout`: instruction HTML + badge (e.g. schema property row).
		 */
		variant: {
			type: String,
			default: 'types',
			validator: (v) => ['types', 'callout'].includes(v),
		},
		/** Type or member names (types variant). */
		items: {
			type: Array,
			default: () => [],
		},
		/** Map of name => description HTML from fieldtype preload (types variant). */
		descriptionsByName: {
			type: Object,
			default: () => ({}),
		},
		/** Translated MTE heading when multiple items use multi layout. */
		multiEntityTitle: {
			type: String,
			default: '',
		},
		/**
		 * When true and more than one item, show Multi Typed Entity heading + blocks.
		 * When false and more than one item, show stacked blocks without that heading.
		 */
		useMultiEntityWhenMultiple: {
			type: Boolean,
			default: true,
		},
		/** Raw description HTML before anchor classes (callout variant). */
		rawDescriptionHtml: {
			type: String,
			default: undefined,
		},
		/** schema.org URL for the callout badge. */
		docUrl: {
			type: String,
			default: '',
		},
		/** Badge label (callout variant). */
		badgeText: {
			type: String,
			default: '',
		},
		alertClass: {
			type: String,
			default: 'mt-3',
		},
		calloutParagraphClass: {
			type: String,
			default: 'schema-description mb-1.5 text-sm text-gray-500 dark:text-gray-400',
		},
		calloutBadgeClass: {
			type: String,
			default: '',
		},
	},

	computed: {
		normalizedItems() {
			if (!Array.isArray(this.items)) {
				return [];
			}

			return this.items.filter((t) => typeof t === 'string' && t !== '');
		},

		decoratedCalloutDescription() {
			if (!this.rawDescriptionHtml || typeof this.rawDescriptionHtml !== 'string') {
				return '';
			}

			return this.applyLinkClasses(this.rawDescriptionHtml);
		},

		calloutBadgeText() {
			return typeof this.badgeText === 'string' ? this.badgeText : '';
		},

		typesLayout() {
			if (this.variant !== 'types') {
				return null;
			}
			const n = this.normalizedItems.length;
			if (n === 0) {
				return null;
			}
			if (n === 1) {
				return 'single';
			}

			return this.useMultiEntityWhenMultiple ? 'multi' : 'stacked';
		},

		isShown() {
			if (this.variant === 'callout') {
				return !!(this.decoratedCalloutDescription || (this.calloutBadgeText && this.docUrl));
			}

			return this.typesLayout !== null;
		},
	},

	methods: {
		applyLinkClasses(html) {
			if (!html || typeof html !== 'string') {
				return '';
			}
			const descriptionLinkClass =
				'underline text-blue-700 dark:text-blue-300 schema-description-link';

			return html.replace(/<a\s/g, `<a class="${descriptionLinkClass}" `);
		},

		descriptionFor(name) {
			const d = this.descriptionsByName || {};

			return d[name] ?? null;
		},

		descriptionDecoratedFor(name) {
			const raw = this.descriptionFor(name);

			return raw ? this.applyLinkClasses(raw) : '';
		},

		schemaOrgUrl(name) {
			return `https://schema.org/${encodeURIComponent(name)}`;
		},

		learnAboutTypeLabel(typeValue) {
			let p = this.$parent;
			while (p) {
				if (typeof p.__ === 'function') {
					return p.__('Learn about the ":type" type', { type: typeValue });
				}
				p = p.$parent;
			}

			return `Learn about the "${typeValue}" type`;
		},
	},
};
</script>

<style scoped>
.schema-description :deep(.schema-description-link:hover) {
	text-decoration: none;
}
</style>
