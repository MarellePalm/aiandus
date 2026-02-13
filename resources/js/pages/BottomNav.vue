<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { dashboard, calendar, map } from '@/routes';

defineProps<{
  active?: 'dashboard' | 'calendar' | 'map';
}>();
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 z-40"
  >
    <div class="mx-auto max-w-md h-20 px-6 flex items-center justify-between">
      <!-- TÄNA -->
      <Link
        :href="dashboard().url"
        class="flex flex-col items-center"
        :class="active === 'dashboard' ? 'text-primary' : 'text-gray-400'"
      >
        <span class="material-symbols-outlined text-2xl">grid_view</span>
        <span class="text-[10px] mt-1" :class="active === 'dashboard' ? 'font-bold' : 'font-medium'">
          Täna
        </span>
      </Link>

      <!-- AIAPLAAN -->
      <Link
        :href="map().url"
        class="flex flex-col items-center"
        :class="active === 'map' ? 'text-primary' : 'text-gray-400'"
      >
        <span class="material-symbols-outlined text-2xl">map</span>
        <span class="text-[10px] mt-1">Aiaplaan</span>
      </Link>

      <!-- KALENDER -->
      <Link
        :href="calendar().url"
        class="flex flex-col items-center"
        :class="active === 'calendar' ? 'text-primary' : 'text-gray-400'"
      >
        <span class="material-symbols-outlined text-2xl">calendar_today</span>
        <span class="text-[10px] mt-1">Kalender</span>
      </Link>

      <!-- TAIMED -->
      <div
        class="flex flex-col items-center text-gray-400 opacity-60">
          <span class="material-symbols-outlined text-2xl">local_florist</span>
          <span class="text-[10px] mt-1 opacity-60 font-medium">Taimed</span>
        </div>
      </div>
    </nav>
  </template>