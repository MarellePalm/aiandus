<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

import DashboardWeather from '@/components/DashboardWeather.vue';
import PrimaryCtaButton from '@/components/PrimaryCtaButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import Moon from '@/pages/calendarNotes/moon.vue';
import UserMenu from '@/pages/UserMenu.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const page = usePage();
const user = page.props.auth.user;

type RecentNote = { id: number; note_date: string; title?: string | null; type?: string; done?: boolean | null; media_urls?: string[] };
const recentNotes = computed<RecentNote[]>(() => (page.props.recentNotes as RecentNote[] | undefined) ?? []);

type RecentPlant = { id: number; name: string; image_url?: string | null; created_at?: string | null; category?: { name: string; slug: string } | null };
const recentPlants = computed<RecentPlant[]>(() => (page.props.recentPlants as RecentPlant[] | undefined) ?? []);

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

function onAddNote() {
  router.visit('/calendar/note-form');
}

// Järjekord: salvestatakse localStorage'i, kasutaja saab plokke üles/alla tõsta
const STORAGE_KEY = 'dashboardSectionOrder';
type SectionId = 'weather' | 'addNote' | 'moon' | 'notes' | 'recent';
const DEFAULT_ORDER: SectionId[] = ['weather', 'addNote', 'moon', 'notes', 'recent'];

const sectionOrder = ref<SectionId[]>([...DEFAULT_ORDER]);

onMounted(() => {
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
  } catch {
    /* ignore */
  }
});

function moveSection(id: SectionId, direction: 'up' | 'down') {
  const arr = sectionOrder.value;
  const i = arr.indexOf(id);
  if (i === -1) return;
  const j = direction === 'up' ? i - 1 : i + 1;
  if (j < 0 || j >= arr.length) return;
  [arr[i], arr[j]] = [arr[j], arr[i]];
  sectionOrder.value = [...arr];
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
  } catch {
    /* ignore */
  }
}

function canMoveUp(id: SectionId) {
  return sectionOrder.value.indexOf(id) > 0;
}
function canMoveDown(id: SectionId) {
  const i = sectionOrder.value.indexOf(id);
  return i >= 0 && i < sectionOrder.value.length - 1;
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="space-y-8 py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <section class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h1 class="text-3xl font-bold tracking-tight text-primary">
              Tere, {{ user?.name }}!
            </h1>
            <p class="mt-1 text-lg italic opacity-80">
              {{ todayLabel }}
            </p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <UserMenu settings-href="/settings" />
          </div>
        </section>

        <!-- Järjestatavad plokid -->
        <template v-for="id in sectionOrder" :key="id">
          <!-- Weather -->
          <section v-if="id === 'weather'" class="relative">
            <div class="flex justify-end gap-1 mb-2">
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki üles"
                :disabled="!canMoveUp('weather')"
                @click="moveSection('weather', 'up')"
              >
                <span class="material-symbols-outlined text-lg">expand_less</span>
              </button>
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki alla"
                :disabled="!canMoveDown('weather')"
                @click="moveSection('weather', 'down')"
              >
                <span class="material-symbols-outlined text-lg">expand_more</span>
              </button>
            </div>
            <DashboardWeather />
          </section>

          <!-- Lisa märkmed -->
          <section v-if="id === 'addNote'">
            <div class="flex justify-end gap-1 mb-2">
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki üles"
                :disabled="!canMoveUp('addNote')"
                @click="moveSection('addNote', 'up')"
              >
                <span class="material-symbols-outlined text-lg">expand_less</span>
              </button>
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki alla"
                :disabled="!canMoveDown('addNote')"
                @click="moveSection('addNote', 'down')"
              >
                <span class="material-symbols-outlined text-lg">expand_more</span>
              </button>
            </div>
            <PrimaryCtaButton
              label="Lisa märkmed"
              icon="edit"
              :full-width="true"
              type="button"
              @click="onAddNote"
            />
          </section>

          <!-- Moon -->
          <section v-if="id === 'moon'">
            <div class="flex items-center justify-between gap-2 mb-3">
              <h3 class="text-lg font-bold">Kuufaas täna</h3>
              <div class="flex gap-1">
                <button
                  type="button"
                  class="section-reorder-btn"
                  aria-label="Tõsta plokki üles"
                  :disabled="!canMoveUp('moon')"
                  @click="moveSection('moon', 'up')"
                >
                  <span class="material-symbols-outlined text-lg">expand_less</span>
                </button>
                <button
                  type="button"
                  class="section-reorder-btn"
                  aria-label="Tõsta plokki alla"
                  :disabled="!canMoveDown('moon')"
                  @click="moveSection('moon', 'down')"
                >
                  <span class="material-symbols-outlined text-lg">expand_more</span>
                </button>
              </div>
            </div>
            <Moon />
          </section>

          <!-- Viimased märkmed -->
          <section v-if="id === 'notes'" class="relative">
            <div class="flex justify-end gap-1 mb-2">
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki üles"
                :disabled="!canMoveUp('notes')"
                @click="moveSection('notes', 'up')"
              >
                <span class="material-symbols-outlined text-lg">expand_less</span>
              </button>
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki alla"
                :disabled="!canMoveDown('notes')"
                @click="moveSection('notes', 'down')"
              >
                <span class="material-symbols-outlined text-lg">expand_more</span>
              </button>
            </div>
            <div class="dashboard-panel">
              <h3 class="text-lg font-bold mb-4">Viimased märkmed</h3>
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
          </section>

          <!-- Viimati lisatud taimed -->
          <section v-if="id === 'recent'" class="relative">
            <div class="flex justify-end gap-1 mb-2">
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki üles"
                :disabled="!canMoveUp('recent')"
                @click="moveSection('recent', 'up')"
              >
                <span class="material-symbols-outlined text-lg">expand_less</span>
              </button>
              <button
                type="button"
                class="section-reorder-btn"
                aria-label="Tõsta plokki alla"
                :disabled="!canMoveDown('recent')"
                @click="moveSection('recent', 'down')"
              >
                <span class="material-symbols-outlined text-lg">expand_more</span>
              </button>
            </div>
            <div class="dashboard-panel">
              <h3 class="text-lg font-bold mb-4">Viimati lisatud taimed</h3>
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
                    <span class="material-symbols-outlined text-xs">grass</span>
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
                <span class="material-symbols-outlined text-lg">grass</span>
                <span class="font-semibold text-sm">Vaata kõiki taimi</span>
                <span class="material-symbols-outlined text-lg ml-auto">chevron_right</span>
              </Link>
            </div>
          </section>
        </template>
      </div>

      <BottomNav active="dashboard" />
    </div>
  </AppLayout>
</template>
