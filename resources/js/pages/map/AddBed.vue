<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(
  defineProps<{
    mode?: 'create' | 'edit';
    bed?: { id: number; name: string; location: string | null; layout?: number[][] | null };
  }>(),
  {
    mode: 'create',
    bed: undefined,
  },
);

function initialLayout(): number[][] {
  const layout = props.bed?.layout;
  if (layout && Array.isArray(layout) && layout.length && layout.some((row) => Array.isArray(row) && row.length)) {
    return layout.map((row) => (Array.isArray(row) ? [...row] : []));
  }
  return [[1]];
}

const showAddBed = ref(true);
const newBedName = ref(props.mode === 'edit' ? props.bed?.name ?? '' : '');
const newBedLocation = ref(props.mode === 'edit' ? props.bed?.location ?? '' : '');
//  1  = peenraruut
//  0  = tühi / määramata
// -1  = vahekäik / kivi / multš (teadlikult mitte-peenar)
const newBedLayout = ref<number[][]>(initialLayout());

// -----------------------------
// Layout helpers
// -----------------------------
function normalizeRectLayout() {
  const maxLen = Math.max(...newBedLayout.value.map((r) => r.length), 1);
  newBedLayout.value = newBedLayout.value.map((r) =>
    r.length < maxLen ? [...r, ...Array.from({ length: maxLen - r.length }, () => 0)] : r,
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
// Counts & validation
// -----------------------------
function hasAnyPlantCell(): boolean {
  return newBedLayout.value.some((row) => row.some((v) => v === 1));
}

function plantCount(): number {
  return newBedLayout.value.flat().filter((v) => v === 1).length;
}

// -----------------------------
// 3-state cell logic
// -----------------------------
type Cell = 1 | 0 | -1;

function cycleCell(cell: number): Cell {
  // 0 -> 1 -> -1 -> 0
  if (cell === 0) return 1;
  if (cell === 1) return -1;
  return 0;
}

const activeTool = ref<Cell>(1);

function setInternalCell(displayR: number, displayC: number, value: Cell) {
  const r = displayR - 1;
  const c = displayC - 1;
  if (r < 0 || c < 0) return;
  if (r >= newBedLayout.value.length) return;
  if (c >= (newBedLayout.value[0]?.length ?? 0)) return;
  newBedLayout.value[r][c] = value;
}

function cycleInternal(displayR: number, displayC: number) {
  const r = displayR - 1;
  const c = displayC - 1;
  if (r < 0 || c < 0) return;
  if (r >= newBedLayout.value.length) return;
  if (c >= (newBedLayout.value[0]?.length ?? 0)) return;
  newBedLayout.value[r][c] = cycleCell(newBedLayout.value[r][c]);
}

// -----------------------------
// Display matrix: add 1-cell margin around real layout
// so "+ cells" can exist without floating/overflow.
// -----------------------------
const displayLayout = computed<number[][]>(() => {
  normalizeRectLayout();

  const cols = newBedLayout.value[0]?.length ?? 1;

  const top = Array.from({ length: cols + 2 }, () => 0);
  const bottom = Array.from({ length: cols + 2 }, () => 0);

  const mid = newBedLayout.value.map((r) => [0, ...r, 0]);

  return [top, ...mid, bottom];
});

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
    // Näitame "+" ruutu iga mitte-tühja (1 või -1) ruudu kõrval
    return inBounds(mat, rr, cc) && mat[rr][cc] !== 0;
  });
}

function isMarginCell(displayR: number, displayC: number): boolean {
  const mat = displayLayout.value;
  const lastR = mat.length - 1;
  const lastC = (mat[0]?.length ?? 1) - 1;
  return displayR === 0 || displayC === 0 || displayR === lastR || displayC === lastC;
}

// When user clicks a "+" in DISPLAY coords,
// we convert to real layout coords, expanding if click was on the margin.
function addAtDisplay(displayR: number, displayC: number) {
  normalizeRectLayout();

  const mat = displayLayout.value;
  const lastR = mat.length - 1;
  const lastC = (mat[0]?.length ?? 1) - 1;

  const onTop = displayR === 0;
  const onBottom = displayR === lastR;
  const onLeft = displayC === 0;
  const onRight = displayC === lastC;

  // Expand first (if clicked on margin)
  if (onTop) addRowTop();
  if (onBottom) addRowBottom();
  if (onLeft) addColumnLeft();
  if (onRight) addColumnRight();

  // Map display -> internal after expansions
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

  newBedLayout.value[internalR][internalC] = 1;
}

// -----------------------------
// Form actions
// -----------------------------
function resetForm() {
  if (props.mode !== 'create') {
    if (window.history.length > 1) window.history.back();
    else router.get('/map');
    return;
  }
  newBedName.value = '';
  newBedLocation.value = '';
  newBedLayout.value = [[1]];
  showAddBed.value = false;
}

function openForm() {
  if (props.mode !== 'create') return;
  newBedName.value = '';
  newBedLocation.value = '';
  newBedLayout.value = [[1]];
  showAddBed.value = true;
}

function submit() {
  if (!newBedName.value.trim() || !hasAnyPlantCell()) return;

  const payload = {
    name: newBedName.value.trim(),
    location: newBedLocation.value.trim() || undefined,
    layout: newBedLayout.value,
  };

  if (props.mode === 'edit' && props.bed) {
    router.put(`/beds/${props.bed.id}`, payload, {
      preserveScroll: true,
    });
  } else {
    router.post(
      '/beds',
      payload,
      {
        preserveScroll: true,
        onSuccess: () => resetForm(),
      },
    );
  }
}

// Style for "+ tile" (same as your existing add square)
const addTileClass =
  'w-12 h-12 rounded-lg border-2 border-dashed border-primary bg-primary/10 text-primary flex items-center justify-center shrink-0 hover:bg-primary/20 transition';
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
      class="rounded-xl border border-border bg-card p-4 space-y-3"
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
        <!-- Grid (real layout + 1-cell margin) -->
        <div class="space-y-2 mb-3">
          <div
            v-for="(row, dri) in displayLayout"
            :key="dri"
            class="flex flex-wrap items-center gap-2"
          >
            <template v-for="(cell, dci) in row" :key="dci">
              <div class="relative w-12 h-12 shrink-0">
                <!-- 1 = peenraruut -->
                <button
                  v-if="cell === 1"
                  type="button"
                  class="w-full h-full rounded-lg border-2 border-amber-300/70 dark:border-amber-700/60 bg-amber-100/50 dark:bg-amber-900/20"
                  title="Peenra ruut"
                  @click="setInternalCell(dri, dci, activeTool)"
                />

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

                <!-- 0 and adjacent to plant => show + tile (always visible) -->
                <button
                  v-else-if="cell === 0 && isAdjacentToPlant(dri, dci)"
                  type="button"
                  :class="addTileClass"
                  :title="isMarginCell(dri, dci) ? 'Lisa (laiendab peenart)' : 'Lisa ruut'"
                  @click="addAtDisplay(dri, dci)"
                >
                  <span class="material-symbols-outlined text-2xl">add</span>
                </button>

                <!-- 0 (tühi) -->
                <button
                  v-else
                  type="button"
                  class="w-full h-full rounded-lg border border-dashed border-muted-foreground/40 bg-muted/30"
                  title="Tühi ala"
                  @click="setInternalCell(dri, dci, activeTool)"
                />
              </div>
            </template>
          </div>
        </div>

        <div class="flex gap-2">
          <button type="submit" class="btn-primary" :disabled="!hasAnyPlantCell()">
            {{ mode === 'edit' ? 'Salvesta muudatused' : 'Loo peenar' }}
          </button>
          <button type="button" class="btn-primary-outline" @click="resetForm">
            {{ mode === 'edit' ? 'Tagasi' : 'Tühista' }}
          </button>
        </div>
      </div>
    </form>
  </section>
</template>