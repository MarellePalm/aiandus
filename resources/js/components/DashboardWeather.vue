<!-- resources/js/components/DashboardWeather.vue – ilmablokk dashboardil -->
<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';

import { useGeolocation } from '@/composables/useGeolocation';

type WeatherDailyItem = {
  date: string;
  tMax: number | null;
  tMin: number | null;
  weatherCode?: number | null;
  icon?: string | null;
};

type WeatherSnapshotFromServer = {
  temp: number | null;
  locationName: string | null;
  tMax: number | null;
  tMin: number | null;
  humidity: number | null;
  windSpeed: number | null;
  daily: WeatherDailyItem[];
  updatedAt: string;
  openWeatherIcon: string | null;
  openWeatherLabel: string | null;
  sunrise: string | null;
  sunset: string | null;
};

const { coords, loading: geoLoading, error: geoError } = useGeolocation();

const queryEnabled = computed(() => {
  return (
    !geoLoading.value &&
    typeof coords.value?.latitude === 'number' &&
    typeof coords.value?.longitude === 'number' &&
    !geoError.value
  );
});

const q = useQuery<WeatherSnapshotFromServer>({
  queryKey: computed(() => ['weather', coords.value?.latitude, coords.value?.longitude]),
  enabled: queryEnabled,
  queryFn: async (): Promise<WeatherSnapshotFromServer> => {
    const lat = coords.value!.latitude;
    const lon = coords.value!.longitude;
    try {
      const res = await fetch(
        `/api/weather?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}`,
        { credentials: 'same-origin' }
      );
      const data = (await res.json()) as {
        message?: string;
        temp?: number | null;
        locationName?: string | null;
        tMax?: number | null;
        tMin?: number | null;
        humidity?: number | null;
        windSpeed?: number | null;
        daily?: WeatherDailyItem[];
        updatedAt?: string;
        openWeatherIcon?: string | null;
        openWeatherLabel?: string | null;
        sunrise?: string | null;
        sunset?: string | null;
      };
      if (!res.ok) {
        throw new Error(data.message ?? `Ilmapäring ebaõnnestus (${res.status})`);
      }
      return {
        temp: data.temp ?? null,
        locationName: data.locationName ?? null,
        tMax: data.tMax ?? null,
        tMin: data.tMin ?? null,
        humidity: data.humidity ?? null,
        windSpeed: data.windSpeed ?? null,
        daily: (data.daily ?? []) as WeatherDailyItem[],
        updatedAt: data.updatedAt ?? new Date().toLocaleString('et-EE'),
        openWeatherIcon: data.openWeatherIcon ?? null,
        openWeatherLabel: data.openWeatherLabel ?? null,
        sunrise: data.sunrise ?? null,
        sunset: data.sunset ?? null,
      };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Ilmapäring ebaõnnestus';
      throw new Error(msg);
    }
  },
  staleTime: 60_000,
  retry: 1,
  refetchOnMount: 'always',
});

const temp = computed(() => q.data.value?.temp ?? null);
const locationName = computed(() => q.data.value?.locationName ?? null);
const tMax = computed(() => q.data.value?.tMax ?? null);
const tMin = computed(() => q.data.value?.tMin ?? null);
const humidity = computed(() => q.data.value?.humidity ?? null);
const windSpeed = computed(() => q.data.value?.windSpeed ?? null);

const daily = computed<WeatherDailyItem[]>(() => q.data.value?.daily ?? []);
const forecastDays = computed(() => daily.value.slice(1, 5));

const todayWeatherLabel = computed(() => q.data.value?.openWeatherLabel ?? null);
const sunrise = computed(() => q.data.value?.sunrise ?? null);
const sunset = computed(() => q.data.value?.sunset ?? null);

const todayWeatherIconUrl = computed(() => {
  const icon = q.data.value?.openWeatherIcon;
  if (!icon) return null;
  return `https://openweathermap.org/img/wn/${icon}@2x.png`;
});

function dailyIconUrl(icon: string | null | undefined, retina = false) {
  if (!icon) return null;
  const suffix = retina ? '@2x' : '';
  return `https://openweathermap.org/img/wn/${icon}${suffix}.png`;
}
</script>

<template>
  <div class="weather-card">
    <span
      class="material-symbols-outlined absolute -top-4 -right-4 text-[120px] opacity-[0.04] pointer-events-none"
      aria-hidden="true"
    >
      light_mode
    </span>

    <div class="flex justify-between items-start mb-6">
      <div class="space-y-1">
        <div class="inline-flex items-center gap-2 mb-2">
          <div class="inline-flex items-center bg-primary/15 text-primary px-3 py-1 rounded-full text-xs font-semibold">
            Täna
          </div>
          <div
            v-if="q.isSuccess.value && locationName"
            class="inline-flex items-center bg-secondary/60 text-xs text-muted-foreground px-3 py-1 rounded-full"
          >
            <span class="material-symbols-outlined text-sm mr-1">location_on</span>
            <span class="truncate max-w-[120px]">{{ locationName }}</span>
          </div>
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
        <div v-if="q.isSuccess.value" class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-3 text-sm text-muted-foreground">
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base" aria-hidden="true">air</span>
            <span>{{ windSpeed != null ? `${windSpeed.toFixed(1)} m/s` : '—' }}</span>
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base" aria-hidden="true">water_drop</span>
            <span>{{ humidity != null ? `${humidity}%` : '—' }}</span>
          </span>
          <template v-if="sunrise || sunset">
            <span class="inline-flex items-center gap-1.5">
              <span class="material-symbols-outlined text-base" aria-hidden="true">wb_twilight</span>
              <span>{{ sunrise ? `Tõuseb ${sunrise}` : '—' }}</span>
            </span>
            <span class="inline-flex items-center gap-1.5">
              <span class="material-symbols-outlined text-base" aria-hidden="true">nightlight_round</span>
              <span>{{ sunset ? `Loojub ${sunset}` : '—' }}</span>
            </span>
          </template>
        </div>
      </div>

      <div class="flex flex-col items-end">
        <img
          v-if="q.isSuccess.value && todayWeatherIconUrl"
          :src="todayWeatherIconUrl"
          alt=""
          class="w-30 h-30 object-contain drop-shadow-sm block"
          width="120"
          height="120"
        />
        <span
          v-else-if="q.isSuccess.value"
          class="material-symbols-outlined text-primary drop-shadow-sm block leading-none"
          style="font-size: 7.5rem; font-variation-settings: 'opsz' 64;"
          aria-hidden="true"
        >
          cloud
        </span>

        <p v-if="q.isSuccess.value && todayWeatherLabel" class="text-lg font-semibold mt-1 text-muted-foreground">
          {{ todayWeatherLabel }}
        </p>
      </div>
    </div>

    <div class="h-px bg-border w-full mb-5"></div>

    <div v-if="q.isSuccess.value && forecastDays.length" class="overflow-x-auto pb-1">
      <div class="flex gap-3 min-w-0" style="width: max-content;">
        <div
          v-for="d in forecastDays"
          :key="d.date"
          class="flex flex-col items-center p-3 rounded-2xl bg-secondary/40 dark:bg-black/10 backdrop-blur-sm shrink-0 w-28"
        >
          <span class="text-[24px] font-bold uppercase tracking-wide text-muted-foreground">
            {{ new Intl.DateTimeFormat('et-EE', { weekday: 'short' }).format(new Date(d.date)) }}
          </span>

          <img
            v-if="dailyIconUrl(d.icon, true)"
            :src="dailyIconUrl(d.icon, true)!"
            alt=""
            class="w-14 h-14 object-contain mb-2"
            width="56"
            height="56"
          />
          <span
            v-else
            class="material-symbols-outlined my-2 text-primary text-4xl"
            aria-hidden="true"
          >
            cloud
          </span>

          <div class="flex flex-col items-center">
            <span class="text-base font-bold">{{ Math.round(d.tMax ?? 0) }}°</span>
            <span class="text-base text-muted-foreground">{{ Math.round(d.tMin ?? 0) }}°</span>
          </div>
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
</template>
