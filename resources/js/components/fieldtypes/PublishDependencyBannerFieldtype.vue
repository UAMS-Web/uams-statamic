<!--
 * CP banner for App\Fieldtypes\PublishDependencyBanner (UAMS-Web/uams-statamic-archive#1593), the Control-Panel
 * signal half of the opt-in publish-dependency mechanism (mirroring
 * OrphanBannerFieldtype).
 *
 * Renders a warning Alert on the publish form when the entry is suppressed because
 * its declared `publish_depends_on` source(s) are down (so its live page redirects
 * and it is hidden from listings), naming those source(s) and the redirect
 * destination, and nothing otherwise. The suppressed state, source names, redirect
 * destination, and policy are computed server-side in
 * PublishDependencyBanner::preload() and read from `meta` — this component only
 * displays them. The editor restores the entry by editing or clearing its tether.
 -->
<template>
	<Alert v-if="isSuppressed" variant="warning">
		<div class="text-sm space-y-1">
			<p class="font-medium">{{ __('This page is suppressed on the live site.') }}</p>
			<p>
				<template v-if="requireAll">{{ __('Every entry it depends on is unpublished or deleted') }}</template>
				<template v-else>{{ __('An entry it depends on is unpublished or deleted') }}</template>
				<template v-if="sourceTitles.length">
					(<span class="font-medium">{{ sourceList }}</span>)</template>,
				{{ __('so it is redirected and hidden from listings. Re-publish the source, or clear the "Publish Depends On" field, to restore this page.') }}
			</p>
			<p v-if="redirectUrl">
				{{ __('Visitors are sent to') }}:
				<a :href="redirectUrl" class="underline" target="_blank" rel="noopener">{{ redirectUrl }}</a>
			</p>
		</div>
	</Alert>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Alert } from '@statamic/cms/ui';

export default {
	name: 'PublishDependencyBannerFieldtype',

	components: { Alert },

	mixins: [Fieldtype],

	computed: {
		/** @returns {boolean} Whether the entry is suppressed by its down sources. */
		isSuppressed() {
			return Boolean(this.meta?.suppressed);
		},

		/** @returns {boolean} Whether suppression required ALL sources down (AND policy). */
		requireAll() {
			return this.meta?.require_all !== false;
		},

		/** @returns {string[]} The down sources' display names. */
		sourceTitles() {
			return Array.isArray(this.meta?.source_titles) ? this.meta.source_titles : [];
		},

		/** @returns {string} The down sources joined for display. */
		sourceList() {
			return this.sourceTitles.join(', ');
		},

		/** @returns {string|null} The live-site redirect destination, when suppressed. */
		redirectUrl() {
			return this.meta?.redirect_url ?? null;
		},
	},
};
</script>
