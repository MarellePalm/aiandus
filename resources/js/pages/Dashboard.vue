<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';

import { useGeolocation } from '@/composables/useGeolocation';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchWeatherMoon } from '@/lib/openMeteo';
import BottomNav from '@/pages/BottomNav.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const page = usePage();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard().url }];

const todayLabel = computed(() => {
  const date = new Date();
  const weekday = new Intl.DateTimeFormat('et-EE', { weekday: 'long' }).format(date);
  const day = new Intl.DateTimeFormat('et-EE', { day: 'numeric' }).format(date);
  const month = new Intl.DateTimeFormat('et-EE', { month: 'long' }).format(date);
  return `${weekday} ${day}. ${month}`;
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
    fetchWeatherMoon({
      latitude: coords.value!.latitude,
      longitude: coords.value!.longitude,
    }),
  staleTime: 60_000,
  retry: 1,
});

const temp = computed(() => q.data?.value?.temp ?? null);
const tMax = computed(() => q.data?.value?.tMax ?? null);
const tMin = computed(() => q.data?.value?.tMin ?? null);

function weatherIconFile(code: number) {
  if (code === 0) return 'sunny';
  if (code <= 3 || code === 45 || code === 48) return 'cloudy';
  if ((code >= 51 && code <= 67) || (code >= 80 && code <= 82) || code >= 95) return 'rainy';
  if (code >= 71 && code <= 77) return 'snowy';
  return 'cloudy';
}

const weatherIconSrc = computed(() => {
  const code = q.data?.value?.weatherCode;
  const name = typeof code === 'number' ? weatherIconFile(code) : 'cloudy';
  return `/icons/weather/${name}.svg`;
});

const locationName = ref<string | null>(null);

async function reverseGeocodeName(lat: number, lon: number) {
  const url =
    'https://geocoding-api.open-meteo.com/v1/reverse' +
    `?latitude=${encodeURIComponent(lat)}` +
    `&longitude=${encodeURIComponent(lon)}` +
    '&language=et' +
    '&count=1';

  const res = await fetch(url);
  if (!res.ok) return null;

  const d = await res.json();
  const r = d?.results?.[0];
  if (!r) return null;

  return [r.name, r.admin1].filter(Boolean).join(', ');
}

watch(
  () => [coords.value?.latitude, coords.value?.longitude],
  async ([lat, lon]) => {
    if (typeof lat === 'number' && typeof lon === 'number') {
      locationName.value = await reverseGeocodeName(lat, lon);
    } else {
      locationName.value = null;
    }
  },
  { immediate: true },
);

function onAddNote() {
  // TODO: router.visit('/calendar/note-form') vms
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <!-- IMPORTANT:
           AppLayout should wrap slot with <div class="page-container">...</div>.
           If not, add a wrapper here: <div class="page-container"> ... </div>
      -->

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

          <div class="icon-btn shrink-0" aria-hidden="true">
            <span class="material-symbols-outlined text-3xl">eco</span>
          </div>
        </section>

        <!-- Weather -->
        <section>
          <div class="weather-card">
            <div class="absolute -top-4 -right-4 opacity-5 rotate-12 pointer-events-none">
              <span class="material-symbols-outlined text-[120px]">psychology_alt</span>
            </div>

            <div class="flex items-start justify-between gap-4 mb-4">
              <div class="flex items-start gap-3">
                <img
                  v-if="q.isSuccess.value"
                  :src="weatherIconSrc"
                  alt=""
                  class="w-10 h-10 mt-1"
                />

                <div>
                  <div class="text-2xl font-bold">
                    <template v-if="q.isSuccess.value && temp !== null">
                      {{ Math.round(temp!) }}°C
                    </template>
                    <template v-else>...</template>
                  </div>

                  <div class="mt-1 text-sm opacity-70 flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">location_on</span>
                    <span class="truncate">
                      <template v-if="locationName">{{ locationName }}</template>
                      <template v-else-if="geoLoading">Asukoht…</template>
                      <template v-else>Asukoht pole saadaval</template>
                    </span>
                  </div>
                </div>
              </div>

              <div class="text-[11px] opacity-60 shrink-0" v-if="q.data?.value?.updatedAt">
                {{ q.data.value.updatedAt }}
              </div>
            </div>

            <p class="text-base leading-relaxed opacity-80 mb-2">
              Suurepärane ilm rohimiseks ja kastmiseks. Sinu aed tunneb end täna hästi!
            </p>

            <div class="text-sm opacity-70 mb-6" v-if="q.isSuccess.value && q.data.value">
              Max {{ Math.round(tMax ?? 0) }}° / Min {{ Math.round(tMin ?? 0) }}°
            </div>

            <div v-if="geoError" class="text-xs text-red-600 dark:text-red-400 mb-3">
              Asukoht pole lubatud või pole saadaval. Luba brauseris Location õigused.
            </div>

            <div v-if="q.isError.value" class="text-xs text-red-600 dark:text-red-400 mb-3">
              {{ q.error.value?.message }}
            </div>

            <button class="btn-primary w-full" type="button" @click="onAddNote">
              <span class="material-symbols-outlined text-[18px]">edit</span>
              Lisa märkmed
            </button>
          </div>
        </section>

        <!-- Moon -->
        <section>
          <h3 class="text-lg font-bold mb-3">Looduse rütmid</h3>

          <div class="moon-card">
            <div class="w-16 h-16 bg-card rounded-full flex items-center justify-center shadow-inner">
              <span class="material-symbols-outlined text-primary text-4xl">brightness_3</span>
            </div>

            <div class="flex-1">
              <h4 class="font-bold text-primary">Kasvav kuu</h4>
              <p class="text-sm opacity-70">
                Aeg istutada lehtköögivilju ja lilli. Mahlad liiguvad ülespoole.
              </p>
            </div>
          </div>
        </section>

        <!-- Recent activity -->
        <section>
          <div class="flex justify-between items-end gap-4 mb-4">
            <h3 class="text-lg font-bold">Viimased toimetused</h3>
            <a class="text-primary text-sm font-semibold whitespace-nowrap" href="#">
              Vaata kõiki
            </a>
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

        <!-- Tip -->
        <section class="pb-6">
          <div class="tip-card">
            <span class="material-symbols-outlined absolute -top-4 left-6 bg-background px-2 text-primary">
              tips_and_updates
            </span>
            <p class="text-sm italic font-medium opacity-80 leading-relaxed">
              "Kasta tomateid alati varahommikul otse juurele, et vältida lehtede märjakssaamist ja
              haiguste levikut."
            </p>
          </div>
        </section>
      </div>

      <!-- FAB + Bottom nav -->
      <button class="fab" type="button" aria-label="Lisa">
        <span class="material-symbols-outlined text-4xl">add</span>
      </button>

      <BottomNav active="dashboard" />
    </div>
  </AppLayout>
</template>
