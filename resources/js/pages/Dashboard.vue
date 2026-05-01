<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

import DashboardWeather from '@/components/DashboardWeather.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import Moon from '@/pages/calendarNotes/moon.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const page = usePage();

type RecentNote = { id: number; note_date: string; title?: string | null; type?: string; done?: boolean | null; media_urls?: string[] };
const recentNotes = computed<RecentNote[]>(() => (page.props.recentNotes as RecentNote[] | undefined) ?? []);

type RecentPlant = { id: number; name: string; image_url?: string | null; created_at?: string | null; category?: { name: string; slug: string } | null };
const recentPlants = computed<RecentPlant[]>(() => (page.props.recentPlants as RecentPlant[] | undefined) ?? []);

type RecentSeed = { id: number; name: string; image_url?: string | null; created_at?: string | null; category?: { name: string; slug: string } | null };
const recentSeeds = computed<RecentSeed[]>(() => (page.props.recentSeeds as RecentSeed[] | undefined) ?? []);
const activeTasksCount = computed(() => recentNotes.value.filter((note) => note.done === false).length);

const overviewStats = computed(() => [
  {
    id: 'notes',
    label: 'Värsked märkmed',
    value: recentNotes.value.length,
    hint: recentNotes.value.length ? 'Hiljuti lisatud' : 'Lisa esimene märge',
    icon: 'edit_note',
    href: '/calendar/overview',
  },
  {
    id: 'plants',
    label: 'Uued taimed',
    value: recentPlants.value.length,
    hint: recentPlants.value.length ? 'Hiljuti lisatud' : 'Aed ootab taimi',
    icon: 'local_florist',
    href: '/plants',
  },
  {
    id: 'seeds',
    label: 'Varud',
    value: recentSeeds.value.length,
    hint: recentSeeds.value.length ? 'Hiljuti lisatud' : 'Lisa seemneid või varusid',
    icon: 'shelves',
    href: '/seeds',
  },
]);

const nextAction = computed(() => ({
  title: `${activeTasksCount.value}`,
  body: 'Aktiivset tegevust',
  href: '/calendar',
  cta: 'Ava kalender',
  icon: 'event_upcoming',
}));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard().url }];

const todayLabel = computed(() => {
  const date = new Date();

  const weekday = new Intl.DateTimeFormat('et-EE', { weekday: 'long' }).format(date);
  const weekdayCap = weekday.charAt(0).toUpperCase() + weekday.slice(1);

  const day = new Intl.DateTimeFormat('et-EE', { day: 'numeric' }).format(date);
  const month = new Intl.DateTimeFormat('et-EE', { month: 'long' }).format(date);

  const time = new Intl.DateTimeFormat('et-EE', { hour: '2-digit', minute: '2-digit' }).format(date);

  return `${weekdayCap} ${day}. ${month} • ${time}`;
});

// Järjekord: salvestatakse localStorage'i, kasutaja saab plokke üles/alla tõsta
const STORAGE_KEY = 'dashboardSectionOrder';
const COLLAPSED_KEY = 'dashboardSectionCollapsed';
type SectionId = 'weather' | 'moon';
const DEFAULT_ORDER: SectionId[] = ['weather', 'moon'];

const sectionOrder = ref<SectionId[]>([...DEFAULT_ORDER]);
const collapsedSectionIds = ref<Set<SectionId>>(new Set());
const editLayout = ref(false);
const draggingId = ref<SectionId | null>(null);

function persistCollapsed(next: Iterable<SectionId>) {
  const arr = [...next];
  collapsedSectionIds.value = new Set(arr);
  try {
    localStorage.setItem(COLLAPSED_KEY, JSON.stringify(arr));
  } catch {
    /* ignore */
  }
}

function applyDefaultCollapsed() {
  // Lahti: esimesed 2 plokki (kasutaja järjekorra järgi). Kinni: ülejäänud.
  const open = new Set(sectionOrder.value.slice(0, 2));
  const collapsed = DEFAULT_ORDER.filter((id) => !open.has(id));
  persistCollapsed(collapsed);
}

function ensureFirstTwoExpanded() {
  const mustBeOpen = new Set(sectionOrder.value.slice(0, 2));
  if (mustBeOpen.size === 0) return;

  const nextCollapsed = new Set(collapsedSectionIds.value);
  let changed = false;
  for (const id of mustBeOpen) {
    if (nextCollapsed.delete(id)) changed = true;
  }
  if (!changed) return;
  persistCollapsed(nextCollapsed);
}

function persistOrder(nextOrder: SectionId[]) {
  sectionOrder.value = [...nextOrder];
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(nextOrder));
  } catch {
    /* ignore */
  }
}

function arrayMove<T>(arr: T[], from: number, to: number) {
  if (from === to) return arr;
  const copy = [...arr];
  const [item] = copy.splice(from, 1);
  copy.splice(to, 0, item);
  return copy;
}

function canMoveSectionUp(id: SectionId): boolean {
  return sectionOrder.value.indexOf(id) > 0;
}

function canMoveSectionDown(id: SectionId): boolean {
  const index = sectionOrder.value.indexOf(id);
  return index !== -1 && index < sectionOrder.value.length - 1;
}

function moveSection(id: SectionId, direction: 'up' | 'down') {
  const from = sectionOrder.value.indexOf(id);
  if (from === -1) return;

  const to = direction === 'up' ? from - 1 : from + 1;
  if (to < 0 || to >= sectionOrder.value.length) return;

  persistOrder(arrayMove(sectionOrder.value, from, to));
}

function onDragStart(id: SectionId, e: DragEvent) {
  draggingId.value = id;
  try {
    e.dataTransfer?.setData('text/plain', id);
    e.dataTransfer?.setDragImage(new Image(), 0, 0);
    e.dataTransfer!.effectAllowed = 'move';
  } catch {
    /* ignore */
  }
}

function onDragEnd() {
  draggingId.value = null;
}

function onDrop(targetId: SectionId, e: DragEvent) {
  e.preventDefault();
  const sourceId = (e.dataTransfer?.getData('text/plain') as SectionId) || draggingId.value;
  if (!sourceId || sourceId === targetId) return;
  const from = sectionOrder.value.indexOf(sourceId);
  const to = sectionOrder.value.indexOf(targetId);
  if (from === -1 || to === -1) return;
  persistOrder(arrayMove(sectionOrder.value, from, to));
  draggingId.value = null;
}

function onDragOver(e: DragEvent) {
  e.preventDefault();
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
}

function isSectionExpanded(id: SectionId): boolean {
  return !collapsedSectionIds.value.has(id);
}
function toggleSectionCollapsed(id: SectionId) {
  const next = new Set(collapsedSectionIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  collapsedSectionIds.value = next;
  try {
    localStorage.setItem(COLLAPSED_KEY, JSON.stringify([...next]));
  } catch {
    /* ignore */
  }
}
function sectionTitle(id: SectionId): string {
  const titles: Record<SectionId, string> = {
    weather: 'Ilm',
    moon: 'Kuufaas täna',
  };
  return titles[id];
}

onMounted(() => {
  try {
    const params = new URLSearchParams(window.location.search);
    editLayout.value = params.get('layout') === 'edit';
  } catch {
    /* ignore */
  }

  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw) as unknown;
      if (Array.isArray(parsed) && parsed.every((id) => DEFAULT_ORDER.includes(id as SectionId))) {
        const order = parsed as SectionId[];
        const missing = DEFAULT_ORDER.filter((id) => !order.includes(id));
        sectionOrder.value = [...order, ...missing];
      }
    }
    const collapsedRaw = localStorage.getItem(COLLAPSED_KEY);
    if (collapsedRaw) {
      const arr = JSON.parse(collapsedRaw) as unknown;
      if (Array.isArray(arr) && arr.every((id) => DEFAULT_ORDER.includes(id as SectionId))) {
        collapsedSectionIds.value = new Set(arr as SectionId[]);
      }
    } else {
      // Esimesel avamisel jätame lahti 2 esimest plokki (järjekorra järgi).
      const open = new Set(sectionOrder.value.slice(0, 2));
      const defaultCollapsed: SectionId[] = DEFAULT_ORDER.filter((id) => !open.has(id));
      collapsedSectionIds.value = new Set(defaultCollapsed);
      try {
        localStorage.setItem(COLLAPSED_KEY, JSON.stringify(defaultCollapsed));
      } catch {
        /* ignore */
      }
    }

    // Igal avamisel hoiame 2 esimest plokki lahti (isegi kui localStorage ütleb teisiti).
    ensureFirstTwoExpanded();
  } catch {
    /* ignore */
  }
});

// Lumememma-stiilis + nupp: väikesed ümarad kiirtegevused selle kohal
const showFabMenu = ref(false);
const fabActions = [
  { href: '/calendar/note-form', icon: 'edit_note', label: 'Lisa märkmed' },
  { href: '/plants/create', icon: 'local_florist', label: 'Lisa taim' },
  { href: '/seeds/create', icon: 'shelves', label: 'Lisa varu' },
  { href: '/map/beds/new', icon: 'map', label: 'Lisa peenar' },
];
function closeFabMenu() {
  showFabMenu.value = false;
}
function goToFabAction(href: string) {
  closeFabMenu();
  router.visit(href);
}

/** Ühine päiseriba: ilm + kuu (sama primary/muted gradient). */
const dashboardSectionHeaderStrip =
  'from-primary/22 via-primary/10 to-muted/50 dark:from-primary/18 dark:via-muted/25 dark:to-card/95';

function sectionAccent(_id: SectionId): string {
  return dashboardSectionHeaderStrip;
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav bg-muted/30 min-h-0 flex flex-col">
      <div class="bg-muted/20 border-beige/50 relative mx-auto w-full max-w-[480px] overflow-x-clip border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
        <DiaryHeader
          title="Minu Aiapäevik"
          :diary-label="todayLabel"
          :show-diary-label="false"
          header-class="pt-6"
          top-row-class="mb-3"
          bottom-row-class="mb-2"
        >
          <div class="space-y-4">
            <div class="rounded-[1.75rem] border border-primary/15 bg-linear-to-br from-primary/12 via-background to-secondary/35 p-5 shadow-sm">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">
                    Tänane ülevaade
                  </p>
                  <p class="mt-3 text-sm text-muted-foreground">{{ todayLabel }}</p>
                </div>
              </div>

              <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div
                  v-for="stat in overviewStats"
                  :key="stat.id"
                  class="rounded-2xl border border-border/70 bg-card/85 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md"
                >
                  <Link :href="stat.href" class="block">
                    <div class="flex items-start justify-between gap-3">
                      <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                          {{ stat.label }}
                        </p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-foreground">{{ stat.value }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ stat.hint }}</p>
                      </div>
                      <span class="material-symbols-outlined rounded-2xl bg-primary/10 p-2 text-primary">
                        {{ stat.icon }}
                      </span>
                    </div>
                  </Link>
                </div>
              </div>

              <Link
                :href="nextAction.href"
                class="mt-3 block rounded-2xl border border-border/70 bg-card/85 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Tegevused</p>
                    <p class="mt-2 text-xl font-bold tracking-tight text-foreground">{{ nextAction.title }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ nextAction.body }}</p>
                  </div>
                  <span class="material-symbols-outlined rounded-2xl bg-primary/10 p-2 text-primary">
                    {{ nextAction.icon }}
                  </span>
                </div>
              </Link>
            </div>

          </div>
        </DiaryHeader>

        <div v-if="editLayout" class="px-6 pt-3 md:px-8">
          <div class="rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
            <p class="min-w-0">
              <span class="font-semibold text-foreground/90">Muuda alumiste plokkide järjekorda:</span>
              tõsta `Ilm` ja `Kuufaas täna` endale sobivasse järjestusse.
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full bg-card px-3 py-1.5 text-xs font-semibold text-foreground ring-1 ring-border hover:bg-muted transition"
                aria-label="Taasta vaikimisi (2 esimest plokki lahti)"
                @click="applyDefaultCollapsed"
              >
                <span class="material-symbols-outlined text-base">restart_alt</span>
                Taasta
              </button>
              <button
                type="button"
                class="ml-auto inline-flex items-center gap-2 rounded-full bg-card px-3 py-1.5 text-xs font-semibold text-foreground ring-1 ring-border hover:bg-muted transition"
                aria-label="Valmis (välju muutmisrežiimist)"
                @click="router.visit('/dashboard')"
              >
                <span class="material-symbols-outlined text-base">check</span>
                Valmis
              </button>
            </div>
          </div>
        </div>

        <div class="px-6 pt-4 pb-24 md:px-8">
          <div class="rounded-[1.75rem] border border-border/70 bg-card/55 p-3 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3 px-2">
            </div>

            <div class="space-y-6">
          <!-- Järjestatavad plokid -->
        <template v-for="id in sectionOrder" :key="id">
          <!-- Weather -->
          <section
            v-if="id === 'weather'"
            class="overflow-hidden rounded-[1.6rem] border border-border bg-card/95 shadow-sm"
            :class="editLayout ? 'ring-1 ring-primary/25' : ''"
            @dragover="editLayout ? onDragOver($event) : undefined"
            @drop="editLayout ? onDrop('weather', $event) : undefined"
          >
            <div
              class="group flex cursor-pointer items-center gap-2 border-b border-border bg-linear-to-r px-4 py-3"
              :class="sectionAccent('weather')"
              @click="toggleSectionCollapsed('weather')"
            >
              <div class="flex flex-1 min-w-0 items-center gap-2">
                <button
                  v-if="editLayout"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground active:scale-95 transition cursor-grab"
                  draggable="true"
                  aria-label="Lohista plokki"
                  @dragstart="onDragStart('weather', $event)"
                  @dragend="onDragEnd"
                  @click.stop
                >
                  <span class="material-symbols-outlined text-lg">drag_indicator</span>
                </button>
                <h3 class="font-semibold text-foreground text-sm">{{ sectionTitle('weather') }}</h3>
                <div v-if="editLayout" class="ml-auto flex items-center gap-1">
                  <button
                    type="button"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                    :disabled="!canMoveSectionUp('weather')"
                    @click.stop="moveSection('weather', 'up')"
                  >
                    <span class="material-symbols-outlined text-sm">arrow_upward</span>
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                    :disabled="!canMoveSectionDown('weather')"
                    @click.stop="moveSection('weather', 'down')"
                  >
                    <span class="material-symbols-outlined text-sm">arrow_downward</span>
                  </button>
                </div>
                <span
                  class="material-symbols-outlined text-lg text-muted-foreground shrink-0 transition"
                  :class="[
                    'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100',
                    { 'rotate-180': isSectionExpanded('weather') },
                  ]"
                  aria-hidden="true"
                >
                  expand_more
                </span>
              </div>
            </div>
            <div v-show="isSectionExpanded('weather')" class="p-4 sm:p-5">
              <DashboardWeather />
            </div>
          </section>

          <!-- Moon -->
          <section
            v-if="id === 'moon'"
            class="overflow-hidden rounded-[1.6rem] border border-border bg-card/95 shadow-sm"
            :class="editLayout ? 'ring-1 ring-primary/25' : ''"
            @dragover="editLayout ? onDragOver($event) : undefined"
            @drop="editLayout ? onDrop('moon', $event) : undefined"
          >
            <div
              class="group flex cursor-pointer items-center gap-2 border-b border-border bg-linear-to-r px-4 py-3"
              :class="sectionAccent('moon')"
              @click="toggleSectionCollapsed('moon')"
            >
              <div class="flex flex-1 min-w-0 items-center gap-2">
                <button
                  v-if="editLayout"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground active:scale-95 transition cursor-grab"
                  draggable="true"
                  aria-label="Lohista plokki"
                  @dragstart="onDragStart('moon', $event)"
                  @dragend="onDragEnd"
                  @click.stop
                >
                  <span class="material-symbols-outlined text-lg">drag_indicator</span>
                </button>
                <h3 class="font-semibold text-foreground text-sm">{{ sectionTitle('moon') }}</h3>
                <div v-if="editLayout" class="ml-auto flex items-center gap-1">
                  <button
                    type="button"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                    :disabled="!canMoveSectionUp('moon')"
                    @click.stop="moveSection('moon', 'up')"
                  >
                    <span class="material-symbols-outlined text-sm">arrow_upward</span>
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                    :disabled="!canMoveSectionDown('moon')"
                    @click.stop="moveSection('moon', 'down')"
                  >
                    <span class="material-symbols-outlined text-sm">arrow_downward</span>
                  </button>
                </div>
                <span
                  class="material-symbols-outlined text-lg text-muted-foreground shrink-0 transition"
                  :class="[
                    'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100',
                    { 'rotate-180': isSectionExpanded('moon') },
                  ]"
                  aria-hidden="true"
                >
                  expand_more
                </span>
              </div>
            </div>
            <div v-show="isSectionExpanded('moon')" class="p-4 sm:p-5">
              <Moon />
            </div>
          </section>

        </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Lumememma-stiilis + nupp: kiirtegevused ümardatud ikoonidena selle kohal -->
      <div class="fixed right-4 bottom-24 z-30 flex flex-col-reverse items-end gap-3">
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 translate-y-2"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="opacity-0 translate-y-2"
        >
          <div v-if="showFabMenu" class="flex flex-col-reverse items-center gap-2">
            <button
              v-for="action in fabActions"
              :key="action.href"
              type="button"
              :aria-label="action.label"
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-card border border-border text-primary shadow-md hover:bg-muted hover:scale-110 active:scale-95 transition"
              @click="goToFabAction(action.href)"
            >
              <span class="material-symbols-outlined text-xl">{{ action.icon }}</span>
            </button>
          </div>
        </Transition>
        <button
          type="button"
          aria-label="Lisa (märkmed, taim, varud, peenar)"
          :aria-expanded="showFabMenu"
          class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg hover:scale-105 active:scale-95 transition"
          @click="showFabMenu = !showFabMenu"
        >
          <span class="material-symbols-outlined text-3xl font-light">add</span>
        </button>
      </div>
      <div
        v-if="showFabMenu"
        class="fixed inset-0 z-20 bg-black/20"
        aria-hidden="true"
        @click="closeFabMenu"
      />

      <BottomNav active="dashboard" />
    </div>
  </AppLayout>
</template>
