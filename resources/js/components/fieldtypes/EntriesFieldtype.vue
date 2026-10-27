<template>
    <component
        v-if="safeAttrs.meta"
        :is="relationshipComponent"
        v-bind="safeAttrs"
    >
        <template
            v-for="(_, name) in $slots"
            #[name]="slotData"
        >
            <slot
                :name="name"
                v-bind="slotData"
            />
        </template>
    </component>
    <div
        v-else
        class="text-sm text-gray-500 dark:text-gray-400"
    >
        {{ $__('Loading...') }}
    </div>
</template>

<script>
/**
 * Wrapper so replicator (and other CP code) can resolve "entries-fieldtype".
 *
 * The PHP Entries fieldtype extends Relationship and uses component
 * "relationship", so Statamic registers the Vue component as
 * "relationship-fieldtype". When config is built with type: "entries"
 * (e.g. schema object Thing set), the CP looks for "entries-fieldtype".
 * This component delegates to relationship-fieldtype so both names work.
 *
 * When meta is null (e.g. set meta not yet loaded), we show "Loading..."
 * instead of passing null to relationship-fieldtype (which would throw on
 * meta.canEdit).
 */
export default {
    name: 'EntriesFieldtype',
    inheritAttrs: false,
    computed: {
        relationshipComponent() {
            const comp = this.$.appContext?.components?.['relationship-fieldtype'];
            return comp || 'relationship-fieldtype';
        },
        safeAttrs() {
            const meta = this.$attrs?.meta;
            if (meta != null && typeof meta === 'object') {
                return this.$attrs;
            }
            return { ...this.$attrs, meta: null };
        },
    },
};
</script>
