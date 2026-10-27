<template>
	<div
		class="flex flex-col space-y-3 p-1.5 bg-gray-100 border border-gray-300 dark:bg-gray-900 dark:border-gray-700 rounded-xl"
	>
		<ui-input-group>
			<ui-input-group-prepend :text="__('URL')" />
			<ui-input
				:model-value="value"
				:isReadOnly="isReadOnly"
				:placeholder="__(config.placeholder) || 'https://example.com/audio.mp3'"
				:aria-label="__('Audio URL')"
				@update:model-value="update"
				@focus="$emit('focus')"
				@blur="$emit('blur')"
				input-class="border-s-0"
			/>
		</ui-input-group>
		<ui-description v-if="isInvalid" class="text-red-600">{{ __('statamic::validation.url') }}</ui-description>
		<audio
			v-if="shouldShowDirectFilePreview"
			ref="directAudio"
			:src="isVisible ? directFileSrc : null"
			controls
			preload="metadata"
			class="w-full"
		></audio>
	</div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

/**
 * Audio fieldtype control-panel component (UAMS-Web/uams-statamic-archive#590), a sibling of the custom
 * `VideoFieldtype.vue` fork: a URL input plus a native `<audio controls>` preview
 * for direct audio file URLs. v1 is direct-file only — there is no third-party
 * embed, so (unlike the Video fork) there is no iframe branch. See
 * `App\Fieldtypes\Audio`.
 *
 * The `<audio>` src is deferred until the field scrolls into view (an
 * IntersectionObserver), so off-screen fields never fetch audio bytes.
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
		shouldShowDirectFilePreview() {
			return !this.isInvalid && this.isAudio;
		},

		directFileSrc() {
			const url = this.value || '';

			return url === '' ? null : url;
		},

		isInvalid() {
			let htmlRegex = new RegExp(/<([A-Z][A-Z0-9]*)\b[^>]*>.*?<\/\1>|<([A-Z][A-Z0-9]*)\b[^\/]*\/>/i);

			return htmlRegex.test(this.value || '');
		},

		isAudio() {
			const url = (this.value || '').toLowerCase();

			return (
				url.includes('.mp3') ||
				url.includes('.ogg') ||
				url.includes('.oga') ||
				url.includes('.wav') ||
				url.includes('.m4a') ||
				url.includes('.aac') ||
				url.includes('.flac') ||
				url.includes('.weba')
			);
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
