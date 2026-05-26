<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import AddBedGardenPlacement from '@/components/map/AddBedGardenPlacement.vue';
import { normalizeImageForUpload } from '@/lib/imageUpload';
import {
    brickCmRectsOverlap,
    brickFitsCm,
    buildBedEditorCmLayout,
    cmToPx,
    EDITOR_PX_PER_CM,
    inferCmPositionsFromGrid,
    packGridIndicesFromCm,
} from '@/pages/map/bedEditorCmLayout';
import type {
    GardenPlacementBed,
    GardenPlacementPlan,
} from '@/pages/map/bedGardenPlacement';

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
const gridScroller = ref<HTMLElement | null>(null);
const selectedCellElement = ref<HTMLElement | null>(null);
const highlightedCellId = ref<string | null>(null);
let highlightTimeout: ReturnType<typeof setTimeout> | null = null;
type WizardStep = 1 | 2 | 3 | 4;
type DesignBrush = 'plantable' | 'walkway' | 'empty';

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
        Math.min(200, Math.round(Number(form.cell_size_cm) || 30)),
    );
}

function clampBrickCm(value: number): number {
    return Math.max(10, Math.min(500, Math.round(value)));
}

/** Igal plokil on oma mõõt (cm); ruudustiku indeks on 1×1. */
function brickFootprintFromCm(
    width_cm: number,
    height_cm: number,
): Pick<BedCell, 'width_cm' | 'height_cm' | 'w' | 'h'> {
    return {
        width_cm: clampBrickCm(width_cm),
        height_cm: clampBrickCm(height_cm),
        w: 1,
        h: 1,
    };
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
    const unitCm = props.bed?.cell_size_cm ?? 30;

    if (Array.isArray(bricks) && bricks.length > 0) {
        return bricks.map((brick) => {
            const w = Math.max(1, brick.w ?? 1);
            const h = Math.max(1, brick.h ?? 1);
            const widthCm = brick.width_cm ?? w * unitCm;
            const heightCm = brick.height_cm ?? h * unitCm;
            const footprint = brickFootprintFromCm(widthCm, heightCm);

            return {
                id: makeCellId(brick.x, brick.y),
                x: brick.x,
                y: brick.y,
                ...footprint,
                left_cm: brick.left_cm,
                top_cm: brick.top_cm,
                active: brick.kind === 'plantable',
                kind: brick.kind ?? 'plantable',
                plants: [...(plantMap.get(`${brick.y},${brick.x}`) ?? [])],
            };
        });
    }

    if (!layout || !Array.isArray(layout) || layout.length === 0) {
        if (props.mode !== 'edit') return [];
        return [
            {
                id: makeCellId(0, 0),
                x: 0,
                y: 0,
                ...brickFootprintFromCm(unitCm, unitCm),
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
            cells.push({
                id: makeCellId(x, y),
                x,
                y,
                ...brickFootprintFromCm(unitCm, unitCm),
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
                  ...brickFootprintFromCm(unitCm, unitCm),
                  active: true,
                  kind: 'plantable',
                  plants: [],
              },
          ];
}

function withCmPositions(
    raw: (Omit<BedCell, 'left_cm' | 'top_cm'> &
        Partial<Pick<BedCell, 'left_cm' | 'top_cm'>>)[],
): BedCell[] {
    const list = raw.map((cell) => ({
        ...cell,
        left_cm: cell.left_cm,
        top_cm: cell.top_cm,
    }));
    inferCmPositionsFromGrid(list);
    return list as BedCell[];
}

const cells = ref<BedCell[]>(withCmPositions(createInitialCells()));
const selectedCellId = ref<string>(cells.value[0]?.id ?? '');

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
    cell_size_cm: props.mode === 'edit' ? (props.bed?.cell_size_cm ?? 30) : 30,
    image: null,
    cells: [],
    layout: [],
    cell_bricks: [],
});

const selectedCell = computed(
    () => cells.value.find((cell) => cell.id === selectedCellId.value) ?? null,
);
const activeCells = computed(() =>
    cells.value.filter((cell) => cell.active && cell.kind === 'plantable'),
);
const activeBricks = computed(() =>
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
    if (!cells.value.length) {
        return `0 × 0 m`;
    }

    const layout = buildBedEditorCmLayout(cells.value);
    return `${formatMeters(layout.totalWidthCm / 100)} × ${formatMeters(layout.totalHeightCm / 100)} m`;
});

const dragOverCm = ref<{ left_cm: number; top_cm: number } | null>(null);
/** Tühi koht vs lohistamine kahe ruudu vahele (teised nihkuvad). */
const dragInsertNeedsShift = ref(false);
type CmShiftMode = 'down' | 'right' | 'both';
const dragShiftMode = ref<CmShiftMode>('both');
const draggingCell = ref<BedCell | null>(null);
const draggingFromPalette = ref(false);
const paletteDragKind = ref<DesignBrush>('plantable');
const POINTER_DRAG_THRESHOLD_PX = 6;
const pointerDragSession = ref<{
    cellId: string;
    pointerId: number;
    startX: number;
    startY: number;
    originLeft: number;
    originTop: number;
    started: boolean;
} | null>(null);
const suppressCellClick = ref(false);
const isDraggingLayout = computed(
    () => draggingFromPalette.value || draggingCell.value !== null,
);

/** Üks rea/võrra äärt ümber — stabiilne suurus lohistamise ajal (ei laiene dragstart'il). */
const GRID_EDGE_PAD = 1;

const displayBounds = computed(() => {
    const b = bounds.value;
    if (b.maxX < b.minX || cells.value.length === 0) {
        return {
            minX: -GRID_EDGE_PAD,
            maxX: GRID_EDGE_PAD,
            minY: -GRID_EDGE_PAD,
            maxY: GRID_EDGE_PAD,
        };
    }

    return {
        minX: b.minX - GRID_EDGE_PAD,
        maxX: b.maxX + GRID_EDGE_PAD,
        minY: b.minY - GRID_EDGE_PAD,
        maxY: b.maxY + GRID_EDGE_PAD,
    };
});

const canContinueFromStep = computed(() => {
    if (currentStep.value === 1) return Boolean(form.name.trim());
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
        Math.min(200, Math.round(Number(form.cell_size_cm) || 30)),
    ),
    layout: form.layout?.length ? form.layout : [[1]],
    cell_bricks: form.cell_bricks?.length ? form.cell_bricks : null,
    rows: form.layout?.length ?? 1,
    columns: form.layout[0]?.length ?? 1,
}));

function displayColumnNumber(x: number): number {
    return x - displayBounds.value.minX + 1;
}

function displayRowNumber(y: number): number {
    return y - displayBounds.value.minY + 1;
}

function formatMeters(value: number): string {
    const rounded = Math.round(value * 10) / 10;
    return Number.isInteger(rounded) ? `${rounded}` : rounded.toFixed(1);
}

function occupiedKey(x: number, y: number): string {
    return `${x}:${y}`;
}

const occupiedCellMap = computed(() => {
    const map = new Map<string, BedCell>();
    layoutCells.value.forEach((cell) => {
        for (let dy = 0; dy < cell.h; dy++) {
            for (let dx = 0; dx < cell.w; dx++) {
                map.set(occupiedKey(cell.x + dx, cell.y + dy), cell);
            }
        }
    });
    return map;
});

function getCellAt(x: number, y: number): BedCell | null {
    return occupiedCellMap.value.get(occupiedKey(x, y)) ?? null;
}

const editorCmLayout = computed(() => buildBedEditorCmLayout(cells.value));

const layoutSurfaceStyle = computed(() => {
    const layout = editorCmLayout.value;
    let widthPx = Math.max(cmToPx(layout.totalWidthCm), cmToPx(80));
    let heightPx = Math.max(cmToPx(layout.totalHeightCm), cmToPx(60));

    for (const cell of cells.value) {
        widthPx = Math.max(
            widthPx,
            cmToPx(cell.left_cm + cell.width_cm) + 4,
        );
        heightPx = Math.max(
            heightPx,
            cmToPx(cell.top_cm + cell.height_cm) + 4,
        );
    }

    return {
        position: 'relative',
        width: `${widthPx}px`,
        height: `${heightPx}px`,
        minWidth: '100%',
    } as const;
});

function cmEditorStyle(
    leftCm: number,
    topCm: number,
    widthCm: number,
    heightCm: number,
): Record<string, string> {
    return {
        position: 'absolute',
        left: `${cmToPx(leftCm)}px`,
        top: `${cmToPx(topCm)}px`,
        width: `${cmToPx(widthCm)}px`,
        height: `${cmToPx(heightCm)}px`,
    };
}

function cellEditorStyle(cell: BedCell): Record<string, string> {
    return cmEditorStyle(cell.left_cm, cell.top_cm, cell.width_cm, cell.height_cm);
}

const layoutSurfaceRef = ref<HTMLElement | null>(null);
/** Vältib dragleave valepositiivset puhastamist laste vahel. */
const layoutDragDepth = ref(0);

const SNAP_CM = 6;

function snapDragCm(
    leftCm: number,
    topCm: number,
    widthCm: number,
    heightCm: number,
    excludeId?: string,
): { left_cm: number; top_cm: number } {
    let bestLeft = Math.max(0, leftCm);
    let bestTop = Math.max(0, topCm);
    let bestDist = SNAP_CM + 1;

    const trySnap = (
        candidateLeft: number,
        candidateTop: number,
        mode: CmShiftMode,
    ) => {
        const top = Math.max(0, candidateTop);
        const left = Math.max(0, candidateLeft);
        const dist = Math.hypot(left - leftCm, top - topCm);
        if (dist > SNAP_CM) {
            return;
        }

        const shifted = cellsAfterCmShift(
            left,
            top,
            widthCm,
            heightCm,
            excludeId,
            mode,
        );
        const fits =
            brickFitsAt(left, top, widthCm, heightCm, excludeId) ||
            brickFitsOnCellList(
                shifted,
                left,
                top,
                widthCm,
                heightCm,
                excludeId,
            );

        if (!fits) {
            return;
        }

        if (dist < bestDist) {
            bestDist = dist;
            bestLeft = left;
            bestTop = top;
        }
    };

    for (const cell of cells.value) {
        if (excludeId && cell.id === excludeId) {
            continue;
        }

        trySnap(cell.left_cm + cell.width_cm, cell.top_cm, 'right');
        trySnap(cell.left_cm - widthCm, cell.top_cm, 'right');
        trySnap(cell.left_cm, cell.top_cm + cell.height_cm, 'down');
        trySnap(cell.left_cm, cell.top_cm - heightCm, 'down');
    }

    return { left_cm: bestLeft, top_cm: bestTop };
}

function addBedEditorCellClasses(x: number, y: number): string[] {
    const cell = getCellAt(x, y);
    if (!cell) {
        return [];
    }
    const isSelected = selectedCell.value?.id === cell.id;
    const hasPlants = cell.plants.length > 0;
    const warm = (x + y) % 2 === 0;
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
        classes.push('bed-cell--editor-selected');
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

    if (!isDraggingLayout.value) {
        void scrollSelectedCellIntoView();
    }
}

async function scrollSelectedCellIntoView() {
    await nextTick();
    const target = selectedCellElement.value;
    if (!target) {
        return;
    }

    target.scrollIntoView({
        block: 'nearest',
        inline: 'nearest',
        behavior: 'smooth',
    });
}

function setSelectedCellElement(el: Element | null, cellId: string) {
    if (!(el instanceof HTMLElement)) return;
    if (cellId === selectedCellId.value) {
        selectedCellElement.value = el;
    }
}

function setSelectedCellRef(el: unknown, cellId: string) {
    setSelectedCellElement(el instanceof HTMLElement ? el : null, cellId);
}

function brickFitsAt(
    leftCm: number,
    topCm: number,
    widthCm: number,
    heightCm: number,
    excludeId?: string,
): boolean {
    return brickFitsCm(
        cells.value,
        leftCm,
        topCm,
        widthCm,
        heightCm,
        excludeId,
    );
}

function makeNewCellAt(
    leftCm: number,
    topCm: number,
    footprint = brickFootprintFromCm(gridUnitCm(), gridUnitCm()),
): BedCell {
    return {
        id: makeCellId(leftCm, topCm),
        x: 0,
        y: 0,
        left_cm: leftCm,
        top_cm: topCm,
        ...footprint,
        active: true,
        kind: 'plantable',
        plants: [],
    };
}

function insertCellAtCm(
    leftCm: number,
    topCm: number,
    existingCell?: BedCell,
    paletteKind: DesignBrush = 'plantable',
) {
    const kind = existingCell?.kind ?? paletteKind;
    const footprint = existingCell
        ? brickFootprintFromCm(existingCell.width_cm, existingCell.height_cm)
        : brickFootprintFromCm(gridUnitCm(), gridUnitCm());

    if (
        !brickFitsAt(
            leftCm,
            topCm,
            footprint.width_cm,
            footprint.height_cm,
            existingCell?.id,
        )
    ) {
        form.setError(
            'cells',
            'Plokk ei mahu siia. Vali teine koht või väiksem suurus.',
        );
        return;
    }

    if (existingCell) {
        cells.value = cells.value.filter((c) => c.id !== existingCell.id);
    }

    const newCell: BedCell = existingCell
        ? {
              ...existingCell,
              left_cm: leftCm,
              top_cm: topCm,
              ...footprint,
          }
        : {
              ...makeNewCellAt(leftCm, topCm, footprint),
              kind,
              active: kind === 'plantable',
          };

    cells.value = [...cells.value, newCell];
    packGridIndicesFromCm(cells.value);
    selectCell(newCell);
    form.clearErrors('cells');
}

function dragFootprint(): Pick<
    BedCell,
    'width_cm' | 'height_cm' | 'w' | 'h'
> {
    if (draggingCell.value) {
        return brickFootprintFromCm(
            draggingCell.value.width_cm,
            draggingCell.value.height_cm,
        );
    }

    const unit = gridUnitCm();
    return brickFootprintFromCm(unit, unit);
}

function cellsAfterCmShift(
    insertLeft: number,
    insertTop: number,
    widthCm: number,
    heightCm: number,
    excludeId?: string,
    mode: CmShiftMode = 'both',
): BedCell[] {
    return cells.value.map((cell) => {
        if (excludeId && cell.id === excludeId) {
            return { ...cell };
        }

        let left_cm = cell.left_cm;
        let top_cm = cell.top_cm;

        const overlaps = brickCmRectsOverlap(
            insertLeft,
            insertTop,
            widthCm,
            heightCm,
            cell.left_cm,
            cell.top_cm,
            cell.width_cm,
            cell.height_cm,
        );

        if (!overlaps) {
            return { ...cell, left_cm, top_cm };
        }

        if (mode === 'right' || mode === 'both') {
            left_cm += widthCm;
        }
        if (mode === 'down' || mode === 'both') {
            top_cm += heightCm;
        }

        return { ...cell, left_cm, top_cm };
    });
}

function brickFitsOnCellList(
    cellList: BedCell[],
    leftCm: number,
    topCm: number,
    widthCm: number,
    heightCm: number,
    excludeId?: string,
): boolean {
    return brickFitsCm(
        cellList,
        leftCm,
        topCm,
        widthCm,
        heightCm,
        excludeId,
    );
}

function shiftCellsForInsert(
    insertLeft: number,
    insertTop: number,
    widthCm: number,
    heightCm: number,
    excludeId?: string,
    mode: CmShiftMode = 'both',
) {
    cells.value = cellsAfterCmShift(
        insertLeft,
        insertTop,
        widthCm,
        heightCm,
        excludeId,
        mode,
    );
    packGridIndicesFromCm(cells.value);
}

function shiftModesToTry(preferred: CmShiftMode): CmShiftMode[] {
    if (preferred === 'right') {
        return ['right'];
    }
    if (preferred === 'down') {
        return ['down'];
    }

    return ['right', 'down', 'both'];
}

function setDragTargetCm(
    leftCm: number,
    topCm: number,
    excludeId?: string,
    shiftMode: CmShiftMode = 'both',
): boolean {
    const footprint = dragFootprint();
    const top = Math.max(0, topCm);
    const left = Math.max(0, leftCm);

    if (
        brickFitsAt(
            left,
            top,
            footprint.width_cm,
            footprint.height_cm,
            excludeId,
        )
    ) {
        dragInsertNeedsShift.value = false;
        dragShiftMode.value = shiftMode;
        dragOverCm.value = { left_cm: left, top_cm: top };
        return true;
    }

    for (const mode of shiftModesToTry(shiftMode)) {
        const shifted = cellsAfterCmShift(
            left,
            top,
            footprint.width_cm,
            footprint.height_cm,
            excludeId,
            mode,
        );
        if (
            brickFitsOnCellList(
                shifted,
                left,
                top,
                footprint.width_cm,
                footprint.height_cm,
                excludeId,
            )
        ) {
            dragInsertNeedsShift.value = true;
            dragShiftMode.value = mode;
            dragOverCm.value = { left_cm: left, top_cm: top };
            return true;
        }
    }

    dragInsertNeedsShift.value = false;
    return false;
}

function columnCellsAt(leftCm: number, excludeId?: string): BedCell[] {
    return cells.value.filter(
        (cell) =>
            Math.abs(cell.left_cm - leftCm) < 0.5 &&
            (!excludeId || cell.id !== excludeId),
    );
}

function rowCellsAt(topCm: number, excludeId?: string): BedCell[] {
    return cells.value.filter(
        (cell) =>
            Math.abs(cell.top_cm - topCm) < 0.5 &&
            (!excludeId || cell.id !== excludeId),
    );
}

function commitDragTarget(
    paletteKind: DesignBrush = 'plantable',
    existingCell?: BedCell,
) {
    const target = dragOverCm.value;
    if (!target) {
        return;
    }

    const excludeId = existingCell?.id ?? draggingCell.value?.id;
    const footprint = existingCell
        ? brickFootprintFromCm(existingCell.width_cm, existingCell.height_cm)
        : dragFootprint();

    if (dragInsertNeedsShift.value) {
        shiftCellsForInsert(
            target.left_cm,
            target.top_cm,
            footprint.width_cm,
            footprint.height_cm,
            excludeId,
            dragShiftMode.value,
        );
    }

    insertCellAtCm(
        target.left_cm,
        target.top_cm,
        existingCell,
        paletteKind,
    );
    dragInsertNeedsShift.value = false;
    dragOverCm.value = null;
}

function setDragDropEffect(event: DragEvent) {
    if (!event.dataTransfer) {
        return;
    }

    event.dataTransfer.dropEffect = draggingFromPalette.value ? 'copy' : 'move';
}

function cleanupPointerDragListeners() {
    window.removeEventListener('pointermove', onWindowPointerMove);
    window.removeEventListener('pointerup', onWindowPointerUp);
    window.removeEventListener('pointercancel', onWindowPointerUp);
}

function buildEdgeDragTargets(
    cell: BedCell,
    relX: number,
    relY: number,
    excludeId?: string,
): { left_cm: number; top_cm: number; mode: CmShiftMode }[] {
    const footprint = dragFootprint();
    const edge = 0.38;
    const tryEdges: {
        left_cm: number;
        top_cm: number;
        mode: CmShiftMode;
    }[] = [];

    if (relY < edge) {
        const column = columnCellsAt(cell.left_cm, excludeId);
        const topmost = column.length
            ? Math.min(...column.map((item) => item.top_cm))
            : cell.top_cm;

        tryEdges.push({
            left_cm: cell.left_cm,
            top_cm: topmost - footprint.height_cm,
            mode: 'down',
        });
        tryEdges.push({
            left_cm: cell.left_cm,
            top_cm: cell.top_cm - footprint.height_cm,
            mode: 'down',
        });
    }
    if (relY > 1 - edge) {
        tryEdges.push({
            left_cm: cell.left_cm,
            top_cm: cell.top_cm + cell.height_cm,
            mode: 'down',
        });
    }
    if (relX > 1 - edge) {
        tryEdges.push({
            left_cm: cell.left_cm + cell.width_cm,
            top_cm: cell.top_cm,
            mode: 'right',
        });
    }
    if (relX < edge) {
        const row = rowCellsAt(cell.top_cm, excludeId);
        const leftmost = row.length
            ? Math.min(...row.map((item) => item.left_cm))
            : cell.left_cm;

        tryEdges.push({
            left_cm: leftmost - footprint.width_cm,
            top_cm: cell.top_cm,
            mode: 'right',
        });
        tryEdges.push({
            left_cm: cell.left_cm - footprint.width_cm,
            top_cm: cell.top_cm,
            mode: 'right',
        });
    }

    return tryEdges;
}

function trySetDragTargetAtClient(
    clientX: number,
    clientY: number,
    excludeId?: string,
): boolean {
    const surface = layoutSurfaceRef.value;
    if (!surface) {
        return false;
    }

    for (const cell of cells.value) {
        if (excludeId && cell.id === excludeId) {
            continue;
        }

        const el = surface.querySelector(
            `[data-bed-cell-id="${cell.id}"]`,
        );
        if (!(el instanceof HTMLElement)) {
            continue;
        }

        const rect = el.getBoundingClientRect();
        if (
            clientX < rect.left ||
            clientX > rect.right ||
            clientY < rect.top ||
            clientY > rect.bottom
        ) {
            continue;
        }

        const relX = (clientX - rect.left) / rect.width;
        const relY = (clientY - rect.top) / rect.height;

        for (const point of buildEdgeDragTargets(
            cell,
            relX,
            relY,
            excludeId,
        )) {
            if (
                setDragTargetCm(
                    point.left_cm,
                    point.top_cm,
                    excludeId,
                    point.mode,
                )
            ) {
                return true;
            }
        }
    }

    const footprint = dragFootprint();
    const rect = surface.getBoundingClientRect();
    const left = Math.max(
        0,
        (clientX - rect.left) / EDITOR_PX_PER_CM - footprint.width_cm / 2,
    );
    const top = Math.max(
        0,
        (clientY - rect.top) / EDITOR_PX_PER_CM - footprint.height_cm / 2,
    );
    const snapped = snapDragCm(
        left,
        top,
        footprint.width_cm,
        footprint.height_cm,
        excludeId,
    );

    for (const mode of ['right', 'down', 'both'] as CmShiftMode[]) {
        if (setDragTargetCm(snapped.left_cm, snapped.top_cm, excludeId, mode)) {
            return true;
        }
    }

    return false;
}

function commitCellMove(cell: BedCell) {
    const target = dragOverCm.value;
    if (!target) {
        return;
    }

    const footprint = brickFootprintFromCm(cell.width_cm, cell.height_cm);

    if (dragInsertNeedsShift.value) {
        shiftCellsForInsert(
            target.left_cm,
            target.top_cm,
            footprint.width_cm,
            footprint.height_cm,
            cell.id,
            dragShiftMode.value,
        );
    }

    cells.value = cells.value.map((item) =>
        item.id === cell.id
            ? {
                  ...item,
                  left_cm: target.left_cm,
                  top_cm: target.top_cm,
              }
            : item,
    );
    packGridIndicesFromCm(cells.value);
    dragOverCm.value = null;
    dragInsertNeedsShift.value = false;
}

function onWindowPointerMove(event: PointerEvent) {
    const session = pointerDragSession.value;
    if (!session || event.pointerId !== session.pointerId) {
        return;
    }

    if (!session.started) {
        const distance = Math.hypot(
            event.clientX - session.startX,
            event.clientY - session.startY,
        );
        if (distance < POINTER_DRAG_THRESHOLD_PX) {
            return;
        }

        session.started = true;
        draggingCell.value =
            cells.value.find((item) => item.id === session.cellId) ?? null;
    }

    if (
        !trySetDragTargetAtClient(
            event.clientX,
            event.clientY,
            session.cellId,
        )
    ) {
        dragOverCm.value = null;
        dragInsertNeedsShift.value = false;
    }
}

function onWindowPointerUp(event: PointerEvent) {
    const session = pointerDragSession.value;
    if (!session || event.pointerId !== session.pointerId) {
        return;
    }

    cleanupPointerDragListeners();

    if (session.started) {
        const cell = cells.value.find((item) => item.id === session.cellId);
        if (cell && dragOverCm.value) {
            suppressCellClick.value = true;
            commitCellMove(cell);
        }
    }

    pointerDragSession.value = null;
    draggingCell.value = null;
    dragOverCm.value = null;
    dragInsertNeedsShift.value = false;
    layoutDragDepth.value = 0;
}

function onCellPointerDown(event: PointerEvent, cell: BedCell) {
    if (event.button !== 0) {
        return;
    }

    pointerDragSession.value = {
        cellId: cell.id,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        originLeft: cell.left_cm,
        originTop: cell.top_cm,
        started: false,
    };

    window.addEventListener('pointermove', onWindowPointerMove);
    window.addEventListener('pointerup', onWindowPointerUp);
    window.addEventListener('pointercancel', onWindowPointerUp);
}

function onCellClick(cell: BedCell) {
    if (suppressCellClick.value) {
        suppressCellClick.value = false;
        return;
    }

    selectCell(cell);
}

function onPaletteDragStart(event: DragEvent, kind: DesignBrush = 'plantable') {
    paletteDragKind.value = kind;
    draggingFromPalette.value = true;
    draggingCell.value = null;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', `palette-${kind}`);
    }
}

function onPaletteDragEnd() {
    draggingFromPalette.value = false;
    paletteDragKind.value = 'plantable';
    dragOverCm.value = null;
    dragInsertNeedsShift.value = false;
    layoutDragDepth.value = 0;
}

function pointerToCm(event: DragEvent): { left_cm: number; top_cm: number } {
    const surface = layoutSurfaceRef.value;
    if (!surface) {
        return { left_cm: 0, top_cm: 0 };
    }

    const rect = surface.getBoundingClientRect();
    const footprint = dragFootprint();

    return {
        left_cm: Math.max(
            0,
            (event.clientX - rect.left) / EDITOR_PX_PER_CM - footprint.width_cm / 2,
        ),
        top_cm: Math.max(
            0,
            (event.clientY - rect.top) / EDITOR_PX_PER_CM - footprint.height_cm / 2,
        ),
    };
}

function onLayoutDragOver(event: DragEvent) {
    if (!draggingFromPalette.value) {
        return;
    }

    const excludeId = undefined;
    const footprint = dragFootprint();
    const pointer = pointerToCm(event);
    const snapped = snapDragCm(
        pointer.left_cm,
        pointer.top_cm,
        footprint.width_cm,
        footprint.height_cm,
        excludeId,
    );

    const layoutModes: CmShiftMode[] = ['right', 'down', 'both'];
    let placed = false;
    for (const mode of layoutModes) {
        if (setDragTargetCm(snapped.left_cm, snapped.top_cm, excludeId, mode)) {
            placed = true;
            break;
        }
    }
    if (!placed) {
        return;
    }

    event.preventDefault();
    setDragDropEffect(event);
}

function onLayoutDrop(event: DragEvent) {
    event.preventDefault();

    if (!dragOverCm.value) {
        return;
    }

    if (draggingFromPalette.value) {
        const kind = paletteDragKind.value;
        draggingFromPalette.value = false;
        paletteDragKind.value = 'plantable';
        commitDragTarget(kind);
        return;
    }

}

function onLayoutDragEnter(event: DragEvent) {
    if (!draggingFromPalette.value) {
        return;
    }

    layoutDragDepth.value += 1;
    event.preventDefault();
}

function onLayoutDragLeave() {
    if (layoutDragDepth.value > 0) {
        layoutDragDepth.value -= 1;
    }

    if (layoutDragDepth.value <= 0) {
        layoutDragDepth.value = 0;
        dragOverCm.value = null;
        dragInsertNeedsShift.value = false;
    }
}

function onPlacedCellDragOver(event: DragEvent, cell: BedCell) {
    if (!draggingFromPalette.value) {
        return;
    }

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const relX = (event.clientX - rect.left) / rect.width;
    const relY = (event.clientY - rect.top) / rect.height;

    for (const point of buildEdgeDragTargets(cell, relX, relY)) {
        if (setDragTargetCm(point.left_cm, point.top_cm, undefined, point.mode)) {
            event.preventDefault();
            event.stopPropagation();
            setDragDropEffect(event);
            return;
        }
    }
}

function onPlacedCellDrop(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();

    if (!dragOverCm.value) {
        return;
    }

    if (draggingFromPalette.value) {
        const kind = paletteDragKind.value;
        draggingFromPalette.value = false;
        paletteDragKind.value = 'plantable';
        commitDragTarget(kind);
        return;
    }

}

function isCellDragTarget(cell: BedCell): boolean {
    if (!dragOverCm.value) {
        return false;
    }

    const footprint = dragFootprint();
    const target = dragOverCm.value;

    return (
        Math.abs(target.left_cm - cell.left_cm) < 0.5 &&
        Math.abs(target.top_cm - cell.top_cm) < 0.5 &&
        Math.abs(footprint.width_cm - cell.width_cm) < 0.5 &&
        Math.abs(footprint.height_cm - cell.height_cm) < 0.5
    );
}

const dragPreviewFootprint = computed(() => dragFootprint());

const dragShiftPreviewStyle = computed((): Record<string, string> | null => {
    if (!dragInsertNeedsShift.value || !dragOverCm.value) {
        return null;
    }

    const footprint = dragPreviewFootprint.value;
    const target = dragOverCm.value;

    return cmEditorStyle(
        target.left_cm,
        target.top_cm,
        footprint.width_cm,
        footprint.height_cm,
    );
});

const dragGhostPreviewStyle = computed((): Record<string, string> | null => {
    if (!dragOverCm.value || dragInsertNeedsShift.value) {
        return null;
    }

    const footprint = dragPreviewFootprint.value;
    const target = dragOverCm.value;

    return cmEditorStyle(
        target.left_cm,
        target.top_cm,
        footprint.width_cm,
        footprint.height_cm,
    );
});

const dragPreviewValid = computed(() => {
    if (!dragOverCm.value) {
        return false;
    }

    const { left_cm, top_cm } = dragOverCm.value;
    const excludeId = draggingCell.value?.id;
    const footprint = dragFootprint();

    if (
        brickFitsAt(
            left_cm,
            top_cm,
            footprint.width_cm,
            footprint.height_cm,
            excludeId,
        )
    ) {
        return true;
    }

    if (!dragInsertNeedsShift.value) {
        return false;
    }

    return brickFitsOnCellList(
        cellsAfterCmShift(
            left_cm,
            top_cm,
            footprint.width_cm,
            footprint.height_cm,
            excludeId,
            dragShiftMode.value,
        ),
        left_cm,
        top_cm,
        footprint.width_cm,
        footprint.height_cm,
        excludeId,
    );
});

const selectedHasPlants = computed(() =>
    Boolean(selectedCell.value?.plants.length),
);
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
    if (activeBricks.value.length <= 1) return;

    const current = selectedCell.value;
    cells.value = cells.value.filter((cell) => cell.id !== current.id);
    selectedCellId.value = cells.value[0]?.id ?? '';
}

function selectedBrickSizeLabel(cell: BedCell): string {
    return `${cell.width_cm} × ${cell.height_cm} cm`;
}

function selectedBrickGridHint(cell: BedCell): string {
    return `Asukoht: ${Math.round(cell.left_cm)} cm vasakult, ${Math.round(cell.top_cm)} cm ülevalt`;
}

function applySelectedBrickCm(width_cm: number, height_cm: number) {
    if (!selectedCell.value || selectedHasPlants.value) {
        return;
    }

    const cell = selectedCell.value;
    const footprint = brickFootprintFromCm(width_cm, height_cm);

    if (
        cell.width_cm === footprint.width_cm &&
        cell.height_cm === footprint.height_cm &&
        cell.w === footprint.w &&
        cell.h === footprint.h
    ) {
        return;
    }

    if (
        !brickFitsAt(
            cell.left_cm,
            cell.top_cm,
            footprint.width_cm,
            footprint.height_cm,
            cell.id,
        )
    ) {
        form.setError(
            'cells',
            footprint.width_cm > cell.width_cm ||
                footprint.height_cm > cell.height_cm
                ? `Suurem plokk (${footprint.width_cm}×${footprint.height_cm} cm) ei mahu — kõrval on teine plokk. Liiguta plokki või eemalda naaber.`
                : 'Plokk ei mahu siia. Vali teine koht või väiksem suurus.',
        );
        return;
    }

    cells.value = cells.value.map((item) =>
        item.id === cell.id
            ? {
                  ...item,
                  ...footprint,
                  active: item.kind === 'plantable',
              }
            : item,
    );
    packGridIndicesFromCm(cells.value);
    form.clearErrors('cells');
}

function toggleSelectedWalkway() {
    if (!selectedCell.value || selectedHasPlants.value) return;
    const cell = selectedCell.value;
    const next: DesignBrush = cell.kind === 'walkway' ? 'plantable' : 'walkway';
    setCellKindAt(cell.left_cm, cell.top_cm, next);
}

function setCellKindAt(leftCm: number, topCm: number, kind: DesignBrush) {
    const existing = cells.value.find(
        (cell) =>
            Math.abs(cell.left_cm - leftCm) < 0.5 &&
            Math.abs(cell.top_cm - topCm) < 0.5,
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

    const newCell: BedCell = {
        ...makeNewCellAt(leftCm, topCm),
        kind,
        active: kind === 'plantable',
    };
    cells.value = [...cells.value, newCell];
    packGridIndicesFromCm(cells.value);
    selectCell(newCell);
    form.clearErrors('cells');
}

function resetToSingleCell() {
    if (activeCells.value.some((cell) => cell.plants.length > 0)) {
        form.setError(
            'cells',
            'Taimedega peenart ei saa ühe nupuga tühjendada.',
        );
        return;
    }
    const unit = gridUnitCm();
    const cell: BedCell = {
        ...makeNewCellAt(0, 0, brickFootprintFromCm(unit, unit)),
        active: true,
        kind: 'plantable',
        plants: [],
    };
    cells.value = [cell];
    selectedCellId.value = cell.id;
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
    for (let y = 0; y < rows; y++) {
        for (let x = 0; x < cols; x++) {
            cells.value.push({
                ...makeNewCellAt(x * cellCm, y * cellCm, {
                    width_cm: cellCm,
                    height_cm: cellCm,
                    w: 1,
                    h: 1,
                }),
                kind: 'plantable' as const,
                active: true,
            });
        }
    }
    packGridIndicesFromCm(cells.value);
    form.clearErrors('cells');
}

function goToStep(step: WizardStep) {
    if (step > currentStep.value && !canContinueFromStep.value) {
        if (currentStep.value === 1) {
            form.setError('name', 'Pane peenrale nimi, siis liigume edasi.');
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
    packGridIndicesFromCm(cells.value);

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
                Math.min(200, Math.round(Number(data.cell_size_cm || 30))),
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
    cleanupPointerDragListeners();
    if (newBedImagePreview.value) {
        URL.revokeObjectURL(newBedImagePreview.value);
    }
    if (highlightTimeout) {
        clearTimeout(highlightTimeout);
    }
});

watch(selectedCellId, () => {
    void scrollSelectedCellIntoView();
});
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
                            class="rounded-full bg-background/80 px-3 py-1.5 text-xs font-semibold text-muted-foreground ring-1 ring-border/70"
                        >
                            {{ activeCells.length }} ruutu
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-4 sm:p-5">
                    <div class="grid gap-4 lg:grid-cols-1">
                        <div>
                            <label class="floating-field">
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
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
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
                                Lohista ülevalt peenraruutu või tee/kivi plokki.
                                Klõpsa plokki — sea mõõt paremal cm-des.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-start"
                    >
                        <div
                            class="min-w-0 flex-1 overflow-hidden rounded-[1.25rem] bg-card ring-1 ring-border/70"
                        >
                            <div
                                class="border-b border-border/60 bg-muted/50 px-3 py-2.5"
                                aria-label="Uue ploki lohistamine ja lähtemõõt"
                            >
                                <p
                                    class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Uus plokk — lohista peenrale
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap items-end gap-2"
                                >
                                    <div
                                        draggable="true"
                                        role="button"
                                        tabindex="0"
                                        class="bed-cell bed-cell--empty bed-cell--warm ring-dashed relative flex size-[3.35rem] shrink-0 cursor-grab touch-manipulation items-center justify-center ring-2 ring-amber-900/25 active:cursor-grabbing"
                                        aria-label="Lohista uus peenraruut"
                                        @dragstart="
                                            onPaletteDragStart(
                                                $event,
                                                'plantable',
                                            )
                                        "
                                        @dragend="onPaletteDragEnd"
                                    >
                                        <span
                                            class="material-symbols-outlined bed-cell-slot-icon"
                                            >add</span
                                        >
                                    </div>
                                    <div
                                        draggable="true"
                                        role="button"
                                        tabindex="0"
                                        class="bed-cell bed-cell--walkway relative flex size-[3.35rem] shrink-0 cursor-grab touch-manipulation items-center justify-center rounded-2xl border border-stone-500/35 ring-2 ring-stone-600/20 active:cursor-grabbing"
                                        aria-label="Lohista tee või kivi"
                                        @dragstart="
                                            onPaletteDragStart(
                                                $event,
                                                'walkway',
                                            )
                                        "
                                        @dragend="onPaletteDragEnd"
                                    >
                                        <span
                                            class="material-symbols-outlined text-lg text-stone-700/75"
                                            >texture</span
                                        >
                                    </div>
                                    <label
                                        class="flex h-[3.35rem] min-w-[4.5rem] flex-col justify-center rounded-xl border border-input bg-background px-2 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                                    >
                                        <span
                                            class="text-[9px] font-semibold tracking-wide text-muted-foreground uppercase"
                                            >Lähtemõõt</span
                                        >
                                        <span
                                            class="mt-0.5 flex items-baseline gap-0.5"
                                        >
                                            <input
                                                v-model="form.cell_size_cm"
                                                type="number"
                                                min="10"
                                                max="200"
                                                step="1"
                                                class="w-full min-w-0 border-0 bg-transparent p-0 text-base leading-none font-semibold text-foreground outline-none"
                                                placeholder="30"
                                                @input="
                                                    form.clearErrors(
                                                        'cell_size_cm',
                                                    )
                                                "
                                            />
                                            <span
                                                class="text-xs font-medium text-muted-foreground"
                                                >cm</span
                                            >
                                        </span>
                                    </label>
                                    <button
                                        v-if="cells.length > 0"
                                        type="button"
                                        class="mb-1 text-[11px] font-semibold text-muted-foreground underline-offset-2 transition hover:text-foreground hover:underline"
                                        @click="resetToSingleCell"
                                    >
                                        Tühjenda kõik
                                    </button>
                                    <button
                                        v-if="gardenPlan && cells.length === 0"
                                        type="button"
                                        class="mb-1 text-[11px] font-semibold text-primary underline-offset-2 transition hover:text-primary/80 hover:underline"
                                        @click="fillFromGardenPlan"
                                    >
                                        Täida kogu ala
                                    </button>
                                </div>
                                <p
                                    v-if="form.errors.cell_size_cm"
                                    class="mt-1.5 text-[10px] text-red-600"
                                >
                                    {{ form.errors.cell_size_cm }}
                                </p>
                            </div>

                            <div
                                class="border-b border-emerald-900/10 bg-emerald-950/5 px-3 py-1"
                            >
                                <p
                                    class="text-[10px] font-semibold tracking-wide text-emerald-950/55 uppercase"
                                >
                                    Sinu peenar
                                </p>
                            </div>

                            <div
                                ref="gridScroller"
                                class="bed-grid-frame relative max-h-[min(42vh,17rem)] min-h-40 overflow-auto overscroll-contain p-3 transition"
                                :class="
                                    cells.length === 0 &&
                                    (draggingCell || draggingFromPalette)
                                        ? 'bg-primary/5'
                                        : ''
                                "
                            >
                                <div
                                    ref="layoutSurfaceRef"
                                    class="mx-auto w-max min-w-0"
                                    :style="layoutSurfaceStyle"
                                    @dragenter.prevent="onLayoutDragEnter"
                                    @dragover.prevent="onLayoutDragOver"
                                    @dragleave="onLayoutDragLeave"
                                    @drop.prevent="onLayoutDrop"
                                >
                                    <div
                                        v-for="cell in cells"
                                        :key="cell.id"
                                        :data-bed-cell-id="cell.id"
                                        role="button"
                                        tabindex="0"
                                        class="relative cursor-grab touch-none select-none active:cursor-grabbing"
                                        :style="cellEditorStyle(cell)"
                                        :ref="
                                            (el) =>
                                                setSelectedCellRef(el, cell.id)
                                        "
                                        :aria-label="`${cellKindLabel(cell)}: veerg ${displayColumnNumber(cell.x)}, rida ${displayRowNumber(cell.y)}`"
                                        :class="[
                                            ...addBedEditorCellClasses(
                                                cell.x,
                                                cell.y,
                                            ),
                                            dragInsertNeedsShift &&
                                            isCellDragTarget(cell)
                                                ? 'ring-2 ring-primary/40'
                                                : '',
                                            pointerDragSession?.cellId ===
                                                cell.id && pointerDragSession.started
                                                ? 'z-20 opacity-40'
                                                : '',
                                        ]"
                                        @pointerdown="
                                            onCellPointerDown($event, cell)
                                        "
                                        @dragover.prevent="
                                            onPlacedCellDragOver(
                                                $event,
                                                cell,
                                            )
                                        "
                                        @drop.prevent="onPlacedCellDrop($event)"
                                        @click.stop="onCellClick(cell)"
                                        @keydown.enter.prevent="onCellClick(cell)"
                                        @keydown.space.prevent="onCellClick(cell)"
                                    >
                                            <div
                                                class="absolute inset-x-0 top-0 h-1/2 bg-linear-to-b from-white/15 to-transparent"
                                            />
                                            <div
                                                v-if="cell.plants.length"
                                                class="absolute inset-0 bg-linear-to-t from-black/45 via-black/15 to-transparent"
                                            />
                                            <div
                                                class="relative z-10 flex size-full flex-col items-center justify-center px-1 text-center"
                                            >
                                                <span
                                                    class="material-symbols-outlined"
                                                    :class="
                                                        cell.plants.length
                                                            ? 'text-lg text-white'
                                                            : isPlantableEmptySlot(
                                                                    cell,
                                                                )
                                                              ? 'bed-cell-slot-icon'
                                                              : selectedCell?.id ===
                                                                  cell.id
                                                                ? 'text-lg text-primary'
                                                                : 'text-lg text-emerald-900/45'
                                                    "
                                                >
                                                    {{ cellIcon(cell) }}
                                                </span>
                                                <span
                                                    v-if="cell.plants.length"
                                                    class="mt-1 line-clamp-2 text-[9px] leading-tight font-semibold text-white"
                                                >
                                                    {{
                                                        getPlantNames(
                                                            cell,
                                                        ).join(', ')
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="mt-0.5 text-[8px] leading-tight font-medium tabular-nums text-emerald-950/50"
                                                >
                                                    {{ cell.width_cm }}×{{
                                                        cell.height_cm
                                                    }}
                                                </span>
                                            </div>
                                    </div>
                                    <div
                                        v-if="dragShiftPreviewStyle"
                                        class="pointer-events-none absolute z-30 rounded-sm border-2 border-dashed border-primary bg-primary/20 shadow-md ring-4 ring-primary/25"
                                        :style="dragShiftPreviewStyle"
                                        aria-hidden="true"
                                    />
                                    <div
                                        v-if="
                                            dragGhostPreviewStyle &&
                                            dragPreviewValid
                                        "
                                        class="pointer-events-none absolute z-25 rounded-sm border-2 border-primary bg-primary/15 shadow-md"
                                        :style="dragGhostPreviewStyle"
                                        aria-hidden="true"
                                    />
                                </div>
                            </div>
                        </div>

                        <aside
                            v-if="selectedCell && cells.length > 0"
                            class="w-full shrink-0 rounded-xl border border-border/70 bg-muted/30 p-2.5 lg:sticky lg:top-2 lg:w-[8.75rem]"
                            aria-label="Valitud ploki seaded"
                        >
                            <p
                                class="text-[11px] leading-tight font-semibold text-foreground"
                            >
                                Valitud
                                <span class="block text-muted-foreground">{{
                                    selectedBrickSizeLabel(selectedCell)
                                }}</span>
                                <span
                                    class="mt-0.5 block text-[10px] font-normal text-muted-foreground/80"
                                    >{{
                                        selectedBrickGridHint(selectedCell)
                                    }}</span
                                >
                            </p>

                            <p
                                v-if="selectedHasPlants"
                                class="mt-2 text-[11px] leading-snug text-primary"
                            >
                                Taimed — mõõtu muudad peenra vaates.
                            </p>

                            <template v-else>
                                <label
                                    class="mt-2 flex flex-col rounded-lg border border-input bg-background px-2 py-1.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                                >
                                    <span
                                        class="text-[9px] font-semibold text-muted-foreground uppercase"
                                        >Laius</span
                                    >
                                    <span class="flex items-baseline gap-0.5">
                                        <input
                                            :value="selectedCell.width_cm"
                                            type="number"
                                            min="10"
                                            max="500"
                                            step="5"
                                            class="w-full min-w-0 border-0 bg-transparent p-0 text-sm font-semibold text-foreground outline-none"
                                            @input="
                                                applySelectedBrickCm(
                                                    Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                    selectedCell.height_cm,
                                                )
                                            "
                                        />
                                        <span
                                            class="text-[10px] text-muted-foreground"
                                            >cm</span
                                        >
                                    </span>
                                </label>
                                <label
                                    class="mt-1.5 flex flex-col rounded-lg border border-input bg-background px-2 py-1.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                                >
                                    <span
                                        class="text-[9px] font-semibold text-muted-foreground uppercase"
                                        >Kõrgus</span
                                    >
                                    <span class="flex items-baseline gap-0.5">
                                        <input
                                            :value="selectedCell.height_cm"
                                            type="number"
                                            min="10"
                                            max="500"
                                            step="5"
                                            class="w-full min-w-0 border-0 bg-transparent p-0 text-sm font-semibold text-foreground outline-none"
                                            @input="
                                                applySelectedBrickCm(
                                                    selectedCell.width_cm,
                                                    Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                                        />
                                        <span
                                            class="text-[10px] text-muted-foreground"
                                            >cm</span
                                        >
                                    </span>
                                </label>
                                <button
                                    type="button"
                                    class="mt-2 w-full text-left text-[10px] font-semibold text-muted-foreground underline-offset-2 hover:underline"
                                    @click="toggleSelectedWalkway"
                                >
                                    {{
                                        selectedCell.kind === 'walkway'
                                            ? '→ peenraruut'
                                            : '→ tee / kivi'
                                    }}
                                </button>
                            </template>

                            <button
                                type="button"
                                class="mt-2 w-full text-left text-[10px] font-semibold text-red-700/90 underline-offset-2 hover:underline disabled:opacity-40"
                                :disabled="
                                    selectedHasPlants ||
                                    activeBricks.length <= 1
                                "
                                @click="removeSelectedCell"
                            >
                                Eemalda
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
                            <label class="form-label text-foreground"
                                >Ühe ruudu mõõt</label
                            >
                            <div
                                class="flex items-center rounded-xl border border-input bg-background px-4 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                            >
                                <input
                                    v-model="form.cell_size_cm"
                                    type="number"
                                    min="10"
                                    max="200"
                                    step="1"
                                    class="h-12 min-w-0 flex-1 border-0 bg-transparent text-base text-foreground outline-none"
                                    placeholder="30"
                                    @input="form.clearErrors('cell_size_cm')"
                                />
                                <span
                                    class="text-sm font-medium text-muted-foreground"
                                    >cm</span
                                >
                            </div>
                            <p
                                class="mt-2 inline-flex items-center gap-2 rounded-full bg-primary/8 px-3 py-1.5 text-sm font-semibold text-primary"
                            >
                                <span
                                    class="material-symbols-outlined text-base"
                                    >straighten</span
                                >
                                1 ruut = {{ form.cell_size_cm || 30 }} cm
                            </p>
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
                <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
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
