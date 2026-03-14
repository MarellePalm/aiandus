<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import DiaryHeader from '@/components/DiaryHeader.vue';
import PrimaryCtaButton from '@/components/PrimaryCtaButton.vue';
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

/** Kokkupandud peenrate id-d – need näidatakse ainult pealkirjana */
const collapsedBedIds = ref<Set<number>>(new Set(props.beds.map((b) => b.id)));

function isBedExpanded(bedId: number): boolean {
  return !collapsedBedIds.value.has(bedId);
}
function toggleBed(bedId: number) {
  const next = new Set(collapsedBedIds.value);
  if (next.has(bedId)) next.delete(bedId);
  else next.add(bedId);
  collapsedBedIds.value = next;
}

const openBedMenuId = ref<number | null>(null);

function toggleBedMenu(bedId: number) {
  openBedMenuId.value = openBedMenuId.value === bedId ? null : bedId;
}
function closeBedMenu() {
  openBedMenuId.value = null;
}
function editBed(bed: Bed) {
  closeBedMenu();
  router.get(`/beds/${bed.id}/edit`);
}

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

function getBedColumns(bed: Bed): number {
  const layout = getBedLayout(bed);
  if (layout.length === 0) return 1;
  return Math.max(...layout.map((r) => r.length), 1);
}

const cellModal = ref<{ bed: Bed; row: number; col: number } | null>(null);

function deleteBed(bed: Bed) {
  if (!confirm(`Eemaldada peenar "${bed.name}"? Taimed jäävad peenrata.`)) return;
  router.delete(`/beds/${bed.id}`, { preserveScroll: true });
}

function assignPlantToCell(plantId: number, bed: Bed, row: number, col: number) {
  const key = `${row},${col}`;
  router.put(`/plants/${plantId}`, { bed_id: bed.id, position_in_bed: key }, {
    preserveScroll: true,
    onSuccess: () => { cellModal.value = null; },
  });
}

function removePlantFromBed(plant: PlantInBed) {
  router.put(`/plants/${plant.id}`, { bed_id: null, position_in_bed: null }, { preserveScroll: true });
}
</script>

<template>
  <Head title="Aiaplaan" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="page-container-wide py-8 space-y-8">
        <DiaryHeader
          title="Minu peenrad"
          header-class="pt-6"
          top-row-class="mb-3"
          bottom-row-class="mb-4"
        />

        <PrimaryCtaButton
          label="Lisa peenar"
          icon="add_circle"
          :full-width="true"
          href="/map/beds/new"
        />

        <div class="w-full space-y-8">
        <!-- Peenrad: pildiline vaade -->
        <section class="space-y-6">
          <p class="text-sm text-muted-foreground">
            Ava peenar ja lohista taimed sobivatesse ruutudele.
          </p>
          <div
            v-if="!props.beds.length"
            class="rounded-2xl border-2 border-dashed border-border bg-muted/20 p-8 text-center text-muted-foreground"
          >
            Peenraid pole. Lisa esimene peenar ülal paremal – siis näed teda siin pildiliselt.
          </div>
          <div
            v-for="bed in props.beds"
            v-else
            :key="bed.id"
            class="rounded-2xl border border-border bg-card/90 shadow-sm"
          >
              <!-- Peenra nimi ja asukoht – klõpsuga sulge/ava -->
              <div
                class="flex items-center gap-2 px-5 py-4 bg-muted/40 border-b border-border cursor-pointer"
                @click="toggleBed(bed.id)"
              >
                <div
                  class="flex flex-1 min-w-0 items-start justify-between gap-2 text-left"
                >
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <h3 class="font-semibold text-foreground">{{ bed.name }}</h3>
                      <span
                        v-if="bed.plants.length"
                        class="inline-flex items-center rounded-full bg-primary/15 text-primary px-2 py-0.5 text-xs font-medium"
                      >
                        {{ bed.plants.length }} taime
                      </span>
                    </div>
                    <p v-if="bed.location" class="text-sm text-muted-foreground truncate">
                      {{ bed.location }}
                    </p>
                  </div>
                  <span
                    class="material-symbols-outlined text-2xl text-muted-foreground shrink-0 transition-transform"
                    :class="{ 'rotate-180': isBedExpanded(bed.id) }"
                    aria-hidden="true"
                  >
                    expand_more
                  </span>
                  </div>
                <div class="relative shrink-0">
                  <button
                    type="button"
                    class="icon-btn size-9 rounded-full"
                    aria-label="Valikud"
                    aria-haspopup="true"
                    :aria-expanded="openBedMenuId === bed.id"
                    @click.stop="toggleBedMenu(bed.id)"
                  >
                    <span class="material-symbols-outlined text-xl">more_vert</span>
                  </button>
                  <!-- Click-outside overlay to close menu -->
                  <div
                    v-if="openBedMenuId === bed.id"
                    class="fixed inset-0 z-[5]"
                    aria-hidden="true"
                    @click="closeBedMenu"
                  />
                  <div
                    v-if="openBedMenuId === bed.id"
                    class="absolute right-0 top-full z-10 mt-1 min-w-[140px] rounded-lg border border-border bg-card py-1 shadow-lg"
                    role="menu"
                  >
                    <button
                      type="button"
                      class="menu-item flex w-full items-center gap-2 px-4 py-2 text-left"
                      role="menuitem"
                      @click.stop="editBed(bed)"
                    >
                      <span class="material-symbols-outlined text-lg">edit</span>
                      Muuda
                    </button>
                    <button
                      type="button"
                      class="menu-item flex w-full items-center gap-2 px-4 py-2 text-left text-destructive"
                      role="menuitem"
                      @click.stop="deleteBed(bed); closeBedMenu()"
                    >
                      <span class="material-symbols-outlined text-lg">delete</span>
                      Kustuta
                    </button>
                  </div>
                </div>
              </div>
              <!-- Pildiline peenar: ruudustik (nähtav ainult lahtisel peenral) -->
              <div v-show="isBedExpanded(bed.id)" class="p-5">
                <div
                  class="inline-grid gap-2 p-3 rounded-2xl border border-amber-100/80 bg-amber-50/40 dark:bg-amber-950/15 dark:border-amber-800/30"
                  :style="{
                    gridTemplateColumns: `repeat(${getBedColumns(bed)}, minmax(0, 1fr))`,
                    gridTemplateRows: `repeat(${getBedLayout(bed).length}, minmax(0, 1fr))`,
                  }"
                >
                  <template v-for="r in range(getBedLayout(bed).length)" :key="r">
                    <template v-for="c in range(getBedColumns(bed))" :key="`${r}-${c}`">
                      <!-- Vahekäik (tee) – ei ole taimeruut -->
                      <div
                        v-if="(getBedLayout(bed)[r]?.[c] ?? 0) === 0"
                        class="min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl border border-dashed border-muted-foreground/30 bg-muted/30 flex items-center justify-center"
                      >
                        <span class="material-symbols-outlined text-2xl text-muted-foreground">
                          directions_walk
                        </span>
                      </div>
                      <!-- Ruut taimega -->
                      <div
                        v-else-if="plantAt(bed, r, c)"
                        class="relative flex flex-col items-center justify-center min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl bg-card border border-amber-300/60 dark:border-amber-700/50 shadow-md overflow-hidden group"
                      >
                        <div
                          class="absolute inset-0 bg-cover bg-center"
                          :style="
                            plantAt(bed, r, c)?.image_url
                              ? { backgroundImage: `url('${plantAt(bed, r, c)?.image_url}')` }
                              : {}
                          "
                        />
                        <div
                          v-if="!plantAt(bed, r, c)?.image_url"
                          class="absolute inset-0 flex items-center justify-center bg-primary/10 text-primary"
                        >
                          <span class="material-symbols-outlined text-3xl">eco</span>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent" />
                        <span
                          class="absolute bottom-1.5 left-1.5 right-1.5 text-white text-[11px] font-semibold truncate drop-shadow-md"
                        >
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
                      <!-- Tühi peenra ruut – kliki ja vali taim -->
                      <button
                        v-else
                        type="button"
                        class="flex flex-col items-center justify-center min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl border border-dashed border-amber-200/60 dark:border-amber-700/40 bg-amber-50/50 dark:bg-amber-900/15 text-amber-700/80 dark:text-amber-200/70 hover:border-primary hover:bg-primary/10 hover:text-primary transition"
                        @click="cellModal = { bed, row: r, col: c }"
                      >
                        <span class="material-symbols-outlined text-xl mb-0.5">add_circle</span>
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
                        <span
                          v-if="!p.image_url"
                          class="flex h-full w-full items-center justify-center text-primary text-sm"
                        >
                          <span class="material-symbols-outlined">eco</span>
                        </span>
                      </div>
                      <span class="text-sm font-medium truncate">{{ p.name }}</span>
                      <span v-if="p.position_in_bed" class="text-xs text-muted-foreground truncate">
                        — {{ p.position_in_bed }}
                      </span>
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

          <!-- Taimed ilma peenrata -->
          <section>
            <h2 class="text-lg font-semibold mb-2">Taimed ilma peenrata</h2>
            <p class="text-xs text-muted-foreground mb-3">
              Ava peenar ja kliki „Lisa taim“ ruudul, et siduda taim peenraga.
            </p>
            <div
              v-if="!plantsWithoutBed.length"
              class="rounded-xl border border-dashed border-border bg-muted/20 py-3 px-4 text-center text-xs text-muted-foreground"
            >
              Kõik taimed on peenrale määratud või sul pole taimi.
            </div>
            <ul v-else class="space-y-3">
              <li
                v-for="plant in plantsWithoutBed"
                :key="plant.id"
                class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-card p-4"
              >
                <div
                  class="h-10 w-10 rounded-full bg-secondary bg-cover bg-center shrink-0"
                  :style="plant.image_url ? { backgroundImage: `url('${plant.image_url}')` } : {}"
                >
                  <span
                    v-if="!plant.image_url"
                    class="flex h-full w-full items-center justify-center text-primary"
                  >
                    <span class="material-symbols-outlined text-xl">eco</span>
                  </span>
                </div>
                <span class="font-medium">{{ plant.name }}</span>
                <span class="text-xs text-muted-foreground">— vali ruut peenra kaardilt</span>
              </li>
            </ul>
          </section>
      </div>

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
      </div>

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>
