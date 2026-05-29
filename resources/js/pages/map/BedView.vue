<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import draggable from 'vuedraggable';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import { DEFAULT_BED_CELL_SIZE_CM } from '@/pages/map/constants';
import type { AppPageProps } from '@/types';

type PlantInBed = {
    id: number;
    name: string;
    image_url: string | null;
    position_in_bed: string | null;
    quantity: number;
    seed_id?: number | null;
};
type Bed = {
    id: number;
    garden_plan_id: number;
    name: string;
    location: string | null;
    image_url?: string | null;
    is_favorite?: boolean;
    rows: number;
    columns: number;
    cell_size_cm?: number;
    layout?: number[][] | null;
    cell_bricks?: Array<{
        x: number;
        y: number;
        w: number;
        h: number;
        kind: 'plantable' | 'walkway' | 'empty';
    }> | null;
    plants: PlantInBed[];
};
type PlantWithoutBed = {
    id: number;
    name: string;
    image_url: string | null;
    quantity: number;
    seed_id?: number | null;
    category?: { name: string; slug: string } | null;
};
type BedNote = {
    id: number;
    note_date: string | null;
    title: string | null;
    body: string | null;
    type: string | null;
    done: boolean | null;
};

const props = defineProps<{
    bed: Bed;
    plantsWithoutBed: PlantWithoutBed[];
    bedNotes?: BedNote[];
}>();

type BedPageProps = AppPageProps<{
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}>;

const page = usePage<BedPageProps>();
const mapHref = `/map/${props.bed.garden_plan_id}`;

const breadcrumbs = [
    { title: 'Aiaplaan', href: mapHref },
    { title: props.bed.name, href: `/beds/${props.bed.id}` },
];

const cellModal = ref<{ row: number; col: number } | null>(null);
const selectedPlantQuantity = ref(1);
const assigningPlantId = ref<number | null>(null);
const editingCellSize = ref(false);
const cellSizeInput = ref<number>(
    props.bed.cell_size_cm ?? DEFAULT_BED_CELL_SIZE_CM,
);
const savingCellSize = ref(false);
const editingLayout = ref(false);
const coverTick = ref(0);
const inlineFeedback = ref<{
    tone: 'success' | 'error';
    message: string;
} | null>(null);
let coverTimer: ReturnType<typeof setInterval> | null = null;
let inlineFeedbackTimer: ReturnType<typeof setTimeout> | null = null;

type CellBrick = {
    x: number;
    y: number;
    w: number;
    h: number;
    kind: 'plantable' | 'walkway' | 'empty';
};

type GridCell = {
    key: string;
    row: number;
    col: number;
    w: number;
    h: number;
    plant: PlantInBed | null;
    active: boolean;
    walkway: boolean;
};

const localCells = ref<GridCell[]>([]);

function getBedBricks(): CellBrick[] {
    const bricks = props.bed.cell_bricks;
    if (Array.isArray(bricks) && bricks.length > 0) {
        return bricks.map((brick) => ({
            x: brick.x,
            y: brick.y,
            w: Math.max(1, brick.w ?? 1),
            h: Math.max(1, brick.h ?? 1),
            kind: brick.kind ?? 'plantable',
        }));
    }

    const layout = getBedLayout();
    const derived: CellBrick[] = [];
    layout.forEach((row, y) => {
        row.forEach((value, x) => {
            if (value === 1) {
                derived.push({
                    x,
                    y,
                    w: 1,
                    h: 1,
                    kind: 'plantable',
                });
            } else if (value === -1) {
                derived.push({
                    x,
                    y,
                    w: 1,
                    h: 1,
                    kind: 'walkway',
                });
            }
        });
    });

    return derived;
}

function brickAnchorAt(row: number, col: number): CellBrick | null {
    return (
        getBedBricks().find(
            (brick) =>
                col >= brick.x &&
                col < brick.x + brick.w &&
                row >= brick.y &&
                row < brick.y + brick.h,
        ) ?? null
    );
}

function bedCellSpanStyle(cell: GridCell): Record<string, string> {
    const base: Record<string, string> = {
        gridColumnStart: String(cell.col + 1),
        gridRowStart: String(cell.row + 1),
    };
    if (cell.w > 1) {
        base.gridColumn = `span ${cell.w}`;
    }
    if (cell.h > 1) {
        base.gridRow = `span ${cell.h}`;
    }
    if (cell.w === 1 && cell.h === 1) {
        base.width = `${bedCellSize.value}px`;
        base.height = `${bedCellSize.value}px`;
    }

    return base;
}

function buildLocalCells() {
    const layout = getBedLayout();
    const items: GridCell[] = [];

    layout.forEach((rowArr, row) => {
        rowArr.forEach((cellValue, col) => {
            const brick = brickAnchorAt(row, col);
            if (brick && (brick.x !== col || brick.y !== row)) {
                return;
            }

            if (!brick && cellValue === 0) {
                return;
            }

            const w = brick?.w ?? 1;
            const h = brick?.h ?? 1;

            items.push({
                key: `${row},${col}`,
                row,
                col,
                w,
                h,
                plant: plantAt(row, col) ?? null,
                active: cellValue === 1,
                walkway: cellValue === -1,
            });
        });
    });

    items.sort((a, b) => a.row - b.row || a.col - b.col);
    localCells.value = items;
}

function buildLayoutPayloadFromLocalCells(): {
    layout: number[][];
    cell_bricks: CellBrick[];
} {
    const rows = getBedLayout().length;
    const cols = getBedColumns();
    const layout: number[][] = Array.from({ length: rows }, () =>
        Array.from({ length: cols }, () => 0),
    );
    const cell_bricks: CellBrick[] = [];

    localCells.value.forEach((cell) => {
        if (!cell.active && !cell.walkway) {
            return;
        }

        const kind: CellBrick['kind'] = cell.walkway
            ? 'walkway'
            : cell.active
              ? 'plantable'
              : 'empty';
        const value = cell.walkway ? -1 : cell.active ? 1 : 0;

        cell_bricks.push({
            x: cell.col,
            y: cell.row,
            w: cell.w,
            h: cell.h,
            kind,
        });

        for (let dy = 0; dy < cell.h; dy += 1) {
            for (let dx = 0; dx < cell.w; dx += 1) {
                const row = cell.row + dy;
                const col = cell.col + dx;
                if (layout[row]?.[col] !== undefined) {
                    layout[row][col] = value;
                }
            }
        }
    });

    return { layout, cell_bricks };
}

type DragMoveEvent = {
    draggedContext: { element: GridCell };
    relatedContext: { element: GridCell };
};

function checkMove(evt: DragMoveEvent): boolean {
    const from = evt.draggedContext.element;
    const to = evt.relatedContext?.element;
    if (!to) {
        return false;
    }

    if (editingLayout.value) {
        return !from.plant && from.active && !to.plant;
    }

    return !!from.plant && to.active && !to.plant;
}

function onDragEnd() {
    const cols = getBedColumns();

    if (editingLayout.value) {
        const { layout: newLayout, cell_bricks: newBricks } =
            buildLayoutPayloadFromLocalCells();
        router.put(
            `/beds/${props.bed.id}`,
            { layout: newLayout, cell_bricks: newBricks },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    buildLocalCells();
                    setInlineFeedback('success', 'Peenra kuju salvestatud.');
                },
                onError: () => {
                    buildLocalCells();
                    setInlineFeedback(
                        'error',
                        'Kuju salvestamine ebaõnnestus.',
                    );
                },
            },
        );
        return;
    }

    let moved = false;

    localCells.value.forEach((cell, index) => {
        const newRow = Math.floor(index / cols);
        const newCol = index % cols;
        const newKey = `${newRow},${newCol}`;
        if (cell.plant && cell.key !== newKey) {
            moved = true;
            router.put(
                `/plants/${cell.plant.id}`,
                { bed_id: props.bed.id, position_in_bed: newKey },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        buildLocalCells();
                        setInlineFeedback('success', 'Taim viidi teise ruutu.');
                    },
                    onError: () => {
                        buildLocalCells();
                        setInlineFeedback(
                            'error',
                            'Taime liigutamine ei õnnestunud. Proovi uuesti.',
                        );
                    },
                },
            );
        }
    });

    if (!moved) {
        buildLocalCells();
    }
}

watch(
    () => props.bed,
    () => {
        buildLocalCells();
    },
    { deep: true },
);

onMounted(() => {
    buildLocalCells();

    coverTimer = setInterval(() => {
        coverTick.value += 1;
    }, 3500);

    if (typeof ResizeObserver !== 'undefined') {
        bedGridResizeObserver = new ResizeObserver(() => {
            if (bedGridPinchState.value) {
                return;
            }
            fitBedGridZoom();
        });
    }

    requestAnimationFrame(() => {
        fitBedGridZoom();
        if (bedGridViewportRef.value && bedGridResizeObserver) {
            bedGridResizeObserver.observe(bedGridViewportRef.value);
        }
    });
});

onBeforeUnmount(() => {
    if (coverTimer) clearInterval(coverTimer);
    if (inlineFeedbackTimer) clearTimeout(inlineFeedbackTimer);
    bedGridResizeObserver?.disconnect();
    bedGridResizeObserver = null;
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

watch(cellModal, (value) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = value ? 'hidden' : '';
    }
    if (!value) {
        return;
    }
    const existing = plantAt(value.row, value.col);
    selectedPlantQuantity.value = existing ? existing.quantity : 1;
});

function bedCoverImage(): string | null {
    if (props.bed.image_url) return props.bed.image_url;
    const images = props.bed.plants
        .map((p) => p.image_url)
        .filter((x): x is string => Boolean(x));
    if (!images.length) return null;
    return images[coverTick.value % images.length];
}

function getBedLayout(): number[][] {
    const L = props.bed.layout;
    if (
        L &&
        Array.isArray(L) &&
        L.length > 0 &&
        L.some((row) => Array.isArray(row) && row.length > 0)
    ) {
        return L as number[][];
    }
    return Array.from({ length: props.bed.rows || 1 }, () =>
        Array.from({ length: props.bed.columns || 1 }, () => 1),
    );
}

function getBedColumns(): number {
    const layout = getBedLayout();
    if (layout.length === 0) return 1;
    return Math.max(...layout.map((r) => r.length), 1);
}

const bedPhysicalSize = computed(() => {
    const cm = props.bed.cell_size_cm ?? DEFAULT_BED_CELL_SIZE_CM;
    const cols = getBedColumns();
    const rows = getBedLayout().length || 1;
    const wCm = cols * cm;
    const hCm = rows * cm;
    const fmt = (v: number) =>
        v >= 100 ? `${(v / 100).toFixed(1)} m` : `${v} cm`;
    return `${fmt(wCm)} × ${fmt(hCm)}`;
});

const bedCellSize = computed(() => {
    const cols = getBedColumns();
    if (cols >= 10) return 42;
    if (cols >= 8) return 48;
    if (cols >= 6) return 56;
    return 64;
});

const BED_GRID_MIN_ZOOM = 0.4;
const BED_GRID_MAX_ZOOM = 2.75;
const BED_GRID_ZOOM_STEP = 0.12;
const BED_GRID_GAP_PX = 8;
const BED_GRID_FRAME_PADDING_PX = 24;

const bedGridViewportRef = ref<HTMLElement | null>(null);
const bedGridZoom = ref(1);
const bedGridPinchState = ref<{
    startDistance: number;
    startZoom: number;
} | null>(null);
const bedGridPointerMap = new Map<number, { x: number; y: number }>();
let bedGridResizeObserver: ResizeObserver | null = null;

const bedGridNaturalSize = computed(() => {
    const cols = getBedColumns();
    const rows = getBedLayout().length || 1;
    const cell = bedCellSize.value;
    const gap = BED_GRID_GAP_PX;
    const pad = BED_GRID_FRAME_PADDING_PX;

    return {
        width: cols * cell + Math.max(0, cols - 1) * gap + pad,
        height: rows * cell + Math.max(0, rows - 1) * gap + pad,
    };
});

const bedGridZoomPercent = computed(() => Math.round(bedGridZoom.value * 100));

function clampBedGridZoom(value: number): number {
    return Math.min(
        BED_GRID_MAX_ZOOM,
        Math.max(BED_GRID_MIN_ZOOM, Number(value.toFixed(3))),
    );
}

function fitBedGridZoom() {
    const viewport = bedGridViewportRef.value;
    if (!viewport) {
        return;
    }

    const { width: naturalW, height: naturalH } = bedGridNaturalSize.value;
    if (!naturalW || !naturalH) {
        return;
    }

    const availableW = viewport.clientWidth - 8;
    const availableH = Math.max(viewport.clientHeight, 220) - 8;
    const fit = Math.min(availableW / naturalW, availableH / naturalH);

    bedGridZoom.value = clampBedGridZoom(fit * 0.96);
}

function changeBedGridZoom(delta: number) {
    bedGridZoom.value = clampBedGridZoom(bedGridZoom.value + delta);
}

function onBedGridWheel(event: WheelEvent) {
    event.preventDefault();
    const delta = event.deltaY > 0 ? -BED_GRID_ZOOM_STEP : BED_GRID_ZOOM_STEP;
    changeBedGridZoom(delta);
}

function applyBedGridPinchZoom() {
    const state = bedGridPinchState.value;
    if (!state || bedGridPointerMap.size < 2) {
        return;
    }

    const points = [...bedGridPointerMap.values()];
    const distance = Math.hypot(
        points[1].x - points[0].x,
        points[1].y - points[0].y,
    );
    if (distance < 8) {
        return;
    }

    const ratio = distance / state.startDistance;
    bedGridZoom.value = clampBedGridZoom(state.startZoom * ratio);
}

function onBedGridPointerDown(event: PointerEvent) {
    const target = event.target as HTMLElement | null;
    if (
        target?.closest('button, [role="button"], a, input, select, textarea')
    ) {
        return;
    }

    bedGridPointerMap.set(event.pointerId, {
        x: event.clientX,
        y: event.clientY,
    });

    if (bedGridPointerMap.size === 2) {
        const points = [...bedGridPointerMap.values()];
        bedGridPinchState.value = {
            startDistance: Math.hypot(
                points[1].x - points[0].x,
                points[1].y - points[0].y,
            ),
            startZoom: bedGridZoom.value,
        };

        const viewport = bedGridViewportRef.value;
        if (viewport) {
            try {
                viewport.setPointerCapture(event.pointerId);
            } catch {
                /* noop */
            }
        }
    }
}

function onBedGridPointerMove(event: PointerEvent) {
    if (!bedGridPointerMap.has(event.pointerId)) {
        return;
    }

    bedGridPointerMap.set(event.pointerId, {
        x: event.clientX,
        y: event.clientY,
    });

    if (bedGridPinchState.value) {
        applyBedGridPinchZoom();
    }
}

function onBedGridPointerUp(event: PointerEvent) {
    bedGridPointerMap.delete(event.pointerId);

    if (bedGridPointerMap.size < 2) {
        bedGridPinchState.value = null;
    }

    const viewport = bedGridViewportRef.value;
    if (viewport?.hasPointerCapture(event.pointerId)) {
        try {
            viewport.releasePointerCapture(event.pointerId);
        } catch {
            /* noop */
        }
    }
}

function plantAt(row: number, col: number): PlantInBed | undefined {
    const key = `${row},${col}`;
    return props.bed.plants.find((p) => p.position_in_bed === key);
}

function plantsWithoutCell(): PlantInBed[] {
    return props.bed.plants.filter(
        (p) => !p.position_in_bed || !/^\d+,\d+$/.test(p.position_in_bed),
    );
}

function removePlantFromBed(plant: PlantInBed) {
    router.put(
        `/plants/${plant.id}`,
        { bed_id: null, position_in_bed: null },
        {
            preserveScroll: true,
            onSuccess: () => {
                setInlineFeedback('success', 'Taim eemaldati peenralt.');
            },
            onError: () => {
                setInlineFeedback(
                    'error',
                    'Taime eemaldamine ei õnnestunud. Proovi uuesti.',
                );
            },
        },
    );
}

function isUnknownQuantityPlant(
    plant: Pick<PlantWithoutBed, 'quantity'> | Pick<PlantInBed, 'quantity'>,
): boolean {
    return plant.quantity === 0;
}

function assignPlantToCell(plantId: number, row: number, col: number) {
    const plant = props.plantsWithoutBed.find((p) => p.id === plantId);
    const key = `${row},${col}`;
    assigningPlantId.value = plantId;
    const payload: Record<string, unknown> = {
        bed_id: props.bed.id,
        position_in_bed: key,
    };
    if (!plant || !isUnknownQuantityPlant(plant)) {
        payload.quantity = Math.max(
            1,
            Math.round(selectedPlantQuantity.value || 1),
        );
    }
    router.put(`/plants/${plantId}`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            cellModal.value = null;
            selectedPlantQuantity.value = 1;
            setInlineFeedback('success', 'Taim lisati valitud ruutu.');
        },
        onError: () => {
            setInlineFeedback(
                'error',
                'Taime lisamine ei õnnestunud. Proovi uuesti.',
            );
        },
        onFinish: () => {
            assigningPlantId.value = null;
        },
    });
}

function updatePlantQuantityInCell(plantId: number, row: number, col: number) {
    const key = `${row},${col}`;
    router.put(
        `/plants/${plantId}`,
        {
            bed_id: props.bed.id,
            position_in_bed: key,
            quantity: Math.max(1, Math.round(selectedPlantQuantity.value || 1)),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                cellModal.value = null;
                selectedPlantQuantity.value = 1;
                setInlineFeedback('success', 'Taime kogus uuendatud.');
            },
            onError: () => {
                setInlineFeedback(
                    'error',
                    'Taime kogust ei õnnestunud uuendada. Proovi uuesti.',
                );
            },
        },
    );
}

const plantsWithoutBedByCategory = computed(() => {
    const map = new Map<string, PlantWithoutBed[]>();
    for (const p of props.plantsWithoutBed) {
        const key = p.category?.name ?? 'Kategooriata';
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(p);
    }
    return Array.from(map.entries()).sort((a, b) =>
        a[0].localeCompare(b[0], 'et'),
    );
});
const availablePlantsCount = computed(() => props.plantsWithoutBed.length);
const assignShowsQuantityPicker = computed(() =>
    props.plantsWithoutBed.some((p) => !isUnknownQuantityPlant(p)),
);
const cellModalLabel = computed(() => {
    if (!cellModal.value) return '';
    return `Rida ${cellModal.value.row + 1}, veerg ${cellModal.value.col + 1}`;
});
const modalPlant = computed(() => {
    if (!cellModal.value) return null;
    return plantAt(cellModal.value.row, cellModal.value.col) ?? null;
});
const totalBedCells = computed(() =>
    getBedLayout().reduce(
        (sum, row) => sum + row.filter((cell) => cell === 1).length,
        0,
    ),
);
const plantedCellCount = computed(() => {
    const positions = new Set(
        props.bed.plants
            .map((plant) => plant.position_in_bed)
            .filter((position): position is string => Boolean(position)),
    );

    return positions.size;
});
const emptyCellCount = computed(() =>
    Math.max(0, totalBedCells.value - plantedCellCount.value),
);
const bedStatus = computed(() => {
    if (!hasPlantsInBed.value) {
        return {
            title: 'Peenar on veel tühi',
            description:
                'Lisa esimene taim sobivasse ruutu, et peenar hakkaks päriselt kuju võtma.',
            actionLabel: availablePlantsCount.value
                ? 'Lisa taim peenrasse'
                : 'Loo uus taim',
            actionType: availablePlantsCount.value
                ? 'add-first-plant'
                : 'create-plant',
        };
    }

    if (emptyCellCount.value > 0) {
        return {
            title: 'Peenras on veel vaba ruumi',
            description: `Vabu ruute on ${emptyCellCount.value}. Saad jätkata istutamist või jätta ruumi järgmise külvi jaoks.`,
            actionLabel: availablePlantsCount.value
                ? 'Täida järgmine ruut'
                : 'Loo uus taim',
            actionType: availablePlantsCount.value
                ? 'fill-next-cell'
                : 'create-plant',
        };
    }

    return {
        title: 'Peenar on praegu täidetud',
        description:
            'Järgmine mõistlik samm on lisada märge, kontrollida taimede seisu või avada kalender järgmiste tööde jaoks.',
        actionLabel: 'Lisa märge',
        actionType: 'create-note',
    };
});
const latestBedNote = computed(() => props.bedNotes?.[0] ?? null);
const fillPercent = computed(() => {
    if (!totalBedCells.value) return 0;
    return Math.min(
        100,
        Math.round((plantedCellCount.value / totalBedCells.value) * 100),
    );
});
const fillTone = computed(() => {
    if (fillPercent.value >= 90) return 'amber';
    if (fillPercent.value >= 60) return 'lime';
    return 'emerald';
});
const bedStatusBadge = computed(() => {
    if (fillPercent.value >= 100) {
        return {
            label: 'Täis',
            className:
                'border-amber-200/70 bg-amber-100/85 text-amber-900 shadow-[0_0_24px_rgba(245,158,11,0.35)]',
        };
    }

    if (plantedCellCount.value > 0) {
        return {
            label: 'Kasvab',
            className:
                'border-emerald-200/70 bg-emerald-100/85 text-emerald-900 shadow-[0_0_24px_rgba(16,185,129,0.35)]',
        };
    }

    return {
        label: 'Tühi',
        className:
            'border-stone-200/80 bg-stone-100/85 text-stone-800 shadow-[0_0_20px_rgba(120,113,108,0.22)]',
    };
});
const hasRecentNote = computed(() => {
    const note = latestBedNote.value;
    if (!note?.note_date) return false;
    const created = new Date(note.note_date);
    if (Number.isNaN(created.getTime())) return false;
    return (Date.now() - created.getTime()) / 86_400_000 <= 21;
});
const hasRecentWatering = computed(() =>
    Boolean(
        props.bedNotes?.some((note) => {
            const haystack =
                `${note.type ?? ''} ${note.title ?? ''} ${note.body ?? ''}`.toLowerCase();
            return (
                haystack.includes('kast') ||
                haystack.includes('vesi') ||
                haystack.includes('water')
            );
        }),
    ),
);
const healthScore = computed(() =>
    Math.min(
        100,
        Math.round(fillPercent.value * 0.62) +
            (hasRecentNote.value ? 20 : 0) +
            (hasRecentWatering.value ? 18 : 0),
    ),
);
const healthRingStyle = computed(() => {
    const circumference = 2 * Math.PI * 26;
    return {
        strokeDasharray: `${circumference}`,
        strokeDashoffset: `${circumference - (healthScore.value / 100) * circumference}`,
    };
});
const gridFrameStyle = computed<Record<string, string>>(() => ({
    '--bed-fill': `${fillPercent.value}%`,
    '--bed-fill-color':
        fillTone.value === 'amber'
            ? 'rgb(245 158 11)'
            : fillTone.value === 'lime'
              ? 'rgb(132 204 22)'
              : 'rgb(16 185 129)',
}));

function formatNoteDate(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('et-EE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const gridLayout = computed(() => getBedLayout());
const hasPlantsInBed = computed(() => props.bed.plants.length > 0);

watch(
    () => [
        props.bed.layout,
        props.bed.rows,
        props.bed.columns,
        bedCellSize.value,
    ],
    () => {
        requestAnimationFrame(() => fitBedGridZoom());
    },
);
const pageFeedback = computed(() => {
    if (inlineFeedback.value) return inlineFeedback.value;
    if (page.props.flash?.error) {
        return { tone: 'error' as const, message: page.props.flash.error };
    }
    if (page.props.flash?.success) {
        return { tone: 'success' as const, message: page.props.flash.success };
    }
    return null;
});

function setInlineFeedback(tone: 'success' | 'error', message: string) {
    inlineFeedback.value = { tone, message };
    if (inlineFeedbackTimer) clearTimeout(inlineFeedbackTimer);
    inlineFeedbackTimer = setTimeout(() => {
        inlineFeedback.value = null;
    }, 2600);
}

function beginEditCellSize() {
    editingCellSize.value = true;
    cellSizeInput.value = props.bed.cell_size_cm ?? DEFAULT_BED_CELL_SIZE_CM;
}

function saveCellSize() {
    const value = Math.min(
        200,
        Math.max(10, Math.round(cellSizeInput.value || 30)),
    );
    savingCellSize.value = true;
    router.put(
        `/beds/${props.bed.id}`,
        { cell_size_cm: value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                editingCellSize.value = false;
                setInlineFeedback(
                    'success',
                    `Ruudu mõõt uuendatud: ${value} cm.`,
                );
            },
            onError: () => {
                setInlineFeedback('error', 'Mõõdu salvestamine ei õnnestunud.');
            },
            onFinish: () => {
                savingCellSize.value = false;
            },
        },
    );
}

function openFirstAvailableCell() {
    const layout = getBedLayout();

    for (let row = 0; row < layout.length; row += 1) {
        for (let col = 0; col < (layout[row]?.length ?? 0); col += 1) {
            if ((layout[row]?.[col] ?? 0) === 1 && !plantAt(row, col)) {
                cellModal.value = { row, col };
                return;
            }
        }
    }
}

function openCellModal(row: number, col: number) {
    cellModal.value = { row, col };
}

function plantedCellClass(row: number, col: number): string {
    const plant = plantAt(row, col);
    if (!plant) return '';

    return plant.image_url
        ? 'bed-cell bed-cell--planted bed-cell--photo'
        : 'bed-cell bed-cell--planted';
}

function emptyCellClass(row: number, col: number): string {
    const checker = (row + col) % 2 === 0;
    return checker
        ? 'bed-cell bed-cell--empty bed-cell--warm'
        : 'bed-cell bed-cell--empty';
}

function changeSelectedPlantQuantity(delta: number) {
    selectedPlantQuantity.value = Math.max(
        1,
        Math.min(99, Math.round(selectedPlantQuantity.value + delta)),
    );
}

function deleteCurrentBed() {
    const message = `Eemaldada peenar "${props.bed.name}"? Taimed jäävad peenrata.`;
    if (!window.confirm(message)) {
        return;
    }

    // Backend suunab kustutamise järel aiaplaani kaardile.
    router.delete(`/beds/${props.bed.id}`);
}

function handleBedStatusAction() {
    if (bedStatus.value.actionType === 'add-first-plant') {
        openFirstAvailableCell();
        return;
    }

    if (bedStatus.value.actionType === 'fill-next-cell') {
        openFirstAvailableCell();
        return;
    }

    if (bedStatus.value.actionType === 'create-note') {
        router.get(
            `/calendar/note-form?bed_id=${props.bed.id}&return_to=${encodeURIComponent(`/beds/${props.bed.id}`)}`,
        );
        return;
    }

    router.get('/plants/create');
}
</script>

<template>
    <Head :title="bed.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title=""
                        header-class="pt-6"
                        top-row-class="mb-2"
                        bottom-row-class="mb-4"
                    >
                        <template #leading>
                            <BackIconButton
                                :href="mapHref"
                                aria-label="Tagasi peenarde loendisse"
                            />
                        </template>
                        <template #actions>
                            <div class="flex max-w-full items-center gap-2">
                                <Link
                                    :href="`/beds/${bed.id}/edit`"
                                    class="inline-flex min-h-11 max-w-full items-center justify-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 text-sm font-semibold text-primary transition hover:bg-primary/15 sm:h-10 sm:min-h-0 sm:gap-2 sm:px-4"
                                    aria-label="Muuda peenart"
                                >
                                    <span
                                        class="material-symbols-outlined shrink-0 text-xl leading-none"
                                        >edit</span
                                    >
                                    <span class="truncate sm:inline"
                                        >Muuda peenart</span
                                    >
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-full border border-border/70 bg-card px-3 text-sm font-semibold text-foreground shadow-sm ring-1 ring-border/70 transition hover:bg-muted sm:h-10 sm:min-h-0 sm:px-4"
                                    aria-label="Kustuta peenar"
                                    @click="deleteCurrentBed"
                                >
                                    <span
                                        class="material-symbols-outlined shrink-0 text-xl leading-none"
                                        >delete</span
                                    >
                                    <span class="hidden truncate sm:inline"
                                        >Kustuta</span
                                    >
                                </button>
                            </div>
                        </template>
                    </DiaryHeader>

                    <main class="flex-1 space-y-4 px-4 py-3 sm:px-6 md:px-8">
                        <div
                            v-if="pageFeedback"
                            class="rounded-[1.5rem] border px-4 py-3 shadow-sm"
                            :class="
                                pageFeedback.tone === 'error'
                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            "
                        >
                            <p class="text-sm font-medium">
                                {{ pageFeedback.message }}
                            </p>
                        </div>

                        <section
                            class="bed-hero relative overflow-hidden rounded-[1.75rem] border border-emerald-900/10 bg-card shadow-[0_18px_55px_rgba(49,79,55,0.18)]"
                        >
                            <div
                                v-if="bed.image_url"
                                class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-[0.18]"
                                :style="{
                                    backgroundImage: `url('${bed.image_url}')`,
                                }"
                                aria-hidden="true"
                            />
                            <div
                                v-if="bed.image_url"
                                class="pointer-events-none absolute inset-0 bg-background/62"
                                aria-hidden="true"
                            />
                            <div
                                class="relative z-10 h-48 overflow-hidden bg-cover bg-center ring-1 ring-black/10 sm:h-56"
                                :style="
                                    bedCoverImage()
                                        ? {
                                              backgroundImage: `url('${bedCoverImage()}')`,
                                          }
                                        : {}
                                "
                            >
                                <div
                                    v-if="!bedCoverImage()"
                                    class="absolute inset-0 flex items-center justify-center bg-linear-to-br from-primary/20 via-muted/30 to-primary/10"
                                >
                                    <span
                                        class="material-symbols-outlined text-5xl text-primary"
                                        >grass</span
                                    >
                                </div>
                                <div
                                    class="pointer-events-none absolute inset-0 bg-linear-to-b from-black/18 via-transparent to-black/35"
                                />
                                <div
                                    class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full border border-white/30 bg-black/25 px-2.5 py-1 text-[11px] font-semibold text-white/95 backdrop-blur-[2px]"
                                >
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        >yard</span
                                    >
                                    Peenar
                                </div>
                                <div
                                    class="absolute top-3 right-3 inline-flex animate-[bedPulse_2.4s_ease-in-out_infinite] items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold backdrop-blur-md"
                                    :class="bedStatusBadge.className"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-current"
                                    ></span>
                                    {{ bedStatusBadge.label }}
                                </div>
                                <div
                                    class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/88 via-black/52 to-transparent p-4 pb-12"
                                >
                                    <h2
                                        class="text-2xl font-semibold tracking-tight text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.45)]"
                                    >
                                        {{ bed.name }}
                                    </h2>
                                    <p
                                        v-if="bed.location"
                                        class="mt-1 inline-flex items-center gap-1 text-sm text-white/90"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[15px]"
                                            >location_on</span
                                        >
                                        {{ bed.location }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="relative z-20 -mt-9 grid grid-cols-[1fr_auto] gap-2 px-3 pb-3 sm:grid-cols-4 sm:px-4 sm:pb-4"
                            >
                                <div class="hero-stat-card">
                                    <span>Ruudud</span>
                                    <strong
                                        >{{ plantedCellCount }} /
                                        {{ totalBedCells }}</strong
                                    >
                                </div>
                                <div class="hero-stat-card">
                                    <span>Vaba</span>
                                    <strong>{{ emptyCellCount }}</strong>
                                </div>
                                <div class="hero-stat-card hidden sm:block">
                                    <span>Märkmed</span>
                                    <strong>{{
                                        props.bedNotes?.length ?? 0
                                    }}</strong>
                                </div>
                                <div
                                    class="hero-health-card row-span-2 flex items-center justify-center sm:row-span-1"
                                >
                                    <svg
                                        class="h-16 w-16 -rotate-90"
                                        viewBox="0 0 64 64"
                                        aria-hidden="true"
                                    >
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="26"
                                            fill="none"
                                            stroke="rgba(255,255,255,0.32)"
                                            stroke-width="7"
                                        />
                                        <circle
                                            cx="32"
                                            cy="32"
                                            r="26"
                                            fill="none"
                                            stroke="url(#healthGradient)"
                                            stroke-linecap="round"
                                            stroke-width="7"
                                            class="health-ring"
                                            :style="healthRingStyle"
                                        />
                                        <defs>
                                            <linearGradient
                                                id="healthGradient"
                                                x1="0"
                                                x2="1"
                                                y1="0"
                                                y2="1"
                                            >
                                                <stop
                                                    stop-color="#34d399"
                                                    offset="0%"
                                                />
                                                <stop
                                                    stop-color="#a3e635"
                                                    offset="100%"
                                                />
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                    <div class="absolute text-center">
                                        <strong
                                            class="block text-sm leading-none text-white"
                                            >{{ healthScore }}</strong
                                        >
                                        <span
                                            class="text-[9px] font-bold tracking-[0.14em] text-white/70 uppercase"
                                            >elu</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-2xl border border-border/70 bg-card/95 p-4 shadow-sm"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        Peenra kokkuvõte
                                    </p>
                                    <h3
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ bedStatus.title }}
                                    </h3>
                                    <p
                                        class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ bedStatus.description }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-primary bg-primary px-3.5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0 sm:w-auto"
                                    @click="handleBedStatusAction"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >arrow_forward</span
                                    >
                                    {{ bedStatus.actionLabel }}
                                </button>
                            </div>

                            <div
                                v-if="hasPlantsInBed"
                                class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Taimi peenras
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ bed.plants.length }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Täidetud ruudud
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ plantedCellCount }} /
                                        {{ totalBedCells }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Vabad ruudud
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ emptyCellCount }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Viimane märge
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            latestBedNote?.title ||
                                            'Märkmeid veel ei ole'
                                        }}
                                    </p>
                                    <p
                                        v-if="latestBedNote?.note_date"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatNoteDate(
                                                latestBedNote.note_date,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-[1.75rem] border border-emerald-900/10 bg-card p-3 shadow-[0_18px_45px_rgba(49,79,55,0.12)] sm:p-4"
                        >
                            <div
                                class="mb-3 flex flex-col gap-3 px-1 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Peenra ruudustik
                                    </h3>
                                    <p
                                        class="mt-0.5 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        Puuduta ruutu, et lisada, muuta või
                                        eemaldada taim.
                                    </p>
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1"
                                    >
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ gridLayout.length }} ×
                                            {{ getBedColumns() }} ruutu
                                        </span>
                                        <button
                                            v-if="!editingCellSize"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            title="Muuda ruudu mõõtu"
                                            @click="beginEditCellSize"
                                        >
                                            <span
                                                class="material-symbols-outlined text-[14px]"
                                                >straighten</span
                                            >
                                            {{
                                                bed.cell_size_cm ??
                                                DEFAULT_BED_CELL_SIZE_CM
                                            }}
                                            cm/ruut
                                        </button>
                                        <form
                                            v-else
                                            class="inline-flex items-center gap-1"
                                            @submit.prevent="saveCellSize"
                                        >
                                            <input
                                                v-model.number="cellSizeInput"
                                                type="number"
                                                min="10"
                                                max="200"
                                                step="5"
                                                class="h-7 w-16 rounded-lg border border-input bg-background px-2 text-xs text-foreground outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
                                                :disabled="savingCellSize"
                                                @keydown.escape="
                                                    editingCellSize = false
                                                "
                                            />
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >cm</span
                                            >
                                            <button
                                                type="submit"
                                                :disabled="savingCellSize"
                                                class="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                                            >
                                                {{
                                                    savingCellSize
                                                        ? '...'
                                                        : 'Salvesta'
                                                }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full px-2 py-0.5 text-xs text-muted-foreground transition hover:text-foreground"
                                                @click="editingCellSize = false"
                                            >
                                                Tühista
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-wrap items-center gap-2"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                        :class="
                                            editingLayout
                                                ? 'border-primary/40 bg-primary/10 text-primary'
                                                : 'border-border bg-background text-muted-foreground hover:text-foreground'
                                        "
                                        @click="editingLayout = !editingLayout"
                                    >
                                        <span
                                            class="material-symbols-outlined text-sm"
                                            >{{
                                                editingLayout
                                                    ? 'check'
                                                    : 'edit_square'
                                            }}</span
                                        >
                                        {{
                                            editingLayout
                                                ? 'Valmis'
                                                : 'Muuda kuju'
                                        }}
                                    </button>
                                    <p
                                        v-if="editingLayout"
                                        class="max-w-md text-xs leading-relaxed text-muted-foreground"
                                    >
                                        Lohista ruute ümber. Uue ploki
                                        lisamiseks ava
                                        <Link
                                            :href="`/beds/${bed.id}/edit?step=2`"
                                            class="font-semibold text-primary underline-offset-2 hover:underline"
                                            >peenra kuju muutmine</Link
                                        >.
                                    </p>
                                    <div
                                        class="rounded-xl border border-border/70 bg-background/70 px-3 py-2 text-center shadow-xs sm:min-w-[5.5rem] sm:text-right"
                                    >
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                        >
                                            Vabu taimi
                                        </p>
                                        <p
                                            class="mt-1 text-sm font-semibold text-foreground"
                                        >
                                            {{ availablePlantsCount }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="mb-4 rounded-2xl border border-emerald-900/10 bg-[linear-gradient(135deg,rgba(236,253,245,0.92),rgba(254,243,199,0.45))] p-3"
                            >
                                <div
                                    v-if="fillPercent === 100"
                                    class="confetti-burst"
                                    aria-hidden="true"
                                >
                                    <i></i><i></i><i></i><i></i><i></i>
                                </div>
                                <div
                                    class="mb-2 flex items-center justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-bold tracking-[0.16em] text-emerald-950/55 uppercase"
                                        >
                                            Peenar täitub
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm font-semibold text-foreground"
                                        >
                                            {{ fillPercent }}% istutatud
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full border border-white/70 bg-white/70 px-2.5 py-1 text-xs font-bold text-emerald-900 shadow-sm backdrop-blur"
                                    >
                                        {{ plantedCellCount }} /
                                        {{ totalBedCells }}
                                    </span>
                                </div>
                                <div
                                    class="bed-fullness-track"
                                    :style="gridFrameStyle"
                                >
                                    <span></span>
                                </div>
                            </div>
                            <div
                                ref="bedGridViewportRef"
                                class="bed-grid-viewport relative -mx-1 min-h-[min(52vw,22rem)] rounded-[1.35rem] border border-emerald-900/10 bg-[linear-gradient(180deg,rgba(236,253,245,0.35),rgba(255,251,235,0.2))] px-1 pb-1"
                                @wheel.prevent="onBedGridWheel"
                                @pointerdown="onBedGridPointerDown"
                                @pointermove="onBedGridPointerMove"
                                @pointerup="onBedGridPointerUp"
                                @pointercancel="onBedGridPointerUp"
                                @pointerleave="onBedGridPointerUp"
                            >
                                <div
                                    class="pointer-events-none absolute top-2 right-2 z-10 flex items-center gap-1 rounded-full border border-white/70 bg-white/90 p-0.5 shadow-sm backdrop-blur"
                                >
                                    <button
                                        type="button"
                                        class="pointer-events-auto flex h-8 w-8 items-center justify-center rounded-full text-foreground hover:bg-muted"
                                        aria-label="Vähenda suum"
                                        @click.stop="
                                            changeBedGridZoom(
                                                -BED_GRID_ZOOM_STEP,
                                            )
                                        "
                                    >
                                        <span
                                            class="material-symbols-outlined text-base"
                                            >remove</span
                                        >
                                    </button>
                                    <span
                                        class="min-w-[2.75rem] text-center text-[11px] font-semibold text-foreground/80 tabular-nums"
                                    >
                                        {{ bedGridZoomPercent }}%
                                    </span>
                                    <button
                                        type="button"
                                        class="pointer-events-auto flex h-8 w-8 items-center justify-center rounded-full text-foreground hover:bg-muted"
                                        aria-label="Suurenda suum"
                                        @click.stop="
                                            changeBedGridZoom(
                                                BED_GRID_ZOOM_STEP,
                                            )
                                        "
                                    >
                                        <span
                                            class="material-symbols-outlined text-base"
                                            >add</span
                                        >
                                    </button>
                                    <button
                                        type="button"
                                        class="pointer-events-auto flex h-8 w-8 items-center justify-center rounded-full text-foreground hover:bg-muted"
                                        aria-label="Mahuta vaatesse"
                                        @click.stop="fitBedGridZoom"
                                    >
                                        <span
                                            class="material-symbols-outlined text-base"
                                            >fit_screen</span
                                        >
                                    </button>
                                </div>
                                <div
                                    class="flex h-full min-h-[inherit] items-center justify-center py-3"
                                >
                                    <div
                                        class="bed-grid-zoom-stage"
                                        :style="{
                                            transform: `scale(${bedGridZoom})`,
                                        }"
                                    >
                                        <draggable
                                            v-model="localCells"
                                            item-key="key"
                                            class="bed-grid-frame inline-grid w-max min-w-0 gap-2 rounded-[1.35rem] border border-emerald-900/10 bg-card/40 p-3 ring-1 ring-white/70 sm:gap-2.5 sm:p-4"
                                            :style="{
                                                gridTemplateColumns: `repeat(${getBedColumns()}, ${bedCellSize}px)`,
                                                gridTemplateRows: `repeat(${gridLayout.length}, ${bedCellSize}px)`,
                                            }"
                                            :move="checkMove"
                                            ghost-class="opacity-40"
                                            drag-class="scale-105 shadow-xl"
                                            @end="onDragEnd"
                                        >
                                            <template #item="{ element: cell }">
                                                <div
                                                    v-if="
                                                        cell.plant &&
                                                        cell.active
                                                    "
                                                    role="button"
                                                    tabindex="0"
                                                    class="group relative cursor-pointer touch-manipulation overflow-hidden text-left outline-none focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:ring-offset-2"
                                                    :class="
                                                        plantedCellClass(
                                                            cell.row,
                                                            cell.col,
                                                        )
                                                    "
                                                    :style="
                                                        bedCellSpanStyle(cell)
                                                    "
                                                    :aria-label="`Ruut: ${cell.plant?.name ?? 'taim'}. Ava üksikasjad.`"
                                                    @click="
                                                        !editingLayout &&
                                                        openCellModal(
                                                            cell.row,
                                                            cell.col,
                                                        )
                                                    "
                                                    @keydown.enter.prevent="
                                                        !editingLayout &&
                                                        openCellModal(
                                                            cell.row,
                                                            cell.col,
                                                        )
                                                    "
                                                    @keydown.space.prevent="
                                                        !editingLayout &&
                                                        openCellModal(
                                                            cell.row,
                                                            cell.col,
                                                        )
                                                    "
                                                >
                                                    <div
                                                        class="plant-bloom absolute inset-0"
                                                    ></div>
                                                    <span class="plant-avatar">
                                                        <img
                                                            v-if="
                                                                cell.plant
                                                                    ?.image_url
                                                            "
                                                            :src="
                                                                cell.plant
                                                                    .image_url!
                                                            "
                                                            :alt="
                                                                cell.plant.name
                                                            "
                                                        />
                                                        <span
                                                            v-else
                                                            class="material-symbols-outlined"
                                                            >psychiatry</span
                                                        >
                                                    </span>
                                                    <span
                                                        class="absolute right-1.5 bottom-1.5 left-1.5 truncate rounded-full bg-emerald-950/45 px-1.5 py-0.5 text-center text-[10px] font-semibold text-white backdrop-blur-[2px]"
                                                        >{{
                                                            cell.plant.name
                                                        }}</span
                                                    >
                                                    <span
                                                        class="absolute top-1.5 left-1.5 rounded-full bg-white/92 px-1.5 py-0.5 text-[10px] font-semibold text-foreground shadow-sm dark:bg-card/92"
                                                    >
                                                        {{
                                                            cell.plant.quantity
                                                        }}
                                                        tk
                                                    </span>
                                                    <button
                                                        type="button"
                                                        class="absolute top-1 right-1 z-20 flex min-h-9 min-w-9 items-center justify-center rounded-full bg-black/60 p-1.5 text-white opacity-100 shadow-sm md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                                                        aria-label="Eemalda taim ruudust"
                                                        @click.stop="
                                                            removePlantFromBed(
                                                                cell.plant,
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            class="material-symbols-outlined text-base"
                                                            >close</span
                                                        >
                                                    </button>
                                                </div>
                                                <div
                                                    v-else-if="cell.walkway"
                                                    class="bed-cell bed-cell--walkway"
                                                    :style="
                                                        bedCellSpanStyle(cell)
                                                    "
                                                />
                                                <button
                                                    v-else-if="cell.active"
                                                    type="button"
                                                    class="group flex touch-manipulation items-center justify-center"
                                                    :class="
                                                        emptyCellClass(
                                                            cell.row,
                                                            cell.col,
                                                        )
                                                    "
                                                    :style="
                                                        bedCellSpanStyle(cell)
                                                    "
                                                    :title="`Lisa taim ruutu (rida ${cell.row + 1}, veerg ${cell.col + 1})`"
                                                    :aria-label="`Lisa taim ruutu, rida ${cell.row + 1}, veerg ${cell.col + 1}`"
                                                    @click="
                                                        !editingLayout &&
                                                        openCellModal(
                                                            cell.row,
                                                            cell.col,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined bed-cell-slot-icon"
                                                        >add</span
                                                    >
                                                </button>
                                            </template>
                                        </draggable>
                                    </div>
                                </div>
                            </div>
                            <p
                                class="mt-2 text-center text-xs text-muted-foreground"
                            >
                                {{ bedPhysicalSize }}
                            </p>
                        </section>

                        <section
                            v-if="!plantsWithoutBed.length"
                            class="rounded-[1.75rem] border border-border/70 bg-card/95 p-4 shadow-[0_8px_20px_rgba(16,24,40,0.05)]"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="max-w-2xl">
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Praegu pole ühtegi vaba taime.
                                    </h3>
                                    <p
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        Kui soovid sellesse peenrasse midagi
                                        lisada, loo esmalt uus taim või eemalda
                                        taim mõnest teisest peenrast.
                                    </p>
                                </div>
                                <Link
                                    href="/plants/create"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-primary bg-primary px-3.5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0"
                                >
                                    <span class="material-symbols-outlined"
                                        >add</span
                                    >
                                    Loo uus taim
                                </Link>
                            </div>
                        </section>

                        <section
                            v-if="plantsWithoutCell().length"
                            class="rounded-3xl border border-border/70 bg-card/95 p-4 shadow-[0_8px_20px_rgba(16,24,40,0.05)]"
                        >
                            <p
                                class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Muud taimed peenral
                            </p>
                            <ul class="flex flex-wrap gap-2">
                                <li
                                    v-for="p in plantsWithoutCell()"
                                    :key="p.id"
                                    class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-muted/35 py-1.5 pr-1.5 pl-3"
                                >
                                    <span
                                        class="text-sm font-medium text-foreground"
                                        >{{ p.name }}</span
                                    >
                                    <span
                                        class="rounded-full bg-background px-2 py-0.5 text-xs font-semibold text-muted-foreground"
                                    >
                                        {{ p.quantity }} tk
                                    </span>
                                    <button
                                        type="button"
                                        class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        aria-label="Eemalda taim peenralt"
                                        @click="removePlantFromBed(p)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-xl"
                                            >close</span
                                        >
                                    </button>
                                </li>
                            </ul>
                        </section>

                        <section
                            class="rounded-3xl border border-border/70 bg-card/95 p-4 shadow-[0_10px_24px_rgba(16,24,40,0.06)]"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Märkmed
                                </h3>
                                <Link
                                    :href="`/calendar/note-form?bed_id=${bed.id}&return_to=${encodeURIComponent(`/beds/${bed.id}`)}`"
                                    class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-primary bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >add</span
                                    >
                                    Lisa
                                </Link>
                            </div>

                            <div
                                v-if="props.bedNotes?.length"
                                class="space-y-2.5"
                            >
                                <Link
                                    v-for="note in props.bedNotes"
                                    :key="note.id"
                                    :href="`/calendar/notes/${note.id}?return_to=${encodeURIComponent(`/beds/${props.bed.id}`)}`"
                                    class="block rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3 shadow-[0_2px_10px_rgba(16,24,40,0.04)] transition hover:border-primary/35 hover:bg-muted/25"
                                >
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <p
                                            class="line-clamp-1 text-sm font-medium text-foreground"
                                        >
                                            {{ note.title || 'Märge' }}
                                        </p>
                                        <span
                                            class="shrink-0 text-xs text-muted-foreground"
                                        >
                                            {{ formatNoteDate(note.note_date) }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="note.body"
                                        class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                                    >
                                        {{ note.body }}
                                    </p>
                                </Link>
                            </div>
                            <p
                                v-else
                                class="rounded-2xl border border-dashed border-border/60 bg-background/60 px-3.5 py-4 text-sm text-muted-foreground"
                            >
                                Selle peenra kohta märkmeid veel pole.
                            </p>
                        </section>
                    </main>
                </div>
                <BottomNav active="map" />
            </div>

            <Teleport to="body">
                <div
                    v-if="cellModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-emerald-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-4"
                    @click.self="cellModal = null"
                >
                    <div
                        class="cell-sheet flex max-h-[min(88dvh,38rem)] w-full max-w-sm flex-col overflow-hidden rounded-t-[1.75rem] bg-card shadow-2xl ring-1 ring-black/5 sm:max-h-[min(76vh,42rem)] sm:rounded-[1.75rem]"
                        @click.stop
                    >
                        <div
                            class="flex items-center justify-between gap-3 border-b border-border/70 bg-[linear-gradient(135deg,rgba(236,253,245,0.95),rgba(255,251,235,0.82))] p-4"
                        >
                            <div>
                                <h3 class="font-semibold">
                                    Lisa taim valitud ruutu
                                </h3>
                                <p
                                    v-if="cellModal"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ cellModalLabel }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl hover:bg-muted"
                                aria-label="Sulge"
                                @click="cellModal = null"
                            >
                                <span class="material-symbols-outlined text-xl"
                                    >close</span
                                >
                            </button>
                        </div>
                        <div
                            class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain p-2"
                        >
                            <template v-if="modalPlant">
                                <div
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border/70 bg-muted/40"
                                        >
                                            <img
                                                v-if="modalPlant.image_url"
                                                :src="modalPlant.image_url"
                                                :alt="modalPlant.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <span
                                                v-else
                                                class="material-symbols-outlined text-lg text-muted-foreground"
                                                >spa</span
                                            >
                                        </span>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-foreground"
                                            >
                                                {{ modalPlant.name }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Muuda selle ruudu kogust või
                                                eemalda taim peenralt.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="isUnknownQuantityPlant(modalPlant)"
                                    class="mb-3 rounded-2xl border border-amber-200/60 bg-amber-50/80 px-3.5 py-3"
                                >
                                    <span
                                        class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900"
                                    >
                                        Seemnetest
                                    </span>
                                    <p
                                        class="mt-2 text-xs leading-5 text-muted-foreground"
                                    >
                                        Kogus on teadmata kuni idanemist
                                        märgitakse seemnepaketi juures.
                                    </p>
                                </div>
                                <div
                                    v-else
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3"
                                >
                                    <p
                                        class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Kogus selles ruudus
                                    </p>
                                    <div
                                        class="mt-2 flex items-center justify-between gap-3"
                                    >
                                        <div class="quantity-stepper">
                                            <button
                                                type="button"
                                                class="quantity-stepper-button"
                                                aria-label="Vähenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        -1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >remove</span
                                                >
                                            </button>
                                            <span
                                                class="min-w-[3rem] text-center text-sm font-semibold text-foreground"
                                            >
                                                {{ selectedPlantQuantity }} tk
                                            </span>
                                            <button
                                                type="button"
                                                class="quantity-stepper-button"
                                                aria-label="Suurenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >add</span
                                                >
                                            </button>
                                        </div>
                                        <p
                                            class="text-xs leading-5 text-muted-foreground"
                                        >
                                            Muuda kogust ilma taime ruudust
                                            eemaldamata.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button
                                        v-if="
                                            !isUnknownQuantityPlant(modalPlant)
                                        "
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-emerald-500/40 bg-[linear-gradient(135deg,rgb(16,185,129),rgb(64,133,63))] px-3.5 py-3 text-sm font-semibold text-white shadow-[0_12px_24px_rgba(16,185,129,0.24)] transition hover:brightness-105 sm:min-h-0"
                                        @click="
                                            cellModal &&
                                            updatePlantQuantityInCell(
                                                modalPlant.id,
                                                cellModal.row,
                                                cellModal.col,
                                            )
                                        "
                                    >
                                        <span
                                            class="material-symbols-outlined text-base"
                                            >check</span
                                        >
                                        Salvesta kogus
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-background px-3.5 py-3 text-sm font-semibold text-foreground transition hover:bg-muted sm:min-h-0"
                                        @click="removePlantFromBed(modalPlant)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-base"
                                            >delete</span
                                        >
                                        Eemalda taim
                                    </button>
                                </div>
                            </template>
                            <template v-else-if="!plantsWithoutBed.length">
                                <p
                                    class="py-3 text-center text-sm leading-6 text-muted-foreground"
                                >
                                    Vabu taimi praegu ei ole. Kõik taimed on
                                    juba peenardesse paigutatud või pole veel
                                    ühtegi taime lisatud.
                                </p>
                                <Link
                                    href="/plants/create"
                                    class="flex min-h-11 w-full touch-manipulation items-center justify-center gap-2 rounded-xl border-2 border-primary/40 bg-primary/10 py-3.5 font-semibold text-primary sm:min-h-0"
                                >
                                    <span class="material-symbols-outlined"
                                        >add_circle</span
                                    >
                                    Lisa uus taim
                                </Link>
                            </template>
                            <template v-else>
                                <div
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3 text-sm text-muted-foreground"
                                >
                                    Vali taim, mis paigutatakse ruutu
                                    <span class="font-semibold text-foreground">
                                        {{ cellModalLabel }}
                                    </span>
                                    .
                                </div>
                                <div
                                    v-if="assignShowsQuantityPicker"
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3"
                                >
                                    <p
                                        class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Kogus selles ruudus
                                    </p>
                                    <div
                                        class="mt-2 flex items-center justify-between gap-3"
                                    >
                                        <div class="quantity-stepper">
                                            <button
                                                type="button"
                                                class="quantity-stepper-button"
                                                aria-label="Vähenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        -1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >remove</span
                                                >
                                            </button>
                                            <span
                                                class="min-w-[3rem] text-center text-sm font-semibold text-foreground"
                                            >
                                                {{ selectedPlantQuantity }} tk
                                            </span>
                                            <button
                                                type="button"
                                                class="quantity-stepper-button"
                                                aria-label="Suurenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >add</span
                                                >
                                            </button>
                                        </div>
                                        <p
                                            class="text-xs leading-5 text-muted-foreground"
                                        >
                                            Kasuta seda, kui ühes ruudus on mitu
                                            sama taime.
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-for="[
                                        categoryName,
                                        plants,
                                    ] in plantsWithoutBedByCategory"
                                    :key="categoryName"
                                    class="mb-4 last:mb-0"
                                >
                                    <p
                                        class="mb-1.5 px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ categoryName }}
                                    </p>
                                    <ul class="space-y-1">
                                        <li
                                            v-for="plant in plants"
                                            :key="plant.id"
                                        >
                                            <button
                                                type="button"
                                                class="plant-picker-card"
                                                :disabled="
                                                    assigningPlantId ===
                                                    plant.id
                                                "
                                                @click="
                                                    cellModal &&
                                                    assignPlantToCell(
                                                        plant.id,
                                                        cellModal.row,
                                                        cellModal.col,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/70 bg-emerald-50 shadow-sm"
                                                >
                                                    <img
                                                        v-if="plant.image_url"
                                                        :src="plant.image_url"
                                                        :alt="plant.name"
                                                        class="h-full w-full object-cover"
                                                    />
                                                    <span
                                                        v-else
                                                        class="material-symbols-outlined text-lg text-muted-foreground"
                                                        >spa</span
                                                    >
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span
                                                        class="block truncate font-medium text-foreground"
                                                    >
                                                        {{ plant.name }}
                                                    </span>
                                                    <span
                                                        class="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"
                                                    >
                                                        {{
                                                            plant.category
                                                                ?.name ??
                                                            'Kategooriata'
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            isUnknownQuantityPlant(
                                                                plant,
                                                            )
                                                        "
                                                        class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-900"
                                                    >
                                                        Seemnetest
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="mt-1 block text-xs text-muted-foreground"
                                                    >
                                                        {{ plant.quantity }}
                                                        tk saadaval
                                                    </span>
                                                </span>
                                                <span
                                                    v-if="
                                                        assigningPlantId ===
                                                        plant.id
                                                    "
                                                    class="picker-spinner"
                                                    aria-hidden="true"
                                                ></span>
                                                <span
                                                    v-else
                                                    class="material-symbols-outlined text-base text-primary"
                                                    >check_circle</span
                                                >
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>

<style scoped>
.bed-hero::before {
    position: absolute;
    inset: -30% -20% auto auto;
    width: 18rem;
    height: 18rem;
    content: '';
    background: radial-gradient(
        circle,
        rgba(187, 247, 208, 0.36),
        rgba(187, 247, 208, 0)
    );
    transform: translate3d(0, 0, 0);
}

.hero-stat-card,
.hero-health-card {
    min-height: 4.5rem;
    padding: 0.8rem;
    border: 1px solid rgba(255, 255, 255, 0.46);
    border-radius: 1rem;
    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.28),
            rgba(255, 255, 255, 0.12)
        ),
        rgba(20, 34, 24, 0.22);
    box-shadow: 0 14px 34px rgba(23, 37, 26, 0.22);
    backdrop-filter: blur(14px);
}

.hero-stat-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    color: rgba(255, 255, 255, 0.82);
}

.hero-stat-card span {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.hero-stat-card strong {
    margin-top: 0.35rem;
    font-size: 1.15rem;
    line-height: 1;
    color: white;
}

.hero-health-card {
    position: relative;
}

.health-ring {
    transition: stroke-dashoffset 900ms cubic-bezier(0.22, 1, 0.36, 1);
}

.bed-fullness-track {
    height: 0.7rem;
    overflow: hidden;
    border-radius: 999px;
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.88),
        rgba(0, 0, 0, 0.04)
    );
    box-shadow: inset 0 1px 3px rgba(35, 52, 38, 0.18);
}

.bed-fullness-track span {
    display: block;
    width: var(--bed-fill);
    height: 100%;
    border-radius: inherit;
    background:
        linear-gradient(90deg, rgba(255, 255, 255, 0.34), transparent 45%),
        var(--bed-fill-color);
    box-shadow: 0 0 22px
        color-mix(in srgb, var(--bed-fill-color), transparent 45%);
    transition:
        width 850ms cubic-bezier(0.22, 1, 0.36, 1),
        background-color 350ms ease;
}

.bed-grid-viewport {
    overflow: hidden;
    /* Ühe sõrmega keri lehte; kahe sõrmega suumi ruudustikku. */
    touch-action: pan-y pinch-zoom;
    user-select: none;
}

.bed-grid-zoom-stage {
    transform-origin: center center;
    transition: transform 120ms ease-out;
    will-change: transform;
}

.bed-grid-viewport:has(.bed-grid-zoom-stage:active) .bed-grid-zoom-stage {
    transition: none;
}

.cell-sheet {
    animation: sheetRise 280ms cubic-bezier(0.22, 1, 0.36, 1);
}

.quantity-stepper {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem;
    border: 1px solid rgba(70, 95, 57, 0.16);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.72);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.quantity-stepper-button {
    display: inline-flex;
    width: 2.75rem;
    height: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: var(--foreground);
    transition:
        transform 150ms ease,
        background-color 150ms ease;
}

.quantity-stepper-button:active {
    transform: scale(0.88);
}

.quantity-stepper-button:hover {
    background: var(--muted);
}

.plant-picker-card {
    display: flex;
    min-height: 4.25rem;
    width: 100%;
    touch-action: manipulation;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid rgba(70, 95, 57, 0.14);
    border-radius: 1.1rem;
    padding: 0.8rem;
    text-align: left;
    background:
        linear-gradient(
            135deg,
            rgba(255, 255, 255, 0.84),
            rgba(236, 253, 245, 0.72)
        ),
        var(--card);
    box-shadow: 0 8px 20px rgba(44, 70, 46, 0.08);
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        box-shadow 220ms ease;
}

.plant-picker-card:hover,
.plant-picker-card:focus-visible {
    transform: translateY(-1px);
    border-color: rgba(16, 185, 129, 0.4);
    box-shadow: 0 14px 28px rgba(22, 101, 52, 0.13);
}

.plant-picker-card:disabled {
    cursor: wait;
    opacity: 0.82;
}

.picker-spinner {
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid rgba(16, 185, 129, 0.24);
    border-top-color: rgb(16, 185, 129);
    border-radius: 999px;
    animation: spin 720ms linear infinite;
}

.confetti-burst {
    position: relative;
    height: 0;
    pointer-events: none;
}

.confetti-burst i {
    position: absolute;
    top: -0.35rem;
    left: calc(50% - 0.2rem);
    width: 0.36rem;
    height: 0.56rem;
    border-radius: 0.1rem;
    background: rgb(16, 185, 129);
    animation: confettiPop 1100ms ease-out both;
}

.confetti-burst i:nth-child(2) {
    background: rgb(245, 158, 11);
    animation-delay: 80ms;
    transform: rotate(24deg);
}

.confetti-burst i:nth-child(3) {
    background: rgb(132, 204, 22);
    animation-delay: 120ms;
}

.confetti-burst i:nth-child(4) {
    background: rgb(14, 165, 233);
    animation-delay: 160ms;
}

.confetti-burst i:nth-child(5) {
    background: rgb(244, 114, 182);
    animation-delay: 200ms;
}

@keyframes sheetRise {
    from {
        opacity: 0;
        transform: translateY(1.5rem) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes bedPulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.035);
    }
}

@keyframes confettiPop {
    0% {
        opacity: 0;
        transform: translate(0, 0) rotate(0deg) scale(0.6);
    }
    18% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: translate(calc((var(--i, 0) + 1) * 0.65rem), -2.2rem)
            rotate(180deg) scale(1);
    }
}

.confetti-burst i:nth-child(1) {
    --i: -3;
}

.confetti-burst i:nth-child(2) {
    --i: -2;
}

.confetti-burst i:nth-child(3) {
    --i: 0;
}

.confetti-burst i:nth-child(4) {
    --i: 2;
}

.confetti-burst i:nth-child(5) {
    --i: 3;
}
</style>
