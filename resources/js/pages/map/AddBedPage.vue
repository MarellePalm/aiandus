<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
      <main class="py-6 space-y-4">
        <header class="mb-2">
          <h1 class="text-2xl font-bold tracking-tight text-primary">Lisa peenar</h1>
          <p class="mt-1 text-muted-foreground">
            Loo uus peenar ja joonista selle kuju ruutudest.
          </p>
        </header>

        <AddBed />
      </main>

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>

