<!-- resources/js/layouts/auth/AuthSimpleLayout.vue -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BackIconButton from '@/components/BackIconButton.vue';

const props = withDefaults(
  defineProps<{
    title?: string;
    description?: string;
    backHref?: string;
    showLogo?: boolean;
  }>(),
  {
    title: '',
    description: '',
    backHref: '/',
    showLogo: true,
  },
);
</script>

<template>
  <div class="page min-h-screen">
    <Head :title="props.title || 'Autentimine'" />

    <div class="page-container-wide relative min-h-screen overflow-hidden py-6 sm:py-10">
      <!-- Top bar -->
      <div class="flex items-center justify-between pb-2">
        <BackIconButton v-if="props.backHref" :href="props.backHref" aria-label="Tagasi" />
        <div class="flex-1" />
      </div>

      <!-- Header: logo + title + description -->
      <header class="flex flex-col items-center pt-4 pb-10 text-center">
        <div
          v-if="props.showLogo"
          class="mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 dark:bg-primary/20"
        >
          <span class="material-symbols-outlined text-4xl text-primary">potted_plant</span>
        </div>

        <h1 v-if="props.title" class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
          {{ props.title }}
        </h1>

        <p v-if="props.description" class="mt-2 text-base text-muted-foreground">
          {{ props.description }}
        </p>
      </header>

      <main class="w-full">
        <slot />
      </main>

      <!-- Decorative leaf -->
      <div class="pointer-events-none fixed right-[-50px] bottom-[-50px] opacity-[0.03] dark:opacity-[0.07]">
        <span class="material-symbols-outlined text-[300px] select-none text-primary">eco</span>
      </div>
    </div>
  </div>
</template>
