/**
 * When extending the control panel, be sure to uncomment the necessary code for your build process:
 * https://statamic.dev/extending/control-panel
 */

// ---------------------------------------
// Custom Fieldtypes
// ---------------------------------------

/**
 * Schema Object Fieldtype
 * (Schema.org type select + dynamic property fields via spatie/schema-org)
 */

import SchemaObjectFieldtype from './components/fieldtypes/SchemaObjectFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('schema_object-fieldtype', SchemaObjectFieldtype);
});

/**
 * Quantitative Value fieldtype (number range / duration units)
 */
import QuantitativeValueFieldtype from './components/fieldtypes/QuantitativeValueFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('quantitative_value-fieldtype', QuantitativeValueFieldtype);
});

/**
 * Schema.org type (chained drilldown or subtree combobox)
 */
import SchemaTypeFieldtype from './components/fieldtypes/SchemaTypeFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('schema_type-fieldtype', SchemaTypeFieldtype);
});

/**
 * CTDL class (CredentialRegistry chained selects)
 */

import CtdlClassFieldtype from './components/fieldtypes/CtdlClassFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('ctdl_class-fieldtype', CtdlClassFieldtype);
});

/**
 * Orphan Banner Fieldtype
 * (universal read-only CP warning when a routable + hierarchical collection
 * entry is orphaned by an unpublished ancestor and redirects on the live site —
 * see App\Fieldtypes\OrphanBanner, UAMS-Web/uams-statamic-archive#1429; folded knowledge_bases onto this in
 * UAMS-Web/uams-statamic-archive#1455, retiring the bespoke KbOrphanBanner)
 */

import OrphanBannerFieldtype from './components/fieldtypes/OrphanBannerFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('orphan_banner-fieldtype', OrphanBannerFieldtype);
});

/**
 * Publish Dependency Banner Fieldtype
 * (opt-in read-only CP warning when a dependent entry's declared
 * `publish_depends_on` source(s) are down, so the entry is redirected + hidden
 * from listings on the live site — see App\Fieldtypes\PublishDependencyBanner,
 * UAMS-Web/uams-statamic-archive#1593; mirrors the orphan_banner mechanism)
 */

import PublishDependencyBannerFieldtype from './components/fieldtypes/PublishDependencyBannerFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('publish_dependency_banner-fieldtype', PublishDependencyBannerFieldtype);
});

// ---------------------------------------
// Custom Fieldtype Partials
// ---------------------------------------

/**
 * Input Partials
 */

import CustomInput from './components/fieldtypes/partials/CustomInput.vue';
import CustomInputText from './components/fieldtypes/partials/CustomInputText.vue';
import GroupFieldtypeShell from '../../vendor/uams-web/aether-fieldtypes/resources/js/components/fieldtypes/partials/GroupFieldtypeShell.vue';
import SchemaOrgDocsAlert from './components/fieldtypes/partials/SchemaOrgDocsAlert.vue';
import SchemaOrgTypeCombobox from './components/fieldtypes/partials/SchemaOrgTypeCombobox.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom-input', CustomInput);
    Statamic.$components.register('custom-text-input', CustomInputText);
    Statamic.$components.register('group-fieldtype-shell', GroupFieldtypeShell);
    Statamic.$components.register('schema-org-docs-alert', SchemaOrgDocsAlert);
    Statamic.$components.register('schema-org-type-combobox', SchemaOrgTypeCombobox);
});

/**
 * Has Subfields
 */

import HasSubfields from './components/fieldtypes/partials/HasSubfields.vue';

Statamic.booting(() => {
    Statamic.$components.register('has-subfields', HasSubfields);
});

// ---------------------------------------
// Custom Field Conditions
// ---------------------------------------

/**
 * Custom Field Conditions
 */

import './conditions.js';

// ---------------------------------------
// Schema Templates (Tools > Schema Templates)
// ---------------------------------------

import SchemaTemplatesIndex from './pages/SchemaTemplates/Index.vue';
import SchemaTemplatesSectionDefaultsIndex from './pages/SchemaTemplates/SectionDefaults/Index.vue';
import SchemaTemplatesBlueprintDefaultsIndex from './pages/SchemaTemplates/BlueprintDefaults/Index.vue';
import SchemaTemplatesJsonLdCache from './pages/SchemaTemplates/JsonLdCache.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('app::SchemaTemplatesIndex', SchemaTemplatesIndex);
    Statamic.$inertia.register('app::SchemaTemplatesSectionDefaultsIndex', SchemaTemplatesSectionDefaultsIndex);
    Statamic.$inertia.register('app::SchemaTemplatesBlueprintDefaultsIndex', SchemaTemplatesBlueprintDefaultsIndex);
    Statamic.$inertia.register('app::SchemaTemplatesJsonLdCache', SchemaTemplatesJsonLdCache);
});
