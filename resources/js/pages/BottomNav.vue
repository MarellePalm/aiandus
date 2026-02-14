<!-- resources/js/Pages/BottomNav.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

import { dashboard, calendar, map } from '@/routes';

const props = defineProps<{
  active?: 'dashboard' | 'calendar' | 'map';
}>();

const activeKey = computed(() => props.active ?? 'dashboard');

function itemClass(key: 'dashboard' | 'calendar' | 'map') {
  return [
    'flex flex-col items-center',
    key === activeKey.value ? 'text-primary' : 'text-muted-foreground',
  ].join(' ');
}

function labelClass(key: 'dashboard' | 'calendar' | 'map') {
  return ['text-[10px] mt-1', key === activeKey.value ? 'font-bold' : 'font-medium'].join(' ');
}
</script>

<template>
  <nav class="fixed bottom-0 left-0 right-0 z-40 bg-card border-t border-border">
    <!-- uses app.css responsive container widths -->
    <div class="page-container">
      <div class="h-20 flex items-center justify-between">
        <!-- TÄNA -->
        <Link :href="dashboard().url" :class="itemClass('dashboard')">
          <span class="material-symbols-outlined text-2xl">grid_view</span>
          <span :class="labelClass('dashboard')">Täna</span>
        </Link>

        <!-- AIAPLAAN -->
        <Link :href="map().url" :class="itemClass('map')">
          <span class="material-symbols-outlined text-2xl">map</span>
          <span :class="labelClass('map')">Aiaplaan</span>
        </Link>

        <!-- KALENDER -->
        <Link :href="calendar().url" :class="itemClass('calendar')">
          <span class="material-symbols-outlined text-2xl">calendar_today</span>
          <span :class="labelClass('calendar')">Kalender</span>
        </Link>

        <!-- TAIMED (disabled placeholder) -->
        <div class="flex flex-col items-center text-muted-foreground opacity-60 select-none">
          <span class="material-symbols-outlined text-2xl">local_florist</span>
          <span class="text-[10px] mt-1 font-medium">Taimed</span>
        </div>
      </div>
    </div>
  </nav>
</template>
