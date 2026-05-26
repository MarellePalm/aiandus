<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: number[][];
        cellSizeCm: number;
    }>(),
    { cellSizeCm: 100 },
);

const emit = defineEmits<{
    'update:modelValue': [value: number[][]];
}>();

const isPainting = ref(false);
const paintMode = ref<1 | 0>(1);

function getActiveBounds(mask: number[][]) {
    let minR = Infinity;
    let maxR = -Infinity;
    let minC = Infinity;
    let maxC = -Infinity;

    mask.forEach((row, r) =>
        row.forEach((v, c) => {
            if (v === 1) {
                minR = Math.min(minR, r);
                maxR = Math.max(maxR, r);
                minC = Math.min(minC, c);
                maxC = Math.max(maxC, c);
            }
        }),
    );

    return { minR, maxR, minC, maxC, hasActive: Number.isFinite(minR) };
}

/** Kuvatav ala: aktiivne + 1 raku veeris ümber */
const displayBounds = computed(() => {
    const b = getActiveBounds(props.modelValue);
    if (!b.hasActive) {
        return { minR: 0, maxR: 4, minC: 0, maxC: 4 };
    }

    return {
        minR: Math.max(0, b.minR - 1),
        maxR: b.maxR + 1,
        minC: Math.max(0, b.minC - 1),
        maxC: b.maxC + 1,
    };
});

const displayRows = computed(() =>
    Array.from(
        { length: displayBounds.value.maxR - displayBounds.value.minR + 1 },
        (_, i) => i + displayBounds.value.minR,
    ),
);

const displayCols = computed(() =>
    Array.from(
        { length: displayBounds.value.maxC - displayBounds.value.minC + 1 },
        (_, i) => i + displayBounds.value.minC,
    ),
);

function getCellValue(r: number, c: number): number {
    return props.modelValue[r]?.[c] ?? 0;
}

function ensureSize(mask: number[][], r: number, c: number): number[][] {
    const rows = Math.max(mask.length, r + 1);
    const cols = Math.max(...mask.map((row) => row.length), c + 1, 1);

    return Array.from({ length: rows }, (_, y) =>
        Array.from({ length: cols }, (_, x) => mask[y]?.[x] ?? 0),
    );
}

function setCellValue(r: number, c: number, value: 1 | 0) {
    const next = ensureSize(
        props.modelValue.map((row) => [...row]),
        r,
        c,
    );
    next[r][c] = value;
    emit('update:modelValue', next);
}

function onCellMouseDown(r: number, c: number) {
    isPainting.value = true;
    paintMode.value = getCellValue(r, c) === 1 ? 0 : 1;
    setCellValue(r, c, paintMode.value);
}

function onCellMouseEnter(r: number, c: number) {
    if (!isPainting.value) {
        return;
    }

    setCellValue(r, c, paintMode.value);
}

function onMouseUp() {
    isPainting.value = false;
}

const hasActiveCells = computed(() =>
    props.modelValue.some((row) => row.includes(1)),
);

const dimensions = computed(() => {
    if (!hasActiveCells.value) {
        return null;
    }

    const b = getActiveBounds(props.modelValue);
    if (!b.hasActive) {
        return null;
    }

    const wCm = (b.maxC - b.minC + 1) * props.cellSizeCm;
    const hCm = (b.maxR - b.minR + 1) * props.cellSizeCm;
    const fmt = (cm: number) => (cm >= 100 ? `${cm / 100} m` : `${cm} cm`);

    return `${fmt(wCm)} × ${fmt(hCm)}`;
});

const cellLabel = computed(() =>
    props.cellSizeCm >= 100
        ? `${props.cellSizeCm / 100} m`
        : `${props.cellSizeCm} cm`,
);

onMounted(() => window.addEventListener('mouseup', onMouseUp));
onBeforeUnmount(() => window.removeEventListener('mouseup', onMouseUp));
</script>

<template>
    <div class="select-none" @mouseup="onMouseUp" @mouseleave="onMouseUp">
        <p class="mb-2 text-xs text-muted-foreground">
            Klõpsa või lohista ruutudel aia kuju märkimiseks. Iga ruut =
            {{ cellLabel }}.
        </p>
        <div
            class="inline-grid gap-px overflow-auto rounded-xl border border-border/50 bg-border/30 p-1"
            :style="{
                gridTemplateColumns: `repeat(${displayCols.length}, 2rem)`,
            }"
        >
            <template v-for="r in displayRows" :key="r">
                <div
                    v-for="c in displayCols"
                    :key="`${r}-${c}`"
                    class="h-8 w-8 cursor-pointer rounded-sm transition-colors"
                    :class="
                        getCellValue(r, c) === 1
                            ? 'bg-emerald-500/80 hover:bg-emerald-400'
                            : 'bg-muted/40 hover:bg-emerald-200/60'
                    "
                    @mousedown.prevent="onCellMouseDown(r, c)"
                    @mouseenter="onCellMouseEnter(r, c)"
                />
            </template>
        </div>
        <p class="mt-2 text-xs text-muted-foreground">
            <template v-if="dimensions">
                Aia suurus: <strong>{{ dimensions }}</strong>
            </template>
            <template v-else> Klõpsa ruutudel aia kuju määramiseks. </template>
        </p>
    </div>
</template>
