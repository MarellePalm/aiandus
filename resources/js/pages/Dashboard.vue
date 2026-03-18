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

function relativeDays(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  const now = new Date();
  const diffMs = now.getTime() - d.getTime();
  const diffDays = Math.floor(diffMs / (24 * 60 * 60 * 1000));
  if (diffDays === 0) return 'Täna';
  if (diffDays === 1) return 'Eile';
  if (diffDays < 7) return `${diffDays} päeva tagasi`;
  if (diffDays < 14) return '1 nädal tagasi';
  if (diffDays < 30) return `${Math.floor(diffDays / 7)} nädalat tagasi`;
  return d.toLocaleDateString('et-EE', { day: 'numeric', month: 'short' });
}

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
type SectionId = 'weather' | 'moon' | 'notes' | 'recent' | 'recentSeeds';
const DEFAULT_ORDER: SectionId[] = ['weather', 'moon', 'notes', 'recent', 'recentSeeds'];

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
    notes: 'Viimased märkmed',
    recent: 'Viimati lisatud taimed',
    recentSeeds: 'Viimati lisatud varud',
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
  { href: '/calendar/note-form', icon: 'calendar_today', label: 'Lisa märkmed' },
  { href: '/plants/create', icon: 'local_florist', label: 'Lisa taim' },
  { href: '/seeds/create', icon: 'inventory_2', label: 'Lisa varu' },
  { href: '/map/beds/new', icon: 'map', label: 'Lisa peenar' },
];
function closeFabMenu() {
  showFabMenu.value = false;
}
function goToFabAction(href: string) {
  closeFabMenu();
  router.visit(href);
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page bg-background-light min-h-0 flex flex-col">
      <div class="bg-background-light border-beige/50 relative mx-auto w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
        <DiaryHeader
          title="Minu Aia Päevik"
          :diary-label="todayLabel"
          :show-diary-label="false"
          header-class="pt-6"
          top-row-class="mb-3"
          bottom-row-class="mb-2"
        >
          <p class="mt-1 text-sm text-muted-foreground">
            {{ todayLabel }}
          </p>
        </DiaryHeader>

        <div v-if="editLayout" class="px-6 pt-3 md:px-8">
          <div class="rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
            <p class="min-w-0">
              <span class="font-semibold text-foreground/90">Muuda avalehte:</span>
              võta ploki ees olevatest täppidest kinni ja lohista järjekorda.
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

        <div class="px-6 pt-4 pb-0 space-y-8 md:px-8">
          <!-- Järjestatavad plokid -->
        <template v-for="id in sectionOrder" :key="id">
          <!-- Weather -->
          <section
            v-if="id === 'weather'"
            class="rounded-2xl border border-border bg-card/90 shadow-sm overflow-hidden"
            :class="editLayout ? 'ring-1 ring-primary/25' : ''"
            @dragover="editLayout ? onDragOver($event) : undefined"
            @drop="editLayout ? onDrop('weather', $event) : undefined"
          >
            <div
              class="group flex items-center gap-2 px-3 py-2 bg-muted/40 border-b border-border cursor-pointer"
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
                <h3 class="font-semibold text-foreground text-xs">{{ sectionTitle('weather') }}</h3>
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
            <div v-show="isSectionExpanded('weather')" class="p-4">
              <DashboardWeather />
            </div>
          </section>

          <!-- Moon -->
          <section
            v-if="id === 'moon'"
            class="rounded-2xl border border-border bg-card/90 shadow-sm overflow-hidden"
            :class="editLayout ? 'ring-1 ring-primary/25' : ''"
            @dragover="editLayout ? onDragOver($event) : undefined"
            @drop="editLayout ? onDrop('moon', $event) : undefined"
          >
            <div
              class="group flex items-center gap-2 px-3 py-2 bg-muted/40 border-b border-border cursor-pointer"
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
                <h3 class="font-semibold text-foreground text-xs">{{ sectionTitle('moon') }}</h3>
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
            <div v-show="isSectionExpanded('moon')" class="p-4">
              <Moon />
            </div>
          </section>

          <!-- Viimased märkmed -->
          <section
            v-if="id === 'notes'"
            class="rounded-2xl border border-border bg-card/90 shadow-sm overflow-hidden"
            :class="editLayout ? 'ring-1 ring-primary/25' : ''"
            @dragover="editLayout ? onDragOver($event) : undefined"
            @drop="editLayout ? onDrop('notes', $event) : undefined"
          >
            <div
              class="group flex items-center gap-2 px-3 py-2 bg-muted/40 border-b border-border cursor-pointer"
              @click="toggleSectionCollapsed('notes')"
            >
              <div class="flex flex-1 min-w-0 items-center gap-2">
                <button
                  v-if="editLayout"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground active:scale-95 transition cursor-grab"
                  draggable="true"
                  aria-label="Lohista plokki"
                  @dragstart="onDragStart('notes', $event)"
                  @dragend="onDragEnd"
                  @click.stop
                >
                  <span class="material-symbols-outlined text-lg">drag_indicator</span>
                </button>
                <h3 class="font-semibold text-foreground text-xs">{{ sectionTitle('notes') }}</h3>
                <span
                  class="material-symbols-outlined text-lg text-muted-foreground shrink-0 transition"
                  :class="[
                    'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100',
                    { 'rotate-180': isSectionExpanded('notes') },
                  ]"
                  aria-hidden="true"
                >
                  expand_more
                </span>
              </div>
            </div>
            <div v-show="isSectionExpanded('notes')" class="p-4">
            <div class="dashboard-panel border-0 shadow-none p-0">
              <div class="flex overflow-x-auto gap-4 pb-2 no-scrollbar">
                <Link
                  v-for="n in recentNotes"
                  :key="n.id"
                  :href="`/calendar/notes/${n.id}/edit`"
                  class="activity-card min-w-[160px] shrink-0"
                >
                  <div
                    class="w-20 h-20 rounded-full mb-3 ring-4 ring-primary/10 bg-secondary flex items-center justify-center bg-cover bg-center"
                    :style="n.media_urls?.length ? { backgroundImage: `url('${n.media_urls[0]}')` } : {}"
                  >
                    <span v-if="!n.media_urls?.length" class="material-symbols-outlined text-3xl text-primary">description</span>
                  </div>
                  <p class="font-bold text-sm truncate">{{ n.title || 'Märge' }}</p>
                  <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">
                    {{ relativeDays(n.note_date) }}
                  </p>
                  <div class="flex gap-1 mt-2">
                    <span class="material-symbols-outlined text-xs">edit_note</span>
                  </div>
                </Link>
              </div>
              <p v-if="recentNotes.length === 0" class="text-sm text-muted-foreground py-4">
                Viimaseid märkmeid pole. Lisa märkmed kalendrist.
              </p>
              <Link
                href="/calendar/overview"
                class="btn-panel-link"
              >
                <span class="material-symbols-outlined text-lg">description</span>
                <span class="font-semibold text-sm">Vaata kõiki märkmeid</span>
                <span class="material-symbols-outlined text-lg ml-auto">chevron_right</span>
              </Link>
            </div>
            </div>
          </section>

          <!-- Viimati lisatud taimed -->
          <section
            v-if="id === 'recent'"
            class="rounded-2xl border border-border bg-card/90 shadow-sm overflow-hidden"
            :class="editLayout ? 'ring-1 ring-primary/25' : ''"
            @dragover="editLayout ? onDragOver($event) : undefined"
            @drop="editLayout ? onDrop('recent', $event) : undefined"
          >
            <div
              class="group flex items-center gap-2 px-3 py-2 bg-muted/40 border-b border-border cursor-pointer"
              @click="toggleSectionCollapsed('recent')"
            >
              <div class="flex flex-1 min-w-0 items-center gap-2">
                <button
                  v-if="editLayout"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground active:scale-95 transition cursor-grab"
                  draggable="true"
                  aria-label="Lohista plokki"
                  @dragstart="onDragStart('recent', $event)"
                  @dragend="onDragEnd"
                  @click.stop
                >
                  <span class="material-symbols-outlined text-lg">drag_indicator</span>
                </button>
                <h3 class="font-semibold text-foreground text-xs">{{ sectionTitle('recent') }}</h3>
                <span
                  class="material-symbols-outlined text-lg text-muted-foreground shrink-0 transition"
                  :class="[
                    'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100',
                    { 'rotate-180': isSectionExpanded('recent') },
                  ]"
                  aria-hidden="true"
                >
                  expand_more
                </span>
              </div>
            </div>
            <div v-show="isSectionExpanded('recent')" class="p-4">
            <div class="dashboard-panel border-0 shadow-none p-0">
              <div class="flex overflow-x-auto gap-4 pb-2 no-scrollbar">
                <Link
                  v-for="p in recentPlants"
                  :key="p.id"
                  :href="`/plants/${p.id}`"
                  class="activity-card min-w-[160px] shrink-0"
                >
                  <div
                    class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-primary/10 bg-muted"
                    :style="p.image_url ? { backgroundImage: `url('${p.image_url}')` } : {}"
                  />
                  <p class="font-bold text-sm">{{ p.name }}</p>
                  <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">
                    {{ relativeDays(p.created_at) }}
                  </p>
                  <div class="flex gap-1 mt-2">
                    <span class="material-symbols-outlined text-xs">local_florist</span>
                  </div>
                </Link>
              </div>
              <p v-if="recentPlants.length === 0" class="text-sm text-muted-foreground py-4">
                Viimati lisatud taimi pole. Lisa taime aia vaatest.
              </p>
              <Link
                href="/plants"
                class="btn-panel-link"
              >
                <span class="material-symbols-outlined text-lg">local_florist</span>
                <span class="font-semibold text-sm">Vaata kõiki taimi</span>
                <span class="material-symbols-outlined text-lg ml-auto">chevron_right</span>
              </Link>
            </div>
            </div>
          </section>

          <!-- Viimati lisatud varud -->
          <section
            v-if="id === 'recentSeeds'"
            class="rounded-2xl border border-border bg-card/90 shadow-sm overflow-hidden last:mb-0"
            :class="editLayout ? 'ring-1 ring-primary/25' : ''"
            @dragover="editLayout ? onDragOver($event) : undefined"
            @drop="editLayout ? onDrop('recentSeeds', $event) : undefined"
          >
            <div
              class="group flex items-center gap-2 px-3 py-2 bg-muted/40 border-b border-border cursor-pointer"
              @click="toggleSectionCollapsed('recentSeeds')"
            >
              <div class="flex flex-1 min-w-0 items-center gap-2">
                <button
                  v-if="editLayout"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground active:scale-95 transition cursor-grab"
                  draggable="true"
                  aria-label="Lohista plokki"
                  @dragstart="onDragStart('recentSeeds', $event)"
                  @dragend="onDragEnd"
                  @click.stop
                >
                  <span class="material-symbols-outlined text-lg">drag_indicator</span>
                </button>
                <h3 class="font-semibold text-foreground text-xs">{{ sectionTitle('recentSeeds') }}</h3>
                <span
                  class="material-symbols-outlined text-lg text-muted-foreground shrink-0 transition"
                  :class="[
                    'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100',
                    { 'rotate-180': isSectionExpanded('recentSeeds') },
                  ]"
                  aria-hidden="true"
                >
                  expand_more
                </span>
              </div>
            </div>
            <div v-show="isSectionExpanded('recentSeeds')" class="p-4">
              <div class="dashboard-panel border-0 shadow-none p-0">
                <div class="flex overflow-x-auto gap-4 pb-2 no-scrollbar">
                  <Link
                    v-for="s in recentSeeds"
                    :key="s.id"
                    :href="`/seeds/${s.id}`"
                    class="activity-card min-w-[160px] shrink-0"
                  >
                    <div
                      class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-primary/10 bg-muted"
                      :style="s.image_url ? { backgroundImage: `url('${s.image_url}')` } : {}"
                    />
                    <p class="font-bold text-sm truncate">{{ s.name }}</p>
                    <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">
                      {{ relativeDays(s.created_at) }}
                    </p>
                    <div class="flex gap-1 mt-2">
                      <span class="material-symbols-outlined text-xs">inventory_2</span>
                    </div>
                  </Link>
                </div>
                <p v-if="recentSeeds.length === 0" class="text-sm text-muted-foreground py-4">
                  Viimati lisatud varusid pole. Lisa varusid varude vaatest.
                </p>
                <Link
                  href="/seeds"
                  class="btn-panel-link"
                >
                  <span class="material-symbols-outlined text-lg">inventory_2</span>
                  <span class="font-semibold text-sm">Vaata kõiki varusid</span>
                  <span class="material-symbols-outlined text-lg ml-auto">chevron_right</span>
                </Link>
              </div>
            </div>
          </section>
        </template>
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

      <BottomNav active="dashboard" :fixed="false" />
    </div>
  </AppLayout>
</template>
