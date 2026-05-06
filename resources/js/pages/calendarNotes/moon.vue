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

</script>

<template>
    <div class="space-y-2.5">
        <div
            class="rounded-[1.35rem] border border-border/70 bg-linear-to-br from-stone-50 via-background to-stone-100/70 p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.85),0_8px_20px_rgba(120,110,92,0.05)]"
        >
            <div class="flex items-start gap-3">
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
                </div>
            </div>

        </div>
    </div>
</template>
