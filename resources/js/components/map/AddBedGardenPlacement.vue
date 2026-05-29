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
    bedFootprintPx,
    draftPlacementOverlapsExistingBeds,
    findNonOverlappingDraftPlacement,
    gardenPlacementPreviewCells,
    gardenSizeCm,
    gardenSurfacePx,
    gridStepPx,
    placementSnapStepPx,
    planShapeCellCm,
    planUsesPinPlacement,
    shapeMaskClipRects,
    snapPlacementPx,
    snapToPlacementEdges,
    type GardenPlacementBed,
    type GardenPlacementPlan,
    type GardenPlacementPreviewCell,
} from '@/pages/map/bedGardenPlacement';
import {
    CM_TO_PX,
    GARDEN_GRID_EDGE_CLASS,
    GARDEN_GRID_MAJOR_LINE,
    GARDEN_GRID_MINOR_LINE,
} from '@/pages/map/constants';

const props = defineProps<{
    gardenPlan: GardenPlacementPlan;
    existingBeds: GardenPlacementBed[];
    draftBed: GardenPlacementBed;
    draftPreviewCells?: GardenPlacementPreviewCell[];
    draftFootprintPx?: { width: number; height: number };
    gardenX: number | null;
    gardenY: number | null;
}>();

const emit = defineEmits<{
    'update:gardenX': [value: number];
    'update:gardenY': [value: number];
    rotate: [];
    'placement-ready': [];
}>();

const viewportRef = ref<HTMLElement | null>(null);
const canvasLayerRef = ref<HTMLElement | null>(null);
const mapBgRef = ref<InstanceType<typeof GardenMapBackground> | null>(null);
const viewportWidth = ref(0);
const viewportHeight = ref(0);
const isDraggingDraft = ref(false);
const dragPointerId = ref<number | null>(null);
const dragOffset = ref({ x: 0, y: 0 });
const dragMoved = ref(false);
let dragCaptureTarget: HTMLElement | null = null;
let suppressNextCanvasClick = false;
let resizeObserver: ResizeObserver | null = null;

const surface = computed(() => gardenSurfacePx(props.gardenPlan));

const footprintPx = computed(
    () => props.draftFootprintPx ?? bedFootprintPx(props.draftBed),
);

const hasVisibleDraft = computed(
    () => footprintPx.value.width >= 4 && footprintPx.value.height >= 4,
);
const usePinPlacement = computed(() => planUsesPinPlacement(props.gardenPlan));

const gardenWidthCm = computed(() => gardenSizeCm(props.gardenPlan).widthCm);
const gardenHeightCm = computed(() => gardenSizeCm(props.gardenPlan).heightCm);

const fitScale = computed(() => {
    const w = viewportWidth.value;
    const h = viewportHeight.value;
    if (!w || !h) {
        return 1;
    }
    const pad = 12;
    const fitW = Math.max(1, w - pad) / surface.value.width;
    const fitH = Math.max(1, h - pad) / surface.value.height;
    return Math.max(0.5, Math.min(4, Math.min(fitW, fitH)));
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

const gardenLayerSizeStyle = computed(() => ({
    width: `${surface.value.width}px`,
    height: `${surface.value.height}px`,
}));

/** Taust (ruudustik / ortofoto) — clip-path ainult siin, mitte peenra peal. */
const gardenBackgroundStyle = computed(() => {
    const style: Record<string, string> = {
        ...gardenLayerSizeStyle.value,
    };

    if (usePinPlacement.value) {
        style.backgroundColor = 'transparent';
    } else {
        const minor = GARDEN_GRID_MINOR_LINE;
        const major = GARDEN_GRID_MAJOR_LINE;
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

function readGardenCoord(value: number | null | undefined): number | null {
    if (value == null || typeof value === 'object') {
        return null;
    }
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : null;
}

const draftPosition = computed(() => {
    const x = readGardenCoord(props.gardenX);
    const y = readGardenCoord(props.gardenY);
    if (x == null || y == null) {
        return null;
    }

    return { x, y };
});

const defaultDraftCenter = computed(() => {
    if (!hasVisibleDraft.value) {
        return null;
    }

    return findNonOverlappingDraftPlacement(
        footprintPx.value,
        props.draftBed,
        props.gardenPlan,
        props.existingBeds,
        0,
    );
});

const placementOverlapsExisting = computed(() => {
    const pos = effectiveDraftPosition.value;
    if (!pos) {
        return false;
    }

    return draftPlacementOverlapsExistingBeds(
        pos.x,
        pos.y,
        props.draftBed,
        props.existingBeds,
    );
});

/** Kuvame peenart kohe; kui vanem pole veel x/y saatnud, kasutame keskpunkti. */
const effectiveDraftPosition = computed(
    () => draftPosition.value ?? defaultDraftCenter.value,
);

function syncDefaultPositionToParent() {
    if (draftPosition.value != null || !defaultDraftCenter.value) {
        return;
    }

    const center = defaultDraftCenter.value;
    emit('update:gardenX', center.x);
    emit('update:gardenY', center.y);
}

const toolbarBelow = computed(() => {
    const pos = effectiveDraftPosition.value;
    if (!pos) {
        return false;
    }
    // Tööriistariba peenra sees (mitte kohal), et ülemine serv ei lõikaks ära.
    return pos.y < 8 / Math.max(fitScale.value, 0.001);
});

const toolbarStyle = computed(() => {
    const inv = 1 / Math.max(fitScale.value, 0.001);
    return {
        transform: `translateX(-50%) scale(${inv})`,
        transformOrigin: toolbarBelow.value ? '50% 100%' : '50% 0',
    };
});

function syncViewportSize() {
    const el = viewportRef.value;
    if (!el) {
        viewportWidth.value = 0;
        viewportHeight.value = 0;
        return;
    }

    viewportWidth.value = el.clientWidth;
    const measuredHeight = el.clientHeight;
    const preferredHeight = Math.min(
        Math.round(window.innerHeight * 0.55),
        480,
    );

    viewportHeight.value = Math.max(measuredHeight, preferredHeight);
}

watch(
    () => [
        fitScale.value,
        displayWidth.value,
        displayHeight.value,
        usePinPlacement.value,
    ],
    () => {
        if (!usePinPlacement.value) {
            return;
        }

        void nextTick(() => {
            mapBgRef.value?.scheduleMapRefresh();
        });
    },
);

function gardenPointFromClient(clientX: number, clientY: number) {
    const layer = canvasLayerRef.value;
    if (!layer) {
        return null;
    }

    const rect = layer.getBoundingClientRect();
    const scale = Math.max(fitScale.value, 0.001);

    return {
        x: (clientX - rect.left) / scale,
        y: (clientY - rect.top) / scale,
    };
}

function applyDraftPosition(rawX: number, rawY: number) {
    const step = placementSnapStepPx(props.draftBed, props.gardenPlan);
    const snapped = {
        x: snapPlacementPx(rawX, step),
        y: snapPlacementPx(rawY, step),
    };
    const next = snapToPlacementEdges(
        snapped.x,
        snapped.y,
        footprintPx.value,
        props.gardenPlan,
        0,
    );

    if (
        draftPlacementOverlapsExistingBeds(
            next.x,
            next.y,
            props.draftBed,
            props.existingBeds,
        )
    ) {
        return;
    }

    emit('update:gardenX', next.x);
    emit('update:gardenY', next.y);
}

function onCanvasClick(event: MouseEvent) {
    if (suppressNextCanvasClick) {
        suppressNextCanvasClick = false;
        return;
    }

    const point = gardenPointFromClient(event.clientX, event.clientY);
    if (!point) {
        return;
    }

    // Tsentreeritud asetus: klõpsupunkt = peenra keskpunkt. Sama loogika
    // (haakumine + servad) kehtib nii aiaplaanil kui ortofotol.
    applyDraftPosition(
        point.x - footprintPx.value.width / 2,
        point.y - footprintPx.value.height / 2,
    );
}

function onDraftPointerMove(event: PointerEvent) {
    if (!isDraggingDraft.value || event.pointerId !== dragPointerId.value) {
        return;
    }

    const point = gardenPointFromClient(event.clientX, event.clientY);
    if (!point) {
        return;
    }

    dragMoved.value = true;
    applyDraftPosition(
        point.x - dragOffset.value.x,
        point.y - dragOffset.value.y,
    );
}

function stopDraftDrag() {
    window.removeEventListener('pointermove', onDraftPointerMove);
    window.removeEventListener('pointerup', stopDraftDrag);
    window.removeEventListener('pointercancel', stopDraftDrag);

    if (dragCaptureTarget && dragPointerId.value !== null) {
        try {
            dragCaptureTarget.releasePointerCapture(dragPointerId.value);
        } catch {
            /* noop */
        }
    }

    if (dragMoved.value) {
        suppressNextCanvasClick = true;
    }

    isDraggingDraft.value = false;
    dragPointerId.value = null;
    dragCaptureTarget = null;
    dragMoved.value = false;
}

function startDraftDrag(event: PointerEvent) {
    const pos = effectiveDraftPosition.value;
    if (!pos) {
        return;
    }

    if (draftPosition.value == null && defaultDraftCenter.value) {
        emit('update:gardenX', pos.x);
        emit('update:gardenY', pos.y);
    }

    event.preventDefault();
    event.stopPropagation();

    const point = gardenPointFromClient(event.clientX, event.clientY);
    if (!point) {
        return;
    }

    isDraggingDraft.value = true;
    dragMoved.value = false;
    dragPointerId.value = event.pointerId;
    // Haardepunkt võib olla peenrast väljas (nt käepide peenra kohal), seega
    // piirame nihke peenra piiridesse — muidu ei pääseks üla-/alaserva.
    dragOffset.value = {
        x: Math.max(0, Math.min(point.x - pos.x, footprintPx.value.width)),
        y: Math.max(0, Math.min(point.y - pos.y, footprintPx.value.height)),
    };

    const captureEl = event.currentTarget as HTMLElement | null;
    dragCaptureTarget = captureEl;
    if (captureEl) {
        try {
            captureEl.setPointerCapture(event.pointerId);
        } catch {
            /* noop */
        }
    }

    window.addEventListener('pointermove', onDraftPointerMove);
    window.addEventListener('pointerup', stopDraftDrag);
    window.addEventListener('pointercancel', stopDraftDrag);
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

function existingBedCells(
    bed: GardenPlacementBed,
): GardenPlacementPreviewCell[] {
    return gardenPlacementPreviewCells(bed);
}

function existingBedCellStyle(
    cell: GardenPlacementPreviewCell,
): Record<string, string> {
    return {
        left: `${cell.left}px`,
        top: `${cell.top}px`,
        width: `${cell.width}px`,
        height: `${cell.height}px`,
    };
}

const draftBedStyle = computed((): Record<string, string> | null => {
    const pos = effectiveDraftPosition.value;
    if (!pos || !hasVisibleDraft.value) {
        return null;
    }

    return {
        left: `${pos.x}px`,
        top: `${pos.y}px`,
        width: `${footprintPx.value.width}px`,
        height: `${footprintPx.value.height}px`,
    };
});

const gardenLabel = computed(() => {
    const { widthCm, heightCm } = gardenSizeCm(props.gardenPlan);
    const w = widthCm / 100;
    const h = heightCm / 100;
    return `${w} m × ${h} m`;
});

function scrollDraftIntoView() {
    if (!effectiveDraftPosition.value || !hasVisibleDraft.value) {
        return;
    }

    void nextTick(() => {
        viewportRef.value
            ?.querySelector<HTMLElement>('.bed-placement-draft')
            ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    });
}

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
        syncDefaultPositionToParent();
        scrollDraftIntoView();
        emit('placement-ready');
    });
});

watch(
    () => [
        props.gardenX,
        props.gardenY,
        footprintPx.value.width,
        footprintPx.value.height,
        hasVisibleDraft.value,
    ],
    () => {
        syncDefaultPositionToParent();
        scrollDraftIntoView();
    },
    { immediate: true },
);

watch(
    () => [
        draftPosition.value?.x,
        draftPosition.value?.y,
        hasVisibleDraft.value,
        fitScale.value,
        displayWidth.value,
    ],
    () => {
        scrollDraftIntoView();
    },
);

onBeforeUnmount(() => {
    stopDraftDrag();
    resizeObserver?.disconnect();
    resizeObserver = null;
});
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm leading-6 text-muted-foreground">
            <template v-if="usePinPlacement">
                Klõpsa ortofotol või lohista peenart paika. Peenar on mõõtkavas
                ({{ gardenLabel }}); käepidemest saad lohistada ja nupuga
                keerata.
            </template>
            <template v-else>
                Klõpsa aiaplaanil või lohista peenart paika. Ruudustik on
                mõõtkavas ({{ gardenLabel }}); serva juures kinnitub
                automaatselt.
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
            style="max-height: min(78vh, 720px); min-height: min(55vh, 480px)"
        >
            <div
                class="relative mx-auto"
                :style="{
                    width: `${displayWidth}px`,
                    height: `${displayHeight}px`,
                }"
            >
                <div
                    ref="canvasLayerRef"
                    class="absolute top-0 left-0 touch-none"
                    :class="
                        usePinPlacement ? 'cursor-crosshair' : 'cursor-default'
                    "
                    :style="canvasTransformStyle"
                    role="presentation"
                    @click="onCanvasClick"
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
                        class="relative overflow-visible"
                        :style="gardenLayerSizeStyle"
                    >
                        <div
                            class="absolute inset-0"
                            :style="gardenBackgroundStyle"
                        >
                            <div
                                v-if="!usePinPlacement"
                                :class="GARDEN_GRID_EDGE_CLASS"
                                aria-hidden="true"
                            />
                            <GardenMapBackground
                                v-if="usePinPlacement"
                                ref="mapBgRef"
                                class="z-0"
                                :center-lat="Number(gardenPlan.center_lat)"
                                :center-lng="Number(gardenPlan.center_lng)"
                                :width-cm="gardenWidthCm"
                                :height-cm="gardenHeightCm"
                                :focus-anchor-lat="null"
                                :focus-anchor-lng="null"
                                :focus-span-meters="0"
                                :planner-zoom="1"
                                :fit-zoom="1"
                                exact-garden-fit
                            />

                            <div
                                v-for="(bed, index) in existingBeds"
                                :key="`existing-bed-${index}`"
                                class="pointer-events-none absolute overflow-visible"
                                :style="existingBedStyle(bed)"
                                aria-hidden="true"
                            >
                                <template v-if="existingBedCells(bed).length">
                                    <div
                                        v-for="(
                                            cell, cellIndex
                                        ) in existingBedCells(bed)"
                                        :key="`existing-bed-${index}-cell-${cellIndex}`"
                                        class="absolute overflow-hidden rounded-none opacity-90"
                                        :class="
                                            cell.kind === 'walkway'
                                                ? 'bg-stone-300/35'
                                                : ''
                                        "
                                        :style="existingBedCellStyle(cell)"
                                    >
                                        <img
                                            v-if="
                                                cell.image_url &&
                                                cell.kind === 'plantable'
                                            "
                                            :src="cell.image_url"
                                            alt=""
                                            class="size-full object-cover"
                                        />
                                        <div
                                            v-else-if="
                                                cell.kind === 'plantable'
                                            "
                                            class="flex size-full items-center justify-center bg-emerald-50/40"
                                        >
                                            <span
                                                class="material-symbols-outlined leading-none text-emerald-800/45"
                                                :style="{
                                                    fontSize: `clamp(8px, ${Math.min(cell.width, cell.height) * 0.42}px, 20px)`,
                                                }"
                                                aria-hidden="true"
                                                >potted_plant</span
                                            >
                                        </div>
                                    </div>
                                </template>
                                <div
                                    v-else
                                    class="absolute inset-0 rounded-none bg-slate-200/55 opacity-90"
                                />
                            </div>
                        </div>

                        <div
                            v-if="draftBedStyle"
                            class="bed-placement-draft absolute z-50 touch-none overflow-visible rounded-none"
                            :class="[
                                isDraggingDraft
                                    ? 'is-dragging cursor-grabbing'
                                    : 'cursor-grab',
                                placementOverlapsExisting
                                    ? 'bed-placement-draft--invalid'
                                    : '',
                            ]"
                            :style="draftBedStyle"
                            role="button"
                            tabindex="0"
                            aria-label="Lohista peenart aiaplaanil"
                            @pointerdown="startDraftDrag"
                            @click.stop
                        >
                            <div
                                class="bed-placement-toolbar"
                                :class="
                                    toolbarBelow
                                        ? 'bed-placement-toolbar--below'
                                        : ''
                                "
                                :style="toolbarStyle"
                            >
                                <span
                                    class="bed-placement-toolbar__grip"
                                    :class="
                                        isDraggingDraft
                                            ? 'cursor-grabbing'
                                            : 'cursor-grab'
                                    "
                                    aria-hidden="true"
                                    @pointerdown="startDraftDrag"
                                >
                                    <svg
                                        width="10"
                                        height="10"
                                        viewBox="0 0 10 10"
                                        fill="currentColor"
                                    >
                                        <circle cx="2" cy="2" r="1.2" />
                                        <circle cx="8" cy="2" r="1.2" />
                                        <circle cx="2" cy="5" r="1.2" />
                                        <circle cx="8" cy="5" r="1.2" />
                                        <circle cx="2" cy="8" r="1.2" />
                                        <circle cx="8" cy="8" r="1.2" />
                                    </svg>
                                </span>
                                <button
                                    type="button"
                                    class="bed-placement-toolbar__btn"
                                    title="Keera 90°"
                                    aria-label="Keera peenart 90 kraadi"
                                    @pointerdown.stop
                                    @click.stop="emit('rotate')"
                                >
                                    <span
                                        class="material-symbols-outlined text-[16px] leading-none"
                                        aria-hidden="true"
                                        >rotate_right</span
                                    >
                                </button>
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-none"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="(
                                        cell, cellIndex
                                    ) in props.draftPreviewCells ?? []"
                                    :key="`draft-cell-${cellIndex}`"
                                    class="absolute overflow-hidden rounded-none"
                                    :class="
                                        cell.kind === 'walkway'
                                            ? 'bg-stone-300/40'
                                            : ''
                                    "
                                    :style="{
                                        left: `${cell.left}px`,
                                        top: `${cell.top}px`,
                                        width: `${cell.width}px`,
                                        height: `${cell.height}px`,
                                    }"
                                >
                                    <img
                                        v-if="
                                            cell.image_url &&
                                            cell.kind === 'plantable'
                                        "
                                        :src="cell.image_url"
                                        alt=""
                                        class="size-full object-cover"
                                    />
                                    <div
                                        v-else-if="cell.kind === 'plantable'"
                                        class="flex size-full items-center justify-center bg-emerald-50/40"
                                    >
                                        <span
                                            class="material-symbols-outlined leading-none text-emerald-800/45"
                                            :style="{
                                                fontSize: `clamp(8px, ${Math.min(cell.width, cell.height) * 0.42}px, 20px)`,
                                            }"
                                            aria-hidden="true"
                                            >potted_plant</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
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
            v-if="
                effectiveDraftPosition &&
                hasVisibleDraft &&
                placementOverlapsExisting
            "
            class="text-sm font-medium text-red-700 dark:text-red-300"
        >
            Peenar kattub olemasoleva peenraga — vali teine koht aiaplaanil.
        </p>
        <p
            v-else-if="effectiveDraftPosition && hasVisibleDraft"
            class="text-sm font-medium text-emerald-800 dark:text-emerald-100"
        >
            Asukoht valitud. Lohista peenart, keera nupuga 90° või liigu
            järgmise sammu juurde.
        </p>
        <p
            v-else-if="!hasVisibleDraft"
            class="text-sm font-medium text-amber-800"
        >
            Peenra kuju on puudu või liiga väike — mine tagasi „Kuju" sammu
            juurde ja lisa vähemalt üks ruut.
        </p>
        <p v-else class="text-sm font-medium text-amber-800">
            Asukoht pole veel valitud — klõpsa aiaplaanil.
        </p>
    </div>
</template>

<style scoped>
.bed-placement-toolbar {
    position: absolute;
    top: 4px;
    left: 50%;
    transform: translateX(-50%);
    pointer-events: none;
    z-index: 40;
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 2px;
    border-radius: 9999px;
    border: 1px solid rgba(22, 101, 52, 0.18);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.16);
    white-space: nowrap;
}

.bed-placement-toolbar--below {
    top: auto;
    bottom: 4px;
}

.bed-placement-toolbar__grip {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 9999px;
    color: rgba(60, 80, 50, 0.7);
    touch-action: none;
    pointer-events: auto;
}

.bed-placement-toolbar__btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 9999px;
    color: rgba(22, 101, 52, 0.9);
    cursor: pointer;
    touch-action: manipulation;
    pointer-events: auto;
}

.bed-placement-toolbar__btn:hover {
    background: rgba(22, 101, 52, 0.1);
}

.bed-placement-draft.is-dragging {
    outline: 2px dashed rgba(47, 99, 60, 0.45);
    outline-offset: 2px;
}

.bed-placement-draft.is-dragging .bed-placement-toolbar {
    opacity: 0.6;
}

.bed-placement-draft--invalid {
    outline: 2px dashed rgba(220, 38, 38, 0.55);
    outline-offset: 2px;
}
</style>
