<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

type PlantInBed = { id: number; name: string; image_url: string | null; position_in_bed: string | null };
type Bed = { id: number; name: string; location: string | null; rows: number; columns: number; layout?: number[][] | null; plants: PlantInBed[] };
type PlantWithoutBed = { id: number; name: string; image_url: string | null };

const props = defineProps<{
  beds: Bed[];
  plantsWithoutBed: PlantWithoutBed[];
}>();

const breadcrumbs = [{ title: 'Aiaplaan', href: '/map' }];

const newBedName = ref('');
const newBedLocation = ref('');
/** Uue või muudetava peenra kujundus: 1 = peenra ruut, 0 = vahekäik */
const newBedLayout = ref<number[][]>([[1]]);
const showAddBed = ref(false);
/** Kui määratud, olemegi muutmise režiimis (vormi sisu salvestatakse selle peenra id alla) */
const editModeBedId = ref<number | null>(null);
/** Kokkupandud peenrate id-d – need näidatakse ainult pealkirjana */
const collapsedBedIds = ref<Set<number>>(new Set());

function isBedExpanded(bedId: number): boolean {
  return !collapsedBedIds.value.has(bedId);
}
function toggleBed(bedId: number) {
  const next = new Set(collapsedBedIds.value);
  if (next.has(bedId)) next.delete(bedId);
  else next.add(bedId);
  collapsedBedIds.value = next;
}

const assignPlantBedId = ref<Record<number, number>>({});
const assignPlantPosition = ref<Record<number, string>>({});

/** Ruudu koordinaatide järgi taim (position_in_bed = "row,col") */
function plantAt(bed: Bed, row: number, col: number): PlantInBed | undefined {
  const key = `${row},${col}`;
  return bed.plants.find((p) => p.position_in_bed === key);
}

/** Taimed, millel on vabatekstiline asukoht (ei mahu ruudustikku) */
function plantsWithoutCell(bed: Bed): PlantInBed[] {
  return bed.plants.filter((p) => !p.position_in_bed || !/^\d+,\d+$/.test(p.position_in_bed));
}

function range(n: number): number[] {
  return Array.from({ length: n }, (_, i) => i);
}

/** Peenra ruudustik: layout kui on, muidu rows×columns täisruudud */
function getBedLayout(bed: Bed): number[][] {
  const L = bed.layout;
  if (L && Array.isArray(L) && L.length > 0 && L.some((row) => Array.isArray(row) && row.length > 0)) {
    return L as number[][];
  }
  const r = bed.rows || 1;
  const c = bed.columns || 1;
  return Array.from({ length: r }, () => Array.from({ length: c }, () => 1));
}

/** Kas ruut (r,c) on peenra ruut (võib taim), mitte vahekäik */
function isPlantCell(bed: Bed, row: number, col: number): boolean {
  const layout = getBedLayout(bed);
  if (row >= layout.length) return false;
  const layoutRow = layout[row];
  if (!layoutRow || col >= layoutRow.length) return false;
  return layoutRow[col] === 1;
}

function getBedColumns(bed: Bed): number {
  const layout = getBedLayout(bed);
  if (layout.length === 0) return 1;
  return Math.max(...layout.map((r) => r.length), 1);
}

const cellModal = ref<{ bed: Bed; row: number; col: number } | null>(null);

function addNewBedRow(isPath: boolean) {
  const firstRow = newBedLayout.value[0];
  const len = firstRow?.length ?? 1;
  newBedLayout.value = [...newBedLayout.value, Array.from({ length: len }, () => (isPath ? 0 : 1))];
}
function addNewBedSquare(rowIndex: number) {
  const row = newBedLayout.value[rowIndex];
  if (!row) return;
  newBedLayout.value = newBedLayout.value.map((r, i) =>
    i === rowIndex ? [...r, 1] : r
  );
}
function removeNewBedCell(rowIndex: number, colIndex: number) {
  const row = newBedLayout.value[rowIndex];
  if (!row || colIndex < 0 || colIndex >= row.length) return;
  const next = newBedLayout.value.map((r, i) =>
    i === rowIndex ? r.filter((_, c) => c !== colIndex) : r
  );
  const filtered = next.filter((r) => r.length > 0);
  newBedLayout.value = filtered.length > 0 ? filtered : [[1]];
}
function hasAnyPlantCell(): boolean {
  return newBedLayout.value.some((row) => row.some((v) => v === 1));
}

function startEditBed(bed: Bed) {
  editModeBedId.value = bed.id;
  newBedName.value = bed.name;
  newBedLocation.value = bed.location ?? '';
  newBedLayout.value = JSON.parse(JSON.stringify(getBedLayout(bed)));
  showAddBed.value = true;
}
function cancelBedForm() {
  showAddBed.value = false;
  editModeBedId.value = null;
  newBedName.value = '';
  newBedLocation.value = '';
  newBedLayout.value = [[1]];
}
function submitNewBed() {
  if (!newBedName.value.trim()) return;
  if (!hasAnyPlantCell()) return;
  const payload = {
    name: newBedName.value.trim(),
    location: newBedLocation.value.trim() || undefined,
    layout: newBedLayout.value,
  };
  if (editModeBedId.value !== null) {
    router.put(`/beds/${editModeBedId.value}`, payload, {
      preserveScroll: true,
      onSuccess: cancelBedForm,
    });
  } else {
    router.post('/beds', payload, {
      preserveScroll: true,
      onSuccess: cancelBedForm,
    });
  }
}

function deleteBed(bed: Bed) {
  if (!confirm(`Eemaldada peenar "${bed.name}"? Taimed jäävad peenrata.`)) return;
  router.delete(`/beds/${bed.id}`, { preserveScroll: true });
}

function assignPlantToBed(plantId: number) {
  const bedId = assignPlantBedId.value[plantId];
  const position = (assignPlantPosition.value[plantId] ?? '').trim() || undefined;
  if (!bedId) return;
  router.put(`/plants/${plantId}`, { bed_id: bedId, position_in_bed: position }, { preserveScroll: true });
}

function assignPlantToCell(plantId: number, bed: Bed, row: number, col: number) {
  const key = `${row},${col}`;
  router.put(`/plants/${plantId}`, { bed_id: bed.id, position_in_bed: key }, {
    preserveScroll: true,
    onSuccess: () => { cellModal.value = null; },
  });
}

const DRAG_PLANT_KEY = 'application/x-plant-id';
function onDragStartPlant(e: DragEvent, plantId: number) {
  if (!e.dataTransfer) return;
  e.dataTransfer.setData(DRAG_PLANT_KEY, String(plantId));
  e.dataTransfer.effectAllowed = 'move';
}
function onDropPlant(e: DragEvent, bed: Bed, row: number, col: number) {
  e.preventDefault();
  const raw = e.dataTransfer?.getData(DRAG_PLANT_KEY);
  if (!raw) return;
  const plantId = parseInt(raw, 10);
  if (Number.isNaN(plantId) || plantAt(bed, row, col)) return;
  assignPlantToCell(plantId, bed, row, col);
}
function onDragOverCell(e: DragEvent) {
  e.preventDefault();
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
}

function removePlantFromBed(plant: PlantInBed) {
  router.put(`/plants/${plant.id}`, { bed_id: null, position_in_bed: null }, { preserveScroll: true });
}
</script>

<template>
  <Head title="Aiaplaan" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <header class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-primary">Aiaplaan</h1>
        <p class="mt-1 text-muted-foreground">
          Näed oma peenart ja seal asetsevaid taimi – lisa peenrad ja paiguta taimed ruutudele.
        </p>
      </header>

      <!-- Lisa peenar -->
      <section class="mb-8">
        <button
          v-if="!showAddBed"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-3 text-sm font-medium hover:bg-muted"
          @click="editModeBedId = null; newBedName = ''; newBedLocation = ''; newBedLayout = [[1]]; showAddBed = true"
        >
          <span class="material-symbols-outlined text-lg">add_circle</span>
          Lisa peenar
        </button>
        <form
          v-else
          class="rounded-xl border border-border bg-card p-4 space-y-3"
          @submit.prevent="submitNewBed"
        >
          <h3 v-if="editModeBedId !== null" class="text-lg font-semibold mb-2">Muuda peenrat</h3>
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
            <label class="block text-sm font-medium text-foreground mb-1">Asukoht (valikuline)</label>
            <input
              v-model="newBedLocation"
              type="text"
              class="form-input w-full"
              placeholder="nt Tagaaed, esiukse paremal"
              maxlength="255"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-foreground mb-2">Peenra ruudud</label>
            <p class="text-xs text-muted-foreground mb-2">Lisa ruute, lisa rida alla või vahekäik (nt tee kasvuhoonesse).</p>
            <div class="space-y-2 mb-3">
              <div
                v-for="(row, ri) in newBedLayout"
                :key="ri"
                class="flex flex-wrap items-center gap-2"
              >
                <template v-for="(cell, ci) in row" :key="ci">
                  <div
                    class="relative w-12 h-12 shrink-0"
                  >
                    <div
                      v-if="cell === 1"
                      class="w-full h-full rounded-lg border-2 border-amber-300/70 dark:border-amber-700/60 bg-amber-100/50 dark:bg-amber-900/20"
                    />
                    <div
                      v-else
                      class="w-full h-full rounded-lg border border-dashed border-muted-foreground/40 bg-muted/40 flex items-center justify-center"
                      title="Vahekäik"
                    >
                      <span class="material-symbols-outlined text-lg text-muted-foreground">directions_walk</span>
                    </div>
                    <button
                      type="button"
                      class="absolute -top-1 -right-1 p-0.5 rounded-full bg-red-500 text-white shadow hover:bg-red-600"
                      aria-label="Eemalda ruut"
                      @click="removeNewBedCell(ri, ci)"
                    >
                      <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                  </div>
                </template>
                <button
                  type="button"
                  class="w-12 h-12 rounded-lg border-2 border-dashed border-primary bg-primary/10 text-primary flex items-center justify-center shrink-0 hover:bg-primary/20 transition"
                  @click="addNewBedSquare(ri)"
                >
                  <span class="material-symbols-outlined text-2xl">add</span>
                </button>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 mb-2">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-sm hover:bg-muted"
                @click="addNewBedRow(false)"
              >
                <span class="material-symbols-outlined text-lg">add</span>
                Lisa rida alla
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-sm hover:bg-muted"
                @click="addNewBedRow(true)"
              >
                <span class="material-symbols-outlined text-lg">directions_walk</span>
                Lisa vahekäik
              </button>
            </div>
            <p v-if="hasAnyPlantCell()" class="text-xs text-muted-foreground">
              {{ newBedLayout.flat().filter((v) => v === 1).length }} peenra ruutu
            </p>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn-primary" :disabled="!hasAnyPlantCell()">
              {{ editModeBedId !== null ? 'Salvesta' : 'Loo peenar' }}
            </button>
            <button type="button" class="btn-outline" @click="cancelBedForm()">
              Tühista
            </button>
          </div>
        </form>
      </section>

      <!-- Peenrad: pildiline vaade -->
      <section class="space-y-6 mb-8">
        <h2 class="text-lg font-semibold">Minu peenrad</h2>
        <div v-if="!beds.length" class="rounded-2xl border-2 border-dashed border-border bg-muted/20 p-8 text-center text-muted-foreground">
          Peenraid pole. Lisa esimene peenar ülal – siis näed teda siin pildiliselt.
        </div>
        <div
          v-for="bed in beds"
          :key="bed.id"
          class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm"
        >
          <!-- Peenra nimi ja asukoht – klõpsuga sulge/ava -->
          <div class="flex items-center gap-2 px-4 py-3 bg-muted/30 border-b border-border">
            <button
              type="button"
              class="flex flex-1 min-w-0 items-start justify-between gap-2 text-left"
              @click="toggleBed(bed.id)"
            >
              <div class="min-w-0">
                <h3 class="font-semibold text-foreground">{{ bed.name }}</h3>
                <p v-if="bed.location" class="text-sm text-muted-foreground truncate">{{ bed.location }}</p>
              </div>
              <span
                class="material-symbols-outlined text-2xl text-muted-foreground shrink-0 transition-transform"
                :class="{ 'rotate-180': isBedExpanded(bed.id) }"
                aria-hidden="true"
              >
                expand_more
              </span>
            </button>
            <div class="flex gap-1 shrink-0">
              <button
                type="button"
                class="p-2 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Muuda peenrat"
                @click.stop="startEditBed(bed)"
              >
                <span class="material-symbols-outlined text-lg">edit</span>
              </button>
              <button
                type="button"
                class="p-2 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Eemalda peenar"
                @click.stop="deleteBed(bed)"
              >
                <span class="material-symbols-outlined text-lg">delete_outline</span>
              </button>
            </div>
          </div>
          <!-- Pildiline peenar: ruudustik (nähtav ainult lahtisel peenral) -->
          <div v-show="isBedExpanded(bed.id)" class="p-4">
            <p class="text-xs font-medium text-muted-foreground mb-3">Lohista taim ruudule või kliki tühjale ruudule</p>
            <div
              class="inline-grid gap-2 p-3 rounded-2xl border-2 border-amber-200/80 bg-amber-50/60 dark:bg-amber-950/20 dark:border-amber-800/50"
              :style="{ gridTemplateColumns: `repeat(${getBedColumns(bed)}, minmax(0, 1fr))`, gridTemplateRows: `repeat(${getBedLayout(bed).length}, minmax(0, 1fr))` }"
            >
              <template v-for="r in range(getBedLayout(bed).length)" :key="r">
                <template v-for="c in range(getBedColumns(bed))" :key="`${r}-${c}`">
                  <!-- Vahekäik (tee) – ei ole taimeruut -->
                  <div
                    v-if="(getBedLayout(bed)[r]?.[c] ?? 0) === 0"
                    class="min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl border border-dashed border-muted-foreground/30 bg-muted/30 flex items-center justify-center"
                  >
                    <span class="material-symbols-outlined text-2xl text-muted-foreground">directions_walk</span>
                  </div>
                  <!-- Ruut taimega -->
                  <div
                    v-else-if="plantAt(bed, r, c)"
                    class="relative flex flex-col items-center justify-center min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl bg-card border border-amber-300/60 dark:border-amber-700/50 shadow-md overflow-hidden group"
                  >
                    <div
                      class="absolute inset-0 bg-cover bg-center"
                      :style="plantAt(bed, r, c)?.image_url ? { backgroundImage: `url('${plantAt(bed, r, c)?.image_url}')` } : {}"
                    />
                    <div v-if="!plantAt(bed, r, c)?.image_url" class="absolute inset-0 flex items-center justify-center bg-primary/10 text-primary">
                      <span class="material-symbols-outlined text-3xl">eco</span>
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent" />
                    <span class="absolute bottom-1.5 left-1.5 right-1.5 text-white text-[11px] font-semibold truncate drop-shadow-md">
                      {{ plantAt(bed, r, c)?.name }}
                    </span>
                    <button
                      type="button"
                      class="absolute top-1 right-1 p-1.5 rounded-full bg-black/60 text-white opacity-0 group-hover:opacity-100 transition hover:bg-black/80"
                      aria-label="Eemalda peenralt"
                      @click.stop="removePlantFromBed(plantAt(bed, r, c)!)"
                    >
                      <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                  </div>
                  <!-- Tühi peenra ruut – lohista taim siia või kliki -->
                  <button
                    v-else
                    type="button"
                    class="flex flex-col items-center justify-center min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl border-2 border-dashed border-amber-300/70 dark:border-amber-700/60 bg-amber-100/50 dark:bg-amber-900/20 text-amber-800/70 dark:text-amber-200/70 hover:border-primary hover:bg-primary/10 hover:text-primary transition"
                    @click="cellModal = { bed, row: r, col: c }"
                    @dragover.prevent="onDragOverCell"
                    @drop="onDropPlant($event, bed, r, c)"
                  >
                    <span class="material-symbols-outlined text-2xl mb-0.5">add_circle</span>
                    <span class="text-[10px] font-medium">Lisa taim</span>
                  </button>
                </template>
              </template>
            </div>
            <!-- Taimed, kellel vabatekstiline asukoht (ei ole ruudul) -->
            <div v-if="plantsWithoutCell(bed).length" class="mt-4 pt-4 border-t border-border">
              <p class="text-xs font-medium text-muted-foreground mb-2">Muud taimed peenral</p>
              <ul class="space-y-2">
                <li
                  v-for="p in plantsWithoutCell(bed)"
                  :key="p.id"
                  class="flex items-center gap-3 rounded-lg bg-muted/50 p-2"
                >
                  <div
                    class="h-8 w-8 rounded-full bg-secondary bg-cover bg-center shrink-0"
                    :style="p.image_url ? { backgroundImage: `url('${p.image_url}')` } : {}"
                  >
                    <span v-if="!p.image_url" class="flex h-full w-full items-center justify-center text-primary text-sm">
                      <span class="material-symbols-outlined">eco</span>
                    </span>
                  </div>
                  <span class="text-sm font-medium truncate">{{ p.name }}</span>
                  <span v-if="p.position_in_bed" class="text-xs text-muted-foreground truncate">— {{ p.position_in_bed }}</span>
                  <button
                    type="button"
                    class="ml-auto p-1.5 rounded-lg text-muted-foreground hover:bg-muted"
                    aria-label="Eemalda peenralt"
                    @click="removePlantFromBed(p)"
                  >
                    <span class="material-symbols-outlined text-lg">close</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <!-- Modal: vali taim tühjale ruudule -->
      <Teleport to="body">
        <div
          v-if="cellModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
          @click.self="cellModal = null"
        >
          <div class="bg-card rounded-xl shadow-xl max-w-sm w-full max-h-[70vh] overflow-hidden flex flex-col" @click.stop>
            <div class="p-4 border-b border-border flex items-center justify-between">
              <h3 class="font-semibold">Vali taim ruudule</h3>
              <button type="button" class="p-2 rounded-lg hover:bg-muted" @click="cellModal = null">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
            <div class="overflow-y-auto p-2">
              <p v-if="!plantsWithoutBed.length" class="text-sm text-muted-foreground py-4 text-center">
                Kõik taimed on juba peenrale määratud.
              </p>
              <button
                v-for="plant in plantsWithoutBed"
                :key="plant.id"
                type="button"
                class="w-full flex items-center gap-3 rounded-lg p-3 text-left hover:bg-muted transition"
                @click="cellModal && assignPlantToCell(plant.id, cellModal.bed, cellModal.row, cellModal.col)"
              >
                <div
                  class="h-10 w-10 rounded-full bg-secondary bg-cover bg-center shrink-0"
                  :style="plant.image_url ? { backgroundImage: `url('${plant.image_url}')` } : {}"
                >
                  <span v-if="!plant.image_url" class="flex h-full w-full items-center justify-center text-primary">
                    <span class="material-symbols-outlined">eco</span>
                  </span>
                </div>
                <span class="font-medium">{{ plant.name }}</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Taimed ilma peenrata – lohista need peenra ruutudele -->
      <section>
        <h2 class="text-lg font-semibold mb-3">Taimed ilma peenrata</h2>
        <p class="text-sm text-muted-foreground mb-4">
          Lohista taim ülaloleva peenra tühjale ruudule või vali peenar ja ruut siit all.
        </p>
        <div v-if="!plantsWithoutBed.length" class="rounded-xl border border-dashed border-border bg-muted/30 p-6 text-center text-muted-foreground">
          Kõik taimed on juba peenrale määratud või sul pole taimi.
        </div>
        <ul v-else class="space-y-3">
          <li
            v-for="plant in plantsWithoutBed"
            :key="plant.id"
            draggable="true"
            class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-card p-4 cursor-grab active:cursor-grabbing"
            @dragstart="onDragStartPlant($event, plant.id)"
          >
            <div
              class="h-10 w-10 rounded-full bg-secondary bg-cover bg-center shrink-0"
              :style="plant.image_url ? { backgroundImage: `url('${plant.image_url}')` } : {}"
            >
              <span v-if="!plant.image_url" class="flex h-full w-full items-center justify-center text-primary">
                <span class="material-symbols-outlined text-xl">eco</span>
              </span>
            </div>
            <span class="font-medium">{{ plant.name }}</span>
            <span class="text-xs text-muted-foreground">— lohista peenraruudule</span>
            <select
              v-model="assignPlantBedId[plant.id]"
              class="form-input w-auto min-w-[140px]"
            >
              <option :value="undefined">Vali peenar</option>
              <option v-for="b in beds" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <input
              v-model="assignPlantPosition[plant.id]"
              type="text"
              class="form-input w-36"
              placeholder="Asukoht peenral"
              maxlength="120"
            />
            <button
              type="button"
              class="btn-primary"
              :disabled="!assignPlantBedId[plant.id]"
              @click="assignPlantToBed(plant.id)"
            >
              Määra peenrale
            </button>
          </li>
        </ul>
      </section>

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>
