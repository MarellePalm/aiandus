<!-- resources/js/Pages/Calendar.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type Note = {
  id?: number | string;
  title?: string;
  body?: string;
  reminder_enabled?: boolean;
  reminder_time?: string | null;
};

const { month, year, notesByDate } = defineProps<{
  month: number;
  year: number;
  notesByDate: Record<string, Note[]>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kalender', href: calendar().url }];

const viewDate = ref(new Date(year, month - 1, 1));
const today = new Date();
const selectedDay = ref(
  today.getFullYear() === year && today.getMonth() === month - 1 ? today.getDate() : 1,
);

const dayLabels = ['E', 'T', 'K', 'N', 'R', 'L', 'P'];

const monthTitle = computed(() => {
  const d = viewDate.value;
  return d.toLocaleDateString('et-EE', { month: 'long', year: 'numeric' });
});

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

const selectedDate = computed(() => {
  const d = viewDate.value;
  return new Date(d.getFullYear(), d.getMonth(), selectedDay.value);
});

const selectedISO = computed(() => {
  const d = selectedDate.value;
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
});

const selectedMonthLabel = computed(() =>
  selectedDate.value.toLocaleDateString('et-EE', { month: 'long', year: 'numeric' }),
);

const selectedWeekday = computed(() =>
  selectedDate.value.toLocaleDateString('et-EE', { weekday: 'long' }),
);

const selectedNotes = computed<Note[]>(() => notesByDate?.[selectedISO.value] ?? []);
const selectedReminders = computed<Note[]>(() => selectedNotes.value.filter((n) => !!n.reminder_enabled));

function selectDay(day: number) {
  selectedDay.value = day;
}
</script>

<template>
  <Head title="Kalender" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="page-container-wide py-8 space-y-8">
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

        <!-- ✅ Stitch-like Day + Month + Alerts -->
        <section class="journal-panel">
          <div class="flex justify-between items-start mb-6">
            <div>
              <p class="journal-subtitle capitalize">{{ selectedMonthLabel }}</p>
              <h2 class="journal-title capitalize">{{ selectedWeekday }}</h2>
            </div>

            <div class="text-primary/70">
              <span class="material-symbols-outlined text-[24px]">brightness_3</span>
            </div>
          </div>

          <!-- Tänased tööd (placeholder praegu) -->
          <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
              <h3 class="journal-section-title">Tänased tööd</h3>
            </div>

            <div class="space-y-3">
              <div class="journal-check-row">
                <div class="journal-check" aria-hidden="true"></div>
                <span class="journal-row-text">Kasta toataimi</span>
              </div>

              <div class="journal-check-row">
                <div class="journal-check" aria-hidden="true"></div>
                <span class="journal-row-text">Kontrolli kasvuhoonet</span>
              </div>
            </div>
          </section>

          <!-- Meeldetuletused -->
          <section class="mb-8">
            <h3 class="journal-section-title">Meeldetuletused</h3>

            <div v-if="selectedReminders.length" class="space-y-3 mt-4">
              <div v-for="n in selectedReminders" :key="String(n.id ?? n.title)" class="journal-reminder">
                <p class="journal-row-text">
                  {{ n.title || 'Meeldetuletus' }}
                  <span v-if="n.reminder_time" class="journal-time">— {{ n.reminder_time }}</span>
                </p>
              </div>
            </div>

            <p v-else class="journal-empty mt-4">Täna meeldetuletusi ei ole.</p>
          </section>

          <!-- Päevikumärkmed -->
          <section>
            <div class="flex justify-between items-baseline mb-3">
              <h3 class="journal-section-title mb-0">Päevikumärkmed</h3>
              <span class="journal-time"></span>
            </div>

            <div v-if="selectedNotes.length" class="space-y-3">
              <div v-for="n in selectedNotes" :key="String(n.id ?? n.title)" class="journal-note">
                <p class="journal-note-title">{{ n.title || 'Märge' }}</p>
                <p v-if="n.body" class="journal-note-body">{{ n.body }}</p>
              </div>
            </div>

            <p v-else class="journal-empty">Sellel päeval pole veel märkmeid.</p>
          </section>
        </section>
      </div>

      <Link href="/calendar/note-form" class="fab" aria-label="Lisa märge">
        <span class="material-symbols-outlined text-4xl">add</span>
      </Link>

      <BottomNav active="calendar" />
    </div>
  </AppLayout>
</template>
