<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import AddBed from '@/pages/map/AddBed.vue';

const breadcrumbs = [
  { title: 'Aiaplaan', href: '/map' },
  { title: 'Lisa peenar', href: '/map/beds/new' },
];
</script>

<template>
  <Head title="Lisa peenar" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <main class="pb-6 space-y-4">
        <DiaryHeader
          title="Lisa peenar"
          back-href="/map"
          title-class="text-2xl font-bold tracking-tight text-primary"
          header-class="pt-6 px-0 md:px-0"
          top-row-class="mb-2"
          bottom-row-class="mb-0"
        />
        <p class="-mt-2 text-muted-foreground">
          Loo uus peenar ja joonista selle kuju ruutudest.
        </p>

        <AddBed />
      </main>

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>

