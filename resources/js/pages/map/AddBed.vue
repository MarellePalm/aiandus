<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { GridItem, GridLayout } from 'grid-layout-plus';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AddBedGardenPlacement from '@/components/map/AddBedGardenPlacement.vue';
import { normalizeImageForUpload } from '@/lib/imageUpload';
import type {
    GardenPlacementBed,
    GardenPlacementPlan,
} from '@/pages/map/bedGardenPlacement';
import { DEFAULT_BED_CELL_SIZE_CM } from '@/pages/map/constants';

type BedPlant = {
    id: number;
    name: string;
    image_url: string | null;
    position_in_bed: string | null;
};

type CellPlant = {
    plant_id: number;
    quantity: number;
    size: string | null;
    note: string | null;
};

type BedCell = {
    id: string;
    x: number;
    y: number;
    left_cm: number;
    top_cm: number;
    w: number;
    h: number;
    width_cm: number;
    height_cm: number;
    active: boolean;
    kind: 'plantable' | 'walkway' | 'empty';
    plants: CellPlant[];
};

type SubmitCell = {
    x: number;
    y: number;
    w: number;
    h: number;
    kind: 'plantable' | 'walkway' | 'empty';
    plants: CellPlant[];
};

const props = withDefaults(
    defineProps<{
        mode?: 'create' | 'edit';
        initialStep?: WizardStep;
        /** Praegune aiaplaan (ainult looja režiimis). */
        gardenPlanId?: number;
        gardenPlan?: GardenPlacementPlan | null;
        existingBeds?: GardenPlacementBed[];
        bed?: {
            id: number;
            garden_plan_id?: number;
            name: string;
            location: string | null;
            image_url?: string | null;
            cell_size_cm?: number;
            layout?: number[][] | null;
            cell_bricks?: Array<{
                x: number;
                y: number;
                w: number;
                h: number;
                width_cm?: number;
                height_cm?: number;
                left_cm?: number;
                top_cm?: number;
                kind: 'plantable' | 'walkway' | 'empty';
            }> | null;
            plants?: BedPlant[];
        };
    }>(),
    {
        mode: 'create',
        gardenPlanId: undefined,
        gardenPlan: null,
        existingBeds: () => [],
        bed: undefined,
    },
);

const existingImageUrl = ref(
    props.mode === 'edit' ? (props.bed?.image_url ?? null) : null,
);
const newBedImagePreview = ref<string | null>(null);
const highlightedCellId = ref<string | null>(null);
let highlightTimeout: ReturnType<typeof setTimeout> | null = null;
type WizardStep = 1 | 2 | 3 | 4;
type DesignBrush = 'plantable' | 'walkway' | 'empty';
const RESIZE_SUBGRID_UNITS = 3;
const DEFAULT_BLOCK_UNITS = RESIZE_SUBGRID_UNITS;
const CELL_PX = 26;
const GRID_MARGIN: [number, number] = [2, 2];
const DEFAULT_BLOCK_PX =
    DEFAULT_BLOCK_UNITS * CELL_PX + (DEFAULT_BLOCK_UNITS - 1) * GRID_MARGIN[0];
const containerWidth = ref(0);

const bedShapeGridHost = ref<HTMLElement | null>(null);
let bedShapeResizeObserver: ResizeObserver | null = null;

function syncContainerWidth() {
    containerWidth.value = bedShapeGridHost.value?.clientWidth ?? 0;
}

const activeBrush = ref<DesignBrush>('plantable');
const externalDragKind = ref<'plantable' | 'walkway' | null>(null);
const dragPreviewGridPos = ref<{ x: number; y: number } | null>(null);

function onChipDragStart(kind: 'plantable' | 'walkway', event: DragEvent) {
    activeBrush.value = kind;
    externalDragKind.value = kind;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', kind);
    }
}

function onChipDragEnd() {
    externalDragKind.value = null;
    dragPreviewGridPos.value = null;
}

function gridPosFromMouse(
    mouseX: number,
    mouseY: number,
    rect: DOMRect,
): { x: number; y: number } {
    const relX = mouseX - rect.left;
    const relY = mouseY - rect.top;
    const pad = 16;
    const slotW = CELL_PX + GRID_MARGIN[0];
    const slotH = CELL_PX + GRID_MARGIN[1];
    const x = Math.floor((relX - pad) / slotW);
    const y = Math.floor((relY - pad) / slotH);
    return { x, y };
}

function onCanvasDragOver(event: DragEvent) {
    if (!externalDragKind.value) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
    const target = event.currentTarget as HTMLElement | null;
    if (!target) return;
    const rect = target.getBoundingClientRect();
    dragPreviewGridPos.value = gridPosFromMouse(event.clientX, event.clientY, rect);
}

function onCanvasDragLeave(event: DragEvent) {
    const target = event.currentTarget as HTMLElement | null;
    const related = event.relatedTarget as Node | null;
    if (target && related && target.contains(related)) return;
    dragPreviewGridPos.value = null;
}

function onCanvasDrop(event: DragEvent) {
    event.preventDefault();
    // Ignore drops from grid item dragging; only accept palette kinds.
    const rawKind = event.dataTransfer?.getData('text/plain') ?? externalDragKind.value;
    const kind =
        rawKind === 'plantable' || rawKind === 'walkway' ? rawKind : null;
    externalDragKind.value = null;
    dragPreviewGridPos.value = null;
    if (!kind) return;
    const target = event.currentTarget as HTMLElement | null;
    if (!target) return;
    const rect = target.getBoundingClientRect();
    const { x, y } = gridPosFromMouse(event.clientX, event.clientY, rect);
    addCellAt(x, y, kind);
}

const hasGardenPlacement = computed(
    () => props.mode === 'create' && props.gardenPlan != null,
);

const wizardSteps = computed(() => {
    if (hasGardenPlacement.value) {
        return [
            { id: 1 as const, label: 'Nimi', icon: 'edit' },
            { id: 2 as const, label: 'Kuju', icon: 'grid_view' },
            { id: 3 as const, label: 'Aiaplaan', icon: 'map' },
            { id: 4 as const, label: 'Viimistlus', icon: 'photo_camera' },
        ];
    }

    return [
        { id: 1 as const, label: 'Peenar nimeta', icon: 'edit' },
        { id: 2 as const, label: 'Kujunda kuju', icon: 'grid_view' },
        { id: 3 as const, label: 'Viimistlus', icon: 'photo_camera' },
    ];
});

const maxStep = computed(
    () => wizardSteps.value[wizardSteps.value.length - 1]?.id ?? 3,
);
const finishStep = computed(() => maxStep.value);
const placementStep = computed(() =>
    hasGardenPlacement.value ? (3 as const) : null,
);

const currentStep = ref<WizardStep>(1);

watch(
    () => props.initialStep,
    (step) => {
        if (step && [1, 2, 3, 4].includes(step)) {
            const capped = hasGardenPlacement.value
                ? step
                : (Math.min(step, 3) as WizardStep);
            currentStep.value = capped;
        }
    },
    { immediate: true },
);

const gardenX = ref<number | null>(null);
const gardenY = ref<number | null>(null);

function makeCellId(x: number, y: number): string {
    return `cell-${x}-${y}-${Math.random().toString(36).slice(2, 8)}`;
}

function gridUnitCm(): number {
    return Math.max(
        10,
        Math.min(200, Math.round(Number(form.cell_size_cm) || DEFAULT_BED_CELL_SIZE_CM)),
    );
}

function editorUnitCm(baseUnitCm = gridUnitCm()): number {
    return Math.max(1, Math.round(baseUnitCm / RESIZE_SUBGRID_UNITS));
}

function clampBrickCm(value: number): number {
    return Math.max(10, Math.min(500, Math.round(value)));
}

function createInitialCells(): BedCell[] {
    const layout = props.bed?.layout;
    const plants = props.bed?.plants ?? [];
    const plantMap = new Map<string, CellPlant[]>();

    plants.forEach((plant) => {
        if (!plant.position_in_bed || !/^\d+,\d+$/.test(plant.position_in_bed))
            return;
        const entries = plantMap.get(plant.position_in_bed) ?? [];
        entries.push({
            plant_id: plant.id,
            quantity: 1,
            size: null,
            note: null,
        });
        plantMap.set(plant.position_in_bed, entries);
    });

    const bricks = props.bed?.cell_bricks;
    const baseUnitCm = props.bed?.cell_size_cm ?? DEFAULT_BED_CELL_SIZE_CM;
    const unitCm = editorUnitCm(baseUnitCm);

    if (Array.isArray(bricks) && bricks.length > 0) {
        return bricks.map((brick) => {
            const leftCm = brick.left_cm ?? brick.x * baseUnitCm;
            const topCm = brick.top_cm ?? brick.y * baseUnitCm;
            const widthCm = brick.width_cm ?? (brick.w ?? 1) * baseUnitCm;
            const heightCm = brick.height_cm ?? (brick.h ?? 1) * baseUnitCm;
            const w = Math.max(1, Math.round(widthCm / unitCm));
            const h = Math.max(1, Math.round(heightCm / unitCm));
            const x = Math.max(0, Math.round(leftCm / unitCm));
            const y = Math.max(0, Math.round(topCm / unitCm));

            return {
                id: makeCellId(x, y),
                x,
                y,
                w,
                h,
                width_cm: clampBrickCm(widthCm),
                height_cm: clampBrickCm(heightCm),
                left_cm: leftCm,
                top_cm: topCm,
                active: brick.kind === 'plantable',
                kind: brick.kind ?? 'plantable',
                plants: [...(plantMap.get(`${brick.y},${brick.x}`) ?? [])],
            };
        });
    }

    if (!layout || !Array.isArray(layout) || layout.length === 0) {
        if (props.mode !== 'edit') {
            return [];
        }
        return [
            {
                id: makeCellId(0, 0),
                x: 0,
                y: 0,
                w: DEFAULT_BLOCK_UNITS,
                h: DEFAULT_BLOCK_UNITS,
                width_cm: baseUnitCm,
                height_cm: baseUnitCm,
                left_cm: 0,
                top_cm: 0,
                active: true,
                kind: 'plantable' as const,
                plants: [],
            },
        ];
    }

    const cells: BedCell[] = [];
    layout.forEach((row, y) => {
        if (!Array.isArray(row)) return;
        row.forEach((rawCell, x) => {
            const cellValue = Number(rawCell);
            if (![1, 0, -1].includes(cellValue)) return;
            const gx = x * DEFAULT_BLOCK_UNITS;
            const gy = y * DEFAULT_BLOCK_UNITS;
            cells.push({
                id: makeCellId(gx, gy),
                x: gx,
                y: gy,
                w: DEFAULT_BLOCK_UNITS,
                h: DEFAULT_BLOCK_UNITS,
                width_cm: baseUnitCm,
                height_cm: baseUnitCm,
                left_cm: x * baseUnitCm,
                top_cm: y * baseUnitCm,
                active: cellValue === 1,
                kind:
                    cellValue === 1
                        ? 'plantable'
                        : cellValue === -1
                          ? 'walkway'
                          : 'empty',
                plants:
                    cellValue === 1
                        ? [...(plantMap.get(`${y},${x}`) ?? [])]
                        : [],
            });
        });
    });

    return cells.length
        ? cells
        : [
              {
                  id: makeCellId(0, 0),
                  x: 0,
                  y: 0,
                  w: DEFAULT_BLOCK_UNITS,
                  h: DEFAULT_BLOCK_UNITS,
                  width_cm: baseUnitCm,
                  height_cm: baseUnitCm,
                  left_cm: 0,
                  top_cm: 0,
                  active: true,
                  kind: 'plantable',
                  plants: [],
              },
          ];
}

const cells = ref<BedCell[]>(createInitialCells());
const selectedCellId = ref<string>(cells.value[0]?.id ?? '');

type LayoutItem = { i: string; x: number; y: number; w: number; h: number };

/** grid-layout-plus paigutus — v-model:layout allikas (vt docs). */
const gridLayout = ref<LayoutItem[]>(
    cells.value.map((c) => ({
        i: c.id,
        x: c.x,
        y: c.y,
        w: c.w,
        h: c.h,
    })),
);

const dynamicColNum = computed(() => {
    const maxCellX = gridLayout.value.length
        ? Math.max(...gridLayout.value.map((i) => i.x + i.w))
        : 1;
    const cols = containerWidth.value
        ? Math.floor((containerWidth.value - GRID_MARGIN[0]) / (CELL_PX + GRID_MARGIN[0]))
        : 6;
    return Math.max(maxCellX + 3, cols, 4);
});

const cellMap = computed(() => new Map(cells.value.map((c) => [c.id, c])));

function layoutItemForCell(cell: BedCell): LayoutItem {
    return { i: cell.id, x: cell.x, y: cell.y, w: cell.w, h: cell.h };
}

function pushLayoutItem(item: LayoutItem) {
    gridLayout.value.push(item);
}

function removeLayoutItem(id: string) {
    const index = gridLayout.value.findIndex((item) => item.i === id);
    if (index >= 0) {
        gridLayout.value.splice(index, 1);
    }
}

function replaceGridLayout(items: LayoutItem[]) {
    gridLayout.value.splice(0, gridLayout.value.length, ...items);
}

function applyGridLayoutToCells(layout: LayoutItem[]) {
    const unit = editorUnitCm();
    cells.value = cells.value.map((cell) => {
        const item = layout.find((l) => l.i === cell.id);
        if (!item) return cell;
        return {
            ...cell,
            x: item.x,
            y: item.y,
            w: item.w,
            h: item.h,
            left_cm: item.x * unit,
            top_cm: item.y * unit,
            width_cm: item.w * unit,
            height_cm: item.h * unit,
        };
    });
}

const form = useForm<{
    name: string;
    location: string;
    cell_size_cm: number;
    image: File | null;
    cells: SubmitCell[];
    layout: number[][];
    cell_bricks: Array<{
        x: number;
        y: number;
        w: number;
        h: number;
        kind: 'plantable' | 'walkway' | 'empty';
    }>;
}>({
    name: props.mode === 'edit' ? (props.bed?.name ?? '') : '',
    location: props.mode === 'edit' ? (props.bed?.location ?? '') : '',
    cell_size_cm: props.mode === 'edit'
        ? (props.bed?.cell_size_cm ?? DEFAULT_BED_CELL_SIZE_CM)
        : DEFAULT_BED_CELL_SIZE_CM,
    image: null,
    cells: [],
    layout: [],
    cell_bricks: [],
});

const selectedCell = computed(
    () => cells.value.find((cell) => cell.id === selectedCellId.value) ?? null,
);
const selectedLayoutItem = computed(
    () => gridLayout.value.find((item) => item.i === selectedCellId.value) ?? null,
);
const selectedCellLive = computed(() => {
    const cell = selectedCell.value;
    if (!cell) return null;
    const item = selectedLayoutItem.value;
    if (!item) return cell;
    const unit = editorUnitCm();
    return {
        ...cell,
        x: item.x,
        y: item.y,
        w: item.w,
        h: item.h,
        left_cm: item.x * unit,
        top_cm: item.y * unit,
        width_cm: item.w * unit,
        height_cm: item.h * unit,
    };
});
const activeCells = computed(() =>
    cells.value.filter((cell) => cell.active && cell.kind === 'plantable'),
);
const layoutCells = computed(() => cells.value);

const bounds = computed(() => {
    if (!layoutCells.value.length) {
        return { minX: 0, maxX: -1, minY: 0, maxY: -1 };
    }
    const xs = layoutCells.value.map((c) => c.x);
    const ys = layoutCells.value.map((c) => c.y);
    const maxXs = layoutCells.value.map((c) => c.x + c.w - 1);
    const maxYs = layoutCells.value.map((c) => c.y + c.h - 1);
    return {
        minX: Math.min(...xs),
        maxX: Math.max(...maxXs),
        minY: Math.min(...ys),
        maxY: Math.max(...maxYs),
    };
});

const layoutBounds = computed(() => bounds.value);

const bedSummaryLabel = computed(() => {
    const b = layoutBounds.value;
    if (b.maxX < b.minX) return '0 × 0 ruutu';
    const cols = b.maxX - b.minX + 1;
    const rows = b.maxY - b.minY + 1;
    return `${cols} × ${rows} ruutu`;
});
const bedPhysicalSizeLabel = computed(() => {
    const b = layoutBounds.value;
    if (b.maxX < b.minX || !cells.value.length) return '0 × 0 m';
    const unit = gridUnitCm();
    const widthCm = (b.maxX - b.minX + 1) * unit;
    const heightCm = (b.maxY - b.minY + 1) * unit;
    return `${formatMeters(widthCm / 100)} × ${formatMeters(heightCm / 100)} m`;
});

const cellSizeCmLabel = computed(() => gridUnitCm());

function recalcCellsCmFromGridUnit() {
    const unit = editorUnitCm();
    cells.value = cells.value.map((cell) => ({
        ...cell,
        left_cm: cell.x * unit,
        top_cm: cell.y * unit,
        width_cm: cell.w * unit,
        height_cm: cell.h * unit,
    }));
}

const canContinueFromStep = computed(() => {
    if (currentStep.value === 1) {
        return (
            Boolean(form.name.trim()) &&
            cellSizeCmLabel.value >= 10 &&
            cellSizeCmLabel.value <= 200
        );
    }
    if (currentStep.value === 2) return activeCells.value.length > 0;
    if (placementStep.value !== null && currentStep.value === placementStep.value) {
        return gardenX.value != null && gardenY.value != null;
    }
    return true;
});

const draftBedForPlacement = computed<GardenPlacementBed>(() => ({
    garden_x: 0,
    garden_y: 0,
    cell_size_cm: Math.max(
        10,
        Math.min(200, Math.round(Number(form.cell_size_cm) || DEFAULT_BED_CELL_SIZE_CM)),
    ),
    layout: form.layout?.length ? form.layout : [[1]],
    rows: form.layout?.length ?? 1,
    columns: form.layout[0]?.length ?? 1,
}));

function formatMeters(value: number): string {
    const rounded = Math.round(value * 10) / 10;
    return Number.isInteger(rounded) ? `${rounded}` : rounded.toFixed(1);
}

function cellClasses(cell: BedCell): string[] {
    const isSelected = selectedCell.value?.id === cell.id;
    const hasPlants = cell.plants.length > 0;
    const warm = (cell.x + cell.y) % 2 === 0;
    const classes: string[] = [
        'bed-cell',
        'relative',
        'size-full',
        'overflow-hidden',
        'border',
        'transition',
        'duration-200',
    ];
    if (highlightedCellId.value === cell.id) {
        classes.push('scale-[1.04]', 'shadow-lg', 'shadow-primary/20');
    }
    if (isSelected) {
        classes.push(
            'bed-cell--editor-selected',
            'ring-2',
            'ring-primary',
            'ring-offset-1',
            'z-10',
        );
        return classes;
    }
    if (cell.kind === 'plantable' && !cell.active) {
        classes.push('bed-cell--inactive');
        classes.push('hover:border-primary/25');
        return classes;
    }
    if (cell.kind === 'walkway') {
        classes.push('bed-cell--walkway');
        classes.push('hover:-translate-y-0.5', 'hover:shadow-md');
        return classes;
    }
    if (cell.kind === 'empty') {
        classes.push('bed-cell--void');
        classes.push('hover:-translate-y-0.5', 'hover:shadow-md');
        return classes;
    }
    if (hasPlants) {
        classes.push('bed-cell--planted');
        classes.push('hover:-translate-y-0.5', 'hover:shadow-md');
        return classes;
    }
    classes.push(warm ? 'bed-cell--empty bed-cell--warm' : 'bed-cell--empty');
    classes.push('hover:-translate-y-0.5', 'hover:shadow-md');
    return classes;
}

function getPlantNames(cell: BedCell): string[] {
    const plantIds = cell.plants.map((plant) => plant.plant_id);
    return plantIds
        .map(
            (id) =>
                props.bed?.plants?.find((plant) => plant.id === id)?.name ??
                'Taim',
        )
        .slice(0, 3);
}

function cellIcon(cell: BedCell): string {
    if (cell.plants.length) return 'eco';
    if (cell.kind === 'walkway') return 'texture';
    if (cell.kind === 'empty') return 'crop_free';
    if (!cell.active) return 'crop_square';
    return 'add';
}

function isPlantableEmptySlot(cell: BedCell): boolean {
    return cell.kind === 'plantable' && cell.active && cell.plants.length === 0;
}

function brushPaletteCellClasses(kind: 'plantable' | 'walkway'): string[] {
    if (kind === 'walkway') {
        return [
            'bed-cell',
            'bed-cell--walkway',
            'bed-brush-palette-cell',
            'relative',
        ];
    }
    return [
        'bed-cell',
        'bed-cell--empty',
        'bed-cell--warm',
        'bed-brush-palette-cell',
        'relative',
    ];
}

function cellKindLabel(cell: BedCell): string {
    if (cell.kind === 'walkway') return 'tee või kivi';
    if (cell.kind === 'empty') return 'tühi ala';
    if (!cell.active) return 'kasutamata ruut';
    return 'peenraruut';
}

function selectCell(cell: BedCell) {
    selectedCellId.value = cell.id;
    highlightedCellId.value = cell.id;

    if (highlightTimeout) clearTimeout(highlightTimeout);
    highlightTimeout = setTimeout(() => {
        if (highlightedCellId.value === cell.id) highlightedCellId.value = null;
    }, 900);

    void nextTick(() => {
        const target = document.querySelector<HTMLElement>(
            `[data-bed-cell-id="${cell.id}"]`,
        );
        target?.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
            behavior: 'smooth',
        });
    });
}

function cellsOverlapGrid(
    ax: number,
    ay: number,
    aw: number,
    ah: number,
    bx: number,
    by: number,
    bw: number,
    bh: number,
): boolean {
    return !(ax + aw <= bx || bx + bw <= ax || ay + ah <= by || by + bh <= ay);
}

function hasLayoutOverlaps(layout: LayoutItem[]): boolean {
    for (let i = 0; i < layout.length; i += 1) {
        for (let j = i + 1; j < layout.length; j += 1) {
            if (
                cellsOverlapGrid(
                    layout[i].x,
                    layout[i].y,
                    layout[i].w,
                    layout[i].h,
                    layout[j].x,
                    layout[j].y,
                    layout[j].w,
                    layout[j].h,
                )
            ) {
                return true;
            }
        }
    }

    return false;
}

/** Paigutab plokid rea kaupa kõrvuti — ei jää üksteise taha. */
function spreadLayoutApart(layout: LayoutItem[]): LayoutItem[] {
    const order = new Map(
        cells.value.map((cell, index) => [cell.id, index]),
    );
    const items = [...layout].sort(
        (a, b) =>
            (order.get(String(a.i)) ?? Number.MAX_SAFE_INTEGER) -
            (order.get(String(b.i)) ?? Number.MAX_SAFE_INTEGER),
    );
    let x = 0;
    let y = 0;
    let rowH = 0;

    return items.map((item) => {
        if (x + item.w > dynamicColNum.value) {
            x = 0;
            y += rowH;
            rowH = 0;
        }

        const placed = { ...item, x, y };
        x += item.w;
        rowH = Math.max(rowH, item.h);
        return placed;
    });
}

function applyPackedLayoutToGrid(packed: LayoutItem[]) {
    gridLayout.value = packed.map((item) => ({ ...item }));
    applyGridLayoutToCells(gridLayout.value);
}

function repackGridLayoutNow() {
    if (gridLayout.value.length === 0) return;
    applyPackedLayoutToGrid(spreadLayoutApart(gridLayout.value));
}

let pendingLayoutRepack = false;

function mergeLayoutMetricsFromLibrary(newLayout: LayoutItem[]) {
    for (const item of newLayout) {
        const target = gridLayout.value.find((entry) => entry.i === item.i);
        if (!target) continue;
        target.w = item.w;
        target.h = item.h;
    }
}

function applyLayoutPositionsFromLibrary(newLayout: LayoutItem[]) {
    for (const item of newLayout) {
        const target = gridLayout.value.find((entry) => entry.i === item.i);
        if (!target) continue;
        target.x = item.x;
        target.y = item.y;
        target.w = item.w;
        target.h = item.h;
    }
    applyGridLayoutToCells(gridLayout.value);
}

function normalizeGridLayoutFromLibrary(newLayout: LayoutItem[]) {
    const libraryOverlaps = hasLayoutOverlaps(newLayout);
    const shouldRepack =
        pendingLayoutRepack ||
        libraryOverlaps ||
        hasLayoutOverlaps(gridLayout.value);

    if (shouldRepack) {
        pendingLayoutRepack = false;
        mergeLayoutMetricsFromLibrary(newLayout);
        repackGridLayoutNow();
        return;
    }

    applyLayoutPositionsFromLibrary(newLayout);
}

function addCellAt(x: number, y: number, kind: DesignBrush = activeBrush.value) {
    if (x < 0 || y < 0) {
        const shiftX = x < 0 ? -x : 0;
        const shiftY = y < 0 ? -y : 0;
        if (shiftX || shiftY) {
            cells.value = cells.value.map((cell) => ({
                ...cell,
                x: cell.x + shiftX,
                y: cell.y + shiftY,
            }));
            gridLayout.value = gridLayout.value.map((item) => ({
                ...item,
                x: item.x + shiftX,
                y: item.y + shiftY,
            }));
            x += shiftX;
            y += shiftY;
            applyGridLayoutToCells(gridLayout.value);
        }
    }
    const occupied = gridLayout.value.some((item) =>
        cellsOverlapGrid(
            x,
            y,
            DEFAULT_BLOCK_UNITS,
            DEFAULT_BLOCK_UNITS,
            item.x,
            item.y,
            item.w,
            item.h,
        ),
    );
    if (occupied) return;
    const unit = editorUnitCm();
    const cell: BedCell = {
        id: makeCellId(x, y),
        x,
        y,
        w: DEFAULT_BLOCK_UNITS,
        h: DEFAULT_BLOCK_UNITS,
        left_cm: x * unit,
        top_cm: y * unit,
        width_cm: DEFAULT_BLOCK_UNITS * unit,
        height_cm: DEFAULT_BLOCK_UNITS * unit,
        active: kind === 'plantable',
        kind,
        plants: [],
    };
    cells.value.push(cell);
    pushLayoutItem(layoutItemForCell(cell));
    selectCell(cell);
    form.clearErrors('cells');
}

function onLayoutUpdated(newLayout: LayoutItem[]) {
    normalizeGridLayoutFromLibrary(newLayout);
}

async function scrollSelectedCellIntoView() {
    await nextTick();
    const id = selectedCellId.value;
    if (!id) return;
    const target = document.querySelector<HTMLElement>(
        `[data-bed-cell-id="${id}"]`,
    );
    target?.scrollIntoView({
        block: 'nearest',
        inline: 'nearest',
        behavior: 'smooth',
    });
}

const selectedHasPlants = computed(() => Boolean(selectedCellLive.value?.plants.length));
const formFeedback = computed(() => {
    const layoutError = (form.errors as Record<string, string | undefined>)
        .layout;

    if (form.errors.cells || layoutError) {
        return {
            tone: 'error' as const,
            message:
                form.errors.cells ??
                layoutError ??
                'Peenart ei saanud salvestada.',
        };
    }

    if (form.recentlySuccessful) {
        return {
            tone: 'success' as const,
            message:
                props.mode === 'edit'
                    ? 'Peenra muudatused on salvestatud.'
                    : 'Peenar on loodud.',
        };
    }

    return null;
});

function removeSelectedCell() {
    if (!selectedCell.value || selectedHasPlants.value) return;
    if (selectedCell.value.kind === 'plantable' && activeCells.value.length <= 1) return;

    const current = selectedCell.value;
    cells.value = cells.value.filter((cell) => cell.id !== current.id);
    removeLayoutItem(current.id);
    selectedCellId.value = cells.value[0]?.id ?? '';
}

function selectedBrickSizeLabel(cell: BedCell): string {
    return `${cell.width_cm} × ${cell.height_cm} cm`;
}

function toggleSelectedWalkway() {
    if (!selectedCell.value || selectedHasPlants.value) return;
    const cell = selectedCell.value;
    const next: DesignBrush = cell.kind === 'walkway' ? 'plantable' : 'walkway';
    setCellKindAt(cell.x, cell.y, next);
}

function setCellKindAt(x: number, y: number, kind: DesignBrush) {
    const existing = cells.value.find(
        (cell) =>
            cell.x === x &&
            cell.y === y,
    );
    if (existing?.plants.length && kind !== 'plantable') {
        form.setError(
            'cells',
            'Taimedega ruutu ei saa muuta teeks või tühjaks alaks.',
        );
        selectCell(existing);
        return;
    }

    if (existing) {
        cells.value = cells.value.map((cell) =>
            cell.id === existing.id
                ? {
                      ...cell,
                      active: kind === 'plantable',
                      kind,
                      plants: kind === 'plantable' ? cell.plants : [],
                  }
                : cell,
        );
        selectCell({ ...existing, active: kind === 'plantable', kind });
        form.clearErrors('cells');
        return;
    }

    addCellAt(x, y, kind);
}

function resetToSingleCell() {
    if (activeCells.value.some((cell) => cell.plants.length > 0)) {
        form.setError(
            'cells',
            'Taimedega peenart ei saa ühe nupuga tühjendada.',
        );
        return;
    }
    const unit = editorUnitCm();
    const cell: BedCell = {
        id: makeCellId(0, 0),
        x: 0,
        y: 0,
        w: DEFAULT_BLOCK_UNITS,
        h: DEFAULT_BLOCK_UNITS,
        left_cm: 0,
        top_cm: 0,
        width_cm: DEFAULT_BLOCK_UNITS * unit,
        height_cm: DEFAULT_BLOCK_UNITS * unit,
        active: true,
        kind: 'plantable',
        plants: [],
    };
    cells.value = [cell];
    selectedCellId.value = cell.id;
    replaceGridLayout([layoutItemForCell(cell)]);
}

function fillFromGardenPlan() {
    if (!props.gardenPlan) return;
    const widthCm = Math.max(1, Math.round(props.gardenPlan.width));
    const heightCm = Math.max(1, Math.round(props.gardenPlan.height));
    const maxDim = Math.max(widthCm, heightCm);
    let cellCm = 30;
    if (maxDim > 1000) cellCm = 200;
    else if (maxDim > 400) cellCm = 100;
    else if (maxDim > 200) cellCm = 50;
    form.cell_size_cm = cellCm;
    const cols = Math.max(1, Math.round(widthCm / cellCm));
    const rows = Math.max(1, Math.round(heightCm / cellCm));
    cells.value = [];
    gridLayout.value.splice(0, gridLayout.value.length);
    for (let y = 0; y < rows; y++) {
        for (let x = 0; x < cols; x++) {
            const gx = x * DEFAULT_BLOCK_UNITS;
            const gy = y * DEFAULT_BLOCK_UNITS;
            const cell: BedCell = {
                id: makeCellId(gx, gy),
                x: gx,
                y: gy,
                w: DEFAULT_BLOCK_UNITS,
                h: DEFAULT_BLOCK_UNITS,
                left_cm: x * cellCm,
                top_cm: y * cellCm,
                width_cm: cellCm,
                height_cm: cellCm,
                kind: 'plantable' as const,
                active: true,
                plants: [],
            };
            cells.value.push(cell);
            pushLayoutItem(layoutItemForCell(cell));
        }
    }
    form.clearErrors('cells');
}

function goToStep(step: WizardStep) {
    if (step > currentStep.value && !canContinueFromStep.value) {
        if (currentStep.value === 1) {
            if (!form.name.trim()) {
                form.setError('name', 'Pane peenrale nimi, siis liigume edasi.');
            } else {
                form.setError(
                    'cell_size_cm',
                    'Ühe ruudu mõõt peab olema 10–200 cm.',
                );
            }
        }
        if (currentStep.value === 2) {
            form.setError('cells', 'Peenras peab olema vähemalt üks ruut.');
        }
        if (
            placementStep.value !== null &&
            currentStep.value === placementStep.value
        ) {
            form.setError(
                'cells',
                'Vali aiaplaanil peenra asukoht enne järgmist sammu.',
            );
        }
        return;
    }
    if (step !== 2) {
        form.clearErrors('cells');
    }
    if (
        placementStep.value !== null &&
        step === placementStep.value &&
        currentStep.value === 2
    ) {
        syncCellsToForm();
    }
    currentStep.value = step;
}

function nextStep() {
    if (currentStep.value >= maxStep.value) return;
    if (currentStep.value === 2 && placementStep.value !== null) {
        syncCellsToForm();
    }
    goToStep((currentStep.value + 1) as WizardStep);
}

function previousStep() {
    if (currentStep.value === 1) return;
    currentStep.value = (currentStep.value - 1) as WizardStep;
}

function layoutValueForCell(cell: BedCell): number {
    if (cell.kind === 'walkway') {
        return -1;
    }
    if (cell.kind === 'empty' || !cell.active) {
        return 0;
    }

    return 1;
}

function syncCellsToForm() {
    const b = layoutBounds.value;
    const layout: number[][] = [];
    for (let y = b.minY; y <= b.maxY; y += 1) {
        const row: number[] = [];
        for (let x = b.minX; x <= b.maxX; x += 1) {
            row.push(0);
        }
        layout.push(row);
    }

    cells.value.forEach((cell) => {
        const value = layoutValueForCell(cell);
        for (let dy = 0; dy < cell.h; dy += 1) {
            for (let dx = 0; dx < cell.w; dx += 1) {
                const row = cell.y - b.minY + dy;
                const col = cell.x - b.minX + dx;
                if (layout[row]?.[col] !== undefined) {
                    layout[row][col] = value;
                }
            }
        }
    });

    form.layout = layout;
    const minLeftCm = cells.value.length
        ? Math.min(...cells.value.map((cell) => cell.left_cm))
        : 0;
    const minTopCm = cells.value.length
        ? Math.min(...cells.value.map((cell) => cell.top_cm))
        : 0;

    form.cell_bricks = cells.value.map((cell) => ({
        x: cell.x - b.minX,
        y: cell.y - b.minY,
        w: cell.w,
        h: cell.h,
        left_cm: Math.round(cell.left_cm - minLeftCm),
        top_cm: Math.round(cell.top_cm - minTopCm),
        width_cm: cell.width_cm,
        height_cm: cell.height_cm,
        kind: cell.kind,
    }));
    form.cells = cells.value
        .filter((cell) => cell.plants.length > 0)
        .map((cell) => ({
            x: cell.x - b.minX,
            y: cell.y - b.minY,
            w: cell.w,
            h: cell.h,
            kind: cell.kind,
            plants: cell.plants.map((plant) => ({
                plant_id: plant.plant_id,
                quantity: plant.quantity,
                size: plant.size,
                note: plant.note,
            })),
        }));
}

function resetForm() {
    if (props.mode === 'edit' && props.bed) {
        router.get(`/beds/${props.bed.id}`, {}, { preserveScroll: true });
        return;
    }

    const planId = props.gardenPlanId ?? props.bed?.garden_plan_id;
    if (planId != null) {
        router.get(`/map/${planId}`, {}, { preserveScroll: true });
        return;
    }
    router.get('/map', {}, { preserveScroll: true });
}

function submit() {
    if (!form.name.trim()) {
        form.setError('name', 'Peenra nimi on kohustuslik.');
        return;
    }

    if (!activeCells.value.length) {
        form.setError('cells', 'Peenras peab olema vähemalt üks ruut.');
        return;
    }

    if (
        hasGardenPlacement.value &&
        (gardenX.value == null || gardenY.value == null)
    ) {
        form.setError('cells', 'Vali peenra asukoht aiaplaanil.');
        currentStep.value = placementStep.value ?? 3;
        return;
    }

    syncCellsToForm();
    form.clearErrors('name');
    form.clearErrors('cells');

    if (props.mode !== 'edit' && props.gardenPlanId == null) {
        form.setError(
            'name',
            'Aiaplaan puudub. Ava kaart uuesti ja proovi uuesti.',
        );
        return;
    }

    form.transform((data) => {
        const base = {
            ...data,
            name: data.name.trim(),
            location: data.location.trim(),
            cell_size_cm: Math.max(
                10,
                Math.min(200, Math.round(Number(data.cell_size_cm || DEFAULT_BED_CELL_SIZE_CM))),
            ),
            cells: form.cells,
        };
        if (props.mode !== 'edit' && props.gardenPlanId != null) {
            const payload: Record<string, unknown> = {
                ...base,
                garden_plan_id: props.gardenPlanId,
            };
            if (gardenX.value != null && gardenY.value != null) {
                payload.garden_x = Math.round(gardenX.value);
                payload.garden_y = Math.round(gardenY.value);
            }
            return payload;
        }
        return base;
    });

    if (props.mode === 'edit' && props.bed) {
        form.put(`/beds/${props.bed.id}`, {
            forceFormData: true,
            preserveScroll: true,
        });
    } else {
        form.post('/beds', {
            forceFormData: true,
            preserveScroll: true,
        });
    }
}

async function onImageChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    const normalizedFile = file ? await normalizeImageForUpload(file) : null;
    form.image = normalizedFile;

    if (newBedImagePreview.value) {
        URL.revokeObjectURL(newBedImagePreview.value);
        newBedImagePreview.value = null;
    }

    if (normalizedFile) {
        newBedImagePreview.value = URL.createObjectURL(normalizedFile);
    }
}

onBeforeUnmount(() => {
    if (bedShapeResizeObserver) {
        bedShapeResizeObserver.disconnect();
        bedShapeResizeObserver = null;
    }
    if (newBedImagePreview.value) {
        URL.revokeObjectURL(newBedImagePreview.value);
    }
    if (highlightTimeout) {
        clearTimeout(highlightTimeout);
    }
});

onMounted(() => {
    syncContainerWidth();
});

watch(
    bedShapeGridHost,
    (host) => {
        if (bedShapeResizeObserver) {
            bedShapeResizeObserver.disconnect();
            bedShapeResizeObserver = null;
        }
        if (!host) return;
        syncContainerWidth();
        bedShapeResizeObserver = new ResizeObserver(() => {
            syncContainerWidth();
        });
        bedShapeResizeObserver.observe(host);
    },
    { immediate: true },
);

watch(selectedCellId, () => {
    void scrollSelectedCellIntoView();
});

watch(
    () => form.cell_size_cm,
    () => {
        if (cells.value.length > 0) {
            recalcCellsCmFromGridUnit();
        }
    },
);
</script>

<template>
    <section class="mb-8">
        <form class="space-y-4 pb-24 sm:pb-0" @submit.prevent="submit">
            <nav
                class="rounded-[1.5rem] border border-emerald-900/10 bg-[linear-gradient(135deg,rgba(236,253,245,0.92),rgba(255,251,235,0.82))] p-3 shadow-[0_16px_36px_rgba(49,79,55,0.12)]"
                aria-label="Peenra loomise sammud"
            >
                <ol
                    class="grid gap-2"
                    :class="
                        wizardSteps.length === 4
                            ? 'grid-cols-2 sm:grid-cols-4'
                            : 'grid-cols-3'
                    "
                >
                    <li v-for="step in wizardSteps" :key="step.id">
                        <button
                            type="button"
                            class="wizard-step"
                            :class="
                                currentStep === step.id
                                    ? 'wizard-step--active'
                                    : currentStep > step.id
                                      ? 'wizard-step--done'
                                      : ''
                            "
                            @click="goToStep(step.id)"
                        >
                            <span class="material-symbols-outlined">{{
                                step.icon
                            }}</span>
                            <span>{{ step.label }}</span>
                        </button>
                    </li>
                </ol>
            </nav>

            <section
                v-show="currentStep === 1"
                class="overflow-hidden rounded-[1.75rem] bg-card ring-1 shadow-soft ring-border/70"
            >
                <div class="border-b border-border/60 px-4 py-4 sm:px-6">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="material-symbols-outlined flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                yard
                            </span>
                            <div>
                                <h2
                                    class="text-lg font-semibold tracking-tight text-foreground"
                                >
                                    Peenar nimeta
                                </h2>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    Pane peenrale nimi ja vali ühe ruudu tegelik
                                    mõõt.
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="currentStep !== 1"
                            class="rounded-full bg-background/80 px-3 py-1.5 text-xs font-semibold text-muted-foreground ring-1 ring-border/70"
                        >
                            {{ activeCells.length }} ruutu
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="bed-step1-fields mx-auto max-w-xl">
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1fr)_7.5rem] sm:items-start sm:gap-3"
                        >
                            <div class="min-w-0">
                                <label class="floating-field floating-field--compact">
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        placeholder=" "
                                        maxlength="120"
                                        @input="form.clearErrors('name')"
                                    />
                                    <span>Peenra nimi</span>
                                </label>
                                <p
                                    v-if="form.errors.name"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div class="sm:max-w-[7.5rem]">
                                <label class="floating-field floating-field--compact">
                                    <input
                                        v-model="form.cell_size_cm"
                                        type="number"
                                        min="10"
                                        max="200"
                                        step="5"
                                        placeholder=" "
                                        class="text-center sm:text-left"
                                        @input="form.clearErrors('cell_size_cm')"
                                    />
                                    <span>Ruut (cm)</span>
                                </label>
                                <p
                                    v-if="form.errors.cell_size_cm"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.cell_size_cm }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-show="currentStep === 2">
                <div
                    class="rounded-[1.5rem] bg-card p-4 ring-1 shadow-soft ring-border/70 sm:p-5"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <h3
                                class="text-lg font-semibold tracking-tight text-foreground"
                            >
                                Peenra kuju
                            </h3>
                            <p
                                class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground"
                            >
                                Lohista peenraruutu või käiguteed allpool olevasse kujundusalasse, et luua ruute.
                                Ruutusid saab lohistada, suurust muuta nurgast ja eemaldada paremal paneelil.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-start"
                    >
                        <div class="min-w-0 flex-1 overflow-hidden rounded-[1.25rem] bg-card ring-1 ring-border/70">
                            <div class="border-b border-border/60 bg-muted/30 px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div
                                        draggable="true"
                                        role="button"
                                        tabindex="0"
                                        class="bed-brush-palette-item"
                                        :class="{
                                            'bed-brush-palette-item--active': activeBrush === 'plantable',
                                            'bed-brush-palette-item--dragging': externalDragKind === 'plantable',
                                        }"
                                        aria-label="Peenraruut — lohista kujundusalasse"
                                        @click="activeBrush = 'plantable'"
                                        @dragstart="onChipDragStart('plantable', $event)"
                                        @dragend="onChipDragEnd"
                                    >
                                        <div
                                            :class="brushPaletteCellClasses('plantable')"
                                        >
                                            <div
                                                class="pointer-events-none absolute inset-x-0 top-0 h-1/2 bg-linear-to-b from-white/15 to-transparent"
                                                aria-hidden="true"
                                            />
                                            <span
                                                class="material-symbols-outlined bed-cell-slot-icon relative z-10"
                                                aria-hidden="true"
                                            >add</span>
                                            <div
                                                class="bed-brush-palette-handle pointer-events-none"
                                                aria-hidden="true"
                                            >
                                                <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor">
                                                    <circle cx="2" cy="2" r="1.2" />
                                                    <circle cx="8" cy="2" r="1.2" />
                                                    <circle cx="2" cy="5" r="1.2" />
                                                    <circle cx="8" cy="5" r="1.2" />
                                                    <circle cx="2" cy="8" r="1.2" />
                                                    <circle cx="8" cy="8" r="1.2" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        draggable="true"
                                        role="button"
                                        tabindex="0"
                                        class="bed-brush-palette-item"
                                        :class="{
                                            'bed-brush-palette-item--active': activeBrush === 'walkway',
                                            'bed-brush-palette-item--dragging': externalDragKind === 'walkway',
                                        }"
                                        aria-label="Käigutee — lohista kujundusalasse"
                                        @click="activeBrush = 'walkway'"
                                        @dragstart="onChipDragStart('walkway', $event)"
                                        @dragend="onChipDragEnd"
                                    >
                                        <div :class="brushPaletteCellClasses('walkway')">
                                            <div
                                                class="pointer-events-none absolute inset-x-0 top-0 h-1/2 bg-linear-to-b from-white/15 to-transparent"
                                                aria-hidden="true"
                                            />
                                            <span
                                                class="material-symbols-outlined relative z-10 text-lg text-stone-700/55"
                                                aria-hidden="true"
                                            >texture</span>
                                            <div
                                                class="bed-brush-palette-handle pointer-events-none"
                                                aria-hidden="true"
                                            >
                                                <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor">
                                                    <circle cx="2" cy="2" r="1.2" />
                                                    <circle cx="8" cy="2" r="1.2" />
                                                    <circle cx="2" cy="5" r="1.2" />
                                                    <circle cx="8" cy="5" r="1.2" />
                                                    <circle cx="2" cy="8" r="1.2" />
                                                    <circle cx="8" cy="8" r="1.2" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="h-5 w-px bg-border/60" aria-hidden="true" />
                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center gap-1.5 rounded-full border border-border/60 bg-background/80 px-3 text-xs text-muted-foreground transition hover:border-primary/30 hover:text-foreground"
                                        title="Muuda sammul „Peenar nimeta“"
                                        @click="goToStep(1)"
                                    >
                                        <span class="material-symbols-outlined text-sm">straighten</span>
                                        1 ruut =
                                        <strong class="font-semibold text-foreground">{{ cellSizeCmLabel }}</strong>
                                        cm
                                    </button>
                                    <span class="flex-1" />
                                    <button
                                        v-if="cells.length > 0"
                                        type="button"
                                        class="text-[11px] font-semibold text-muted-foreground underline-offset-2 transition hover:text-foreground hover:underline"
                                        @click="resetToSingleCell"
                                    >
                                        Tühjenda
                                    </button>
                                    <button
                                        v-if="gardenPlan && cells.length === 0"
                                        type="button"
                                        class="text-[11px] font-semibold text-primary underline-offset-2 transition hover:underline"
                                        @click="fillFromGardenPlan"
                                    >
                                        Täida kogu ala
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="cells.length === 0"
                                class="bed-canvas flex min-h-[26rem] items-center justify-center p-4"
                                :class="{ 'bed-canvas--drag-over': externalDragKind }"
                                @dragover.prevent="onCanvasDragOver"
                                @dragleave="onCanvasDragLeave"
                                @drop.prevent="onCanvasDrop"
                            >
                                <div class="pointer-events-none flex min-h-32 w-full max-w-sm flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-emerald-900/25 bg-background/50 px-4 py-6">
                                    <span class="material-symbols-outlined text-2xl text-emerald-700/50">drag_indicator</span>
                                    <p class="text-center text-sm font-medium text-emerald-950/60 select-none">
                                        Lohista ruut siia, et alustada kuju loomist.
                                    </p>
                                </div>
                            </div>
                            <div
                                v-else
                                ref="bedShapeGridHost"
                                class="bed-canvas bed-shape-grid relative min-h-[26rem] max-h-[clamp(26rem,84vh,68rem)] overflow-auto overscroll-contain p-4"
                                :class="{ 'bed-canvas--drag-over': externalDragKind }"
                                @dragover.prevent="onCanvasDragOver"
                                @dragleave="onCanvasDragLeave"
                                @drop.prevent="onCanvasDrop"
                            >
                                <div
                                    v-if="dragPreviewGridPos && externalDragKind"
                                    class="pointer-events-none absolute z-50"
                                    :style="{
                                        left: `${16 + dragPreviewGridPos.x * (CELL_PX + GRID_MARGIN[0])}px`,
                                        top: `${16 + dragPreviewGridPos.y * (CELL_PX + GRID_MARGIN[1])}px`,
                                        width: `${DEFAULT_BLOCK_PX}px`,
                                        height: `${DEFAULT_BLOCK_PX}px`,
                                    }"
                                    aria-hidden="true"
                                >
                                    <div
                                        :class="[
                                            ...brushPaletteCellClasses(externalDragKind),
                                            'size-full opacity-75 ring-2 ring-primary/40 ring-offset-1',
                                        ]"
                                    >
                                        <span
                                            class="material-symbols-outlined absolute inset-0 z-10 m-auto flex size-8 items-center justify-center"
                                            :class="externalDragKind === 'walkway'
                                                ? 'text-stone-700/55'
                                                : 'bed-cell-slot-icon'"
                                            aria-hidden="true"
                                        >{{ externalDragKind === 'walkway' ? 'texture' : 'add' }}</span>
                                    </div>
                                </div>
                                <GridLayout
                                    v-model:layout="gridLayout"
                                    :col-num="dynamicColNum"
                                    :row-height="CELL_PX"
                                    :margin="GRID_MARGIN"
                                    is-draggable
                                    is-resizable
                                    :vertical-compact="false"
                                    :prevent-collision="false"
                                    auto-size
                                    use-css-transforms
                                    @layout-updated="onLayoutUpdated"
                                >
                                    <GridItem
                                        v-for="item in gridLayout"
                                        :key="item.i"
                                        :i="item.i"
                                        :x="item.x"
                                        :y="item.y"
                                        :w="item.w"
                                        :h="item.h"
                                        :min-w="1"
                                        :min-h="1"
                                        drag-allow-from=".bed-shape-drag-handle"
                                        drag-ignore-from=".no-drag"
                                    >
                                        <div v-if="cellMap.get(item.i)" class="bed-shape-cell-shell relative size-full">
                                            <div
                                                class="no-drag bed-shape-cell size-full"
                                                :class="cellClasses(cellMap.get(item.i)!)"
                                                role="button"
                                                tabindex="0"
                                                :aria-label="cellKindLabel(cellMap.get(item.i)!)"
                                                :data-bed-cell-id="cellMap.get(item.i)!.id"
                                                @click.stop="selectCell(cellMap.get(item.i)!)"
                                                @keydown.enter.prevent="selectCell(cellMap.get(item.i)!)"
                                                @keydown.space.prevent="selectCell(cellMap.get(item.i)!)"
                                            >
                                                <div class="absolute inset-x-0 top-0 h-1/2 bg-linear-to-b from-white/15 to-transparent" />
                                                <div v-if="cellMap.get(item.i)!.plants.length" class="absolute inset-0 bg-linear-to-t from-black/45 via-black/15 to-transparent" />
                                                <div class="relative z-10 flex size-full flex-col items-center justify-center px-1 text-center">
                                                    <span
                                                        class="material-symbols-outlined"
                                                        :class="
                                                            cellMap.get(item.i)!.plants.length ? 'text-lg text-white'
                                                            : isPlantableEmptySlot(cellMap.get(item.i)!) ? 'bed-cell-slot-icon'
                                                            : selectedCell?.id === cellMap.get(item.i)!.id ? 'text-lg text-primary'
                                                            : 'text-lg text-emerald-900/45'
                                                        "
                                                    >{{ cellIcon(cellMap.get(item.i)!) }}</span>
                                                    <span v-if="cellMap.get(item.i)!.plants.length" class="mt-1 line-clamp-2 text-[9px] font-semibold leading-tight text-white">
                                                        {{ getPlantNames(cellMap.get(item.i)!).join(', ') }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="bed-shape-drag-handle" title="Lohista" aria-label="Lohista ruutu">
                                                <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor" aria-hidden="true">
                                                    <circle cx="2" cy="2" r="1.2" />
                                                    <circle cx="8" cy="2" r="1.2" />
                                                    <circle cx="2" cy="5" r="1.2" />
                                                    <circle cx="8" cy="5" r="1.2" />
                                                    <circle cx="2" cy="8" r="1.2" />
                                                    <circle cx="8" cy="8" r="1.2" />
                                                </svg>
                                            </div>
                                        </div>
                                    </GridItem>
                                </GridLayout>
                            </div>
                        </div>

                        <aside
                            v-if="selectedCellLive && cells.length > 0"
                            class="w-full shrink-0 space-y-2 rounded-xl border border-border/60 bg-card p-3 shadow-sm lg:sticky lg:top-2 lg:w-36"
                            aria-label="Valitud ploki seaded"
                        >
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="size-2.5 rounded-full"
                                    :class="selectedCellLive.kind === 'walkway' ? 'bg-stone-400' : 'bg-emerald-500'"
                                />
                                <span class="text-xs font-semibold capitalize text-foreground">
                                    {{ cellKindLabel(selectedCellLive) }}
                                </span>
                            </div>
                            <div class="rounded-lg bg-muted/50 px-2.5 py-2 text-center">
                                <p class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    Mõõt
                                </p>
                                <p class="mt-0.5 text-sm font-bold text-foreground">
                                    {{ selectedBrickSizeLabel(selectedCellLive) }}
                                </p>
                                <p class="mt-0.5 text-[9px] leading-snug text-muted-foreground">
                                    Muuda suurust<br />nurgas lohistades
                                </p>
                            </div>
                            <button
                                v-if="!selectedHasPlants"
                                type="button"
                                class="w-full rounded-lg border border-border/60 bg-muted/40 px-2 py-1.5 text-left text-[11px] font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                @click="toggleSelectedWalkway"
                            >
                                → {{ selectedCellLive.kind === 'walkway' ? 'peenraruut' : 'käigutee' }}
                            </button>
                            <p v-if="selectedHasPlants" class="text-[10px] leading-snug text-primary">
                                Taimed — mõõtu muudad peenra vaates.
                            </p>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-red-200/60 bg-red-50/40 px-2 py-1.5 text-left text-[11px] font-semibold text-red-700/80 transition hover:bg-red-50 hover:text-red-700 disabled:opacity-35"
                                :disabled="selectedHasPlants || (selectedCellLive?.kind === 'plantable' && activeCells.length <= 1)"
                                @click="removeSelectedCell"
                            >
                                Eemalda ruut
                            </button>
                        </aside>
                    </div>
                </div>
            </section>

            <section
                v-if="hasGardenPlacement"
                v-show="currentStep === 3"
                class="overflow-hidden rounded-[1.75rem] bg-card ring-1 shadow-soft ring-border/70"
            >
                <div class="border-b border-border/60 px-4 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span
                            class="material-symbols-outlined flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            map
                        </span>
                        <div>
                            <h2
                                class="text-lg font-semibold tracking-tight text-foreground"
                            >
                                Paiguta aiaplaanile
                            </h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                <template
                                    v-if="
                                        gardenPlan &&
                                        gardenPlan.center_lat != null &&
                                        gardenPlan.center_lng != null
                                    "
                                >
                                    Klõpsa kaardil, kuhu peenar tuleb — ortofotol
                                    märgitakse täpiga.
                                </template>
                                <template v-else>
                                    Kanna valmis peenar aia joonisele — ruudustik
                                    on mõõtkavas.
                                </template>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <AddBedGardenPlacement
                        v-if="gardenPlan"
                        :garden-plan="gardenPlan"
                        :existing-beds="existingBeds"
                        :draft-bed="draftBedForPlacement"
                        :garden-x="gardenX"
                        :garden-y="gardenY"
                        @update:garden-x="gardenX = $event"
                        @update:garden-y="gardenY = $event"
                    />
                    <p
                        v-if="form.errors.cells && currentStep === 3"
                        class="mt-3 text-sm text-red-600"
                    >
                        {{ form.errors.cells }}
                    </p>
                </div>
            </section>

            <section
                v-show="currentStep === finishStep"
                class="overflow-hidden rounded-[1.75rem] bg-card ring-1 shadow-soft ring-border/70"
            >
                <div class="border-b border-border/60 px-4 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span
                            class="material-symbols-outlined flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            auto_awesome
                        </span>
                        <div>
                            <h2
                                class="text-lg font-semibold tracking-tight text-foreground"
                            >
                                Viimistlus
                            </h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Lisa pilt, asukoht ja kontrolli kokkuvõtet.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="grid gap-4 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_20rem]"
                >
                    <div class="space-y-4">
                        <label class="floating-field">
                            <input
                                v-model="form.location"
                                type="text"
                                placeholder=" "
                                maxlength="255"
                                @input="form.clearErrors('location')"
                            />
                            <span>Asukoht aias</span>
                        </label>

                        <div>
                            <p class="form-label text-foreground">Ühe ruudu mõõt</p>
                            <p class="mt-2 text-sm font-semibold text-foreground">
                                1 ruut = {{ cellSizeCmLabel }} cm
                            </p>
                            <button
                                type="button"
                                class="mt-1.5 text-xs font-semibold text-primary underline-offset-2 hover:underline"
                                @click="goToStep(1)"
                            >
                                Muuda sammul 1
                            </button>
                        </div>

                        <label class="photo-drop-zone">
                            <input
                                type="file"
                                accept="image/*"
                                class="sr-only"
                                @change="onImageChange"
                            />
                            <span class="material-symbols-outlined"
                                >photo_camera</span
                            >
                            <strong>Lisa peenra foto</strong>
                            <small>Puuduta, et valida pilt galeriist.</small>
                        </label>
                    </div>

                    <aside class="summary-card">
                        <div
                            class="h-28 overflow-hidden rounded-2xl bg-[linear-gradient(135deg,rgba(187,247,208,0.72),rgba(254,243,199,0.68))]"
                        >
                            <div
                                v-if="newBedImagePreview || existingImageUrl"
                                class="h-full w-full bg-cover bg-center"
                                :style="{
                                    backgroundImage: `url('${newBedImagePreview ?? existingImageUrl}')`,
                                }"
                            />
                            <div
                                v-else
                                class="grid h-full place-items-center text-primary"
                            >
                                <span class="material-symbols-outlined text-4xl"
                                    >yard</span
                                >
                            </div>
                        </div>
                        <p
                            class="mt-4 text-xs font-bold tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Kokkuvõte
                        </p>
                        <h3 class="mt-1 text-lg font-semibold text-foreground">
                            {{ form.name || 'Uus peenar' }}
                        </h3>
                        <dl class="mt-3 grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Ruudu mõõt</dt>
                                <dd class="font-semibold text-foreground">
                                    1 ruut = {{ cellSizeCmLabel }} cm
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Kuju</dt>
                                <dd class="font-semibold text-foreground">
                                    {{ bedSummaryLabel }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Mõõt</dt>
                                <dd class="font-semibold text-foreground">
                                    {{ bedPhysicalSizeLabel }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Asukoht</dt>
                                <dd
                                    class="text-right font-semibold text-foreground"
                                >
                                    {{ form.location || 'Lisamata' }}
                                </dd>
                            </div>
                        </dl>
                    </aside>
                </div>
            </section>

            <div
                v-if="formFeedback"
                class="rounded-[1.5rem] px-4 py-3 ring-1"
                :class="
                    formFeedback.tone === 'error'
                        ? 'bg-rose-50 text-rose-700 ring-rose-200'
                        : 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                "
            >
                <p class="text-sm font-medium">{{ formFeedback.message }}</p>
            </div>

            <div
                class="fixed right-4 bottom-[4.85rem] left-4 z-40 rounded-[1.25rem] bg-card/95 p-3 shadow-lg ring-1 ring-border/80 backdrop-blur sm:static sm:mx-0 sm:bg-transparent sm:p-0 sm:shadow-none sm:ring-0"
            >
                <div
                    class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row sm:justify-end sm:gap-3"
                >
                    <button
                        type="button"
                        class="btn-primary-outline bg-background/80 sm:order-1"
                        :disabled="form.processing"
                        @click="
                            currentStep === 1 ? resetForm() : previousStep()
                        "
                    >
                        {{
                            currentStep === 1
                                ? mode === 'edit'
                                    ? 'Tagasi'
                                    : 'Tühista'
                                : 'Tagasi'
                        }}
                    </button>
                    <button
                        v-if="currentStep < maxStep"
                        type="button"
                        class="btn-primary shadow-sm sm:order-2"
                        :disabled="!canContinueFromStep"
                        @click="nextStep"
                    >
                        Järgmine
                    </button>
                    <button
                        v-else
                        type="submit"
                        class="btn-primary shadow-sm sm:order-2"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? 'Salvestan...'
                                : mode === 'edit'
                                  ? 'Salvesta muudatused'
                                  : 'Loo peenar'
                        }}
                    </button>
                </div>
            </div>
        </form>
    </section>
</template>

<style scoped>
.wizard-step {
    display: flex;
    min-height: 3rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border: 1px solid rgba(70, 95, 57, 0.14);
    border-radius: 1rem;
    padding: 0.55rem 0.45rem;
    color: var(--muted-foreground);
    background: rgba(255, 255, 255, 0.58);
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.15;
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        background-color 180ms ease,
        color 180ms ease;
}

.wizard-step .material-symbols-outlined {
    font-size: 1rem;
}

.wizard-step--active {
    color: var(--primary);
    border-color: color-mix(in srgb, var(--primary), transparent 58%);
    background: color-mix(in srgb, var(--primary), white 90%);
    box-shadow: 0 10px 22px rgba(49, 79, 55, 0.12);
    transform: translateY(-1px);
}

.wizard-step--done {
    color: rgb(22, 101, 52);
    background: rgba(220, 252, 231, 0.74);
}

.floating-field {
    position: relative;
    display: block;
}

.floating-field input {
    width: 100%;
    height: 4rem;
    border: 1px solid var(--input);
    border-radius: 1.25rem;
    background:
        linear-gradient(
            135deg,
            rgba(255, 255, 255, 0.72),
            rgba(236, 253, 245, 0.48)
        ),
        var(--background);
    padding: 1.45rem 1rem 0.55rem;
    color: var(--foreground);
    font-size: 1.08rem;
    font-weight: 700;
    outline: none;
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        transform 180ms ease;
}

.floating-field span {
    position: absolute;
    top: 1.25rem;
    left: 1rem;
    color: var(--muted-foreground);
    font-size: 0.95rem;
    font-weight: 700;
    pointer-events: none;
    transition:
        transform 180ms ease,
        color 180ms ease,
        font-size 180ms ease;
}

.floating-field input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary), transparent 82%);
    transform: translateY(-1px);
}

.floating-field input:focus + span,
.floating-field input:not(:placeholder-shown) + span {
    color: var(--primary);
    font-size: 0.72rem;
    transform: translateY(-0.85rem);
}

.floating-field--compact input {
    height: 3.25rem;
    border-radius: 1rem;
    padding: 1.05rem 0.875rem 0.4rem;
    font-size: 1rem;
}

.floating-field--compact span {
    top: 1rem;
    left: 0.875rem;
    font-size: 0.8125rem;
    font-weight: 600;
}

.floating-field--compact input:focus + span,
.floating-field--compact input:not(:placeholder-shown) + span {
    transform: translateY(-0.7rem);
}

.preset-card {
    display: flex;
    width: 100%;
    min-height: 4.25rem;
    align-items: center;
    gap: 0.8rem;
    border: 1px solid rgba(70, 95, 57, 0.14);
    border-radius: 1.1rem;
    padding: 0.75rem;
    text-align: left;
    background:
        radial-gradient(
            circle at 20% 15%,
            rgba(255, 255, 255, 0.72),
            transparent 35%
        ),
        linear-gradient(
            135deg,
            rgba(236, 253, 245, 0.82),
            rgba(255, 251, 235, 0.62)
        );
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        box-shadow 220ms ease;
}

.preset-card:hover,
.preset-card:focus-visible {
    transform: translateY(-2px);
    border-color: rgba(16, 185, 129, 0.42);
    box-shadow: 0 14px 28px rgba(44, 70, 46, 0.12);
}

.preset-preview {
    display: grid;
    width: 3.2rem;
    height: 3.2rem;
    flex-shrink: 0;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.16rem;
    border-radius: 0.9rem;
    padding: 0.45rem;
    background: rgba(255, 255, 255, 0.7);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.preset-preview i {
    border-radius: 0.18rem;
    background: linear-gradient(145deg, rgb(134, 191, 120), rgb(70, 128, 74));
}

.design-brush {
    display: flex;
    min-height: 4.35rem;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid rgba(70, 95, 57, 0.14);
    border-radius: 1.1rem;
    padding: 0.75rem;
    text-align: left;
    color: var(--foreground);
    background:
        radial-gradient(
            circle at 18% 18%,
            rgba(255, 255, 255, 0.82),
            transparent 34%
        ),
        color-mix(in srgb, var(--card), var(--primary) 4%);
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        box-shadow 180ms ease,
        background-color 160ms ease;
}

.design-brush:hover,
.design-brush:focus-visible {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--primary), transparent 55%);
    box-shadow: 0 12px 24px rgba(44, 70, 46, 0.1);
}

.design-brush--active {
    border-color: color-mix(in srgb, var(--primary), transparent 35%);
    background:
        linear-gradient(
            135deg,
            color-mix(in srgb, var(--primary), white 88%),
            rgba(255, 251, 235, 0.78)
        ),
        var(--card);
    box-shadow:
        0 14px 30px rgba(44, 70, 46, 0.14),
        inset 0 0 0 1px rgba(255, 255, 255, 0.55);
}

.design-brush .material-symbols-outlined {
    display: grid;
    width: 2.35rem;
    height: 2.35rem;
    flex-shrink: 0;
    place-items: center;
    border-radius: 999px;
    color: var(--primary);
    background: rgba(255, 255, 255, 0.7);
}

.design-brush strong,
.design-brush small {
    display: block;
}

.design-brush strong {
    font-size: 0.86rem;
    line-height: 1.1;
}

.design-brush small {
    margin-top: 0.18rem;
    color: var(--muted-foreground);
    font-size: 0.72rem;
    line-height: 1.25;
}

.legend-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.35rem 0.65rem;
    border: 1px solid transparent;
}

.legend-pill::before {
    content: '';
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 999px;
}

.legend-pill--plantable {
    color: rgb(22, 101, 52);
    border-color: rgba(34, 197, 94, 0.24);
    background: rgba(220, 252, 231, 0.68);
}

.legend-pill--plantable::before {
    background: linear-gradient(145deg, rgb(134, 191, 120), rgb(70, 128, 74));
}

.legend-pill--walkway {
    color: rgb(87, 83, 78);
    border-color: rgba(120, 113, 108, 0.24);
    background: rgba(245, 245, 244, 0.82);
}

.legend-pill--walkway::before {
    background:
        radial-gradient(
            circle at 30% 30%,
            rgba(255, 255, 255, 0.8),
            transparent 25%
        ),
        rgb(168, 162, 158);
}

.legend-pill--inactive {
    color: rgb(100, 116, 139);
    border-color: rgba(148, 163, 184, 0.28);
    background: rgba(248, 250, 252, 0.45);
}

.legend-pill--inactive::before {
    border: 1px dashed rgba(148, 163, 184, 0.45);
    background: transparent;
}

.legend-pill--empty {
    color: rgb(100, 116, 139);
    border-color: rgba(148, 163, 184, 0.28);
    background: rgba(248, 250, 252, 0.86);
}

.legend-pill--empty::before {
    border: 1px dashed rgba(100, 116, 139, 0.65);
    background: rgba(255, 255, 255, 0.52);
}

.bed-cell {
    min-width: 44px;
    min-height: 44px;
    border-radius: 0.9rem;
    touch-action: none;
}

.bed-canvas {
    background-color: color-mix(in srgb, var(--card), rgb(240, 235, 220) 18%);
    background-image: radial-gradient(
        circle,
        rgba(120, 100, 60, 0.18) 1px,
        transparent 1px
    );
    background-size: 82px 82px; /* 3 * CELL_PX + 2 * GRID_MARGIN */
    border-radius: 0 0 1.25rem 1.25rem;
}

.bed-canvas.bed-shape-grid :deep(.vgl-item) .bed-cell {
    border-width: 1.5px;
    box-shadow:
        0 4px 14px rgba(49, 79, 55, 0.16),
        0 1px 0 rgba(255, 255, 255, 0.5) inset;
}

.bed-cell--editor-selected {
    border-color: var(--primary) !important;
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--primary), transparent 72%),
        0 6px 16px color-mix(in srgb, var(--primary), transparent 78%) !important;
    transform: translateY(-1px);
}

.bed-canvas--drag-over {
    outline: 2px dashed rgba(34, 197, 94, 0.45);
    outline-offset: -4px;
}

.bed-brush-palette-item {
    cursor: grab;
    padding: 3px;
    border: 2px solid transparent;
    border-radius: 1rem;
    touch-action: none;
    user-select: none;
    transition:
        border-color 160ms ease,
        box-shadow 160ms ease,
        opacity 160ms ease,
        transform 160ms ease;
}

.bed-brush-palette-item:hover {
    transform: translateY(-1px);
}

.bed-brush-palette-item:active {
    cursor: grabbing;
}

.bed-brush-palette-item--active {
    border-color: color-mix(in srgb, var(--primary), transparent 35%);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary), transparent 78%);
}

.bed-brush-palette-item--dragging {
    opacity: 0.45;
}

.bed-brush-palette-cell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    min-width: 56px;
    min-height: 56px;
    border-width: 1.5px;
    box-shadow:
        0 4px 14px rgba(49, 79, 55, 0.16),
        0 1px 0 rgba(255, 255, 255, 0.5) inset;
}

.bed-brush-palette-handle {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.92);
    color: rgba(60, 80, 50, 0.72);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.bed-shape-cell-shell {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    touch-action: none;
}

/* grid-layout-plus funktsionaalsus; visuaal tuleb .bed-cell klassidest */
.bed-shape-grid :deep(.vgl-item:not(.vgl-item--placeholder)) {
    background: transparent;
    border: none;
}

.bed-shape-grid .no-drag {
    width: 100%;
    height: 100%;
}

.bed-shape-drag-handle {
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 40;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 5px;
    background: rgba(255, 255, 255, 0.92);
    color: rgba(60, 80, 50, 0.72);
    cursor: grab;
    touch-action: none;
    pointer-events: auto;
    opacity: 1;
    transition:
        opacity 120ms ease,
        transform 120ms ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.bed-shape-cell-shell:hover .bed-shape-drag-handle,
.bed-shape-grid :deep(.vgl-item--dragging) .bed-shape-drag-handle,
.bed-shape-grid :deep(.vgl-item:has(.bed-cell--editor-selected)) .bed-shape-drag-handle {
    opacity: 1;
    transform: scale(1.03);
}

.bed-shape-drag-handle:active {
    cursor: grabbing;
}

/* grid-layout-plus: muutujad peavad olema .vgl-layout peal (teek defineerib seal vaikimisi punase placeholderi) */
.bed-shape-grid :deep(.vgl-layout) {
    --vgl-placeholder-bg: rgba(34, 197, 94, 0.16);
    --vgl-placeholder-opacity: 100%;
    --vgl-placeholder-z-index: 1;
    --vgl-resizer-size: 18px;
    --vgl-resizer-border-color: rgba(22, 101, 52, 0.55);
    --vgl-resizer-border-width: 2.5px;
    --vgl-item-dragging-opacity: 100%;
    --vgl-item-resizing-opacity: 95%;
    min-height: 10rem;
}

.bed-shape-grid :deep(.vgl-item) {
    overflow: visible;
    transition:
        left 180ms ease,
        top 180ms ease,
        width 180ms ease,
        height 180ms ease;
}

/* Lohistamise sihtkoht — roheline kriipsjoon, mitte punane täit */
.bed-shape-grid :deep(.vgl-item--placeholder) {
    background-color: rgba(34, 197, 94, 0.12) !important;
    border: 2px dashed rgba(16, 185, 129, 0.55);
    outline: none;
    border-radius: 0.9rem;
    opacity: 1 !important;
}

.bed-shape-grid :deep(.vgl-item--dragging) {
    filter: drop-shadow(0 10px 22px rgba(49, 79, 55, 0.28));
}

.bed-shape-grid :deep(.vgl-item--dragging .bed-cell),
.bed-shape-grid :deep(.vgl-item--resizing .bed-cell) {
    transform: none;
}

.bed-shape-grid :deep(.vgl-item--resizing) {
    opacity: 95%;
}

.bed-shape-grid :deep(.vgl-item__resizer) {
    z-index: 30;
    width: 18px !important;
    height: 18px !important;
    opacity: 0.45;
    background: transparent;
    transition: opacity 120ms ease;
}

.bed-shape-grid :deep(.vgl-item:hover .vgl-item__resizer),
.bed-shape-grid :deep(.vgl-item--resizing .vgl-item__resizer),
.bed-shape-grid :deep(.vgl-item:has(.bed-cell--editor-selected) .vgl-item__resizer) {
    opacity: 1;
}

.bed-shape-grid :deep(.vgl-item__resizer::before) {
    inset: 0 3px 3px 0;
    border-color: rgba(22, 101, 52, 0.55);
}

.bed-shape-grid :deep(.vgl-item:hover .vgl-item__resizer::before),
.bed-shape-grid :deep(.vgl-item--resizing .vgl-item__resizer::before),
.bed-shape-grid :deep(.vgl-item:has(.bed-cell--editor-selected) .vgl-item__resizer::before) {
    border-color: rgba(22, 101, 52, 0.9);
}

.bed-cell--inactive {
    border-color: rgba(148, 163, 184, 0.22);
    color: rgba(100, 116, 139, 0.45);
    background: transparent;
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.18);
}

.bed-cell--void {
    border-color: rgba(148, 163, 184, 0.28);
    color: rgba(100, 116, 139, 0.58);
    background:
        repeating-linear-gradient(
            -45deg,
            rgba(148, 163, 184, 0.1) 0 6px,
            transparent 6px 12px
        ),
        rgba(248, 250, 252, 0.84);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.64);
}

.photo-drop-zone {
    display: grid;
    min-height: 8.5rem;
    place-items: center;
    border: 1px dashed color-mix(in srgb, var(--primary), transparent 45%);
    border-radius: 1.35rem;
    padding: 1rem;
    text-align: center;
    color: var(--primary);
    background:
        radial-gradient(
            circle at 50% 10%,
            rgba(187, 247, 208, 0.44),
            transparent 36%
        ),
        rgba(236, 253, 245, 0.48);
    cursor: pointer;
}

.photo-drop-zone .material-symbols-outlined {
    font-size: 2rem;
}

.photo-drop-zone strong {
    display: block;
    margin-top: 0.35rem;
    color: var(--foreground);
}

.photo-drop-zone small {
    color: var(--muted-foreground);
}

.summary-card {
    border: 1px solid rgba(70, 95, 57, 0.14);
    border-radius: 1.35rem;
    padding: 1rem;
    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.76),
            rgba(236, 253, 245, 0.52)
        ),
        var(--card);
    box-shadow: 0 14px 30px rgba(44, 70, 46, 0.1);
}
</style>
