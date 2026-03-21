<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { getMoonInfo } from '@/lib/moon/moon';
import { moonAdvice as getMoonAdvice } from '@/lib/moon/moonAdvice';
import { MOON_CARD_DISCLAIMER_ET } from '@/lib/moon/moonCopy';
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

const illumPct = computed(() => Math.round(moonInfo.value.illumination * 100));

const SIGN_SYMBOL: Record<string, string> = {
  Jäär: '♈',
  Sõnn: '♉',
  Kaksikud: '♊',
  Vähk: '♋',
  Lõvi: '♌',
  Neitsi: '♍',
  Kaalud: '♎',
  Skorpion: '♏',
  Ambur: '♐',
  Kaljukits: '♑',
  Veevalaja: '♒',
  Kalad: '♓',
};
const signSymbol = computed(() => SIGN_SYMBOL[zodiac.value.moonSign] ?? '☾');

const bioIcon = computed(() => {
  switch (zodiac.value.biodynamicDayType) {
    case 'leaf':
      return 'eco';
    case 'fruit':
      return 'nutrition';
    case 'root':
      return 'grass';
    default:
      return 'local_florist';
  }
});

const bioAccentClass = computed(() => {
  switch (zodiac.value.biodynamicDayType) {
    case 'leaf':
      return 'from-emerald-500/15 to-emerald-600/5 border-emerald-500/20 text-emerald-800 dark:text-emerald-200';
    case 'fruit':
      return 'from-amber-500/15 to-orange-500/5 border-amber-500/25 text-amber-900 dark:text-amber-100';
    case 'root':
      return 'from-stone-400/20 to-stone-600/10 border-stone-400/30 text-stone-800 dark:text-stone-200';
    default:
      return 'from-primary/15 to-primary/5 border-primary/20 text-foreground';
  }
});

const gardenLine = computed(() => moon.value.tasks.join(', '));
const homeLine = computed(() => moon.value.homeTasks.join(', '));
</script>

<template>
  <div
    class="relative overflow-hidden rounded-2xl border border-border/70 bg-card/95 shadow-sm"
  >
    <div
      class="pointer-events-none absolute -right-6 -top-10 h-36 w-36 rounded-full bg-primary/[0.07] blur-3xl"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute -bottom-8 -left-4 h-24 w-24 rounded-full bg-secondary/40 blur-2xl"
      aria-hidden="true"
    />

    <div class="relative p-4 sm:p-5">
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
          <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">
            Tänane kuufaasi nimi
          </p>
          <h4 class="mt-0.5 text-lg font-bold leading-snug tracking-tight text-foreground sm:text-xl">
            {{ moon.displayTitle }}
          </h4>
          <p class="mt-1 text-sm font-semibold text-primary leading-snug">
            {{ moon.moodHeadline }}
          </p>
          <p class="mt-2 text-sm leading-relaxed text-foreground/85">
            {{ moon.leadParagraph }}
          </p>
          <div class="mt-2">
            <span
              class="inline-flex items-center rounded-full bg-muted/50 px-2 py-0.5 text-[10px] tabular-nums text-muted-foreground"
            >
              {{ illumPct }}% valgustatud
            </span>
          </div>
        </div>
      </div>

      <div
        class="mt-4 flex items-center gap-3 rounded-xl border bg-linear-to-r px-3 py-2.5 sm:px-4"
        :class="bioAccentClass"
      >
        <span class="text-2xl leading-none opacity-90" aria-hidden="true">{{ signSymbol }}</span>
        <div class="min-w-0 flex-1">
          <p class="text-[10px] font-semibold uppercase tracking-wide opacity-80">
            Tänane kuumärk
          </p>
          <p class="text-sm font-semibold leading-tight">
            Kuu on {{ zodiac.moonSignInessive }}
          </p>
          <p class="text-xs opacity-90 mt-1 leading-relaxed">
            {{ zodiac.signNarrative }}
          </p>
        </div>
        <span
          class="material-symbols-outlined shrink-0 text-[22px] opacity-80"
          aria-hidden="true"
        >
          {{ bioIcon }}
        </span>
      </div>

      <div v-if="gardenLine" class="mt-3">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">
          Täna võiks aias teha
        </p>
        <p class="text-sm text-foreground/90 leading-relaxed">
          {{ gardenLine }}
        </p>
      </div>

      <div v-if="homeLine" class="mt-2">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">
          Kodused tööd
        </p>
        <p class="text-sm text-foreground/85 leading-relaxed">
          {{ homeLine }}
        </p>
      </div>

      <p class="mt-4 text-[11px] text-foreground/60 leading-relaxed border-t border-border/50 pt-3">
        {{ MOON_CARD_DISCLAIMER_ET }}
      </p>

      <Link
        href="/calendar/moon"
        class="group mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-primary/25 bg-primary/8 py-3 text-sm font-semibold text-primary transition hover:bg-primary/12 hover:border-primary/35"
      >
        <span class="material-symbols-outlined text-[20px] transition group-hover:translate-x-0.5">
          calendar_month
        </span>
        <span>Kuufaaside kalender</span>
        <span class="material-symbols-outlined text-lg opacity-70">chevron_right</span>
      </Link>
    </div>
  </div>
</template>
