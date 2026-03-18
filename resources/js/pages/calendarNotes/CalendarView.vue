<!-- resources/js/Pages/Calendar.vue -->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const openMenuId = ref<number | string | null>(null);

function toggleNoteMenu(id: number | string | undefined) {
  if (id == null) return;
  openMenuId.value = openMenuId.value === id ? null : id;
}

function closeNoteMenu() {
  openMenuId.value = null;
}

function deleteNote(n: Note) {
  if (n.id == null) return;
  if (!confirm('Kustuta see märge?')) return;
  closeNoteMenu();
  router.delete(`/calendar/notes/${n.id}`);
}

function selectDay(day: number) {
  selectedDay.value = day;
}
</script>

<template>
  <Head title="Kalender" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="page-container-wide pb-8 space-y-8">
        <DiaryHeader
          title="Kalender"
          title-class="text-lg font-semibold"
          header-class="pt-6"
          top-row-class="mb-3"
          bottom-row-class="mb-4"
        />

        <div class="flex items-center justify-center">
          <CalendarSwitchTabs active="day" />
        </div>

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
        <section class="journal-panel">
          <div class="mb-6">
            <p class="journal-subtitle capitalize">{{ selectedMonthLabel }}</p>
            <h2 class="journal-title capitalize">{{ selectedWeekday }}</h2>
          </div>

          <!-- Tänased tööd -->
          <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
              <h3 class="journal-section-title inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[14px]">checklist</span>
                Tänased tööd
              </h3>
            </div>

            <div v-if="selectedTasks.length" class="space-y-3">
              <label
                v-for="t in selectedTasks"
                :key="String(t.id)"
                class="journal-check-row cursor-pointer items-start"
              >
                <input
                  type="checkbox"
                  :checked="!!t.done"
                  class="journal-check accent-primary mt-0.5"
                  @change="toggleTaskDone(t)"
                />
                <span class="min-w-0">
                  <span
                    :class="['journal-row-text', t.done ? 'line-through opacity-70' : '']"
                  >
                    {{ t.title || t.body || 'Ülesanne' }}
                  </span>
                  <span
                    v-if="t.due_at"
                    class="journal-time block mt-0.5"
                  >
                    Tähtaeg: {{ formatDueAt(t.due_at) }}
                  </span>
                </span>
              </label>
            </div>
            <p v-else class="journal-empty">Täna ülesandeid pole.</p>
          </section>

          <!-- Meeldetuletused -->
          <section class="mb-8">
            <h3 class="journal-section-title inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-[14px]">notifications_active</span>
              Meeldetuletused
            </h3>

            <div v-if="selectedReminders.length" class="space-y-3 mt-4">
              <div v-for="n in selectedReminders" :key="String(n.id ?? n.title)" class="journal-reminder">
                <p class="journal-row-text">
                  {{ n.title || 'Meeldetuletus' }}
                  <span v-if="n.reminder_time" class="journal-time block mt-0.5">— {{ n.reminder_time }}</span>
                </p>
              </div>
            </div>

            <p v-else class="journal-empty mt-4">Täna meeldetuletusi ei ole.</p>
          </section>

          <!-- Päevikumärkmed -->
          <section>
            <div class="flex justify-between items-baseline mb-3">
              <h3 class="journal-section-title mb-0 inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[14px]">description</span>
                Päevikumärkmed
              </h3>
              <span class="journal-time journal-time--placeholder" aria-hidden="true" />
            </div>

            <div v-if="selectedDiaryNotes.length" class="space-y-3">
              <div
                v-for="n in selectedDiaryNotes"
                :key="String(n.id ?? n.title)"
                class="journal-note relative flex gap-2 group"
              >
                <div class="min-w-0 flex-1">
                  <p class="journal-note-title">{{ n.title || 'Märge' }}</p>
                  <p v-if="n.body" class="journal-note-body">{{ n.body }}</p>
                  <div v-if="n.media_urls?.length" class="mt-3 flex flex-wrap gap-2">
                    <a
                      v-for="(url, i) in n.media_urls"
                      :key="i"
                      :href="url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="block"
                    >
                      <img
                        :src="url"
                        :alt="`Märkme foto ${i + 1}`"
                        class="w-16 h-16 object-cover rounded-lg border border-border hover:opacity-90 transition"
                      />
                    </a>
                  </div>
                </div>
                <div class="relative shrink-0">
                  <button
                    type="button"
                    class="icon-btn size-9 rounded-full
                           opacity-0 pointer-events-none
                           group-hover:opacity-100 group-hover:pointer-events-auto
                           group-focus-within:opacity-100 group-focus-within:pointer-events-auto
                           transition-opacity"
                    :class="openMenuId === n.id ? 'opacity-100 pointer-events-auto' : ''"
                    aria-label="Valikud"
                    aria-haspopup="true"
                    :aria-expanded="openMenuId === n.id"
                    @click="toggleNoteMenu(n.id)"
                  >
                    <span class="material-symbols-outlined text-xl">more_vert</span>
                  </button>
                  <!-- Click-outside overlay to close menu -->
                  <div
                    v-if="openMenuId === n.id"
                    class="fixed inset-0 z-5"
                    aria-hidden="true"
                    @click="closeNoteMenu"
                  />
                  <div
                    v-if="openMenuId === n.id"
                    class="absolute right-0 top-full z-10 mt-1 min-w-[140px] rounded-lg border border-border bg-card py-1 shadow-lg"
                    role="menu"
                  >
                    <Link
                      :href="`/calendar/notes/${n.id}/edit`"
                      class="menu-item flex w-full items-center gap-2 px-4 py-2 text-left"
                      role="menuitem"
                      @click="closeNoteMenu"
                    >
                      <span class="material-symbols-outlined text-lg">edit</span>
                      Muuda
                    </Link>
                    <button
                      type="button"
                      class="menu-item flex w-full items-center gap-2 px-4 py-2 text-left text-destructive"
                      role="menuitem"
                      @click="deleteNote(n)"
                    >
                      <span class="material-symbols-outlined text-lg">delete</span>
                      Kustuta
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <p v-else class="journal-empty">Sellel päeval pole veel päevikumärkmeid.</p>
          </section>

          <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-stretch sm:justify-between">
            <Link
              href="/calendar/overview"
              class="btn-panel-link mt-0!"
            >
              <span class="material-symbols-outlined text-lg">description</span>
              <span class="font-semibold text-sm">Vaata kõiki märkmeid</span>
              <span class="material-symbols-outlined text-lg ml-auto">chevron_right</span>
            </Link>
          </div>
        </section>
      </div>

      <FloatingPlusButton aria-label="Lisa märge" :size-px="52" :icon-size-px="30" @click="onAdd" />

      <BottomNav active="calendar" />
    </div>
  </AppLayout>
</template>
