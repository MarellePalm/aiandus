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
  openWeatherConditionId?: number | null;
  sunrise: string | null;
  sunset: string | null;
  weatherapiIcon?: string | null;
  weatherConditionSource?: string | null;
  coordinatesUsed?: { lat: number; lon: number } | null;
  weatherIconSource?: string | null;
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
        openWeatherConditionId?: number | null;
        sunrise?: string | null;
        sunset?: string | null;
        weatherapiIcon?: string | null;
        weatherConditionSource?: string | null;
        coordinatesUsed?: { lat: number; lon: number } | null;
        weatherIconSource?: string | null;
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
        openWeatherConditionId: data.openWeatherConditionId ?? null,
        sunrise: data.sunrise ?? null,
        sunset: data.sunset ?? null,
        weatherapiIcon: data.weatherapiIcon ?? null,
        weatherConditionSource: data.weatherConditionSource ?? null,
        coordinatesUsed: data.coordinatesUsed ?? null,
        weatherIconSource: data.weatherIconSource ?? null,
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

/** Kust kirjeldus tuleb + mis GPS punkt + OWM geokoodeeringu nimi; suur ikoon võib olla WeatherAPI. */
const weatherDataCaption = computed(() => {
  const src = q.data.value?.weatherConditionSource ?? 'OpenWeatherMap';
  const loc = q.data.value?.locationName;
  const c = q.data.value?.coordinatesUsed;
  const iconSrc = q.data.value?.weatherIconSource;
  const bits: string[] = [`Ilmateade: ${src}`];
  if (loc) bits.push(loc);
  if (c && typeof c.lat === 'number' && typeof c.lon === 'number') {
    bits.push(
      `${c.lat.toFixed(2).replace('.', ',')}° N, ${c.lon.toFixed(2).replace('.', ',')}° E`,
    );
  }
  let s = bits.join(' · ');
  if (iconSrc && iconSrc !== src) {
    s += ` · suur ikoon: ${iconSrc}`;
  }
  return s;
});

/** Suur ikoon: WeatherAPI (täpsem „tänane“ tunne), muidu OpenWeather PNG. */
const todayHeroIconUrl = computed(() => {
  const w = q.data.value?.weatherapiIcon;
  if (w) return w;
  const icon = q.data.value?.openWeatherIcon;
  if (!icon) return null;
  return `https://openweathermap.org/img/wn/${icon}@2x.png`;
});

const openWeatherConditionId = computed(() => q.data.value?.openWeatherConditionId ?? null);
const openWeatherIconCode = computed(() => q.data.value?.openWeatherIcon ?? '');

/** Kirjelduse kõrval: tingimuse ID + öö/päev, mitte alati „pool pilves“. */
const detailConditionMaterialIcon = computed(() => {
  const id = openWeatherConditionId.value;
  const ow = openWeatherIconCode.value;
  const day = ow.endsWith('d');
  if (id === 800) return day ? 'sunny' : 'nightlight';
  if (id === 801 || id === 802) return day ? 'partly_cloudy_day' : 'partly_cloudy_night';
  if (id !== null && id >= 803 && id <= 804) return 'cloud';
  if (id !== null && id >= 200 && id < 300) return 'thunderstorm';
  if (id !== null && id >= 300 && id < 600) return 'rainy';
  if (id !== null && id >= 600 && id < 700) return 'weather_snowy';
  if (id !== null && id >= 700 && id < 800) return 'foggy';

  const base = ow.length >= 2 ? ow.slice(0, 2) : '';
  switch (base) {
    case '01':
      return day ? 'sunny' : 'nightlight';
    case '02':
      return day ? 'partly_cloudy_day' : 'partly_cloudy_night';
    case '03':
    case '04':
      return 'cloud';
    case '09':
      return 'rainy';
    case '10':
      return 'rainy';
    case '11':
      return 'thunderstorm';
    case '13':
      return 'weather_snowy';
    case '50':
      return 'foggy';
    default:
      return 'wb_cloudy';
  }
});

const weatherLabelNormalized = computed(() => (todayWeatherLabel.value ?? '').toLowerCase());

const fallbackWeatherSymbol = computed(() => {
  const label = weatherLabelNormalized.value;
  if (label.includes('päike') || label.includes('päikeseline') || label.includes('selge')) return 'light_mode';
  if (label.includes('äike')) return 'thunderstorm';
  if (label.includes('vihm')) return 'rainy';
  if (label.includes('lumi')) return 'weather_snowy';
  if (label.includes('udu') || label.includes('häg')) return 'foggy';
  return 'cloud';
});

const fallbackWeatherColorClass = computed(() => {
  const label = weatherLabelNormalized.value;
  if (label.includes('päike') || label.includes('päikeseline') || label.includes('selge')) return 'text-amber-400';
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
    <div class="mb-4">
      <div class="flex items-start justify-between gap-2">
        <div class="space-y-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[48px] sm:text-[52px] font-bold leading-none tracking-tight text-foreground">
              <template v-if="q.isSuccess.value && temp !== null">
                {{ Math.round(temp) }}°C
              </template>
              <template v-else>...</template>
            </span>
            <div
              v-if="q.isSuccess.value && locationName"
              class="inline-flex items-center rounded-full bg-muted/60 px-2.5 py-1 text-xs text-foreground/85 ring-1 ring-border/60 dark:bg-card/80"
            >
              <span class="material-symbols-outlined text-sm mr-1">location_on</span>
              <span class="truncate max-w-[160px]">{{ locationName }}</span>
            </div>
          </div>
        </div>
        <div class="shrink-0 pt-1">
        <img
          v-if="q.isSuccess.value && todayHeroIconUrl"
          :src="todayHeroIconUrl"
          alt=""
          class="w-28 h-28 sm:w-32 sm:h-32 object-contain drop-shadow-sm block opacity-95"
          width="112"
          height="112"
        />
        <span
          v-else-if="q.isSuccess.value"
          class="material-symbols-outlined drop-shadow-sm block leading-none"
          :class="fallbackWeatherColorClass"
          style="font-size: 6rem; font-variation-settings: 'opsz' 72;"
          aria-hidden="true"
        >
          {{ fallbackWeatherSymbol }}
        </span>
        </div>
      </div>

      <div v-if="q.isSuccess.value" class="mt-1 rounded-lg bg-muted/30 p-2 ring-1 ring-border/60 text-sm text-muted-foreground dark:bg-card/70">
        <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
          <span class="inline-flex shrink-0 items-center gap-1.5">
          <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">device_thermostat</span>
          <span class="text-foreground/85 font-medium">
            Max {{ Math.round(tMax ?? 0) }}° / Min {{ Math.round(tMin ?? 0) }}°
          </span>
        </span>
          <span class="inline-flex min-w-0 flex-1 items-start gap-1.5 basis-full sm:basis-auto">
          <span class="material-symbols-outlined mt-0.5 shrink-0 text-base text-foreground/80" aria-hidden="true">{{ detailConditionMaterialIcon }}</span>
          <span class="min-w-0 break-words text-foreground/85 font-semibold leading-snug">
            {{ todayWeatherLabel ?? '—' }}
          </span>
        </span>
        </div>
        <p class="mt-1.5 text-[11px] leading-snug text-muted-foreground">
          {{ weatherDataCaption }}
        </p>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
          <span class="inline-flex items-center gap-1.5">
          <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">air</span>
          <span class="text-foreground/80">{{ windSpeed != null ? `${windSpeed.toFixed(1)} m/s` : '—' }}</span>
        </span>
          <span class="inline-flex items-center gap-1.5">
          <span class="material-symbols-outlined text-base text-foreground/80" aria-hidden="true">water_drop</span>
          <span class="text-foreground/80">{{ humidity != null ? `${humidity}%` : '—' }}</span>
        </span>
        </div>
        <div v-if="q.isSuccess.value && (sunrise || sunset)" class="mt-1.5 flex items-center gap-2 text-xs sm:text-sm">
          <span class="inline-flex items-center gap-1.5 text-foreground/80">
            <span class="material-symbols-outlined text-base text-amber-400" aria-hidden="true">light_mode</span>
          <span class="text-foreground/80">{{ sunrise ? `Tõuseb ${sunrise}` : '—' }}</span>
          </span>
          <span class="inline-flex items-center gap-1.5 text-foreground/80">
            <span class="material-symbols-outlined text-base text-amber-500" aria-hidden="true">wb_twilight</span>
          <span class="text-foreground/80">{{ sunset ? `Loojub ${sunset}` : '—' }}</span>
          </span>
        </div>
      </div>
    </div>

    <div class="h-px bg-border w-full mb-1.5"></div>

    <div v-if="q.isSuccess.value && forecastDays.length" class="relative overflow-x-auto pb-2">
      <div class="pointer-events-none absolute right-0 top-0 h-full w-10 bg-linear-to-l from-background to-transparent"></div>
      <div class="flex gap-2.5 min-w-0" style="width: max-content;">
        <div
          v-for="d in forecastDays"
          :key="d.date"
          class="flex w-22 shrink-0 flex-col items-center rounded-lg bg-muted/15 p-2 ring-1 ring-border/70 dark:bg-card/70"
        >
          <span class="text-[11px] font-semibold uppercase tracking-wide text-foreground/85">
            {{ new Intl.DateTimeFormat('et-EE', { weekday: 'short' }).format(new Date(d.date)) }}
          </span>

          <img
            v-if="dailyIconUrl(d.icon, true)"
            :src="dailyIconUrl(d.icon, true)!"
            alt=""
            class="w-11 h-11 object-contain mb-1"
            width="56"
            height="56"
          />
          <span v-else class="mb-1 block h-11 w-11" aria-hidden="true" />

          <div class="flex flex-col items-center">
            <span class="text-sm font-bold text-foreground">{{ Math.round(d.tMax ?? 0) }}°</span>
            <span class="text-sm text-muted-foreground">{{ Math.round(d.tMin ?? 0) }}°</span>
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
