<script setup lang="ts">
import { computed } from 'vue';
import { moonAdvice as getMoonAdvice } from '@/lib/moon/moonAdvice';
import { getMoonInfo } from '@/lib/moon/moon';
import { getZodiacInfo } from '@/lib/moon/zodiac';
import MoonZodiacBadge from './MoonZodiacBadge.vue'; // kohanda pathi kui vaja

const props = withDefaults(
  defineProps<{
    date?: Date;
  }>(),
  { date: undefined }
);

const dateRef = computed(() => props.date ?? new Date());
const moon = computed(() => getMoonAdvice(getMoonInfo(dateRef.value)));
const zodiac = computed(() => getZodiacInfo(dateRef.value));
</script>

<template>
  <!-- soovitan overflow-hidden, et parempoolne riba “lõikuks” ilusti kaardi raadiusega -->
  <div class="rhythm-card flex items-stretch gap-4 overflow-hidden">
    <div class="rhythm-icon">
      <span class="material-symbols-outlined rhythm-icon-symbol">{{ moon.icon }}</span>
    </div>

    <div class="flex-1">
      <div class="flex items-center gap-2">
        <h4 class="rhythm-title">{{ moon.title }}</h4>
        <span class="rhythm-badge">{{ moon.subtitle }}</span>
      </div>

      <p class="rhythm-body">{{ moon.text }}</p>
      <p class="mt-1 text-sm text-muted-foreground">{{ moon.textLong }}</p>

      <div class="mt-3 space-y-2 text-sm">
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground">
          <span><strong class="text-foreground">Päike:</strong> {{ zodiac.sunSign }}</span>
          <span><strong class="text-foreground">Kuu:</strong> {{ zodiac.moonSign }} ({{ zodiac.biodynamicDayLabel }})</span>
        </div>
        <p class="text-muted-foreground">{{ zodiac.biodynamicDescription }}</p>
      </div>
    </div>

    <!-- PAREM “pildi moodi” riba -->
    <!-- stitchi järgi on see pigem kitsas: w-28 (7rem). Kui tahad laiemat, pane w-32 vms -->
    <div class="shrink-0 w-28 self-stretch">
      <MoonZodiacBadge :date="dateRef" />
    </div>
  </div>
</template>