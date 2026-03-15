<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getMoonInfo } from '@/lib/moon/moon';
import { moonAdvice } from '@/lib/moon/moonAdvice';
import { getZodiacInfo } from '@/lib/moon/zodiac';
import BottomNav from '@/pages/BottomNav.vue';

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
    tasksShort: advice.tasks.slice(0, 2).join(', '),
  };
}
</script>

<template>
  <Head title="Kuufaaside kalender" />
  <AppLayout>
    <div class="page-container py-6 pb-24">
      <DiaryHeader
        title="Kuufaaside kalender"
        title-class="text-lg font-semibold"
        header-class="pt-6"
        top-row-class="mb-3"
        bottom-row-class="mb-4"
      />

      <Link
        href="/calendar"
        class="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground text-sm mb-4"
      >
        <span class="material-symbols-outlined text-lg">arrow_back</span>
        Tagasi kalendrisse
      </Link>

      <p class="text-sm text-muted-foreground mb-4">
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
            class="min-h-24 md:min-h-28 flex flex-col items-center justify-start gap-0.5 rounded-xl py-2 px-1.5 border border-border/60 bg-card text-left"
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
      </section>
    </div>

    <BottomNav active="calendar" />
  </AppLayout>
</template>
