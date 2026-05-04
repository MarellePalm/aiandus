<script setup lang="ts">
import { computed } from 'vue';

/** `moon.ts` faasi indeksid */
const PHASE_NEW_MOON = 0;
const PHASE_FULL_MOON = 4;

/**
 * Viitekalendri loogika: varjukülg roheline (primary), valgustatud osa valge.
 * Ring (stroke) koguaeg ümber; sisu samm-sammult lunationT + mask.
 */
const props = withDefaults(
    defineProps<{
        lunationT: number;
        phaseIndex: number;
        size?: number;
    }>(),
    { size: 64 },
);

const cx = props.size / 2;
const cy = props.size / 2;

const outerR = props.size / 2 - 2;
const ringStroke = Math.max(1.5, props.size * 0.055);
const innerR = Math.max(2, outerR - ringStroke / 2 - 0.5);

const t = computed(() => {
    const x = props.lunationT;
    if (Number.isNaN(x)) return 0;
    return Math.max(0, Math.min(1, x));
});

const isNewMoonPhase = computed(() => props.phaseIndex === PHASE_NEW_MOON);
const isFullMoonPhase = computed(() => props.phaseIndex === PHASE_FULL_MOON);

const shadowOffset = computed(() => {
    if (isFullMoonPhase.value) {
        return 4 * innerR;
    }
    return -2 * innerR * Math.cos(2 * Math.PI * t.value);
});

const maskId = computed(
    () => `moon-mask-${t.value.toFixed(4)}-p${props.phaseIndex}-${props.size}`,
);
const glowId = computed(
    () => `moon-glow-${t.value.toFixed(4)}-p${props.phaseIndex}-${props.size}`,
);
const discId = computed(
    () => `moon-disc-${t.value.toFixed(4)}-p${props.phaseIndex}-${props.size}`,
);
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
            <radialGradient :id="glowId" cx="50%" cy="50%" r="60%">
                <stop offset="0%" stop-color="#f7f2df" stop-opacity="0.28" />
                <stop offset="70%" stop-color="#f7f2df" stop-opacity="0.1" />
                <stop offset="100%" stop-color="#f7f2df" stop-opacity="0" />
            </radialGradient>
            <linearGradient :id="discId" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#fff9ed" />
                <stop offset="100%" stop-color="#efe7d4" />
            </linearGradient>
            <mask :id="maskId">
                <rect width="100%" height="100%" fill="white" />
                <circle
                    :cx="cx + shadowOffset"
                    :cy="cy"
                    :r="innerR"
                    fill="black"
                />
            </mask>
        </defs>

        <circle
            :cx="cx"
            :cy="cy"
            :r="outerR + ringStroke * 0.9"
            :fill="`url(#${glowId})`"
        />

        <!-- Uuskuu: ainult varjukülg -->
        <circle
            v-if="isNewMoonPhase"
            :cx="cx"
            :cy="cy"
            :r="innerR"
            fill="#647267"
        />

        <!-- Täiskuu: ainult valgus (kogu ketas valge) -->
        <circle
            v-else-if="isFullMoonPhase"
            :cx="cx"
            :cy="cy"
            :r="innerR"
            :fill="`url(#${discId})`"
        />

        <!-- Vahefaasid: pehme varjukülg + soe valgustatud osa -->
        <g v-else>
            <circle :cx="cx" :cy="cy" :r="innerR" fill="#6f7f72" />
            <circle
                :cx="cx"
                :cy="cy"
                :r="innerR"
                :fill="`url(#${discId})`"
                :mask="`url(#${maskId})`"
            />
        </g>

        <circle
            :cx="cx"
            :cy="cy"
            :r="outerR"
            fill="none"
            stroke="#8ba38a"
            :stroke-width="ringStroke"
        />
    </svg>
</template>

<style scoped>
.moon-phase-icon {
    display: block;
}
</style>
