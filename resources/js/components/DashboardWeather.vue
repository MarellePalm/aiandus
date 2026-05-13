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
    queryKey: computed(() => [
        'weather',
        coords.value?.latitude,
        coords.value?.longitude,
    ]),
    enabled: queryEnabled,
    queryFn: async (): Promise<WeatherSnapshotFromServer> => {
        const lat = coords.value!.latitude;
        const lon = coords.value!.longitude;
        try {
            const res = await fetch(
                `/api/weather?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}`,
                { credentials: 'same-origin' },
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
                throw new Error(
                    data.message ?? `Ilmapäring ebaõnnestus (${res.status})`,
                );
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
            const msg =
                err instanceof Error ? err.message : 'Ilmapäring ebaõnnestus';
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
/** Tänane on daily[0]; näitame kõiki järgnevaid päevi (OpenWeather ~5–6 päeva). */
const forecastDays = computed(() => daily.value.slice(1));

const todayWeatherLabel = computed(
    () => q.data.value?.openWeatherLabel ?? null,
);
const sunrise = computed(() => q.data.value?.sunrise ?? null);
const sunset = computed(() => q.data.value?.sunset ?? null);
const conditionId = computed(
    () => q.data.value?.openWeatherConditionId ?? null,
);

/** Suur ikoon: WeatherAPI (täpsem „tänane“ tunne), muidu OpenWeather PNG. */
const todayHeroIconUrl = computed(() => {
    const w = q.data.value?.weatherapiIcon;
    if (w) return w;
    const icon = q.data.value?.openWeatherIcon;
    if (!icon) return null;
    return `https://openweathermap.org/img/wn/${icon}@2x.png`;
});

const weatherLabelNormalized = computed(() =>
    (todayWeatherLabel.value ?? '').toLowerCase(),
);

const fallbackWeatherSymbol = computed(() => {
    const label = weatherLabelNormalized.value;
    if (
        label.includes('päike') ||
        label.includes('päikeseline') ||
        label.includes('selge')
    )
        return 'light_mode';
    if (label.includes('äike')) return 'thunderstorm';
    if (label.includes('vihm')) return 'rainy';
    if (label.includes('lumi')) return 'weather_snowy';
    if (label.includes('udu') || label.includes('häg')) return 'foggy';
    return 'cloud';
});

const fallbackWeatherColorClass = computed(() => {
    const label = weatherLabelNormalized.value;
    if (
        label.includes('päike') ||
        label.includes('päikeseline') ||
        label.includes('selge')
    )
        return 'text-amber-400';
    if (label.includes('äike')) return 'text-violet-400';
    if (label.includes('vihm')) return 'text-sky-400';
    if (label.includes('lumi')) return 'text-cyan-200';
    if (label.includes('udu') || label.includes('häg')) return 'text-slate-300';
    return 'text-slate-200';
});

const weatherCardToneClass = computed(() => {
    const id = conditionId.value;
    const label = weatherLabelNormalized.value;

    const isThunder =
        (id != null && id >= 200 && id < 300) || label.includes('äike');
    const isRain =
        (id != null && id >= 500 && id < 600) || label.includes('vihm');
    const isSnow =
        (id != null && id >= 600 && id < 700) || label.includes('lumi');
    const isClear =
        (id != null && id === 800) ||
        label.includes('selge') ||
        label.includes('päike');

    if (isThunder) {
        return 'from-[#e8e4f0]/50 via-[#eeeaf8]/20 to-transparent';
    }
    if (isSnow) {
        return 'from-[#ddeeff]/40 via-[#eef4fa]/20 to-transparent';
    }
    if (isRain) {
        return 'from-[#dde8f2]/45 via-[#eaf2f8]/20 to-transparent';
    }
    if (isClear) {
        return 'from-[#fef3d0]/50 via-[#fdf8ed]/20 to-transparent';
    }
    return 'from-[#f0f4ee]/35 via-transparent to-transparent';
});

type WeatherAlert = {
    id: string;
    label: string;
    icon: string;
    toneClass: string;
};

const weatherAlerts = computed<WeatherAlert[]>(() => {
    const alerts: WeatherAlert[] = [];
    const minTemp = tMin.value;
    const wind = windSpeed.value;
    const id = conditionId.value;
    const label = weatherLabelNormalized.value;

    const hasRainSignal =
        (id != null && id >= 500 && id < 600) || label.includes('vihm');
    const hasThunderSignal = id != null && id >= 200 && id < 300;
    const hasSnowSignal =
        (id != null && id >= 600 && id < 700) || label.includes('lumi');

    if (minTemp != null && minTemp <= 0) {
        alerts.push({
            id: 'frost',
            label: 'Külmaoht',
            icon: 'ac_unit',
            toneClass: 'bg-cyan-50 text-cyan-800 ring-cyan-200',
        });
    }

    if (wind != null && wind >= 11) {
        alerts.push({
            id: 'wind',
            label: 'Tuuline',
            icon: 'air',
            toneClass: 'bg-amber-50 text-amber-800 ring-amber-200',
        });
    }

    if (hasThunderSignal) {
        alerts.push({
            id: 'thunder',
            label: 'Äikeseoht',
            icon: 'thunderstorm',
            toneClass: 'bg-violet-50 text-violet-800 ring-violet-200',
        });
    } else if (hasRainSignal || hasSnowSignal) {
        alerts.push({
            id: 'precip',
            label: hasSnowSignal ? 'Lumi' : 'Sadu tulekul',
            icon: hasSnowSignal ? 'weather_snowy' : 'rainy',
            toneClass: hasSnowSignal
                ? 'bg-sky-50 text-sky-800 ring-sky-200'
                : 'bg-blue-50 text-blue-800 ring-blue-200',
        });
    }

    return alerts.slice(0, 3);
});

function dailyIconUrl(icon: string | null | undefined, retina = false) {
    if (!icon) return null;
    const suffix = retina ? '@2x' : '';
    return `https://openweathermap.org/img/wn/${icon}${suffix}.png`;
}
</script>

<template>
    <div class="weather-card">
        <div
            class="bg-linear-to-br px-4 pt-3 pb-2"
            :class="weatherCardToneClass"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <div
                            v-if="q.isSuccess.value && locationName"
                            class="inline-flex min-w-0 items-center gap-1 text-xs text-muted-foreground"
                        >
                            <span
                                class="material-symbols-outlined shrink-0 text-sm"
                                >location_on</span
                            >
                            <span
                                class="max-w-[110px] truncate sm:max-w-[160px]"
                                >{{ locationName }}</span
                            >
                        </div>
                    </div>

                    <div class="mt-2.5 flex items-end gap-2.5">
                        <span
                            class="text-[32px] leading-none font-bold tracking-tight text-foreground"
                        >
                            <template v-if="q.isSuccess.value && temp !== null">
                                {{ Math.round(temp) }}°C
                            </template>
                            <template v-else>...</template>
                        </span>
                        <span
                            class="pb-0.5 text-xs font-medium text-muted-foreground sm:text-sm"
                        >
                            Max {{ Math.round(tMax ?? 0) }}° / Min
                            {{ Math.round(tMin ?? 0) }}°
                        </span>
                    </div>

                    <div
                        v-if="q.isSuccess.value && weatherAlerts.length"
                        class="mt-2 flex flex-wrap gap-1.5"
                    >
                        <span
                            v-for="alert in weatherAlerts"
                            :key="alert.id"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                            :class="alert.toneClass"
                        >
                            <span class="material-symbols-outlined text-sm">{{
                                alert.icon
                            }}</span>
                            {{ alert.label }}
                        </span>
                    </div>
                </div>

                <div class="shrink-0">
                    <img
                        v-if="q.isSuccess.value && todayHeroIconUrl"
                        :src="todayHeroIconUrl"
                        alt=""
                        class="block h-12 w-12 object-contain opacity-95 drop-shadow-sm"
                        width="96"
                        height="96"
                    />
                    <span
                        v-else-if="q.isSuccess.value"
                        class="material-symbols-outlined block leading-none drop-shadow-sm"
                        :class="fallbackWeatherColorClass"
                        style="
                            font-size: 3rem;
                            font-variation-settings: 'opsz' 72;
                        "
                        aria-hidden="true"
                    >
                        {{ fallbackWeatherSymbol }}
                    </span>
                </div>
            </div>

            <div
                v-if="q.isSuccess.value"
                class="mt-2.5 grid grid-cols-4 border-t border-border/30"
            >
                <div
                    class="border-r border-border/30 px-2 py-1.5 last:border-r-0"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Tuul
                    </p>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <span
                            class="material-symbols-outlined hidden text-base text-foreground/80 sm:inline-flex"
                            aria-hidden="true"
                            >air</span
                        >
                        <span class="text-sm font-medium text-foreground">
                            {{
                                windSpeed != null
                                    ? `${windSpeed.toFixed(1)} m/s`
                                    : '—'
                            }}
                        </span>
                    </div>
                </div>

                <div
                    class="border-r border-border/30 px-2 py-1.5 last:border-r-0"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Niiskus
                    </p>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <span
                            class="material-symbols-outlined hidden text-base text-foreground/80 sm:inline-flex"
                            aria-hidden="true"
                            >water_drop</span
                        >
                        <span class="text-sm font-medium text-foreground">
                            {{ humidity != null ? `${humidity}%` : '—' }}
                        </span>
                    </div>
                </div>

                <div
                    class="border-r border-border/30 px-2 py-1.5 last:border-r-0"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Tõus
                    </p>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <span
                            class="material-symbols-outlined hidden text-base text-amber-400 sm:inline-flex"
                            aria-hidden="true"
                            >light_mode</span
                        >
                        <span class="text-sm font-medium text-foreground">
                            {{ sunrise ?? '—' }}
                        </span>
                    </div>
                </div>

                <div
                    class="border-r border-border/30 px-2 py-1.5 last:border-r-0"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Loojang
                    </p>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <span
                            class="material-symbols-outlined hidden text-base text-amber-500 sm:inline-flex"
                            aria-hidden="true"
                            >wb_twilight</span
                        >
                        <span class="text-sm font-medium text-foreground">
                            {{ sunset ?? '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="q.isSuccess.value && forecastDays.length"
            class="mt-2 border-t border-border/30 pt-2 pb-3"
        >
            <div
                class="flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-1 [-webkit-overflow-scrolling:touch] [scrollbar-gutter:stable]"
            >
                <div
                    v-for="d in forecastDays"
                    :key="d.date"
                    class="flex w-[4.75rem] shrink-0 snap-start flex-col items-center rounded-lg px-1 py-1 text-center sm:w-[5.25rem]"
                >
                    <span
                        class="text-[11px] font-semibold tracking-wide text-foreground/85 uppercase"
                    >
                        {{
                            new Intl.DateTimeFormat('et-EE', {
                                weekday: 'short',
                            }).format(new Date(d.date))
                        }}
                    </span>

                    <img
                        v-if="dailyIconUrl(d.icon, true)"
                        :src="dailyIconUrl(d.icon, true)!"
                        alt=""
                        class="mb-1 h-8 w-8 object-contain sm:h-10 sm:w-10"
                        width="56"
                        height="56"
                    />
                    <span
                        v-else
                        class="mb-1 block h-8 w-8 sm:h-10 sm:w-10"
                        aria-hidden="true"
                    />

                    <div class="flex flex-col items-center">
                        <span class="text-sm font-bold text-foreground"
                            >{{ Math.round(d.tMax ?? 0) }}°</span
                        >
                        <span class="text-xs text-muted-foreground"
                            >{{ Math.round(d.tMin ?? 0) }}°</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="geoError"
            class="mt-4 px-4 pb-3 text-xs text-red-600 dark:text-red-400"
        >
            Asukoht pole lubatud või pole saadaval. Luba brauseris Location
            õigused.
        </div>

        <div
            v-if="q.isError.value"
            class="mt-4 px-4 pb-3 text-xs text-red-600 dark:text-red-400"
        >
            {{ q.error.value?.message }}
        </div>
    </div>
</template>
