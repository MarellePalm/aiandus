<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import GardenMapBackground from '@/components/GardenMapBackground.vue';
import {
    type GardenAreaApplyResult,
    type LatLngPoint,
    type ParcelBounds,
    latLngPolygonToApplyResult,
    latLngRingToGardenBounds,
} from '@/lib/gardenAreaSelection';

const props = defineProps<{
    mapFrame: ParcelBounds;
    polygonLatLng: LatLngPoint[];
    focusAnchorLat?: number | null;
    focusAnchorLng?: number | null;
}>();

const emit = defineEmits<{
    'update:polygonLatLng': [LatLngPoint[]];
    apply: [GardenAreaApplyResult];
}>();

const overlayRef = ref<HTMLElement | null>(null);
const mapBgRef = ref<InstanceType<typeof GardenMapBackground> | null>(null);
const mapViewTick = ref(0);
const mapZoomLevel = ref<number | null>(null);
const dragVertexIndex = ref<number | null>(null);

const focusAnchor = computed(() => {
    if (
        props.focusAnchorLat != null &&
        props.focusAnchorLng != null &&
        Number.isFinite(props.focusAnchorLat) &&
        Number.isFinite(props.focusAnchorLng)
    ) {
        return { lat: props.focusAnchorLat, lng: props.focusAnchorLng };
    }

    return {
        lat: props.mapFrame.centerLat,
        lng: props.mapFrame.centerLng,
    };
});

const focusSpanMeters = computed(() => 150);

const zoomPercentLabel = computed(() => {
    if (mapZoomLevel.value == null) {
        return '—';
    }

    return `${Math.round(mapZoomLevel.value)}`;
});

const polygonSvgPoints = computed(() => {
    void mapViewTick.value;
    return props.polygonLatLng
        .map((p) => {
            const pt = latLngToContainerPixel(p.lat, p.lng);
            return `${pt.x},${pt.y}`;
        })
        .join(' ');
});

const polygonSvgViewBox = computed(() => {
    const w = overlayRef.value?.clientWidth ?? 1;
    const h = overlayRef.value?.clientHeight ?? 1;
    return `0 0 ${w} ${h}`;
});

const areaLabel = computed(() => {
    const fmt = (n: number) => {
        const r = Math.round(n * 100) / 100;
        return Number.isInteger(r)
            ? `${r}`
            : r.toFixed(2).replace(/\.?0+$/, '');
    };

    if (props.polygonLatLng.length < 3) {
        return `${props.polygonLatLng.length} nurka (vaja vähemalt 3)`;
    }

    const b = latLngRingToGardenBounds(props.polygonLatLng);
    return `${fmt(b.widthMeters)} × ${fmt(b.heightMeters)} m · ${props.polygonLatLng.length} nurka`;
});

function bumpMapView() {
    mapViewTick.value += 1;
}

function latLngToContainerPixel(
    lat: number,
    lng: number,
): { x: number; y: number } {
    void mapViewTick.value;
    const map = mapBgRef.value?.getMap();
    const fallbackW = overlayRef.value?.clientWidth ?? 1;
    const fallbackH = overlayRef.value?.clientHeight ?? 1;

    if (!map) {
        return { x: fallbackW / 2, y: fallbackH / 2 };
    }

    const point = map.latLngToContainerPoint([lat, lng]);

    return { x: point.x, y: point.y };
}

function pointerToLatLng(clientX: number, clientY: number): LatLngPoint {
    const el = overlayRef.value;
    const map = mapBgRef.value?.getMap();

    if (!el || !map) {
        return { lat: focusAnchor.value.lat, lng: focusAnchor.value.lng };
    }

    const rect = el.getBoundingClientRect();
    const latlng = map.containerPointToLatLng([
        clientX - rect.left,
        clientY - rect.top,
    ]);

    return { lat: latlng.lat, lng: latlng.lng };
}

function emitPolygon(points: LatLngPoint[]) {
    emit('update:polygonLatLng', points);
    const result = latLngPolygonToApplyResult(points);
    if (result) {
        emit('apply', result);
    }
}

function onMapViewChange() {
    const map = mapBgRef.value?.getMap();
    if (map) {
        mapZoomLevel.value = map.getZoom();
    }
    bumpMapView();
}

function onMapClick(payload: { lat: number; lng: number }) {
    if (dragVertexIndex.value !== null) {
        return;
    }

    emitPolygon([
        ...props.polygonLatLng,
        { lat: payload.lat, lng: payload.lng },
    ]);
}

function changeMapZoom(delta: number) {
    if (!mapBgRef.value) {
        return;
    }

    if (delta > 0) {
        mapBgRef.value.zoomIn();
    } else {
        mapBgRef.value.zoomOut();
    }
}

function resetMapView() {
    mapBgRef.value?.resetView();
    bumpMapView();
}

function onVertexPointerDown(index: number, event: PointerEvent) {
    event.stopPropagation();
    if (event.button !== 0) {
        return;
    }

    dragVertexIndex.value = index;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function onVertexPointerMove(event: PointerEvent) {
    if (dragVertexIndex.value === null) {
        return;
    }

    const idx = dragVertexIndex.value;
    const next = props.polygonLatLng.map((p, i) =>
        i === idx ? pointerToLatLng(event.clientX, event.clientY) : p,
    );
    emitPolygon(next);
}

function onVertexPointerUp(event: PointerEvent) {
    if (dragVertexIndex.value === null) {
        return;
    }

    dragVertexIndex.value = null;
    try {
        (event.currentTarget as HTMLElement).releasePointerCapture(
            event.pointerId,
        );
    } catch {
        /* noop */
    }
}

function undoPolygonPoint() {
    if (!props.polygonLatLng.length) {
        return;
    }
    emitPolygon(props.polygonLatLng.slice(0, -1));
}

function clearPolygon() {
    emitPolygon([]);
}

watch(
    () => props.mapFrame,
    () => {
        if (props.polygonLatLng.length >= 3) {
            emitPolygon(props.polygonLatLng);
        }
        nextTick(() => mapBgRef.value?.scheduleMapRefresh());
    },
    { immediate: true },
);

onMounted(() => {
    const refresh = () => mapBgRef.value?.scheduleMapRefresh();
    nextTick(refresh);
    window.setTimeout(refresh, 150);
    window.setTimeout(refresh, 500);
});

onBeforeUnmount(() => {
    dragVertexIndex.value = null;
});
</script>

<template>
    <div class="mt-4">
        <p
            class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
        >
            Joonista aia piir
        </p>
        <p class="mt-1 text-sm leading-6 text-muted-foreground">
            Alusta tühjalt — iga nurk tekib klõpsuga ortofotol (vähemalt 3).
            Lohista rohelisi täppe täpsustamiseks. Suumi rullikuga või +/-,
            liiguta kaarti lohistades.
        </p>

        <div class="mt-2 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full border border-border/70 bg-card/80 px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted disabled:opacity-50"
                :disabled="!polygonLatLng.length"
                @click="undoPolygonPoint"
            >
                Võta tagasi
            </button>
            <button
                type="button"
                class="rounded-full border border-border/70 bg-card/80 px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted disabled:opacity-50"
                :disabled="!polygonLatLng.length"
                @click="clearPolygon"
            >
                Tühjenda kõik
            </button>
        </div>

        <div
            class="relative mt-3 h-[min(72vw,480px)] overflow-hidden rounded-2xl border border-border/70 ring-1 ring-border/50"
        >
            <GardenMapBackground
                :key="`${mapFrame.centerLat}-${mapFrame.centerLng}`"
                ref="mapBgRef"
                :center-lat="mapFrame.centerLat"
                :center-lng="mapFrame.centerLng"
                :width-cm="Math.round(mapFrame.widthMeters * 100)"
                :height-cm="Math.round(mapFrame.heightMeters * 100)"
                :focus-anchor-lat="focusAnchor.lat"
                :focus-anchor-lng="focusAnchor.lng"
                :focus-span-meters="focusSpanMeters"
                :planner-zoom="1"
                :fit-zoom="1"
                free-zoom
                interactive
                @view-change="onMapViewChange"
                @map-click="onMapClick"
            />

            <div
                class="pointer-events-none absolute top-2 right-2 z-20 flex items-center gap-1 rounded-full border border-white/80 bg-black/55 p-1 shadow-lg backdrop-blur-sm"
            >
                <button
                    type="button"
                    class="pointer-events-auto inline-flex h-7 w-7 items-center justify-center rounded-full text-white transition hover:bg-white/20"
                    aria-label="Suumi välja"
                    @click="changeMapZoom(-1)"
                >
                    <span class="material-symbols-outlined text-base"
                        >remove</span
                    >
                </button>
                <button
                    type="button"
                    class="pointer-events-auto inline-flex h-7 min-w-11 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white"
                    title="Lähtesta suum ja asukoht"
                    @click="resetMapView"
                >
                    {{ zoomPercentLabel }}
                </button>
                <button
                    type="button"
                    class="pointer-events-auto inline-flex h-7 w-7 items-center justify-center rounded-full text-white transition hover:bg-white/20"
                    aria-label="Suumi sisse"
                    @click="changeMapZoom(1)"
                >
                    <span class="material-symbols-outlined text-base">add</span>
                </button>
            </div>

            <div
                ref="overlayRef"
                class="pointer-events-none absolute inset-0 z-10"
            >
                <div class="absolute inset-0 bg-black/10" />
                <svg
                    class="absolute inset-0 h-full w-full"
                    :viewBox="polygonSvgViewBox"
                >
                    <polygon
                        v-if="polygonLatLng.length >= 3"
                        :points="polygonSvgPoints"
                        fill="rgba(76, 130, 95, 0.28)"
                        stroke="rgb(76, 130, 95)"
                        stroke-width="0.6"
                        vector-effect="non-scaling-stroke"
                    />
                    <polyline
                        v-else-if="polygonLatLng.length >= 2"
                        :points="polygonSvgPoints"
                        fill="none"
                        stroke="rgb(76, 130, 95)"
                        stroke-width="0.6"
                        stroke-dasharray="2 1"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>
                <button
                    v-for="(point, index) in polygonLatLng"
                    :key="`vertex-${index}`"
                    type="button"
                    class="pointer-events-auto absolute z-20 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary shadow-md ring-2 ring-primary/30"
                    :style="{
                        left: `${latLngToContainerPixel(point.lat, point.lng).x}px`,
                        top: `${latLngToContainerPixel(point.lat, point.lng).y}px`,
                    }"
                    :aria-label="`Nurk ${index + 1}`"
                    @pointerdown="onVertexPointerDown(index, $event)"
                    @pointermove="onVertexPointerMove"
                    @pointerup="onVertexPointerUp"
                    @pointercancel="onVertexPointerUp"
                    @click.stop
                />
                <span
                    class="absolute top-2 left-2 max-w-[min(100%,18rem)] rounded-full bg-primary px-2 py-0.5 text-[10px] leading-snug font-semibold text-primary-foreground shadow-sm"
                >
                    {{ areaLabel }}
                </span>
            </div>
        </div>
    </div>
</template>
