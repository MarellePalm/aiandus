<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
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
type BedNote = {
  id: number;
  note_date: string | null;
  title: string | null;
  body: string | null;
  type: string | null;
  done: boolean | null;
};

const props = defineProps<{
  bed: Bed;
  plantsWithoutBed: PlantWithoutBed[];
  bedNotes?: BedNote[];
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

const bedCellSize = computed(() => {
  const cols = getBedColumns();
  if (cols >= 10) return 42;
  if (cols >= 8) return 48;
  if (cols >= 6) return 56;
  return 64;
});

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

const plantsWithoutBedByCategory = computed(() => {
  const map = new Map<string, PlantWithoutBed[]>();
  for (const p of props.plantsWithoutBed) {
    const key = p.category?.name ?? 'Kategooriata';
    if (!map.has(key)) map.set(key, []);
    map.get(key)!.push(p);
  }
  return Array.from(map.entries()).sort((a, b) => a[0].localeCompare(b[0], 'et'));
});

function formatNoteDate(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString('et-EE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
  <Head :title="bed.name" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background text-foreground font-display min-h-screen antialiased">
        <div class="bg-background border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
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

          <main class="flex-1 px-6 py-4 md:px-8 space-y-5">
            <section class="relative overflow-hidden rounded-3xl border border-border/70 bg-card shadow-[0_10px_30px_rgba(16,24,40,0.08)]">
              <div
                v-if="bed.image_url"
                class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-[0.18]"
                :style="{ backgroundImage: `url('${bed.image_url}')` }"
                aria-hidden="true"
              />
              <div
                v-if="bed.image_url"
                class="pointer-events-none absolute inset-0 bg-background/62"
                aria-hidden="true"
              />
              <div
                class="h-38 bg-cover bg-center relative overflow-hidden rounded-t-2xl z-10 ring-1 ring-black/10"
                :style="bedCoverImage() ? { backgroundImage: `url('${bedCoverImage()}')` } : {}"
              >
                <div v-if="!bedCoverImage()" class="absolute inset-0 bg-linear-to-br from-primary/20 via-muted/30 to-primary/10 flex items-center justify-center">
                  <span class="material-symbols-outlined text-5xl text-primary">grass</span>
                </div>
                <div class="pointer-events-none absolute inset-0 bg-linear-to-b from-black/18 via-transparent to-black/35" />
                <div class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full border border-white/30 bg-black/25 px-2.5 py-1 text-[11px] font-semibold text-white/95 backdrop-blur-[2px]">
                  <span class="material-symbols-outlined text-[14px]">yard</span>
                  Peenar
                </div>
                <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/85 via-black/52 to-transparent p-3.5">
                  <h2 class="text-xl font-semibold tracking-tight text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.45)]">{{ bed.name }}</h2>
                  <p v-if="bed.location" class="mt-1 inline-flex items-center gap-1 text-sm text-white/90">
                    <span class="material-symbols-outlined text-[15px]">location_on</span>
                    {{ bed.location }}
                  </p>
                </div>
              </div>
              <div class="relative z-10 px-4 pb-3 pt-2" />
            </section>

            <section class="rounded-3xl border border-border/70 bg-card p-3 sm:p-4 shadow-[0_10px_24px_rgba(16,24,40,0.06)]">
              <div class="mb-3 flex items-start justify-between gap-3 px-1">
                <div>
                  <h3 class="text-sm font-semibold text-foreground">Peenra ruudustik</h3>
                  <p class="mt-0.5 text-xs text-muted-foreground">Puuduta ruutu, et lisada või eemaldada taim.</p>
                </div>
              </div>
              <div class="overflow-x-auto overflow-y-visible pb-1 -mx-1 px-1 custom-scrollbar">
                <div
                  class="inline-grid gap-2 sm:gap-2.5 p-3 sm:p-4 rounded-2xl border border-primary/20 bg-linear-to-br from-primary/6 via-background to-muted/20 shadow-soft ring-1 ring-primary/10 w-max min-w-0"
                  :style="{
                    gridTemplateColumns: `repeat(${getBedColumns()}, ${bedCellSize}px)`,
                    gridTemplateRows: `repeat(${getBedLayout().length}, ${bedCellSize}px)`,
                  }"
                >
                  <template v-for="r in range(getBedLayout().length)" :key="r">
                    <template v-for="c in range(getBedColumns())" :key="`${r}-${c}`">
                      <div v-if="plantAt(r, c)" class="relative rounded-xl bg-card border-2 border-primary/35 overflow-hidden group" :style="{ width: `${bedCellSize}px`, height: `${bedCellSize}px` }">
                        <div class="absolute inset-0 bg-cover bg-center" :style="plantAt(r, c)?.image_url ? { backgroundImage: `url('${plantAt(r, c)?.image_url}')` } : {}" />
                        <div class="absolute inset-0 bg-linear-to-t from-black/75 via-black/20 to-transparent" />
                        <span class="absolute bottom-1.5 left-1.5 right-1.5 text-white text-[11px] font-semibold truncate">{{ plantAt(r, c)?.name }}</span>
                        <button type="button" class="absolute top-1 right-1 p-1 rounded-full bg-black/60 text-white opacity-0 group-hover:opacity-100" @click.stop="removePlantFromBed(plantAt(r, c)!)">
                          <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                      </div>
                      <div v-else-if="(getBedLayout()[r]?.[c] ?? 0) === -1" class="rounded-xl border border-border/70 bg-muted/35" :style="{ width: `${bedCellSize}px`, height: `${bedCellSize}px` }" />
                      <div v-else-if="(getBedLayout()[r]?.[c] ?? 1) === 0" class="rounded-xl opacity-0 pointer-events-none" :style="{ width: `${bedCellSize}px`, height: `${bedCellSize}px` }" />
                      <button v-else type="button" class="rounded-xl border border-dashed border-primary/35 bg-primary/8 text-primary/70 hover:bg-primary/14 hover:text-primary transition flex items-center justify-center" :style="{ width: `${bedCellSize}px`, height: `${bedCellSize}px` }" title="Lisa taim sellesse ruutu" @click="cellModal = { row: r, col: c }">
                        <span class="material-symbols-outlined text-xl">add</span>
                      </button>
                    </template>
                  </template>
                </div>
              </div>
            </section>

            <section v-if="plantsWithoutCell().length" class="rounded-3xl border border-border/70 bg-card/95 p-4 shadow-[0_8px_20px_rgba(16,24,40,0.05)]">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Muud taimed peenral</p>
              <ul class="flex flex-wrap gap-2">
                <li v-for="p in plantsWithoutCell()" :key="p.id" class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-muted/35 py-1.5 pl-3 pr-1.5">
                  <span class="text-sm font-medium text-foreground">{{ p.name }}</span>
                  <button type="button" class="rounded-full p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground" @click="removePlantFromBed(p)">
                    <span class="material-symbols-outlined text-lg">close</span>
                  </button>
                </li>
              </ul>
            </section>

            <section class="rounded-3xl border border-border/70 bg-card/95 p-4 shadow-[0_10px_24px_rgba(16,24,40,0.06)]">
              <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-foreground">Märkmed</h3>
                <Link
                  :href="`/calendar/note-form?bed_id=${bed.id}&return_to=${encodeURIComponent(`/beds/${bed.id}`)}`"
                  class="inline-flex items-center gap-1.5 rounded-xl border border-primary bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                >
                  <span class="material-symbols-outlined text-base">add</span>
                  Lisa
                </Link>
              </div>

              <div v-if="props.bedNotes?.length" class="space-y-2.5">
                <article
                  v-for="note in props.bedNotes"
                  :key="note.id"
                  class="rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3 shadow-[0_2px_10px_rgba(16,24,40,0.04)]"
                >
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium text-foreground line-clamp-1">
                      {{ note.title || 'Märge' }}
                    </p>
                    <span class="text-xs text-muted-foreground shrink-0">
                      {{ formatNoteDate(note.note_date) }}
                    </span>
                  </div>
                  <p v-if="note.body" class="mt-1 text-sm text-muted-foreground line-clamp-2">
                    {{ note.body }}
                  </p>
                </article>
              </div>
              <p v-else class="rounded-2xl border border-dashed border-border/60 bg-background/60 px-3.5 py-4 text-sm text-muted-foreground">
                Selle peenra kohta märkmeid veel pole.
              </p>
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

