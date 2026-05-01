<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import SearchModal from '@/pages/Seeds/SearchModal.vue';

type PlantInBed = { id: number; name: string; image_url: string | null; position_in_bed: string | null };
type Bed = {
  id: number;
  name: string;
  location: string | null;
  image_url?: string | null;
  rows: number;
  columns: number;
  garden_x: number;
  garden_y: number;
  layout?: number[][] | null;
  plants: PlantInBed[];
};
type PlantWithoutBed = { id: number; name: string; image_url: string | null; category?: { name: string; slug: string } | null };

const props = defineProps<{
  beds: Bed[];
  plantsWithoutBed: PlantWithoutBed[];
}>();

const breadcrumbs = [{ title: 'Aiaplaan', href: '/map' }];
const showOnboardingHint = computed(() => props.beds.length === 0 && props.plantsWithoutBed.length === 0);
const showPlantsWithoutBedHint = ref(false);
const PLANTS_WITHOUT_BED_HINT_SEEN_KEY = 'mapPlantsWithoutBedHintSeen';
const showSearch = ref(false);
const searchQuery = ref('');
const FAVORITE_BED_IDS_KEY = 'favoriteBedIds';
const favoriteBedIds = ref<number[]>([]);

type TabKey = 'all' | 'favorites';
const activeTab = ref<TabKey>('all');
const recentFirst = ref(false);

const GARDEN_WIDTH = 980;
const GARDEN_HEIGHT = 760;
const GARDEN_PADDING = 24;

const localPositions = ref<Record<number, { x: number; y: number }>>({});
const draggingBedId = ref<number | null>(null);
const dragMoved = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const dragPointerId = ref<number | null>(null);
const selectedBedId = ref<number | null>(null);

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

  syncPositionsFromProps();
  selectedBedId.value = props.beds[0]?.id ?? null;
});

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', onPointerMove);
  window.removeEventListener('pointerup', stopDragging);
  window.removeEventListener('pointercancel', stopDragging);
});

function syncPositionsFromProps() {
  const next: Record<number, { x: number; y: number }> = {};
  props.beds.forEach((bed) => {
    next[bed.id] = {
      x: bed.garden_x ?? GARDEN_PADDING,
      y: bed.garden_y ?? GARDEN_PADDING,
    };
  });
  localPositions.value = next;
}

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

const selectedBed = computed(() => {
  const fallbackId = filteredBeds.value[0]?.id ?? props.beds[0]?.id ?? null;
  const id = selectedBedId.value ?? fallbackId;
  return props.beds.find((bed) => bed.id === id) ?? null;
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

function bedPreviewGridStyle(bed: Bed) {
  const cols = getBedColumns(bed);
  const rows = getBedRows(bed);

  return {
    gridTemplateColumns: `repeat(${cols}, 18px)`,
    gridTemplateRows: `repeat(${rows}, 18px)`,
  };
}

function bedPreviewCellClass(bed: Bed, row: number, col: number): string {
  const layoutValue = getBedLayout(bed)[row]?.[col] ?? 1;
  if (layoutValue === -1 || layoutValue === 0) return 'bg-transparent border-transparent';
  return 'bg-[linear-gradient(180deg,rgba(145,101,67,0.95),rgba(109,76,49,0.98))] border-amber-900/15';
}

function bedCardSize(bed: Bed) {
  const cols = getBedColumns(bed);
  const rows = getBedRows(bed);

  return {
    width: Math.min(148, Math.max(48, cols * 18)),
    height: Math.min(148, Math.max(48, rows * 18)),
  };
}

function clampBedPosition(bed: Bed, x: number, y: number) {
  const size = bedCardSize(bed);
  return {
    x: Math.max(GARDEN_PADDING, Math.min(x, GARDEN_WIDTH - size.width - GARDEN_PADDING)),
    y: Math.max(GARDEN_PADDING, Math.min(y, GARDEN_HEIGHT - size.height - GARDEN_PADDING)),
  };
}

function getBedPosition(bed: Bed) {
  const stored = localPositions.value[bed.id] ?? { x: bed.garden_x ?? GARDEN_PADDING, y: bed.garden_y ?? GARDEN_PADDING };
  return clampBedPosition(bed, stored.x, stored.y);
}

function plannerBedStyle(bed: Bed) {
  const position = getBedPosition(bed);
  const size = bedCardSize(bed);

  return {
    left: `${position.x}px`,
    top: `${position.y}px`,
    width: `${size.width}px`,
    minHeight: `${size.height}px`,
  };
}

function openBed(bedId: number) {
  if (dragMoved.value) return;
  selectedBedId.value = bedId;
}

function startDragging(bed: Bed, event: PointerEvent) {
  const target = event.target as HTMLElement | null;
  if (target?.closest('[data-no-drag="true"]')) return;

  const current = getBedPosition(bed);
  draggingBedId.value = bed.id;
  dragMoved.value = false;
  dragPointerId.value = event.pointerId;
  dragOffset.value = {
    x: event.clientX - current.x,
    y: event.clientY - current.y,
  };

  window.addEventListener('pointermove', onPointerMove);
  window.addEventListener('pointerup', stopDragging);
  window.addEventListener('pointercancel', stopDragging);
}

function onPointerMove(event: PointerEvent) {
  if (draggingBedId.value === null) return;

  const bed = props.beds.find((item) => item.id === draggingBedId.value);
  if (!bed) return;

  dragMoved.value = true;
  const next = clampBedPosition(bed, event.clientX - dragOffset.value.x, event.clientY - dragOffset.value.y);

  localPositions.value = {
    ...localPositions.value,
    [bed.id]: next,
  };
}

function stopDragging() {
  window.removeEventListener('pointermove', onPointerMove);
  window.removeEventListener('pointerup', stopDragging);
  window.removeEventListener('pointercancel', stopDragging);

  if (draggingBedId.value === null) return;

  const bedId = draggingBedId.value;
  draggingBedId.value = null;
  dragPointerId.value = null;

  const position = localPositions.value[bedId];
  if (!position) return;

  router.put(
    `/beds/${bedId}`,
    {
      garden_x: Math.round(position.x),
      garden_y: Math.round(position.y),
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onFinish: () => {
        setTimeout(() => {
          dragMoved.value = false;
        }, 80);
      },
    },
  );
}

function plannerSurfaceStyle() {
  return {
    width: `${GARDEN_WIDTH}px`,
    height: `${GARDEN_HEIGHT}px`,
  };
}

function goToSelectedBed() {
  if (!selectedBed.value) return;
  router.get(`/beds/${selectedBed.value.id}`);
}
</script>

<template>
  <Head title="Aiaplaan" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background text-foreground font-display min-h-screen antialiased">
        <div class="bg-background border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title="Aiaplaan"
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

            <div class="space-y-3">
              <div class="rounded-[1.75rem] border border-primary/15 bg-linear-to-br from-primary/12 via-background to-secondary/35 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <p class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">
                      Minu aed
                    </p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-foreground">
                      Sätti peenrad aias täpselt sinna, kuhu need päriselt kuuluvad.
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-foreground/75">
                      Lohista peenraid ringi nagu miniatuurses aiaplaani tööriistas. Ava peenar, et selle kuju ja taimed täpsemalt paika panna.
                    </p>
                  </div>
                  <div class="hidden shrink-0 rounded-[1.5rem] border border-primary/15 bg-card/80 p-3 shadow-sm sm:flex sm:flex-col sm:items-center sm:justify-center">
                    <span class="material-symbols-outlined text-[2.4rem] text-primary">yard</span>
                    <span class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-primary/80">Planeeri</span>
                  </div>
                </div>
              </div>

              <div class="no-scrollbar flex gap-2 overflow-x-auto pb-2">
                <button :class="tabClass(activeTab === 'all')" type="button" @click="resetToAll">Kõik</button>
                <button :class="tabClass(activeTab === 'favorites')" type="button" @click="activeTab = 'favorites'">Lemmikud</button>
                <button :class="tabClass(recentFirst)" type="button" @click="recentFirst = !recentFirst">Hiljuti lisatud</button>
              </div>
            </div>
          </DiaryHeader>

          <main class="flex-1 px-6 py-4 md:px-8">
            <div class="space-y-6 sm:space-y-8">
              <section class="space-y-6">
                <div
                  v-if="showOnboardingHint"
                  class="rounded-2xl border border-primary/20 bg-linear-to-r from-primary/10 via-primary/5 to-transparent px-4 py-3 shadow-sm"
                >
                  <p class="text-sm leading-relaxed text-foreground/85">
                    Lisa esmalt peenar, seejärel saad teda aias ringi tõsta ja hiljem avada, et ruudustik ning taimed täpsemalt paika panna.
                  </p>
                </div>

                <div
                  class="rounded-[2rem] border border-border/70 bg-card/75 p-4 shadow-soft sm:p-5"
                >
                  <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <h3 class="text-lg font-semibold text-foreground">Miniatuurne aiaplaan</h3>
                      <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        Lohista peenraid kaardil ringi. Paigutus salvestatakse automaatselt.
                      </p>
                    </div>
                    <div class="flex items-center gap-2 rounded-2xl border border-border/70 bg-background/80 px-3 py-2 text-xs text-muted-foreground shadow-xs">
                      <span class="material-symbols-outlined rounded-xl bg-primary/10 p-2 text-primary">forest</span>
                      <div>
                        Peenraid aias
                        <div class="text-base font-semibold text-foreground">{{ filteredBeds.length }}</div>
                      </div>
                    </div>
                  </div>

                  <div
                    v-if="!props.beds.length"
                    class="rounded-[1.75rem] border-2 border-dashed border-primary/30 bg-linear-to-br from-muted/25 to-primary/6 p-8 text-center text-muted-foreground"
                  >
                    Lisa esimene peenar ja sellest saab sinu aiaplaani esimene ehitusklots.
                  </div>

                  <div
                    v-else-if="filteredBeds.length === 0"
                    class="rounded-[1.75rem] border border-dashed border-primary/30 bg-primary/5 px-6 py-8 text-center"
                  >
                    <p class="text-sm text-muted-foreground">Valitud filtriga peenraid ei leitud.</p>
                  </div>

                  <div
                    v-else
                    class="overflow-x-auto rounded-[1.75rem] border border-border/80 bg-[linear-gradient(180deg,rgba(251,248,241,0.98),rgba(244,239,229,0.98))] p-3 shadow-inner sm:p-4"
                  >
                    <div
                      class="relative overflow-hidden rounded-[1.6rem] border border-emerald-900/10 bg-[radial-gradient(circle_at_top,_rgba(185,214,160,0.22),_transparent_32%),repeating-linear-gradient(0deg,rgba(95,135,80,0.06),rgba(95,135,80,0.06)_1px,transparent_1px,transparent_44px),repeating-linear-gradient(90deg,rgba(95,135,80,0.06),rgba(95,135,80,0.06)_1px,transparent_1px,transparent_44px),linear-gradient(180deg,rgba(239,247,232,0.96),rgba(228,239,219,0.98))]"
                      :style="plannerSurfaceStyle()"
                    >
                      <div class="pointer-events-none absolute inset-x-6 top-4 flex items-center justify-between text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-900/45">
                        <span>Aed</span>
                        <span>Lohista peenraid</span>
                      </div>

                      <article
                        v-for="bed in filteredBeds"
                        :key="bed.id"
                        class="absolute transition-transform duration-150"
                        :class="[
                          draggingBedId === bed.id ? 'z-30 scale-[1.02]' : 'z-10 hover:z-20 hover:-translate-y-1',
                        ]"
                        :style="plannerBedStyle(bed)"
                        @pointerdown="startDragging(bed, $event)"
                        @click="openBed(bed.id)"
                      >
                        <div class="relative z-10">
                          <div
                            class="grid place-content-center gap-[3px] rounded-[0.9rem] transition"
                            :class="selectedBed?.id === bed.id ? 'bg-emerald-200/30 ring-2 ring-emerald-400/55 ring-offset-4 ring-offset-[#eef4e6]' : ''"
                            :style="bedPreviewGridStyle(bed)"
                          >
                            <template v-for="(rowData, r) in getBedLayout(bed)" :key="`plan-row-${bed.id}-${r}`">
                              <span
                                v-for="(_, c) in rowData"
                                :key="`plan-cell-${bed.id}-${r}-${c}`"
                                class="rounded-[4px] border"
                                :class="bedPreviewCellClass(bed, r, c)"
                              />
                            </template>
                          </div>
                        </div>
                      </article>
                    </div>
                  </div>

                  <div
                    v-if="selectedBed"
                    class="mt-4 rounded-[1.5rem] border border-border/80 bg-card/95 p-4 shadow-sm"
                  >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Valitud peenar</p>
                        <h4 class="mt-1 text-lg font-semibold text-foreground">{{ selectedBed.name }}</h4>
                        <p class="mt-1 text-sm text-muted-foreground">
                          {{ selectedBed.location || 'Asukoht lisamata' }}
                        </p>
                      </div>
                      <div class="flex items-center gap-2" data-no-drag="true">
                        <button
                          type="button"
                          class="flex h-10 w-10 items-center justify-center rounded-full border border-primary/10 bg-white/90 transition hover:scale-105 hover:bg-primary/5"
                          :class="isFavoriteBed(selectedBed.id) ? 'text-rose-600 shadow-sm' : 'text-foreground/45'"
                          @click.prevent.stop="toggleFavoriteBed(selectedBed.id)"
                        >
                          <span
                            class="material-symbols-outlined text-[20px] leading-none"
                            :style="
                              isFavoriteBed(selectedBed.id)
                                ? { fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24` }
                                : { fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24` }
                            "
                          >
                            favorite
                          </span>
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
                          @click="goToSelectedBed"
                        >
                          Ava peenar
                          <span class="material-symbols-outlined text-base">arrow_forward</span>
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                          @click="editBed(selectedBed.id)"
                        >
                          Muuda
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                          @click="deleteBed(selectedBed.id, selectedBed.name)"
                        >
                          Kustuta
                        </button>
                      </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Kuju</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">
                          {{ getBedRows(selectedBed) }} × {{ getBedColumns(selectedBed) }}
                        </p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Taimi</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">{{ selectedBed.plants.length }}</p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Järgmine samm</p>
                        <p class="mt-2 text-sm text-foreground/80">Ava peenar ja paiguta taimed ruutudesse.</p>
                      </div>
                    </div>
                  </div>
                </div>
              </section>

              <section class="rounded-2xl border border-border/40 bg-card/70 p-3.5 shadow-soft sm:p-4">
                <div class="mb-3 border-b border-border/60 pb-3">
                  <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold sm:text-lg">Taimed ilma peenrata</h2>
                    <span class="inline-flex items-center rounded-full border border-primary/20 bg-primary/8 px-2.5 py-1 text-[11px] font-semibold text-primary">
                      {{ plantsWithoutBed.length }}
                    </span>
                  </div>
                  <p v-if="showPlantsWithoutBedHint" class="mt-1.5 text-xs text-muted-foreground">
                    Kui peenrad on aias paigas, ava sobiv peenar ja määra taimed sinna ruutude kaupa.
                  </p>
                </div>

                <div
                  v-if="!plantsWithoutBed.length"
                  class="rounded-xl bg-muted/25 px-4 py-3 text-center text-xs text-muted-foreground"
                >
                  Kõik taimed on peenrale määratud või sul pole taimi.
                </div>

                <div v-else class="no-scrollbar flex gap-3 overflow-x-auto snap-x snap-mandatory pb-2 pr-16">
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
                    <div class="absolute inset-x-0 bottom-0 p-2.5 backdrop-blur-[1px]">
                      <p class="truncate text-sm font-semibold text-white">{{ plant.name }}</p>
                      <p class="truncate text-[11px] text-white/85">Lisa sobivasse peenrasse</p>
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
      placeholder="Nt: ürdid, tagaaed..."
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
