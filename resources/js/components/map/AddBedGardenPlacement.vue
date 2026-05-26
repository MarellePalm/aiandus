<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import GardenMapBackground from '@/components/GardenMapBackground.vue';
import { ortophotoFocusSpanMeters } from '@/lib/gardenAreaSelection';
import {
    bedFootprintPx,
    bedPinTipPx,
    gardenPositionFromClick,
    gardenSizeCm,
    gardenSurfacePx,
    gridStepPx,
    planShapeCellCm,
    planUsesPinPlacement,
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
const mapBgRef = ref<InstanceType<typeof GardenMapBackground> | null>(null);
const viewportWidth = ref(0);
const viewportHeight = ref(0);
let resizeObserver: ResizeObserver | null = null;

const surface = computed(() => gardenSurfacePx(props.gardenPlan));
const draftSize = computed(() => bedFootprintPx(props.draftBed));
const usePinPlacement = computed(() => planUsesPinPlacement(props.gardenPlan));

const gardenWidthCm = computed(() => gardenSizeCm(props.gardenPlan).widthCm);
const gardenHeightCm = computed(() => gardenSizeCm(props.gardenPlan).heightCm);

const ortophotoFocusSpan = computed(() =>
    ortophotoFocusSpanMeters(
        gardenWidthCm.value / 100,
        gardenHeightCm.value / 100,
    ),
);

const placementFitZoom = computed(() => {
    const w = viewportWidth.value;
    const h = viewportHeight.value;
    if (!w || !h) {
        return 1;
    }

    const pad = 32;
    const fitW = Math.max(1, w - pad) / surface.value.width;
    const fitH = Math.max(1, h - pad) / surface.value.height;

    return Math.min(fitW, fitH);
});

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
    const style: Record<string, string> = {
        width: `${surface.value.width}px`,
        height: `${surface.value.height}px`,
    };

    if (usePinPlacement.value) {
        style.backgroundColor = 'transparent';
    } else {
        const minor = 'rgba(34, 98, 58, 0.12)';
        const major = 'rgba(34, 98, 58, 0.24)';
        style.backgroundColor = 'rgba(240, 250, 235, 0.98)';
        style.backgroundImage = [
            `linear-gradient(${minor} 1px, transparent 1px)`,
            `linear-gradient(90deg, ${minor} 1px, transparent 1px)`,
            `linear-gradient(${major} 1.5px, transparent 1.5px)`,
            `linear-gradient(90deg, ${major} 1.5px, transparent 1.5px)`,
        ].join(', ');
        style.backgroundSize = [
            `${gridStepPx(props.gardenPlan)}px ${gridStepPx(props.gardenPlan)}px`,
            `${gridStepPx(props.gardenPlan)}px ${gridStepPx(props.gardenPlan)}px`,
            `${gridMajorGardenPx.value}px ${gridMajorGardenPx.value}px`,
            `${gridMajorGardenPx.value}px ${gridMajorGardenPx.value}px`,
        ].join(', ');
    }

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

function syncViewportSize() {
    viewportWidth.value = viewportRef.value?.clientWidth ?? 0;
    viewportHeight.value = viewportRef.value?.clientHeight ?? 0;
}

watch(
    () => [fitScale.value, displayWidth.value, displayHeight.value, usePinPlacement.value],
    () => {
        if (!usePinPlacement.value) {
            return;
        }

        void nextTick(() => {
            mapBgRef.value?.scheduleMapRefresh();
        });
    },
);

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

function bedPinStyle(
    gardenX: number,
    gardenY: number,
    bed: GardenPlacementBed,
): Record<string, string> {
    const size = bedFootprintPx(bed);
    const tip = bedPinTipPx(gardenX, gardenY, size);

    return {
        left: `${tip.x}px`,
        top: `${tip.y}px`,
        transform: 'translate(-50%, -100%)',
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

function draftPinStyle(): Record<string, string> | null {
    if (!draftPosition.value) {
        return null;
    }

    return bedPinStyle(
        draftPosition.value.x,
        draftPosition.value.y,
        props.draftBed,
    );
}

const gardenLabel = computed(() => {
    const { widthCm, heightCm } = gardenSizeCm(props.gardenPlan);
    const w = widthCm / 100;
    const h = heightCm / 100;
    return `${w} m × ${h} m`;
});

onMounted(() => {
    syncViewportSize();
    if (typeof ResizeObserver !== 'undefined' && viewportRef.value) {
        resizeObserver = new ResizeObserver(() => {
            syncViewportSize();
            if (usePinPlacement.value) {
                mapBgRef.value?.scheduleMapRefresh();
            }
        });
        resizeObserver.observe(viewportRef.value);
    }

    void nextTick(() => {
        mapBgRef.value?.scheduleMapRefresh();
    });
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
});
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm leading-6 text-muted-foreground">
            <template v-if="usePinPlacement">
                Klõpsa ortofotol, kuhu peenar tuleb — märgitakse täpiga. Täpne
                kuju ja suurus seadistad järgmises sammus.
            </template>
            <template v-else>
                Klõpsa aiaplaanil, kuhu peenar tuleb. Ruudustik on mõõtkavas
                ({{ gardenLabel }}); sinu peenar kuvatakse amberina.
            </template>
        </p>

        <div
            ref="viewportRef"
            class="relative w-full rounded-[1.25rem] border border-emerald-900/15 p-2 shadow-inner"
            :class="
                usePinPlacement
                    ? 'overflow-hidden bg-[#e5e3df]'
                    : 'overflow-auto bg-[linear-gradient(180deg,rgba(251,248,241,0.98),rgba(241,247,235,0.98))]'
            "
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
                        <GardenMapBackground
                            v-if="usePinPlacement"
                            ref="mapBgRef"
                            class="z-0"
                            :center-lat="Number(gardenPlan.center_lat)"
                            :center-lng="Number(gardenPlan.center_lng)"
                            :width-cm="gardenWidthCm"
                            :height-cm="gardenHeightCm"
                            :focus-anchor-lat="Number(gardenPlan.center_lat)"
                            :focus-anchor-lng="Number(gardenPlan.center_lng)"
                            :focus-span-meters="ortophotoFocusSpan"
                            :planner-zoom="placementFitZoom"
                            :fit-zoom="placementFitZoom"
                        />

                        <template v-if="usePinPlacement">
                            <div
                                v-for="(bed, index) in existingBeds"
                                :key="`existing-pin-${index}`"
                                class="pointer-events-none absolute z-20"
                                :style="bedPinStyle(bed.garden_x, bed.garden_y, bed)"
                                aria-hidden="true"
                            >
                                <div
                                    class="flex flex-col-reverse items-center"
                                >
                                    <div
                                        class="h-2 w-px rounded-full bg-slate-500/40"
                                        aria-hidden="true"
                                    />
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-400/40 bg-card shadow-sm"
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full bg-slate-500"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="draftPinStyle()"
                                class="pointer-events-none absolute z-30"
                                :style="draftPinStyle()!"
                                aria-hidden="true"
                            >
                                <div
                                    class="flex flex-col-reverse items-center"
                                >
                                    <div
                                        class="h-2 w-px rounded-full bg-primary/45"
                                        aria-hidden="true"
                                    />
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-full border border-primary/35 bg-card shadow-md ring-4 ring-primary/25"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 rounded-full bg-primary shadow-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-else>
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
                        </template>
                    </div>
                </div>
            </div>

            <div
                v-if="!usePinPlacement"
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
