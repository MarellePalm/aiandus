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

const newBedName = ref(props.mode === 'edit' ? props.bed?.name ?? '' : '');
const newBedLocation = ref(props.mode === 'edit' ? props.bed?.location ?? '' : '');
const existingImageUrl = ref(props.mode === 'edit' ? props.bed?.image_url ?? null : null);
const newBedImage = ref<File | null>(null);
const newBedImagePreview = ref<string | null>(null);
const HIDDEN_FILLER = -2;

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
    r.length < maxLen ? [...r, ...Array.from({ length: maxLen - r.length }, () => HIDDEN_FILLER)] : [...r],
  );
}

function compactLayout() {
  if (!newBedLayout.value.length) {
    newBedLayout.value = [[1]];
    return;
  }

  let layout = newBedLayout.value.map((r) => [...r]);

  const isEmptyRow = (row: number[]) => row.every((v) => v === 0) || row.every((v) => v === HIDDEN_FILLER);
  const isEmptyCol = (idx: number) => {
    const col = layout.map((r) => r[idx] ?? HIDDEN_FILLER);
    return col.every((v) => v === 0) || col.every((v) => v === HIDDEN_FILLER);
  };

  while (layout.length > 1 && isEmptyRow(layout[0])) layout.shift();
  while (layout.length > 1 && isEmptyRow(layout[layout.length - 1])) layout.pop();

  const colCount = () => Math.max(...layout.map((r) => r.length), 1);
  while (colCount() > 1 && isEmptyCol(0)) layout = layout.map((r) => r.slice(1));
  while (colCount() > 1 && isEmptyCol(colCount() - 1)) layout = layout.map((r) => r.slice(0, -1));

  if (!layout.length || !layout.some((row) => row.length)) layout = [[1]];

  newBedLayout.value = layout;
  normalizeRectLayout();
}

function expandUp(row: number, col: number) {
  normalizeRectLayout();
  const cols = newBedLayout.value[0]?.length ?? 1;
  const safeRow = Math.max(0, Math.min(row, newBedLayout.value.length - 1));
  const safeCol = Math.max(0, Math.min(col, cols - 1));

  if (safeRow > 0 && cellValueAt(safeRow - 1, safeCol) <= -1) {
    newBedLayout.value[safeRow - 1][safeCol] = 0;
    return;
  }

  const newRow = Array.from({ length: cols }, (_, i) => (i === safeCol ? 0 : HIDDEN_FILLER));
  newBedLayout.value = [newRow, ...newBedLayout.value];
}

function expandDown(row: number, col: number) {
  normalizeRectLayout();
  const safeRow = Math.max(0, Math.min(row, newBedLayout.value.length - 1));
  const cols = newBedLayout.value[0]?.length ?? 1;
  const safeCol = Math.max(0, Math.min(col, cols - 1));

  if (safeRow < newBedLayout.value.length - 1 && cellValueAt(safeRow + 1, safeCol) <= -1) {
    newBedLayout.value[safeRow + 1][safeCol] = 0;
    return;
  }

  const newRow = Array.from({ length: cols }, (_, i) => (i === safeCol ? 0 : HIDDEN_FILLER));
  newBedLayout.value = [...newBedLayout.value, newRow];
}

function expandLeft(row: number, col: number) {
  normalizeRectLayout();
  const safeRow = Math.max(0, Math.min(row, newBedLayout.value.length - 1));
  const rowLen = newBedLayout.value[safeRow]?.length ?? 1;
  const safeCol = Math.max(0, Math.min(col, rowLen - 1));

  if (safeCol > 0 && cellValueAt(safeRow, safeCol - 1) <= -1) {
    newBedLayout.value[safeRow][safeCol - 1] = 0;
    return;
  }

  newBedLayout.value[safeRow] = [0, ...newBedLayout.value[safeRow]];
  normalizeRectLayout();
}

function expandRight(row: number, col: number) {
  normalizeRectLayout();
  const safeRow = Math.max(0, Math.min(row, newBedLayout.value.length - 1));
  const rowLen = newBedLayout.value[safeRow]?.length ?? 1;
  const safeCol = Math.max(0, Math.min(col, rowLen - 1));

  if (safeCol < rowLen - 1 && cellValueAt(safeRow, safeCol + 1) <= -1) {
    newBedLayout.value[safeRow][safeCol + 1] = 0;
    return;
  }

  newBedLayout.value[safeRow] = [...newBedLayout.value[safeRow], 0];
  normalizeRectLayout();
}

function insertColumnAt(index: number, row: number) {
  normalizeRectLayout();
  const cols = newBedLayout.value[0]?.length ?? 1;
  const safe = Math.max(0, Math.min(index, cols));
  const safeRow = Math.max(0, Math.min(row, newBedLayout.value.length - 1));
  newBedLayout.value = newBedLayout.value.map((row) => [
    ...row.slice(0, safe),
    HIDDEN_FILLER,
    ...row.slice(safe),
  ]);
  newBedLayout.value[safeRow][safe] = 0;
}

function insertRowAt(index: number, col: number) {
  normalizeRectLayout();
  const rows = newBedLayout.value.length;
  const cols = newBedLayout.value[0]?.length ?? 1;
  const safe = Math.max(0, Math.min(index, rows));
  const safeCol = Math.max(0, Math.min(col, cols - 1));
  const newRow = Array.from({ length: cols }, (_, i) => (i === safeCol ? 0 : HIDDEN_FILLER));
  newBedLayout.value = [
    ...newBedLayout.value.slice(0, safe),
    newRow,
    ...newBedLayout.value.slice(safe),
  ];
}

function canExpandTop(row: number, col: number): boolean {
  if (cellValueAt(row, col) <= -1) return false;
  for (let r = 0; r < newBedLayout.value.length; r += 1) {
    if (cellValueAt(r, col) > -1) return row === r;
  }
  return row === 0;
}

function canExpandBottom(row: number, col: number): boolean {
  if (cellValueAt(row, col) <= -1) return false;
  for (let r = newBedLayout.value.length - 1; r >= 0; r -= 1) {
    if (cellValueAt(r, col) > -1) return row === r;
  }
  return row === newBedLayout.value.length - 1;
}

function canExpandLeft(row: number, col: number): boolean {
  if (cellValueAt(row, col) <= -1) return false;
  return cellValueAt(row, col - 1) <= -1;
}

function canExpandRight(row: number, col: number): boolean {
  if (cellValueAt(row, col) <= -1) return false;
  return cellValueAt(row, col + 1) <= -1;
}

function canInsertBetweenRight(col: number): boolean {
  return col < (newBedLayout.value[0]?.length ?? 1) - 1;
}

function canInsertBetweenBottom(row: number): boolean {
  return row < newBedLayout.value.length - 1;
}

function cellValueAt(row: number, col: number): number {
  return newBedLayout.value[row]?.[col] ?? -1;
}

function canShowArrowAt(row: number, col: number): boolean {
  return cellValueAt(row, col) > -1;
}

function canInsertBetweenRightAt(row: number, col: number): boolean {
  if (!canInsertBetweenRight(col)) return false;
  return cellValueAt(row, col) > -1 && cellValueAt(row, col + 1) > -1;
}

function canInsertBetweenBottomAt(row: number, col: number): boolean {
  if (!canInsertBetweenBottom(row)) return false;
  return cellValueAt(row, col) > -1 && cellValueAt(row + 1, col) > -1;
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
  const r = displayR;
  const c = displayC;
  if (r < 0 || c < 0) return;
  if (r >= newBedLayout.value.length) return;
  if (c >= (newBedLayout.value[0]?.length ?? 0)) return;
  newBedLayout.value[r][c] = value;
  compactLayout();
}

function plantAtInternal(internalR: number, internalC: number) {
  const key = `${internalR},${internalC}`;
  return props.bed?.plants?.find((p) => p.position_in_bed === key);
}

const displayLayout = computed<number[][]>(() => {
  return newBedLayout.value;
});

// Hoia algne layout ristkülikuna, kuid väldi reaktiivse state muutmist computed sees.
normalizeRectLayout();
compactLayout();

// -----------------------------
// Form actions
// -----------------------------
function resetForm() {
  router.get('/map', { preserveScroll: true });
}

function submit() {
  if (!newBedName.value.trim() || !hasAnyPlantCell()) return;

  const payload = {
    name: newBedName.value.trim(),
    location: newBedLocation.value.trim() || undefined,
    layout: newBedLayout.value.map((row) => row.map((cell) => (cell === HIDDEN_FILLER ? -1 : cell))),
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

const addTileClass =
  'w-12 h-12 rounded-lg border-2 border-dashed border-primary/55 bg-primary/10 text-primary flex items-center justify-center hover:bg-primary/18 transition';

</script>

<template>
  <section class="mb-8">
    <form
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
          placeholder="nt Tagaaed, esiuksest paremal"
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
            <span class="inline-block w-5 h-5 rounded-md border-2 border-primary/60 bg-primary/15" />
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
      <div class="space-y-2 flex flex-col items-center">
        <div class="flex w-full flex-col items-center">
          <div
            class="mx-auto grid gap-2 justify-center"
            :style="{ gridTemplateColumns: `repeat(${displayLayout[0]?.length ?? 1}, 3rem)` }"
          >
            <template v-for="(row, dri) in displayLayout" :key="dri">
              <template v-for="(cell, dci) in row" :key="`${dri}-${dci}`">
                <div class="group/cell relative w-12 h-12">
                  <button
                    v-if="canExpandTop(dri, dci) && canShowArrowAt(dri, dci)"
                    type="button"
                    class="pointer-events-auto absolute -top-3 left-1/2 z-20 flex h-5 w-5 -translate-x-1/2 items-center justify-center rounded-full border border-black/45 bg-background text-black opacity-100 shadow-sm transition sm:pointer-events-none sm:opacity-0 sm:group-hover/cell:pointer-events-auto sm:group-hover/cell:opacity-100 sm:group-focus-within/cell:pointer-events-auto sm:group-focus-within/cell:opacity-100 hover:bg-black/10"
                    title="Laienda üles"
                    @click.stop="expandUp(dri, dci)"
                  >
                    <span class="material-symbols-outlined text-[11px] leading-none">keyboard_arrow_up</span>
                  </button>
                  <button
                    v-if="canExpandBottom(dri, dci) && canShowArrowAt(dri, dci)"
                    type="button"
                    class="pointer-events-auto absolute -bottom-3 left-1/2 z-20 flex h-5 w-5 -translate-x-1/2 items-center justify-center rounded-full border border-black/45 bg-background text-black opacity-100 shadow-sm transition sm:pointer-events-none sm:opacity-0 sm:group-hover/cell:pointer-events-auto sm:group-hover/cell:opacity-100 sm:group-focus-within/cell:pointer-events-auto sm:group-focus-within/cell:opacity-100 hover:bg-black/10"
                    title="Laienda alla"
                    @click.stop="expandDown(dri, dci)"
                  >
                    <span class="material-symbols-outlined text-[11px] leading-none">keyboard_arrow_down</span>
                  </button>
                  <button
                    v-if="canExpandLeft(dri, dci) && canShowArrowAt(dri, dci)"
                    type="button"
                    class="pointer-events-auto absolute -left-3 top-1/2 z-20 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border border-black/45 bg-background text-black opacity-100 shadow-sm transition sm:pointer-events-none sm:opacity-0 sm:group-hover/cell:pointer-events-auto sm:group-hover/cell:opacity-100 sm:group-focus-within/cell:pointer-events-auto sm:group-focus-within/cell:opacity-100 hover:bg-black/10"
                    title="Laienda vasakule"
                    @click.stop="expandLeft(dri, dci)"
                  >
                    <span class="material-symbols-outlined text-[11px] leading-none">keyboard_arrow_left</span>
                  </button>
                  <button
                    v-if="canExpandRight(dri, dci) && canShowArrowAt(dri, dci)"
                    type="button"
                    class="pointer-events-auto absolute -right-3 top-1/2 z-20 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border border-black/45 bg-background text-black opacity-100 shadow-sm transition sm:pointer-events-none sm:opacity-0 sm:group-hover/cell:pointer-events-auto sm:group-hover/cell:opacity-100 sm:group-focus-within/cell:pointer-events-auto sm:group-focus-within/cell:opacity-100 hover:bg-black/10"
                    title="Laienda paremale"
                    @click.stop="expandRight(dri, dci)"
                  >
                    <span class="material-symbols-outlined text-[11px] leading-none">keyboard_arrow_right</span>
                  </button>

                  <button
                    v-if="canInsertBetweenRightAt(dri, dci)"
                    type="button"
                    class="pointer-events-auto absolute -right-3 top-[28%] z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-md border border-black/45 bg-background text-black opacity-100 shadow-sm transition sm:pointer-events-none sm:opacity-0 sm:group-hover/cell:pointer-events-auto sm:group-hover/cell:opacity-100 sm:group-focus-within/cell:pointer-events-auto sm:group-focus-within/cell:opacity-100 hover:bg-black/10"
                    title="Lisa veerg kahe ruudu vahele"
                    @click.stop="insertColumnAt(dci + 1, dri)"
                  >
                    <span class="material-symbols-outlined text-[10px] leading-none">add</span>
                  </button>
                  <button
                    v-if="canInsertBetweenBottomAt(dri, dci)"
                    type="button"
                    class="pointer-events-auto absolute -bottom-3 left-[28%] z-10 flex h-5 w-5 -translate-x-1/2 items-center justify-center rounded-md border border-black/45 bg-background text-black opacity-100 shadow-sm transition sm:pointer-events-none sm:opacity-0 sm:group-hover/cell:pointer-events-auto sm:group-hover/cell:opacity-100 sm:group-focus-within/cell:pointer-events-auto sm:group-focus-within/cell:opacity-100 hover:bg-black/10"
                    title="Lisa rida kahe ruudu vahele"
                    @click.stop="insertRowAt(dri + 1, dci)"
                  >
                    <span class="material-symbols-outlined text-[10px] leading-none">add</span>
                  </button>

                  <!-- 1 = peenraruut -->
                  <button
                    v-if="cell === 1"
                    type="button"
                    class="relative w-full h-full overflow-hidden rounded-lg border-2 border-primary/45 bg-primary/10"
                    :title="plantAtInternal(dri, dci) ? `Siin on taim: ${plantAtInternal(dri, dci)?.name}` : 'Peenra ruut'"
                    @click="setInternalCell(dri, dci, activeTool)"
                  >
                <span
                  v-if="!plantAtInternal(dri, dci)"
                  class="material-symbols-outlined absolute inset-0 m-auto h-5 w-5 text-primary/65"
                >
                  add
                </span>
                    <div v-if="plantAtInternal(dri, dci)" class="absolute inset-0 bg-primary/25" />
                    <div
                      v-if="plantAtInternal(dri, dci)?.image_url"
                      class="absolute inset-0 bg-cover bg-center opacity-90"
                      :style="{ backgroundImage: `url('${plantAtInternal(dri, dci)?.image_url}')` }"
                    />
                    <div v-if="plantAtInternal(dri, dci)" class="absolute inset-0 bg-linear-to-t from-black/70 via-black/20 to-transparent" />
                    <span
                      v-if="plantAtInternal(dri, dci)"
                      class="absolute bottom-1 left-1 right-1 truncate text-[10px] font-semibold text-white"
                    >
                      {{ plantAtInternal(dri, dci)?.name }}
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

                  <div
                    v-else-if="cell === HIDDEN_FILLER"
                    class="w-full h-full pointer-events-none opacity-0"
                    aria-hidden="true"
                  />

                  <!-- 0 = tühi / lisatav ruut -->
                  <button
                    v-else
                    type="button"
                    :class="cell === 0 ? addTileClass : 'w-full h-full rounded-lg border border-dashed border-muted-foreground/40 bg-muted/30'"
                    title="Lisa ruut"
                    @click="setInternalCell(dri, dci, activeTool)"
                  >
                    <span class="material-symbols-outlined text-lg leading-none">add</span>
                  </button>
                </div>
              </template>
            </template>
          </div>
        </div>
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