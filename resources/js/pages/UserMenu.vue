<!-- resources/js/components/ui/UserMenu.vue -->
<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

type Props = {
  settingsHref?: string;
};

const props = withDefaults(defineProps<Props>(), {
  settingsHref: '/settings',
});

const open = ref(false);
const rootRef = ref<HTMLElement | null>(null);

function close() {
  open.value = false;
}

function toggle() {
  open.value = !open.value;
}

function onDocClick(e: MouseEvent) {
  if (!open.value) return;
  const el = rootRef.value;
  const target = e.target as Node | null;
  if (el && target && !el.contains(target)) close();
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') close();
}

function logout() {
  router.post('/logout', {}, { onFinish: close });
}

onMounted(() => {
  document.addEventListener('click', onDocClick);
  document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick);
  document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div ref="rootRef" class="relative z-50">
    <button
      type="button"
      class="icon-btn"
      aria-label="Profiil"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click="toggle"
    >
      <span class="material-symbols-outlined">person</span>
    </button>

    <div
      v-if="open"
      class="menu absolute right-0 mt-2 w-48"
      role="menu"
      aria-label="Profiili menüü"
    >
      <Link
        :href="props.settingsHref"
        role="menuitem"
        class="menu-item"
        @click="close"
      >
        Seaded
      </Link>

      <div class="menu-separator" />

      <button
        type="button"
        role="menuitem"
        class="menu-item"
        @click="logout"
      >
        Logi välja
      </button>
    </div>
  </div>
</template>
