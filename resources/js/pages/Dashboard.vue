<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
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
type SectionId = 'weather' | 'addNote' | 'moon' | 'recent';
const DEFAULT_ORDER: SectionId[] = ['weather', 'addNote', 'moon', 'recent'];

const sectionOrder = ref<SectionId[]>([...DEFAULT_ORDER]);

onMounted(() => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw) as unknown;
      if (Array.isArray(parsed) && parsed.every((id) => DEFAULT_ORDER.includes(id as SectionId)) && parsed.length === DEFAULT_ORDER.length) {
        sectionOrder.value = parsed as SectionId[];
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
                class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
                aria-label="Tõsta plokki üles"
                :disabled="!canMoveUp('weather')"
                @click="moveSection('weather', 'up')"
              >
                <span class="material-symbols-outlined text-lg">expand_less</span>
              </button>
              <button
                type="button"
                class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
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
                  class="material-symbols-outlined text-10xl text-primary drop-shadow-sm"
                  aria-hidden="true"
                >
                  {{ iconWeatherMaterial(q.data.value?.weatherCode ?? 3) }}
                </span>

                <p v-if="q.isSuccess.value && todayWeatherLabel" class="text-sm font-medium mt-1 text-muted-foreground">
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
                class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
                aria-label="Tõsta plokki üles"
                :disabled="!canMoveUp('addNote')"
                @click="moveSection('addNote', 'up')"
              >
                <span class="material-symbols-outlined text-lg">expand_less</span>
              </button>
              <button
                type="button"
                class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
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
                  class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
                  aria-label="Tõsta plokki üles"
                  :disabled="!canMoveUp('moon')"
                  @click="moveSection('moon', 'up')"
                >
                  <span class="material-symbols-outlined text-lg">expand_less</span>
                </button>
                <button
                  type="button"
                  class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
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

          <!-- Recent activity -->
          <section v-if="id === 'recent'">
            <div class="flex justify-between items-end gap-4 mb-4">
              <h3 class="text-lg font-bold">Viimased toimetused</h3>
              <div class="flex items-center gap-2">
                <div class="flex gap-1">
                  <button
                    type="button"
                    class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
                    aria-label="Tõsta plokki üles"
                    :disabled="!canMoveUp('recent')"
                    @click="moveSection('recent', 'up')"
                  >
                    <span class="material-symbols-outlined text-lg">expand_less</span>
                  </button>
                  <button
                    type="button"
                    class="p-1.5 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:pointer-events-none"
                    aria-label="Tõsta plokki alla"
                    :disabled="!canMoveDown('recent')"
                    @click="moveSection('recent', 'down')"
                  >
                    <span class="material-symbols-outlined text-lg">expand_more</span>
                  </button>
                </div>
                <a class="text-primary text-sm font-semibold whitespace-nowrap" href="#">
                  Vaata kõiki
                </a>
              </div>
            </div>

          <div class="flex overflow-x-auto gap-4 pb-2 no-scrollbar">
            <div class="activity-card min-w-[160px]">
              <div
                class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-primary/10"
                style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCq98xf0LSMWWdH7roCHqqRk4_03xFu-WcAuulswyFfi1uI477LXOcRc0hO5v4GuJHpLPIb8G_ZWzOtoTjGg5SElrBReXT1NevglrMNI7hFyEZuGKBJ83fFDNuMDC9rcQTe0aFtuI-2B0AfKB2QVzty39WmhtzYzXP1ypBv5-IPThhVP6FueJPqZov5KuRvLTfQ7T0Pf8E3fyD1osmkKP85kkXHDnxFPreGAl61dE5x8EkCfqEuXpeSUhfc912AnRhyKQEt0NXwGy8');"
              />
              <p class="font-bold text-sm">Basiilik</p>
              <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">
                2 päeva tagasi
              </p>
              <div class="flex gap-1 mt-2">
                <span class="material-symbols-outlined text-xs">water_drop</span>
              </div>
            </div>

            <div class="activity-card min-w-[160px]">
              <div
                class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-primary/10"
                style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAzk0qvCFs1figJ_8BAfOkxedBEZJ4jNh-4BfNiPj2KeWmHnZDouTumoxClPeUlEwyF7ILR10f9DyHuA9c4fKNrqrYh3LVGrR4ibXCFfS1LpqhoHCsgzitUvsPeCWkAHknlXTO-IwYIN-QgaxT1E7zkdLmT1z3MNUkcJRMe-fLxYr3Agd5UxRdlbDw0dOu6PK_ie3c0iYa8y684XPzqoY-PJpOeVufsBLJMtYDgvWzg-1MEyHMtVg4PhTpzX04ARvd80qaYj0CzAws');"
              />
              <p class="font-bold text-sm">Kirsstomat</p>
              <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">
                Täna
              </p>
              <div class="flex gap-1 mt-2">
                <span class="material-symbols-outlined text-xs">nest_eco_leaf</span>
              </div>
            </div>

            <div class="activity-card min-w-[160px]">
              <div
                class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-primary/10"
                style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDIVdQDFw7NO9oSEpJsA99zvlAs4iIqLyXgA6XuGJRLjMiIVhnRBZl-eV7O3VfLL7NCJaEVr704eSRojSfFiLT99hp8vlO68atVzfwI1FQR90YDXBwVrKZqrNoQAfOjKRGskdf5SGOgfjkPwwl1U8e6gCsLUeIs6wTy_2xjlroL0Z8yKtq-_QYKFZM-AXvcdtYkcEgMDfLFP_ScP_ht_AgjMBbNOnXLnZ7paxIqJdUGjiNcAdWQ-ljjE5g_LZkYEulbDfu5cCyQ8a0');"
              />
              <p class="font-bold text-sm">Lavendel</p>
              <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">
                Eile
              </p>
              <div class="flex gap-1 mt-2">
                <span class="material-symbols-outlined text-xs">content_cut</span>
              </div>
            </div>
          </div>
          </section>
        </template>
      </div>

      <BottomNav active="dashboard" />
    </div>
  </AppLayout>
</template>
