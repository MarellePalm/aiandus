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
  weatherapiIcon?: string | null;
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
        weatherapiIcon?: string | null;
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
        weatherapiIcon: data.weatherapiIcon ?? null,
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

// Eelistame päris ilmaikooni API-st (värviline, ilmale vastav)
const todayWeatherIconUrl = computed(() => {
  const weatherapi = q.data.value?.weatherapiIcon;
  if (weatherapi) return weatherapi;
  const icon = q.data.value?.openWeatherIcon;
  if (!icon) return null;
  return `https://openweathermap.org/img/wn/${icon}@2x.png`;
});

const weatherLabelNormalized = computed(() => (todayWeatherLabel.value ?? '').toLowerCase());

const fallbackWeatherSymbol = computed(() => {
  const label = weatherLabelNormalized.value;
  if (label.includes('päike') || label.includes('selge')) return 'light_mode';
  if (label.includes('äike')) return 'thunderstorm';
  if (label.includes('vihm')) return 'rainy';
  if (label.includes('lumi')) return 'weather_snowy';
  if (label.includes('udu') || label.includes('häg')) return 'foggy';
  return 'cloud';
});

const fallbackWeatherColorClass = computed(() => {
  const label = weatherLabelNormalized.value;
  if (label.includes('päike') || label.includes('selge')) return 'text-amber-400';
  if (label.includes('äike')) return 'text-violet-400';
  if (label.includes('vihm')) return 'text-sky-400';
  if (label.includes('lumi')) return 'text-cyan-200';
  if (label.includes('udu') || label.includes('häg')) return 'text-slate-300';
  return 'text-slate-200';
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

    <div class="flex justify-between items-start mb-4">
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
          <span class="text-[40px] sm:text-[44px] font-bold leading-none tracking-tight">
            <template v-if="q.isSuccess.value && temp !== null">
              {{ Math.round(temp) }}°C
            </template>
            <template v-else>...</template>
          </span>
        </div>

        <div v-if="q.isSuccess.value" class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/60 px-2.5 py-1 ring-1 ring-border/70">
            <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">device_thermostat</span>
            <span class="text-foreground/85 font-medium">
              Max {{ Math.round(tMax ?? 0) }}° / Min {{ Math.round(tMin ?? 0) }}°
            </span>
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/60 px-2.5 py-1 ring-1 ring-border/70">
            <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">partly_cloudy_day</span>
            <span class="text-foreground/85 font-semibold truncate max-w-40">
              {{ todayWeatherLabel ?? '—' }}
            </span>
          </span>
        </div>

        <div v-if="q.isSuccess.value" class="flex flex-wrap items-center gap-2 mt-3 text-sm text-muted-foreground">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/60 px-2.5 py-1 ring-1 ring-border/70">
            <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">air</span>
            <span class="text-foreground/80">{{ windSpeed != null ? `${windSpeed.toFixed(1)} m/s` : '—' }}</span>
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/60 px-2.5 py-1 ring-1 ring-border/70">
            <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">water_drop</span>
            <span class="text-foreground/80">{{ humidity != null ? `${humidity}%` : '—' }}</span>
          </span>
        </div>
        <div v-if="q.isSuccess.value && (sunrise || sunset)" class="mt-2 grid grid-cols-2 gap-2 text-sm text-muted-foreground">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/60 px-2.5 py-1 ring-1 ring-border/70">
            <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">wb_twilight</span>
            <span class="text-foreground/80">{{ sunrise ? `Tõuseb ${sunrise}` : '—' }}</span>
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/60 px-2.5 py-1 ring-1 ring-border/70">
            <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">nightlight_round</span>
            <span class="text-foreground/80">{{ sunset ? `Loojub ${sunset}` : '—' }}</span>
          </span>
        </div>
      </div>

      <div class="flex flex-col items-end">
        <div class="rounded-full bg-secondary/60 ring-1 ring-border/70 p-1.5 sm:p-2">
          <img
            v-if="q.isSuccess.value && todayWeatherIconUrl"
            :src="todayWeatherIconUrl"
            alt=""
            class="w-24 h-24 sm:w-28 sm:h-28 object-contain drop-shadow-sm block"
            width="112"
            height="112"
          />
          <span
            v-else-if="q.isSuccess.value"
            class="material-symbols-outlined drop-shadow-sm block leading-none"
            :class="fallbackWeatherColorClass"
            style="font-size: 6.75rem; font-variation-settings: 'opsz' 64;"
            aria-hidden="true"
          >
            {{ fallbackWeatherSymbol }}
          </span>
        </div>

        <!-- Label kuvatakse vasakus veerus Max/Min rea paremas servas -->
      </div>
    </div>

    <div class="h-px bg-border w-full mb-5"></div>

    <div v-if="q.isSuccess.value && forecastDays.length" class="relative overflow-x-auto pb-2">
      <div class="pointer-events-none absolute right-0 top-0 h-full w-10 bg-linear-to-l from-background to-transparent"></div>
      <div class="flex gap-3 min-w-0" style="width: max-content;">
        <div
          v-for="d in forecastDays"
          :key="d.date"
          class="flex flex-col items-center p-3 rounded-2xl bg-secondary/50 dark:bg-black/10 backdrop-blur-sm shrink-0 w-28 ring-1 ring-border/70 shadow-sm"
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
