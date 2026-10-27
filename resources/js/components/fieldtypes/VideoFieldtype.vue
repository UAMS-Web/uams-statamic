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

/**
 * Fork of Statamic `VideoFieldtype.vue` with a host-agnostic preview fix:
 * direct file URLs (same extension heuristic as core `isVideo`) use a native
 * `<video>` element instead of an `<iframe>`, avoiding spurious downloads from
 * hosts that serve the bytes as a file (Box, object storage, CDNs, etc.).
 *
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
			let embed_url = this.value || '';

			if (embed_url.includes('youtube')) {
				embed_url = embed_url.includes('shorts/')
					? embed_url.replace('shorts/', 'embed/')
					: embed_url.replace('watch?v=', 'embed/');
			}

			if (embed_url.includes('youtu.be')) {
				embed_url = embed_url.replace('youtu.be', 'www.youtube.com/embed');
			}

			if (embed_url.includes('vimeo')) {
				embed_url = embed_url.replace('/vimeo.com', '/player.vimeo.com/video');

				if (!this.value.includes('progressive_redirect') && embed_url.split('/').length > 5) {
					let hash = embed_url.substr(embed_url.lastIndexOf('/') + 1);
					embed_url = embed_url.substr(0, embed_url.lastIndexOf('/')) + '?h=' + hash.replace('?', '&');
				}
			}

			if (embed_url.includes('&') && !embed_url.includes('?')) {
				embed_url = embed_url.replace('&', '?');
			}

			return embed_url;
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
