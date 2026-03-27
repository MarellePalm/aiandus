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
  image_url?: string | null;
  layout?: number[][] | null;
  plants?: { id: number; name: string; image_url: string | null; position_in_bed: string | null }[];
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
            back-href="/map"
            title-class="text-forest text-3xl font-bold tracking-tight"
            header-class="pt-6 px-0 md:px-0"
            top-row-class="mb-2"
            bottom-row-class="mb-0"
          />
        </section>

        <section class="page-container-wide">
          <AddBed mode="edit" :bed="bed" />
        </section>
      </main>

      <BottomNav active="map" />
    </div>
  </AppLayout>
</template>

