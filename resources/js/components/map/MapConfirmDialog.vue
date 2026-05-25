<script setup lang="ts">
defineProps<{
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/45 px-4"
        @click.self="emit('close')"
    >
        <form
            class="w-full max-w-md rounded-3xl border border-border/70 bg-background p-5 shadow-2xl"
            @submit.prevent="emit('confirm')"
        >
            <div class="mb-4">
                <p
                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    Kinnitus
                </p>
                <h3 class="mt-1 text-lg font-semibold text-foreground">
                    {{ title }}
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ message }}
                </p>
            </div>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-full border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition hover:bg-muted"
                    @click="emit('close')"
                >
                    Tühista
                </button>
                <button
                    type="submit"
                    class="inline-flex items-center rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </form>
    </div>
</template>
