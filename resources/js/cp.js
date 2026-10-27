/**
 * When extending the control panel, be sure to uncomment the necessary code for your build process:
 * https://statamic.dev/extending/control-panel
 */

// ---------------------------------------
// Custom Fieldtypes
// ---------------------------------------

/**
 * Input Mask Fieldtype
 */

import MaskFieldFieldtype from './components/fieldtypes/MaskFieldtype.vue';

Statamic.booting(() => {
    // Should be named [snake_case_handle]-fieldtype
    Statamic.$components.register('mask_field-fieldtype', MaskFieldFieldtype);
});

/**
 * Table Data Importer Trigger Fieldtype
 */

import TriggerFieldtype from './components/fieldtypes/TriggerFieldtype.vue';

Statamic.booting(() => {
    // Register as [handle]-fieldtype, where handle is 'trigger'
    Statamic.$components.register('trigger-fieldtype', TriggerFieldtype);
});

/**
 * Radio Fieldtype
 * (extends the base Statamic Radio fieldtype)
 */

import CustomRadioFieldtype from './components/fieldtypes/RadioFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_radio-fieldtype', CustomRadioFieldtype);
});

/**
 * LikertScale Fieldtype
 * (single-row scale + multi-row matrix; App\Fieldtypes\LikertScale)
 */

import LikertScaleFieldtype from './components/fieldtypes/LikertScaleFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('likert_scale-fieldtype', LikertScaleFieldtype);
});

/**
 * Checkboxes Fieldtype
 * (extends the base Statamic Checkboxes fieldtype)
 */

import CustomCheckboxesFieldtype from './components/fieldtypes/CheckboxesFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_checkboxes-fieldtype', CustomCheckboxesFieldtype);
});

/**
 * Consent Fieldtype
 */

import ConsentFieldtype from './components/fieldtypes/ConsentFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('consent-fieldtype', ConsentFieldtype);
});

/**
 * Phone Input Fieldtype
 * (extends the general HTML Input Fieldtype)
 */

import PhoneInputFieldtype from './components/fieldtypes/PhoneFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('phone_input-fieldtype', PhoneInputFieldtype);
});

/**
 * Address Fieldtype
 * (uses HasSubfieldsFieldtype for structured address input)
 */

import AddressFieldtype from './components/fieldtypes/AddressFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('address-fieldtype', AddressFieldtype);
});

/**
 * Name Fieldtype
 * (uses HasSubfieldsFieldtype for structured name input)
 */

import NameFieldtype from './components/fieldtypes/NameFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('name-fieldtype', NameFieldtype);
});

/**
 * Person Name Fieldtype (text + phonetic text pairs, display format key)
 */

import PersonNameFieldtype from './components/fieldtypes/PersonNameFieldtype.vue';
import PersonNameDisplayFormatFieldtype from './components/fieldtypes/PersonNameDisplayFormatFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('person_name-fieldtype', PersonNameFieldtype);
    Statamic.$components.register('person_name_display_format-fieldtype', PersonNameDisplayFormatFieldtype);
});

/**
 * Partial date (year / month / day parts with configurable granularity)
 */

import PartialDateFieldtype from './components/fieldtypes/PartialDateFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('partial_date-fieldtype', PartialDateFieldtype);
});

/**
 * Text + Phonetic Pair Fieldtype (shared TextPhoneticPair control + group shell)
 */

import TextPhoneticPairFieldtype from './components/fieldtypes/TextPhoneticPairFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('text_phonetic_pair-fieldtype', TextPhoneticPairFieldtype);
});

/**
 * Role Title fieldtype (faculty / administrative configurations + composed value)
 */

import RoleTitleFieldtype from './components/fieldtypes/RoleTitleFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('role_title-fieldtype', RoleTitleFieldtype);
});

/**
 * Currency Fieldtype
 */

import CurrencyFieldtype from './components/fieldtypes/CurrencyFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('currency-fieldtype', CurrencyFieldtype);
});

/**
 * Email Fieldtype
 */

import EmailFieldtype from './components/fieldtypes/EmailFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('email-fieldtype', EmailFieldtype);
});

/**
 * Password Fieldtype
 */

import PasswordFieldtype from './components/fieldtypes/PasswordFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('password-fieldtype', PasswordFieldtype);
});

/**
 * Integer Fieldtype
 * (extends the base Statamic Integer fieldtype)
 */

import CustomIntegerFieldtype from './components/fieldtypes/IntegerFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_integer-fieldtype', CustomIntegerFieldtype);
});

/**
 * Floatval Fieldtype
 * (extends the base Statamic Floatval fieldtype)
 */

import CustomFloatvalFieldtype from './components/fieldtypes/FloatvalFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_floatval-fieldtype', CustomFloatvalFieldtype);
});

/**
 * Month Fieldtype
 */

import MonthFieldtype from './components/fieldtypes/MonthFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('month-fieldtype', MonthFieldtype);
});

/**
 * Text Fieldtype
 * (extends the base Statamic Text fieldtype; custom component so core config UI is not overridden)
 */

import CustomTextFieldtype from './components/fieldtypes/TextFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_text-fieldtype', CustomTextFieldtype);
});

/**
 * URL Fieldtype
 */

import UrlFieldtype from './components/fieldtypes/UrlFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('url-fieldtype', UrlFieldtype);
});

/**
 * Video Fieldtype
 * (extends the base Statamic Video fieldtype; custom component replaces core CP
 * preview so direct file URLs use `<video>`, not iframe)
 */

import CustomVideoFieldtype from './components/fieldtypes/VideoFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_video-fieldtype', CustomVideoFieldtype);
});

/**
 * Audio Fieldtype
 * (new `audio` handle backed by App\Fieldtypes\Audio; custom component previews
 * direct file URLs with `<audio>` controls, mirroring the custom Video fieldtype)
 */

import CustomAudioFieldtype from './components/fieldtypes/AudioFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_audio-fieldtype', CustomAudioFieldtype);
});

/**
 * Week Fieldtype
 */

import WeekFieldtype from './components/fieldtypes/WeekFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('week-fieldtype', WeekFieldtype);
});

/**
 * CSS Selector Fieldtype
 */

import CssSelectorFieldtype from './components/fieldtypes/CssSelectorFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('css_selector-fieldtype', CssSelectorFieldtype);
});

/**
 * Xpath Fieldtype
 */

import XpathFieldtype from './components/fieldtypes/XpathFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('xpath-fieldtype', XpathFieldtype);
});

/**
 * ZIP Code Fieldtype
 */

import ZipCodeFieldtype from './components/fieldtypes/ZipCodeFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('zip_code-fieldtype', ZipCodeFieldtype);
});

/**
 * Identifier Fieldtype
 */

import IdentifierFieldtype from './components/fieldtypes/IdentifierFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('identifier-fieldtype', IdentifierFieldtype);
});

/**
 * Signature Fieldtype
 */

import SignatureFieldtype from './components/fieldtypes/SignatureFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('signature-fieldtype', SignatureFieldtype);
});

/**
 * Subsection Fieldtype
 */

import SubsectionFieldtype from './components/fieldtypes/SubsectionFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('subsection-fieldtype', SubsectionFieldtype);
});

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
 * ISCO-08 chained occupation structure (ILO classification)
 */
import Isco08Fieldtype from './components/fieldtypes/Isco08Fieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('isco_08-fieldtype', Isco08Fieldtype);
});

/**
 * True/False Fieldtype (alias for button_group-fieldtype)
 *
 * PHP App\Fieldtypes\TrueFalse uses handle "true_false" but component
 * "button_group", so Statamic uses "button_group-fieldtype". When config has
 * type: "true_false" (e.g. schema object boolean set), the CP looks for
 * "true_false-fieldtype". This wrapper delegates so that component exists.
 */
import TrueFalseFieldtype from './components/fieldtypes/TrueFalseFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('true_false-fieldtype', TrueFalseFieldtype);
});

/**
 * Entries Fieldtype (alias for relationship-fieldtype)
 *
 * PHP App\Fieldtypes\Entries uses handle "entries" but component "relationship",
 * so Statamic registers only "relationship-fieldtype". When config has type:
 * "entries" (e.g. schema object Thing set), the CP looks for "entries-fieldtype".
 * This wrapper delegates so that component name exists.
 */
import EntriesFieldtype from './components/fieldtypes/EntriesFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('entries-fieldtype', EntriesFieldtype);
});

/**
 * Option Fieldtype (Gravity Forms option-field analog)
 *
 * Priced choices (drop-down / radio / checkbox) for a parent product. The
 * selected option subtotal feeds the form Total pipeline (UAMS-Web/uams-statamic-archive#488) via the same
 * `product:price-changed` seam the product fieldtype (UAMS-Web/uams-statamic-archive#300) established.
 */

import OptionFieldtype from './components/fieldtypes/OptionFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('option-fieldtype', OptionFieldtype);
});

/**
 * Product Fieldtype (Gravity Forms product-field analog)
 *
 * Single product (base price + optional quantity) or a drop-down of priced
 * choices. The selected line subtotal feeds the form Total pipeline (UAMS-Web/uams-statamic-archive#488).
 */

import ProductFieldtype from './components/fieldtypes/ProductFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('product-fieldtype', ProductFieldtype);
});

/**
 * Total Fieldtype
 * (read-only running order total; the live sum on the public form is produced
 * by total-pricing-engine.js — see App\Fieldtypes\Total, UAMS-Web/uams-statamic-archive#488)
 */

import TotalFieldtype from './components/fieldtypes/TotalFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('total-fieldtype', TotalFieldtype);
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

/**
 * GPS / Map Fieldtype
 * (Leaflet + OpenStreetMap coordinate picker; stores structured { lat, lng, zoom })
 */

import GpsFieldtype from './components/fieldtypes/GpsFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('gps-fieldtype', GpsFieldtype);
});

/**
 * Date Fieldtype
 * (extends the base Statamic Date fieldtype; App\Fieldtypes\Date routes fields
 * that configure a Boundaries & Limits constraint to this component so the CP
 * picker honors earliest/latest min-max, allowed days_of_week, and
 * date_exceptions blackout dates the base picker ignores)
 */

import CustomDateFieldtype from './components/fieldtypes/DateFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom_date-fieldtype', CustomDateFieldtype);
});

/*
 * Date Field Select Fieldtype
 * (App\Fieldtypes\DateFieldSelect — the CP-only select behind the Date fieldtype's
 * `earliest_date_field` / `latest_date_field` boundary config; lists the other
 * `date` fields of the blueprint being edited, populated client-side)
 */

import DateFieldSelectFieldtype from './components/fieldtypes/DateFieldSelectFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('date_field_select-fieldtype', DateFieldSelectFieldtype);
});

// ---------------------------------------
// Custom Fieldtype Partials
// ---------------------------------------

/**
 * Input Partials
 */

import CustomInput from './components/fieldtypes/partials/CustomInput.vue';
import CustomInputText from './components/fieldtypes/partials/CustomInputText.vue';
import PhoneticTextInput from './components/fieldtypes/partials/PhoneticTextInput.vue';
import TextPhoneticPair from './components/fieldtypes/partials/TextPhoneticPair.vue';
import GroupFieldtypeShell from './components/fieldtypes/partials/GroupFieldtypeShell.vue';
import PhoneticTextInstructions from './components/fieldtypes/partials/PhoneticTextInstructions.vue';
import SchemaOrgDocsAlert from './components/fieldtypes/partials/SchemaOrgDocsAlert.vue';
import SchemaOrgTypeCombobox from './components/fieldtypes/partials/SchemaOrgTypeCombobox.vue';

Statamic.booting(() => {
    Statamic.$components.register('custom-input', CustomInput);
    Statamic.$components.register('custom-text-input', CustomInputText);
    Statamic.$components.register('phonetic-text-input', PhoneticTextInput);
    Statamic.$components.register('text-phonetic-pair', TextPhoneticPair);
    Statamic.$components.register('group-fieldtype-shell', GroupFieldtypeShell);
    Statamic.$components.register('phonetic-text-instructions-alert', PhoneticTextInstructions);
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

// ---------------------------------------
// People (Merge duplicate people entries)
// ---------------------------------------

import PeopleMerge from './pages/People/Merge.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('app::PeopleMerge', PeopleMerge);
});

// ---------------------------------------
// Marketing Redirects (Tools > Marketing Redirects)
// ---------------------------------------

import MarketingRedirectCreate from './pages/Redirects/MarketingRedirectCreate.vue';
import MarketingRedirectReview from './pages/Redirects/MarketingRedirectReview.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('app::MarketingRedirectCreate', MarketingRedirectCreate);
    Statamic.$inertia.register('app::MarketingRedirectReview', MarketingRedirectReview);
});
