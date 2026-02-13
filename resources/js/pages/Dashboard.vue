<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

import { computed, ref, watch } from 'vue';
import { useQuery } from '@tanstack/vue-query';

import { fetchWeatherMoon } from '@/lib/openMeteo';
import { useGeolocation } from '@/composables/useGeolocation';
import BottomNav from '@/pages/BottomNav.vue';

const { coords, loading: geoLoading, error: geoError } = useGeolocation();

const enabled = computed(() => {
  return (
    !geoLoading.value &&
    typeof coords.value?.latitude === 'number' &&
    typeof coords.value?.longitude === 'number' &&
    !geoError.value
  );
});

const q = useQuery({
  queryKey: computed(() => ['weather', coords.value?.latitude, coords.value?.longitude]),
  enabled,
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
  // 4-ikooni loogika: päike / pilv / vihm / lumi
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

// ---- Asukohanimi (reverse geocoding)
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

  // nt "Tallinn, Harjumaa"
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
  { immediate: true }
);

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
];
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="bg-background-light dark:bg-background-dark font-display text-[#2E2E2E] dark:text-gray-200 min-h-screen pb-24">
      <!-- Header -->
      <div class="px-6 pt-8 pb-4">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold tracking-tight text-primary">Tere, aednik!</h1>
            <p class="font-serif italic text-lg opacity-80 mt-1">Esmaspäev, 12. juuni</p>
          </div>
          <div class="bg-primary/10 p-2 rounded-full">
            <span class="material-symbols-outlined text-primary text-3xl">eco</span>
          </div>
        </div>
      </div>

      <!-- Weather card -->
      <div class="px-6 py-4">
        <div class="relative overflow-hidden bg-white dark:bg-gray-800 rounded-xl p-6 custom-shadow border border-primary/5">
          <div class="absolute -top-4 -right-4 opacity-5 rotate-12 pointer-events-none">
            <span class="material-symbols-outlined text-[120px]">psychology_alt</span>
          </div>

          <div class="flex items-start justify-between mb-4">
            <div class="flex items-start gap-3">
              <!-- icon from your assets -->
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
                  <template v-else-if="q.isLoading.value">...</template>
                  <template v-else>...</template>
                </div>

                <!-- location name -->
                <div class="text-sm opacity-70 flex items-center gap-1 mt-1">
                  <span class="material-symbols-outlined text-base">location_on</span>
                  <span>
                    <template v-if="locationName">{{ locationName }}</template>
                    <template v-else-if="geoLoading">Asukoht…</template>
                    <template v-else>Asukoht pole saadaval</template>
                  </span>
                </div>
              </div>
            </div>

            <!-- optional: refresh/updated info (väike) -->
            <div class="text-[11px] opacity-60" v-if="q.data?.value?.updatedAt">
              {{ q.data.value.updatedAt }}
            </div>
          </div>

          <p class="text-base leading-relaxed mb-2 opacity-80">
            Suurepärane ilm rohimiseks ja kastmiseks. Sinu aed tunneb end täna hästi!
          </p>

          <div class="text-sm opacity-70 mb-6" v-if="q.isSuccess.value && q.data.value">
            Max {{ Math.round(tMax ?? 0) }}° / Min {{ Math.round(tMin ?? 0) }}°
          </div>

          <div v-if="geoError" class="text-xs text-red-500 mb-3">
            Asukoht pole lubatud või pole saadaval. Luba brauseris Location õigused.
          </div>

          <div v-if="q.isError.value" class="text-xs text-red-500 mb-3">
            {{ q.error.value?.message }}
          </div>

          <div class="flex gap-3">
            <button
              class="flex-1 bg-primary text-white py-3 rounded-full font-medium flex items-center justify-center gap-2 shadow-lg shadow-primary/20"
            >
              <span class="material-symbols-outlined text-sm">edit</span>
              Lisa märkmed
            </button>
          </div>
        </div>
      </div>

      <!-- Moon Phase (jätame sinu olemasoleva) -->
      <div class="px-6 py-4">
        <h3 class="text-lg font-bold mb-3 px-1">Looduse rütmid</h3>
        <div class="bg-accent-beige/30 dark:bg-gray-800/50 leaf-shape p-5 flex items-center gap-4 border border-accent-beige">
          <div class="w-16 h-16 bg-white dark:bg-gray-700 rounded-full flex items-center justify-center shadow-inner">
            <span class="material-symbols-outlined text-primary text-4xl">brightness_3</span>
          </div>
          <div class="flex-1">
            <h4 class="font-bold text-primary">Kasvav kuu</h4>
            <p class="text-sm opacity-70">
              Aeg istutada lehtköögivilju ja lilli. Mahlad liiguvad ülespoole.
            </p>
          </div>
        </div>
      </div>

      <!-- Recent Activity (sinu olemasolev) -->
      <div class="py-4">
        <div class="px-6 flex justify-between items-end mb-4">
          <h3 class="text-lg font-bold">Viimased toimetused</h3>
          <a class="text-primary text-sm font-semibold" href="#">Vaata kõiki</a>
        </div>

        <div class="flex overflow-x-auto gap-4 px-6 pb-4 no-scrollbar">
          <!-- Card 1 -->
          <div class="min-w-[160px] bg-white dark:bg-gray-800 p-4 rounded-xl custom-shadow flex flex-col items-center text-center">
            <div
              class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-primary/10"
              style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCq98xf0LSMWWdH7roCHqqRk4_03xFu-WcAuulswyFfi1uI477LXOcRc0hO5v4GuJHpLPIb8G_ZWzOtoTjGg5SElrBReXT1NevglrMNI7hFyEZuGKBJ83fFDNuMDC9rcQTe0aFtuI-2B0AfKB2QVzty39WmhtzYzXP1ypBv5-IPThhVP6FueJPqZov5KuRvLTfQ7T0Pf8E3fyD1osmkKP85kkXHDnxFPreGAl61dE5x8EkCfqEuXpeSUhfc912AnRhyKQEt0NXwGy8');"
            ></div>
            <p class="font-bold text-sm">Basiilik</p>
            <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">2 päeva tagasi</p>
            <div class="flex gap-1 mt-2">
              <span class="material-symbols-outlined text-xs text-blue-400">water_drop</span>
            </div>
          </div>

          <!-- Card 2 -->
          <div class="min-w-[160px] bg-white dark:bg-gray-800 p-4 rounded-xl custom-shadow flex flex-col items-center text-center">
            <div
              class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-orange-100"
              style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAzk0qvCFs1figJ_8BAfOkxedBEZJ4jNh-4BfNiPj2KeWmHnZDouTumoxClPeUlEwyF7ILR10f9DyHuA9c4fKNrqrYh3LVGrR4ibXCFfS1LpqhoHCsgzitUvsPeCWkAHknlXTO-IwYIN-QgaxT1E7zkdLmT1z3MNUkcJRMe-fLxYr3Agd5UxRdlbDw0dOu6PK_ie3c0iYa8y684XPzqoY-PJpOeVufsBLJMtYDgvWzg-1MEyHMtVg4PhTpzX04ARvd80qaYj0CzAws');"
            ></div>
            <p class="font-bold text-sm">Kirsstomat</p>
            <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">Täna</p>
            <div class="flex gap-1 mt-2">
              <span class="material-symbols-outlined text-xs text-orange-400">nest_eco_leaf</span>
            </div>
          </div>

          <!-- Card 3 -->
          <div class="min-w-[160px] bg-white dark:bg-gray-800 p-4 rounded-xl custom-shadow flex flex-col items-center text-center">
            <div
              class="w-20 h-20 bg-cover bg-center rounded-full mb-3 ring-4 ring-purple-100"
              style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDIVdQDFw7NO9oSEpJsA99zvlAs4iIqLyXgA6XuGJRLjMiIVhnRBZl-eV7O3VfLL7NCJaEVr704eSRojSfFiLT99hp8vlO68atVzfwI1FQR90YDXBwVrKZqrNoQAfOjKRGskdf5SGOgfjkPwwl1U8e6gCsLUeIs6wTy_2xjlroL0Z8yKtq-_QYKFZM-AXvcdtYkcEgMDfLFP_ScP_ht_AgjMBbNOnXLnZ7paxIqJdUGjiNcAdWQ-ljjE5g_LZkYEulbDfu5cCyQ8a0');"
            ></div>
            <p class="font-bold text-sm">Lavendel</p>
            <p class="text-[10px] uppercase tracking-tighter text-primary font-bold mt-1 italic">Eile</p>
            <div class="flex gap-1 mt-2">
              <span class="material-symbols-outlined text-xs text-primary">content_cut</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Daily Tip -->
      <div class="px-6 py-4 mb-8">
        <div class="bg-white/40 dark:bg-gray-800/20 border-2 border-dashed border-primary/20 rounded-xl p-6 relative">
          <span class="material-symbols-outlined absolute -top-4 left-6 bg-background-light dark:bg-background-dark px-2 text-primary">
            tips_and_updates
          </span>
          <p class="text-sm italic font-medium opacity-80 leading-relaxed">
            "Kasta tomateid alati varahommikul otse juurele, et vältida lehtede märjakssaamist ja haiguste levikut."
          </p>
        </div>
      </div>

      <!-- FAB -->
      <button class="fixed right-6 bottom-24 w-16 h-16 bg-primary text-white rounded-full shadow-2xl flex items-center justify-center z-50">
        <span class="material-symbols-outlined text-4xl">add</span>
      </button>

      <BottomNav active="dashboard" />
    </div>
  </AppLayout>
</template>

<style>
.leaf-shape {
  border-radius: 24px 4px 24px 4px;
}
.custom-shadow {
  box-shadow: 0 10px 30px -12px rgba(107, 141, 104, 0.15);
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
