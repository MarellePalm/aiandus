<script setup lang="ts">
defineProps<{
  active: 'dashboard' | 'map' | 'calendar' | 'plants';
}>();
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 h-20 px-6 flex items-center justify-between z-40"
  >
    <!-- TÄNA / DASHBOARD -->
    <div
      class="flex flex-col items-center"
      :class="active === 'dashboard' ? 'text-primary' : 'text-gray-400'"
    >
      <span class="material-symbols-outlined text-2xl">grid_view</span>
      <span
        class="text-[10px] mt-1"
        :class="active === 'dashboard' ? 'font-bold' : 'font-medium'"
      >
        Täna
      </span>
    </div>

    <!-- AIAPLAAN -->
    <div
      class="flex flex-col items-center"
      :class="active === 'map' ? 'text-primary' : 'text-gray-400'"
    >
      <span class="material-symbols-outlined text-2xl">map</span>
      <span class="text-[10px] mt-1">Aiaplaan</span>
    </div>

    <!-- KALENDER -->
    <div
      class="flex flex-col items-center"
      :class="active === 'calendar' ? 'text-primary' : 'text-gray-400'"
    >
      <span class="material-symbols-outlined text-2xl">calendar_today</span>
      <span class="text-[10px] mt-1">Kalender</span>
    </div>

    <!-- TAIMED -->
    <div
      class="flex flex-col items-center"
      :class="active === 'plants' ? 'text-primary' : 'text-gray-400'"
    >
      <span class="material-symbols-outlined text-2xl">local_florist</span>
      <span class="text-[10px] mt-1">Taimed</span>
    </div>
  </nav>
</template>
