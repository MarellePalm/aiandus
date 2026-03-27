<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

import { calendar } from '@/routes'

type ActiveKey = 'day' | 'moon'

const props = withDefaults(
  defineProps<{
    active?: ActiveKey
  }>(),
  {
    active: 'day',
  },
)

const dayHref = calendar().url
const moonHref = '/calendar/moon'

function tabClass(key: ActiveKey) {
  const active = props.active === key

  if (active) return 'bg-primary text-primary-foreground shadow-soft'
  return 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
}
</script>

<template>
  <div class="inline-flex items-center gap-1 rounded-2xl border border-border bg-card/40 p-1 backdrop-blur-sm">
    <Link
      :href="dayHref"
      :class="[
        'px-3 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors',
        tabClass('day'),
      ]"
    >
      <span class="material-symbols-outlined text-[18px]">calendar_today</span>
      Kalender
    </Link>

    <Link
      :href="moonHref"
      :class="[
        'px-3 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors',
        tabClass('moon'),
      ]"
    >
      <span class="material-symbols-outlined text-[18px]">nightlight</span>
      Kuukalender
    </Link>
  </div>
</template>

