<script setup lang="ts">
import { nextTick, onBeforeUnmount, watch } from 'vue';

type PlantTarget = { id: number; name: string };

const props = defineProps<{
    open: boolean;
    plant: PlantTarget | null;
    processing?: boolean;
}>();

defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();

watch(
    () => props.open,
    async (val) => {
        if (val) {
            document.body.style.overflow = 'hidden';
            await nextTick();
        } else {
            document.body.style.overflow = '';
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[60] flex items-center justify-center p-4"
                aria-modal="true"
                role="dialog"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
                    aria-label="Sulge taustal"
                    @click="$emit('close')"
                />

                <div
                    class="relative w-full max-w-sm rounded-3xl bg-card shadow-xl ring-1 ring-border"
                >
                    <div
                        class="pointer-events-none absolute -top-3 -left-3 opacity-20"
                    >
                        <div class="h-10 w-10 rounded-full bg-muted" />
                    </div>

                    <div class="p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3
                                    class="text-lg font-semibold text-foreground"
                                >
                                    Kustuta taim?
                                </h3>
                                <p class="mt-1 text-sm text-foreground/70">
                                    {{ plant?.name }} eemaldatakse jäädavalt.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="rounded-full p-2 text-foreground/60 hover:bg-black/5 hover:text-foreground"
                                aria-label="Sulge"
                                @click="$emit('close')"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="mt-6 flex flex-col gap-3">
                            <button
                                type="button"
                                class="rounded-2xl bg-primary px-4 py-3 font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:opacity-50"
                                :disabled="processing"
                                @click="$emit('confirm')"
                            >
                                {{
                                    processing ? 'Kustutan...' : 'Jah, kustuta'
                                }}
                            </button>

                            <button
                                type="button"
                                class="text-sm text-foreground/60 hover:text-foreground"
                                :disabled="processing"
                                @click="$emit('close')"
                            >
                                Tühista
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
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
