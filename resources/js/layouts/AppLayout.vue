<script setup lang="ts">

import BottomNav from '@/pages/BottomNav.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}


withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="min-h-screen bg-background relative">
      <!-- Page content -->
      <main class="pb-24">
        <slot />
      </main>
  
      <!-- Global bottom navigation -->
      <BottomNav />
    </div>
  </template>
  
