<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import {
    bedFootprintPx,
    gardenPositionFromClick,
    gardenSizeCm,
    gardenSurfacePx,
    gridStepPx,
    planShapeCellCm,
    shapeMaskClipRects,
    type GardenPlacementBed,
    type GardenPlacementPlan,
} from '@/pages/map/bedGardenPlacement';
import { CM_TO_PX } from '@/pages/map/constants';

const props = defineProps<{
    gardenPlan: GardenPlacementPlan;
    existingBeds: GardenPlacementBed[];
    draftBed: GardenPlacementBed;
    gardenX: number | null;
    gardenY: number | null;
}>();

const emit = defineEmits<{
    'update:gardenX': [value: number];
    'update:gardenY': [value: number];
}>();

const viewportRef = ref<HTMLElement | null>(null);
const viewportWidth = ref(0);
let resizeObserver: ResizeObserver | null = null;

const surface = computed(() => gardenSurfacePx(props.gardenPlan));
const draftSize = computed(() => bedFootprintPx(props.draftBed));

const fitScale = computed(() => {
    const w = viewportWidth.value;
    if (!w) {
        return 1;
    }
    return Math.min(1, (w - 16) / surface.value.width);
});

const displayWidth = computed(() =>
    Math.round(surface.value.width * fitScale.value),
);
const displayHeight = computed(() =>
    Math.round(surface.value.height * fitScale.value),
);

const gridMajorGardenPx = computed(() => {
    const cell = planShapeCellCm(props.gardenPlan);
    const majorCm = cell >= 100 ? cell : Math.min(100, Math.max(cell * 2, 50));
    return Math.max(
        gridStepPx(props.gardenPlan),
        Math.round(majorCm * CM_TO_PX),
    );
});

const clipRects = computed(() =>
    shapeMaskClipRects(
        props.gardenPlan,
        surface.value.width,
        surface.value.height,
    ),
);

const useShapeClip = computed(() => clipRects.value.length > 0);

const clipPathId = computed(
    () => `add-bed-garden-clip-${props.gardenPlan.width}`,
);

const gardenSurfaceStyle = computed(() => {
    const minor = 'rgba(34, 98, 58, 0.12)';
    const major = 'rgba(34, 98, 58, 0.24)';

    const style: Record<string, string> = {
        width: `${surface.value.width}px`,
        height: `${surface.value.height}px`,
        backgroundColor: 'rgba(240, 250, 235, 0.98)',
        backgroundImage: [
            `linear-gradient(${minor} 1px, transparent 1px)`,
            `linear-gradient(90deg, ${minor} 1px, transparent 1px)`,
            `linear-gradient(${major} 1.5px, transparent 1.5px)`,
            `linear-gradient(90deg, ${major} 1.5px, transparent 1.5px)`,
        ].join(', '),
        backgroundSize: [
            `${gridStepPx(props.gardenPlan)}px ${gridStepPx(props.gardenPlan)}px`,
            `${gridStepPx(props.gardenPlan)}px ${gridStepPx(props.gardenPlan)}px`,
            `${gridMajorGardenPx.value}px ${gridMajorGardenPx.value}px`,
            `${gridMajorGardenPx.value}px ${gridMajorGardenPx.value}px`,
        ].join(', '),
    };

    if (useShapeClip.value) {
        style.clipPath = `url(#${clipPathId.value})`;
    }

    return style;
});

const canvasTransformStyle = computed(() => ({
    transform: `scale(${fitScale.value})`,
    transformOrigin: 'top left',
    width: `${surface.value.width}px`,
    height: `${surface.value.height}px`,
}));

const scaleBarPx = computed(() => Math.round(100 * CM_TO_PX * fitScale.value));

const draftPosition = computed(() => {
    if (props.gardenX == null || props.gardenY == null) {
        return null;
    }

    return {
        x: props.gardenX,
        y: props.gardenY,
    };
});

function syncViewportWidth() {
    viewportWidth.value = viewportRef.value?.clientWidth ?? 0;
}

function onCanvasClick(event: MouseEvent) {
    const layer = event.currentTarget as HTMLElement | null;
    if (!layer) {
        return;
    }

    const rect = layer.getBoundingClientRect();
    const scale = Math.max(fitScale.value, 0.001);
    const x = (event.clientX - rect.left) / scale;
    const y = (event.clientY - rect.top) / scale;
    const next = gardenPositionFromClick(x, y, draftSize.value, props.gardenPlan);

    emit('update:gardenX', next.x);
    emit('update:gardenY', next.y);
}

function existingBedStyle(bed: GardenPlacementBed): Record<string, string> {
    const size = bedFootprintPx(bed);

    return {
        left: `${bed.garden_x}px`,
        top: `${bed.garden_y}px`,
        width: `${size.width}px`,
        height: `${size.height}px`,
    };
}

function draftBedStyle(): Record<string, string> | null {
    if (!draftPosition.value) {
        return null;
    }

    return {
        left: `${draftPosition.value.x}px`,
        top: `${draftPosition.value.y}px`,
        width: `${draftSize.value.width}px`,
        height: `${draftSize.value.height}px`,
    };
}

const gardenLabel = computed(() => {
    const { widthCm, heightCm } = gardenSizeCm(props.gardenPlan);
    const w = widthCm / 100;
    const h = heightCm / 100;
    return `${w} m × ${h} m`;
});

onMounted(() => {
    syncViewportWidth();
    if (typeof ResizeObserver !== 'undefined' && viewportRef.value) {
        resizeObserver = new ResizeObserver(() => syncViewportWidth());
        resizeObserver.observe(viewportRef.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
});
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm leading-6 text-muted-foreground">
            Klõpsa aiaplaanil, kuhu peenar tuleb. Ruudustik on mõõtkavas ({{
                gardenLabel
            }}); sinu peenar kuvatakse amberina.
        </p>

        <div
            ref="viewportRef"
            class="relative w-full overflow-auto rounded-[1.25rem] border border-emerald-900/15 bg-[linear-gradient(180deg,rgba(251,248,241,0.98),rgba(241,247,235,0.98))] p-2 shadow-inner"
            style="max-height: min(58vh, 520px)"
        >
            <div
                class="relative mx-auto"
                :style="{
                    width: `${displayWidth}px`,
                    height: `${displayHeight}px`,
                }"
            >
                <div
                    class="absolute top-0 left-0 cursor-crosshair touch-none"
                    :style="canvasTransformStyle"
                    role="button"
                    tabindex="0"
                    aria-label="Vali peenra asukoht aiaplaanil"
                    @click="onCanvasClick"
                    @keydown.enter.prevent
                >
                    <svg
                        v-if="useShapeClip"
                        class="pointer-events-none absolute size-0 overflow-hidden"
                        aria-hidden="true"
                    >
                        <defs>
                            <clipPath
                                :id="clipPathId"
                                clipPathUnits="userSpaceOnUse"
                            >
                                <rect
                                    v-for="(rect, index) in clipRects"
                                    :key="`clip-${index}`"
                                    :x="rect.x"
                                    :y="rect.y"
                                    :width="rect.w"
                                    :height="rect.h"
                                />
                            </clipPath>
                        </defs>
                    </svg>

                    <div
                        class="relative"
                        :style="gardenSurfaceStyle"
                    >
                        <div
                            v-for="(bed, index) in existingBeds"
                            :key="`existing-bed-${index}`"
                            class="pointer-events-none absolute rounded-sm border border-slate-500/35 bg-slate-200/55"
                            :style="existingBedStyle(bed)"
                            aria-hidden="true"
                        />

                        <div
                            v-if="draftBedStyle()"
                            class="pointer-events-none absolute z-10 rounded-sm border-2 border-amber-800/50 bg-amber-100/85 shadow-md ring-4 ring-primary/20"
                            :style="draftBedStyle()!"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </div>

            <div
                class="pointer-events-none absolute bottom-3 left-3 z-20 rounded-xl border border-emerald-900/10 bg-white/85 px-2.5 py-1.5 text-[11px] font-medium text-emerald-950/80 shadow-sm backdrop-blur-sm"
            >
                <div class="mb-0.5 tracking-[0.14em] uppercase opacity-70">
                    Mõõtkava
                </div>
                <div class="flex items-center gap-2">
                    <div
                        class="h-1.5 rounded-full bg-emerald-700/75"
                        :style="{ width: `${scaleBarPx}px` }"
                    />
                    <span class="font-semibold">1 m</span>
                </div>
            </div>
        </div>

        <p
            v-if="gardenX != null && gardenY != null"
            class="text-sm font-medium text-emerald-800 dark:text-emerald-100"
        >
            Asukoht valitud. Võid klõpsata teise koha või liikuda järgmise sammu
            juurde.
        </p>
        <p v-else class="text-sm font-medium text-amber-800">
            Asukoht pole veel valitud — klõpsa aiaplaanil.
        </p>
    </div>
</template>
