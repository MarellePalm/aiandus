<script setup lang="ts">
import type { Map as LeafletMap } from 'leaflet';
import { computed, ref, watch, withDefaults } from 'vue';

import type { LatLngPoint } from '@/lib/gardenAreaSelection';

const props = withDefaults(
    defineProps<{
        ring: LatLngPoint[];
        getMap: () => LeafletMap | null;
        /** Täida ainult tegeliku joonistatud polügooni puhul. */
        showFill?: boolean;
    }>(),
    {
        showFill: true,
    },
);

const overlayRef = ref<HTMLElement | null>(null);
const mapViewTick = ref(0);

const polygonSvgPoints = computed(() => {
    void mapViewTick.value;
    const map = props.getMap();

    if (!map || props.ring.length < 2) {
        return '';
    }

    return props.ring
        .map((p) => {
            const pt = map.latLngToContainerPoint([p.lat, p.lng]);
            return `${pt.x},${pt.y}`;
        })
        .join(' ');
});

const polygonSvgViewBox = computed(() => {
    const w = overlayRef.value?.clientWidth ?? 1;
    const h = overlayRef.value?.clientHeight ?? 1;
    return `0 0 ${w} ${h}`;
});

function bump() {
    mapViewTick.value += 1;
}

defineExpose({ bump });

watch(
    () => props.ring,
    () => bump(),
    { deep: true },
);
</script>

<template>
    <div ref="overlayRef" class="pointer-events-none absolute inset-0 z-[6]">
        <svg
            v-if="ring.length >= 2"
            class="absolute inset-0 h-full w-full"
            :viewBox="polygonSvgViewBox"
        >
            <polygon
                v-if="ring.length >= 3 && showFill"
                :points="polygonSvgPoints"
                fill="rgba(76, 130, 95, 0.22)"
                stroke="rgb(76, 130, 95)"
                stroke-width="2"
                vector-effect="non-scaling-stroke"
            />
            <polygon
                v-else-if="ring.length >= 3"
                :points="polygonSvgPoints"
                fill="none"
                stroke="rgb(76, 130, 95)"
                stroke-width="2"
                vector-effect="non-scaling-stroke"
            />
            <polyline
                v-else
                :points="polygonSvgPoints"
                fill="none"
                stroke="rgb(76, 130, 95)"
                stroke-width="2"
                stroke-dasharray="4 2"
                vector-effect="non-scaling-stroke"
            />
        </svg>
    </div>
</template>
