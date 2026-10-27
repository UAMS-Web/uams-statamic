<template>
    <component
        :is="buttonGroupComponent"
        v-bind="$attrs"
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
</template>

<script>
/**
 * Wrapper so replicator (and other CP code) can resolve "true_false-fieldtype".
 *
 * The PHP TrueFalse fieldtype extends ButtonGroup and uses component
 * "button_group", so Statamic would use "button_group-fieldtype" when the
 * fieldtype is resolved by handle. When config is built with type: "true_false"
 * (e.g. schema object boolean set), the CP looks for "true_false-fieldtype".
 * This component delegates to button_group-fieldtype so that name works.
 */
export default {
    name: 'TrueFalseFieldtype',
    inheritAttrs: false,
    computed: {
        buttonGroupComponent() {
            const comp = this.$.appContext?.components?.['button_group-fieldtype'];
            return comp || 'button_group-fieldtype';
        },
    },
};
</script>
