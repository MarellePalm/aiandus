<script setup lang="ts">
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
</script>

<template>
    <div class="flex items-start gap-3 py-1">
        <div
            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[1rem] border border-border/70 bg-white/90 shadow-[inset_0_1px_0_rgba(255,255,255,0.9),0_4px_14px_rgba(120,110,92,0.06)]"
        >
            <MoonPhaseIcon
                :lunation-t="moonInfo.lunationT"
                :phase-index="moonInfo.phaseIndex"
                :size="38"
                class="drop-shadow-sm"
            />
        </div>

        <div class="min-w-0 flex-1">
            <p
                class="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
            >
                Täna
            </p>
            <h4
                class="mt-0.5 text-base leading-snug font-medium tracking-tight text-foreground"
            >
                <span class="font-bold">{{ moon.displayTitle }}</span>
                <span class="text-foreground/70"> - {{ dayTypeLabel }}</span>
            </h4>
            <p class="mt-0.5 text-sm leading-snug text-foreground/80">
                {{ moon.moodHeadline }}
            </p>
            <div class="mt-1 flex flex-wrap gap-1.5">
                <span
                    v-for="task in shortTaskLines"
                    :key="task"
                    class="inline-flex max-w-full items-center rounded-full bg-emerald-50/70 px-2 py-0.5 text-[11px] font-medium text-emerald-900/80 ring-1 ring-emerald-200/70 lg:text-xs"
                >
                    <span class="truncate">{{ task }}</span>
                </span>
                <span
                    class="inline-flex max-w-full items-center rounded-full bg-rose-50/70 px-2 py-0.5 text-[11px] font-medium text-rose-900/70 ring-1 ring-rose-200/70 lg:text-xs"
                >
                    <span class="truncate">{{ shortAvoidLine }}</span>
                </span>
            </div>
        </div>
        </div>
</template>
