<template>
	<div
		class="flex flex-col space-y-3 p-1.5 bg-gray-100 border border-gray-300 dark:bg-gray-900 dark:border-gray-700 rounded-xl"
	>
		<ui-input-group>
			<ui-input-group-prepend :text="__('URL')" />
			<ui-input
				:model-value="value"
				:isReadOnly="isReadOnly"
				:placeholder="__(config.placeholder) || 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'"
				:aria-label="__('Video URL')"
				@update:model-value="update"
				@focus="$emit('focus')"
				@blur="$emit('blur')"
				input-class="border-s-0"
			/>
		</ui-input-group>
		<ui-description v-if="isInvalid" class="text-red-600">{{ __('statamic::validation.url') }}</ui-description>
		<iframe
			v-if="shouldShowIframePreview"
			ref="iframe"
			:src="isVisible ? embedUrl : null"
			frameborder="0"
			allow="fullscreen"
			class="aspect-video rounded-lg"
			loading="lazy"
		></iframe>
		<video
			v-else-if="shouldShowDirectFilePreview"
			ref="directVideo"
			:src="isVisible ? directFileSrc : null"
			controls
			playsinline
			preload="metadata"
			class="aspect-video w-full rounded-lg bg-black"
		></video>
	</div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { videoEmbedUrl } from '../../lib/video-embed-url.js';

/**
 * Fork of Statamic `VideoFieldtype.vue` with a host-agnostic preview fix:
 * direct file URLs (same extension heuristic as core `isVideo`) use a native
 * `<video>` element instead of an `<iframe>`, avoiding spurious downloads from
 * hosts that serve the bytes as a file (Box, object storage, CDNs, etc.).
 *
 * The embed URL rewrite lives in `resources/js/lib/video-embed-url.js`.
 * Keep in sync with core after Statamic upgrades; see `App\Fieldtypes\Video`.
 */
export default {
	mixins: [Fieldtype],

	data() {
		return {
			isVisible: false,
			observer: null,
		};
	},

	computed: {
		shouldShowIframePreview() {
			return !this.isInvalid && this.isEmbeddable;
		},

		shouldShowDirectFilePreview() {
			return !this.isInvalid && this.isVideo;
		},

		embedUrl() {
			return videoEmbedUrl(this.value);
		},

		directFileSrc() {
			const url = this.value || '';

			return url === '' ? null : url;
		},

		isEmbeddable() {
			const url = this.value || '';
			const isYoutube = url.includes('youtube') || url.includes('youtu.be');
			const isVimeo = url.includes('vimeo');

			return isYoutube || isVimeo;
		},

		isInvalid() {
			let htmlRegex = new RegExp(/<([A-Z][A-Z0-9]*)\b[^>]*>.*?<\/\1>|<([A-Z][A-Z0-9]*)\b[^\/]*\/>/i);

			return htmlRegex.test(this.value || '');
		},

		isUrl() {
			const url = this.value || '';

			return url.startsWith('http://') || url.startsWith('https://');
		},

		isVideo() {
			const url = this.value || '';
			const isVideo =
				url.includes('.mp4') || url.includes('.ogv') || url.includes('.mov') || url.includes('.webm');

			return !this.isEmbeddable && isVideo;
		},
	},

	mounted() {
		this.observer = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting && entry.intersectionRatio > 0) {
						this.isVisible = true;
						this.observer.disconnect();
					}
				});
			},
			{ threshold: 0.01 }
		);

		if (this.$el) {
			this.observer.observe(this.$el);
		}
	},

	beforeUnmount() {
		if (this.observer) {
			this.observer.disconnect();
		}
	},
};
</script>
