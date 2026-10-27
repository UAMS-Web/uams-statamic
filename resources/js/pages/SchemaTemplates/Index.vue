<script setup>
import { Link, Head } from '@statamic/cms/inertia';
import { Header, CardPanel, Heading, Subheading, Icon } from '@statamic/cms/ui';

defineProps({
    canEditSiteDefaults: Boolean,
    canEditSectionDefaults: Boolean,
    canEditBlueprintDefaults: Boolean,
    canManageJsonLdCache: Boolean,
});
</script>

<template>
    <Head :title="__('Schema Templates')" />
    <div class="max-w-5xl mx-auto">
        <Header :title="__('Schema Templates')" icon="code" />
        <CardPanel>
            <div class="flex flex-wrap starting-style-transition-children">
                <Link
                    v-if="canEditSiteDefaults"
                    :href="cp_url('schema-templates/site-defaults/edit')"
                    class="group w-full items-start rounded-md border border-transparent p-4 hover:bg-gray-100 dark:hover:border-dark-400 dark:hover:bg-dark-575 md:flex lg:w-1/2"
                >
                    <Icon name="earth" class="size-6 text-gray-400 mt-1 mb-2 me-4" />
                    <div class="mb-4 flex-1 md:mb-0 md:me-6">
                        <Heading size="lg" :text="__('Site Defaults')" />
                        <Subheading :text="__('Global schema output and template parameter for the schema tag.')" />
                    </div>
                </Link>
                <Link
                    v-if="canEditSectionDefaults"
                    :href="cp_url('schema-templates/section-defaults')"
                    class="group w-full items-start rounded-md border border-transparent p-4 hover:bg-gray-100 dark:hover:border-dark-400 dark:hover:bg-dark-575 md:flex lg:w-1/2"
                >
                    <Icon name="folder" class="size-6 text-gray-400 mt-1 mb-2 me-4" />
                    <div class="mb-4 flex-1 md:mb-0 md:me-6">
                        <Heading size="lg" :text="__('Section Defaults')" />
                        <Subheading :text="__('Override schema per collection or taxonomy.')" />
                    </div>
                </Link>
                <Link
                    v-if="canEditBlueprintDefaults"
                    :href="cp_url('schema-templates/blueprint-defaults')"
                    class="group w-full items-start rounded-md border border-transparent p-4 hover:bg-gray-100 dark:hover:border-dark-400 dark:hover:bg-dark-575 md:flex lg:w-1/2"
                >
                    <Icon name="blueprints" class="size-6 text-gray-400 mt-1 mb-2 me-4" />
                    <div class="mb-4 flex-1 md:mb-0 md:me-6">
                        <Heading size="lg" :text="__('Blueprint Defaults')" />
                        <Subheading :text="__('Override schema per blueprint within a section.')" />
                    </div>
                </Link>
                <Link
                    v-if="canManageJsonLdCache"
                    :href="cp_url('schema-templates/json-ld-cache')"
                    class="group w-full items-start rounded-md border border-transparent p-4 hover:bg-gray-100 dark:hover:border-dark-400 dark:hover:bg-dark-575 md:flex lg:w-1/2"
                >
                    <Icon name="cache" class="size-6 text-gray-400 mt-1 mb-2 me-4" />
                    <div class="mb-4 flex-1 md:mb-0 md:me-6">
                        <Heading size="lg" :text="__('JSON-LD cache')" />
                        <Subheading :text="__('Clear or warm cached output from the schema_ld_json tag.')" />
                    </div>
                </Link>
            </div>
        </CardPanel>
    </div>
</template>
