<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

type SortOption = {
    label: string;
    value: string;
};

withDefaults(
    defineProps<{
        options: SortOption[];
        modelValue: string;
        iconOnly?: boolean;
        compact?: boolean;
        label?: string;
    }>(),
    {
        iconOnly: false,
        compact: false,
        label: 'Sorteeri',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
const triggerRef = ref<HTMLElement | null>(null);
const menuStyle = ref<Record<string, string>>({});

function toggleDropdown() {
    open.value = !open.value;
    if (open.value) {
        nextTick(() => updateMenuPosition());
    }
}

function selectOption(value: string) {
    emit('update:modelValue', value);
    open.value = false;
}

function onDocClick(e: MouseEvent) {
    const target = e.target as HTMLElement | null;
    if (target?.closest?.('[data-sort-dropdown]')) return;
    open.value = false;
}

function updateMenuPosition() {
    const el = triggerRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    menuStyle.value = {
        top: `${rect.bottom + 8}px`,
        left: `${Math.max(8, rect.right - 220)}px`,
        width: '220px',
    };
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    window.addEventListener('resize', updateMenuPosition);
    window.addEventListener('scroll', updateMenuPosition, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    window.removeEventListener('resize', updateMenuPosition);
    window.removeEventListener('scroll', updateMenuPosition, true);
});
</script>

<template>
    <div class="ml-auto flex justify-end" data-sort-dropdown>
        <button
            ref="triggerRef"
            type="button"
            class="flex h-9 items-center justify-center rounded-full border border-primary/30 bg-primary/5 px-3 text-primary shadow-sm transition hover:bg-primary/10"
            :class="[
                iconOnly
                    ? 'h-9 w-9 px-0'
                    : compact
                      ? 'h-9 w-9 px-0 lg:h-9 lg:w-auto lg:px-3'
                      : 'w-9 px-0 lg:w-auto lg:px-3',
            ]"
            @click.stop="toggleDropdown"
        >
            <span class="material-symbols-outlined text-[20px]">sort</span>
            <span
                v-if="!iconOnly"
                class="ml-1.5 hidden text-sm font-medium lg:inline"
            >
                {{ label }}
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed z-[120] overflow-hidden rounded-xl border border-primary/20 bg-card shadow-xl ring-1 ring-primary/10"
                :style="menuStyle"
            >
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    class="block w-full px-4 py-3 text-left text-sm text-foreground transition hover:bg-primary/10"
                    @click="selectOption(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </Teleport>
    </div>
</template>
