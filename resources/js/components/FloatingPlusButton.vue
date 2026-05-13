<script setup lang="ts">
withDefaults(
    defineProps<{
        ariaLabel?: string;
        sizePx?: number;
        iconSizePx?: number;
        bottomPx?: number;
    }>(),
    {
        ariaLabel: 'Lisa',
        sizePx: 52,
        iconSizePx: 30,
        bottomPx: 96,
    },
);

const emit = defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <button
        type="button"
        :aria-label="ariaLabel"
        class="fixed right-6 z-50 flex cursor-pointer items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform active:scale-95"
        :style="{
            width: `${sizePx}px`,
            height: `${sizePx}px`,
            bottom: `calc(${bottomPx}px + env(safe-area-inset-bottom, 0px))`,
        }"
        @click="emit('click')"
    >
        <span
            class="material-symbols-outlined block leading-none"
            :style="{
                fontSize: `${iconSizePx}px`,
                width: `${iconSizePx}px`,
                height: `${iconSizePx}px`,
                lineHeight: `${iconSizePx}px`,
                fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24`,
            }"
        >
            add
        </span>
    </button>
</template>
