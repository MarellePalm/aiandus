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
const insertionMode = ref<'adjacent' | 'between'>('adjacent');
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

function shiftCells(axis: 'x' | 'y', comparison: (cell: BedCell) => boolean, delta: number) {
  cells.value = cells.value.map((cell) => (comparison(cell) ? { ...cell, [axis]: cell[axis] + delta } : cell));
}

function addRelative(direction: 'up' | 'down' | 'left' | 'right') {
  if (!selectedCell.value) return;
  const cell = selectedCell.value;

  if (direction === 'up') addCellAt(cell.x, cell.y - 1);
  if (direction === 'down') addCellAt(cell.x, cell.y + 1);
  if (direction === 'left') addCellAt(cell.x - 1, cell.y);
  if (direction === 'right') addCellAt(cell.x + 1, cell.y);
}

function addBetween(direction: 'up' | 'down' | 'left' | 'right') {
  if (!selectedCell.value) return;
  const cell = selectedCell.value;

  if (direction === 'right') {
    const targetX = cell.x + 1;
    const occupied = getCellAt(targetX, cell.y);
    if (occupied) {
      shiftCells('x', (item) => item.x > cell.x, 1);
    }
    addCellAt(targetX, cell.y);
  }

  if (direction === 'left') {
    const targetX = cell.x - 1;
    const occupied = getCellAt(targetX, cell.y);
    if (occupied) {
      shiftCells('x', (item) => item.x < cell.x, -1);
    }
    addCellAt(targetX, cell.y);
  }

  if (direction === 'down') {
    const targetY = cell.y + 1;
    const occupied = getCellAt(cell.x, targetY);
    if (occupied) {
      shiftCells('y', (item) => item.y > cell.y, 1);
    }
    addCellAt(cell.x, targetY);
  }

  if (direction === 'up') {
    const targetY = cell.y - 1;
    const occupied = getCellAt(cell.x, targetY);
    if (occupied) {
      shiftCells('y', (item) => item.y < cell.y, -1);
    }
    addCellAt(cell.x, targetY);
  }
}

function handleDirectionalAdd(direction: 'up' | 'down' | 'left' | 'right') {
  if (insertionMode.value === 'between') {
    addBetween(direction);
  } else {
    addRelative(direction);
  }
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
    <form class="rounded-3xl border border-border bg-card p-4 shadow-soft space-y-5 sm:p-5" @submit.prevent="submit">
      <div class="space-y-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-foreground">Peenra nimi</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            placeholder="Nt Ürdipeenar"
            maxlength="120"
          />
          <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-foreground">Asukoht</label>
          <input
            v-model="form.location"
            type="text"
            class="w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            placeholder="Nt Kasvuhoone kõrval"
            maxlength="255"
          />
          <p v-if="form.errors.location" class="mt-1 text-sm text-red-600">{{ form.errors.location }}</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-foreground">Peenra pilt</label>
          <input
            type="file"
            accept="image/*"
            class="w-full rounded-2xl border border-border bg-background px-4 py-3 text-sm text-foreground file:mr-3 file:rounded-xl file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:font-medium file:text-primary hover:file:bg-primary/20"
            @change="onImageChange"
          />
          <div v-if="newBedImagePreview || existingImageUrl" class="mt-3 overflow-hidden rounded-2xl border border-border">
            <div class="h-32 w-full bg-cover bg-center" :style="{ backgroundImage: `url('${newBedImagePreview ?? existingImageUrl}')` }" />
          </div>
          <p v-if="form.errors.image" class="mt-1 text-sm text-red-600">{{ form.errors.image }}</p>
        </div>
      </div>

      <section class="rounded-2xl border border-border bg-background/60 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="text-base font-semibold text-foreground">Peenra kaart</h3>
            <p class="mt-1 text-sm text-muted-foreground">
              Vali ruut ja lisa uus ruut selle kõrvale või vajadusel olemasolevate ruutude vahele.
            </p>
          </div>

          <div class="inline-flex rounded-2xl border border-border bg-card p-1">
            <button
              type="button"
              class="rounded-xl px-3 py-2 text-sm font-medium transition"
              :class="insertionMode === 'adjacent' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-muted/60'"
              @click="insertionMode = 'adjacent'"
            >
              Vabale kohale
            </button>
            <button
              type="button"
              class="rounded-xl px-3 py-2 text-sm font-medium transition"
              :class="insertionMode === 'between' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-muted/60'"
              @click="insertionMode = 'between'"
            >
              Vahele nihutades
            </button>
          </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
          <div ref="gridScroller" class="overflow-x-auto rounded-2xl border border-border bg-card p-3 sm:p-4">
            <div class="inline-grid gap-2" :style="{ gridTemplateColumns: `repeat(${displayColumns.length}, minmax(0, 3.2rem))` }">
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
                        class="relative h-full w-full overflow-hidden rounded-2xl border transition"
                        :class="[
                          selectedCell?.id === getCellAt(x, y)?.id
                            ? 'border-primary bg-primary/12 ring-2 ring-primary/35 ring-offset-2 ring-offset-background shadow-md'
                            : 'border-primary/30 bg-primary/8 hover:bg-primary/14',
                          highlightedCellId === getCellAt(x, y)?.id
                            ? 'scale-[1.04] shadow-lg shadow-primary/20'
                            : '',
                        ]"
                        @click="getCellAt(x, y) && selectCell(getCellAt(x, y)!)"
                      >
                        <div v-if="getCellAt(x, y)?.plants.length" class="absolute inset-0 bg-linear-to-t from-black/45 to-black/10" />
                        <div class="relative z-10 flex h-full w-full flex-col items-center justify-center px-1 text-center">
                          <span class="material-symbols-outlined text-lg text-primary">grid_view</span>
                          <span
                            v-if="getCellAt(x, y)?.plants.length"
                            class="mt-1 line-clamp-2 text-[9px] font-semibold leading-tight text-foreground"
                          >
                            {{ getPlantNames(getCellAt(x, y)!).join(', ') }}
                          </span>
                        </div>
                      </button>

                      <template v-if="selectedCell?.id === getCellAt(x, y)?.id">
                        <button
                          type="button"
                          class="absolute -top-3 left-1/2 z-20 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                          @click.stop="handleDirectionalAdd('up')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_upward</span>
                        </button>
                        <button
                          type="button"
                          class="absolute -bottom-3 left-1/2 z-20 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                          @click.stop="handleDirectionalAdd('down')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_downward</span>
                        </button>
                        <button
                          type="button"
                          class="absolute top-1/2 -left-3 z-20 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                          @click.stop="handleDirectionalAdd('left')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_back</span>
                        </button>
                        <button
                          type="button"
                          class="absolute top-1/2 -right-3 z-20 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-sm hover:bg-primary/10"
                          @click.stop="handleDirectionalAdd('right')"
                        >
                          <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </button>
                      </template>
                    </template>

                    <button
                      v-else
                      type="button"
                      class="h-full w-full rounded-2xl border border-dashed border-border bg-muted/20 text-muted-foreground transition hover:border-primary/30 hover:bg-primary/6 hover:text-primary"
                      @click="addCellFromPlaceholder(x, y)"
                    >
                      <span class="material-symbols-outlined text-lg">add</span>
                    </button>
                  </div>
                </template>
              </template>
            </div>
          </div>

          <aside class="rounded-2xl border border-border bg-card p-4 space-y-4">
            <div class="rounded-2xl border border-border bg-background/70 p-3">
              <p class="text-sm font-semibold text-foreground">Valitud ruudu toimingud</p>
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

            <div class="rounded-2xl bg-primary/8 p-3 text-sm text-primary">
              <p>Aktiivseid ruute kokku: <strong>{{ activeCells.length }}</strong></p>
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
          </aside>
        </div>

        <p v-if="form.errors.cells" class="mt-3 text-sm text-red-600">{{ form.errors.cells }}</p>
      </section>

      <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn-primary" :disabled="form.processing">
          {{ mode === 'edit' ? 'Salvesta muudatused' : 'Loo peenar' }}
        </button>
        <button type="button" class="btn-primary-outline" :disabled="form.processing" @click="resetForm">
          {{ mode === 'edit' ? 'Tagasi' : 'Tühista' }}
        </button>
      </div>
    </form>
  </section>
</template>
