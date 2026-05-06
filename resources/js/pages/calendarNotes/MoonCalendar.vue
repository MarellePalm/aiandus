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
    if (label === 'Juurepäev') return ['Juurviljade külv', 'Juurviljade istutus'];
    if (label === 'Lehepäev') return ['Lehtköögiviljade külv', 'Lehttaimede istutus'];
    if (label === 'Lillepäev') return ['Õistaimede külv', 'Õistaimede istutus'];
    if (label === 'Viljapäev') return ['Viljataimede külv', 'Viljataimede istutus'];
    return ['Külv', 'Istutamine'];
}

function phaseTiming(
    phase: string,
): { confidence: 'hea' | 'mõõdukas' | 'pigem väldi'; reason: string } {
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

const selectedDateObj = computed(() =>
    selectedDay.value == null ? null : dateForDay(selectedDay.value),
);
const selectedInfo = computed(() =>
    selectedDateObj.value ? dayInfoForDate(selectedDateObj.value) : null,
);
const selectedDateLabel = computed(() =>
    selectedDateObj.value ? formatDateLong(selectedDateObj.value) : null,
);
const selectedLeadText = computed(() => selectedInfo.value?.leadParagraph ?? '');
const selectedBestTask = computed(() =>
    selectedInfo.value ? bestTaskOfDay(selectedInfo.value.tasks) : null,
);
const selectedTasks = computed(() => {
    const tasks = selectedInfo.value?.tasks ?? [];
    if (!tasks.length) return [];
    return (selectedBestTask.value ? tasks.slice(1) : tasks).slice(0, 3);
});
const selectedAvoid = computed(() => selectedInfo.value?.avoid.slice(0, 2) ?? []);
const selectedTaskSummary = computed(() =>
    [selectedBestTask.value, ...selectedTasks.value].filter(Boolean).join(', '),
);
const selectedAvoidSummary = computed(() => selectedAvoid.value.join(' '));
const selectedBestFor = computed(() => selectedInfo.value?.bestFor.slice(0, 2) ?? []);
const selectedNotIdealFor = computed(() => selectedInfo.value?.notIdealFor.slice(0, 2) ?? []);
const selectedTipsSummary = computed(() => {
    const notes = selectedInfo.value?.biodynamicNotes ?? [];
    if (notes.length) return notes.slice(0, 2).join(' ');
    return selectedLeadText.value;
});
const selectedTimingBadge = computed(() => {
    if (!selectedInfo.value) return '';
    if (selectedInfo.value.timingConfidence === 'hea') return 'Hea aeg';
    if (selectedInfo.value.timingConfidence === 'mõõdukas') return 'Mõõdukas aeg';
    return 'Pigem väldi';
});

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
                            class="mx-auto hidden w-full max-w-lg rounded-[1.5rem] border border-border/70 bg-linear-to-br from-stone-50 via-background to-stone-100/70 p-3.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.85),0_8px_24px_rgba(120,110,92,0.06)] lg:order-2 lg:mx-0 lg:block lg:max-w-none lg:sticky lg:top-6"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-15 w-15 shrink-0 items-center justify-center rounded-[1.15rem] border border-border/70 bg-white/90 shadow-[inset_0_1px_0_rgba(255,255,255,0.9),0_6px_18px_rgba(120,110,92,0.08)]"
                                >
                                    <MoonPhaseIcon
                                        :lunation-t="selectedInfo.lunationT"
                                        :phase-index="selectedInfo.phaseIndex"
                                        :size="42"
                                        class="drop-shadow-sm"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Valitud päev
                                    </p>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ selectedDateLabel }}
                                    </p>
                                    <h2
                                        class="mt-1 text-lg font-bold tracking-tight text-foreground"
                                    >
                                        <span class="font-bold">{{
                                            selectedInfo.phaseDisplay
                                        }}</span>
                                        <span class="text-foreground/70"
                                            > -  {{ selectedInfo.biodynamicLabel }}</span
                                        >
                                    </h2>
                                    <p class="mt-0.5 text-sm text-foreground/80">
                                        {{ selectedInfo.moodHeadline }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span
                                    class="inline-flex rounded-full border border-border/70 bg-white/80 px-2.5 py-1 text-xs font-medium text-foreground shadow-sm"
                                >
                                    Kuu on {{ selectedInfo.moonSignInessive }}
                                </span>
                            </div>

                            <div class="mt-4 hidden space-y-3 lg:block">
                                <div
                                    class="rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.12em] text-primary/90 uppercase"
                                        >
                                            Külv ja istutus täna
                                        </p>
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="
                                                selectedInfo?.timingConfidence === 'hea'
                                                    ? 'border-primary/30 bg-primary/10 text-primary'
                                                    : selectedInfo?.timingConfidence === 'mõõdukas'
                                                      ? 'border-amber-200/60 bg-amber-50/45 text-amber-700/80'
                                                      : 'border-rose-200/60 bg-rose-50/45 text-rose-700/75'
                                            "
                                        >
                                            {{ selectedTimingBadge }}
                                        </span>
                                    </div>

                                    <p class="mb-2 text-sm text-foreground/80">
                                        {{ selectedInfo?.timingReason }}
                                    </p>

                                </div>

                                <div
                                    class="rounded-2xl border border-primary/15 bg-linear-to-br from-primary/8 via-stone-50 to-white px-4 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.9),0_6px_16px_rgba(120,110,92,0.05)]"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                    >
                                        <span class="material-symbols-outlined mr-1 align-[-2px] text-[13px] normal-case"
                                            >check_circle</span
                                        >
                                        Täna tee
                                    </p>
                                    <p
                                        v-if="selectedTaskSummary"
                                        class="mt-1.5 text-[15px] leading-7 font-small text-foreground/90"
                                    >
                                        {{ selectedTaskSummary }}
                                    </p>
                                    <p
                                        v-else
                                        class="mt-1.5 text-[15px] leading-7 text-foreground/75"
                                    >
                                        Täna ei ole erisoovitusi.
                                    </p>
                                </div>

                                <div
                                    v-if="selectedNotIdealFor.length"
                                    class="rounded-2xl border border-rose-200/55 bg-rose-50/40 px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.12em] text-rose-700/80 uppercase"
                                    >
                                        <span class="material-symbols-outlined mr-1 align-[-2px] text-[13px] normal-case"
                                            >block</span
                                        >
                                        Ära tee täna
                                    </p>
                                    <p class="mt-1.5 text-[14px] leading-6 text-foreground/85">
                                        {{ selectedNotIdealFor.join(', ') }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl border border-stone-200/90 bg-white/85 px-4 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.9),0_4px_14px_rgba(120,110,92,0.04)]"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                    >
                                        <span class="material-symbols-outlined mr-1 align-[-2px] text-[13px] normal-case"
                                            >info</span
                                        >
                                        Tasub meeles pidada
                                    </p>
                                    <p
                                        v-if="selectedTipsSummary"
                                        class="mt-1.5 text-[14px] leading-6 text-foreground/80"
                                    >
                                        {{ selectedTipsSummary }}
                                    </p>
                                    <p
                                        v-else
                                        class="mt-1.5 text-[14px] leading-6 text-foreground/75"
                                    >
                                        Täna piisab rahulikust hooldusest ja jälgimisest.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            class="mx-auto w-full max-w-lg card p-2.5 sm:p-3 lg:order-1 lg:mx-0 lg:max-w-none"
                        >
                            <div class="mb-3 flex items-center justify-between lg:mb-4">
                                <button
                                    type="button"
                                    class="icon-btn"
                                    @click="prevMonth"
                                    aria-label="Eelmine kuu"
                                >
                                    <span class="material-symbols-outlined"
                                        >chevron_left</span
                                    >
                                </button>
                                <h2
                                    class="text-base font-bold capitalize sm:text-lg lg:text-xl"
                                >
                                    {{ monthTitle }}
                                </h2>
                                <button
                                    type="button"
                                    class="icon-btn"
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
                                    class="grid grid-cols-7 gap-x-1 gap-y-1.5 sm:gap-x-1.5 sm:gap-y-2 lg:gap-x-2.5 lg:gap-y-3"
                                >
                                    <div
                                        v-for="lbl in dayLabels"
                                        :key="'w-' + lbl"
                                        class="pb-0.5 text-center text-[10px] font-bold text-primary/60 sm:text-xs lg:pb-1 lg:text-[13px] lg:text-foreground/70"
                                    >
                                        {{ lbl }}
                                    </div>
                                    <button
                                        v-for="d in weekDays"
                                        :key="d.getTime()"
                                        type="button"
                                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl border border-border/60 bg-card px-1 py-1 text-left transition hover:bg-muted/45 sm:min-h-15 sm:px-1.5 lg:min-h-24 lg:rounded-2xl lg:gap-2 lg:px-2 lg:py-2"
                                        :class="[
                                            isSelectedDate(d)
                                                ? 'border-stone-300 bg-stone-100 ring-2 ring-stone-300/70'
                                                : '',
                                            !isInViewMonth(d)
                                                ? 'opacity-45'
                                                : '',
                                        ]"
                                        :aria-pressed="isSelectedDate(d)"
                                        @click="selectCalendarDate(d)"
                                    >
                                        <span
                                            class="shrink-0 text-[11px] leading-none font-semibold sm:text-xs"
                                            :class="
                                                isToday(d)
                                                    ? 'text-primary'
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
                                            class="shrink-0 text-primary"
                                        />
                                        <span
                                            class="hidden text-center text-[10px] leading-tight font-medium text-foreground/70 lg:block"
                                            :title="
                                                dayInfoForDate(d).phaseDisplay
                                            "
                                        >
                                            {{
                                                shortPhaseLabel(
                                                    dayInfoForDate(d).phaseDisplay,
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
                                    class="grid grid-cols-7 gap-x-1 gap-y-1.5 sm:gap-x-1.5 sm:gap-y-2 lg:gap-x-2.5 lg:gap-y-3"
                                >
                                    <div
                                        v-for="lbl in dayLabels"
                                        :key="lbl"
                                        class="pb-0.5 text-center text-[10px] font-bold text-primary/60 sm:text-xs lg:pb-1 lg:text-[13px] lg:text-foreground/70"
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
                                        class="flex min-h-14 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-border/60 bg-card px-1 py-1 text-left transition hover:bg-muted/45 sm:min-h-15 sm:px-1.5 lg:min-h-24 lg:rounded-2xl lg:gap-2 lg:px-2 lg:py-2"
                                        :class="
                                            selectedDay === day
                                                ? 'border-stone-300 bg-stone-100 ring-2 ring-stone-300/70'
                                                : ''
                                        "
                                        role="button"
                                        tabindex="0"
                                        :aria-pressed="selectedDay === day"
                                        @click="selectedDay = day"
                                        @keydown.enter="selectedDay = day"
                                    >
                                        <span
                                            class="shrink-0 text-[11px] leading-none font-semibold text-foreground sm:text-xs"
                                            >{{ day }}</span
                                        >
                                        <MoonPhaseIcon
                                            :lunation-t="dayInfo(day).lunationT"
                                            :phase-index="
                                                dayInfo(day).phaseIndex
                                            "
                                            :size="26"
                                            class="shrink-0 text-primary"
                                        />
                                        <span
                                            class="hidden text-center text-[10px] leading-tight font-medium text-foreground/70 lg:block"
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
                                class="mt-3 overflow-hidden rounded-[1.35rem] border border-border/70 bg-card/75 shadow-soft lg:hidden"
                            >
                                <div
                                    class="flex items-center gap-2 border-b border-border/50 px-2.5 py-2"
                                >
                                    <button
                                        type="button"
                                        class="icon-btn shrink-0"
                                        aria-label="Eelmine päev"
                                        @click="shiftFromDate(selectedDateObj, -1)"
                                    >
                                        <span class="material-symbols-outlined"
                                            >chevron_left</span
                                        >
                                    </button>
                                    <div class="min-w-0 flex-1 text-center">
                                        <p
                                            class="text-sm leading-tight font-semibold text-foreground"
                                        >
                                            {{ selectedDateLabel }}
                                        </p>
                                        <p
                                            v-if="isToday(selectedDateObj)"
                                            class="mt-0.5 text-xs font-medium text-primary"
                                        >
                                            Täna
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="icon-btn shrink-0"
                                        aria-label="Järgmine päev"
                                        @click="shiftFromDate(selectedDateObj, 1)"
                                    >
                                        <span class="material-symbols-outlined"
                                            >chevron_right</span
                                        >
                                    </button>
                                </div>

                                <div class="space-y-3 px-3 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <MoonPhaseIcon
                                            :lunation-t="selectedInfo.lunationT"
                                            :phase-index="selectedInfo.phaseIndex"
                                            :size="30"
                                            class="shrink-0 text-primary"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-base leading-tight font-semibold text-foreground"
                                            >
                                                <span class="font-bold">{{
                                                    selectedInfo.phaseDisplay
                                                }}</span>
                                                <span class="text-foreground/70"
                                                    >, {{ selectedInfo.biodynamicLabel }}</span
                                                >
                                            </p>
                                            <p
                                                class="mt-0.5 text-sm text-foreground/75"
                                            >
                                                {{ selectedInfo.moodHeadline }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-xl border border-primary/20 bg-primary/5 px-3 py-2.5"
                                    >
                                        <div class="mb-1.5 flex items-center justify-between gap-2">
                                            <p
                                                class="text-[11px] font-semibold tracking-[0.12em] text-primary/90 uppercase"
                                            >
                                                Külv ja istutus täna
                                            </p>
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                                :class="
                                                    selectedInfo?.timingConfidence === 'hea'
                                                        ? 'border-primary/30 bg-primary/10 text-primary'
                                                        : selectedInfo?.timingConfidence === 'mõõdukas'
                                                          ? 'border-amber-200/60 bg-amber-50/45 text-amber-700/80'
                                                          : 'border-rose-200/60 bg-rose-50/45 text-rose-700/75'
                                                "
                                            >
                                                {{ selectedTimingBadge }}
                                            </span>
                                        </div>
                                        <p class="text-sm leading-snug text-foreground/80">
                                            {{ selectedInfo?.timingReason }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="selectedTaskSummary"
                                        class="rounded-xl border border-primary/25 bg-primary/10 px-3 py-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)]"
                                    >
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.12em] text-primary uppercase"
                                        >
                                            <span class="material-symbols-outlined mr-1 align-[-2px] text-[13px] normal-case"
                                                >check_circle</span
                                            >
                                            Täna tee
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm leading-snug text-foreground/90"
                                        >
                                            {{ selectedTaskSummary }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="selectedNotIdealFor.length"
                                        class="rounded-xl border border-rose-200/55 bg-rose-50/40 px-3 py-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)]"
                                    >
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.12em] text-rose-700/80 uppercase"
                                        >
                                            <span class="material-symbols-outlined mr-1 align-[-2px] text-[13px] normal-case"
                                                >block</span
                                            >
                                            Ära tee täna
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm leading-snug text-foreground/85"
                                        >
                                            {{ selectedNotIdealFor.join(', ') }}
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-xl border border-stone-200/90 bg-white/85 px-3 py-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.85)]"
                                    >
                                        <p
                                            class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                        >
                                            <span class="material-symbols-outlined mr-1 align-[-2px] text-[13px] normal-case"
                                                >info</span
                                            >
                                            Tasub meeles pidada
                                        </p>
                                        <p
                                            class="mt-1.5 text-sm leading-relaxed text-foreground/80"
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
