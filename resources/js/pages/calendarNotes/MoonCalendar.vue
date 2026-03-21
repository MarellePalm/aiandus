<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';

import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getMoonInfo } from '@/lib/moon/moon';
import {
  MOON_TRADITION_SCIENCE_NOTE_ET,
  moonAdvice,
} from '@/lib/moon/moonAdvice';
import { folkTimingForAgeDays } from '@/lib/moon/folkTiming';
import { MOON_CARD_DISCLAIMER_ET, MOON_FOOTER_INFO_ET, MOON_INTRO_HOME_ET } from '@/lib/moon/moonCopy';
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
    moodHeadline: advice.moodHeadline,
    leadParagraph: advice.leadParagraph,
    textLong: advice.textLong,
    astronomyShort: advice.astronomyShort,
    signNarrative: zodiac.signNarrative,
    moonSignInessive: zodiac.moonSignInessive,
    homeTasks: advice.homeTasks ?? [],
    traditionKeyword: advice.traditionKeyword,
    tasksShort: advice.tasks.slice(0, 2).join(', '),
    folkTiming: folkTimingForAgeDays(moon.ageDays),
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
  const d = new Date();
  const isSameMonth =
    d.getFullYear() === viewDate.value.getFullYear() && d.getMonth() === viewDate.value.getMonth();
  if (!isSameMonth) {
    selectedDay.value = null;
    return;
  }

  const todayDay = d.getDate();
  selectedDay.value = todayDay >= 1 && todayDay <= daysInMonth.value ? todayDay : null;
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
                class="rounded-xl border border-border bg-card/60 backdrop-blur-md shadow-soft w-full min-h-[180px] overflow-hidden flex flex-col"
                :class="row.off === 0 ? 'ring-2 ring-primary/25 border-primary/30' : ''"
                aria-live="polite"
              >
                <!-- Päis: ikoon + kuupäev (kitsas rida) -->
                <div class="flex items-center gap-3 px-3 sm:px-4 pt-3 sm:pt-4 pb-3 border-b border-border/50">
                  <div class="shrink-0">
                    <MoonPhaseIcon
                      :lunation-t="row.info.lunationT"
                      :phase-index="row.info.phaseIndex"
                      :size="44"
                      class="text-primary"
                    />
                  </div>
                  <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-foreground leading-tight text-base">
                      {{ formatDateLong(row.d) }}
                      <span
                        v-if="isToday(row.d)"
                        class="ml-1.5 text-xs font-semibold text-primary align-middle"
                      >
                        Täna
                      </span>
                    </h3>
                  </div>
                </div>

                <div class="px-3 sm:px-4 pb-3 sm:pb-4 pt-3 w-full min-w-0 space-y-3">
                  <div class="rounded-xl bg-primary/6 border border-primary/15 px-3 py-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-primary/80">
                      Tänane kuufaasi nimi
                    </p>
                    <p class="text-base font-bold text-foreground mt-1">
                      {{ row.info.phaseDisplay }}
                    </p>
                    <p class="text-sm font-medium text-primary mt-1.5">
                      {{ row.info.moodHeadline }}
                    </p>
                    <p class="text-sm text-foreground/85 leading-relaxed mt-2">
                      {{ row.info.leadParagraph }}
                    </p>
                  </div>

                  <div class="rounded-xl bg-muted/40 border border-border/60 px-3 py-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                      Tänane kuumärk
                    </p>
                    <p class="text-sm font-semibold text-foreground mt-1">
                      Kuu on {{ row.info.moonSignInessive }}
                    </p>
                    <p class="text-[10px] text-muted-foreground mt-0.5">
                      {{ row.info.biodynamicLabel }}
                    </p>
                  </div>

                  <div
                    v-if="row.info.signNarrative"
                    class="rounded-xl border border-border/50 bg-muted/20 px-3 py-2.5"
                  >
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">
                      Kui Kuu on selles märgis
                    </p>
                    <p class="text-sm text-foreground/85 leading-relaxed">
                      {{ row.info.signNarrative }}
                    </p>
                  </div>

                  <p
                    v-if="row.info.astronomyShort"
                    class="text-xs text-foreground/60 leading-relaxed"
                  >
                    <span class="font-medium text-foreground/75">Lisa: </span>{{ row.info.astronomyShort }}
                  </p>

                  <!-- Kuufaasi soovitused (pärimus tekst) -->
                  <div
                    v-if="row.textParagraphs.length"
                    class="rounded-xl border border-border/50 bg-card px-3 py-3"
                  >
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-2.5">
                      Kuufaasi soovitused
                    </p>
                    <div class="space-y-2.5">
                      <p
                        v-for="(block, idx) in row.textParagraphs"
                        :key="idx"
                        class="text-sm text-foreground/80 leading-relaxed border-l-2 border-primary/25 pl-3 -ml-px"
                      >
                        {{ block }}
                      </p>
                    </div>
                  </div>

                  <!-- Kodused tööd (pärimus) -->
                  <div
                    v-if="row.info.homeTasks?.length"
                    class="rounded-xl border border-border/60 bg-card/80 px-3 py-3"
                  >
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-2.5">
                      Kodused tööd
                    </p>
                    <ul class="w-full space-y-1.5 text-sm text-foreground/85">
                      <li
                        v-for="ht in row.info.homeTasks"
                        :key="ht"
                        class="flex gap-2 items-start"
                      >
                        <span class="text-muted-foreground shrink-0" aria-hidden="true">–</span>
                        <span class="min-w-0 flex-1 leading-snug">{{ ht }}</span>
                      </li>
                    </ul>
                  </div>

                  <!-- Aiatööd -->
                  <div v-if="row.info.tasks.length" class="rounded-xl border border-border/60 bg-muted/25 px-3 py-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-2.5">
                      Aiatoimetused
                    </p>
                    <ul class="w-full space-y-2 text-sm text-foreground/90">
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
                  </div>

                  <!-- Pehme / kõva / mäda / kuiv — teisesjärguline, kaardi lõpus -->
                  <div
                    v-if="row.info.folkTiming?.length"
                    class="rounded-xl border border-dashed border-border/55 bg-muted/10 px-3 py-3 mt-1"
                  >
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-2.5">
                      Pehme, kõva, mäda, kuiv
                    </p>
                    <div class="space-y-2.5">
                      <div
                        v-for="ft in row.info.folkTiming"
                        :key="ft.id"
                        class="border-l-2 border-muted-foreground/25 pl-3 -ml-px"
                      >
                        <p class="text-sm font-semibold text-foreground/90 leading-snug">{{ ft.title }}</p>
                        <p class="text-sm text-foreground/75 leading-relaxed mt-1">{{ ft.body }}</p>
                      </div>
                    </div>
                  </div>

                  <p
                    class="text-[11px] text-foreground/60 leading-relaxed border-t border-border/50 pt-3 mt-1"
                  >
                    {{ MOON_CARD_DISCLAIMER_ET }}
                  </p>
                </div>
              </section>
            </div>
          </div>
        </section>

        <p
          class="max-w-lg mx-auto w-full text-xs text-foreground/65 leading-relaxed px-1 sm:px-0"
        >
          {{ MOON_FOOTER_INFO_ET }}
        </p>
        <p
          class="max-w-lg mx-auto w-full text-[11px] text-foreground/55 leading-relaxed px-1 sm:px-0"
        >
          {{ MOON_TRADITION_SCIENCE_NOTE_ET }}
        </p>
          </main>
        </div>

        <BottomNav active="calendar" />
      </div>
    </div>
  </AppLayout>
</template>
