<!--
 * GPS / Map coordinate fieldtype.
 *
 * Interactive Leaflet + OpenStreetMap picker. Click the map or drag the marker
 * to set the location; the read-only latitude / longitude fields display the
 * current pin, and a paste box accepts decimal / DMS / geo URI / UTM. A
 * Street / Aerial button group toggles the base layer, and on-map buttons reset
 * the view, center on the pin, and clear it. The form controls are Statamic UI
 * components (Field / Input / Button); the stored value is a structured
 * { lat, lng, zoom } object. Not a fork of a Statamic component.
 -->
<template>
	<GroupFieldtypeShell>
		<Field
			v-if="addressSearchEnabled && !isReadOnly"
			:id="searchId"
			:label="__('statamic::fieldtypes.gps.field.address_search_label')"
			:instructions="__('statamic::fieldtypes.gps.field.address_search_instructions')"
			:error="searchError"
		>
			<Input
				:id="searchId"
				v-model="searchInput"
				:placeholder="__('statamic::fieldtypes.gps.field.address_search_placeholder')"
				autocomplete="off"
				@input="onSearchInput"
				@keydown.enter.prevent="searchNow"
			/>
			<div v-if="searchPending" class="gps-search-status mt-1">
				{{ __('statamic::fieldtypes.gps.field.address_search_searching') }}
			</div>
			<ul v-else-if="searchResults.length" class="gps-search-results mt-1">
				<li v-for="(result, index) in searchResults" :key="index">
					<button type="button" class="gps-search-result" @click="selectResult(result)">
						{{ result.label }}
					</button>
				</li>
			</ul>
			<div v-else-if="searchedEmpty" class="gps-search-status mt-1">
				{{ __('statamic::fieldtypes.gps.field.address_search_no_results') }}
			</div>
			<Description class="mt-1" :text="__('statamic::fieldtypes.gps.field.address_search_attribution')" />
		</Field>

		<Field
			v-if="!isReadOnly"
			:id="pasteId"
			:label="__('statamic::fieldtypes.gps.field.paste_label')"
			:instructions="__('statamic::fieldtypes.gps.field.paste_instructions')"
			:error="pasteError"
		>
			<div class="flex gap-2">
				<Input
					:id="pasteId"
					v-model="pasteInput"
					class="flex-1"
					:placeholder="pastePlaceholder"
					@keydown.enter.prevent="applyPaste"
				/>
				<Button type="button" variant="primary" @click="applyPaste">{{ __('statamic::fieldtypes.gps.field.set') }}</Button>
			</div>
		</Field>

		<Field
			:label="__('statamic::fieldtypes.gps.field.map_label')"
			:instructions="__('statamic::fieldtypes.gps.field.map_instructions')"
		>
			<div
				ref="map"
				class="gps-fieldtype-map w-full rounded-md border border-gray-500 dark:border-gray-700"
				style="height: 400px"
			></div>
		</Field>

		<div>
			<Heading size="lg" :text="__('statamic::fieldtypes.gps.field.generated_heading')" />
			<Description
				class="mt-1"
				:text="__('statamic::fieldtypes.gps.field.generated_description')"
			/>

			<div class="grid grid-cols-2 gap-4 mt-3">
				<Field :id="latId" :label="__('statamic::fieldtypes.gps.field.latitude')">
					<Input
						:id="latId"
						:model-value="latitude"
						read-only
						:placeholder="__('statamic::fieldtypes.gps.field.not_set')"
					/>
				</Field>
				<Field :id="lngId" :label="__('statamic::fieldtypes.gps.field.longitude')">
					<Input
						:id="lngId"
						:model-value="longitude"
						read-only
						:placeholder="__('statamic::fieldtypes.gps.field.not_set')"
					/>
				</Field>
			</div>
		</div>
	</GroupFieldtypeShell>
</template>

<script>
// Options API: depends on FieldtypeMixin (this.update(), value/config/meta).
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Button, Description, Field, Heading, Input } from '@statamic/cms/ui';
import { markRaw } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { parseCoordinates } from '../../lib/parse-coordinates';
import { resultToStoredCoordinate } from '../../lib/nominatim';
import GroupFieldtypeShell from './partials/GroupFieldtypeShell.vue';

// Leaflet's default marker resolves its image URLs relative to the CSS, which
// breaks under a bundler. Point the default icon at the bundled asset URLs.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
	iconRetinaUrl: markerIcon2x,
	iconUrl: markerIcon,
	shadowUrl: markerShadow,
});

export default {
	components: { Button, Description, Field, GroupFieldtypeShell, Heading, Input },

	mixins: [Fieldtype],

	data() {
		return {
			map: null,
			marker: null,
			resizeObserver: null,
			baseLayers: null,
			activeView: 'street',
			streetButton: null,
			aerialButton: null,
			viewControl: null,
			mapActionsControl: null,
			centerButton: null,
			clearButton: null,
			pasteInput: '',
			pasteError: null,
			searchInput: '',
			searchResults: [],
			searchPending: false,
			searchedEmpty: false,
			searchError: null,
			searchDebounce: null,
			searchSequence: 0,
		};
	},

	computed: {
		latitude() {
			return this.value?.lat ?? null;
		},
		longitude() {
			return this.value?.lng ?? null;
		},
		hasPin() {
			return this.value?.lat != null && this.value?.lng != null;
		},
		latId() {
			return `field_${this.handle}_lat`;
		},
		lngId() {
			return `field_${this.handle}_lng`;
		},
		pasteId() {
			return `field_${this.handle}_paste`;
		},
		searchId() {
			return `field_${this.handle}_address_search`;
		},
		addressSearchEnabled() {
			return this.meta?.addressSearchEnabled ?? false;
		},
		addressSearchMinLength() {
			return this.meta?.addressSearchMinLength ?? 3;
		},
		pastePlaceholder() {
			return `e.g., ${this.defaultLatitude}, ${this.defaultLongitude}`;
		},
		defaultLatitude() {
			return this.meta?.defaultLatitude ?? 0;
		},
		defaultLongitude() {
			return this.meta?.defaultLongitude ?? 0;
		},
		defaultZoom() {
			return this.meta?.defaultZoom ?? 14;
		},
		tileUrl() {
			return this.meta?.tileUrl ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
		},
		tileAttribution() {
			return (
				this.meta?.tileAttribution ??
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
			);
		},
		tileMaxZoom() {
			return this.meta?.tileMaxZoom ?? 19;
		},
		aerialUrl() {
			return this.meta?.aerialUrl ?? null;
		},
		aerialAttribution() {
			return this.meta?.aerialAttribution ?? '';
		},
		aerialMaxZoom() {
			return this.meta?.aerialMaxZoom ?? 19;
		},
		aerialMaxNativeZoom() {
			return this.meta?.aerialMaxNativeZoom ?? 19;
		},
	},

	watch: {
		// The on-map Center on Pin / Clear actions only make sense with a pin,
		// so they are shown once one exists and hidden again when it is cleared.
		hasPin() {
			this.updateMapActions();
		},
	},

	mounted() {
		this.initMap();
	},

	beforeUnmount() {
		if (this.searchDebounce) {
			clearTimeout(this.searchDebounce);
			this.searchDebounce = null;
		}

		if (this.resizeObserver) {
			this.resizeObserver.disconnect();
			this.resizeObserver = null;
		}

		if (this.map) {
			this.map.remove();
			this.map = null;
		}
	},

	methods: {
		initMap() {
			const lat = this.value?.lat ?? this.defaultLatitude;
			const lng = this.value?.lng ?? this.defaultLongitude;
			const zoom = this.value?.zoom ?? this.defaultZoom;

			// markRaw keeps Leaflet's map (and the marker / layers / controls,
			// below) out of Vue's reactive proxy. A reactive Leaflet map breaks
			// Leaflet's internal event-handler identity: when switching the
			// Street/Aerial base layer removes a layer, map.off() can't match the
			// handlers map.on() bound, so the removed layer stays subscribed and
			// fires its zoom handler on the next zoom against a now-null _map —
			// the CP-only "_updateLevels/_resetView: cannot read
			// 'project'/'getCenter' of null" crash that never reproduced in a
			// plain (non-Vue) page. zoomSnap/zoomDelta pin whole-level steps.
			this.map = markRaw(
				L.map(this.$refs.map, {
					zoomSnap: 1,
					zoomDelta: 1,
				}).setView([lat, lng], zoom)
			);

			// Tile sources are config-driven (config/maps.php) so a provider can
			// be switched without a code change, per the OSM tile usage policy.
			// Street is the default base; an optional aerial layer (OSM has no
			// imagery, so it comes from a separate provider, e.g. Esri) is offered
			// via an always-visible Street / Aerial button group on the map.
			const street = markRaw(
				L.tileLayer(this.tileUrl, {
					attribution: this.tileAttribution,
					maxZoom: this.tileMaxZoom,
				})
			);
			this.baseLayers = { street };
			this.activeView = 'street';
			street.addTo(this.map);

			if (this.aerialUrl) {
				// maxNativeZoom is the deepest level with real imagery; Leaflet
				// stretches its own tiles past it instead of re-fetching the
				// identical upsampled tiles Esri serves at finer levels.
				this.baseLayers.aerial = markRaw(
					L.tileLayer(this.aerialUrl, {
						attribution: this.aerialAttribution,
						maxZoom: this.aerialMaxZoom,
						maxNativeZoom: this.aerialMaxNativeZoom,
					})
				);
				this.addViewControl();
			}

			this.addMapActionsControl();

			if (this.hasPin) {
				this.placeMarker(this.value.lat, this.value.lng);
			}

			if (!this.isReadOnly) {
				this.map.on('click', (e) => this.setLatLng(e.latlng.lat, e.latlng.lng));
			}

			this.map.on('zoomend', () => this.persistZoom());

			// The map mounts inside the publish form before its container has
			// settled to full width (tabs, responsive columns), which leaves the
			// tile grid sized for the wrong viewport. Recompute on every resize —
			// the observer also fires once on observe, fixing the initial paint.
			this.$nextTick(() => this.map && this.map.invalidateSize());

			if (window.ResizeObserver) {
				this.resizeObserver = new ResizeObserver(() => {
					if (this.map) {
						this.map.invalidateSize();
					}
				});
				this.resizeObserver.observe(this.$refs.map);
			}
		},

		addViewControl() {
			const component = this;

			// A custom Leaflet control gives an always-visible Street / Aerial
			// toggle styled like the zoom buttons, instead of the default layers
			// popover that hides the choice behind an icon. It lives in Leaflet's
			// control pane (outside Vue's render tree), so it is plain DOM rather
			// than a Statamic UI Button.
			const ViewControl = L.Control.extend({
				options: { position: 'topright' },
				onAdd() {
					const container = L.DomUtil.create('div', 'leaflet-bar gps-view-control');
					component.streetButton = component.createViewButton(container, component.__('statamic::fieldtypes.gps.field.street'), 'street');
					component.aerialButton = component.createViewButton(container, component.__('statamic::fieldtypes.gps.field.aerial'), 'aerial');
					L.DomEvent.disableClickPropagation(container).disableScrollPropagation(container);

					return container;
				},
			});

			this.viewControl = markRaw(new ViewControl());
			this.viewControl.addTo(this.map);
			this.updateViewButtons();
		},

		createViewButton(container, label, view) {
			const button = L.DomUtil.create('button', 'gps-view-button', container);
			button.type = 'button';
			button.textContent = label;

			L.DomEvent.on(button, 'click', (event) => {
				L.DomEvent.stop(event);
				this.switchBaseLayer(view);
			});

			return button;
		},

		addMapActionsControl() {
			const component = this;

			// On-map action buttons (bottom-left), styled like the other map
			// controls. Plain DOM (a Leaflet control) rather than Statamic UI
			// Buttons because it lives in Leaflet's control pane, outside Vue's
			// render tree. Reset View is always available; Center on Pin / Clear
			// join it only once a pin exists.
			const MapActionsControl = L.Control.extend({
				options: { position: 'bottomleft' },
				onAdd() {
					const container = L.DomUtil.create('div', 'leaflet-bar gps-view-control');
					component.createActionButton(container, component.__('statamic::fieldtypes.gps.field.reset_view'), () => component.resetView());
					component.centerButton = component.createActionButton(container, component.__('statamic::fieldtypes.gps.field.center_on_pin'), () => component.centerOnPin());

					if (!component.isReadOnly) {
						component.clearButton = component.createActionButton(container, component.__('statamic::fieldtypes.gps.field.clear'), () => component.clear());
					}

					L.DomEvent.disableClickPropagation(container).disableScrollPropagation(container);

					return container;
				},
			});

			this.mapActionsControl = markRaw(new MapActionsControl());
			this.mapActionsControl.addTo(this.map);
			this.updateMapActions();
		},

		createActionButton(container, label, onClick) {
			const button = L.DomUtil.create('button', 'gps-view-button', container);
			button.type = 'button';
			button.textContent = label;

			L.DomEvent.on(button, 'click', (event) => {
				L.DomEvent.stop(event);
				onClick();
			});

			return button;
		},

		resetView() {
			// View-only: re-frame the map on the configured default location and
			// zoom without touching the stored pin (Clear handles the value).
			this.map.setView([this.defaultLatitude, this.defaultLongitude], this.defaultZoom);
		},

		updateMapActions() {
			// Center on Pin / Clear only make sense with a pin; Reset View stays.
			const display = this.hasPin ? '' : 'none';

			if (this.centerButton) {
				this.centerButton.style.display = display;
			}

			if (this.clearButton) {
				this.clearButton.style.display = display;
			}
		},

		switchBaseLayer(view) {
			if (view === this.activeView || !this.baseLayers?.[view]) {
				return;
			}

			this.baseLayers[view].addTo(this.map);
			this.map.removeLayer(this.baseLayers[this.activeView]);
			this.activeView = view;
			this.updateViewButtons();
		},

		updateViewButtons() {
			if (this.streetButton) {
				this.streetButton.classList.toggle('gps-view-button--active', this.activeView === 'street');
			}

			if (this.aerialButton) {
				this.aerialButton.classList.toggle('gps-view-button--active', this.activeView === 'aerial');
			}
		},

		centerOnPin() {
			if (!this.hasPin) {
				return;
			}

			this.map.setView([this.value.lat, this.value.lng], this.value.zoom ?? this.map.getZoom());
		},

		applyPaste() {
			const parsed = parseCoordinates(this.pasteInput);

			if (!parsed) {
				this.pasteError = this.__('statamic::fieldtypes.gps.field.paste_error');

				return;
			}

			this.pasteError = null;
			this.pasteInput = '';
			this.map.panTo([parsed.lat, parsed.lng]);
			this.setLatLng(parsed.lat, parsed.lng);
		},

		placeMarker(lat, lng) {
			if (this.marker) {
				this.marker.setLatLng([lat, lng]);

				return;
			}

			this.marker = markRaw(L.marker([lat, lng], { draggable: !this.isReadOnly }).addTo(this.map));

			this.marker.on('dragend', () => {
				const { lat, lng } = this.marker.getLatLng();
				this.setLatLng(lat, lng);
			});
		},

		setLatLng(lat, lng) {
			const coordinate = resultToStoredCoordinate({ lat, lng }, this.map.getZoom());

			if (!coordinate) {
				return;
			}

			this.placeMarker(coordinate.lat, coordinate.lng);
			this.update(coordinate);
		},

		onSearchInput() {
			this.searchError = null;

			if (this.searchDebounce) {
				clearTimeout(this.searchDebounce);
				this.searchDebounce = null;
			}

			// Below the minimum length there is nothing worth geocoding; clear any
			// prior results so the dropdown does not show stale matches. Bumping the
			// sequence also invalidates any still in-flight request, so a slower
			// earlier response cannot repopulate the just-cleared dropdown.
			if (this.searchInput.trim().length < this.addressSearchMinLength) {
				this.searchSequence++;
				this.searchResults = [];
				this.searchedEmpty = false;
				this.searchPending = false;

				return;
			}

			// Debounce keystrokes so a typed address issues one request, honoring
			// the Nominatim usage policy's rate expectations.
			this.searchDebounce = setTimeout(() => this.searchNow(), 400);
		},

		async searchNow() {
			if (this.searchDebounce) {
				clearTimeout(this.searchDebounce);
				this.searchDebounce = null;
			}

			const query = this.searchInput.trim();

			if (query.length < this.addressSearchMinLength) {
				return;
			}

			// A monotonic sequence guards against out-of-order responses: a slower
			// earlier request must not overwrite the results of a newer one.
			const sequence = ++this.searchSequence;
			this.searchPending = true;
			this.searchedEmpty = false;
			this.searchError = null;

			try {
				const { data } = await this.$axios.get(cp_url('gps/nominatim-search'), {
					params: { q: query },
				});

				if (sequence !== this.searchSequence) {
					return;
				}

				this.searchResults = Array.isArray(data?.results) ? data.results : [];
				this.searchedEmpty = this.searchResults.length === 0;
			} catch (error) {
				if (sequence !== this.searchSequence) {
					return;
				}

				this.searchResults = [];
				this.searchedEmpty = false;
				this.searchError = this.__('statamic::fieldtypes.gps.field.address_search_error');
			} finally {
				if (sequence === this.searchSequence) {
					this.searchPending = false;
				}
			}
		},

		selectResult(result) {
			const lat = Number(result?.lat);
			const lng = Number(result?.lng);

			if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
				return;
			}

			// Finalize the selection: cancel any queued debounce and invalidate any
			// in-flight request first. Otherwise a still-armed debounce would fire
			// searchNow() against the selected label and reopen the dropdown over
			// the choice, and a slow in-flight response would repopulate it.
			if (this.searchDebounce) {
				clearTimeout(this.searchDebounce);
				this.searchDebounce = null;
			}
			this.searchSequence++;
			this.searchPending = false;
			this.searchResults = [];
			this.searchedEmpty = false;
			this.searchInput = result?.label ?? this.searchInput;
			this.map.panTo([lat, lng]);
			this.setLatLng(lat, lng);
		},

		persistZoom() {
			// Only record the framed zoom once a coordinate exists. Use a null
			// check (not falsy) so a valid pin at latitude 0 still persists its
			// zoom, consistent with hasPin / setLatLng.
			if (this.value?.lat == null) {
				return;
			}

			this.update({ ...this.value, zoom: Math.round(this.map.getZoom()) });
		},

		clear() {
			if (this.marker) {
				this.map.removeLayer(this.marker);
				this.marker = null;
			}

			this.update(null);
		},
	},
};
</script>

<style>
/*
 * Street / Aerial base-layer toggle — an always-visible on-map control styled
 * to match Leaflet's zoom buttons (white, bordered) rather than the default
 * layers popover. Not scoped: the control DOM is created imperatively by
 * Leaflet, outside this component's template, so scoped attributes never reach
 * it. Class names are prefixed `gps-view-` to avoid leaking.
 */
.gps-view-control {
	display: flex;
	overflow: hidden;
}

.gps-view-button {
	-webkit-appearance: none;
	appearance: none;
	height: 30px;
	padding: 0 0.625rem;
	border: 0;
	background: #fff;
	color: #333;
	font-size: 12px;
	font-weight: 600;
	line-height: 30px;
	cursor: pointer;
}

.gps-view-button + .gps-view-button {
	border-left: 1px solid #ccc;
}

.gps-view-button:hover {
	background: #f4f4f4;
}

.gps-view-button--active {
	background: #e2e8f0;
	color: #111;
	cursor: default;
}

/*
 * Address-search results: a compact list of selectable geocoding matches and
 * the searching / no-results status line, styled to sit unobtrusively under the
 * search input. Each result is a full-width button so the whole row is a target.
 */
.gps-search-status {
	font-size: 0.8125rem;
	color: #6b7280;
}

.gps-search-results {
	list-style: none;
	margin: 0;
	padding: 0;
	border: 1px solid #e2e8f0;
	border-radius: 0.375rem;
	overflow: hidden;
}

.gps-search-result {
	display: block;
	width: 100%;
	padding: 0.375rem 0.625rem;
	border: 0;
	background: #fff;
	color: #1f2937;
	font-size: 0.8125rem;
	text-align: left;
	cursor: pointer;
}

.gps-search-results li + li .gps-search-result {
	border-top: 1px solid #e2e8f0;
}

.gps-search-result:hover,
.gps-search-result:focus-visible {
	background: #f4f4f4;
}
</style>
