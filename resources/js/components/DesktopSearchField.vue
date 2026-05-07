<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
    }>(),
    {
        placeholder: 'Otsi...',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <label
        class="hidden h-10 min-w-[240px] items-center gap-2 rounded-full border border-border bg-background px-4 text-sm text-muted-foreground transition focus-within:border-primary lg:flex"
    >
        <span class="material-symbols-outlined text-[19px] text-primary/80"
            >search</span
        >
        <input
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            class="w-full bg-transparent text-foreground outline-none placeholder:text-muted-foreground"
            @input="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />
    </label>
</template>
