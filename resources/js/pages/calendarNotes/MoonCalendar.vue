<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getMoonInfo } from '@/lib/moon/moon';
import {
  moonAdvice,
} from '@/lib/moon/moonAdvice';
import { MOON_INTRO_HOME_ET } from '@/lib/moon/moonCopy';
import { calendarMomentForZodiac, getZodiacInfo } from '@/lib/moon/zodiac';
import BottomNav from '@/pages/BottomNav.vue';
import CalendarSwitchTabs from '@/components/calendar/CalendarSwitchTabs.vue';

import MoonPhaseIcon from './MoonPhaseIcon.vue';

const today = new Date();
const viewDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));

/** Vaikimisi ainult nädal + detail; täiskuu nupuga. */
const showFullMonth = ref(false);

const selectedDay = ref<number | null>(null);

const dayLabels = ['E', 'T', 'K', 'N', 'R', 'L', 'P'];

/** Naaberpäevade riba: päevi enne/pärast fookust (keritav). */
const neighborOffsets = [-3, -2, -1, 0, 1, 2, 3];

const monthTitle = computed(() =>
  viewDate.value.toLocaleDateString('et-EE', { month: 'long', year: 'numeric' }),
);

const daysInMonth = computed(() => {
  const d = viewDate.value;
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
});

const startOffset = computed(() => {
  const d = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);
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
  return new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), day);
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
  return d.getFullYear() === viewDate.value.getFullYear() && d.getMonth() === viewDate.value.getMonth();
}

function isSameCalendarDate(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
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

function shiftSelectedDay(delta: number) {
  if (!selectedDateObj.value) return;
  selectCalendarDate(addDays(selectedDateObj.value, delta));
}

function dayInfoForDate(d: Date) {
  const moon = getMoonInfo(d);
  const advice = moonAdvice(moon);
  const zodiac = getZodiacInfo(calendarMomentForZodiac(d));
  return {
    phase: moon.phase,
    /** Sõbralik nimi (nt „Kuu loomine“, „Noorkuu“). */
    phaseDisplay: advice.displayTitle,
    phaseIndex: moon.phaseIndex,
    illumination: moon.illumination,
    lunationT: moon.lunationT,
    biodynamicLabel: zodiac.biodynamicDayLabel,
    biodynamicDescription: zodiac.biodynamicDescription,
    moonSign: zodiac.moonSign,
    tasks: advice.tasks,
    avoid: advice.avoid,
    moodHeadline: advice.moodHeadline,
    leadParagraph: advice.leadParagraph,
    textLong: advice.textLong,
    astronomyShort: advice.astronomyShort,
    signNarrative: zodiac.signNarrative,
    moonSignInessive: zodiac.moonSignInessive,
    homeTasks: advice.homeTasks ?? [],
    traditionKeyword: advice.traditionKeyword,
    tasksShort: advice.tasks.slice(0, 2).join(', '),
  };
}

/** textLong sisaldab reavahetusi – eraldame loetavad lõigud. */
function paragraphsFromTextLong(text: string | undefined | null): string[] {
  if (!text?.trim()) return [];
  return text
    .split(/\n+/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function dayInfo(day: number) {
  return dayInfoForDate(dateForDay(day));
}

function formatDateLong(d: Date) {
  return d.toLocaleDateString('et-EE', { day: 'numeric', month: 'long', year: 'numeric' });
}

function isToday(d: Date): boolean {
  return isSameCalendarDate(d, new Date());
}

const selectedDateObj = computed(() =>
  selectedDay.value == null ? null : dateForDay(selectedDay.value),
);

type DayInfo = ReturnType<typeof dayInfoForDate>;

/** Naaberpäevad + kuuinfo (üks kord päeva kohta). */
const neighborDates = computed(() => {
  if (!selectedDateObj.value)
    return [] as { d: Date; off: number; info: DayInfo; textParagraphs: string[] }[];
  return neighborOffsets.map((off) => {
    const d = addDays(selectedDateObj.value!, off);
    const info = dayInfoForDate(d);
    return {
      off,
      d,
      info,
      textParagraphs: paragraphsFromTextLong(info.textLong),
    };
  });
});

const descriptionScrollRef = ref<HTMLElement | null>(null);
/** Vältib kerimise tuvastuses, kui kerime programmiliselt keskele. */
const descriptionScrollProgrammatic = ref(false);
let descriptionScrollDebounce: ReturnType<typeof setTimeout> | null = null;

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
    d.getFullYear() === viewDate.value.getFullYear() && d.getMonth() === viewDate.value.getMonth();
  selectedDay.value = isSameMonth ? d.getDate() : 1;
}

watch(viewDate, () => syncSelectedDayWithMonth(), { immediate: true });

function scrollDescriptionToCenter(behavior: ScrollBehavior = 'auto') {
  nextTick(() => {
    const el = descriptionScrollRef.value;
    if (!el) return;
    const w = el.clientWidth;
    if (w <= 0) return;
    const idx = neighborDates.value.findIndex((r) => r.off === 0);
    if (idx < 0) return;
    descriptionScrollProgrammatic.value = true;
    el.scrollTo({ left: idx * w, behavior });
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        descriptionScrollProgrammatic.value = false;
      });
    });
  });
}

/** Keritav päevakirjelduste rida (nagu dashboardi viimased taimed). */
function onDescriptionScrollEnd() {
  if (descriptionScrollProgrammatic.value) return;
  const el = descriptionScrollRef.value;
  if (!el || !neighborDates.value.length) return;
  const w = el.clientWidth;
  if (w <= 0) return;
  const idx = Math.round(el.scrollLeft / w);
  const row = neighborDates.value[idx];
  if (!row || !selectedDateObj.value) return;
  if (isSameCalendarDate(row.d, selectedDateObj.value)) return;
  selectCalendarDate(row.d);
}

function onDescriptionScrollDebounced() {
  if (descriptionScrollProgrammatic.value) return;
  if (descriptionScrollDebounce) clearTimeout(descriptionScrollDebounce);
  descriptionScrollDebounce = setTimeout(() => {
    descriptionScrollDebounce = null;
    onDescriptionScrollEnd();
  }, 120);
}

watch([selectedDateObj, showFullMonth], () => {
  scrollDescriptionToCenter('smooth');
});

onMounted(() => scrollDescriptionToCenter('auto'));
</script>

<template>
  <Head title="Kuufaaside kalender" />
  <AppLayout>
    <div class="page page-with-bottomnav">
      <div class="bg-background-light text-forest font-display min-h-screen antialiased">
        <div class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title="Kuufaaside kalender"
            header-class="pt-6"
            top-row-class="mb-3"
            bottom-row-class="mb-4"
          >
            <template #leading>
              <BackIconButton href="/calendar" aria-label="Tagasi kalendrisse" />
            </template>
            <div class="flex items-center justify-center pb-2">
              <CalendarSwitchTabs active="moon" />
            </div>
          </DiaryHeader>

          <main class="flex-1 px-6 py-4 md:px-8 space-y-6">
            <p
              class="max-w-lg mx-auto w-full text-sm leading-relaxed text-foreground/85 px-1 sm:px-0"
            >
              {{ MOON_INTRO_HOME_ET }}
            </p>

        <section class="card p-3 sm:p-4 md:p-5 max-w-lg mx-auto w-full">
          <div class="flex items-center justify-between mb-3">
            <button type="button" class="icon-btn" @click="prevMonth" aria-label="Eelmine kuu">
              <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <h2 class="text-base sm:text-lg font-bold capitalize">{{ monthTitle }}</h2>
            <button type="button" class="icon-btn" @click="nextMonth" aria-label="Järgmine kuu">
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>

          <!-- Nädalavaade -->
          <template v-if="!showFullMonth">
            <div class="grid grid-cols-7 gap-x-1 gap-y-1.5 sm:gap-x-1.5 sm:gap-y-2">
              <div
                v-for="lbl in dayLabels"
                :key="'w-' + lbl"
                class="text-center text-[10px] sm:text-xs font-bold text-primary/60 pb-0.5"
              >
                {{ lbl }}
              </div>
              <button
                v-for="d in weekDays"
                :key="d.getTime()"
                type="button"
                class="min-h-13 sm:min-h-14 md:min-h-15 flex flex-col items-center justify-center gap-0 rounded-lg py-0.5 px-0.5 sm:px-1 border border-border/60 bg-card text-left transition hover:bg-muted/45"
                :class="[
                  isSelectedDate(d) ? 'ring-2 ring-primary/40 bg-primary/5 border-primary/30' : '',
                  !isInViewMonth(d) ? 'opacity-45' : '',
                ]"
                :aria-pressed="isSelectedDate(d)"
                @click="selectCalendarDate(d)"
              >
                <span
                  class="text-[11px] sm:text-xs font-semibold shrink-0 leading-none"
                  :class="isToday(d) ? 'text-primary' : 'text-foreground'"
                >
                  {{ d.getDate() }}
                </span>
                <MoonPhaseIcon
                  :lunation-t="dayInfoForDate(d).lunationT"
                  :phase-index="dayInfoForDate(d).phaseIndex"
                  :size="28"
                  class="text-primary shrink-0"
                />
                <span
                  class="mt-0.5 w-full text-center text-[8px] sm:text-[9px] font-medium leading-[1.15] text-foreground/75 line-clamp-2 px-0.5"
                  :title="dayInfoForDate(d).phaseDisplay"
                >
                  {{ dayInfoForDate(d).phaseDisplay }}
                </span>
              </button>
            </div>

            <button
              type="button"
              class="mt-3 w-full rounded-xl border border-border bg-muted/30 py-2.5 text-sm font-semibold text-foreground hover:bg-muted/50 transition"
              @click="showFullMonth = true"
            >
              Vaata tervet kuud
            </button>
          </template>

          <!-- Terve kuu -->
          <template v-else>
            <div class="flex justify-end mb-2">
              <button
                type="button"
                class="text-sm font-semibold text-primary hover:underline"
                @click="showFullMonth = false"
              >
                ← Näita ainult nädalat
              </button>
            </div>

            <div class="grid grid-cols-7 gap-x-1 gap-y-1.5 sm:gap-x-1.5 sm:gap-y-2">
              <div
                v-for="lbl in dayLabels"
                :key="lbl"
                class="text-center text-[10px] sm:text-xs font-bold text-primary/60 pb-0.5"
              >
                {{ lbl }}
              </div>

              <div v-for="n in startOffset" :key="'sp-' + n" class="min-h-13 sm:min-h-14 md:min-h-15" />

              <div
                v-for="day in daysInMonth"
                :key="day"
                class="min-h-13 sm:min-h-14 md:min-h-15 flex flex-col items-center justify-center gap-0 rounded-lg py-0.5 px-0.5 sm:px-1 border border-border/60 bg-card text-left cursor-pointer transition hover:bg-muted/45"
                :class="selectedDay === day ? 'ring-2 ring-primary/40 bg-primary/5 border-primary/30' : ''"
                role="button"
                tabindex="0"
                :aria-pressed="selectedDay === day"
                @click="selectedDay = day"
                @keydown.enter="selectedDay = day"
              >
                <span class="text-[11px] sm:text-xs font-semibold text-foreground shrink-0 leading-none">{{ day }}</span>
                <MoonPhaseIcon
                  :lunation-t="dayInfo(day).lunationT"
                  :phase-index="dayInfo(day).phaseIndex"
                  :size="28"
                  class="text-primary shrink-0"
                />
                <span
                  class="mt-0.5 w-full text-center text-[8px] sm:text-[9px] font-medium leading-[1.15] text-foreground/75 line-clamp-2 px-0.5"
                  :title="dayInfo(day).phaseDisplay"
                >
                  {{ dayInfo(day).phaseDisplay }}
                </span>
              </div>
            </div>
          </template>

          <!-- Eelmine / valitud kuupäev / järgmine -->
          <div
            v-if="selectedDateObj"
            class="mt-3 flex items-center gap-1 sm:gap-2 rounded-xl border border-border/60 bg-muted/20 px-1 py-2 sm:px-2"
          >
            <button
              type="button"
              class="icon-btn shrink-0"
              aria-label="Eelmine päev"
              @click="shiftSelectedDay(-1)"
            >
              <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <div class="min-w-0 flex-1 px-1 text-center">
              <p class="text-sm font-semibold text-foreground leading-tight">
                {{ formatDateLong(selectedDateObj) }}
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
              @click="shiftSelectedDay(1)"
            >
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>

          <!-- Päevakirjeldused: keri vasakule/paremale (nagu dashboardi viimased taimed) -->
          <div
            v-if="selectedDateObj && neighborDates.length"
            ref="descriptionScrollRef"
            class="mt-3 sm:mt-4 flex overflow-x-auto gap-0 pb-2 no-scrollbar snap-x snap-mandatory scroll-smooth w-full"
            aria-label="Päevade kirjeldused, keri küljele"
            @scroll.passive="onDescriptionScrollDebounced"
          >
            <div
              v-for="row in neighborDates"
              :key="row.d.getTime()"
              class="w-full shrink-0 snap-center snap-always flex-[0_0_100%] box-border max-w-full min-w-0"
              :data-desc-offset="row.off"
            >
              <section
                class="rounded-xl border border-border bg-card/60 backdrop-blur-md shadow-soft w-full min-h-[180px] overflow-hidden flex flex-col transition-shadow duration-300"
                :class="
                  row.off === 0
                    ? 'ring-2 ring-primary/30 border-primary/35 shadow-[0_12px_40px_-18px_rgba(34,197,94,0.22)] dark:shadow-[0_12px_40px_-18px_rgba(34,197,94,0.12)]'
                    : ''
                "
                aria-live="polite"
              >
                <div class="px-3 sm:px-4 pb-3 sm:pb-4 pt-4 sm:pt-5 w-full min-w-0 space-y-3">
                  <div
                    class="relative overflow-hidden rounded-xl border border-primary/20 bg-linear-to-br from-primary/10 via-primary/4 to-sky-500/8 px-3 py-3 shadow-sm"
                  >
                    <span
                      class="pointer-events-none absolute -right-4 -top-4 text-primary/12 dark:text-primary/20"
                      aria-hidden="true"
                    >
                      <span class="material-symbols-outlined text-[4.5rem] leading-none">wb_twilight</span>
                    </span>
                    <div class="relative z-1 mb-2 flex items-center gap-3">
                      <MoonPhaseIcon
                        :lunation-t="row.info.lunationT"
                        :phase-index="row.info.phaseIndex"
                        :size="40"
                        class="shrink-0 text-primary"
                      />
                      <p class="min-w-0 flex-1 text-[18px] font-semibold uppercase tracking-wide text-primary leading-tight">
                        {{ row.info.phaseDisplay }}
                      </p>
                    </div>
                    <p class="relative z-1 text-sm font-medium text-primary mt-1.5">
                      {{ row.info.moodHeadline }}
                    </p>
                    <p class="relative z-1 text-sm text-foreground/85 leading-relaxed mt-2">
                      {{ row.info.leadParagraph }}
                    </p>
                  </div>

                  <div
                    class="rounded-xl border border-primary/20 bg-linear-to-br from-muted/50 via-muted/35 to-primary/8 px-3 py-2.5 shadow-sm"
                  >
                    <p
                      class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-primary/85 dark:text-primary/80"
                    >
                      <span
                        class="material-symbols-outlined text-[14px] text-primary/70 dark:text-primary/65"
                        aria-hidden="true"
                      >
                        signpost
                      </span>
                      Päevatüüp
                    </p>
                    <p class="text-sm font-semibold text-foreground mt-1">
                      Kuu on {{ row.info.moonSignInessive }}
                    </p>
                    <p class="text-[10px] text-muted-foreground mt-0.5">
                      {{ row.info.biodynamicLabel }}
                    </p>
                    <p class="text-sm text-foreground/80 leading-relaxed mt-2">
                      {{ row.info.biodynamicDescription }}
                    </p>
                  </div>

                  <!-- Aiatööd -->
                  <div
                    class="rounded-xl border border-emerald-500/25 bg-linear-to-b from-emerald-500/8 to-muted/20 px-3 py-3 shadow-sm"
                  >
                    <p
                      class="mb-2.5 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-800/90 dark:text-emerald-300/90"
                    >
                      <span class="material-symbols-outlined text-[14px] opacity-85" aria-hidden="true">grass</span>
                      Täna tee aias
                    </p>
                    <ul v-if="row.info.tasks.length" class="w-full space-y-2 text-sm text-foreground/90">
                      <li
                        v-for="task in row.info.tasks"
                        :key="task"
                        class="flex gap-2.5 items-start"
                      >
                        <span
                          class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[10px] font-bold text-primary"
                          aria-hidden="true"
                        >
                          ✓
                        </span>
                        <span class="min-w-0 flex-1 leading-snug pt-0.5">{{ task }}</span>
                      </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Täna ei ole erisoovitusi.</p>
                  </div>

                  <div
                    v-if="row.info.avoid.length"
                    class="relative overflow-hidden rounded-xl border border-amber-400/40 bg-linear-to-br from-amber-500/14 via-amber-500/5 to-orange-500/8 px-3 py-3 shadow-[0_10px_32px_-14px_rgba(217,119,6,0.38)] dark:border-amber-500/30 dark:shadow-[0_10px_32px_-14px_rgba(251,191,36,0.12)]"
                  >
                    <div
                      class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-amber-300/25 blur-2xl dark:bg-amber-400/15"
                      aria-hidden="true"
                    />
                    <div class="relative mb-2.5 flex items-center gap-2">
                      <span
                        class="material-symbols-outlined shrink-0 text-[22px] text-amber-700 dark:text-amber-300"
                        style="font-variation-settings: 'FILL' 1, 'wght' 400"
                        aria-hidden="true"
                      >
                        auto_awesome
                      </span>
                      <p class="text-sm font-semibold leading-snug text-amber-950 dark:text-amber-100">
                        Need võivad täna oodata
                      </p>
                    </div>
                    <ul class="relative w-full space-y-2.5 text-sm text-stone-800 dark:text-amber-50/95">
                      <li
                        v-for="item in row.info.avoid"
                        :key="item"
                        class="flex gap-2.5 items-start"
                      >
                        <span
                          class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-600/25 text-[11px] font-semibold text-amber-950 dark:bg-amber-400/25 dark:text-amber-100"
                          aria-hidden="true"
                        >
                          –
                        </span>
                        <span class="min-w-0 flex-1 leading-snug">{{ item }}</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </section>
            </div>
          </div>
        </section>

          </main>
        </div>

        <BottomNav active="calendar" />
      </div>
    </div>
  </AppLayout>
</template>
