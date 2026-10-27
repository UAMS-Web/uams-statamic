<!--
 * Schema.org object fieldtype: select one or more types (Multi Typed Entities / MTE),
 * then show one text field per valid property across those types. Layout and markup
 * mirror Statamic GroupFieldtype and Publish Field/Label so the DOM matches core.
 -->
<template>
	<section>
		<div>
			<div :class="[publishFieldsClass, typeSectionErrors.length || fieldErrors.length ? 'has-error' : '']">
				<div
					:class="['min-w-0 form-group select-fieldtype field-w-undefined', typeSectionErrors.length ? 'has-error' : '']"
					data-ui-input-group
				>
					<div>
						<div data-ui-field-text="" class="mb-1.5">
							<label
								:for="typeFieldId"
								class="flex justify-between mb-1.5 text-sm font-medium [&_button]:font-medium text-gray-925 select-none dark:text-gray-300"
								data-ui-label=""
							>
								<div><span>{{ __('Schema.org Type') }}</span></div>
							</label>
							<p class="mb-1.5 text-sm text-gray-500 dark:text-gray-400">
								{{ typeSelectorMultiple ? __('Select one or more types.') : __('Select a type.') }}
							</p>
						</div>
					</div>
					<div dir="ltr">
						<schema-org-type-combobox
							:id="typeFieldId"
							:model-value="comboboxModelValue"
							:options="typeOptions"
							:placeholder="typeSelectorMultiple ? __('Select types') : __('Select a type')"
							:clearable="config.type_selector_clearable !== false"
							:disabled="config.type_selector_disabled === true"
							:multiple="typeSelectorMultiple"
							:read-only="isReadOnly"
							:searchable="config.type_selector_searchable !== false"
							:adaptive-width="true"
							@update:model-value="onTypeChange"
						/>
					</div>
					<div v-if="typeSectionErrors.length">
						<ErrorMessage
							v-for="(error, i) in typeSectionErrors"
							class="mt-2"
							:key="i"
							:text="error"
						/>
					</div>
					<schema-org-docs-alert
						variant="types"
						:items="localTypes"
						:descriptions-by-name="typeDescriptions"
						:multi-entity-title="__('Multi Typed Entity')"
						:use-multi-entity-when-multiple="true"
					/>

				</div>

				<div
					v-if="localTypes.length > 0 && propertyNames.length > 0"
					class="min-w-0 form-group group-fieldtype field-w-undefined"
					data-ui-input-group
				>
					<div>
						<div data-ui-field-text="" class="mb-1.5">
							<label
								:for="propertiesLabelId"
								class="flex justify-between mb-1.5 text-sm font-medium [&_button]:font-medium text-gray-925 select-none dark:text-gray-300"
								data-ui-label=""
							>
								<div><span>{{ __('Schema.org Properties') }}</span></div>
							</label>
						</div>
					</div>
					<div dir="ltr">
						<p
							v-if="replicatorDataLoadingForTypesKey === typesCacheKey"
							class="text-sm text-gray-500 dark:text-gray-400"
						>
							{{ __('Loading properties…') }}
						</p>
						<section v-else>
							<div class="bg-white dark:bg-gray-800 dark:border-gray-900 rounded-lg border">
								<div class="publish-fields pt-4 px-4 py-4">
									<div
										v-for="propName in propertyNames"
										:key="propName"
										class="min-w-0 form-group replicator-fieldtype field-w-null"
										data-ui-input-group
									>
										<div>
											<div data-ui-field-text="" class="mb-1.5">
												<label
													:for="inputId(propName)"
													class="flex justify-between mb-1.5 text-sm font-medium [&_button]:font-medium text-gray-925 select-none dark:text-gray-300"
													data-ui-label=""
												>
													<div><span>{{ propName }}</span></div>
												</label>
												<schema-org-docs-alert
													variant="callout"
													:raw-description-html="propertyInstruction(propName)"
													:doc-url="schemaOrgUrl(propName)"
													:badge-text="learnAboutPropertyLabel(propName)"
													alert-class=""
													callout-paragraph-class="schema-description mb-1.5 text-sm text-gray-500 dark:text-gray-400"
													callout-badge-class="mb-1.5"
												/>
											</div>
										</div>
										<div
											dir="ltr"
											class="flex flex-col md:flex-row md:items-stretch gap-2 border border-gray-200 dark:border-gray-800 rounded-md p-2 bg-gray-50 dark:bg-gray-900"
										>
											<div class="w-auto min-w-[8rem] md:flex-[0_0_33%]">
												<Select
													:id="modeInputId(propName)"
													class="w-full"
													:model-value="propertyMode(propName)"
													:options="propertyModeOptions"
													:read-only="isReadOnly"
													:clearable="false"
													@update:model-value="onPropertyModeChange(propName, $event)"
												/>
											</div>
											<div
												class="flex-1 min-w-0 md:flex-[0_0_66%] flex flex-col"
												:class="{
													'justify-center': !propertyUsesReplicatorMode(propName),
												}"
											>
												<div
													v-if="propertyModeIsInherit(propName)"
													class="text-sm text-gray-500 dark:text-gray-400 px-1"
												>
													{{ __('Value inherited from parent object.') }}
												</div>
												<div
													v-else-if="propertyModeIsDisable(propName)"
													class="text-sm text-gray-500 dark:text-gray-400 px-1"
												>
													{{ __('Property omitted from output.') }}
												</div>
												<template
													v-else-if="
														replicatorDataReadyForType
														&& propertyUsesReplicatorMode(propName)
													"
												>
													<div
														v-if="propertyModeIsInheritCustom(propName)"
														class="text-sm text-gray-500 dark:text-gray-400 px-1 mb-2"
													>
														{{ __('Includes value inherited from parent; add more below.') }}
													</div>
													<component
														:is="'replicator-fieldtype'"
														:key="`${typesCacheKey}-${propName}-replicator`"
														:config="replicatorConfig(propName)"
														:meta="replicatorMeta(propName)"
														:value="propertyReplicatorValue(propName)"
														:handle="propertyReplicatorHandle"
														:field-path-prefix="propertyReplicatorFieldPathPrefix(propName)"
														:meta-path-prefix="propertyReplicatorMetaPathPrefix(propName)"
														:read-only="isReadOnly"
														@update:value="onReplicatorChange(propName, $event)"
														@update:meta="onReplicatorMetaUpdate(propName, $event)"
													/>
												</template>
											</div>
										</div>
									</div>
								</div>
							</div>
						</section>
					</div>
				</div>

				<div
					v-else-if="localTypes.length > 0 && propertyNames.length === 0"
					class="min-w-0 form-group field-w-undefined text-sm text-gray-500 dark:text-gray-400"
				>
					{{ __('No editable properties for the selected type(s).') }}
				</div>

			</div>
		</div>
	</section>
</template>

<script>
import { computed, getCurrentInstance, inject, ref, watch } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { ErrorMessage, publishContextKey, Select } from '@statamic/cms/ui';
import SchemaOrgDocsAlert from './partials/SchemaOrgDocsAlert.vue';
import SchemaOrgTypeCombobox from './partials/SchemaOrgTypeCombobox.vue';

const PROPERTY_MODE_INHERIT = 'inherit';
const PROPERTY_MODE_DISABLE = 'disable';
const PROPERTY_MODE_CUSTOM = 'custom';
const PROPERTY_MODE_INHERIT_CUSTOM = 'inherit_custom';

export default {
	components: { ErrorMessage, SchemaOrgDocsAlert, SchemaOrgTypeCombobox, Select },
	mixins: [Fieldtype],

	setup(props) {
		const instance = getCurrentInstance().proxy;
		const publishContext = inject(publishContextKey);

		const typeOptions = computed(() => {
			const raw = props.meta?.type_options ?? {};
			return Object.entries(raw).map(([value, label]) => ({ value, label: label || value }));
		});

		const propertiesByType = computed(() => props.meta?.properties_by_type ?? {});

		const localTypes = ref([]);

		// Union of valid property names across all selected types (MTE).
		// When multiple types are selected, use server-provided property_names from
		// replicator-data so combined blacklist applies (e.g. CollegeOrUniversity
		// blacklist suppresses priceRange even when LocalBusiness is also selected).
		const propertyNames = computed(() => {
			if (localTypes.value.length === 0) return [];
			if (localTypes.value.length > 1) {
				const cached = typeReplicatorDataCache.value[typesCacheKey.value];
				if (cached && Array.isArray(cached.property_names)) return cached.property_names;
			}
			const seen = new Set();
			const out = [];
			for (const t of localTypes.value) {
				const list = propertiesByType.value[t];
				if (!Array.isArray(list)) continue;
				for (const name of list) {
					if (!seen.has(name)) {
						seen.add(name);
						out.push(name);
					}
				}
			}
			return out;
		});

		const typesCacheKey = computed(() =>
			[...localTypes.value].sort().join(',')
		);

		const typeDescriptions = computed(() => props.meta?.type_descriptions ?? {});
		function typeLabel(typeValue) {
			const opt = typeOptions.value.find((o) => o.value === typeValue);
			return opt ? opt.label : typeValue;
		}

		const typeSelectorMultiple = computed(
			() => props.config?.type_selector_multiple !== false
		);

		// Combobox model: array when multiple, single value when not.
		const comboboxModelValue = computed(() => {
			if (typeSelectorMultiple.value) {
				return localTypes.value;
			}
			return localTypes.value.length > 0 ? localTypes.value[0] : '';
		});

		const propertyInstructionsByType = computed(
			() => props.meta?.property_instructions_by_type ?? {}
		);
		function propertyInstruction(propName) {
			for (const t of localTypes.value) {
				const byType = propertyInstructionsByType.value[t];
				if (byType?.[propName]) return byType[propName];
			}
			return null;
		}

		// Lazy-loaded replicator config/meta per type set (key = sorted types joined).
		const typeReplicatorDataCache = ref({});
		const replicatorDataLoadingForTypesKey = ref(null);
		const replicatorDataReadyForType = computed(
			() =>
				localTypes.value.length > 0 &&
				!!typeReplicatorDataCache.value[typesCacheKey.value]
		);

		async function fetchReplicatorDataForTypes(types) {
			if (!Array.isArray(types) || types.length === 0) return;
			const key = [...types].sort().join(',');
			if (typeReplicatorDataCache.value[key]) return;
			replicatorDataLoadingForTypesKey.value = key;
			try {
				const { data } = await instance.$axios.post(
					cp_url('schema-object/replicator-data'),
					{ types }
				);
				typeReplicatorDataCache.value[key] = {
					config_by_property: data.config_by_property ?? {},
					meta_by_property: data.meta_by_property ?? {},
					property_names: Array.isArray(data.property_names) ? data.property_names : [],
				};
			} catch (err) {
				instance.$toast?.error?.(
					instance.__('Something went wrong') ?? 'Something went wrong'
				);
			} finally {
				replicatorDataLoadingForTypesKey.value = null;
			}
		}

		const propertyModeOptions = computed(() => [
			{ value: PROPERTY_MODE_INHERIT, label: instance.__('Inherit from parent') },
			{ value: PROPERTY_MODE_DISABLE, label: instance.__('Disable (omit from output)') },
			{ value: PROPERTY_MODE_INHERIT_CUSTOM, label: instance.__('Inherit and add custom') },
			{ value: PROPERTY_MODE_CUSTOM, label: instance.__('Use custom value') },
		]);

		function propertyGroupValue(propName) {
			const v = props.value?.properties?.[propName];
			if (v && typeof v === 'object' && 'mode' in v) {
				return {
					mode: v.mode ?? PROPERTY_MODE_INHERIT,
					value: Array.isArray(v.value) ? v.value : [],
				};
			}
			return { mode: PROPERTY_MODE_INHERIT, value: [] };
		}

		function propertyMode(propName) {
			return propertyGroupValue(propName).mode;
		}

		// Mode checks use module-level constants so the template does not rely on
		// exposing PROPERTY_MODE_* from setup (avoids mismatch vs string literals).
		function propertyModeIsInherit(propName) {
			return propertyMode(propName) === PROPERTY_MODE_INHERIT;
		}

		function propertyModeIsDisable(propName) {
			return propertyMode(propName) === PROPERTY_MODE_DISABLE;
		}

		function propertyModeIsInheritCustom(propName) {
			return propertyMode(propName) === PROPERTY_MODE_INHERIT_CUSTOM;
		}

		function propertyUsesReplicatorMode(propName) {
			const mode = propertyMode(propName);
			return mode === PROPERTY_MODE_CUSTOM || mode === PROPERTY_MODE_INHERIT_CUSTOM;
		}

		function modeInputId(propName) {
			return `${fieldIdBase.value}_properties_${String(propName).replace(/[^a-z0-9_]/gi, '_').toLowerCase()}_mode`;
		}

		function onPropertyModeChange(propName, newMode) {
			const group = propertyGroupValue(propName);
			instance.update({
				type: props.value?.type ?? localTypes.value,
				properties: {
					...(props.value?.properties ?? {}),
					[propName]: { mode: newMode ?? group.mode, value: group.value },
				},
			});
		}

		function replicatorConfig(propName) {
			const cached = typeReplicatorDataCache.value[typesCacheKey.value];
			const byProp = cached?.config_by_property ?? {};
			return byProp[propName] ?? { sets: [] };
		}

		function replicatorMeta(propName) {
			const cached = typeReplicatorDataCache.value[typesCacheKey.value];
			const byProp = cached?.meta_by_property ?? {};
			const base = byProp[propName] ?? { new: {}, defaults: {} };
			const newMeta = base.new && typeof base.new === 'object' ? { ...base.new } : {};
			const newDefaults = base.defaults && typeof base.defaults === 'object' ? { ...base.defaults } : {};
			// Ensure every set handle from config has meta so the replicator never POSTs to Statamic's endpoint
			// (that endpoint only works for blueprint-defined replicator fields, not our dynamic sets).
			const config = replicatorConfig(propName);
			const groups = config?.sets ?? [];
			for (const group of groups) {
				const sets = group?.sets ?? [];
				for (const set of sets) {
					const h = set?.handle ?? set?.[0]?.handle;
					if (h && !Object.prototype.hasOwnProperty.call(newMeta, h)) {
						newMeta[h] = {};
						newDefaults[h] = {};
					}
				}
			}
			const rows = propertyReplicatorValue(propName);
			const existing = {};
			if (Array.isArray(rows)) {
				rows.forEach((row) => {
					if (row._id && newMeta[row.type]) {
						existing[row._id] = newMeta[row.type];
					}
				});
			}
			return { new: newMeta, defaults: newDefaults, existing, collapsed: [] };
		}

		function propertyReplicatorValue(propName) {
			const group = propertyGroupValue(propName);
			return Array.isArray(group.value) ? group.value : [];
		}

		// Handle for the replicator: it manages the "value" key of the group (mode + value).
		const propertyReplicatorHandle = 'value';

		// Full path to the group (e.g. schema.properties.about).
		function propertyReplicatorFullPath(propName) {
			const h = fieldHandle.value || props.handle || '';
			return h ? `${h}.properties.${propName}` : `properties.${propName}`;
		}

		// Path prefix so replicator's path is groupPath.value (e.g., schema.properties.about.value).
		// Nested fields (e.g., Code in antlers set) then resolve under that path so meta/config exist.
		function propertyReplicatorFieldPathPrefix(propName) {
			return propertyReplicatorFullPath(propName);
		}
		function propertyReplicatorMetaPathPrefix(propName) {
			return propertyReplicatorFullPath(propName);
		}

		// Legacy: parent path only (used by onReplicatorMetaUpdate for setFieldMeta).
		function propertyReplicatorPathPrefix(propName) {
			const h = fieldHandle.value || props.handle || '';
			return h ? `${h}.properties` : 'properties';
		}

		function onReplicatorChange(propName, newReplicatorValue) {
			const group = propertyGroupValue(propName);
			instance.update({
				type: props.value?.type ?? localTypes.value,
				properties: {
					...(props.value?.properties ?? {}),
					[propName]: {
						mode: group.mode,
						value: Array.isArray(newReplicatorValue) ? newReplicatorValue : [],
					},
				},
			});
		}

		// Merge replicator meta into the publish container so nested fields (e.g., Code in
		// antlers set) resolve meta at groupPath.value (schema.properties.about.value).
		function onReplicatorMetaUpdate(propName, meta) {
			const setFieldMeta = publishContext?.setFieldMeta;
			if (typeof setFieldMeta !== 'function') return;
			const metaPath = `${propertyReplicatorMetaPathPrefix(propName)}.${propertyReplicatorHandle}`;
			setFieldMeta(metaPath, meta);
		}

		// Default to hide_display true (omit pt-4) when not explicitly false
		const hideDisplay = computed(() => props.config?.hide_display !== false);
		const publishFieldsClass = computed(() =>
			hideDisplay.value ? 'publish-fields' : 'publish-fields pt-4'
		);

		// Field handle for error keys (must match PHP extraRules() which uses $field->handle()).
		const fieldHandle = computed(() => props.handle ?? props.config?.handle ?? props.name ?? '');
		// Validation errors for this field (from publish context; keyed by field handle, like HasSubfields/SubfieldContainer).
		const fieldErrors = computed(() => {
			const errs = publishContext?.errors;
			const errsVal = errs && typeof errs === 'object' && 'value' in errs ? errs.value : errs;
			if (!errsVal) return [];
			const key = fieldHandle.value;
			return errsVal[key] ?? [];
		});

		// Errors for the type subfield (when field is required and type is empty). Key = handle.type to match extraRules().
		const typeErrors = computed(() => {
			const errs = publishContext?.errors;
			const errsVal =
				errs && typeof errs === 'object' && 'value' in errs ? errs.value : errs;
			if (!errsVal) return [];
			const key = fieldHandle.value ? `${fieldHandle.value}.type` : 'type';
			return errsVal[key] ?? [];
		});

		// All errors to show below the type selector (type subfield + field-level).
		const typeSectionErrors = computed(() => {
			const type = typeErrors.value;
			const field = fieldErrors.value;
			if (type.length && field.length) return [...type, ...field];
			if (type.length) return type;
			return field;
		});

		const fieldIdBase = computed(() =>
			(props.id || `field_${(fieldHandle.value || 'schema').replace(/[^a-z0-9_]/gi, '_')}`).replace(/\./g, '_')
		);
		const typeFieldId = computed(() => `${fieldIdBase.value}_type`);
		const propertiesLabelId = computed(() => `${fieldIdBase.value}_properties`);

		function inputId(propName) {
			return `${fieldIdBase.value}_properties_${String(propName).replace(/[^a-z0-9_]/gi, '_').toLowerCase()}`;
		}

		// Full schema.org URL so DocsCallout uses it as-is (no statamic.dev prefix)
		function schemaOrgUrl(name) {
			return `https://schema.org/${encodeURIComponent(name)}`;
		}

		function learnAboutPropertyLabel(propName) {
			return instance.__('Learn about the ":property" property', { property: propName });
		}

		function onTypeChange(value) {
			const next = normalizeTypeValueToArray(value);
			localTypes.value = next;
			const allPropNames = new Set();
			for (const t of next) {
				const list = propertiesByType.value[t];
				if (Array.isArray(list)) list.forEach((n) => allPropNames.add(n));
			}
			const existing = props.value?.properties ?? {};
			const properties = {};
			for (const name of allPropNames) {
				const current = existing[name];
				properties[name] =
					current && typeof current === 'object' && 'mode' in current
						? current
						: { mode: PROPERTY_MODE_INHERIT, value: Array.isArray(current) ? current : [] };
			}
			instance.update({ type: next, properties });
		}

		function normalizeTypeValueToArray(value) {
			if (value == null || value === '') return [];
			if (Array.isArray(value)) {
				return value.filter((t) => t != null && t !== '');
			}
			return [value];
		}

		// Initialize localTypes from props.value (preProcessed in PHP; type is array).
		watch(
			() => props.value,
			(val) => {
				if (val && typeof val === 'object' && val.type !== undefined) {
					localTypes.value = Array.isArray(val.type) ? [...val.type] : [];
				}
			},
			{ immediate: true }
		);

		// Fetch replicator data for the selected types when needed (lazy + cache).
		watch(
			[() => localTypes.value, propertyNames],
			([types, names]) => {
				if (
					Array.isArray(types) &&
					types.length > 0 &&
					Array.isArray(names) &&
					names.length > 0
				) {
					fetchReplicatorDataForTypes(types);
				}
			},
			{ immediate: true }
		);

		// Sync replicator meta into the publish container so nested fields (e.g., entries
		// "value") resolve meta at metaPathPrefix.handle; otherwise they get null and crash.
		watch(
			[
				() => localTypes.value,
				() => typeReplicatorDataCache.value,
				() => props.value,
			],
			() => {
				const setFieldMeta = publishContext?.setFieldMeta;
				if (typeof setFieldMeta !== 'function') return;
				const names = propertyNames.value;
				if (!Array.isArray(names) || names.length === 0) return;
				names.forEach((propName) => {
					const metaPath = `${propertyReplicatorMetaPathPrefix(propName)}.${propertyReplicatorHandle}`;
					setFieldMeta(metaPath, replicatorMeta(propName));
				});
			},
			{ immediate: true, deep: true }
		);

		return {
			publishFieldsClass,
			typeFieldId,
			propertiesLabelId,
			typeOptions,
			localTypes,
			typesCacheKey,
			typeSelectorMultiple,
			comboboxModelValue,
			propertyNames,
			propertyModeOptions,
			propertyMode,
			propertyModeIsInherit,
			propertyModeIsDisable,
			propertyModeIsInheritCustom,
			propertyUsesReplicatorMode,
			modeInputId,
			onPropertyModeChange,
			typeLabel,
			typeDescriptions,
			propertyInstruction,
			fieldErrors,
			typeSectionErrors,
			inputId,
			replicatorConfig,
			replicatorMeta,
			propertyReplicatorValue,
			propertyReplicatorHandle,
			propertyReplicatorFieldPathPrefix,
			propertyReplicatorMetaPathPrefix,
			propertyReplicatorPathPrefix,
			onTypeChange,
			onReplicatorChange,
			onReplicatorMetaUpdate,
			replicatorDataLoadingForTypesKey,
			replicatorDataReadyForType,
			schemaOrgUrl,
			learnAboutPropertyLabel,
		};
	},
};
</script>
