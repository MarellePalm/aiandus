<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';

const props = withDefaults(
  defineProps<{ placement?: 'overlay' | 'inline' }>(),
  { placement: 'overlay' }
);

const emit = defineEmits<{
  (e: 'edit'): void
  (e: 'delete'): void
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const rootClass = computed(() =>
  props.placement === 'inline'
    ? 'relative z-20'
    : 'absolute top-2 right-2 z-20'
);

const buttonClass = computed(() =>
  props.placement === 'inline'
    ? 'flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 sm:h-10 sm:w-10'
    : 'flex h-8 w-8 items-center justify-center rounded-full bg-card/80 text-primary shadow-sm backdrop-blur-md transition hover:scale-105 hover:bg-card'
);

const menuClass = computed(() =>
  props.placement === 'inline'
    ? 'absolute bottom-10 right-0 z-30 min-w-[140px] overflow-hidden rounded-2xl border border-border bg-card shadow-lg'
    : 'absolute top-10 right-0 z-30 min-w-[140px] overflow-hidden rounded-2xl border border-border bg-card shadow-lg'
);

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
  document.addEventListener('click', onClickOutside, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside, true);
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
  <div ref="root" :class="rootClass">
    <button
      type="button"
      :class="buttonClass"
      aria-label="Menüü"
      @click.prevent.stop="toggle"
    >
      <span class="material-symbols-outlined" :class="placement === 'inline' ? 'text-xl' : 'text-[18px]'">more_horiz</span>
    </button>

    <transition name="menu">
      <div
        v-if="open"
        :class="menuClass"
        @click.stop
      >
        <button
          class="w-full px-4 py-3 text-left text-sm text-foreground hover:bg-primary/5"
          @click.prevent.stop="onEdit"
        >
          Muuda
        </button>

        <button
          class="w-full px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
          @click.prevent.stop="onDelete"
        >
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