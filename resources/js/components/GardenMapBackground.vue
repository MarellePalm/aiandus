<script setup lang="ts">
import L from 'leaflet';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import 'leaflet/dist/leaflet.css';

const props = withDefaults(
    defineProps<{
        centerLat: number;
        centerLng: number;
        widthCm: number;
        heightCm: number;
        /** Plaani CSS-zoom (sama mis MapView zoom). */
        plannerZoom: number;
        /** Zoom, millega aed mahub vaatesse. */
        fitZoom: number;
        /** Täpsem algvaade: suum keskpunkti ümber (m), mitte kogu krunt. */
        focusAnchorLat?: number | null;
        focusAnchorLng?: number | null;
        focusSpanMeters?: number;
        /** Hiirega liigutamine ja rullikuga suumimine. */
        interactive?: boolean;
    }>(),
    {
        focusSpanMeters: 0,
        interactive: false,
    },
);

const emit = defineEmits<{
    viewChange: [];
    mapClick: [{ lat: number; lng: number }];
    leafletZoomChange: [zoom: number];
}>();

/** Maa-ameti ortofoto WMTS (Web Mercator / GMC) – ametlik KVP URL. */
const ORTOFOTO_TILE_URL =
    'https://tiles.maaamet.ee/tm/wmts?service=WMTS&request=GetTile&version=1.0.0&layers=&styles=&tilematrixSet=GMC&format=image/png&layer=foto&tilematrix={z}&tilerow={y}&tilecol={x}';

/** Maa-ameti kache maksimaalne zoom. */
const MAX_NATIVE_ZOOM = 18;
/** Üleskaalatud suum täppide paigutamiseks (interaktiivne eelvaade). */
const MAX_MAP_ZOOM = 22;

const containerRef = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let tileLayer: L.TileLayer | null = null;
let resizeObserver: ResizeObserver | null = null;
let lastBoundsKey = '';
let anchorBaseZoom: number | null = null;

function boundsSyncKey(): string {
    return [
        props.centerLat,
        props.centerLng,
        props.widthCm,
        props.heightCm,
        props.focusAnchorLat,
        props.focusAnchorLng,
        props.focusSpanMeters,
    ].join('|');
}

function gardenBounds(): L.LatLngBounds {
    const widthM = Math.max(1, props.widthCm) / 100;
    const heightM = Math.max(1, props.heightCm) / 100;
    const latRad = (props.centerLat * Math.PI) / 180;
    const metersPerDegreeLat = 111_320;
    const metersPerDegreeLng = Math.max(
        1,
        metersPerDegreeLat * Math.cos(latRad),
    );
    const latHalf = heightM / 2 / metersPerDegreeLat;
    const lngHalf = widthM / 2 / metersPerDegreeLng;

    return L.latLngBounds(
        [props.centerLat - latHalf, props.centerLng - lngHalf],
        [props.centerLat + latHalf, props.centerLng + lngHalf],
    );
}

function focusBounds(): L.LatLngBounds | null {
    const lat = props.focusAnchorLat;
    const lng = props.focusAnchorLng;
    const span = props.focusSpanMeters;

    if (
        lat == null ||
        lng == null ||
        !Number.isFinite(lat) ||
        !Number.isFinite(lng) ||
        !span ||
        span <= 0
    ) {
        return null;
    }

    const latRad = (lat * Math.PI) / 180;
    const metersPerDegreeLat = 111_320;
    const metersPerDegreeLng = Math.max(
        1,
        metersPerDegreeLat * Math.cos(latRad),
    );
    const latHalf = span / 2 / metersPerDegreeLat;
    const lngHalf = span / 2 / metersPerDegreeLng;

    return L.latLngBounds(
        [lat - latHalf, lng - lngHalf],
        [lat + latHalf, lng + lngHalf],
    );
}

function fitTargetBounds(): L.LatLngBounds {
    return focusBounds() ?? gardenBounds();
}

function hasValidCenter(): boolean {
    return (
        Number.isFinite(props.centerLat) &&
        Number.isFinite(props.centerLng) &&
        Math.abs(props.centerLat) <= 90 &&
        Math.abs(props.centerLng) <= 180
    );
}

function maxMapZoom(): number {
    return MAX_MAP_ZOOM;
}

function sharpZoomFactor(): number {
    const fit = Math.max(props.fitZoom, 0.001);
    return Math.max(0.25, props.plannerZoom / fit);
}

function syncMapView() {
    if (!map || !hasValidCenter()) {
        return;
    }

    const bounds = fitTargetBounds();
    const center = focusBounds()?.getCenter() ?? bounds.getCenter();
    map.invalidateSize({ pan: false });

    const boundsKey = boundsSyncKey();
    const boundsChanged = boundsKey !== lastBoundsKey;
    if (boundsChanged) {
        lastBoundsKey = boundsKey;
        anchorBaseZoom = null;
    }

    if (anchorBaseZoom === null) {
        map.fitBounds(bounds, {
            padding: [16, 16],
            animate: false,
        });
        anchorBaseZoom = map.getZoom();
    }

    const extraZoom = Math.log2(sharpZoomFactor());
    const targetZoom = Math.min(
        maxMapZoom(),
        Math.max(0, (anchorBaseZoom ?? 0) + extraZoom),
    );

    map.setView(center, targetZoom, { animate: false });
}

function notifyViewChange() {
    emit('viewChange');
    if (map) {
        emit('leafletZoomChange', map.getZoom());
    }
}

function scheduleMapRefresh() {
    if (!map) {
        return;
    }

    requestAnimationFrame(() => {
        if (!map) {
            return;
        }

        map.invalidateSize({ pan: false });
        syncMapView();
        notifyViewChange();

        requestAnimationFrame(() => {
            if (!map) {
                return;
            }

            map.invalidateSize({ pan: false });
            syncMapView();
            notifyViewChange();
        });
    });
}

function initMap() {
    const container = containerRef.value;
    if (!container || map) {
        return;
    }

    const focus = focusBounds();
    const initialCenter = focus?.getCenter() ?? [
        props.centerLat,
        props.centerLng,
    ];

    map = L.map(container, {
        center: initialCenter as L.LatLngExpression,
        zoom: 17,
        maxZoom: maxMapZoom(),
        dragging: props.interactive,
        zoomControl: false,
        scrollWheelZoom: props.interactive,
        doubleClickZoom: props.interactive,
        boxZoom: false,
        keyboard: props.interactive,
        touchZoom: props.interactive,
        attributionControl: false,
        zoomSnap: 1,
        zoomDelta: props.interactive ? 1 : 1,
        wheelPxPerZoomLevel: props.interactive ? 60 : 60,
    });

    tileLayer = L.tileLayer(ORTOFOTO_TILE_URL, {
        maxZoom: MAX_MAP_ZOOM,
        maxNativeZoom: MAX_NATIVE_ZOOM,
        detectRetina: false,
        minZoom: 4,
        crossOrigin: 'anonymous',
        keepBuffer: 2,
    });
    tileLayer.addTo(map);

    map.on('moveend zoomend', notifyViewChange);

    if (props.interactive) {
        map.on('click', (event: L.LeafletMouseEvent) => {
            emit('mapClick', {
                lat: event.latlng.lat,
                lng: event.latlng.lng,
            });
        });
    }

    if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => {
            if (!map) {
                return;
            }
            map.invalidateSize({ pan: false });
            anchorBaseZoom = null;
            if (!props.interactive) {
                scheduleMapRefresh();
            } else {
                notifyViewChange();
            }
        });
        resizeObserver.observe(container);
    }

    scheduleMapRefresh();
}

function destroyMap() {
    resizeObserver?.disconnect();
    resizeObserver = null;
    tileLayer = null;
    map?.remove();
    map = null;
    lastBoundsKey = '';
    anchorBaseZoom = null;
}

function getMap(): L.Map | null {
    return map;
}

function zoomIn() {
    map?.zoomIn();
}

function zoomOut() {
    map?.zoomOut();
}

function resetView() {
    syncMapView();
    notifyViewChange();
}

onMounted(() => {
    void nextTick(() => {
        initMap();
    });
});

onBeforeUnmount(() => {
    destroyMap();
});

watch(
    () => {
        const keys: unknown[] = [
            props.centerLat,
            props.centerLng,
            props.widthCm,
            props.heightCm,
            props.focusAnchorLat,
            props.focusAnchorLng,
            props.focusSpanMeters,
        ];

        keys.push(props.plannerZoom, props.fitZoom);

        return keys;
    },
    () => {
        scheduleMapRefresh();
    },
);

defineExpose({
    getMap,
    zoomIn,
    zoomOut,
    resetView,
    syncMapView,
    scheduleMapRefresh,
});
</script>

<template>
    <div
        ref="containerRef"
        class="absolute inset-0 z-0"
        :class="interactive ? '' : 'pointer-events-none'"
        aria-hidden="true"
    />
    <p
        class="pointer-events-none absolute right-2 bottom-1 z-[1] text-[9px] leading-none text-white/80 drop-shadow-sm"
    >
        © Maa- ja Ruumiamet
    </p>
</template>

<style scoped>
:deep(.leaflet-container) {
    width: 100%;
    height: 100%;
    background: #e5e3df;
}
</style>
