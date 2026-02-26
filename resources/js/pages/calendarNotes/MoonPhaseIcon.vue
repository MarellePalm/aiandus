<script setup lang="ts">
import { computed } from 'vue';

/**
 * Kuu ikoon vastavalt kuuseisule: valgustatus (illumination) määrab täpselt,
 * kui suur osa kuust on valge; faas määrab vari vasakule (kasvav) või paremale (kahanev).
 */
const props = withDefaults(
  defineProps<{
    /** Faasi indeks 0..7 – kasvav 0–4 (vari vasakul), kahanev 5–7 (vari paremal) */
    phaseIndex?: number;
    /** Valgustatus 0..1 – täpne osa kuust, mis on valgustatud */
    illumination?: number;
    size?: number;
  }>(),
  { phaseIndex: 0, illumination: 0, size: 64 }
);

const r = props.size / 2 - 2;
const cx = props.size / 2;
const cy = props.size / 2;
const phase = Math.max(0, Math.min(7, props.phaseIndex ?? 0));
const illum = Math.max(0, Math.min(1, props.illumination ?? 0));

// Vari asukoht: kasvaval (0–4) vari vasakul, kahaneval (5–7) vari paremal.
// Kasutame illuminationit, et valgustatud osa oleks täpselt õige (nt 73%).
const shadowOffset = computed(() => {
  if (phase <= 4) {
    return -2 * r * illum;
  }
  return 2 * r * (1 - illum);
});
const maskId = computed(() => `moon-phase-mask-${phase}-${illum.toFixed(2)}-${props.size}`);
</script>

<template>
  <svg
    :width="size"
    :height="size"
    :viewBox="`0 0 ${size} ${size}`"
    class="moon-phase-icon text-primary"
    aria-hidden="true"
  >
    <defs>
      <mask :id="maskId">
        <rect width="100%" height="100%" fill="white" />
        <circle
          :cx="cx + shadowOffset"
          :cy="cy"
          :r="r"
          fill="black"
        />
      </mask>
    </defs>
    <circle
      :cx="cx"
      :cy="cy"
      :r="r"
      fill="currentColor"
      :mask="`url(#${maskId})`"
    />
  </svg>
</template>

<style scoped>
.moon-phase-icon {
  display: block;
}
</style>
