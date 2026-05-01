<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
    bed?: {
      id: number;
      name: string;
      location: string | null;
      image_url?: string | null;
      layout?: number[][] | null;
      plants?: BedPlant[];
    };
  }>(),
  {
    mode: 'create',
    bed: undefined,
  },
);

const existingImageUrl = ref(props.mode === 'edit' ? props.bed?.image_url ?? null : null);
const newBedImagePreview = ref<string | null>(null);
const gridScroller = ref<HTMLElement | null>(null);
const selectedCellElement = ref<HTMLElement | null>(null);
const highlightedCellId = ref<string | null>(null);
let highlightTimeout: ReturnType<typeof setTimeout> | null = null;

function makeCellId(x: number, y: number): string {
  return `cell-${x}-${y}-${Math.random().toString(36).slice(2, 8)}`;
}

function createInitialCells(): BedCell[] {
  const layout = props.bed?.layout;
  const plants = props.bed?.plants ?? [];
  const plantMap = new Map<string, CellPlant[]>();

  plants.forEach((plant) => {
    if (!plant.position_in_bed || !/^\d+,\d+$/.test(plant.position_in_bed)) return;
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
    return [{ id: makeCellId(0, 0), x: 0, y: 0, active: true, plants: [] }];
  }

  const cells: BedCell[] = [];
  layout.forEach((row, y) => {
    if (!Array.isArray(row)) return;
    row.forEach((rawCell, x) => {
      if (Number(rawCell) !== 1) return;
      cells.push({
        id: makeCellId(x, y),
        x,
        y,
        active: true,
        plants: [...(plantMap.get(`${y},${x}`) ?? [])],
      });
    });
  });

  return cells.length ? cells : [{ id: makeCellId(0, 0), x: 0, y: 0, active: true, plants: [] }];
}

const cells = ref<BedCell[]>(createInitialCells());
const selectedCellId = ref<string>(cells.value[0]?.id ?? '');

const form = useForm<{
  name: string;
  location: string;
  image: File | null;
  cells: SubmitCell[];
}>({
  name: props.mode === 'edit' ? props.bed?.name ?? '' : '',
  location: props.mode === 'edit' ? props.bed?.location ?? '' : '',
  image: null,
  cells: [],
});

const selectedCell = computed(() => cells.value.find((cell) => cell.id === selectedCellId.value) ?? null);
const activeCells = computed(() => cells.value.filter((cell) => cell.active));

const selectedPlantCount = computed(() => selectedCell.value?.plants.length ?? 0);

const bounds = computed(() => {
  const source = activeCells.value.length ? activeCells.value : [{ x: 0, y: 0 }];
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
  Array.from({ length: displayBounds.value.maxY - displayBounds.value.minY + 1 }, (_, index) => displayBounds.value.minY + index),
);
const displayColumns = computed(() =>
  Array.from({ length: displayBounds.value.maxX - displayBounds.value.minX + 1 }, (_, index) => displayBounds.value.minX + index),
);

function occupiedKey(x: number, y: number): string {
  return `${x}:${y}`;
}

const occupiedCellMap = computed(() => {
  const map = new Map<string, BedCell>();
  activeCells.value.forEach((cell) => {
    map.set(occupiedKey(cell.x, cell.y), cell);
  });
  return map;
});

function getCellAt(x: number, y: number): BedCell | null {
  return occupiedCellMap.value.get(occupiedKey(x, y)) ?? null;
}

function getPlantNames(cell: BedCell): string[] {
  const plantIds = cell.plants.map((plant) => plant.plant_id);
  return plantIds
    .map((id) => props.bed?.plants?.find((plant) => plant.id === id)?.name ?? 'Taim')
    .slice(0, 3);
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

function addCellAt(x: number, y: number) {
  const existing = getCellAt(x, y);
  if (existing) {
    selectCell(existing);
    return;
  }

  const newCell: BedCell = {
    id: makeCellId(x, y),
    x,
    y,
    active: true,
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

function shiftCells(axis: 'x' | 'y', comparison: (cell: BedCell) => boolean, delta: number) {
  cells.value = cells.value.map((cell) => (comparison(cell) ? { ...cell, [axis]: cell[axis] + delta } : cell));
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

const selectedHasPlants = computed(() => Boolean(selectedCell.value?.plants.length));

function removeSelectedCell() {
  if (!selectedCell.value || selectedHasPlants.value) return;
  if (activeCells.value.length <= 1) return;

  const current = selectedCell.value;
  cells.value = cells.value.filter((cell) => cell.id !== current.id);
  selectedCellId.value = cells.value[0]?.id ?? '';
}

function addCellFromPlaceholder(x: number, y: number) {
  addCellAt(x, y);
}

function syncCellsToForm() {
  form.cells = activeCells.value.map((cell) => ({
    x: cell.x,
    y: cell.y,
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

  form.transform((data) => ({
    ...data,
    name: data.name.trim(),
    location: data.location.trim(),
    cells: form.cells,
  }));

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

function onImageChange(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0] ?? null;
  form.image = file;

  if (newBedImagePreview.value) {
    URL.revokeObjectURL(newBedImagePreview.value);
    newBedImagePreview.value = null;
  }

  if (file) {
    newBedImagePreview.value = URL.createObjectURL(file);
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
    <form class="space-y-5 rounded-[2rem] border border-border/70 bg-card/95 p-4 shadow-soft sm:p-5" @submit.prevent="submit">
      <div class="space-y-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Peenra nimi</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-2xl border border-border/80 bg-background px-4 py-3 text-foreground shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
            placeholder="Nt Ürdipeenar"
            maxlength="120"
          />
          <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Asukoht</label>
          <input
            v-model="form.location"
            type="text"
            class="w-full rounded-2xl border border-border/80 bg-background px-4 py-3 text-foreground shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
            placeholder="Nt Kasvuhoone kõrval"
            maxlength="255"
          />
          <p v-if="form.errors.location" class="mt-1 text-sm text-red-600">{{ form.errors.location }}</p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Peenra pilt</label>
          <input
            type="file"
            accept="image/*"
            class="w-full rounded-2xl border border-border/80 bg-background px-4 py-3 text-sm text-foreground file:mr-3 file:rounded-xl file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:font-medium file:text-primary hover:file:bg-primary/20"
            @change="onImageChange"
          />
          <div v-if="newBedImagePreview || existingImageUrl" class="mt-3 overflow-hidden rounded-[1.5rem] border border-border/80 shadow-sm">
            <div class="h-32 w-full bg-cover bg-center" :style="{ backgroundImage: `url('${newBedImagePreview ?? existingImageUrl}')` }" />
          </div>
          <p v-if="form.errors.image" class="mt-1 text-sm text-red-600">{{ form.errors.image }}</p>
        </div>
      </div>

      <section class="rounded-[1.75rem] border border-border/80 bg-linear-to-br from-background via-background to-primary/[0.03] p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-primary/8 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">
              <span class="material-symbols-outlined text-sm">grid_view</span>
              Peenra kaart
            </div>
            <p class="mt-3 text-lg font-semibold tracking-tight text-foreground">Kujunda peenra kuju ruutudest</p>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">
              Ehita peenar ruutudest. Telefonis kasuta ruudustiku all olevaid suunanoole nuppe.
            </p>
          </div>
          <div class="flex items-center gap-2 rounded-2xl border border-border/80 bg-card/85 px-3 py-2 shadow-xs">
            <span class="material-symbols-outlined rounded-xl bg-primary/10 p-2 text-primary">grass</span>
            <div class="text-xs text-muted-foreground">
              Aktiivseid ruute
              <div class="text-base font-semibold text-foreground">{{ activeCells.length }}</div>
            </div>
          </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
          <div class="space-y-4">


          <div
            ref="gridScroller"
            class="overflow-x-auto rounded-[1.75rem] border border-border/80 bg-[radial-gradient(circle_at_top,_rgba(162,191,130,0.12),_transparent_40%),linear-gradient(180deg,rgba(255,255,255,0.96),rgba(249,246,239,0.96))] p-3 shadow-sm sm:p-4"
          >
            <div class="inline-grid gap-2.5" :style="{ gridTemplateColumns: `repeat(${displayColumns.length}, minmax(0, 3.2rem))` }">
              <template v-for="y in displayRows" :key="`row-${y}`">
                <template v-for="x in displayColumns" :key="`cell-${x}-${y}`">
                  <div
                    class="relative h-13 w-13 sm:h-14 sm:w-14"
                    :ref="(el) => {
                      const cell = getCellAt(x, y);
                      if (cell) setSelectedCellElement(el, cell.id);
                    }"
                  >
                    <template v-if="getCellAt(x, y)">
                      <button
                        type="button"
                        class="relative h-full w-full overflow-hidden rounded-[1.15rem] border transition duration-200"
                        :class="[
                          selectedCell?.id === getCellAt(x, y)?.id
                            ? 'border-emerald-500/70 bg-emerald-100/80 ring-2 ring-emerald-400/40 ring-offset-2 ring-offset-[#f6f1e7] shadow-md'
                            : 'border-amber-900/15 bg-[linear-gradient(180deg,rgba(141,97,61,0.92),rgba(108,73,46,0.98))] shadow-sm hover:-translate-y-0.5 hover:shadow-md',
                          highlightedCellId === getCellAt(x, y)?.id
                            ? 'scale-[1.04] shadow-lg shadow-primary/20'
                            : '',
                        ]"
                        @click="getCellAt(x, y) && selectCell(getCellAt(x, y)!)"
                      >
                        <div class="absolute inset-x-0 top-0 h-1/2 bg-linear-to-b from-white/12 to-transparent" />
                        <div v-if="getCellAt(x, y)?.plants.length" class="absolute inset-0 bg-linear-to-t from-black/45 via-black/15 to-transparent" />
                        <div class="relative z-10 flex h-full w-full flex-col items-center justify-center px-1 text-center">
                          <span
                            class="material-symbols-outlined text-lg"
                            :class="getCellAt(x, y)?.plants.length ? 'text-white' : selectedCell?.id === getCellAt(x, y)?.id ? 'text-emerald-700' : 'text-amber-50/90'"
                          >
                            {{ getCellAt(x, y)?.plants.length ? 'eco' : 'grid_view' }}
                          </span>
                          <span
                            v-if="getCellAt(x, y)?.plants.length"
                            class="mt-1 line-clamp-2 text-[9px] font-semibold leading-tight text-white"
                          >
                            {{ getPlantNames(getCellAt(x, y)!).join(', ') }}
                          </span>
                        </div>
                        <span
                          class="absolute right-1.5 bottom-1.5 rounded-full px-1.5 py-0.5 text-[9px] font-semibold"
                          :class="selectedCell?.id === getCellAt(x, y)?.id ? 'bg-white/95 text-emerald-700' : 'bg-black/15 text-amber-50/85'"
                        >
                          {{ x }},{{ y }}
                        </span>
                      </button>

                      <template v-if="selectedCell?.id === getCellAt(x, y)?.id">
                        <button
                          type="button"
                          class="absolute -top-3 left-1/2 z-20 hidden h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10 sm:flex"
                          @click.stop="handleDirectionalAdd('up')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_upward</span>
                        </button>
                        <button
                          type="button"
                          class="absolute -bottom-3 left-1/2 z-20 hidden h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10 sm:flex"
                          @click.stop="handleDirectionalAdd('down')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_downward</span>
                        </button>
                        <button
                          type="button"
                          class="absolute top-1/2 -left-3 z-20 hidden h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10 sm:flex"
                          @click.stop="handleDirectionalAdd('left')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_back</span>
                        </button>
                        <button
                          type="button"
                          class="absolute top-1/2 -right-3 z-20 hidden h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10 sm:flex"
                          @click.stop="handleDirectionalAdd('right')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </button>
                      </template>
                    </template>

                    <button
                      v-else
                      type="button"
                      class="h-full w-full rounded-[1.15rem] border border-dashed border-emerald-900/15 bg-white/50 text-muted-foreground transition hover:border-primary/30 hover:bg-primary/6 hover:text-primary"
                      @click="addCellFromPlaceholder(x, y)"
                    >
                      <span class="material-symbols-outlined text-lg">add</span>
                    </button>
                  </div>
                </template>
              </template>
            </div>
          </div>

            <div class="rounded-[1.5rem] border border-border/80 bg-card/95 p-4 shadow-sm sm:hidden">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-foreground">Valitud ruut</p>
                  <p class="mt-1 text-sm text-muted-foreground">{{ selectedCellLabel }}</p>
                </div>
                <div class="rounded-xl bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary shadow-xs">
                  {{ selectedPlantCount }} taimekirjet
                </div>
              </div>

              <div class="mt-4 grid place-items-center">
                <button
                  type="button"
                  class="mb-2 flex h-11 w-11 items-center justify-center rounded-full border border-primary/30 bg-background text-primary shadow-sm hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="!selectedCell"
                  @click="handleDirectionalAdd('up')"
                >
                  <span class="material-symbols-outlined text-base">arrow_upward</span>
                </button>

                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="flex h-11 w-11 items-center justify-center rounded-full border border-primary/30 bg-background text-primary shadow-sm hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!selectedCell"
                    @click="handleDirectionalAdd('left')"
                  >
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                  </button>

                  <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-primary shadow-xs">
                    <span class="material-symbols-outlined text-base">grid_view</span>
                  </div>

                  <button
                    type="button"
                    class="flex h-11 w-11 items-center justify-center rounded-full border border-primary/30 bg-background text-primary shadow-sm hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!selectedCell"
                    @click="handleDirectionalAdd('right')"
                  >
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                  </button>
                </div>

                <button
                  type="button"
                  class="mt-2 flex h-11 w-11 items-center justify-center rounded-full border border-primary/30 bg-background text-primary shadow-sm hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="!selectedCell"
                  @click="handleDirectionalAdd('down')"
                >
                  <span class="material-symbols-outlined text-base">arrow_downward</span>
                </button>
              </div>

              <div v-if="selectedHasPlants" class="mt-4 rounded-2xl border border-primary/20 bg-primary/8 p-3 text-sm text-primary">
                Valitud ruudus on taim(ed). Seda ruutu ei saa eemaldada enne, kui taimed on ümber paigutatud.
              </div>

              <button
                type="button"
                class="mt-4 w-full rounded-2xl border px-3 py-3 text-sm font-medium"
                :class="selectedHasPlants || activeCells.length <= 1 ? 'border-border/60 bg-muted/30 text-muted-foreground cursor-not-allowed' : 'border-border bg-background text-foreground hover:bg-muted/50'"
                :disabled="selectedHasPlants || activeCells.length <= 1"
                @click="removeSelectedCell"
              >
                Eemalda valitud ruut
              </button>
            </div>
          </div>

          <aside class="hidden rounded-[1.5rem] border border-border/80 bg-card/95 p-4 shadow-sm lg:block">
            <div class="space-y-4">
            <div class="rounded-[1.35rem] border border-border/80 bg-background/70 p-3">
              <div class="mb-3">
                <p class="text-sm font-semibold text-foreground">Valitud ruudu toimingud</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ selectedCellLabel }}</p>
              </div>
              <div class="mt-3 grid place-items-center">
                <button
                  type="button"
                  class="mb-2 flex h-10 w-10 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                  @click="handleDirectionalAdd('up')"
                >
                  <span class="material-symbols-outlined text-base">arrow_upward</span>
                </button>

                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                    @click="handleDirectionalAdd('left')"
                  >
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                  </button>

                  <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-base">grid_view</span>
                  </div>

                  <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                    @click="handleDirectionalAdd('right')"
                  >
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                  </button>
                </div>

                <button
                  type="button"
                  class="mt-2 flex h-10 w-10 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                  @click="handleDirectionalAdd('down')"
                >
                  <span class="material-symbols-outlined text-base">arrow_downward</span>
                </button>
              </div>
            </div>

            <div class="rounded-[1.35rem] border border-border/80 bg-background/70 p-3">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-foreground">Taimed ruudus</p>
                <span class="rounded-xl bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary shadow-xs">
                  {{ selectedPlantCount }}
                </span>
              </div>
              <p class="mt-2 text-sm text-muted-foreground">
                {{ selectedHasPlants ? 'Valitud ruudus on juba taim(ed).' : 'Ruudus ei ole veel taimi.' }}
              </p>
            </div>

            <div v-if="selectedHasPlants" class="rounded-2xl border border-primary/20 bg-primary/8 p-3 text-sm text-primary">
              Valitud ruudus on taim(ed). Seda ruutu ei saa eemaldada enne, kui taimed on ümber paigutatud.
            </div>

            <button
              type="button"
              class="w-full rounded-2xl border px-3 py-2.5 text-sm font-medium"
              :class="selectedHasPlants || activeCells.length <= 1 ? 'border-border/60 bg-muted/30 text-muted-foreground cursor-not-allowed' : 'border-border bg-background text-foreground hover:bg-muted/50'"
              :disabled="selectedHasPlants || activeCells.length <= 1"
              @click="removeSelectedCell"
            >
              Eemalda valitud ruut
            </button>
            </div>
          </aside>
        </div>

      </section>

      <div class="sticky bottom-3 z-10 -mx-1 mt-1 rounded-[1.75rem] border border-border/70 bg-card/95 p-3 shadow-soft backdrop-blur sm:static sm:mx-0 sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none sm:backdrop-blur-0">
      <div class="flex flex-col gap-2 sm:flex-row">
        <button type="submit" class="btn-primary shadow-sm" :disabled="form.processing">
          {{ mode === 'edit' ? 'Salvesta muudatused' : 'Loo peenar' }}
        </button>
        <button type="button" class="btn-primary-outline bg-background/80" :disabled="form.processing" @click="resetForm">
          {{ mode === 'edit' ? 'Tagasi' : 'Tühista' }}
        </button>
      </div>
      </div>
    </form>
  </section>
</template>
