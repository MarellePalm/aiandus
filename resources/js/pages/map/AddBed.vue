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
type BedPreset = {
    id: string;
    label: string;
    description: string;
    columns: number;
    rows: number;
};

const currentStep = ref<WizardStep>(1);
const designBrush = ref<DesignBrush>('plantable');
const wizardSteps = [
    { id: 1 as const, label: 'Peenar nimeta', icon: 'edit' },
    { id: 2 as const, label: 'Kujunda kuju', icon: 'grid_view' },
    { id: 3 as const, label: 'Viimistlus', icon: 'photo_camera' },
];
const designBrushes: Array<{
    id: DesignBrush;
    label: string;
    icon: string;
    description: string;
}> = [
    {
        id: 'plantable',
        label: 'Peenraruut',
        icon: 'psychiatry',
        description: 'Siia saab taime istutada.',
    },
    {
        id: 'walkway',
        label: 'Tee / kivi',
        icon: 'texture',
        description: 'Kivitee või vahekäik.',
    },
    {
        id: 'empty',
        label: 'Tühi',
        icon: 'crop_free',
        description: 'Auk või vaba ala kujus.',
    },
];
const bedPresets: BedPreset[] = [
    {
        id: 'small',
        label: 'Väike 2×4',
        description: 'Kompaktne ürdi- või salatipeenar.',
        columns: 2,
        rows: 4,
    },
    {
        id: 'medium',
        label: 'Keskmine 3×6',
        description: 'Mõnus põhivalik köögiviljadele.',
        columns: 3,
        rows: 6,
    },
    {
        id: 'large',
        label: 'Suur 4×8',
        description: 'Rohkem ruumi segapeenrale.',
        columns: 4,
        rows: 8,
    },
];

function makeCellId(x: number, y: number): string {
    return `cell-${x}-${y}-${Math.random().toString(36).slice(2, 8)}`;
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

    if (!layout || !Array.isArray(layout) || layout.length === 0) {
        return [
            {
                id: makeCellId(0, 0),
                x: 0,
                y: 0,
                active: true,
                kind: 'plantable',
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
}>({
    name: props.mode === 'edit' ? (props.bed?.name ?? '') : '',
    location: props.mode === 'edit' ? (props.bed?.location ?? '') : '',
    cell_size_cm: props.mode === 'edit' ? (props.bed?.cell_size_cm ?? 30) : 30,
    image: null,
    cells: [],
    layout: [],
});

const selectedCell = computed(
    () => cells.value.find((cell) => cell.id === selectedCellId.value) ?? null,
);
const activeCells = computed(() =>
    cells.value.filter((cell) => cell.active && cell.kind === 'plantable'),
);
const layoutCells = computed(() => cells.value);

const selectedPlantCount = computed(
    () => selectedCell.value?.plants.length ?? 0,
);
const walkwayCellCount = computed(
    () => cells.value.filter((cell) => cell.kind === 'walkway').length,
);
const emptyDesignCellCount = computed(
    () => cells.value.filter((cell) => cell.kind === 'empty').length,
);
const selectedCellLabel = computed(() =>
    selectedCell.value
        ? `Veerg ${displayColumnNumber(selectedCell.value.x)}, rida ${displayRowNumber(selectedCell.value.y)}`
        : 'Vali peenrast ruut',
);

const bounds = computed(() => {
    const source = layoutCells.value.length
        ? layoutCells.value
        : [{ x: 0, y: 0 }];
    const xs = source.map((cell) => cell.x);
    const ys = source.map((cell) => cell.y);

    return {
        minX: Math.min(...xs),
        maxX: Math.max(...xs),
        minY: Math.min(...ys),
        maxY: Math.max(...ys),
    };
});

const displayBounds = computed(() => ({
    minX: bounds.value.minX,
    maxX: bounds.value.maxX,
    minY: bounds.value.minY,
    maxY: bounds.value.maxY,
}));

const displayRows = computed(() =>
    Array.from(
        { length: displayBounds.value.maxY - displayBounds.value.minY + 1 },
        (_, index) => displayBounds.value.minY + index,
    ),
);
const displayColumns = computed(() =>
    Array.from(
        { length: displayBounds.value.maxX - displayBounds.value.minX + 1 },
        (_, index) => displayBounds.value.minX + index,
    ),
);
const bedSummaryLabel = computed(
    () => `${displayColumns.value.length} × ${displayRows.value.length} ruutu`,
);
const bedPhysicalSizeLabel = computed(() => {
    const cellSize = Math.max(10, Number(form.cell_size_cm || 30));
    const widthMeters = (displayColumns.value.length * cellSize) / 100;
    const heightMeters = (displayRows.value.length * cellSize) / 100;
    return `${formatMeters(widthMeters)} × ${formatMeters(heightMeters)} m`;
});
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
        map.set(occupiedKey(cell.x, cell.y), cell);
    });
    return map;
});

function getCellAt(x: number, y: number): BedCell | null {
    return occupiedCellMap.value.get(occupiedKey(x, y)) ?? null;
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
    return 'grid_view';
}

function cellKindLabel(cell: BedCell): string {
    if (cell.kind === 'walkway') return 'tee või kivi';
    if (cell.kind === 'empty') return 'tühi ala';
    return 'peenraruut';
}

function selectCell(cell: BedCell) {
    selectedCellId.value = cell.id;
    highlightedCellId.value = cell.id;

    if (highlightTimeout) clearTimeout(highlightTimeout);
    highlightTimeout = setTimeout(() => {
        if (highlightedCellId.value === cell.id) highlightedCellId.value = null;
    }, 900);
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

function addCellAt(x: number, y: number) {
    const existing = getCellAt(x, y);
    if (existing) {
        if (existing.kind !== 'plantable') {
            cells.value = cells.value.map((cell) =>
                cell.id === existing.id
                    ? { ...cell, active: true, kind: 'plantable' }
                    : cell,
            );
        }
        selectCell(existing);
        return;
    }

    const newCell: BedCell = {
        id: makeCellId(x, y),
        x,
        y,
        active: true,
        kind: 'plantable',
        plants: [],
    };

    cells.value = [...cells.value, newCell];
    selectCell(newCell);
}

function addRelative(direction: 'up' | 'down' | 'left' | 'right') {
    if (!selectedCell.value) return;
    const cell = selectedCell.value;

    if (direction === 'up') addCellAt(cell.x, cell.y - 1);
    if (direction === 'down') addCellAt(cell.x, cell.y + 1);
    if (direction === 'left') addCellAt(cell.x - 1, cell.y);
    if (direction === 'right') addCellAt(cell.x + 1, cell.y);
}

function shiftCells(
    axis: 'x' | 'y',
    comparison: (cell: BedCell) => boolean,
    delta: number,
) {
    cells.value = cells.value.map((cell) =>
        comparison(cell) ? { ...cell, [axis]: cell[axis] + delta } : cell,
    );
}

function addBetween(direction: 'up' | 'down' | 'left' | 'right') {
    if (!selectedCell.value) return;
    const cell = selectedCell.value;

    if (direction === 'right') {
        const targetX = cell.x + 1;
        if (getCellAt(targetX, cell.y)) {
            shiftCells('x', (item) => item.x > cell.x, 1);
        }
        addCellAt(targetX, cell.y);
    }

    if (direction === 'left') {
        const targetX = cell.x - 1;
        if (getCellAt(targetX, cell.y)) {
            shiftCells('x', (item) => item.x < cell.x, -1);
        }
        addCellAt(targetX, cell.y);
    }

    if (direction === 'down') {
        const targetY = cell.y + 1;
        if (getCellAt(cell.x, targetY)) {
            shiftCells('y', (item) => item.y > cell.y, 1);
        }
        addCellAt(cell.x, targetY);
    }

    if (direction === 'up') {
        const targetY = cell.y - 1;
        if (getCellAt(cell.x, targetY)) {
            shiftCells('y', (item) => item.y < cell.y, -1);
        }
        addCellAt(cell.x, targetY);
    }
}

function handleDirectionalAdd(direction: 'up' | 'down' | 'left' | 'right') {
    if (!selectedCell.value) return;
    const cell = selectedCell.value;

    const target = {
        up: { x: cell.x, y: cell.y - 1 },
        down: { x: cell.x, y: cell.y + 1 },
        left: { x: cell.x - 1, y: cell.y },
        right: { x: cell.x + 1, y: cell.y },
    }[direction];

    if (getCellAt(target.x, target.y)) {
        addBetween(direction);
        return;
    }

    addRelative(direction);
}

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
    if (activeCells.value.length <= 1) return;

    const current = selectedCell.value;
    cells.value = cells.value.filter((cell) => cell.id !== current.id);
    selectedCellId.value = cells.value[0]?.id ?? '';
}

function addCellFromPlaceholder(x: number, y: number) {
    setCellKindAt(x, y, designBrush.value);
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
        id: makeCellId(x, y),
        x,
        y,
        active: kind === 'plantable',
        kind,
        plants: [],
    };
    cells.value = [...cells.value, newCell];
    selectCell(newCell);
    form.clearErrors('cells');
}

function paintCell(cell: BedCell) {
    setCellKindAt(cell.x, cell.y, designBrush.value);
}

function cycleCellKind(cell: BedCell) {
    const nextKind: DesignBrush =
        cell.kind === 'plantable'
            ? 'walkway'
            : cell.kind === 'walkway'
              ? 'empty'
              : 'plantable';
    setCellKindAt(cell.x, cell.y, nextKind);
}

function applyPreset(preset: BedPreset) {
    const next: BedCell[] = [];
    for (let y = 0; y < preset.rows; y += 1) {
        for (let x = 0; x < preset.columns; x += 1) {
            next.push({
                id: makeCellId(x, y),
                x,
                y,
                active: true,
                kind: 'plantable',
                plants: [],
            });
        }
    }
    cells.value = next;
    selectedCellId.value = next[0]?.id ?? '';
    form.clearErrors('cells');
}

function fillVisibleRectangle() {
    const next: BedCell[] = [];
    for (
        let y = displayBounds.value.minY;
        y <= displayBounds.value.maxY;
        y += 1
    ) {
        for (
            let x = displayBounds.value.minX;
            x <= displayBounds.value.maxX;
            x += 1
        ) {
            const existing = getCellAt(x, y);
            next.push(
                existing
                    ? { ...existing, active: true, kind: 'plantable' }
                    : {
                          id: makeCellId(x, y),
                          x,
                          y,
                          active: true,
                          kind: 'plantable',
                          plants: [],
                      },
            );
        }
    }
    cells.value = next;
    selectedCellId.value = next[0]?.id ?? '';
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
    const cell: BedCell = {
        id: makeCellId(0, 0),
        x: 0,
        y: 0,
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

function syncCellsToForm() {
    form.layout = displayRows.value.map((y) =>
        displayColumns.value.map((x) => {
            const cell = getCellAt(x, y);
            if (!cell) return 0;
            if (cell.kind === 'walkway') return -1;
            if (cell.kind === 'empty') return 0;
            return 1;
        }),
    );
    form.cells = activeCells.value
        .filter((cell) => cell.plants.length > 0)
        .map((cell) => ({
            x: cell.x - displayBounds.value.minX,
            y: cell.y - displayBounds.value.minY,
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

watch(selectedCellId, async () => {
    await nextTick();
    selectedCellElement.value?.scrollIntoView({
        block: 'nearest',
        inline: 'nearest',
        behavior: 'smooth',
    });
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
                                    Pane peenrale nimi ja vali kiire suuruse
                                    algus.
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
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
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

                        <div class="space-y-2">
                            <p
                                class="text-xs font-bold tracking-[0.16em] text-muted-foreground uppercase"
                            >
                                Suuruse presetid
                            </p>
                            <button
                                v-for="preset in bedPresets"
                                :key="preset.id"
                                type="button"
                                class="preset-card"
                                @click="applyPreset(preset)"
                            >
                                <span class="preset-preview">
                                    <i
                                        v-for="index in Math.min(
                                            preset.rows * preset.columns,
                                            18,
                                        )"
                                        :key="index"
                                    ></i>
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="block text-sm font-semibold text-foreground"
                                    >
                                        {{ preset.label }}
                                    </span>
                                    <span
                                        class="mt-0.5 block text-xs leading-5 text-muted-foreground"
                                        >{{ preset.description }}</span
                                    >
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-show="currentStep === 2"
                class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]"
            >
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
                                Vali pintsel ja puuduta ruute: peenraruut,
                                kivitee või tühi ala.
                            </p>
                        </div>
                        <div
                            class="inline-flex w-fit items-center gap-2 rounded-full bg-primary/8 px-3 py-1.5 text-sm font-medium text-primary"
                        >
                            <span class="material-symbols-outlined text-base"
                                >grid_view</span
                            >
                            {{ selectedCellLabel }}
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="brush in designBrushes"
                            :key="brush.id"
                            type="button"
                            class="design-brush"
                            :class="
                                designBrush === brush.id
                                    ? 'design-brush--active'
                                    : ''
                            "
                            @click="designBrush = brush.id"
                        >
                            <span class="material-symbols-outlined">{{
                                brush.icon
                            }}</span>
                            <span>
                                <strong>{{ brush.label }}</strong>
                                <small>{{ brush.description }}</small>
                            </span>
                        </button>
                    </div>
                    <div
                        class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-muted-foreground"
                    >
                        <span class="legend-pill legend-pill--plantable"
                            >{{ activeCells.length }} peenraruutu</span
                        >
                        <span class="legend-pill legend-pill--walkway"
                            >{{ walkwayCellCount }} teed/kivi</span
                        >
                        <span class="legend-pill legend-pill--empty"
                            >{{ emptyDesignCellCount }} tühja ala</span
                        >
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100 sm:min-h-0 sm:py-2"
                            @click="fillVisibleRectangle"
                        >
                            <span class="material-symbols-outlined text-base"
                                >select_all</span
                            >
                            Täida kõik
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-border bg-background px-3 text-sm font-semibold text-foreground transition hover:bg-muted sm:min-h-0 sm:py-2"
                            @click="resetToSingleCell"
                        >
                            <span class="material-symbols-outlined text-base"
                                >ink_eraser</span
                            >
                            Tühjenda
                        </button>
                    </div>

                    <div class="mt-4 max-w-sm">
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
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            See on ühe peenraruudu suurus päris aias.
                        </p>
                        <p
                            v-if="form.errors.cell_size_cm"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ form.errors.cell_size_cm }}
                        </p>
                    </div>

                    <div
                        ref="gridScroller"
                        class="mt-4 overflow-x-auto rounded-[1.25rem] bg-background p-3 ring-1 ring-border/70 sm:p-4"
                    >
                        <div
                            class="bed-grid-frame inline-grid gap-2.5 rounded-[1.15rem] p-2 sm:p-2.5"
                            :style="{
                                gridTemplateColumns: `repeat(${displayColumns.length}, minmax(0, 3.35rem))`,
                            }"
                        >
                            <template
                                v-for="y in displayRows"
                                :key="`row-${y}`"
                            >
                                <template
                                    v-for="x in displayColumns"
                                    :key="`cell-${x}-${y}`"
                                >
                                    <div
                                        class="relative size-[3.35rem]"
                                        :ref="
                                            (el) => {
                                                const cell = getCellAt(x, y);
                                                if (cell)
                                                    setSelectedCellRef(
                                                        el,
                                                        cell.id,
                                                    );
                                            }
                                        "
                                    >
                                        <template v-if="getCellAt(x, y)">
                                            <button
                                                type="button"
                                                :aria-label="`${cellKindLabel(getCellAt(x, y)!)}: veerg ${displayColumnNumber(x)}, rida ${displayRowNumber(y)}`"
                                                :class="
                                                    addBedEditorCellClasses(
                                                        x,
                                                        y,
                                                    )
                                                "
                                                @click="
                                                    getCellAt(x, y) &&
                                                    paintCell(getCellAt(x, y)!)
                                                "
                                                @dblclick="
                                                    getCellAt(x, y) &&
                                                    cycleCellKind(
                                                        getCellAt(x, y)!,
                                                    )
                                                "
                                            >
                                                <div
                                                    class="absolute inset-x-0 top-0 h-1/2 bg-linear-to-b from-white/15 to-transparent"
                                                />
                                                <div
                                                    v-if="
                                                        getCellAt(x, y)?.plants
                                                            .length
                                                    "
                                                    class="absolute inset-0 bg-linear-to-t from-black/45 via-black/15 to-transparent"
                                                />
                                                <div
                                                    class="relative z-10 flex size-full flex-col items-center justify-center px-1 text-center"
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-lg"
                                                        :class="
                                                            getCellAt(x, y)
                                                                ?.plants.length
                                                                ? 'text-white'
                                                                : selectedCell?.id ===
                                                                    getCellAt(
                                                                        x,
                                                                        y,
                                                                    )?.id
                                                                  ? 'text-primary'
                                                                  : 'text-emerald-900/45'
                                                        "
                                                    >
                                                        {{
                                                            cellIcon(
                                                                getCellAt(
                                                                    x,
                                                                    y,
                                                                )!,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            getCellAt(x, y)
                                                                ?.plants.length
                                                        "
                                                        class="mt-1 line-clamp-2 text-[9px] leading-tight font-semibold text-white"
                                                    >
                                                        {{
                                                            getPlantNames(
                                                                getCellAt(
                                                                    x,
                                                                    y,
                                                                )!,
                                                            ).join(', ')
                                                        }}
                                                    </span>
                                                </div>

                                                <span
                                                    class="absolute right-1.5 bottom-1.5 rounded-full px-1.5 py-0.5 text-[9px] font-semibold"
                                                    :class="
                                                        selectedCell?.id ===
                                                        getCellAt(x, y)?.id
                                                            ? 'bg-card/95 text-primary'
                                                            : 'bg-white/55 text-emerald-950/75'
                                                    "
                                                >
                                                    {{
                                                        displayColumnNumber(x)
                                                    }},{{ displayRowNumber(y) }}
                                                </span>
                                            </button>

                                            <template
                                                v-if="
                                                    selectedCell?.id ===
                                                    getCellAt(x, y)?.id
                                                "
                                            >
                                                <button
                                                    type="button"
                                                    class="absolute -top-3 left-1/2 z-20 hidden size-7 -translate-x-1/2 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 sm:flex"
                                                    @click.stop="
                                                        handleDirectionalAdd(
                                                            'up',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_upward</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="absolute -bottom-3 left-1/2 z-20 hidden size-7 -translate-x-1/2 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 sm:flex"
                                                    @click.stop="
                                                        handleDirectionalAdd(
                                                            'down',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_downward</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="absolute top-1/2 -left-3 z-20 hidden size-7 -translate-y-1/2 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 sm:flex"
                                                    @click.stop="
                                                        handleDirectionalAdd(
                                                            'left',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_back</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="absolute top-1/2 -right-3 z-20 hidden size-7 -translate-y-1/2 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 sm:flex"
                                                    @click.stop="
                                                        handleDirectionalAdd(
                                                            'right',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_forward</span
                                                    >
                                                </button>
                                            </template>
                                        </template>

                                        <button
                                            v-else
                                            type="button"
                                            class="size-full rounded-2xl border border-dashed border-primary/20 bg-card/70 text-muted-foreground transition hover:border-primary/35 hover:bg-primary/8 hover:text-primary"
                                            @click="
                                                addCellFromPlaceholder(x, y)
                                            "
                                        >
                                            <span
                                                class="material-symbols-outlined text-lg"
                                                >add</span
                                            >
                                        </button>
                                    </div>
                                </template>
                            </template>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-[1.25rem] bg-secondary/40 p-4 ring-1 ring-border/70 xl:hidden"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Valitud ruut
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ selectedCellLabel }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                            >
                                {{ selectedPlantCount }} taimekirjet
                            </span>
                        </div>

                        <div class="mt-4 grid place-items-center">
                            <button
                                type="button"
                                class="mb-2 flex size-12 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!selectedCell"
                                @click="handleDirectionalAdd('up')"
                            >
                                <span
                                    class="material-symbols-outlined text-base"
                                    >arrow_upward</span
                                >
                            </button>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="flex size-12 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!selectedCell"
                                    @click="handleDirectionalAdd('left')"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >arrow_back</span
                                    >
                                </button>
                                <div
                                    class="flex size-12 items-center justify-center rounded-2xl bg-primary/12 text-primary"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >grid_view</span
                                    >
                                </div>
                                <button
                                    type="button"
                                    class="flex size-12 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!selectedCell"
                                    @click="handleDirectionalAdd('right')"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >arrow_forward</span
                                    >
                                </button>
                            </div>
                            <button
                                type="button"
                                class="mt-2 flex size-12 items-center justify-center rounded-full bg-card text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!selectedCell"
                                @click="handleDirectionalAdd('down')"
                            >
                                <span
                                    class="material-symbols-outlined text-base"
                                    >arrow_downward</span
                                >
                            </button>
                        </div>

                        <p
                            v-if="selectedHasPlants"
                            class="mt-4 rounded-2xl bg-primary/8 p-3 text-sm leading-6 text-primary ring-1 ring-primary/15"
                        >
                            Valitud ruudus on taim(ed). Seda ruutu ei saa
                            eemaldada enne, kui taimed on ümber paigutatud.
                        </p>

                        <button
                            type="button"
                            class="mt-4 h-12 w-full rounded-full px-4 text-sm font-semibold transition"
                            :class="
                                selectedHasPlants || activeCells.length <= 1
                                    ? 'cursor-not-allowed bg-muted/40 text-muted-foreground'
                                    : 'bg-card text-foreground shadow-sm ring-1 ring-border hover:bg-secondary/70'
                            "
                            :disabled="
                                selectedHasPlants || activeCells.length <= 1
                            "
                            @click="removeSelectedCell"
                        >
                            Eemalda valitud ruut
                        </button>
                    </div>
                </div>

                <aside class="hidden space-y-4 xl:block">
                    <div
                        class="rounded-[1.5rem] bg-card p-4 ring-1 shadow-soft ring-border/70"
                    >
                        <p class="text-sm font-semibold text-foreground">
                            Valitud ruut
                        </p>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            {{ selectedCellLabel }}
                        </p>

                        <div class="mt-5 grid place-items-center">
                            <button
                                type="button"
                                class="mb-2 flex size-11 items-center justify-center rounded-full bg-background text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!selectedCell"
                                @click="handleDirectionalAdd('up')"
                            >
                                <span
                                    class="material-symbols-outlined text-base"
                                    >arrow_upward</span
                                >
                            </button>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="flex size-11 items-center justify-center rounded-full bg-background text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!selectedCell"
                                    @click="handleDirectionalAdd('left')"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >arrow_back</span
                                    >
                                </button>
                                <div
                                    class="flex size-12 items-center justify-center rounded-2xl bg-primary/12 text-primary"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >grid_view</span
                                    >
                                </div>
                                <button
                                    type="button"
                                    class="flex size-11 items-center justify-center rounded-full bg-background text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!selectedCell"
                                    @click="handleDirectionalAdd('right')"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >arrow_forward</span
                                    >
                                </button>
                            </div>
                            <button
                                type="button"
                                class="mt-2 flex size-11 items-center justify-center rounded-full bg-background text-primary shadow-sm ring-1 ring-primary/25 hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!selectedCell"
                                @click="handleDirectionalAdd('down')"
                            >
                                <span
                                    class="material-symbols-outlined text-base"
                                    >arrow_downward</span
                                >
                            </button>
                        </div>
                    </div>

                    <div
                        class="rounded-[1.5rem] bg-secondary/40 p-4 ring-1 ring-border/70"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-foreground">
                                Ruudu sisu
                            </p>
                            <span
                                class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                            >
                                {{ selectedPlantCount }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            {{
                                selectedHasPlants
                                    ? 'Valitud ruudus on juba taim(ed).'
                                    : 'Ruudus ei ole veel taimi.'
                            }}
                        </p>
                        <p
                            v-if="selectedHasPlants"
                            class="mt-4 rounded-2xl bg-primary/8 p-3 text-sm leading-6 text-primary ring-1 ring-primary/15"
                        >
                            Taimedega ruutu ei saa enne ümberpaigutamist
                            eemaldada.
                        </p>
                        <button
                            type="button"
                            class="mt-4 h-12 w-full rounded-full px-4 text-sm font-semibold transition"
                            :class="
                                selectedHasPlants || activeCells.length <= 1
                                    ? 'cursor-not-allowed bg-muted/40 text-muted-foreground'
                                    : 'bg-card text-foreground shadow-sm ring-1 ring-border hover:bg-secondary/70'
                            "
                            :disabled="
                                selectedHasPlants || activeCells.length <= 1
                            "
                            @click="removeSelectedCell"
                        >
                            Eemalda ruut
                        </button>
                    </div>
                </aside>
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

.legend-pill--empty {
    color: rgb(100, 116, 139);
    border-color: rgba(148, 163, 184, 0.28);
    background: rgba(248, 250, 252, 0.86);
}

.legend-pill--empty::before {
    border: 1px dashed rgba(100, 116, 139, 0.65);
    background: rgba(255, 255, 255, 0.52);
}

.bed-grid-frame {
    border: 1px solid rgba(70, 95, 57, 0.12);
    background:
        linear-gradient(rgba(255, 255, 255, 0.62), rgba(255, 255, 255, 0.38)),
        radial-gradient(
            circle at 20% 0%,
            color-mix(in srgb, var(--primary), transparent 82%),
            transparent 36%
        ),
        color-mix(in srgb, var(--background), var(--primary) 5%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.7),
        0 16px 34px rgba(44, 70, 46, 0.1);
}

.bed-cell {
    min-width: 44px;
    min-height: 44px;
    border-radius: 0.9rem;
}

.bed-cell--empty {
    border-color: rgba(84, 112, 65, 0.16);
    color: rgba(34, 78, 44, 0.68);
    background:
        radial-gradient(
            circle at 28% 24%,
            rgba(255, 255, 255, 0.5),
            transparent 26%
        ),
        linear-gradient(145deg, rgb(190, 220, 159), rgb(143, 185, 122));
    box-shadow:
        inset 0 2px 5px rgba(255, 255, 255, 0.28),
        inset 0 -8px 14px rgba(59, 99, 50, 0.16);
}

.bed-cell--warm {
    background:
        radial-gradient(
            circle at 28% 24%,
            rgba(255, 255, 255, 0.52),
            transparent 26%
        ),
        linear-gradient(145deg, rgb(203, 224, 168), rgb(153, 191, 128));
}

.bed-cell--planted {
    border-color: rgba(21, 128, 61, 0.32);
    background:
        radial-gradient(
            circle at 50% 28%,
            rgba(220, 252, 231, 0.9),
            transparent 44%
        ),
        linear-gradient(145deg, rgb(79, 151, 83), rgb(34, 99, 54));
    box-shadow:
        0 10px 22px rgba(34, 197, 94, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.22);
}

.bed-cell--walkway {
    border-color: rgba(120, 113, 108, 0.22);
    color: rgb(87, 83, 78);
    background:
        radial-gradient(
            circle at 18% 22%,
            rgba(255, 255, 255, 0.52) 0 10%,
            transparent 11%
        ),
        radial-gradient(
            circle at 74% 28%,
            rgba(120, 113, 108, 0.28) 0 10%,
            transparent 11%
        ),
        radial-gradient(
            circle at 45% 72%,
            rgba(87, 83, 78, 0.18) 0 8%,
            transparent 9%
        ),
        linear-gradient(145deg, rgb(231, 229, 228), rgb(196, 190, 184));
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

.bed-cell--editor-selected {
    border-color: color-mix(in srgb, var(--primary), transparent 20%);
    color: var(--primary);
    background:
        radial-gradient(
            circle at 50% 20%,
            rgba(255, 255, 255, 0.82),
            transparent 42%
        ),
        color-mix(in srgb, var(--primary), white 84%);
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--primary), transparent 78%),
        0 14px 26px rgba(44, 70, 46, 0.16);
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
