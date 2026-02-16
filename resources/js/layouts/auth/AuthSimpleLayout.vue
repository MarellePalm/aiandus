<!-- resources/js/layouts/auth/AuthSimpleLayout.vue -->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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
      <!-- Optional botanical decor (uses note-decor classes from app.css, already used in NoteForm) -->
      <div class="note-decor note-decor--tr" aria-hidden="true">
        <svg width="200" height="200" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M40 160C40 160 50 100 120 80C190 60 180 20 180 20"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="2"
          />
          <path
            d="M120 80C120 80 140 110 130 140"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="2"
          />
          <circle cx="120" cy="80" r="4" fill="currentColor" />
        </svg>
      </div>

      <div class="note-decor note-decor--bl" aria-hidden="true">
        <svg width="250" height="250" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M160 160C160 160 150 100 80 80C10 60 20 20 20 20"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="2"
          />
          <path
            d="M80 80C80 80 60 110 70 140"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="2"
          />
          <circle cx="80" cy="80" r="4" fill="currentColor" />
        </svg>
      </div>

      <!-- Top bar -->
      <div class="flex items-center justify-between pb-2">
        <Link v-if="props.backHref" :href="props.backHref" class="icon-btn" aria-label="Tagasi">
          <span class="material-symbols-outlined">arrow_back_ios</span>
        </Link>
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
