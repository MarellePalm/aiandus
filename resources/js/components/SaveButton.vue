<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    {
        type: 'button',
        disabled: false,
    },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="w-full rounded-2xl bg-primary px-4 py-3 font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-60"
    >
        <slot>Salvesta</slot>
    </button>
</template>
