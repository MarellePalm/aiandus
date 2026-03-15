<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

type PlantInBed = { id: number; name: string; image_url: string | null; position_in_bed: string | null };
type Bed = { id: number; name: string; location: string | null; rows: number; columns: number; layout?: number[][] | null; plants: PlantInBed[] };
type PlantWithoutBed = { id: number; name: string; image_url: string | null; category?: { name: string; slug: string } | null };

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

/** Taimed ilma peenrata, rühmitatud kategooria järgi (modali jaoks) */
const plantsWithoutBedByCategory = computed(() => {
  const map = new Map<string, PlantWithoutBed[]>();
  for (const p of props.plantsWithoutBed) {
    const key = p.category?.name ?? 'Kategooriata';
    if (!map.has(key)) map.set(key, []);
    map.get(key)!.push(p);
  }
  return Array.from(map.entries()).sort((a, b) => {
    if (a[0] === 'Kategooriata') return 1;
    if (b[0] === 'Kategooriata') return -1;
    return a[0].localeCompare(b[0], 'et');
  });
});

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
      <div class="page-container-wide py-6 sm:py-8 space-y-6 sm:space-y-8">
        <DiaryHeader
          title="Minu peenrad"
          header-class="pt-4 sm:pt-6"
          top-row-class="mb-3"
          bottom-row-class="mb-4"
        />

        <div class="w-full space-y-6 sm:space-y-8">
        <!-- Peenrad: pildiline vaade -->
        <section class="space-y-6">
          <p class="text-sm text-muted-foreground">
            Ava peenar ja kliki tühjal ruudul <strong>+</strong>, et lisada taim. Allpool on taimed, kes ootavad peenrale määramist.
          </p>
            <div
              v-if="!props.beds.length"
              class="rounded-2xl border-2 border-dashed border-primary/30 bg-gradient-to-br from-muted/30 to-primary/5 p-8 text-center text-muted-foreground"
            >
            Peenraid pole. Lisa esimene peenar paremal alanurgas oleva + nupuga – siis näed teda siin pildiliselt.
          </div>
          <div
            v-for="bed in props.beds"
            v-else
            :key="bed.id"
            class="rounded-2xl border border-border bg-card shadow-soft overflow-hidden"
          >
              <!-- Peenra nimi ja asukoht – klõpsuga sulge/ava -->
              <div
                class="flex items-center gap-2 px-4 sm:px-5 py-3.5 sm:py-4 min-h-[48px] bg-gradient-to-r from-muted/50 to-muted/30 dark:from-muted/30 dark:to-muted/20 border-b border-border cursor-pointer hover:from-muted/60 hover:to-muted/40 transition-colors touch-manipulation"
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
                <div class="relative shrink-0 flex items-center">
                  <button
                    type="button"
                    class="icon-btn size-10 sm:size-9 min-w-[44px] min-h-[44px] rounded-full touch-manipulation"
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
              <!-- Pildiline peenar: ruudustik (nähtav ainult lahtisel peenral); mobiilil horisontaalselt keritav -->
              <div v-show="isBedExpanded(bed.id)" class="p-3 sm:p-5">
                <div class="overflow-x-auto overflow-y-visible pb-1 -mx-1 px-1 custom-scrollbar">
                  <div
                    class="inline-grid gap-2 sm:gap-2.5 p-3 sm:p-4 rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/60 to-amber-100/30 dark:from-amber-950/20 dark:to-amber-900/10 dark:border-amber-800/40 shadow-soft ring-1 ring-amber-200/30 dark:ring-amber-800/20 w-max min-w-0"
                    :style="{
                      gridTemplateColumns: `repeat(${getBedColumns(bed)}, minmax(64px, 1fr))`,
                      gridTemplateRows: `repeat(${getBedLayout(bed).length}, minmax(64px, 1fr))`,
                    }"
                  >
                  <template v-for="r in range(getBedLayout(bed).length)" :key="r">
                    <template v-for="c in range(getBedColumns(bed))" :key="`${r}-${c}`">
                      <!-- Vahekäik / kivi / tee (väärtus -1) -->
                      <div
                        v-if="(getBedLayout(bed)[r]?.[c] ?? 0) === -1"
                        class="min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl border border-border bg-muted/50 relative overflow-hidden shadow-sm"
                        title="Vahekäik / kivi"
                      >
                        <span
                          class="absolute inset-0 opacity-40 pointer-events-none text-muted-foreground"
                          style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 10px 10px;"
                        />
                      </div>
                      <!-- Tühi ruut (väärtus 0) – mitte peenra osa -->
                      <div
                        v-else-if="(getBedLayout(bed)[r]?.[c] ?? 1) === 0"
                        class="min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl border border-dashed border-muted-foreground/25 bg-muted/25 flex items-center justify-center"
                      />
                      <!-- Ruut taimega -->
                      <div
                        v-else-if="plantAt(bed, r, c)"
                        class="relative flex flex-col items-center justify-center min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl bg-card border-2 border-amber-300/70 dark:border-amber-600/50 shadow-md hover:shadow-lg hover:scale-[1.02] active:scale-[0.99] transition-all duration-200 overflow-hidden group"
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
                        class="flex flex-col items-center justify-center min-w-[64px] min-h-[64px] w-full aspect-square max-w-[88px] max-h-[88px] rounded-xl border-2 border-dashed border-amber-300/50 dark:border-amber-600/40 bg-amber-50/70 dark:bg-amber-900/20 text-amber-800/90 dark:text-amber-200/80 hover:border-primary hover:bg-primary/15 hover:text-primary hover:shadow-md hover:scale-[1.03] active:scale-[0.98] transition-all duration-200"
                        @click="cellModal = { bed, row: r, col: c }"
                      >
                        <span class="material-symbols-outlined text-2xl mb-0.5">add_circle</span>
                        <span class="text-[10px] font-semibold">Lisa taim</span>
                      </button>
                    </template>
                  </template>
                  </div>
                </div>
                <!-- Taimed, kellel vabatekstiline asukoht (ei ole ruudul) -->
                <div v-if="plantsWithoutCell(bed).length" class="mt-4 pt-4 border-t border-border">
                  <p class="text-xs font-medium text-muted-foreground mb-2">Muud taimed peenral</p>
                  <ul class="space-y-2">
                    <li
                      v-for="p in plantsWithoutCell(bed)"
                      :key="p.id"
                      class="flex items-center gap-3 rounded-xl bg-muted/40 border border-border/50 p-2.5 hover:bg-muted/60 transition-colors"
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
          <section class="rounded-2xl border border-border/60 bg-muted/20 dark:bg-muted/10 p-4 sm:p-5">
            <h2 class="text-lg font-semibold mb-1 flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-[1.25rem]" aria-hidden="true">eco</span>
              Taimed ilma peenrata
            </h2>
            <p class="text-xs text-muted-foreground mb-3">
              Vali üleval peenral tühi ruut ja lisa taim.
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
                class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-card p-4 shadow-soft hover:shadow-md hover:border-primary/20 transition-all duration-200"
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
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
          @click.self="cellModal = null"
        >
          <div class="bg-card rounded-2xl shadow-xl ring-1 ring-black/5 max-w-sm w-full max-h-[70vh] overflow-hidden flex flex-col" @click.stop>
            <div class="p-4 border-b border-border flex items-center justify-between">
              <h3 class="font-semibold">Vali taim ruudule</h3>
              <button type="button" class="p-2 rounded-lg hover:bg-muted" @click="cellModal = null">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
            <div class="overflow-y-auto p-2">
              <template v-if="!plantsWithoutBed.length">
                <p class="text-sm text-muted-foreground py-3 text-center">
                  Kõik taimed on juba peenrale määratud. Lisa uus taim ja tule tagasi.
                </p>
                <Link
                  href="/plants/create"
                  class="flex items-center justify-center gap-2 w-full py-3.5 rounded-xl border-2 border-primary/40 bg-primary/10 text-primary font-semibold hover:bg-primary/20 hover:border-primary/60 hover:shadow-soft transition-all duration-200"
                  @click="cellModal = null"
                >
                  <span class="material-symbols-outlined">add_circle</span>
                  Lisa uus taim
                </Link>
              </template>
              <template v-else>
                <div
                  v-for="[categoryName, plants] in plantsWithoutBedByCategory"
                  :key="categoryName"
                  class="mb-4 last:mb-0"
                >
                  <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide px-1 mb-1.5">
                    {{ categoryName }}
                  </p>
                  <ul class="space-y-1">
                    <li v-for="plant in plants" :key="plant.id">
                      <button
                        type="button"
                        class="w-full flex items-center gap-3 rounded-xl p-3 text-left hover:bg-primary/10 hover:border-primary/30 border border-transparent transition-all duration-200"
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
                    </li>
                  </ul>
                </div>
              </template>
            </div>
          </div>
        </div>
      </Teleport>
      </div>

      <FloatingPlusButton
        aria-label="Lisa peenar"
        :size-px="52"
        :icon-size-px="30"
        @click="router.visit('/map/beds/new')"
      />

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>
