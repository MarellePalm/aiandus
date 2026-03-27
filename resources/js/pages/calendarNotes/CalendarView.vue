<!-- resources/js/Pages/Calendar.vue -->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import CalendarSwitchTabs from '@/components/calendar/CalendarSwitchTabs.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type Note = {
  id?: number | string;
  title?: string;
  body?: string;
  type?: string;
  done?: boolean;
  due_at?: string | null;
  reminder_enabled?: boolean;
  reminder_time?: string | null;
  media_urls?: string[];
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

function onAdd() {
  router.get('/calendar/note-form', { date: selectedISO.value });
}

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

// Tühjad lahtrid enne kuu 1. päeva: Eesti nädal algab esmaspäevast (0=esmaspäev, 6=pühapäev)
const startOffset = computed(() => {
  const d = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);
  const day = d.getDay(); // JS: 0=pühapäev, 1=esmaspäev, ...
  return (day + 6) % 7; // esmaspäev=0 tühikuid, pühapäev=6 tühikuid
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
const selectedTasks = computed<Note[]>(() =>
  selectedNotes.value.filter((n) => n.type === 'task'),
);
const selectedReminders = computed<Note[]>(() =>
  selectedNotes.value.filter((n) => n.type === 'reminder' || !!n.reminder_enabled),
);
const selectedDiaryNotes = computed<Note[]>(() =>
  selectedNotes.value.filter((n) => !n.type || n.type === 'note'),
);
type DayFeedItem = {
  key: string;
  kind: 'action' | 'note';
  note: Note;
};
const dayFeedItems = computed<DayFeedItem[]>(() => [
  ...selectedTasks.value.map((note, index) => ({ key: `task-${String(note.id ?? index)}`, kind: 'action' as const, note })),
  ...selectedReminders.value.map((note, index) => ({
    key: `reminder-${String(note.id ?? index)}`,
    kind: 'action' as const,
    note,
  })),
  ...selectedDiaryNotes.value.map((note, index) => ({ key: `note-${String(note.id ?? index)}`, kind: 'note' as const, note })),
]);
const selectedSummary = computed(() => ({
  actions: selectedTasks.value.length + selectedReminders.value.length,
  notes: selectedDiaryNotes.value.length,
}));
const selectedTotal = computed(
  () => selectedSummary.value.actions + selectedSummary.value.notes,
);
const showCalendarEmptyHint = ref(false);
const CALENDAR_EMPTY_HINT_SEEN_KEY = 'calendarEmptyHintSeen';

function toggleTaskDone(n: Note) {
  if (n.id == null) return;
  router.post(`/calendar/notes/${n.id}/toggle-done`, {}, { preserveScroll: true });
}

function formatDueAt(iso: string) {
  const d = new Date(iso);
  const date = d.toLocaleDateString('et-EE', { day: 'numeric', month: 'numeric', year: 'numeric' });
  const time = d.toLocaleTimeString('et-EE', { hour: '2-digit', minute: '2-digit' });
  return `${date} kell ${time}`;
}

function selectDay(day: number) {
  selectedDay.value = day;
}

function previewText(text?: string, max = 120) {
  if (!text) return '';
  return text.length > max ? `${text.slice(0, max).trim()}...` : text;
}

onMounted(() => {
  try {
    const seen = localStorage.getItem(CALENDAR_EMPTY_HINT_SEEN_KEY) === '1';
    if (!seen) {
      showCalendarEmptyHint.value = true;
      localStorage.setItem(CALENDAR_EMPTY_HINT_SEEN_KEY, '1');
    }
  } catch {
    showCalendarEmptyHint.value = false;
  }
});
</script>

<template>
  <Head title="Kalender" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background-light text-forest font-display min-h-screen antialiased">
        <div class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title="Kalender"
            header-class="pt-6"
            top-row-class="mb-3"
            bottom-row-class="mb-4"
          >
            <template #leading>
              <BackIconButton href="/dashboard" aria-label="Tagasi avalehele" />
            </template>
            <div class="flex items-center justify-center pb-2">
              <CalendarSwitchTabs active="day" />
            </div>
          </DiaryHeader>

          <main class="flex-1 px-6 py-4 md:px-8 space-y-6">
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

            <div v-for="n in startOffset" :key="'sp-' + n" class="h-10" />

            <button
              v-for="day in daysInMonth"
              :key="day"
              type="button"
              class="h-10 flex flex-col items-center justify-center rounded-lg text-sm font-medium relative transition-colors"
              :class="[
                day === selectedDay ? 'leaf-shape bg-primary text-primary-foreground font-bold shadow-md shadow-primary/30' : '',
                day !== selectedDay &&
                viewDate.getMonth() === today.getMonth() &&
                viewDate.getFullYear() === today.getFullYear() &&
                day === today.getDate()
                  ? 'ring-2 ring-primary/40 ring-offset-2 ring-offset-background bg-primary/5'
                  : 'hover:bg-muted/50',
              ]"
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

        <!-- Valitud päev: ülesanded ja märkmed -->
        <section class="rounded-2xl border border-border bg-card/90 p-4 sm:p-5 shadow-soft">
          <div class="mb-5">
            <p class="text-xs tracking-wide text-muted-foreground capitalize">{{ selectedMonthLabel }}</p>
            <h2 class="mt-1 text-2xl font-bold capitalize text-foreground">{{ selectedWeekday }}</h2>
          </div>

          <section class="mb-2 space-y-3">
            <div v-if="dayFeedItems.length" class="flex overflow-x-auto gap-3 pb-1 no-scrollbar">
              <article
                v-for="item in dayFeedItems"
                :key="item.key"
                class="basis-[calc((100%-0.75rem)/2)] min-w-[calc((100%-0.75rem)/2)] shrink-0 rounded-xl border border-border/60 bg-card p-2.5 shadow-soft"
              >
                <div class="mb-2 flex items-start justify-between gap-2">
                  <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[9px] font-semibold text-primary">
                    <span class="material-symbols-outlined text-[12px]">
                      {{ item.kind === 'action' ? 'checklist' : 'description' }}
                    </span>
                    {{ item.kind === 'action' ? 'Töö' : 'Märge' }}
                  </span>
                  <input
                    v-if="item.kind === 'action'"
                    type="checkbox"
                    :checked="!!item.note.done"
                    class="journal-check accent-primary mt-0.5"
                    @change="toggleTaskDone(item.note)"
                  />
                </div>
                <p :class="['text-base leading-snug font-bold line-clamp-2', item.kind === 'action' && item.note.done ? 'line-through opacity-70' : '']">
                  {{ item.note.title || item.note.body || 'Kirje' }}
                </p>
                <p v-if="item.note.body && item.note.title" class="mt-1 text-xs text-muted-foreground line-clamp-2">
                  {{ previewText(item.note.body, 90) }}
                </p>
                <p v-if="item.kind === 'action' && item.note.due_at" class="mt-1.5 text-[11px] font-semibold text-primary line-clamp-1">
                  {{ formatDueAt(item.note.due_at) }}
                </p>
                <p v-else-if="item.kind === 'action' && item.note.reminder_time" class="mt-1.5 text-[11px] font-semibold text-primary line-clamp-1">
                  {{ item.note.reminder_time }}
                </p>
                <img
                  v-else-if="item.kind === 'note' && item.note.media_urls?.[0]"
                  :src="item.note.media_urls[0]"
                  alt="Märkme foto"
                  class="mt-2 h-14 w-14 rounded-lg border border-border/60 object-cover"
                />
              </article>
            </div>

            <div v-if="selectedTotal === 0" class="rounded-xl border border-border bg-muted/25 px-4 py-3">
              <p class="text-sm text-muted-foreground">Sellel päeval pole veel kirjeid.</p>
              <p v-if="showCalendarEmptyHint" class="mt-1 text-xs text-muted-foreground">
                Kasuta paremal all olevat + nuppu, et lisada uus kirje.
              </p>
            </div>
          </section>

          <div class="mt-5">
            <Link href="/calendar/overview" class="btn-panel-link mt-0!">
              <span class="material-symbols-outlined text-lg">description</span>
              <span class="font-semibold text-sm">Vaata kõiki märkmeid</span>
              <span class="material-symbols-outlined text-lg ml-auto">chevron_right</span>
            </Link>
          </div>
        </section>
          </main>
        </div>

        <FloatingPlusButton aria-label="Lisa märge" :size-px="52" :icon-size-px="30" @click="onAdd" />

        <BottomNav active="calendar" />
      </div>
    </div>
  </AppLayout>
</template>
