<!-- resources/js/Pages/Calendar.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type Task = {
  id: string;
  title: string;
  latin?: string;
  metaLeftIcon: string;
  metaLeftText: string;
  metaRightIcon: string;
  metaRightText: string;
  metaRightClass?: string;
  progress?: number;
  hasProgress?: boolean;
  done?: boolean;
};

const { month, year, notesByDate } = defineProps<{
  month: number;
  year: number;
  notesByDate: Record<string, any[]>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kalender', href: calendar().url }];

const viewDate = ref(new Date(year, month - 1, 1));
const today = new Date();
const selectedDay = ref(
  today.getFullYear() === year && today.getMonth() === month - 1 ? today.getDate() : 1,
);

const monthTitle = computed(() => {
  const d = viewDate.value;
  return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const phaseLabel = computed(() => 'Planning Phase');
const dayLabels = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

function prevMonth() {
  const d = new Date(viewDate.value);
  d.setMonth(d.getMonth() - 1);
  viewDate.value = d;
  selectedDay.value = 1;
}

function nextMonth() {
  const d = new Date(viewDate.value);
  d.setMonth(d.getMonth() + 1);
  viewDate.value = d;
  selectedDay.value = 1;
}

const daysInMonth = computed(() => {
  const d = viewDate.value;
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
});

const startWeekday = computed(() => {
  const d = viewDate.value;
  return new Date(d.getFullYear(), d.getMonth(), 1).getDay(); // 0=Sun
});

const markedDays = computed(() => {
  const s = new Set<number>();
  const obj = notesByDate || {};
  for (const key of Object.keys(obj)) {
    const d = new Date(key); // "YYYY-MM-DD"
    if (d.getFullYear() === viewDate.value.getFullYear() && d.getMonth() === viewDate.value.getMonth()) {
      s.add(d.getDate());
    }
  }
  return s;
});

const tasks = ref<Task[]>([
  {
    id: 't1',
    title: 'Pruning the Roses',
    latin: "Rosa gallica 'Officinalis'",
    metaLeftIcon: 'location_on',
    metaLeftText: 'South Bed',
    metaRightIcon: 'priority_high',
    metaRightText: 'High Priority',
    metaRightClass: 'text-orange-600 dark:text-orange-400 font-semibold',
    progress: 65,
    hasProgress: true,
    done: false,
  },
  {
    id: 't2',
    title: 'Seeding Spinach',
    latin: 'Spinacia oleracea',
    metaLeftIcon: 'eco',
    metaLeftText: 'Vegetable Patch',
    metaRightIcon: 'schedule',
    metaRightText: 'Morning',
    hasProgress: false,
    done: false,
  },
]);

const selectedDateLabel = computed(() => {
  const d = viewDate.value;
  const dt = new Date(d.getFullYear(), d.getMonth(), selectedDay.value);
  return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});

function selectDay(day: number) {
  selectedDay.value = day;
}
</script>

<template>
  <Head title="Kalender" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="py-8 space-y-8">
        <!-- Header -->
        <header class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3 min-w-0">
            <span class="material-symbols-outlined text-3xl text-primary">potted_plant</span>
            <h1 class="text-xl font-bold tracking-tight truncate">Minu Aia Päevik</h1>
          </div>

          <button type="button" class="icon-btn" aria-label="Profiil">
            <span class="material-symbols-outlined">person</span>
          </button>
        </header>

        <!-- Calendar card -->
        <section class="card p-4">
          <div class="flex items-center justify-between mb-4">
            <button type="button" class="icon-btn" @click="prevMonth" aria-label="Eelmine kuu">
              <span class="material-symbols-outlined">chevron_left</span>
            </button>

            <div class="text-center">
              <h3 class="text-lg font-bold">{{ monthTitle }}</h3>
              <p class="text-xs text-primary font-medium tracking-widest uppercase">{{ phaseLabel }}</p>
            </div>

            <button type="button" class="icon-btn" @click="nextMonth" aria-label="Järgmine kuu">
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>

          <div class="grid grid-cols-7 gap-1">
            <div
              v-for="lbl in dayLabels"
              :key="lbl"
              class="text-center text-[10px] font-bold text-primary/60 pb-2"
            >
              {{ lbl }}
            </div>

            <div v-for="n in startWeekday" :key="'sp-' + n" class="h-10" />

            <button
              v-for="day in daysInMonth"
              :key="day"
              type="button"
              class="h-10 flex flex-col items-center justify-center rounded-lg text-sm font-medium relative"
              :class="day === selectedDay ? 'leaf-shape bg-primary text-primary-foreground font-bold shadow-md shadow-primary/30' : ''"
              @click="selectDay(day)"
              :aria-label="`Vali päev ${day}`"
            >
              {{ day }}
              <span
                v-if="markedDays.has(day) && day !== selectedDay"
                class="absolute bottom-1.5 size-1 bg-primary rounded-full"
                aria-hidden="true"
              />
            </button>
          </div>
        </section>

        <!-- Tasks header -->
        <section class="flex items-center justify-between gap-4">
          <h2 class="text-[22px] font-bold leading-tight tracking-[-0.015em]">Today's Tasks</h2>
          <span class="text-xs bg-primary/20 text-primary font-bold px-2 py-1 rounded-full uppercase tracking-tighter">
            {{ selectedDateLabel }}
          </span>
        </section>

        <!-- Tasks list -->
        <section class="space-y-4">
          <div
            v-for="t in tasks"
            :key="t.id"
            class="card p-4 leaf-shape border-l-4"
            :class="t.id === 't1' ? 'border-primary' : 'border-primary/40'"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0 flex-1">
                <h3 class="text-lg font-bold leading-tight truncate">{{ t.title }}</h3>

                <p v-if="t.latin" class="italic text-primary text-sm mb-2">
                  {{ t.latin }}
                </p>

                <div class="flex items-center gap-2 text-muted-foreground text-xs">
                  <span class="material-symbols-outlined text-sm">{{ t.metaLeftIcon }}</span>
                  <span>{{ t.metaLeftText }}</span>
                  <span class="mx-1">•</span>
                  <span class="material-symbols-outlined text-sm">{{ t.metaRightIcon }}</span>
                  <span :class="t.metaRightClass">{{ t.metaRightText }}</span>
                </div>
              </div>

              <div class="flex size-9 items-center justify-center rounded-full bg-secondary">
                <input
                  v-model="t.done"
                  type="checkbox"
                  class="h-5 w-5 rounded-full border-primary/30 border-2 bg-transparent text-primary
                         checked:bg-primary checked:border-primary focus:ring-0 focus:ring-offset-0 focus:outline-none
                         transition-all cursor-pointer"
                  :aria-label="`Märgi tehtuks: ${t.title}`"
                />
              </div>
            </div>

            <div v-if="t.hasProgress" class="mt-4 w-full bg-primary/10 h-1.5 rounded-full overflow-hidden">
              <div class="bg-primary h-full rounded-full" :style="{ width: (t.progress ?? 0) + '%' }" />
            </div>
          </div>

          <div class="flex flex-col items-center justify-center py-8 opacity-50">
            <span class="material-symbols-outlined text-4xl text-primary mb-2">potted_plant</span>
            <p class="text-sm font-medium">A day for rest and growth.</p>
          </div>
        </section>
      </div>

      <!-- ✅ Modern “Add” FAB: single action, consistent, accessible -->
      <Link href="/calendar/note-form" class="fab" aria-label="Lisa märge">
        <span class="material-symbols-outlined text-4xl">add</span>
      </Link>

      <BottomNav active="calendar" />
    </div>
  </AppLayout>
</template>

<style scoped>
.leaf-shape {
  border-top-left-radius: 2rem;
  border-bottom-right-radius: 2rem;
}
</style>
