<!-- resources/js/Pages/BottomNav.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

import { calendar, dashboard, map } from '@/routes';

type NavKey = 'dashboard' | 'calendar' | 'map' | 'plants' | 'settings';

const props = defineProps<{
  active: NavKey;
}>();

const activeKey = computed(() => props.active);

function itemClass(key: NavKey) {
  return [
    'flex flex-col items-center gap-1',
    key === activeKey.value ? 'text-primary' : 'text-muted-foreground',
  ].join(' ');
}

function labelClass(key: NavKey) {
  return ['text-[10px]', key === activeKey.value ? 'font-bold' : 'font-medium'].join(' ');
}
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-40 bg-card/95 backdrop-blur border-t border-border"
    aria-label="Põhinavigatsioon"
  >
    <div class="page-container-wide">
      <div class="h-20 flex items-center justify-around">
        <!-- TÄNA -->
        <Link
          :href="dashboard().url"
          :class="itemClass('dashboard')"
          :aria-current="activeKey === 'dashboard' ? 'page' : undefined"
        >
          <span class="material-symbols-outlined text-2xl">grid_view</span>
          <span :class="labelClass('dashboard')">Täna</span>
        </Link>

        <!-- AIAPLAAN -->
        <Link
          :href="map().url"
          :class="itemClass('map')"
          :aria-current="activeKey === 'map' ? 'page' : undefined"
        >
          <span class="material-symbols-outlined text-2xl">map</span>
          <span :class="labelClass('map')">Aiaplaan</span>
        </Link>

        <!-- KALENDER -->
        <Link
          :href="calendar().url"
          :class="itemClass('calendar')"
          :aria-current="activeKey === 'calendar' ? 'page' : undefined"
        >
          <span class="material-symbols-outlined text-2xl">calendar_today</span>
          <span :class="labelClass('calendar')">Kalender</span>
        </Link>

        <!-- TAIMED (disabled placeholder by default) -->
        <div
          class="flex flex-col items-center gap-1 text-muted-foreground opacity-60 select-none"
          aria-disabled="true"
        >
          <span class="material-symbols-outlined text-2xl">local_florist</span>
          <span class="text-[10px] font-medium">Taimed</span>
        </div>

        <!-- SEADED -->
        <Link
          href="/settings"
          :class="itemClass('settings')"
          :aria-current="activeKey === 'settings' ? 'page' : undefined"
        >
          <span class="material-symbols-outlined text-2xl">settings</span>
          <span :class="labelClass('settings')">Seaded</span>
        </Link>
      </div>
    </div>
  </nav>
</template>
