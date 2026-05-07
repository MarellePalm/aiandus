<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CalendarSwitchTabs from '@/components/calendar/CalendarSwitchTabs.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getMoonInfo } from '@/lib/moon/moon';
import { moonAdvice } from '@/lib/moon/moonAdvice';
import { calendarMomentForZodiac, getZodiacInfo } from '@/lib/moon/zodiac';
import BottomNav from '@/pages/BottomNav.vue';

import MoonPhaseIcon from './MoonPhaseIcon.vue';

const today = new Date();
const viewDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));

/** Vaikimisi ainult nädal + detail; täiskuu nupuga. */
const showFullMonth = ref(false);
const isDesktop = ref(false);
let desktopMediaQuery: MediaQueryList | null = null;
let handleDesktopChange: ((event: MediaQueryListEvent) => void) | null = null;

const selectedDay = ref<number | null>(null);

const dayLabels = ['E', 'T', 'K', 'N', 'R', 'L', 'P'];

const monthTitle = computed(() =>
    viewDate.value.toLocaleDateString('et-EE', {
        month: 'long',
        year: 'numeric',
    }),
);
const displayFullMonth = computed(() => isDesktop.value || showFullMonth.value);

const daysInMonth = computed(() => {
    const d = viewDate.value;
    return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
});

const startOffset = computed(() => {
    const d = new Date(
        viewDate.value.getFullYear(),
        viewDate.value.getMonth(),
        1,
    );
    const day = d.getDay();
    return (day + 6) % 7;
});

function prevMonth() {
    const d = new Date(viewDate.value);
    d.setMonth(d.getMonth() - 1);
    viewDate.value = d;
}

function nextMonth() {
    const d = new Date(viewDate.value);
    d.setMonth(d.getMonth() + 1);
    viewDate.value = d;
}

function dateForDay(day: number) {
    return new Date(
        viewDate.value.getFullYear(),
        viewDate.value.getMonth(),
        day,
    );
}

function startOfWeekMonday(d: Date): Date {
    const x = new Date(d);
    const dow = x.getDay();
    const mondayOffset = (dow + 6) % 7;
    x.setDate(x.getDate() - mondayOffset);
    x.setHours(0, 0, 0, 0);
    return x;
}

/** Päev, mille ümber nädal ja naaberrida keerlevad. */
const focusDate = computed(() => {
    if (selectedDay.value != null) {
        return dateForDay(selectedDay.value);
    }
    return new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);
});

const weekDays = computed(() => {
    const start = startOfWeekMonday(focusDate.value);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        return d;
    });
});

function isInViewMonth(d: Date): boolean {
    return (
        d.getFullYear() === viewDate.value.getFullYear() &&
        d.getMonth() === viewDate.value.getMonth()
    );
}

function isSameCalendarDate(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function isSelectedDate(d: Date): boolean {
    if (selectedDay.value == null) return false;
    return isSameCalendarDate(d, dateForDay(selectedDay.value));
}

function selectCalendarDate(d: Date) {
    viewDate.value = new Date(d.getFullYear(), d.getMonth(), 1);
    selectedDay.value = d.getDate();
}

function addDays(d: Date, days: number): Date {
    const x = new Date(d);
    x.setDate(x.getDate() + days);
    return x;
}

function shiftFromDate(d: Date, delta: number) {
    selectCalendarDate(addDays(d, delta));
}

function dayInfoForDate(d: Date) {
    const moon = getMoonInfo(d);
    const advice = moonAdvice(moon);
    const zodiac = getZodiacInfo(calendarMomentForZodiac(d));
    const timing = phaseTiming(moon.phase);
    return {
        phase: moon.phase,
        /** Sõbralik nimi (nt „Kuu loomine“, „Noorkuu“). */
        phaseDisplay: advice.displayTitle,
        phaseIndex: moon.phaseIndex,
        illumination: moon.illumination,
        lunationT: moon.lunationT,
        biodynamicLabel: zodiac.biodynamicDayLabel,
        biodynamicCrops: zodiac.crops,
        biodynamicNotes: zodiac.notes,
        tasks: advice.tasks,
        avoid: advice.avoid,
        moodHeadline: advice.moodHeadline,
        leadParagraph: advice.leadParagraph,
        moonSignInessive: zodiac.moonSignInessive,
        bestFor: dayTypeBestFor(zodiac.biodynamicDayLabel),
        notIdealFor:
            timing.confidence === 'hea'
                ? ['Väga tugev tagasilõikus']
                : ['Mahukad istutustööd', 'Õrnade taimede ümberistutus'],
        timingConfidence: timing.confidence,
        timingReason: timing.reason,
    };
}

function dayInfo(day: number) {
    return dayInfoForDate(dateForDay(day));
}

function shortPhaseLabel(phase: string): string {
    const labels: Record<string, string> = {
        Uuskuu: 'Uuskuu',
        'Kasvav sirp': 'Kasvav sirp',
        'Esimene veerand': '1. veerand',
        'Kasvav kumer kuu': 'Kasvav kuu',
        Täiskuu: 'Täiskuu',
        'Kahanev kumer kuu': 'Kahanev kuu',
        'Viimane veerand': 'Viim. veerand',
        'Kahanev sirp': 'Kahanev sirp',
    };

    return labels[phase] ?? phase;
}

function dayTypeBestFor(label: string): string[] {
    if (label === 'Juurepäev')
        return ['Juurviljade külv', 'Juurviljade istutus'];
    if (label === 'Lehepäev')
        return ['Lehtköögiviljade külv', 'Lehttaimede istutus'];
    if (label === 'Lillepäev') return ['Õistaimede külv', 'Õistaimede istutus'];
    if (label === 'Viljapäev')
        return ['Viljataimede külv', 'Viljataimede istutus'];
    return ['Külv', 'Istutamine'];
}

function phaseTiming(phase: string): {
    confidence: 'hea' | 'mõõdukas' | 'pigem väldi';
    reason: string;
} {
    if (
        phase === 'Kasvav sirp' ||
        phase === 'Esimene veerand' ||
        phase === 'Kasvav kumer kuu'
    ) {
        return {
            confidence: 'hea',
            reason: 'Kasvav kuu toetab külvi ja istutamist.',
        };
    }
    if (phase === 'Uuskuu') {
        return {
            confidence: 'pigem väldi',
            reason: 'Uuskuu päeval eelista planeerimist ja ettevalmistustöid.',
        };
    }
    if (phase === 'Täiskuu') {
        return {
            confidence: 'mõõdukas',
            reason: 'Täiskuu ajal sobib pigem hooldus ja korje kui uus istutus.',
        };
    }
    return {
        confidence: 'mõõdukas',
        reason: 'Kahanev kuu soosib rohkem hooldustöid kui uut külvi.',
    };
}

function bestTaskOfDay(tasks: string[]): string | null {
    const task = tasks[0];
    if (!task) return null;

    const normalized = task.trim().toLowerCase();
    const pretty: Record<string, string> = {
        rohi: 'Rohi peenrad',
        harvenda: 'Harvenda taimi',
        komposti: 'Lisa komposti',
        korrasta: 'Korrasta peenrad',
    };

    if (pretty[normalized]) return pretty[normalized];
    return task.charAt(0).toUpperCase() + task.slice(1);
}

function formatDateLong(d: Date) {
    return d.toLocaleDateString('et-EE', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function isToday(d: Date): boolean {
    return isSameCalendarDate(d, new Date());
}

function fullMonthDayIsToday(day: number): boolean {
    return isToday(dateForDay(day));
}

function weekDayCellClass(d: Date): string {
    const selected = isSelectedDate(d);
    const today = isToday(d);
    const out = !isInViewMonth(d);
    const parts: string[] = [
        'moon-cal-cell relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl border px-1 py-1 text-left transition duration-200 sm:min-h-15 lg:min-h-24 lg:gap-2 lg:rounded-2xl lg:px-2 lg:py-2',
        'border-indigo-200/35 bg-linear-to-b from-white via-white to-amber-50/25 shadow-[inset_0_1px_0_rgba(255,255,255,0.95),0_2px_8px_rgba(30,42,74,0.06)]',
        'hover:z-[1] hover:-translate-y-0.5 hover:border-amber-300/55 hover:shadow-[0_6px_16px_rgba(251,191,36,0.18)]',
        'dark:border-white/12 dark:from-slate-800/95 dark:via-slate-800/90 dark:to-indigo-950/50 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.06),0_2px_10px_rgba(0,0,0,0.25)]',
        'dark:hover:border-amber-400/35 dark:hover:shadow-[0_6px_18px_rgba(251,191,36,0.12)]',
    ];
    if (out) parts.push('opacity-40');
    if (selected) {
        parts.push(
            'z-[2] border-amber-400/70 bg-linear-to-b from-amber-100/90 via-amber-50/50 to-white ring-2 ring-amber-400/75 shadow-[0_0_0_3px_rgba(251,191,36,0.22),0_8px_20px_rgba(217,119,6,0.15)]',
            'dark:from-amber-500/25 dark:via-amber-600/15 dark:to-indigo-950/60 dark:ring-amber-400/50',
        );
    } else if (today) {
        parts.push(
            'ring-2 ring-primary/35 ring-offset-2 ring-offset-background dark:ring-offset-slate-900',
        );
    }
    return parts.join(' ');
}

function monthDayCellClass(day: number): string {
    const selected = selectedDay.value === day;
    const today = fullMonthDayIsToday(day);
    const parts: string[] = [
        'moon-cal-cell flex min-h-14 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border px-1 py-1 text-left transition duration-200 sm:min-h-15 sm:px-1.5 lg:min-h-24 lg:gap-2 lg:rounded-2xl lg:px-2 lg:py-2',
        'border-indigo-200/35 bg-linear-to-b from-white via-white to-amber-50/25 shadow-[inset_0_1px_0_rgba(255,255,255,0.95),0_2px_8px_rgba(30,42,74,0.06)]',
        'hover:z-[1] hover:-translate-y-0.5 hover:border-amber-300/55 hover:shadow-[0_6px_16px_rgba(251,191,36,0.18)]',
        'dark:border-white/12 dark:from-slate-800/95 dark:via-slate-800/90 dark:to-indigo-950/50 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.06),0_2px_10px_rgba(0,0,0,0.25)]',
        'dark:hover:border-amber-400/35 dark:hover:shadow-[0_6px_18px_rgba(251,191,36,0.12)]',
    ];
    if (selected) {
        parts.push(
            'z-[2] border-amber-400/70 bg-linear-to-b from-amber-100/90 via-amber-50/50 to-white ring-2 ring-amber-400/75 shadow-[0_0_0_3px_rgba(251,191,36,0.22),0_8px_20px_rgba(217,119,6,0.15)]',
            'dark:from-amber-500/25 dark:via-amber-600/15 dark:to-indigo-950/60 dark:ring-amber-400/50',
        );
    } else if (today) {
        parts.push(
            'ring-2 ring-primary/35 ring-offset-2 ring-offset-background dark:ring-offset-slate-900',
        );
    }
    return parts.join(' ');
}

function weekdayHeaderClass(): string {
    return 'pb-0.5 text-center text-[11px] font-bold tracking-[0.12em] text-foreground/75 uppercase sm:text-xs lg:pb-1 lg:text-[13px] lg:font-semibold lg:tracking-normal lg:normal-case lg:text-foreground/80 dark:text-amber-100/85';
}

const selectedDateObj = computed(() =>
    selectedDay.value == null ? null : dateForDay(selectedDay.value),
);
const selectedInfo = computed(() =>
    selectedDateObj.value ? dayInfoForDate(selectedDateObj.value) : null,
);
const selectedDateLabel = computed(() =>
    selectedDateObj.value ? formatDateLong(selectedDateObj.value) : null,
);
const selectedLeadText = computed(
    () => selectedInfo.value?.leadParagraph ?? '',
);
const selectedBestTask = computed(() =>
    selectedInfo.value ? bestTaskOfDay(selectedInfo.value.tasks) : null,
);
const selectedTasks = computed(() => {
    const tasks = selectedInfo.value?.tasks ?? [];
    if (!tasks.length) return [];
    return (selectedBestTask.value ? tasks.slice(1) : tasks).slice(0, 3);
});
const selectedTaskSummary = computed(() =>
    [selectedBestTask.value, ...selectedTasks.value].filter(Boolean).join(', '),
);
const selectedNotIdealFor = computed(
    () => selectedInfo.value?.notIdealFor.slice(0, 2) ?? [],
);
const selectedTipsSummary = computed(() => {
    const notes = selectedInfo.value?.biodynamicNotes ?? [];
    if (notes.length) return notes.slice(0, 2).join(' ');
    return selectedLeadText.value;
});
const selectedTimingBadge = computed(() => {
    if (!selectedInfo.value) return '';
    if (selectedInfo.value.timingConfidence === 'hea') return 'Hea aeg';
    if (selectedInfo.value.timingConfidence === 'mõõdukas')
        return 'Mõõdukas aeg';
    return 'Pigem väldi';
});

// Tähekesed öötaeva-tausta jaoks (deterministlikud).
const moonPanelStars = [
    { x: '6%', y: '14%', size: 2, delay: '0s', duration: '3.4s' },
    { x: '20%', y: '74%', size: 1.5, delay: '0.6s', duration: '2.9s' },
    { x: '34%', y: '8%', size: 2.4, delay: '1.2s', duration: '3.8s' },
    { x: '48%', y: '32%', size: 1.6, delay: '0.4s', duration: '3.2s' },
    { x: '62%', y: '70%', size: 1.8, delay: '1.8s', duration: '3.6s' },
    { x: '76%', y: '18%', size: 2.2, delay: '0.9s', duration: '2.7s' },
    { x: '88%', y: '60%', size: 1.4, delay: '2.4s', duration: '4s' },
    { x: '14%', y: '92%', size: 1.7, delay: '1.4s', duration: '3.3s' },
    { x: '54%', y: '90%', size: 2, delay: '0.2s', duration: '3.5s' },
    { x: '92%', y: '36%', size: 1.5, delay: '2.7s', duration: '3.1s' },
] as const;

const isWaxing = computed(
    () =>
        selectedInfo.value != null &&
        selectedInfo.value.lunationT != null &&
        selectedInfo.value.lunationT < 0.5,
);

const selectedIlluminationPercent = computed(() =>
    Math.round((selectedInfo.value?.illumination ?? 0) * 100),
);

function syncSelectedDayWithMonth() {
    const maxDay = daysInMonth.value;

    // Kui kasutaja on päeva juba valinud, hoia valik alles.
    if (selectedDay.value != null) {
        if (selectedDay.value < 1) selectedDay.value = 1;
        if (selectedDay.value > maxDay) selectedDay.value = maxDay;
        return;
    }

    // Esmakordsel avamisel: täna jooksvas kuus, muidu kuu 1. päev.
    const d = new Date();
    const isSameMonth =
        d.getFullYear() === viewDate.value.getFullYear() &&
        d.getMonth() === viewDate.value.getMonth();
    selectedDay.value = isSameMonth ? d.getDate() : 1;
}

watch(viewDate, () => syncSelectedDayWithMonth(), { immediate: true });

onMounted(() => {
    try {
        desktopMediaQuery = window.matchMedia('(min-width: 1024px)');
        isDesktop.value = desktopMediaQuery.matches;
        handleDesktopChange = (event: MediaQueryListEvent) => {
            isDesktop.value = event.matches;
        };
        desktopMediaQuery.addEventListener('change', handleDesktopChange);
    } catch {
        /* ignore */
    }
});

onUnmounted(() => {
    if (desktopMediaQuery && handleDesktopChange) {
        desktopMediaQuery.removeEventListener('change', handleDesktopChange);
    }
});
</script>

<template>
    <Head title="Kuukalender" />
    <AppLayout>
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title="Kuukalender"
                        header-class="pt-6"
                        top-row-class="mb-3"
                        bottom-row-class="mb-4"
                    >
                        <template #leading>
                            <BackIconButton
                                href="/calendar"
                                aria-label="Tagasi kalendrisse"
                            />
                        </template>
                        <div class="flex items-center justify-center pb-2">
                            <CalendarSwitchTabs active="moon" />
                        </div>
                    </DiaryHeader>

                    <main
                        class="mx-auto flex-1 space-y-4 px-6 py-4 md:max-w-6xl md:px-8 lg:grid lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.95fr)] lg:items-start lg:gap-6 lg:space-y-0"
                    >
                        <section
                            v-if="selectedDateObj && selectedInfo"
                            class="moon-night-panel relative mx-auto hidden w-full max-w-lg overflow-hidden rounded-[1.5rem] shadow-[0_12px_32px_rgba(15,23,42,0.22)] ring-1 ring-white/10 lg:sticky lg:top-6 lg:order-2 lg:mx-0 lg:block lg:max-w-none"
                        >
                            <div
                                class="moon-night-bg absolute inset-0"
                                aria-hidden="true"
                            />
                            <span
                                v-for="(star, starIndex) in moonPanelStars"
                                :key="`star-d-${starIndex}`"
                                class="moon-night-star"
                                aria-hidden="true"
                                :style="{
                                    left: star.x,
                                    top: star.y,
                                    width: `${star.size}px`,
                                    height: `${star.size}px`,
                                    animationDelay: star.delay,
                                    animationDuration: star.duration,
                                }"
                            />

                            <div class="relative px-4 py-4">
                                <div
                                    class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3"
                                >
                                    <div class="min-w-0 text-white/95">
                                        <p
                                            class="text-[10px] font-semibold tracking-[0.22em] text-white/55 uppercase"
                                        >
                                            {{ selectedInfo.biodynamicLabel }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-white/70"
                                        >
                                            {{ selectedDateLabel }}
                                        </p>
                                        <h2
                                            class="mt-1 text-lg font-bold tracking-tight"
                                        >
                                            {{ selectedInfo.phaseDisplay }}
                                        </h2>
                                        <p class="mt-0.5 text-sm text-white/75">
                                            {{
                                                isWaxing
                                                    ? 'Kasvav'
                                                    : 'Kahanev'
                                            }}
                                            · valgustatud
                                            <span
                                                class="font-semibold text-white tabular-nums"
                                            >
                                                {{
                                                    selectedIlluminationPercent
                                                }}%
                                            </span>
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm font-medium text-white drop-shadow-[0_1px_1px_rgba(0,0,0,0.45)]"
                                        >
                                            {{ selectedInfo.moodHeadline }}
                                        </p>
                                    </div>
                                    <div class="moon-night-disc relative shrink-0">
                                        <span
                                            class="moon-night-glow"
                                            aria-hidden="true"
                                        />
                                        <div class="moon-night-float relative">
                                            <MoonPhaseIcon
                                                :lunation-t="
                                                    selectedInfo.lunationT
                                                "
                                                :phase-index="
                                                    selectedInfo.phaseIndex
                                                "
                                                :size="56"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white/95 ring-1 ring-white/15"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[14px] text-amber-200"
                                            aria-hidden="true"
                                            >auto_awesome</span
                                        >
                                        Kuu on
                                        {{ selectedInfo.moonSignInessive }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="relative space-y-3 border-t border-white/10 bg-linear-to-b from-white/[0.05] to-white/[0.1] px-4 py-4 backdrop-blur-[2px]"
                            >
                                <div
                                    class="rounded-2xl bg-white/[0.06] px-4 py-3 ring-1 ring-white/12"
                                >
                                    <div
                                        class="mb-2 flex items-center justify-between gap-2"
                                    >
                                        <p
                                            class="text-[10px] font-semibold tracking-[0.18em] text-white/65 uppercase"
                                        >
                                            Külv ja istutus täna
                                        </p>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                                            :class="
                                                selectedInfo?.timingConfidence ===
                                                'hea'
                                                    ? 'bg-emerald-300/15 text-emerald-50 ring-emerald-200/30'
                                                    : selectedInfo?.timingConfidence ===
                                                        'mõõdukas'
                                                      ? 'bg-amber-300/15 text-amber-50 ring-amber-200/30'
                                                      : 'bg-rose-300/15 text-rose-50 ring-rose-200/30'
                                            "
                                        >
                                            {{ selectedTimingBadge }}
                                        </span>
                                    </div>
                                    <p class="text-sm leading-snug text-white/85">
                                        {{ selectedInfo?.timingReason }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl bg-emerald-300/12 px-4 py-3 ring-1 ring-emerald-200/25"
                                >
                                    <p
                                        class="flex items-center justify-between text-[10px] font-semibold tracking-[0.18em] text-emerald-50/90 uppercase"
                                    >
                                        <span>Täna tee</span>
                                        <span
                                            class="material-symbols-outlined align-[-2px] text-[14px] text-emerald-200 normal-case"
                                            >check_circle</span
                                        >
                                    </p>
                                    <p
                                        v-if="selectedTaskSummary"
                                        class="mt-1.5 text-[15px] leading-7 font-medium text-white"
                                    >
                                        {{ selectedTaskSummary }}
                                    </p>
                                    <p
                                        v-else
                                        class="mt-1.5 text-[15px] leading-7 text-white/75"
                                    >
                                        Täna ei ole erisoovitusi.
                                    </p>
                                </div>

                                <div
                                    v-if="selectedNotIdealFor.length"
                                    class="rounded-2xl bg-rose-300/12 px-4 py-3 ring-1 ring-rose-200/25"
                                >
                                    <p
                                        class="flex items-center justify-between text-[10px] font-semibold tracking-[0.18em] text-rose-50/90 uppercase"
                                    >
                                        <span>Ära tee täna</span>
                                        <span
                                            class="material-symbols-outlined align-[-2px] text-[14px] text-rose-200 normal-case"
                                            >block</span
                                        >
                                    </p>
                                    <p
                                        class="mt-1.5 text-[14px] leading-6 text-white/90"
                                    >
                                        {{ selectedNotIdealFor.join(', ') }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl bg-white/[0.05] px-4 py-3 ring-1 ring-white/10"
                                >
                                    <p
                                        class="flex items-center justify-between text-[10px] font-semibold tracking-[0.18em] text-white/70 uppercase"
                                    >
                                        <span>Tasub meeles pidada</span>
                                        <span
                                            class="material-symbols-outlined align-[-2px] text-[14px] text-white/70 normal-case"
                                            >info</span
                                        >
                                    </p>
                                    <p
                                        v-if="selectedTipsSummary"
                                        class="mt-1.5 text-[14px] leading-6 text-white/85"
                                    >
                                        {{ selectedTipsSummary }}
                                    </p>
                                    <p
                                        v-else
                                        class="mt-1.5 text-[14px] leading-6 text-white/70"
                                    >
                                        Täna piisab rahulikust hooldusest ja
                                        jälgimisest.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            class="moon-cal-shell relative mx-auto w-full max-w-lg overflow-hidden rounded-[1.4rem] border border-indigo-300/30 bg-linear-to-br from-indigo-50/85 via-white to-amber-50/55 p-2.5 shadow-[0_10px_28px_rgba(30,42,74,0.12)] ring-1 ring-amber-300/25 sm:p-3.5 lg:order-1 lg:mx-0 lg:max-w-none dark:border-white/10 dark:from-slate-900/90 dark:via-slate-900/85 dark:to-indigo-950/80 dark:ring-amber-400/15"
                        >
                            <span
                                class="pointer-events-none absolute -top-10 -right-8 h-32 w-32 rounded-full bg-amber-300/20 blur-3xl dark:bg-amber-500/15"
                                aria-hidden="true"
                            />
                            <span
                                class="pointer-events-none absolute -bottom-12 -left-10 h-28 w-28 rounded-full bg-indigo-400/20 blur-3xl dark:bg-indigo-500/20"
                                aria-hidden="true"
                            />

                            <div
                                class="relative mb-3 flex items-center justify-between lg:mb-4"
                            >
                                <button
                                    type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-white/85 text-indigo-700 ring-1 ring-indigo-200/60 shadow-sm transition hover:bg-white hover:text-amber-600 hover:ring-amber-300/60 dark:bg-white/10 dark:text-amber-100 dark:ring-white/15 dark:hover:bg-white/15"
                                    @click="prevMonth"
                                    aria-label="Eelmine kuu"
                                >
                                    <span class="material-symbols-outlined"
                                        >chevron_left</span
                                    >
                                </button>
                                <h2
                                    class="flex items-center gap-2 text-base font-bold capitalize sm:text-lg lg:text-xl"
                                >
                                    <span
                                        class="material-symbols-outlined text-base text-amber-600/80 dark:text-amber-200/85"
                                        aria-hidden="true"
                                    >
                                        nights_stay
                                    </span>
                                    {{ monthTitle }}
                                </h2>
                                <button
                                    type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-white/85 text-indigo-700 ring-1 ring-indigo-200/60 shadow-sm transition hover:bg-white hover:text-amber-600 hover:ring-amber-300/60 dark:bg-white/10 dark:text-amber-100 dark:ring-white/15 dark:hover:bg-white/15"
                                    @click="nextMonth"
                                    aria-label="Järgmine kuu"
                                >
                                    <span class="material-symbols-outlined"
                                        >chevron_right</span
                                    >
                                </button>
                            </div>

                            <!-- Nädalavaade -->
                            <template v-if="!displayFullMonth">
                                <div
                                    class="relative grid grid-cols-7 gap-x-1 gap-y-1.5 sm:gap-x-1.5 sm:gap-y-2 lg:gap-x-2.5 lg:gap-y-3"
                                >
                                    <div
                                        v-for="lbl in dayLabels"
                                        :key="'w-' + lbl"
                                        :class="weekdayHeaderClass()"
                                    >
                                        {{ lbl }}
                                    </div>
                                    <button
                                        v-for="d in weekDays"
                                        :key="d.getTime()"
                                        type="button"
                                        :class="weekDayCellClass(d)"
                                        :aria-pressed="isSelectedDate(d)"
                                        @click="selectCalendarDate(d)"
                                    >
                                        <span
                                            class="shrink-0 text-[11px] leading-none font-semibold sm:text-xs"
                                            :class="
                                                isToday(d)
                                                    ? 'text-amber-600 dark:text-amber-200'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{ d.getDate() }}
                                        </span>
                                        <MoonPhaseIcon
                                            :lunation-t="
                                                dayInfoForDate(d).lunationT
                                            "
                                            :phase-index="
                                                dayInfoForDate(d).phaseIndex
                                            "
                                            :size="26"
                                            class="shrink-0 drop-shadow-[0_0_4px_rgba(251,191,36,0.25)]"
                                        />
                                        <span
                                            class="hidden text-center text-[10px] leading-tight font-medium text-foreground/75 lg:block dark:text-amber-100/85"
                                            :title="
                                                dayInfoForDate(d).phaseDisplay
                                            "
                                        >
                                            {{
                                                shortPhaseLabel(
                                                    dayInfoForDate(d)
                                                        .phaseDisplay,
                                                )
                                            }}
                                        </span>
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="mt-3 w-full rounded-xl border border-border bg-muted/30 py-2.5 text-sm font-semibold text-foreground transition hover:bg-muted/50 lg:hidden"
                                    @click="showFullMonth = true"
                                >
                                    Vaata tervet kuud
                                </button>
                            </template>

                            <!-- Terve kuu -->
                            <template v-else>
                                <div class="mb-2 flex justify-end lg:mb-3">
                                    <button
                                        type="button"
                                        class="text-sm font-semibold text-primary hover:underline lg:hidden"
                                        @click="showFullMonth = false"
                                    >
                                        ← Näita ainult nädalat
                                    </button>
                                </div>

                                <div
                                    class="relative grid grid-cols-7 gap-x-1 gap-y-1.5 sm:gap-x-1.5 sm:gap-y-2 lg:gap-x-2.5 lg:gap-y-3"
                                >
                                    <div
                                        v-for="lbl in dayLabels"
                                        :key="lbl"
                                        :class="weekdayHeaderClass()"
                                    >
                                        {{ lbl }}
                                    </div>

                                    <div
                                        v-for="n in startOffset"
                                        :key="'sp-' + n"
                                        class="min-h-13 sm:min-h-14 md:min-h-15 lg:min-h-24"
                                    />

                                    <div
                                        v-for="day in daysInMonth"
                                        :key="day"
                                        :class="monthDayCellClass(day)"
                                        role="button"
                                        tabindex="0"
                                        :aria-pressed="selectedDay === day"
                                        @click="selectedDay = day"
                                        @keydown.enter="selectedDay = day"
                                    >
                                        <span
                                            class="shrink-0 text-[11px] leading-none font-semibold sm:text-xs"
                                            :class="
                                                fullMonthDayIsToday(day)
                                                    ? 'text-amber-600 dark:text-amber-200'
                                                    : 'text-foreground'
                                            "
                                            >{{ day }}</span
                                        >
                                        <MoonPhaseIcon
                                            :lunation-t="dayInfo(day).lunationT"
                                            :phase-index="
                                                dayInfo(day).phaseIndex
                                            "
                                            :size="26"
                                            class="shrink-0 drop-shadow-[0_0_4px_rgba(251,191,36,0.25)]"
                                        />
                                        <span
                                            class="hidden text-center text-[10px] leading-tight font-medium text-foreground/75 lg:block dark:text-amber-100/85"
                                            :title="dayInfo(day).phaseDisplay"
                                        >
                                            {{
                                                shortPhaseLabel(
                                                    dayInfo(day).phaseDisplay,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <section
                                v-if="selectedDateObj && selectedInfo"
                                class="moon-night-panel relative mt-3 overflow-hidden rounded-[1.35rem] shadow-[0_10px_28px_rgba(15,23,42,0.18)] ring-1 ring-white/10 lg:hidden"
                            >
                                <div
                                    class="moon-night-bg absolute inset-0"
                                    aria-hidden="true"
                                />
                                <span
                                    v-for="(star, starIndex) in moonPanelStars"
                                    :key="`star-m-${starIndex}`"
                                    class="moon-night-star"
                                    aria-hidden="true"
                                    :style="{
                                        left: star.x,
                                        top: star.y,
                                        width: `${star.size}px`,
                                        height: `${star.size}px`,
                                        animationDelay: star.delay,
                                        animationDuration: star.duration,
                                    }"
                                />

                                <div
                                    class="relative flex items-center gap-2 border-b border-white/10 px-2.5 py-2"
                                >
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/8 text-white/85 ring-1 ring-white/15 transition hover:bg-white/15"
                                        aria-label="Eelmine päev"
                                        @click="
                                            shiftFromDate(selectedDateObj, -1)
                                        "
                                    >
                                        <span class="material-symbols-outlined"
                                            >chevron_left</span
                                        >
                                    </button>
                                    <div class="min-w-0 flex-1 text-center">
                                        <p
                                            class="text-sm leading-tight font-semibold text-white"
                                        >
                                            {{ selectedDateLabel }}
                                        </p>
                                        <p
                                            v-if="isToday(selectedDateObj)"
                                            class="mt-0.5 text-xs font-semibold tracking-[0.16em] text-amber-200 uppercase"
                                        >
                                            Täna
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/8 text-white/85 ring-1 ring-white/15 transition hover:bg-white/15"
                                        aria-label="Järgmine päev"
                                        @click="
                                            shiftFromDate(selectedDateObj, 1)
                                        "
                                    >
                                        <span class="material-symbols-outlined"
                                            >chevron_right</span
                                        >
                                    </button>
                                </div>

                                <div class="relative space-y-3 px-3 py-3">
                                    <div
                                        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3"
                                    >
                                        <div class="min-w-0 text-white/95">
                                            <p
                                                class="text-[10px] font-semibold tracking-[0.22em] text-white/55 uppercase"
                                            >
                                                {{
                                                    selectedInfo.biodynamicLabel
                                                }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-base leading-tight font-bold tracking-tight"
                                            >
                                                {{ selectedInfo.phaseDisplay }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs text-white/70 sm:text-sm"
                                            >
                                                {{
                                                    isWaxing
                                                        ? 'Kasvav'
                                                        : 'Kahanev'
                                                }}
                                                · valgustatud
                                                <span
                                                    class="font-semibold text-white tabular-nums"
                                                >
                                                    {{
                                                        selectedIlluminationPercent
                                                    }}%
                                                </span>
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-medium text-white drop-shadow-[0_1px_1px_rgba(0,0,0,0.45)]"
                                            >
                                                {{ selectedInfo.moodHeadline }}
                                            </p>
                                        </div>
                                        <div
                                            class="moon-night-disc relative shrink-0"
                                        >
                                            <span
                                                class="moon-night-glow"
                                                aria-hidden="true"
                                            />
                                            <div
                                                class="moon-night-float relative"
                                            >
                                                <MoonPhaseIcon
                                                    :lunation-t="
                                                        selectedInfo.lunationT
                                                    "
                                                    :phase-index="
                                                        selectedInfo.phaseIndex
                                                    "
                                                    :size="44"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-xl bg-white/[0.06] px-3 py-2.5 ring-1 ring-white/12"
                                    >
                                        <div
                                            class="mb-1.5 flex items-center justify-between gap-2"
                                        >
                                            <p
                                                class="text-[10px] font-semibold tracking-[0.18em] text-white/65 uppercase"
                                            >
                                                Külv ja istutus täna
                                            </p>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                                                :class="
                                                    selectedInfo?.timingConfidence ===
                                                    'hea'
                                                        ? 'bg-emerald-300/15 text-emerald-50 ring-emerald-200/30'
                                                        : selectedInfo?.timingConfidence ===
                                                            'mõõdukas'
                                                          ? 'bg-amber-300/15 text-amber-50 ring-amber-200/30'
                                                          : 'bg-rose-300/15 text-rose-50 ring-rose-200/30'
                                                "
                                            >
                                                {{ selectedTimingBadge }}
                                            </span>
                                        </div>
                                        <p
                                            class="text-sm leading-snug text-white/85"
                                        >
                                            {{ selectedInfo?.timingReason }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="selectedTaskSummary"
                                        class="rounded-xl bg-emerald-300/12 px-3 py-2 ring-1 ring-emerald-200/25"
                                    >
                                        <p
                                            class="flex items-center justify-between text-[10px] font-semibold tracking-[0.18em] text-emerald-50/90 uppercase"
                                        >
                                            <span>Täna tee</span>
                                            <span
                                                class="material-symbols-outlined align-[-2px] text-[13px] text-emerald-200 normal-case"
                                                >check_circle</span
                                            >
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm leading-snug font-medium text-white"
                                        >
                                            {{ selectedTaskSummary }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="selectedNotIdealFor.length"
                                        class="rounded-xl bg-rose-300/12 px-3 py-2 ring-1 ring-rose-200/25"
                                    >
                                        <p
                                            class="flex items-center justify-between text-[10px] font-semibold tracking-[0.18em] text-rose-50/90 uppercase"
                                        >
                                            <span>Ära tee täna</span>
                                            <span
                                                class="material-symbols-outlined align-[-2px] text-[13px] text-rose-200 normal-case"
                                                >block</span
                                            >
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm leading-snug text-white/90"
                                        >
                                            {{ selectedNotIdealFor.join(', ') }}
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-xl bg-white/[0.05] px-3 py-2 ring-1 ring-white/10"
                                    >
                                        <p
                                            class="flex items-center justify-between text-[10px] font-semibold tracking-[0.18em] text-white/70 uppercase"
                                        >
                                            <span>Tasub meeles pidada</span>
                                            <span
                                                class="material-symbols-outlined align-[-2px] text-[13px] text-white/70 normal-case"
                                                >info</span
                                            >
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm leading-relaxed text-white/85"
                                        >
                                            {{
                                                selectedTipsSummary ||
                                                'Täna piisab rahulikust hooldusest ja jälgimisest.'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </section>
                    </main>
                </div>

                <BottomNav active="calendar" />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.moon-night-bg {
    background:
        radial-gradient(
            120% 120% at 85% 10%,
            rgba(255, 224, 153, 0.18) 0%,
            transparent 55%
        ),
        radial-gradient(
            90% 80% at 15% 90%,
            rgba(118, 150, 232, 0.22) 0%,
            transparent 55%
        ),
        linear-gradient(135deg, #1e2a4a 0%, #2c3868 45%, #3b3a6a 100%);
}

.moon-night-star {
    position: absolute;
    border-radius: 9999px;
    background: radial-gradient(
        circle,
        rgba(255, 255, 255, 0.95) 0%,
        rgba(255, 255, 255, 0.6) 60%,
        transparent 100%
    );
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.55);
    animation-name: moon-night-twinkle;
    animation-iteration-count: infinite;
    animation-timing-function: ease-in-out;
    will-change: opacity, transform;
}
@keyframes moon-night-twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.85);
    }
    50% {
        opacity: 1;
        transform: scale(1.15);
    }
}

.moon-night-disc {
    width: 56px;
    height: 56px;
}
.moon-night-glow {
    position: absolute;
    inset: -10px;
    border-radius: 9999px;
    background: radial-gradient(
        circle,
        rgba(255, 220, 130, 0.55) 0%,
        rgba(255, 220, 130, 0.18) 45%,
        transparent 75%
    );
    filter: blur(6px);
    animation: moon-night-glow-pulse 5.5s ease-in-out infinite;
    pointer-events: none;
}
@keyframes moon-night-glow-pulse {
    0%,
    100% {
        opacity: 0.7;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.08);
    }
}

.moon-night-float {
    animation: moon-night-float 6s ease-in-out infinite;
    will-change: transform;
}
@keyframes moon-night-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -3px, 0);
    }
}

.moon-cal-shell {
    background-image:
        radial-gradient(
            circle at 18% 22%,
            rgba(251, 191, 36, 0.07) 0,
            transparent 60%
        ),
        radial-gradient(
            circle at 82% 78%,
            rgba(99, 102, 241, 0.08) 0,
            transparent 60%
        ),
        radial-gradient(
            circle at 50% 50%,
            transparent 0,
            transparent 100%
        );
}
:global(.dark) .moon-cal-shell {
    background-image:
        radial-gradient(
            circle at 18% 22%,
            rgba(251, 191, 36, 0.1) 0,
            transparent 55%
        ),
        radial-gradient(
            circle at 82% 78%,
            rgba(99, 102, 241, 0.18) 0,
            transparent 55%
        );
}

.moon-cal-cell {
    will-change: transform;
}

@media (prefers-reduced-motion: reduce) {
    .moon-night-star,
    .moon-night-glow,
    .moon-night-float {
        animation: none !important;
    }
    .moon-cal-cell {
        transition: none !important;
    }
}
</style>
