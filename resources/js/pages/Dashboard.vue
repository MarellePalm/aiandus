<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref, onMounted } from 'vue';

import { useGeolocation } from '@/composables/useGeolocation';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchWeatherMoon, iconWeatherMaterial, labelWeather } from '@/lib/openMeteo';
import BottomNav from '@/pages/BottomNav.vue';
import UserMenu from '@/pages/UserMenu.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import Moon from '@/pages/calendarNotes/moon.vue';

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

const { coords, loading: geoLoading, error: geoError } = useGeolocation();

const queryEnabled = computed(() => {
  return (
    !geoLoading.value &&
    typeof coords.value?.latitude === 'number' &&
    typeof coords.value?.longitude === 'number' &&
    !geoError.value
  );
});

const q = useQuery({
  queryKey: computed(() => ['weather', coords.value?.latitude, coords.value?.longitude]),
  enabled: queryEnabled,
  queryFn: () =>
    fetchWeatherMoon(
      { latitude: coords.value!.latitude, longitude: coords.value!.longitude },
      // 4 päeva: täna + 3 järgmist (et saaks Stitchi 3-päeva rea)
      { days: 4 }
    ),
  staleTime: 60_000,
  retry: 1,
});

const temp = computed(() => q.data.value?.temp ?? null);
const tMax = computed(() => q.data.value?.tMax ?? null);
const tMin = computed(() => q.data.value?.tMin ?? null);

const daily = computed(() => q.data.value?.daily ?? []);

// Stitch look: 3 päeva reas (homme+ülehomme+üle-ülehomme), täna jääb “hero” peale
const forecastDays = computed(() => daily.value.slice(1, 4));

const todayWeatherLabel = computed(() => {
  const code = q.data.value?.weatherCode;
  if (typeof code !== 'number') return null;
  return labelWeather(code);
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
      <div class="space-y-8 py-8">
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
            <div class="weather-card">
            <!-- Decorative icon -->
            <span
              class="material-symbols-outlined absolute -top-4 -right-4 text-[120px] opacity-[0.04] pointer-events-none"
              aria-hidden="true"
            >
              light_mode
            </span>

            <!-- Top / Today -->
            <div class="flex justify-between items-start mb-6">
              <div class="space-y-1">
                <div class="inline-flex items-center bg-primary/15 text-primary px-3 py-1 rounded-full text-xs font-semibold mb-2">
                  Täna
                </div>

                <div class="flex items-baseline gap-1">
                  <span class="text-[44px] font-bold leading-none">
                    <template v-if="q.isSuccess.value && temp !== null">
                      {{ Math.round(temp) }}°C
                    </template>
                    <template v-else>...</template>
                  </span>
                </div>

                <p v-if="q.isSuccess.value" class="text-muted-foreground font-medium">
                  Max {{ Math.round(tMax ?? 0) }}° / Min {{ Math.round(tMin ?? 0) }}°
                </p>
              </div>

              <div class="flex flex-col items-end">
                <span
                  v-if="q.isSuccess.value"
                  class="material-symbols-outlined text-primary drop-shadow-sm block leading-none"
                  style="font-size: 7.5rem; font-variation-settings: 'opsz' 64;"
                  aria-hidden="true"
                >
                  {{ iconWeatherMaterial(q.data.value?.weatherCode ?? 3) }}
                </span>

                <p v-if="q.isSuccess.value && todayWeatherLabel" class="text-lg font-semibold mt-1 text-muted-foreground">
                  {{ todayWeatherLabel }}
                </p>
              </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-border w-full mb-5"></div>

            <!-- Forecast row (3 days) -->
            <div v-if="q.isSuccess.value && forecastDays.length" class="grid grid-cols-3 gap-3">
              <div
                v-for="d in forecastDays"
                :key="d.date"
                class="flex flex-col items-center p-3 rounded-2xl bg-secondary/40 dark:bg-black/10 backdrop-blur-sm"
              >
                <span class="text-[24px] font-bold uppercase tracking-wide text-muted-foreground">
                  {{ new Intl.DateTimeFormat('et-EE', { weekday: 'short' }).format(new Date(d.date)) }}
                </span>

                <span class="material-symbols-outlined my-2 text-primary text-2xl" aria-hidden="true">
                  {{ iconWeatherMaterial(d.weatherCode ?? 3) }}
                </span>

                <div class="flex flex-col items-center">
                  <span class="text-base font-bold">{{ Math.round(d.tMax ?? 0) }}°</span>
                  <span class="text-base text-muted-foreground">{{ Math.round(d.tMin ?? 0) }}°</span>
                </div>
              </div>
            </div>

            <div v-if="geoError" class="text-xs text-red-600 dark:text-red-400 mt-4">
              Asukoht pole lubatud või pole saadaval. Luba brauseris Location õigused.
            </div>

            <div v-if="q.isError.value" class="text-xs text-red-600 dark:text-red-400 mt-4">
              {{ q.error.value?.message }}
            </div>
            </div>
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
            <button class="btn-primary w-full cursor-pointer" type="button" @click="onAddNote">
              <span class="material-symbols-outlined text-[18px]">edit</span>
              Lisa märkmed
            </button>
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
