<!--
 * CP banner for App\Fieldtypes\OrphanBanner (UAMS-Web/uams-statamic-archive#1429), the universal Control-Panel
 * signal half of the orphaned-ancestor mechanism (generalizing UAMS-Web/uams-statamic-archive#1077's
 * KbOrphanBannerFieldtype across every routable + hierarchical collection).
 *
 * Renders a warning Alert on the publish form when the entry is orphaned by an
 * unpublished structural ancestor (so its live page redirects), naming that
 * ancestor and the redirect destination, and nothing otherwise. The orphaned
 * state, ancestor name, and redirect destination are computed server-side in
 * OrphanBanner::preload() and read from `meta` — this component only displays them.
 -->
<template>
	<Alert v-if="isOrphaned" variant="warning">
		<div class="text-sm space-y-1">
			<p class="font-medium">{{ __('This page redirects on the live site.') }}</p>
			<p v-if="ancestorTitle">
				{{ __('A parent entry') }} (<span class="font-medium">{{ ancestorTitle }}</span>)
				{{ __('is unpublished, so its published descendants are redirected. Re-publish the ancestor to restore this page.') }}
			</p>
			<p v-else>
				{{ __('A parent entry is unpublished, so its published descendants are redirected. Re-publish the ancestor to restore this page.') }}
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
	name: 'OrphanBannerFieldtype',

	components: { Alert },

	mixins: [Fieldtype],

	computed: {
		/** @returns {boolean} Whether the entry is orphaned by an unpublished ancestor. */
		isOrphaned() {
			return Boolean(this.meta?.orphaned);
		},

		/** @returns {string|null} The unpublished ancestor's title, when known. */
		ancestorTitle() {
			return this.meta?.ancestor_title ?? null;
		},

		/** @returns {string|null} The live-site redirect destination, when orphaned. */
		redirectUrl() {
			return this.meta?.redirect_url ?? null;
		},
	},
};
</script>
