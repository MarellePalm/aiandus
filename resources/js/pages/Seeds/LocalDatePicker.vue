<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
    }>(),
    {
        placeholder: 'pp.kk.aaaa',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const isOpen = ref(false);
const rootRef = ref<HTMLElement | null>(null);
const monthCursor = ref(new Date());

const weekDays = ['E', 'T', 'K', 'N', 'R', 'L', 'P'];
const monthNames = [
    'jaanuar',
    'veebruar',
    'märts',
    'aprill',
    'mai',
    'juuni',
    'juuli',
    'august',
    'september',
    'oktoober',
    'november',
    'detsember',
];

function parseIsoDate(value: string): Date | null {
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    if (!y || !m || !d) return null;
    return new Date(y, m - 1, d);
}

function toIsoDate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function toEtDate(date: Date): string {
    const d = String(date.getDate()).padStart(2, '0');
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const y = String(date.getFullYear());
    return `${d}.${m}.${y}`;
}

const selectedDate = computed(() => parseIsoDate(props.modelValue));

const displayValue = computed(() => {
    if (!selectedDate.value) return '';
    return toEtDate(selectedDate.value);
});

const currentMonthLabel = computed(() => {
    const d = monthCursor.value;
    return `${monthNames[d.getMonth()]} ${d.getFullYear()}`;
});

const days = computed(() => {
    const year = monthCursor.value.getFullYear();
    const month = monthCursor.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    const startOffset = (firstDay.getDay() + 6) % 7;
    const totalCells = Math.ceil((startOffset + lastDay.getDate()) / 7) * 7;

    const result: Array<{ date: Date; inMonth: boolean }> = [];
    for (let i = 0; i < totalCells; i++) {
        const dayNumber = i - startOffset + 1;
        const date = new Date(year, month, dayNumber);
        result.push({
            date,
            inMonth: date.getMonth() === month,
        });
    }
    return result;
});

function openCalendar() {
    if (selectedDate.value) {
        monthCursor.value = new Date(selectedDate.value.getFullYear(), selectedDate.value.getMonth(), 1);
    } else {
        const now = new Date();
        monthCursor.value = new Date(now.getFullYear(), now.getMonth(), 1);
    }
    isOpen.value = true;
}

function closeCalendar() {
    isOpen.value = false;
}

function previousMonth() {
    monthCursor.value = new Date(monthCursor.value.getFullYear(), monthCursor.value.getMonth() - 1, 1);
}

function nextMonth() {
    monthCursor.value = new Date(monthCursor.value.getFullYear(), monthCursor.value.getMonth() + 1, 1);
}

function selectDate(date: Date) {
    emit('update:modelValue', toIsoDate(date));
    closeCalendar();
}

function clearDate() {
    emit('update:modelValue', '');
}

function isToday(date: Date): boolean {
    const now = new Date();
    return now.toDateString() === date.toDateString();
}

function isSelected(date: Date): boolean {
    if (!selectedDate.value) return false;
    return selectedDate.value.toDateString() === date.toDateString();
}

function onDocumentClick(e: MouseEvent) {
    if (!isOpen.value) return;
    const target = e.target as Node | null;
    if (rootRef.value && target && !rootRef.value.contains(target)) {
        closeCalendar();
    }
}

watch(
    () => props.modelValue,
    () => {
        if (!props.modelValue) return;
        const d = parseIsoDate(props.modelValue);
        if (d) monthCursor.value = new Date(d.getFullYear(), d.getMonth(), 1);
    },
);

onMounted(() => {
    document.addEventListener('mousedown', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentClick);
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            class="w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-left text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
            @click="openCalendar"
        >
            {{ displayValue || placeholder }}
        </button>

        <button
            v-if="modelValue"
            type="button"
            class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full px-2 py-1 text-xs text-[#2E2E2E]/60 hover:bg-black/5"
            @click.stop="clearDate"
        >
            Puhasta
        </button>

        <div
            v-if="isOpen"
            class="absolute z-50 mt-2 w-full rounded-2xl border border-black/10 bg-white p-3 shadow-xl"
        >
            <div class="mb-2 flex items-center justify-between">
                <button type="button" class="rounded-lg px-2 py-1 hover:bg-black/5" @click="previousMonth">‹</button>
                <p class="text-sm font-semibold capitalize">{{ currentMonthLabel }}</p>
                <button type="button" class="rounded-lg px-2 py-1 hover:bg-black/5" @click="nextMonth">›</button>
            </div>

            <div class="mb-1 grid grid-cols-7 gap-1 text-center text-xs font-medium text-[#2E2E2E]/60">
                <span v-for="wd in weekDays" :key="wd">{{ wd }}</span>
            </div>

            <div class="grid grid-cols-7 gap-1">
                <button
                    v-for="day in days"
                    :key="day.date.toISOString()"
                    type="button"
                    class="h-8 rounded-lg text-sm transition"
                    :class="[
                        day.inMonth ? 'text-[#2E2E2E]' : 'text-[#2E2E2E]/30',
                        isSelected(day.date) ? 'bg-[#6B8C68] text-white' : 'hover:bg-black/5',
                        !isSelected(day.date) && isToday(day.date) ? 'ring-1 ring-[#6B8C68]/40' : '',
                    ]"
                    @click="selectDate(day.date)"
                >
                    {{ day.date.getDate() }}
                </button>
            </div>
        </div>
    </div>
</template>
