<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import AddBed from '@/pages/map/AddBed.vue';

type Bed = {
  id: number;
  name: string;
  location: string | null;
  layout?: number[][] | null;
};

const props = defineProps<{
  bed: Bed;
}>();

const breadcrumbs = [
  { title: 'Aiaplaan', href: '/map' },
  { title: 'Muuda peenart', href: `/beds/${props.bed.id}/edit` },
];
</script>

<template>
  <Head title="Muuda peenart" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <main class="py-6 space-y-4">
        <header class="mb-2 page-container-wide">
          <h1 class="text-2xl font-bold tracking-tight text-primary">Muuda peenart</h1>
          <p class="mt-1 text-muted-foreground">
            Kohanda peenra nime, asukohta ja kuju.
          </p>
        </header>

        <section class="page-container-wide">
          <AddBed mode="edit" :bed="bed" />
        </section>
      </main>

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>

