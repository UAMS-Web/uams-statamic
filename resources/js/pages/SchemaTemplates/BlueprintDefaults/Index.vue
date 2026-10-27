<script setup>
import { Link, Head } from '@statamic/cms/inertia';
import { Header, Subheading, Panel, Icon } from '@statamic/cms/ui';

defineProps({
    collections: Array,
    taxonomies: Array,
});
</script>

<template>
    <Head :title="__('Schema Blueprint Defaults')" />

    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Header :title="__('Schema Blueprint Defaults')" icon="blueprints" />

        <section class="space-y-6 starting-style-transition-children">
            <template v-if="collections?.length">
                <Subheading size="lg" class="mb-2" :text="__('Collections')" />
                <Panel>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Blueprint') }}</th>
                                <th class="text-end!" scope="col">{{ __('Collection') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="section in collections" :key="section.handle">
                                <tr
                                    v-for="blueprint in section.blueprints"
                                    :key="blueprint.handle"
                                >
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <Icon :name="section.icon" class="text-gray-500 me-1" />
                                            <Link
                                                :href="cp_url(`schema-templates/blueprint-defaults/collection/${section.handle}/${blueprint.handle}/edit`)"
                                                v-text="__(blueprint.title)"
                                            />
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span
                                            class="pe-2 font-mono text-xs text-gray-500 dark:text-gray-400"
                                            v-text="__(section.title)"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </Panel>
            </template>

            <template v-if="taxonomies?.length">
                <Subheading size="lg" class="mb-2" :text="__('Taxonomies')" />
                <Panel>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Blueprint') }}</th>
                                <th class="text-end!" scope="col">{{ __('Taxonomy') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="section in taxonomies" :key="section.handle">
                                <tr
                                    v-for="blueprint in section.blueprints"
                                    :key="blueprint.handle"
                                >
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <Icon :name="section.icon" class="text-gray-500 me-1" />
                                            <Link
                                                :href="cp_url(`schema-templates/blueprint-defaults/taxonomy/${section.handle}/${blueprint.handle}/edit`)"
                                                v-text="__(blueprint.title)"
                                            />
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span
                                            class="pe-2 font-mono text-xs text-gray-500 dark:text-gray-400"
                                            v-text="__(section.title)"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </Panel>
            </template>

            <p
                v-if="!collections?.length && !taxonomies?.length"
                class="text-gray-500"
            >
                {{ __('No collections or taxonomies with blueprints.') }}
            </p>
        </section>
    </div>
</template>
