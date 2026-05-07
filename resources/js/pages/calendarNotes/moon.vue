<script setup lang="ts">
import { computed } from 'vue';

import { getMoonInfo, SYNODIC_MONTH } from '@/lib/moon/moon';
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
const zodiac = computed(() =>
    getZodiacInfo(calendarMomentForZodiac(dateRef.value)),
);
const dayTypeLabel = computed(
    () =>
        zodiac.value.biodynamicDayLabel.charAt(0).toUpperCase() +
        zodiac.value.biodynamicDayLabel.slice(1),
);
const shortTaskLines = computed(() => {
    const tasks = moon.value.tasks.slice(0, 2);
    return tasks.length ? tasks : ['Tee täna rahulik hooldusring peenardes.'];
});
const shortAvoidLine = computed(
    () => moon.value.avoid[0] ?? 'Väldi täna liigset kiirustamist aiatöödega.',
);

const illuminationPercent = computed(() =>
    Math.round(moonInfo.value.illumination * 100),
);
const cyclePercent = computed(() =>
    Math.min(100, Math.max(0, Math.round(moonInfo.value.lunationT * 100))),
);
const ageDaysLabel = computed(() => {
    const d = moonInfo.value.ageDays;
    const rounded = Math.round(d * 10) / 10;
    return `${rounded.toFixed(1).replace('.', ',')} / ${SYNODIC_MONTH.toFixed(1).replace('.', ',')} p`;
});

const isWaxing = computed(() => moonInfo.value.lunationT < 0.5);

// Tähekesed dekoratiivseks öötaustaks (juhuslikud, aga deterministlikud).
const stars = [
    { x: '8%', y: '18%', size: 2, delay: '0s', duration: '3.4s' },
    { x: '22%', y: '70%', size: 1.5, delay: '0.6s', duration: '2.8s' },
    { x: '38%', y: '12%', size: 2.4, delay: '1.2s', duration: '3.8s' },
    { x: '62%', y: '28%', size: 1.6, delay: '0.3s', duration: '3.1s' },
    { x: '78%', y: '64%', size: 2, delay: '1.6s', duration: '3.6s' },
    { x: '90%', y: '20%', size: 1.4, delay: '0.9s', duration: '2.6s' },
    { x: '14%', y: '88%', size: 1.8, delay: '2.2s', duration: '4s' },
    { x: '52%', y: '82%', size: 2.2, delay: '1.4s', duration: '3.3s' },
] as const;
</script>

<template>
    <!-- Sektsioon ise on juba kaart raamiga; see plokk täidab kogu sisealuse
         laiuse ja ulatab serva servani (mitte ei jäta valget äärt). -->
    <div class="moon-card overflow-hidden">
        <div class="moon-hero relative overflow-hidden">
            <div class="moon-hero-bg absolute inset-0" aria-hidden="true" />
            <span
                v-for="(star, starIndex) in stars"
                :key="`star-${starIndex}`"
                class="moon-star"
                aria-hidden="true"
                :style="{
                    left: star.x,
                    top: star.y,
                    width: `${star.size}px`,
                    height: `${star.size}px`,
                    animationDelay: star.delay,
                    animationDuration: star.duration,
                }"
            />

            <div class="relative flex items-center gap-4 px-4 py-3 sm:px-5 sm:py-4">
                <div class="moon-disc-wrap relative shrink-0">
                    <span class="moon-disc-glow" aria-hidden="true" />
                    <div class="moon-disc-float relative">
                        <MoonPhaseIcon
                            :lunation-t="moonInfo.lunationT"
                            :phase-index="moonInfo.phaseIndex"
                            :size="68"
                        />
                    </div>
                </div>

                <div class="min-w-0 flex-1 text-white/95">
                    <p
                        class="text-[10px] font-semibold tracking-[0.22em] text-white/65 uppercase"
                    >
                        {{ dayTypeLabel }}
                    </p>
                    <h4
                        class="mt-0.5 text-base leading-snug font-bold tracking-tight sm:text-lg"
                    >
                        {{ moon.displayTitle }}
                    </h4>
                    <p class="mt-0.5 text-xs text-white/75 sm:text-sm">
                        {{ isWaxing ? 'Kasvav' : 'Kahanev' }} · valgustatud
                        <span class="font-semibold text-white tabular-nums">
                            {{ illuminationPercent }}%
                        </span>
                    </p>
                </div>
            </div>

            <div class="relative px-4 pb-3 sm:px-5 sm:pb-4">
                <div
                    class="flex items-center justify-between text-[10px] font-medium tracking-[0.16em] text-white/55 uppercase"
                >
                    <span>Kuutsükkel</span>
                    <span class="tabular-nums text-white/75">
                        {{ ageDaysLabel }}
                    </span>
                </div>
                <div
                    class="relative mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-white/15"
                >
                    <div
                        class="absolute inset-y-0 left-0 rounded-full bg-linear-to-r from-white/85 via-amber-100 to-white/85"
                        :style="{ width: `${cyclePercent}%` }"
                    />
                </div>
            </div>

            <!-- Üks tervik: alumine osa sama öö-tooniga, mitte hele „laht“ eraldi plokk. -->
            <div
                class="moon-footer relative border-t border-white/12 bg-linear-to-b from-white/[0.05] to-white/[0.11] px-4 py-3 backdrop-blur-[2px] sm:px-5 sm:py-3.5"
            >
                <p
                    class="text-sm leading-snug font-medium text-white drop-shadow-[0_1px_1px_rgba(0,0,0,0.45)]"
                >
                    {{ moon.moodHeadline }}
                </p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span
                        v-for="task in shortTaskLines"
                        :key="task"
                        class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-emerald-300/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-50 ring-1 ring-emerald-200/35 backdrop-blur-[1px] lg:text-xs"
                    >
                        <span
                            class="material-symbols-outlined text-[14px] text-emerald-200"
                            aria-hidden="true"
                        >
                            check_circle
                        </span>
                        <span class="truncate">{{ task }}</span>
                    </span>
                    <span
                        class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-rose-300/15 px-2.5 py-1 text-[11px] font-semibold text-rose-50 ring-1 ring-rose-200/35 backdrop-blur-[1px] lg:text-xs"
                    >
                        <span
                            class="material-symbols-outlined text-[14px] text-rose-200"
                            aria-hidden="true"
                        >
                            do_not_disturb_on
                        </span>
                        <span class="truncate">{{ shortAvoidLine }}</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.moon-hero-bg {
    background:
        radial-gradient(
            120% 120% at 85% 10%,
            rgba(255, 224, 153, 0.18) 0%,
            transparent 55%
        ),
        radial-gradient(
            90% 80% at 15% 90%,
            rgba(118, 150, 232, 0.22) 0%,
            transparent 55%
        ),
        linear-gradient(135deg, #1e2a4a 0%, #2c3868 45%, #3b3a6a 100%);
}

.moon-star {
    position: absolute;
    border-radius: 9999px;
    background: radial-gradient(
        circle,
        rgba(255, 255, 255, 0.95) 0%,
        rgba(255, 255, 255, 0.6) 60%,
        transparent 100%
    );
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.55);
    animation-name: moon-star-twinkle;
    animation-iteration-count: infinite;
    animation-timing-function: ease-in-out;
    will-change: opacity, transform;
}

@keyframes moon-star-twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.85);
    }
    50% {
        opacity: 1;
        transform: scale(1.15);
    }
}

.moon-disc-wrap {
    width: 68px;
    height: 68px;
}

.moon-disc-glow {
    position: absolute;
    inset: -10px;
    border-radius: 9999px;
    background: radial-gradient(
        circle,
        rgba(255, 240, 200, 0.55) 0%,
        rgba(255, 240, 200, 0.18) 45%,
        transparent 75%
    );
    filter: blur(6px);
    animation: moon-glow-pulse 5.5s ease-in-out infinite;
    pointer-events: none;
}
@keyframes moon-glow-pulse {
    0%,
    100% {
        opacity: 0.7;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.08);
    }
}

.moon-disc-float {
    animation: moon-float 6s ease-in-out infinite;
    will-change: transform;
}
@keyframes moon-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -3px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .moon-star,
    .moon-disc-glow,
    .moon-disc-float {
        animation: none !important;
    }
}
</style>
