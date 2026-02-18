<script setup lang="ts">
import { Head } from "@inertiajs/vue3";

import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
  slug: string;
}>();
</script>

<template>
  <Head :title="props.slug" />

  <AppLayout>
    <div class="page">
      <div class="p-8">
        <h1 class="text-3xl font-bold">
          Kategooria: {{ props.slug }}
        </h1>
      </div>
    </div>
  </AppLayout>
</template>
