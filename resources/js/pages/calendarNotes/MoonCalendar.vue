<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getMoonInfo } from '@/lib/moon/moon';
import { moonAdvice } from '@/lib/moon/moonAdvice';
import { getZodiacInfo } from '@/lib/moon/zodiac';
import BottomNav from '@/pages/BottomNav.vue';
import CalendarSwitchTabs from '@/components/calendar/CalendarSwitchTabs.vue';

import MoonPhaseIcon from './MoonPhaseIcon.vue';

const today = new Date();
const viewDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));

const dayLabels = ['E', 'T', 'K', 'N', 'R', 'L', 'P'];

const monthTitle = computed(() =>
  viewDate.value.toLocaleDateString('et-EE', { month: 'long', year: 'numeric' }),
);

const daysInMonth = computed(() => {
  const d = viewDate.value;
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
});

// Tühjad lahtrid enne kuu 1. päeva: nädal algab esmaspäevast (E)
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

function moonInfoForDay(day: number) {
  return getMoonInfo(dateForDay(day));
}

function dayInfo(day: number) {
  const d = dateForDay(day);
  const moon = moonInfoForDay(day);
  const advice = moonAdvice(moon);
  const zodiac = getZodiacInfo(d);
  return {
    phase: moon.phase,
    phaseIndex: moon.phaseIndex,
    illumination: moon.illumination,
    biodynamicLabel: zodiac.biodynamicDayLabel,
    moonSign: zodiac.moonSign,
    tasks: advice.tasks,
    textLong: advice.textLong,
    tasksShort: advice.tasks.slice(0, 2).join(', '),
  };
}

function formatSelectedDate(day: number) {
  const d = dateForDay(day);
  return d.toLocaleDateString('et-EE', { day: 'numeric', month: 'long', year: 'numeric' });
}

const selectedDay = ref<number | null>(null);
const selectedInfo = computed(() => (selectedDay.value == null ? null : dayInfo(selectedDay.value)));

function syncSelectedDayWithMonth() {
  const d = new Date();
  const isSameMonth = d.getFullYear() === viewDate.value.getFullYear() && d.getMonth() === viewDate.value.getMonth();
  if (!isSameMonth) {
    selectedDay.value = null;
    return;
  }

  const todayDay = d.getDate();
  // Kui täna on vaadatava kuu sees, vali see automaatselt.
  selectedDay.value = todayDay >= 1 && todayDay <= daysInMonth.value ? todayDay : null;
}

watch(viewDate, () => syncSelectedDayWithMonth(), { immediate: true });
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
        <p class="text-sm text-muted-foreground">
          Kuufaas ja biodünaamiline päevatüüp (lehepäev, viljapäev, juurepäev, õiepäev) aitavad planeerida aiandustöid.
        </p>

        <section class="card p-4 md:p-6">
          <div class="flex items-center justify-between mb-4">
            <button type="button" class="icon-btn" @click="prevMonth" aria-label="Eelmine kuu">
              <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <h2 class="text-lg font-bold capitalize">{{ monthTitle }}</h2>
            <button type="button" class="icon-btn" @click="nextMonth" aria-label="Järgmine kuu">
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>

        <div class="grid grid-cols-7 gap-x-2 gap-y-4">
          <div
            v-for="lbl in dayLabels"
            :key="lbl"
            class="text-center text-xs font-bold text-primary/60 pb-1"
          >
            {{ lbl }}
          </div>

          <div v-for="n in startOffset" :key="'sp-' + n" class="min-h-24 md:min-h-28" />

          <div
            v-for="day in daysInMonth"
            :key="day"
            class="min-h-24 md:min-h-28 flex flex-col items-center justify-start gap-0.5 rounded-xl py-2 px-1.5 border border-border/60 bg-card text-left cursor-pointer
                   transition hover:bg-muted/45"
            :class="selectedDay === day ? 'ring-2 ring-primary/40 bg-primary/5 border-primary/30' : ''"
            role="button"
            tabindex="0"
            :aria-pressed="selectedDay === day"
            @click="selectedDay = day"
            @keydown.enter="selectedDay = day"
          >
            <span class="text-sm font-semibold text-foreground shrink-0">{{ day }}</span>
            <MoonPhaseIcon
              :phase-index="dayInfo(day).phaseIndex"
              :illumination="dayInfo(day).illumination"
              :size="32"
              class="text-primary shrink-0"
            />
            <span class="text-[11px] font-medium text-foreground/90 leading-tight text-center line-clamp-2">
              {{ dayInfo(day).phase }}
            </span>
            <span class="text-[10px] text-primary/80 font-medium leading-tight text-center">
              {{ dayInfo(day).biodynamicLabel }}
            </span>
            <span
              v-if="dayInfo(day).tasksShort"
              class="text-[9px] text-muted-foreground leading-tight text-center line-clamp-2 mt-0.5"
              :title="dayInfo(day).tasks.join(', ')"
            >
              {{ dayInfo(day).tasksShort }}
            </span>
          </div>
        </div>

        <section
          v-if="selectedDay != null"
          class="mt-5 rounded-2xl border border-border bg-card/60 backdrop-blur-md p-4 shadow-soft"
          aria-live="polite"
        >
          <div class="flex items-start gap-4">
            <div class="shrink-0">
              <MoonPhaseIcon
                :phase-index="selectedInfo!.phaseIndex"
                :illumination="selectedInfo!.illumination"
                :size="56"
                class="text-primary"
              />
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h3 class="font-bold text-foreground">
                    {{ formatSelectedDate(selectedDay) }}
                  </h3>
                  <p class="text-sm text-foreground/80 mt-1">
                    {{ selectedInfo!.phase }} • {{ selectedInfo!.biodynamicLabel }}
                  </p>
                  <p v-if="selectedInfo!.textLong" class="text-xs text-foreground/70 mt-2 leading-relaxed">
                    {{ selectedInfo!.textLong }}
                  </p>
                </div>

                <button
                  type="button"
                  class="icon-btn size-9"
                  aria-label="Sulge info"
                  @click="selectedDay = null"
                >
                  <span class="material-symbols-outlined text-lg">close</span>
                </button>
              </div>

              <div class="mt-3">
                <p class="text-[11px] uppercase tracking-widest text-muted-foreground font-bold mb-2">
                  Tööd sel päeval
                </p>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="task in selectedInfo!.tasks"
                    :key="task"
                    class="rounded-full border border-border bg-muted/25 px-3 py-1 text-xs text-foreground/80"
                  >
                    {{ task }}
                  </span>
                </div>
              </div>
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
