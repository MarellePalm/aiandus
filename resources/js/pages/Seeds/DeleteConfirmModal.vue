<script setup lang="ts">
defineProps<{
    open: boolean;
    title: string;
    message: string;
    processing?: boolean;
}>();

defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <transition name="fade">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" aria-modal="true" role="dialog">
            <div class="absolute inset-0 bg-black/30 backdrop-blur-[2px]" @click="$emit('close')" />

            <div class="relative w-full max-w-sm rounded-3xl bg-[#FAF8F4] p-6 text-center shadow-xl ring-1 ring-black/5">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#E6E2D5]">
                    <span class="material-symbols-outlined text-2xl text-[#6B8C68]">delete</span>
                </div>

                <h3 class="text-lg font-semibold text-[#2E2E2E]">{{ title }}</h3>
                <p class="mt-2 text-sm text-[#2E2E2E]/70">{{ message }}</p>

                <div class="mt-6 flex flex-col gap-3">
                    <button
                        class="rounded-2xl bg-[#6B8C68] py-3 font-medium text-white transition hover:bg-[#4F6A52] disabled:opacity-60"
                        :disabled="processing"
                        @click="$emit('confirm')"
                    >
                        {{ processing ? 'Kustutan...' : 'Jah, kustuta' }}
                    </button>

                    <button
                        class="text-sm text-[#2E2E2E]/60 hover:text-[#2E2E2E]"
                        :disabled="processing"
                        @click="$emit('close')"
                    >
                        Tühista
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
