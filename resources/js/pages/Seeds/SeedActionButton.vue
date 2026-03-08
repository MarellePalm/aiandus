<script setup lang="ts">
withDefaults(
    defineProps<{
        ariaLabel: string;
        positionClass?: string;
        absolute?: boolean;
    }>(),
    {
        positionClass: '',
        absolute: true,
    },
);
</script>

<template>
    <button
        type="button"
        :aria-label="ariaLabel"
        class="flex items-center justify-center rounded-full bg-white/70 text-[#6B8C68] shadow-sm backdrop-blur-md transition hover:scale-105 hover:bg-white"
        :class="[absolute ? 'absolute z-10 h-8 w-8' : '', positionClass]"
    >
        <slot />
    </button>
</template>
