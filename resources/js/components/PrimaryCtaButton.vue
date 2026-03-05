<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        label: string;
        icon: string;
        fullWidth?: boolean;
        href?: string;
        type?: 'button' | 'submit';
    }>(),
    {
        fullWidth: false,
        href: undefined,
        type: 'button',
    },
);

const baseClass = 'btn-primary cursor-pointer inline-flex items-center justify-center gap-2';
</script>

<template>
  <component
    :is="href ? Link : 'button'"
    :href="href"
    :type="href ? undefined : type"
    :class="[baseClass, fullWidth && 'w-full']"
    v-bind="$attrs"
  >
    <span class="material-symbols-outlined text-[18px]">
      {{ icon }}
    </span>
    <span>
      {{ label }}
    </span>
  </component>
</template>

