<script setup lang="ts">
withDefaults(
    defineProps<{
        ariaLabel: string;
        positionClass: string;
    }>(),
    {},
);
</script>

<template>
    <button
        type="button"
        :aria-label="ariaLabel"
        class="absolute z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white/70 text-[#6B8C68] shadow-sm backdrop-blur-md transition hover:scale-105 hover:bg-white"
        :class="positionClass"
    >
        <slot />
    </button>
</template>
