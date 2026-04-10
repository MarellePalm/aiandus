<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CalendarSwitchTabs from '@/components/calendar/CalendarSwitchTabs.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getMoonInfo } from '@/lib/moon/moon';
import {
  moonAdvice,
} from '@/lib/moon/moonAdvice';
import { calendarMomentForZodiac, getZodiacInfo } from '@/lib/moon/zodiac';
import BottomNav from '@/pages/BottomNav.vue';

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

function shiftFromDate(d: Date, delta: number) {
  selectCalendarDate(addDays(d, delta));
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
    biodynamicCrops: zodiac.crops,
    biodynamicNotes: zodiac.notes,
    tasks: advice.tasks,
    avoid: advice.avoid,
    moodHeadline: advice.moodHeadline,
    leadParagraph: advice.leadParagraph,
    moonSignInessive: zodiac.moonSignInessive,
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
      textParagraphs: paragraphsFromTextLong(info.leadParagraph),
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
  <Head title="Kuukalender" />
  <AppLayout>
    <div class="page page-with-bottomnav">
      <div class="bg-background text-foreground font-display min-h-screen antialiased">
        <div class="bg-background border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title="Kuukalender"
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
        <section class="card p-2 sm:p-3 md:p-3.5 max-w-lg mx-auto w-full">
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
                class="rounded-xl border border-border bg-card/60 backdrop-blur-md shadow-soft w-full min-h-[180px] overflow-hidden flex flex-col transition-shadow duration-300"
                :class="
                  row.off === 0
                    ? 'ring-2 ring-primary/30 border-primary/35 shadow-[0_12px_40px_-18px_rgba(34,197,94,0.22)] dark:shadow-[0_12px_40px_-18px_rgba(34,197,94,0.12)]'
                    : ''
                "
                aria-live="polite"
              >
                <div class="flex items-center gap-1 sm:gap-2 px-2 py-2 border-b border-border/50">
                  <button
                    type="button"
                    class="icon-btn shrink-0"
                    aria-label="Eelmine päev"
                    @click="shiftFromDate(row.d, -1)"
                  >
                    <span class="material-symbols-outlined">chevron_left</span>
                  </button>
                  <div class="min-w-0 flex-1 px-1 text-center">
                    <p class="text-sm font-semibold text-foreground leading-tight">
                      {{ formatDateLong(row.d) }}
                    </p>
                    <p
                      v-if="isToday(row.d)"
                      class="mt-0.5 text-xs font-medium text-primary"
                    >
                      Täna
                    </p>
                  </div>
                  <button
                    type="button"
                    class="icon-btn shrink-0"
                    aria-label="Järgmine päev"
                    @click="shiftFromDate(row.d, 1)"
                  >
                    <span class="material-symbols-outlined">chevron_right</span>
                  </button>
                </div>
                <div class="px-2.5 sm:px-3 pb-3 sm:pb-4 pt-3.5 sm:pt-4 w-full min-w-0 space-y-3.5">
                  <div class="space-y-2.5">
                    <div class="flex items-center gap-2.5">
                      <MoonPhaseIcon
                        :lunation-t="row.info.lunationT"
                        :phase-index="row.info.phaseIndex"
                        :size="30"
                        class="shrink-0 text-primary"
                      />
                      <p class="min-w-0 flex-1 text-base font-semibold text-foreground leading-tight">
                        {{ row.info.phaseDisplay }}
                      </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-0.5">
                      <span class="inline-flex rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
                        {{ row.info.moodHeadline.replace(/\.$/, '') }}
                      </span>
                      <span class="inline-flex rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
                        Kuu on {{ row.info.moonSignInessive }}
                      </span>
                      <span class="inline-flex rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
                        {{ row.info.biodynamicLabel }}
                      </span>
                    </div>
                    <div v-if="row.info.biodynamicCrops.length" class="pt-2 border-t border-border/50">
                      <div class="flex flex-wrap gap-1.5">
                        <span
                          v-for="crop in row.info.biodynamicCrops"
                          :key="crop"
                          class="inline-flex rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                        >
                          {{ crop }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Aiatööd -->
                  <div class="pt-1.5 border-t border-border/50">
                    <div v-if="bestTaskOfDay(row.info.tasks)" class="mb-3 rounded-lg border border-border/60 bg-muted/25 px-3 py-2">
                      <p class="text-xs font-semibold text-foreground/70">Parim tegevus täna</p>
                      <p class="text-sm font-semibold text-foreground">
                        {{ bestTaskOfDay(row.info.tasks) }}
                      </p>
                    </div>
                    <p class="mb-2 text-sm font-semibold text-foreground">
                      Täna tee
                    </p>
                    <ul v-if="row.info.tasks.length" class="w-full space-y-2 text-sm text-foreground">
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
                        <span class="min-w-0 flex-1 leading-snug pt-0.5 text-foreground">{{ task }}</span>
                      </li>
                    </ul>
                    <p v-else class="text-sm text-foreground">Täna ei ole erisoovitusi.</p>
                  </div>

                  <div v-if="row.info.avoid.length" class="border-t border-border/50 pt-3">
                    <p class="mb-2 text-sm font-semibold text-foreground">
                      Soovitused
                    </p>
                    <ul class="w-full space-y-1.5 text-sm text-foreground">
                      <li
                        v-for="item in row.info.avoid"
                        :key="item"
                        class="flex gap-2.5 items-start"
                      >
                        <span
                          class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
                          aria-hidden="true"
                        >
                          –
                        </span>
                        <span class="min-w-0 flex-1 leading-snug text-foreground">{{ item }}</span>
                      </li>
                    </ul>
                  </div>
                  <div v-if="row.info.biodynamicNotes.length" class="border-t border-border/50 pt-3">
                    <p class="mb-2 text-sm font-semibold text-foreground">
                      Märkused
                    </p>
                    <ul class="space-y-1.5 text-sm text-foreground">
                      <li
                        v-for="note in row.info.biodynamicNotes"
                        :key="note"
                        class="flex items-start gap-2"
                      >

                        <span class="min-w-0 flex-1 leading-snug text-foreground">{{ note }}</span>
                      </li>
                    </ul>
                  </div>
                  <div v-if="row.textParagraphs.length" class="border-t border-border/50 pt-3">
                    <p class="text-sm text-foreground leading-relaxed">
                      {{ row.textParagraphs[0] }}
                    </p>
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
