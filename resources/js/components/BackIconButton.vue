<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
  href: string;
  ariaLabel?: string;
}>();
</script>

<template>
  <Link
    :href="href"
    :aria-label="ariaLabel ?? 'Tagasi'"
    class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10"
  >
    <span class="material-symbols-outlined text-[22px]">arrow_back_ios_new</span>
  </Link>
</template>
