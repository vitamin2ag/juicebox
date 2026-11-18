<script setup lang="ts">
import { Fieldtype } from '@statamic/cms';
import { Field, Input } from '@statamic/cms/ui';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';

type Coordinates = { latitude: number | string | null; longitude: number | string | null };

// Statamic defines the props and events every fieldtype takes; oxlint cannot see through the reference.
// oxlint-disable-next-line vue/valid-define-emits
const emit = defineEmits(Fieldtype.emits);
// oxlint-disable-next-line vue/valid-define-props
const props = defineProps(Fieldtype.props);
const { update, isReadOnly } = Fieldtype.use(emit, props);

const value = (props.value ?? {}) as Partial<Coordinates>;
const latitude = ref(value.latitude === null || value.latitude === undefined ? '' : String(value.latitude));
const longitude = ref(value.longitude === null || value.longitude === undefined ? '' : String(value.longitude));

const element = useTemplateRef<HTMLDivElement>('mapElement');
let map: L.Map | undefined;
let marker: L.Marker | undefined;

function position(): L.LatLngTuple | null {
    const lat = Number.parseFloat(latitude.value);
    const lng = Number.parseFloat(longitude.value);

    return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
}

function save(): void {
    update({ latitude: latitude.value === '' ? null : latitude.value, longitude: longitude.value === '' ? null : longitude.value });
}

function typed(): void {
    save();
    place(false);
}

function pick(lat: number, lng: number): void {
    latitude.value = lat.toFixed(6);
    longitude.value = lng.toFixed(6);
    save();
    place(false);
}

// Puts the marker where the fields point, creating it on first use, and pans there when asked to.
function place(pan: boolean): void {
    const target = position();

    if (!map || !target) {
        return;
    }

    if (marker) {
        marker.setLatLng(target);
    } else {
        marker = L.marker(target, {
            draggable: !isReadOnly.value,
            icon: L.divIcon({ className: 'juicebox-geo-pin', iconSize: [18, 18], iconAnchor: [9, 9] }),
        }).addTo(map);
        marker.on('dragend', () => {
            const { lat, lng } = marker!.getLatLng();
            pick(lat, lng);
        });
    }

    if (pan) {
        map.setView(target, 14);
    }
}

onMounted(() => {
    // Eastern Switzerland, until a position is set.
    map = L.map(element.value!, { center: [47.334169, 9.133759], zoom: 9, scrollWheelZoom: false });

    // Like Google Maps: the wheel zooms only with Ctrl or Cmd held, so scrolling the form never gets caught
    // by the map. This listener is added before Leaflet's own, which exists only while the handler is enabled.
    element.value!.addEventListener(
        'wheel',
        (event: WheelEvent) => {
            if (event.ctrlKey || event.metaKey) {
                map!.scrollWheelZoom.enable();
            } else {
                map!.scrollWheelZoom.disable();
            }
        },
        { capture: true },
    );
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    if (!isReadOnly.value) {
        map.on('click', (event: L.LeafletMouseEvent) => pick(event.latlng.lat, event.latlng.lng));
    }

    place(true);
});

onBeforeUnmount(() => map?.remove());
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <Field :label="__('Latitude')">
                <Input v-model="latitude" type="number" step="any" min="-90" max="90" :read-only="isReadOnly" @update:model-value="typed" />
            </Field>
            <Field :label="__('Longitude')">
                <Input
                    v-model="longitude"
                    type="number"
                    step="any"
                    min="-180"
                    max="180"
                    :read-only="isReadOnly"
                    @update:model-value="typed"
                />
            </Field>
        </div>
        <div ref="mapElement" class="relative z-0 h-72 w-full overflow-hidden rounded-lg border" />
    </div>
</template>

<style>
.juicebox-geo-pin {
    border: 3px solid #fff;
    border-radius: 9999px;
    background: #e11d48;
    box-shadow: 0 1px 4px rgb(0 0 0 / 0.5);
}
</style>
