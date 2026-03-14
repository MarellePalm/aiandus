<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';

const emit = defineEmits<{
  (e: 'edit'): void
  (e: 'delete'): void
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const toggle = () => {
  open.value = !open.value;
};

const close = () => {
  open.value = false;
};

const onClickOutside = (e: MouseEvent) => {
  if (!root.value) return;
  if (!root.value.contains(e.target as Node)) {
    close();
  }
};

onMounted(() => {
  document.addEventListener('click', onClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside);
});

const onEdit = () => {
  close();
  emit('edit');
};

const onDelete = () => {
  close();
  emit('delete');
};
</script>

<template>
  <div ref="root" class="absolute top-2 right-2 z-20">
    <button
      type="button"
      class="flex h-8 w-8 items-center justify-center rounded-full bg-white/75 text-primary shadow-sm backdrop-blur-md transition hover:scale-105 hover:bg-white"
      @click.prevent.stop="toggle"
    >
      <span class="material-symbols-outlined text-[18px]">more_horiz</span>
    </button>

    <transition name="menu">
      <div
        v-if="open"
        class="absolute top-10 right-0 min-w-[140px] overflow-hidden rounded-2xl border border-black/5 bg-white shadow-lg"
        @click.stop
      >
        <button
          class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm hover:bg-neutral-100"
          @click.prevent.stop="onEdit"
        >
          <span class="material-symbols-outlined text-[18px]">edit</span>
          Muuda
        </button>

        <button
          class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50"
          @click.prevent.stop="onDelete"
        >
          <span class="material-symbols-outlined text-[18px]">delete</span>
          Kustuta
        </button>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.menu-enter-active,
.menu-leave-active {
  transition: all 0.15s ease;
}
.menu-enter-from,
.menu-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>