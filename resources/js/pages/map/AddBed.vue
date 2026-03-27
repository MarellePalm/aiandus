<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
  defineProps<{
    mode?: 'create' | 'edit';
    bed?: {
      id: number;
      name: string;
      location: string | null;
      image_url?: string | null;
      layout?: number[][] | null;
      plants?: { id: number; name: string; image_url: string | null; position_in_bed: string | null }[];
    };
  }>(),
  {
    mode: 'create',
    bed: undefined,
  },
);

function initialLayout(): number[][] {
  const layout = props.bed?.layout;
  if (layout && Array.isArray(layout) && layout.length && layout.some((row) => Array.isArray(row) && row.length)) {
    return layout.map((row) =>
      Array.isArray(row)
        ? row.map((cell) => {
            const n = Number(cell);
            if (n === 1 || n === 0 || n === -1) return n;
            return 0;
          })
        : [],
    );
  }
  return [[1]];
}

const showAddBed = ref(true);
const newBedName = ref(props.mode === 'edit' ? props.bed?.name ?? '' : '');
const newBedLocation = ref(props.mode === 'edit' ? props.bed?.location ?? '' : '');
const existingImageUrl = ref(props.mode === 'edit' ? props.bed?.image_url ?? null : null);
const newBedImage = ref<File | null>(null);
const newBedImagePreview = ref<string | null>(null);

//  1  = peenraruut
//  0  = tühi / määramata
// -1  = vahekäik / kivi / multš (teadlikult mitte-peenar)
const newBedLayout = ref<number[][]>(initialLayout());

// -----------------------------
// Layout helpers
// -----------------------------
function normalizeRectLayout() {
  const maxLen = Math.max(...newBedLayout.value.map((r) => r.length), 1);
  const needsNormalize = newBedLayout.value.some((r) => r.length !== maxLen);
  if (!needsNormalize) return;

  newBedLayout.value = newBedLayout.value.map((r) =>
    r.length < maxLen ? [...r, ...Array.from({ length: maxLen - r.length }, () => 0)] : [...r],
  );
}

function addColumnLeft() {
  normalizeRectLayout();
  newBedLayout.value = newBedLayout.value.map((r) => [0, ...r]);
}

function addColumnRight() {
  normalizeRectLayout();
  newBedLayout.value = newBedLayout.value.map((r) => [...r, 0]);
}

function addRowTop() {
  normalizeRectLayout();
  const len = newBedLayout.value[0]?.length ?? 1;
  newBedLayout.value = [Array.from({ length: len }, () => 0), ...newBedLayout.value];
}

function addRowBottom() {
  normalizeRectLayout();
  const len = newBedLayout.value[0]?.length ?? 1;
  newBedLayout.value = [...newBedLayout.value, Array.from({ length: len }, () => 0)];
}

// -----------------------------
// Validation
// -----------------------------
function hasAnyPlantCell(): boolean {
  return newBedLayout.value.some((row) => row.some((v) => v === 1));
}

// -----------------------------
// Tooling
// -----------------------------
type Cell = 1 | 0 | -1;
const activeTool = ref<Cell>(1);

function setInternalCell(displayR: number, displayC: number, value: Cell) {
  const r = displayR - 1;
  const c = displayC - 1;
  if (r < 0 || c < 0) return;
  if (r >= newBedLayout.value.length) return;
  if (c >= (newBedLayout.value[0]?.length ?? 0)) return;
  newBedLayout.value[r][c] = value;
}

function plantAtInternal(internalR: number, internalC: number) {
  const key = `${internalR},${internalC}`;
  return props.bed?.plants?.find((p) => p.position_in_bed === key);
}

// -----------------------------
// Display matrix: add 1-cell margin around real layout
// -----------------------------
const displayLayout = computed<number[][]>(() => {
  const cols = newBedLayout.value[0]?.length ?? 1;
  const top = Array.from({ length: cols + 2 }, () => 0);
  const bottom = Array.from({ length: cols + 2 }, () => 0);
  const mid = newBedLayout.value.map((r) => [0, ...r, 0]);

  return [top, ...mid, bottom];
});

// Hoia algne layout ristkülikuna, kuid väldi reaktiivse state muutmist computed sees.
normalizeRectLayout();

function inBounds(mat: number[][], r: number, c: number) {
  return r >= 0 && r < mat.length && c >= 0 && c < (mat[0]?.length ?? 0);
}

function isAdjacentToPlant(displayR: number, displayC: number): boolean {
  const mat = displayLayout.value;
  const dirs = [
    [-1, 0],
    [1, 0],
    [0, -1],
    [0, 1],
  ] as const;

  return dirs.some(([dr, dc]) => {
    const rr = displayR + dr;
    const cc = displayC + dc;
    // "+" ruut iga mitte-tühja (1 või -1) ruudu kõrval
    return inBounds(mat, rr, cc) && mat[rr][cc] !== 0;
  });
}

function isMarginCell(displayR: number, displayC: number): boolean {
  const mat = displayLayout.value;
  const lastR = mat.length - 1;
  const lastC = (mat[0]?.length ?? 1) - 1;
  return displayR === 0 || displayC === 0 || displayR === lastR || displayC === lastC;
}

function addAtDisplay(displayR: number, displayC: number) {
  normalizeRectLayout();

  const mat = displayLayout.value;
  const lastR = mat.length - 1;
  const lastC = (mat[0]?.length ?? 1) - 1;

  const onTop = displayR === 0;
  const onBottom = displayR === lastR;
  const onLeft = displayC === 0;
  const onRight = displayC === lastC;

  if (onTop) addRowTop();
  if (onBottom) addRowBottom();
  if (onLeft) addColumnLeft();
  if (onRight) addColumnRight();

  let internalR = displayR - 1;
  let internalC = displayC - 1;

  if (onTop) internalR = 0;
  if (onLeft) internalC = 0;
  if (onBottom) internalR = newBedLayout.value.length - 1;
  if (onRight) internalC = (newBedLayout.value[0]?.length ?? 1) - 1;

  if (
    internalR < 0 ||
    internalR >= newBedLayout.value.length ||
    internalC < 0 ||
    internalC >= (newBedLayout.value[0]?.length ?? 0)
  ) {
    return;
  }

  if (activeTool.value === 0) return;
  newBedLayout.value[internalR][internalC] = activeTool.value;
  normalizeRectLayout();
}

// -----------------------------
// Form actions
// -----------------------------
function resetForm() {
  router.get('/map', { preserveScroll: true });
}

function openForm() {
  showAddBed.value = true;
}

function submit() {
  if (!newBedName.value.trim() || !hasAnyPlantCell()) return;

  const payload = {
    name: newBedName.value.trim(),
    location: newBedLocation.value.trim() || undefined,
    layout: newBedLayout.value,
    image: newBedImage.value ?? undefined,
  };

  if (props.mode === 'edit' && props.bed) {
    router.put(`/beds/${props.bed.id}`, payload, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => resetForm(),
    });
  } else {
    router.post('/beds', payload, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => resetForm(),
    });
  }
}

function onImageChange(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0] ?? null;
  newBedImage.value = file;

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
});

const addTileClass = computed(() => {
  if (activeTool.value === -1) {
    // Vahekäigu lisamine (neutraalsem)
    return 'w-12 h-12 rounded-lg border-2 border-dashed border-border bg-muted/40 text-muted-foreground flex items-center justify-center hover:bg-muted/60 transition';
  }

  // Peenra lisamine (default)
  return 'w-12 h-12 rounded-lg border-2 border-dashed border-primary bg-primary/10 text-primary flex items-center justify-center hover:bg-primary/20 transition';
});
</script>

<template>
  <section class="mb-8">
    <button
      v-if="mode === 'create' && !showAddBed"
      type="button"
      class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-3 text-sm font-medium hover:bg-muted"
      @click="openForm"
    >
      <span class="material-symbols-outlined text-lg">add_circle</span>
      Lisa peenar
    </button>

    <form
      v-else
      class="rounded-xl border border-border bg-card p-4 space-y-4"
      @submit.prevent="submit"
    >
      <div>
        <label class="block text-sm font-medium text-foreground mb-1">Peenra nimi</label>
        <input
          v-model="newBedName"
          type="text"
          class="form-input w-full"
          placeholder="nt Kõrvitsapeenar"
          maxlength="120"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-foreground mb-1">
          Asukoht (valikuline)
        </label>
        <input
          v-model="newBedLocation"
          type="text"
          class="form-input w-full"
          placeholder="nt Tagaaed, esiukse paremal"
          maxlength="255"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-foreground mb-1">Peenra pilt (valikuline)</label>
        <input
          type="file"
          accept="image/*"
          class="form-input w-full file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20"
          @change="onImageChange"
        />
        <div v-if="newBedImagePreview || existingImageUrl" class="mt-3">
          <div
            class="h-28 w-full rounded-xl border border-border bg-cover bg-center"
            :style="{ backgroundImage: `url('${newBedImagePreview ?? existingImageUrl}')` }"
          />
        </div>
      </div>

      <!-- AKTIIVNE LEGEND = tööriistavalik -->
      <div class="rounded-xl border border-border bg-secondary/40 p-3">
        <div class="font-semibold text-foreground mb-2">Legend (vajuta, et valida)</div>

        <div class="grid grid-cols-3 gap-2 text-xs">
          <button
            type="button"
            class="flex items-center gap-2 rounded-lg border px-2 py-2"
            :class="activeTool === 1 ? 'border-primary bg-primary/10 text-primary font-semibold' : 'text-muted-foreground'"
            @click="activeTool = 1"
          >
            <span class="inline-block w-5 h-5 rounded-md border-2 border-amber-300/70 bg-amber-100/50" />
            Peenar
          </button>

          <button
            type="button"
            class="flex items-center gap-2 rounded-lg border px-2 py-2"
            :class="activeTool === -1 ? 'border-primary bg-primary/10 text-primary font-semibold' : 'text-muted-foreground'"
            @click="activeTool = -1"
          >
            <span class="inline-block w-5 h-5 rounded-md border border-border bg-muted/60 relative overflow-hidden">
              <span
                class="absolute inset-0 opacity-40 pointer-events-none text-muted-foreground"
                style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 10px 10px;"
              />
            </span>
            Vahekäik/kivi või tühi ruut
          </button>

          <button
            type="button"
            class="flex items-center gap-2 rounded-lg border px-2 py-2"
            :class="activeTool === 0 ? 'border-primary bg-primary/10 text-primary font-semibold' : 'text-muted-foreground'"
            @click="activeTool = 0"
          >
            <span class="inline-flex w-5 h-5 rounded-md border border-dashed border-muted-foreground/40 bg-muted/30 items-center justify-center">
              ⌀
            </span>
            Kustuta
          </button>
        </div>

      </div>

      <!-- GRID (CSS grid, mobiilis stabiilne) -->
      <div
        class="grid gap-2 justify-start"
        :style="{ gridTemplateColumns: `repeat(${displayLayout[0]?.length ?? 1}, 3rem)` }"
      >
        <template v-for="(row, dri) in displayLayout" :key="dri">
          <template v-for="(cell, dci) in row" :key="`${dri}-${dci}`">
            <div class="relative w-12 h-12">
              <!-- 1 = peenraruut -->
              <button
                v-if="cell === 1"
                type="button"
                class="relative w-full h-full overflow-hidden rounded-lg border-2 border-amber-300/70 dark:border-amber-700/60 bg-amber-100/50 dark:bg-amber-900/20"
                :title="plantAtInternal(dri - 1, dci - 1) ? `Siin on taim: ${plantAtInternal(dri - 1, dci - 1)?.name}` : 'Peenra ruut'"
                @click="setInternalCell(dri, dci, activeTool)"
              >
                <div
                  v-if="plantAtInternal(dri - 1, dci - 1)?.image_url"
                  class="absolute inset-0 bg-cover bg-center opacity-90"
                  :style="{ backgroundImage: `url('${plantAtInternal(dri - 1, dci - 1)?.image_url}')` }"
                />
                <div v-if="plantAtInternal(dri - 1, dci - 1)" class="absolute inset-0 bg-linear-to-t from-black/70 via-black/20 to-transparent" />
                <span
                  v-if="plantAtInternal(dri - 1, dci - 1)"
                  class="absolute bottom-1 left-1 right-1 truncate text-[10px] font-semibold text-white"
                >
                  {{ plantAtInternal(dri - 1, dci - 1)?.name }}
                </span>
              </button>

              <!-- -1 = vahekäik/kivi -->
              <button
                v-else-if="cell === -1"
                type="button"
                class="w-full h-full rounded-lg border border-border bg-muted/60 relative overflow-hidden"
                title="Vahekäik / kivi"
                @click="setInternalCell(dri, dci, activeTool)"
              >
                <span
                  class="absolute inset-0 opacity-40 pointer-events-none text-muted-foreground"
                  style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 10px 10px;"
                />
              </button>

              <!-- 0 = tühi / lisatav ruut -->
              <button
                v-else
                type="button"
                :class="cell === 0 && isAdjacentToPlant(dri, dci) ? addTileClass : 'w-full h-full rounded-lg border border-dashed border-muted-foreground/40 bg-muted/30'"
                :title="isMarginCell(dri, dci) ? 'Lisa ruut (laiendab peenart)' : 'Lisa ruut'"
                @click="isMarginCell(dri, dci) ? addAtDisplay(dri, dci) : setInternalCell(dri, dci, activeTool)"
              />
            </div>
          </template>
        </template>
      </div>

      <div class="flex gap-2 pt-2">
        <button type="submit" class="btn-primary" :disabled="!hasAnyPlantCell()">
          {{ mode === 'edit' ? 'Salvesta muudatused' : 'Loo peenar' }}
        </button>
        <button type="button" class="btn-primary-outline" @click="resetForm">
          {{ mode === 'edit' ? 'Tagasi' : 'Tühista' }}
        </button>
      </div>
    </form>
  </section>
</template>