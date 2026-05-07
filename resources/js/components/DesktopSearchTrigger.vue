<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string;
    }>(),
    {
        label: 'Otsi...',
    },
);

const emit = defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <button
        type="button"
        class="hidden h-10 min-w-[240px] items-center gap-2 rounded-full border border-border bg-background px-4 text-left text-sm text-muted-foreground transition hover:bg-muted/40 lg:flex"
        @click="emit('click')"
    >
        <span class="material-symbols-outlined text-[19px] text-primary/80"
            >search</span
        >
        <span>{{ label }}</span>
    </button>
</template>
