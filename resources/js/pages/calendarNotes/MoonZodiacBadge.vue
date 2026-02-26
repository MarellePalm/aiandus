<script setup lang="ts">
import { computed } from 'vue';
import { getZodiacInfo } from '@/lib/moon/zodiac';

const props = withDefaults(
  defineProps<{
    date?: Date;
  }>(),
  { date: undefined }
);

const dateRef = computed(() => props.date ?? new Date());
const zodiac = computed(() => getZodiacInfo(dateRef.value));

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

const theme = computed(() => {
  // Need on “stitchi sarnased” vaikeklassid.
  // Kui sul on oma Nitro värvinimed, asenda need siin ära.
  switch (zodiac.value.biodynamicDayType) {
    case 'leaf':
      return {
        wrap: 'bg-emerald-200 dark:bg-emerald-900/40',
        icon: 'eco',
        iconWrap: 'bg-white dark:bg-emerald-900/50',
        iconColor: 'text-emerald-700 dark:text-emerald-300',
        divider: 'bg-emerald-400/30 dark:bg-emerald-600/30',
      };
    case 'fruit':
      return {
        wrap: 'bg-amber-200 dark:bg-amber-900/40',
        icon: 'nutrition',
        iconWrap: 'bg-white dark:bg-amber-900/50',
        iconColor: 'text-amber-700 dark:text-amber-300',
        divider: 'bg-amber-400/30 dark:bg-amber-600/30',
      };
    case 'root':
      return {
        wrap: 'bg-orange-200 dark:bg-orange-900/40',
        icon: 'grass',
        iconWrap: 'bg-white dark:bg-orange-900/50',
        iconColor: 'text-orange-700 dark:text-orange-300',
        divider: 'bg-orange-400/30 dark:bg-orange-600/30',
      };
    case 'flower':
    default:
      return {
        wrap: 'bg-yellow-200 dark:bg-yellow-900/35',
        icon: 'local_florist',
        iconWrap: 'bg-white dark:bg-yellow-900/50',
        iconColor: 'text-yellow-700 dark:text-yellow-300',
        divider: 'bg-yellow-400/30 dark:bg-yellow-600/30',
      };
  }
});
</script>

<template>
  <!-- Root on “stitch badge” -->
  <div class="flex items-center px-2 py-3 h-full" :class="theme.wrap">
    <div class="flex items-center justify-between w-full">
      <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm" :class="theme.iconWrap">
        <span class="material-symbols-outlined text-xl" :class="theme.iconColor">{{ theme.icon }}</span>
      </div>

      <div class="h-8 w-px mx-2" :class="theme.divider"></div>

      <div class="flex flex-col items-center text-center min-w-0">
        <span class="text-sky-600 dark:text-sky-400 text-lg leading-none">{{ signSymbol }}</span>
        <span class="text-[10px] font-bold text-gray-800 dark:text-gray-200 uppercase tracking-tight truncate">
          {{ zodiac.moonSign }}
        </span>
        <span class="text-[10px] text-gray-600 dark:text-gray-400 truncate">
          {{ zodiac.biodynamicDayLabel }}
        </span>
      </div>
    </div>
  </div>
</template>