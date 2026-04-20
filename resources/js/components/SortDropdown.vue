<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";

type SortOption = {
  label: string;
  value: string;
};

withDefaults(defineProps<{
  options: SortOption[];
  modelValue: string;
  iconOnly?: boolean;
  compact?: boolean;
}>(), {
  iconOnly: false,
  compact: false,
});

const emit = defineEmits<{
  (e: "update:modelValue", value: string): void;
}>();

const open = ref(false);

function toggleDropdown() {
  open.value = !open.value;
}

function selectOption(value: string) {
  emit("update:modelValue", value);
  open.value = false;
}

function onDocClick(e: MouseEvent) {
  const target = e.target as HTMLElement | null;
  if (target?.closest?.("[data-sort-dropdown]")) return;
  open.value = false;
}

onMounted(() => document.addEventListener("click", onDocClick));
onBeforeUnmount(() => document.removeEventListener("click", onDocClick));
</script>

<template>
  <div class="relative inline-block" data-sort-dropdown>
    <button
      type="button"
      class="flex items-center gap-2 rounded-2xl border border-border bg-background px-4 py-2 text-sm font-medium text-foreground/80 shadow-sm transition hover:bg-black/5"
      :class="[
        iconOnly ? 'h-9 w-9 justify-center rounded-full p-0' : '',
        compact && !iconOnly ? 'px-3 py-1.5 text-xs' : '',
      ]"
      @click.stop="toggleDropdown"
    >
      <template v-if="iconOnly">
        <span class="material-symbols-outlined text-[20px]">sort</span>
      </template>
      <template v-else>
        <span>Sorteeri</span>
        <span class="text-xs">{{ open ? "▲" : "▼" }}</span>
      </template>
    </button>

    <div
      v-if="open"
      class="absolute right-0 top-12 z-50 min-w-[220px] overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-border"
    >
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        class="block w-full px-4 py-3 text-left text-sm text-foreground transition hover:bg-black/5"
        @click="selectOption(option.value)"
      >
        {{ option.label }}
      </button>
    </div>
  </div>
</template>