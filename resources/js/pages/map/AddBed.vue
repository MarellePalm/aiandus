<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import { normalizeImageForUpload } from '@/lib/imageUpload';

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
                kind: 'plantable' | 'walkway' | 'empty';
            }> | null;
            plants?: BedPlant[];
        };
    }>(),
    {
        mode: 'create',
        gardenPlanId: undefined,
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
type WizardStep = 1 | 2 | 3;
type DesignBrush = 'plantable' | 'walkway' | 'empty';

const currentStep = ref<WizardStep>(
    props.initialStep && [1, 2, 3].includes(props.initialStep)
        ? props.initialStep
        : 1,
);
const wizardSteps = [
    { id: 1 as const, label: 'Peenar nimeta', icon: 'edit' },
    { id: 2 as const, label: 'Kujunda kuju', icon: 'grid_view' },
    { id: 3 as const, label: 'Viimistlus', icon: 'photo_camera' },
];
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

function cmToGridSpan(cm: number, unitCm = gridUnitCm()): number {
    return Math.max(1, Math.min(4, Math.ceil(cm / unitCm)));
}

function brickFootprintFromCm(
    width_cm: number,
    height_cm: number,
    unitCm = gridUnitCm(),
): Pick<BedCell, 'width_cm' | 'height_cm' | 'w' | 'h'> {
    const widthCm = clampBrickCm(width_cm);
    const heightCm = clampBrickCm(height_cm);

    return {
        width_cm: widthCm,
        height_cm: heightCm,
        w: cmToGridSpan(widthCm, unitCm),
        h: cmToGridSpan(heightCm, unitCm),
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
            const footprint = brickFootprintFromCm(widthCm, heightCm, unitCm);

            return {
                id: makeCellId(brick.x, brick.y),
                x: brick.x,
                y: brick.y,
                ...footprint,
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
                ...brickFootprintFromCm(unitCm, unitCm, unitCm),
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
                ...brickFootprintFromCm(unitCm, unitCm, unitCm),
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
                  ...brickFootprintFromCm(unitCm, unitCm, unitCm),
                  active: true,
                  kind: 'plantable',
                  plants: [],
              },
          ];
}

const cells = ref<BedCell[]>(createInitialCells());
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
    const b = layoutBounds.value;
    if (b.maxX < b.minX || !cells.value.length) {
        return `0 × 0 m`;
    }

    const unit = gridUnitCm();
    let maxRight = 0;
    let maxBottom = 0;

    cells.value.forEach((cell) => {
        const left = (cell.x - b.minX) * unit;
        const top = (cell.y - b.minY) * unit;
        maxRight = Math.max(maxRight, left + cell.width_cm);
        maxBottom = Math.max(maxBottom, top + cell.height_cm);
    });

    return `${formatMeters(maxRight / 100)} × ${formatMeters(maxBottom / 100)} m`;
});

const dragOverCell = ref<{ x: number; y: number } | null>(null);
const draggingCell = ref<BedCell | null>(null);
const draggingFromPalette = ref(false);
const isDraggingLayout = computed(
    () => draggingFromPalette.value || draggingCell.value !== null,
);

const displayBounds = computed(() => {
    const b = bounds.value;
    if (b.maxX < b.minX) {
        return { minX: 0, maxX: 0, minY: 0, maxY: 0 };
    }
    if (isDraggingLayout.value) {
        return {
            minX: b.minX - 1,
            maxX: b.maxX + 1,
            minY: b.minY - 1,
            maxY: b.maxY + 1,
        };
    }

    return { minX: b.minX, maxX: b.maxX, minY: b.minY, maxY: b.maxY };
});

const displayRows = computed(() =>
    Array.from(
        { length: displayBounds.value.maxY - displayBounds.value.minY + 1 },
        (_, i) => displayBounds.value.minY + i,
    ),
);
const displayColumns = computed(() =>
    Array.from(
        { length: displayBounds.value.maxX - displayBounds.value.minX + 1 },
        (_, i) => displayBounds.value.minX + i,
    ),
);

const canContinueFromStep = computed(() => {
    if (currentStep.value === 1) return Boolean(form.name.trim());
    if (currentStep.value === 2) return activeCells.value.length > 0;
    return true;
});

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

function anchorCellAt(x: number, y: number): BedCell | null {
    const cell = getCellAt(x, y);
    if (!cell || cell.x !== x || cell.y !== y) return null;
    return cell;
}

const GRID_CELL_SIZE = '3.35rem';
const GRID_CELL_GAP = '0.625rem';

/** Paiguta ruut täpsele (x,y) koordinaadile — ilma selleta läheb CSS auto-placement sassi. */
function gridCellPlacement(
    x: number,
    y: number,
    w: number,
    h: number,
): Record<string, string> {
    const colStart = x - displayBounds.value.minX + 1;
    const rowStart = y - displayBounds.value.minY + 1;
    const style: Record<string, string> = {
        gridColumnStart: String(colStart),
        gridRowStart: String(rowStart),
    };

    if (w > 1) {
        style.gridColumn = `span ${w}`;
    }
    if (h > 1) {
        style.gridRow = `span ${h}`;
    }
    if (w > 1 || h > 1) {
        style.minWidth = `calc(${w} * ${GRID_CELL_SIZE} + (${w - 1} * ${GRID_CELL_GAP}))`;
        style.minHeight = `calc(${h} * ${GRID_CELL_SIZE} + (${h - 1} * ${GRID_CELL_GAP}))`;
    }

    return style;
}

const gridFrameStyle = computed(() => ({
    gridTemplateColumns: `repeat(${displayColumns.value.length}, ${GRID_CELL_SIZE})`,
    gridTemplateRows: `repeat(${displayRows.value.length}, ${GRID_CELL_SIZE})`,
}));

const dragDropSlots = computed(() => {
    if (!isDraggingLayout.value) {
        return [];
    }

    const slots: { x: number; y: number }[] = [];
    for (const y of displayRows.value) {
        for (const x of displayColumns.value) {
            if (!getCellAt(x, y)) {
                slots.push({ x, y });
            }
        }
    }

    return slots;
});

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

    void scrollSelectedCellIntoView();
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

function brickFits(
    x: number,
    y: number,
    w: number,
    h: number,
    excludeId?: string,
): boolean {
    for (let dy = 0; dy < h; dy += 1) {
        for (let dx = 0; dx < w; dx += 1) {
            const occupant = getCellAt(x + dx, y + dy);
            if (occupant && occupant.id !== excludeId) {
                return false;
            }
        }
    }

    return true;
}

function makeNewCellAt(x: number, y: number): BedCell {
    const unit = gridUnitCm();

    return {
        id: makeCellId(x, y),
        x,
        y,
        ...brickFootprintFromCm(unit, unit),
        active: true,
        kind: 'plantable',
        plants: [],
    };
}

function insertCellAt(x: number, y: number, existingCell?: BedCell) {
    const kind = existingCell?.kind ?? 'plantable';
    const footprint = existingCell
        ? brickFootprintFromCm(existingCell.width_cm, existingCell.height_cm)
        : brickFootprintFromCm(gridUnitCm(), gridUnitCm());

    if (!brickFits(x, y, footprint.w, footprint.h, existingCell?.id)) {
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
        ? { ...existingCell, x, y, ...footprint }
        : {
              ...makeNewCellAt(x, y),
              ...footprint,
              kind,
              active: kind === 'plantable',
          };

    cells.value = [...cells.value, newCell];
    selectCell(newCell);
    form.clearErrors('cells');
}

function onCellDragStart(event: DragEvent, cell: BedCell) {
    draggingCell.value = cell;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', cell.id);
    }
}

function onCellDragEnd() {
    draggingCell.value = null;
    draggingFromPalette.value = false;
    dragOverCell.value = null;
}

function onPaletteDragStart(event: DragEvent) {
    draggingFromPalette.value = true;
    draggingCell.value = null;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', 'palette-cell');
    }
}

function onPaletteDragEnd() {
    draggingFromPalette.value = false;
    dragOverCell.value = null;
}

function onCellDragOver(event: DragEvent, x: number, y: number) {
    if (!draggingCell.value && !draggingFromPalette.value) return;
    if (getCellAt(x, y)) return;

    const footprint = draggingCell.value
        ? brickFootprintFromCm(
              draggingCell.value.width_cm,
              draggingCell.value.height_cm,
          )
        : brickFootprintFromCm(gridUnitCm(), gridUnitCm());

    if (!brickFits(x, y, footprint.w, footprint.h, draggingCell.value?.id)) {
        return;
    }

    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = draggingFromPalette.value
            ? 'copy'
            : 'move';
    }
    dragOverCell.value = { x, y };
}

function onCellDragLeave() {
    dragOverCell.value = null;
}

function onCellDrop(event: DragEvent, x: number, y: number) {
    event.preventDefault();
    dragOverCell.value = null;

    if (draggingFromPalette.value) {
        draggingFromPalette.value = false;
        if (!getCellAt(x, y)) {
            insertCellAt(x, y);
        }
        return;
    }

    if (draggingCell.value) {
        const cell = draggingCell.value;
        draggingCell.value = null;
        if (cell.x === x && cell.y === y) return;
        cells.value = cells.value.filter((c) => c.id !== cell.id);
        insertCellAt(x, y, cell);
    }
}

function onEmptyCanvasDragOver(event: DragEvent) {
    if (!draggingCell.value && !draggingFromPalette.value) return;
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = draggingFromPalette.value
            ? 'copy'
            : 'move';
    }
}

function onEmptyCanvasDrop(event: DragEvent) {
    event.preventDefault();
    const fromPalette = draggingFromPalette.value;
    draggingCell.value = null;
    draggingFromPalette.value = false;
    dragOverCell.value = null;
    if (fromPalette || cells.value.length === 0) {
        insertCellAt(0, 0);
    }
}

function isDragPreview(x: number, y: number): boolean {
    if (!dragOverCell.value) return false;
    if (getCellAt(x, y)) return false;
    return dragOverCell.value.x === x && dragOverCell.value.y === y;
}

const dragPreviewValid = computed(() => {
    if (!dragOverCell.value) {
        return false;
    }

    const { x, y } = dragOverCell.value;
    const footprint = draggingCell.value
        ? brickFootprintFromCm(
              draggingCell.value.width_cm,
              draggingCell.value.height_cm,
          )
        : brickFootprintFromCm(gridUnitCm(), gridUnitCm());

    return brickFits(x, y, footprint.w, footprint.h, draggingCell.value?.id);
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
    const unit = gridUnitCm();
    if (cell.w === 1 && cell.h === 1) {
        return `1 ruut (${unit} cm ruudustikus)`;
    }

    return `${cell.w}×${cell.h} ruutu ruudustikus`;
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

    if (!brickFits(cell.x, cell.y, footprint.w, footprint.h, cell.id)) {
        const unit = gridUnitCm();
        form.setError(
            'cells',
            footprint.w > cell.w || footprint.h > cell.h
                ? `Suurem plokk (${footprint.w}×${footprint.h} ruutu, ${unit} cm/ruut) ei mahu — kõrval on teine plokk. Liiguta plokki või eemalda naaber.`
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
    form.clearErrors('cells');
}

function toggleSelectedWalkway() {
    if (!selectedCell.value || selectedHasPlants.value) return;
    const cell = selectedCell.value;
    const next: DesignBrush = cell.kind === 'walkway' ? 'plantable' : 'walkway';
    setCellKindAt(cell.x, cell.y, next);
}

function setCellKindAt(x: number, y: number, kind: DesignBrush) {
    const existing = getCellAt(x, y);
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
        ...makeNewCellAt(x, y),
        kind,
        active: kind === 'plantable',
    };
    cells.value = [...cells.value, newCell];
    selectCell(newCell);
    form.clearErrors('cells');
}

function onPlacedCellClick(x: number, y: number) {
    const cell = anchorCellAt(x, y);
    if (!cell) return;
    selectCell(cell);
}

function resetToSingleCell() {
    if (activeCells.value.some((cell) => cell.plants.length > 0)) {
        form.setError(
            'cells',
            'Taimedega peenart ei saa ühe nupuga tühjendada.',
        );
        return;
    }
    const cell: BedCell = {
        id: makeCellId(0, 0),
        x: 0,
        y: 0,
        ...brickFootprintFromCm(gridUnitCm(), gridUnitCm()),
        active: true,
        kind: 'plantable',
        plants: [],
    };
    cells.value = [cell];
    selectedCellId.value = cell.id;
}

function goToStep(step: WizardStep) {
    if (step > currentStep.value && !canContinueFromStep.value) {
        if (currentStep.value === 1) {
            form.setError('name', 'Pane peenrale nimi, siis liigume edasi.');
        }
        if (currentStep.value === 2) {
            form.setError('cells', 'Peenras peab olema vähemalt üks ruut.');
        }
        return;
    }
    if (step !== 2) {
        form.clearErrors('cells');
    }
    currentStep.value = step;
}

function nextStep() {
    if (currentStep.value === 3) return;
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
    form.cell_bricks = cells.value.map((cell) => ({
        x: cell.x - b.minX,
        y: cell.y - b.minY,
        w: cell.w,
        h: cell.h,
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
            return { ...base, garden_plan_id: props.gardenPlanId };
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
    if (newBedImagePreview.value) {
        URL.revokeObjectURL(newBedImagePreview.value);
    }
    if (highlightTimeout) clearTimeout(highlightTimeout);
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
                <ol class="grid grid-cols-3 gap-2">
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
                                Ülevalt lohista uut plokki, all on sinu peenar.
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
                                        @dragstart="onPaletteDragStart"
                                        @dragend="onPaletteDragEnd"
                                    >
                                        <span
                                            class="material-symbols-outlined bed-cell-slot-icon"
                                            >add</span
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
                                                step="5"
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
                                v-if="cells.length === 0"
                                class="bed-grid-frame flex min-h-40 items-center justify-center p-4 transition"
                                :class="
                                    draggingCell || draggingFromPalette
                                        ? 'bg-primary/5'
                                        : ''
                                "
                                @dragover.prevent="onEmptyCanvasDragOver"
                                @drop.prevent="onEmptyCanvasDrop"
                            >
                                <div
                                    class="flex min-h-32 w-full max-w-sm items-center justify-center rounded-xl border-2 border-dashed border-emerald-900/25 bg-background/50 px-4 py-6"
                                >
                                    <p
                                        class="pointer-events-none text-center text-sm font-medium text-emerald-950/60 select-none"
                                    >
                                        Lohista pruun ruut siia
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else
                                ref="gridScroller"
                                class="bed-grid-frame max-h-[min(42vh,17rem)] overflow-auto overscroll-contain p-3"
                            >
                                <div
                                    class="mx-auto inline-grid w-max min-w-0 gap-2.5"
                                    :style="gridFrameStyle"
                                >
                                    <div
                                        v-for="cell in cells"
                                        :key="cell.id"
                                        class="relative"
                                        :style="
                                            gridCellPlacement(
                                                cell.x,
                                                cell.y,
                                                cell.w,
                                                cell.h,
                                            )
                                        "
                                        :ref="
                                            (el) =>
                                                setSelectedCellRef(el, cell.id)
                                        "
                                    >
                                        <button
                                            type="button"
                                            draggable="true"
                                            class="size-full cursor-grab active:cursor-grabbing"
                                            :aria-label="`${cellKindLabel(cell)}: veerg ${displayColumnNumber(cell.x)}, rida ${displayRowNumber(cell.y)}`"
                                            :class="
                                                addBedEditorCellClasses(
                                                    cell.x,
                                                    cell.y,
                                                )
                                            "
                                            @dragstart="
                                                onCellDragStart($event, cell)
                                            "
                                            @dragend="onCellDragEnd"
                                            @click.stop="
                                                onPlacedCellClick(
                                                    cell.x,
                                                    cell.y,
                                                )
                                            "
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
                                            </div>
                                        </button>
                                    </div>
                                    <div
                                        v-for="slot in dragDropSlots"
                                        :key="`drop-${slot.x}-${slot.y}`"
                                        class="relative size-[3.35rem]"
                                        :style="
                                            gridCellPlacement(
                                                slot.x,
                                                slot.y,
                                                1,
                                                1,
                                            )
                                        "
                                        @dragover="
                                            onCellDragOver(
                                                $event,
                                                slot.x,
                                                slot.y,
                                            )
                                        "
                                        @dragleave="onCellDragLeave"
                                        @drop="
                                            onCellDrop($event, slot.x, slot.y)
                                        "
                                    >
                                        <div
                                            class="size-full rounded-2xl transition"
                                            :class="
                                                isDragPreview(slot.x, slot.y) &&
                                                dragPreviewValid
                                                    ? 'border-2 border-primary bg-primary/15 shadow-md'
                                                    : 'border-0 bg-transparent'
                                            "
                                        />
                                    </div>
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
                v-show="currentStep === 3"
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
                                    step="10"
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
                        v-if="currentStep < 3"
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
