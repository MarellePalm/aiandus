<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { getMoonInfo } from '@/lib/moon/moon';
import { moonAdvice as getMoonAdvice } from '@/lib/moon/moonAdvice';
import { calendarMomentForZodiac, getZodiacInfo } from '@/lib/moon/zodiac';

import MoonPhaseIcon from './MoonPhaseIcon.vue';

const props = withDefaults(
  defineProps<{
    date?: Date;
  }>(),
  { date: undefined },
);

const dateRef = computed(() => props.date ?? new Date());
const moonInfo = computed(() => getMoonInfo(dateRef.value));
const moon = computed(() => getMoonAdvice(moonInfo.value));
const zodiac = computed(() => getZodiacInfo(calendarMomentForZodiac(dateRef.value)));
const dayTypeLabel = computed(
  () => zodiac.value.biodynamicDayLabel.charAt(0).toUpperCase() + zodiac.value.biodynamicDayLabel.slice(1),
);

const SIGN_SYMBOL: Record<string, string> = {
  Jäär: '♈︎',
  Sõnn: '♉︎',
  Kaksikud: '♊︎',
  Vähk: '♋︎',
  Lõvi: '♌︎',
  Neitsi: '♍︎',
  Kaalud: '♎︎',
  Skorpion: '♏︎',
  Ambur: '♐︎',
  Kaljukits: '♑︎',
  Veevalaja: '♒︎',
  Kalad: '♓︎',
};
const signSymbol = computed(() => SIGN_SYMBOL[zodiac.value.moonSign] ?? '☾');

</script>

<template>
  <div class="space-y-4">
    <div class="flex items-start gap-4">
      <div
        class="flex h-19 w-19 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-primary/25 via-primary/10 to-transparent ring-1 ring-primary/20 shadow-inner"
      >
        <MoonPhaseIcon
          :lunation-t="moonInfo.lunationT"
          :phase-index="moonInfo.phaseIndex"
          :size="52"
          class="text-primary drop-shadow-sm"
        />
      </div>
      <div class="min-w-0 flex-1 pt-0.5">
        <p class="text-[11px] font-semibold text-muted-foreground">
          Tänane kuufaasi nimi
        </p>
        <h4 class="mt-0.5 text-lg font-bold leading-snug tracking-tight text-foreground sm:text-xl">
          {{ moon.displayTitle }}
        </h4>
        <p class="mt-1 text-sm text-primary/90 leading-snug">
          {{ moon.moodHeadline }}
        </p>
      </div>
    </div>

    <div class="border-t border-border/60 pt-3">
      <div class="flex items-center gap-2">
        <span class="text-base leading-none opacity-80 text-muted-foreground" aria-hidden="true">{{ signSymbol }}</span>
        <p class="text-sm font-semibold text-foreground">
          Kuu on {{ zodiac.moonSignInessive }}
        </p>
        <span class="ml-auto inline-flex items-center rounded-full border border-border/70 bg-muted/35 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
          {{ dayTypeLabel }}
        </span>
      </div>
      <p class="mt-1.5 text-xs text-muted-foreground leading-relaxed">
        {{ zodiac.biodynamicDescription }}
      </p>
    </div>

    <Link
      href="/calendar/moon"
      class="group flex w-full items-center justify-center gap-2 rounded-xl border border-primary/25 bg-primary/8 py-3 text-sm font-semibold text-primary transition hover:bg-primary/12 hover:border-primary/35"
    >
      <span class="material-symbols-outlined text-[20px] transition group-hover:translate-x-0.5">
        calendar_month
      </span>
      <span>Kuufaaside kalender</span>
      <span class="material-symbols-outlined text-lg opacity-70">chevron_right</span>
    </Link>
  </div>
</template>
