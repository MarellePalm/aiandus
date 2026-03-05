<script setup lang="ts">
defineProps<{
    isFavorite: boolean;
}>();

const emit = defineEmits<{
    (e: 'delete'): void;
    (e: 'favorite'): void;
    (e: 'edit'): void;
}>();
</script>

<template>
    <button
        type="button"
        class="absolute top-2 right-2 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white/70 text-[#6B8C68] shadow-sm backdrop-blur-md transition hover:scale-105 hover:bg-white"
        aria-label="Kustuta kategooria"
        @click.prevent.stop="emit('delete')"
    >
        <span class="material-symbols-outlined text-[18px]">delete</span>
    </button>

    <button
        type="button"
        class="absolute top-2 left-2 z-10 flex h-8 w-8 items-center justify-center rounded-full shadow-sm backdrop-blur-md transition hover:scale-105"
        :class="isFavorite ? 'bg-rose-50 ring-1 ring-rose-200' : 'bg-white/70 ring-1 ring-black/10 hover:bg-white'"
        aria-label="Lisa lemmikuks"
        @click.prevent.stop="emit('favorite')"
    >
        <span
            class="material-symbols-outlined text-[18px] transition"
            :class="isFavorite ? 'text-rose-600 drop-shadow-sm' : 'text-[#2E2E2E]/45'"
            :style="
                isFavorite
                    ? { fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24` }
                    : { fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24` }
            "
        >
            favorite
        </span>
    </button>

    <button
        type="button"
        class="absolute top-2 left-1/2 z-10 flex h-8 w-8 -translate-x-1/2 items-center justify-center rounded-full bg-gray-100/90 text-sm text-gray-600 shadow-sm ring-1 ring-gray-300/70 backdrop-blur-sm transition hover:scale-105 hover:bg-gray-100"
        aria-label="Muuda kategooriat"
        @click.prevent.stop="emit('edit')"
    >
        <span class="grayscale opacity-90">✏️</span>
    </button>
</template>
