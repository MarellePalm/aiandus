<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { moonAdvice as getMoonAdvice } from '@/lib/moon/moonAdvice';
import { getMoonInfo } from '@/lib/moon/moon';
import { getZodiacInfo } from '@/lib/moon/zodiac';
import MoonPhaseIcon from './MoonPhaseIcon.vue';
import MoonZodiacBadge from './MoonZodiacBadge.vue';

const props = withDefaults(
  defineProps<{
    date?: Date;
  }>(),
  { date: undefined }
);

const dateRef = computed(() => props.date ?? new Date());
const moonInfo = computed(() => getMoonInfo(dateRef.value));
const moon = computed(() => getMoonAdvice(moonInfo.value));
const zodiac = computed(() => getZodiacInfo(dateRef.value));
</script>

<template>
  <!-- soovitan overflow-hidden, et parempoolne riba “lõikuks” ilusti kaardi raadiusega -->
  <div class="rhythm-card flex flex-col overflow-hidden">
    <div class="flex items-center gap-4 flex-1 min-h-0">
      <div class="rhythm-icon shrink-0">
        <MoonPhaseIcon
        :phase-index="moonInfo.phaseIndex"
        :illumination="moonInfo.illumination"
        :size="56"
        />
      </div>
      <h4 class="rhythm-title flex-1 min-w-0">{{ moon.title }}</h4>
      <div class="shrink-0 w-32 self-stretch">
        <MoonZodiacBadge :date="dateRef" />
      </div>
    </div>

    <div class="mt-3 space-y-1 text-sm text-muted-foreground text-left">
        <p>
          {{ zodiac.biodynamicDescription }}
        </p>
        <p v-if="moon.tasks.length">
          <strong class="font-semibold">Tööd:</strong>
          {{ moon.tasks.join(', ') }}
        </p>
        <p v-if="moon.textLong">
          <strong class="font-semibold">Märkused:</strong>
          {{ moon.textLong }}
        </p>
    </div>

    <Link
      href="/calendar/moon"
      class="btn-panel-link"
    >
      <span class="material-symbols-outlined text-lg">calendar_month</span>
      <span class="font-semibold text-sm">Vaata kuukalendrit</span>
      <span class="material-symbols-outlined text-lg ml-auto">chevron_right</span>
    </Link>
  </div>
</template>