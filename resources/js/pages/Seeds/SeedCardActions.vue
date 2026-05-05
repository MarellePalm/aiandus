<script setup lang="ts">
import SeedActionButton from './SeedActionButton.vue';

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
    <SeedActionButton
        aria-label="Kustuta"
        position-class="top-2 right-2"
        @click.prevent.stop="emit('delete')"
    >
        <span class="material-symbols-outlined text-[18px]">delete</span>
    </SeedActionButton>

    <SeedActionButton
        aria-label="Lisa lemmikuks"
        position-class="top-2 left-2"
        @click.prevent.stop="emit('favorite')"
    >
        <span
            class="material-symbols-outlined text-[18px] transition"
            :class="
                isFavorite ? 'text-rose-600 drop-shadow-sm' : 'text-[#6B8C68]'
            "
            :style="
                isFavorite
                    ? {
                          fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24`,
                      }
                    : {
                          fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24`,
                      }
            "
        >
            favorite
        </span>
    </SeedActionButton>

    <SeedActionButton
        aria-label="Muuda varu"
        position-class="top-2 left-1/2 -translate-x-1/2"
        @click.prevent.stop="emit('edit')"
    >
        <span class="material-symbols-outlined text-[18px]">edit</span>
    </SeedActionButton>
</template>
