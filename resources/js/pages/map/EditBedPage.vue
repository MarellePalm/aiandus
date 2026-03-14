<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import DiaryHeader from '@/components/DiaryHeader.vue';
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
      <main class="pb-6 space-y-4">
        <section class="page-container-wide">
          <DiaryHeader
            title="Muuda peenart"
            title-class="text-2xl font-bold tracking-tight text-primary"
            header-class="pt-6 px-0 md:px-0"
            top-row-class="mb-2"
            bottom-row-class="mb-0"
          />
          <p class="-mt-2 text-muted-foreground">
            Kohanda peenra nime, asukohta ja kuju.
          </p>
        </section>

        <section class="page-container-wide">
          <AddBed mode="edit" :bed="bed" />
        </section>
      </main>

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>

