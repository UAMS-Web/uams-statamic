<script setup>
import { Link, Head } from '@statamic/cms/inertia';
import { Header, Subheading, Panel, Icon } from '@statamic/cms/ui';

defineProps({
    collections: Array,
    taxonomies: Array,
});
</script>

<template>
    <Head :title="__('Schema Section Defaults')" />
    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Header :title="__('Schema Section Defaults')" icon="folder" />
        <section class="space-y-6 starting-style-transition-children">
            <template v-if="collections && collections.length">
                <Subheading size="lg" class="mb-2" :text="__('Collections')" />
                <Panel>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Collection') }}</th>
                                <th class="text-end!" scope="col">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="section in collections" :key="section.handle">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <Icon :name="section.icon" class="text-gray-500 me-1" />
                                        <span v-text="__(section.title)" />
                                    </div>
                                </td>
                                <td class="text-end">
                                    <Link
                                        :href="cp_url('schema-templates/section-defaults/' + section.type + '/' + section.handle + '/edit')"
                                        v-text="__('Edit')"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Panel>
            </template>
            <template v-if="taxonomies && taxonomies.length">
                <Subheading size="lg" class="mb-2" :text="__('Taxonomies')" />
                <Panel>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Taxonomy') }}</th>
                                <th class="text-end!" scope="col">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="section in taxonomies" :key="section.handle">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <Icon :name="section.icon" class="text-gray-500 me-1" />
                                        <span v-text="__(section.title)" />
                                    </div>
                                </td>
                                <td class="text-end">
                                    <Link
                                        :href="cp_url('schema-templates/section-defaults/' + section.type + '/' + section.handle + '/edit')"
                                        v-text="__('Edit')"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Panel>
            </template>
            <p v-if="(!collections || !collections.length) && (!taxonomies || !taxonomies.length)" class="text-gray-500">
                {{ __('No collections or taxonomies.') }}
            </p>
        </section>
    </div>
</template>
