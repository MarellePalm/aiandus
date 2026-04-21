<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CardActionsMenu from '@/components/CardActionsMenu.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import SearchModal from '@/pages/Seeds/SearchModal.vue';

type PlantInBed = { id: number; name: string; image_url: string | null; position_in_bed: string | null };
type Bed = { id: number; name: string; location: string | null; image_url?: string | null; rows: number; columns: number; layout?: number[][] | null; plants: PlantInBed[] };
type PlantWithoutBed = { id: number; name: string; image_url: string | null; category?: { name: string; slug: string } | null };

const props = defineProps<{
  beds: Bed[];
  plantsWithoutBed: PlantWithoutBed[];
}>();

const breadcrumbs = [{ title: 'Aiaplaan', href: '/map' }];
const showOnboardingHint = computed(
  () => props.beds.length === 0 && props.plantsWithoutBed.length === 0,
);
const showPlantsWithoutBedHint = ref(false);
const PLANTS_WITHOUT_BED_HINT_SEEN_KEY = 'mapPlantsWithoutBedHintSeen';
const showSearch = ref(false);
const searchQuery = ref('');
const FAVORITE_BED_IDS_KEY = 'favoriteBedIds';
const favoriteBedIds = ref<number[]>([]);

type TabKey = 'all' | 'favorites';
const activeTab = ref<TabKey>('all');
const recentFirst = ref(false);

onMounted(() => {
  try {
    const hasSeenHint = localStorage.getItem(PLANTS_WITHOUT_BED_HINT_SEEN_KEY) === '1';
    if (!hasSeenHint) {
      showPlantsWithoutBedHint.value = true;
      localStorage.setItem(PLANTS_WITHOUT_BED_HINT_SEEN_KEY, '1');
    }
  } catch {
    showPlantsWithoutBedHint.value = false;
  }

  try {
    const raw = localStorage.getItem(FAVORITE_BED_IDS_KEY);
    if (!raw) return;
    const parsed = JSON.parse(raw);
    if (Array.isArray(parsed)) {
      favoriteBedIds.value = parsed.map((v) => Number(v)).filter((v) => Number.isInteger(v));
    }
  } catch {
    favoriteBedIds.value = [];
  }
});

const bedNames = computed(() => props.beds.map((b) => b.name));

const filteredBeds = computed(() => {
  let list = [...props.beds];

  if (activeTab.value === 'favorites') {
    list = list.filter((b) => favoriteBedIds.value.includes(b.id));
  }

  if (recentFirst.value) {
    list = list.slice().sort((a, b) => b.id - a.id);
  } else {
    list = list.slice().sort((a, b) => a.name.localeCompare(b.name, 'et'));
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter((b) =>
      b.name.toLowerCase().includes(q) || (b.location ?? '').toLowerCase().includes(q),
    );
  }

  return list;
});

const tabClass = (active: boolean) => {
  const base = 'flex h-9 shrink-0 items-center justify-center rounded-full px-4 text-sm font-medium transition-colors';
  if (active) return `${base} bg-primary text-white`;
  return `${base} bg-primary/10 text-primary hover:bg-primary/15`;
};

function resetToAll() {
  activeTab.value = 'all';
  searchQuery.value = '';
}

function toggleFavoriteBed(id: number) {
  const isFavorite = favoriteBedIds.value.includes(id);
  favoriteBedIds.value = isFavorite
    ? favoriteBedIds.value.filter((x) => x !== id)
    : [...favoriteBedIds.value, id];

  try {
    localStorage.setItem(FAVORITE_BED_IDS_KEY, JSON.stringify(favoriteBedIds.value));
  } catch {
    // ignore storage failures
  }
}

function isFavoriteBed(id: number): boolean {
  return favoriteBedIds.value.includes(id);
}

function editBed(id: number) {
  router.get(`/beds/${id}/edit`);
}

function deleteBed(id: number, name: string) {
  if (!confirm(`Eemaldada peenar "${name}"? Taimed jäävad peenrata.`)) return;
  router.delete(`/beds/${id}`, { preserveScroll: true });
}

function getBedLayout(bed: Bed): number[][] {
  const L = bed.layout;
  if (L && Array.isArray(L) && L.length > 0 && L.some((row) => Array.isArray(row) && row.length > 0)) {
    return L as number[][];
  }
  return Array.from({ length: bed.rows || 1 }, () => Array.from({ length: bed.columns || 1 }, () => 1));
}

function getBedColumns(bed: Bed): number {
  const layout = getBedLayout(bed);
  if (layout.length === 0) return 1;
  return Math.max(...layout.map((r) => r.length), 1);
}

function getBedRows(bed: Bed): number {
  return Math.max(getBedLayout(bed).length, 1);
}

function bedThumbGridStyle(bed: Bed) {
  const cols = getBedColumns(bed);
  const rows = getBedRows(bed);
  const maxSide = Math.max(cols, rows, 1);
  const cellSize = 22 / maxSide;

  return {
    gridTemplateColumns: `repeat(${cols}, ${cellSize}px)`,
    gridTemplateRows: `repeat(${rows}, ${cellSize}px)`,
  };
}

function isLargeBedForThumb(bed: Bed): boolean {
  return Math.max(getBedColumns(bed), getBedRows(bed)) > 6;
}

function bedThumbShapeStyle(bed: Bed) {
  const cols = getBedColumns(bed);
  const rows = getBedRows(bed);
  const maxSide = Math.max(cols, rows, 1);
  const maxPx = 30;

  return {
    width: `${Math.max(12, Math.round((cols / maxSide) * maxPx))}px`,
    height: `${Math.max(12, Math.round((rows / maxSide) * maxPx))}px`,
  };
}

function bedThumbCellClass(bed: Bed, row: number, col: number): string {
  const layoutValue = getBedLayout(bed)[row]?.[col] ?? 1;
  if (layoutValue === -1) return 'bg-transparent border-transparent';
  if (layoutValue === 0) return 'bg-transparent border-transparent';
  return 'bg-primary/15 border-primary/35';
}
</script>

<template>
  <Head title="Aiaplaan" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background text-foreground font-display min-h-screen antialiased">
        <div class="bg-background border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title="Peenrad"
            header-class="pt-6"
            top-row-class="mb-3"
            bottom-row-class="mb-4"
          >
            <template #leading>
              <BackIconButton href="/dashboard" aria-label="Tagasi avalehele" />
            </template>
            <template #actions>
              <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 sm:h-10 sm:w-10"
                @click="showSearch = true"
              >
                <span class="material-symbols-outlined text-xl">search</span>
              </button>
            </template>
            <div class="no-scrollbar flex gap-2 overflow-x-auto pb-2">
              <button :class="tabClass(activeTab === 'all')" type="button" @click="resetToAll">Kõik</button>
              <button :class="tabClass(activeTab === 'favorites')" type="button" @click="activeTab = 'favorites'">Lemmikud</button>
              <button :class="tabClass(recentFirst)" type="button" @click="recentFirst = !recentFirst">Hiljuti lisatud</button>
            </div>
          </DiaryHeader>

          <main class="flex-1 px-6 py-4 md:px-8">
        <div class="space-y-6 sm:space-y-8">
        <section class="space-y-6">
          <div
            v-if="showOnboardingHint"
            class="rounded-2xl border border-primary/20 bg-linear-to-r from-primary/10 via-primary/5 to-transparent px-4 py-3 shadow-sm"
          >
            <p class="text-sm text-foreground/85 leading-relaxed">
              Ava peenar ja kliki tühjal ruudul <strong>+</strong>, et lisada taim. Allpool on taimed, kes ootavad peenrale määramist.
            </p>
          </div>
            <div
              v-if="!props.beds.length"
              class="rounded-2xl border-2 border-dashed border-primary/30 bg-linear-to-br from-muted/30 to-primary/5 p-8 text-center text-muted-foreground"
            >
              Peenraid pole. Lisa esimene peenar paremal alanurgas oleva + nupuga – siis näed teda siin pildiliselt.
            </div>

            <div v-else-if="filteredBeds.length === 0" class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-6 py-8 text-center">
              <p class="text-sm text-muted-foreground">Sobivaid peenraid ei leitud.</p>
            </div>

            <div v-else class="space-y-3">
                <Link
                  v-for="bed in filteredBeds"
                  :key="bed.id"
                  :href="`/beds/${bed.id}`"
                  class="group relative block overflow-hidden rounded-2xl border border-border/70 bg-card px-4 py-3 text-left shadow-soft transition-all duration-200 hover:border-primary/30 hover:bg-muted/20"
                >
                  <div
                    v-if="bed.image_url"
                    class="absolute inset-0 bg-cover bg-center opacity-[0.3] transition-opacity duration-200 group-hover:opacity-[0.36]"
                    :style="{ backgroundImage: `url('${bed.image_url}')` }"
                    aria-hidden="true"
                  />
                  <div
                    v-if="bed.image_url"
                    class="absolute inset-0 bg-background/48"
                    aria-hidden="true"
                  />

                  <div class="relative z-10 flex items-center gap-3">
                    <div class="min-w-0 flex flex-1 items-center gap-3">
                      <div class="h-14 w-14 shrink-0 rounded-xl border border-stone-300/55 bg-stone-100/80 p-1">
                        <div
                          v-if="!isLargeBedForThumb(bed)"
                          class="grid h-full w-full place-content-center gap-[2px]"
                          :style="bedThumbGridStyle(bed)"
                        >
                          <template v-for="(rowData, r) in getBedLayout(bed)" :key="`thumb-row-${bed.id}-${r}`">
                            <span
                              v-for="(_, c) in rowData"
                              :key="`thumb-cell-${bed.id}-${r}-${c}`"
                              class="rounded-[2px] border"
                              :class="bedThumbCellClass(bed, r, c)"
                            />
                          </template>
                        </div>

                        <div
                          v-else
                          class="flex h-full w-full items-center justify-center"
                        >
                          <div
                            class="rounded-[5px] border border-stone-400/65 bg-stone-100/90 relative overflow-hidden"
                            :style="bedThumbShapeStyle(bed)"
                          >
                            <span class="absolute inset-0 opacity-40" style="background-image: repeating-linear-gradient(90deg, rgba(161,161,170,0.35), rgba(161,161,170,0.35) 2px, transparent 2px, transparent 4px);" />
                          </div>
                        </div>
                      </div>

                      <div class="min-w-0">
                        <p class="text-base font-semibold text-foreground truncate">{{ bed.name }}</p>
                        <p v-if="bed.location" class="text-xs text-muted-foreground mt-0.5 truncate">{{ bed.location }}</p>
                      </div>
                    </div>
                    <div class="ml-2 flex shrink-0 items-center gap-2" @click.stop>
                      <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-primary/10 bg-white transition hover:scale-105 hover:bg-primary/5"
                        :class="isFavoriteBed(bed.id) ? 'text-rose-600 shadow-sm' : 'text-foreground/45'"
                        @click.prevent.stop="toggleFavoriteBed(bed.id)"
                        aria-label="Lisa lemmikuks"
                      >
                        <span
                          class="material-symbols-outlined text-[20px] leading-none transition"
                          :style="
                            isFavoriteBed(bed.id)
                              ? { fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24` }
                              : { fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24` }
                          "
                        >
                          favorite
                        </span>
                      </button>
                      <CardActionsMenu
                        placement="inline"
                        @edit="editBed(bed.id)"
                        @delete="deleteBed(bed.id, bed.name)"
                      />
                    </div>
                  </div>
                </Link>
              </div>
        </section>

          <!-- Taimed ilma peenrata -->
          <section class="rounded-2xl border border-border/40 bg-card/70 p-3.5 sm:p-4 shadow-soft">
            <div class="mb-3 border-b border-border/60 pb-3">
              <div class="flex items-center justify-between gap-3">
                <h2 class="text-base sm:text-lg font-semibold">Taimed ilma peenrata</h2>
                <span class="inline-flex items-center rounded-full border border-primary/20 bg-primary/8 px-2.5 py-1 text-[11px] font-semibold text-primary">
                  {{ plantsWithoutBed.length }}
                </span>
              </div>
              <p v-if="showPlantsWithoutBedHint" class="text-xs text-muted-foreground mt-1.5">
                Vali peenral tühi ruut ja lisa taim.
              </p>
            </div>

            <div
              v-if="!plantsWithoutBed.length"
              class="rounded-xl bg-muted/25 py-3 px-4 text-center text-xs text-muted-foreground"
            >
              Kõik taimed on peenrale määratud või sul pole taimi.
            </div>

            <div v-else class="flex gap-3 overflow-x-auto pb-2 pr-16 no-scrollbar snap-x snap-mandatory">
              <article
                v-for="plant in plantsWithoutBed"
                :key="plant.id"
                class="group relative h-36 w-36 shrink-0 snap-start overflow-hidden rounded-2xl border border-border/60 bg-card shadow-soft"
              >
                <div
                  class="absolute inset-0 bg-cover bg-center"
                  :style="plant.image_url ? { backgroundImage: `url('${plant.image_url}')` } : {}"
                />
                <div
                  v-if="!plant.image_url"
                  class="absolute inset-0 flex items-center justify-center bg-linear-to-br from-primary/12 via-muted/40 to-muted/20 text-primary"
                >
                  <span class="material-symbols-outlined text-3xl">eco</span>
                </div>
                <div class="absolute inset-0 bg-linear-to-t from-black/75 via-black/30 to-transparent" />
                <div class="absolute bottom-0 left-0 right-0 p-2.5 backdrop-blur-[1px]">
                  <p class="text-sm font-semibold text-white truncate">{{ plant.name }}</p>
                  <p class="text-[11px] text-white/85 truncate">Lisa peenrale</p>
                </div>
              </article>
            </div>
          </section>
      </div>
          </main>
        </div>

        <FloatingPlusButton
          aria-label="Lisa peenar"
          :size-px="52"
          :icon-size-px="30"
          :bottom-px="112"
          @click="router.visit('/map/beds/new')"
        />

        <BottomNav active="map" />
      </div>
    </div>
    <SearchModal
      v-model:open="showSearch"
      :initial-query="searchQuery"
      :suggestions="bedNames"
      title="Otsi peenraid"
      placeholder="Nt: kurgipeenar, tagaaed..."
      @search="(q) => (searchQuery = q)"
      @clear="searchQuery = ''"
    />
  </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
