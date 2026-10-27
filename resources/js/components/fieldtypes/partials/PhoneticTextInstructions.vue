<!--
 * Shared CP alert: IPA / phonetic text guidance for fieldtypes that collect
 * phonetic transcription (PersonName, TextPhoneticPair, etc.).
 -->
<template>
	<Alert :heading="__('statamic::fieldtypes.phonetic_instructions.alert_heading')">
		<Heading>{{ __('statamic::fieldtypes.phonetic_instructions.alert_heading') }}</Heading>
		<Description>
			<p>{{ __('statamic::fieldtypes.phonetic_instructions.p1') }}</p>
			<p v-html="__('statamic::fieldtypes.phonetic_instructions.p2_html')" />
			<p v-html="__('statamic::fieldtypes.phonetic_instructions.p3_html')" />
			<p v-html="__('statamic::fieldtypes.phonetic_instructions.p4_html')" />
			<p v-html="__('statamic::fieldtypes.phonetic_instructions.p5_html')" />
		</Description>
	</Alert>
</template>

<script>
import { Alert, Description, Heading } from '@statamic/cms/ui';

export default {
	name: 'PhoneticTextInstructions',
	components: {
		Alert,
		Description,
		Heading
	}
};
</script>
