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

const highlightedTasks = computed(() => moon.value.tasks.slice(0, 3));
const cautionText = computed(() => moon.value.avoid[0] ?? null);
</script>

<template>
    <div class="space-y-3.5">
        <div
            class="rounded-[1.6rem] border border-border/70 bg-linear-to-br from-stone-50 via-background to-stone-100/70 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.85),0_10px_30px_rgba(120,110,92,0.08)]"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex h-19 w-19 shrink-0 items-center justify-center rounded-2xl border border-border/70 bg-white/85 shadow-[inset_0_1px_0_rgba(255,255,255,0.9),0_8px_24px_rgba(120,110,92,0.10)]"
                >
                    <MoonPhaseIcon
                        :lunation-t="moonInfo.lunationT"
                        :phase-index="moonInfo.phaseIndex"
                        :size="54"
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
                        class="mt-1.5 text-lg leading-snug font-bold tracking-tight text-foreground sm:text-xl"
                    >
                        {{ moon.displayTitle }}
                    </h4>
                    <p class="mt-1 text-sm leading-snug text-foreground/80">
                        {{ moon.moodHeadline }}
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        {{ moon.leadParagraph }}
                    </p>
                </div>
            </div>

            <div
                v-if="highlightedTasks.length"
                class="mt-4 flex flex-wrap gap-2"
            >
                <span
                    v-for="task in highlightedTasks"
                    :key="task"
                    class="inline-flex items-center rounded-full border border-stone-200 bg-stone-100/75 px-3 py-1.5 text-xs font-medium text-foreground/85 shadow-sm"
                >
                    {{ task }}
                </span>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-2xl border border-border/70 bg-card/80 p-3.5">
                <div class="flex items-center gap-2">
                    <span
                        class="text-base leading-none text-muted-foreground opacity-80"
                        aria-hidden="true"
                        >{{ signSymbol }}</span
                    >
                    <p class="text-sm font-semibold text-foreground">
                        Kuu on {{ zodiac.moonSignInessive }}
                    </p>
                    <span
                        class="ml-auto inline-flex items-center rounded-full border border-border/70 bg-muted/35 px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                    >
                        {{ dayTypeLabel }}
                    </span>
                </div>

                <p
                    v-if="zodiac.notes?.length"
                    class="mt-2 text-xs leading-relaxed text-muted-foreground"
                >
                    {{ zodiac.notes[0] }}
                </p>
            </div>

            <div
                v-if="cautionText"
                class="rounded-xl border border-stone-200/90 bg-stone-100/70 p-3.5"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Tasub meeles pidada
                </p>
                <p class="mt-1.5 text-sm leading-relaxed text-foreground/85">
                    {{ cautionText }}
                </p>
            </div>
        </div>
    </div>
</template>
