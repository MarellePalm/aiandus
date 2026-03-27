<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CardActionsMenu from '@/components/CardActionsMenu.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

type PlantInBed = { id: number; name: string; image_url: string | null; position_in_bed: string | null };
type Bed = {
  id: number;
  name: string;
  location: string | null;
  image_url?: string | null;
  rows: number;
  columns: number;
  layout?: number[][] | null;
  plants: PlantInBed[];
};
type PlantWithoutBed = { id: number; name: string; image_url: string | null; category?: { name: string; slug: string } | null };

const props = defineProps<{
  bed: Bed;
  plantsWithoutBed: PlantWithoutBed[];
}>();

const breadcrumbs = [
  { title: 'Aiaplaan', href: '/map' },
  { title: props.bed.name, href: `/beds/${props.bed.id}` },
];

const cellModal = ref<{ row: number; col: number } | null>(null);
const coverTick = ref(0);
let coverTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  coverTimer = setInterval(() => {
    coverTick.value += 1;
  }, 3500);
});

onBeforeUnmount(() => {
  if (coverTimer) clearInterval(coverTimer);
});

function bedCoverImage(): string | null {
  if (props.bed.image_url) return props.bed.image_url;
  const images = props.bed.plants.map((p) => p.image_url).filter((x): x is string => Boolean(x));
  if (!images.length) return null;
  return images[coverTick.value % images.length];
}

function range(n: number): number[] {
  return Array.from({ length: n }, (_, i) => i);
}

function getBedLayout(): number[][] {
  const L = props.bed.layout;
  if (L && Array.isArray(L) && L.length > 0 && L.some((row) => Array.isArray(row) && row.length > 0)) {
    return L as number[][];
  }
  return Array.from({ length: props.bed.rows || 1 }, () => Array.from({ length: props.bed.columns || 1 }, () => 1));
}

function getBedColumns(): number {
  const layout = getBedLayout();
  if (layout.length === 0) return 1;
  return Math.max(...layout.map((r) => r.length), 1);
}

function plantAt(row: number, col: number): PlantInBed | undefined {
  const key = `${row},${col}`;
  return props.bed.plants.find((p) => p.position_in_bed === key);
}

function plantsWithoutCell(): PlantInBed[] {
  return props.bed.plants.filter((p) => !p.position_in_bed || !/^\d+,\d+$/.test(p.position_in_bed));
}

function removePlantFromBed(plant: PlantInBed) {
  router.put(`/plants/${plant.id}`, { bed_id: null, position_in_bed: null }, { preserveScroll: true });
}

function assignPlantToCell(plantId: number, row: number, col: number) {
  const key = `${row},${col}`;
  router.put(`/plants/${plantId}`, { bed_id: props.bed.id, position_in_bed: key }, {
    preserveScroll: true,
    onSuccess: () => { cellModal.value = null; },
  });
}

function deleteBed() {
  if (!confirm(`Eemaldada peenar "${props.bed.name}"? Taimed jäävad peenrata.`)) return;
  router.delete(`/beds/${props.bed.id}`, {
    preserveScroll: true,
    onSuccess: () => router.visit('/map'),
  });
}

const plantsWithoutBedByCategory = computed(() => {
  const map = new Map<string, PlantWithoutBed[]>();
  for (const p of props.plantsWithoutBed) {
    const key = p.category?.name ?? 'Kategooriata';
    if (!map.has(key)) map.set(key, []);
    map.get(key)!.push(p);
  }
  return Array.from(map.entries()).sort((a, b) => a[0].localeCompare(b[0], 'et'));
});
</script>

<template>
  <Head :title="bed.name" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background-light text-forest font-display min-h-screen antialiased">
        <div class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title=""
            header-class="pt-6"
            top-row-class="mb-2"
            bottom-row-class="mb-4"
          >
            <template #leading>
              <BackIconButton href="/map" aria-label="Tagasi peenarde loendisse" />
            </template>
          </DiaryHeader>

          <main class="flex-1 px-6 py-4 md:px-8 space-y-4">
            <section class="rounded-2xl border border-border bg-card shadow-soft">
              <div
                class="h-38 bg-cover bg-center relative overflow-hidden rounded-t-2xl"
                :style="bedCoverImage() ? { backgroundImage: `url('${bedCoverImage()}')` } : {}"
              >
                <div class="absolute right-2 top-2 z-10">
                  <CardActionsMenu
                    @edit="router.get(`/beds/${bed.id}/edit`)"
                    @delete="deleteBed"
                  />
                </div>
                <div v-if="!bedCoverImage()" class="absolute inset-0 bg-linear-to-br from-primary/15 via-muted/40 to-muted/20 flex items-center justify-center">
                  <span class="material-symbols-outlined text-5xl text-primary/80">grass</span>
                </div>
                <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/70 via-black/30 to-transparent p-3">
                  <h2 class="text-lg font-semibold text-white">{{ bed.name }}</h2>
                </div>
              </div>
              <div class="p-4 flex items-start justify-between gap-3">
                <div>
                  <p v-if="bed.location" class="text-sm text-muted-foreground">{{ bed.location }}</p>
                </div>
              </div>
            </section>

            <section class="rounded-2xl border border-border bg-card p-3 sm:p-4 shadow-soft">
              <div class="overflow-x-auto overflow-y-visible pb-1 -mx-1 px-1 custom-scrollbar">
                <div
                  class="inline-grid gap-2 sm:gap-2.5 p-3 sm:p-4 rounded-2xl border border-amber-200/70 bg-linear-to-br from-amber-50/60 to-amber-100/30 dark:from-amber-950/20 dark:to-amber-900/10 dark:border-amber-800/40 shadow-soft ring-1 ring-amber-200/30 dark:ring-amber-800/20 w-max min-w-0"
                  :style="{
                    gridTemplateColumns: `repeat(${getBedColumns()}, minmax(64px, 1fr))`,
                    gridTemplateRows: `repeat(${getBedLayout().length}, minmax(64px, 1fr))`,
                  }"
                >
                  <template v-for="r in range(getBedLayout().length)" :key="r">
                    <template v-for="c in range(getBedColumns())" :key="`${r}-${c}`">
                      <div v-if="plantAt(r, c)" class="relative min-w-[64px] min-h-[64px] rounded-xl bg-card border-2 border-amber-300/70 overflow-hidden group">
                        <div class="absolute inset-0 bg-cover bg-center" :style="plantAt(r, c)?.image_url ? { backgroundImage: `url('${plantAt(r, c)?.image_url}')` } : {}" />
                        <div class="absolute inset-0 bg-linear-to-t from-black/75 via-black/20 to-transparent" />
                        <span class="absolute bottom-1.5 left-1.5 right-1.5 text-white text-[11px] font-semibold truncate">{{ plantAt(r, c)?.name }}</span>
                        <button type="button" class="absolute top-1 right-1 p-1 rounded-full bg-black/60 text-white opacity-0 group-hover:opacity-100" @click.stop="removePlantFromBed(plantAt(r, c)!)">
                          <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                      </div>
                      <div v-else-if="(getBedLayout()[r]?.[c] ?? 0) === -1" class="min-w-[64px] min-h-[64px] rounded-xl border border-border bg-muted/50" />
                      <div v-else-if="(getBedLayout()[r]?.[c] ?? 1) === 0" class="min-w-[64px] min-h-[64px] rounded-xl border border-dashed border-muted-foreground/25 bg-muted/25" />
                      <button v-else type="button" class="min-w-[64px] min-h-[64px] rounded-xl border-2 border-dashed border-amber-300/50 bg-amber-50/70 text-amber-800/90" @click="cellModal = { row: r, col: c }">
                        <span class="material-symbols-outlined text-2xl">add_circle</span>
                      </button>
                    </template>
                  </template>
                </div>
              </div>
            </section>

            <section v-if="plantsWithoutCell().length" class="rounded-2xl border border-border bg-card/90 p-4">
              <p class="text-xs font-medium text-muted-foreground mb-2">Muud taimed peenral</p>
              <ul class="space-y-2">
                <li v-for="p in plantsWithoutCell()" :key="p.id" class="flex items-center gap-3 rounded-xl bg-muted/40 border border-border/50 p-2.5">
                  <span class="text-sm font-medium truncate">{{ p.name }}</span>
                  <button type="button" class="ml-auto p-1.5 rounded-lg text-muted-foreground hover:bg-muted" @click="removePlantFromBed(p)">
                    <span class="material-symbols-outlined text-lg">close</span>
                  </button>
                </li>
              </ul>
            </section>
          </main>
        </div>
        <BottomNav active="map" />
      </div>

      <Teleport to="body">
        <div v-if="cellModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="cellModal = null">
          <div class="bg-card rounded-2xl shadow-xl ring-1 ring-black/5 max-w-sm w-full max-h-[70vh] overflow-hidden flex flex-col" @click.stop>
            <div class="p-4 border-b border-border flex items-center justify-between">
              <h3 class="font-semibold">Vali taim ruudule</h3>
              <button type="button" class="p-2 rounded-lg hover:bg-muted" @click="cellModal = null">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
            <div class="overflow-y-auto p-2">
              <template v-if="!plantsWithoutBed.length">
                <p class="text-sm text-muted-foreground py-3 text-center">Kõik taimed on juba peenrale määratud.</p>
                <Link href="/plants/create" class="flex items-center justify-center gap-2 w-full py-3.5 rounded-xl border-2 border-primary/40 bg-primary/10 text-primary font-semibold">
                  <span class="material-symbols-outlined">add_circle</span>
                  Lisa uus taim
                </Link>
              </template>
              <template v-else>
                <div v-for="[categoryName, plants] in plantsWithoutBedByCategory" :key="categoryName" class="mb-4 last:mb-0">
                  <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide px-1 mb-1.5">{{ categoryName }}</p>
                  <ul class="space-y-1">
                    <li v-for="plant in plants" :key="plant.id">
                      <button
                        type="button"
                        class="w-full flex items-center gap-3 rounded-xl p-3 text-left hover:bg-primary/10 border border-transparent"
                        @click="cellModal && assignPlantToCell(plant.id, cellModal.row, cellModal.col)"
                      >
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
  </AppLayout>
</template>

