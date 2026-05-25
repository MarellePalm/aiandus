<script setup lang="ts">
defineProps<{
    open: boolean;
    name: string;
    submitting: boolean;
}>();

const emit = defineEmits<{
    'update:name': [value: string];
    cancel: [];
    submit: [];
}>();
</script>

<template>
    <div
        v-if="open"
        data-place-bed-dialog
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/35 p-4 sm:items-center"
        @click.self="emit('cancel')"
    >
        <div
            data-place-bed-dialog
            class="w-full max-w-sm rounded-2xl border border-border/80 bg-card p-4 shadow-xl"
            @click.stop
        >
            <p class="text-base font-semibold text-foreground">Uus peenar</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Asukoht on valitud. Anna peenrale nimi — seejärel joonistad kuju
                ruutude kaupa.
            </p>
            <label
                class="mt-4 block text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                for="place-bed-name"
            >
                Peenra nimi
            </label>
            <input
                id="place-bed-name"
                :value="name"
                type="text"
                maxlength="120"
                class="mt-1.5 w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm font-medium text-foreground ring-offset-background outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                @input="
                    emit(
                        'update:name',
                        ($event.target as HTMLInputElement).value,
                    )
                "
                @keydown.enter.prevent="emit('submit')"
            />
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    class="rounded-full border border-border/70 px-4 py-2 text-sm font-semibold text-foreground transition hover:bg-muted"
                    :disabled="submitting"
                    @click="emit('cancel')"
                >
                    Tühista
                </button>
                <button
                    type="button"
                    class="rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-60"
                    :disabled="submitting"
                    @click="emit('submit')"
                >
                    Loo peenar
                </button>
            </div>
        </div>
    </div>
</template>
