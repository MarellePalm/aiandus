<!-- resources/js/Pages/Calendar/NoteForm.vue -->
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type ReminderFrequency = 'once' | 'daily' | 'weekly' | 'monthly';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Kalender', href: calendar().url },
  { title: 'Lisa märge', href: '/calendar/note-form' },
];

const form = useForm<{
  note_date: string;
  title: string;
  body: string;

  bed_id: number | null;
  plant_id: number | null;

  reminder_enabled: boolean;
  reminder_time: string | null;
  reminder_frequency: ReminderFrequency | null;
}>({
  note_date: new Date().toISOString().slice(0, 10),
  title: '',
  body: '',

  bed_id: null,
  plant_id: null,

  reminder_enabled: true,
  reminder_time: '09:00',
  reminder_frequency: 'once',
});

watch(
  () => form.reminder_enabled,
  (enabled) => {
    if (!enabled) {
      form.reminder_time = null;
      form.reminder_frequency = null;
      return;
    }
    if (!form.reminder_time) form.reminder_time = '09:00';
    if (!form.reminder_frequency) form.reminder_frequency = 'once';
  },
);

function submit() {
  form.post('/calendar/notes', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('title', 'body');
      router.visit(`/calendar?date=${form.note_date}`);
    },
  });
}

function cancel() {
  router.visit('/calendar');
}
</script>

<template>
  <Head title="Lisa märge" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <!-- ✅ Same as CalendarView: wide container -->
      <div class="page-container-wide relative min-h-screen overflow-hidden">
        <!-- Subtle Botanical Illustrations -->
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

        <div class="py-8 sm:py-10">
          <!-- ✅ Remove max-w-md so it can stretch -->
          <div class="w-full">
            <div class="panel note-card overflow-hidden">
              <!-- Top bar -->
              <div class="sticky top-0 z-10 bg-card/80 backdrop-blur border-b border-border px-5 sm:px-6 py-4">
                <div class="flex items-center justify-between gap-3">
                  <button type="button" class="icon-btn" @click="cancel" aria-label="Tagasi kalendrisse">
                    <span class="material-symbols-outlined">close</span>
                  </button>

                  <div class="min-w-0 text-center">
                    <h1 class="text-base sm:text-lg font-bold tracking-tight truncate">Lisa märge</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">
                      Pane kirja tähelepanekud või hooldustegevused 🌱
                    </p>
                  </div>

                  <button
                    type="button"
                    class="icon-btn-primary"
                    @click="submit"
                    :disabled="form.processing"
                    aria-label="Salvesta"
                  >
                    <span class="material-symbols-outlined">check</span>
                  </button>
                </div>
              </div>

              <form class="px-5 sm:px-6 py-6 space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                  <div>
                    <label class="form-label note-label" for="note_date">Kuupäev</label>
                    <input id="note_date" v-model="form.note_date" type="date" class="form-input note-input" />
                    <p v-if="form.errors.note_date" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.note_date }}
                    </p>
                  </div>

                  <div>
                    <label class="form-label note-label" for="title">Pealkiri</label>
                    <div class="relative">
                      <input
                        id="title"
                        v-model="form.title"
                        type="text"
                        placeholder="nt. Väetasin tomatid"
                        class="form-input note-input pr-12"
                        autocomplete="off"
                      />
                      <span
                        class="absolute right-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-muted-foreground"
                        aria-hidden="true"
                      >
                        label
                      </span>
                    </div>
                    <p v-if="form.errors.title" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.title }}
                    </p>
                  </div>
                </div>

                <div>
                  <label class="form-label note-label" for="body">Märkme sisu</label>
                  <div class="relative">
                    <textarea
                      id="body"
                      v-model="form.body"
                      rows="7"
                      placeholder="Kirjelda lähemalt oma tegevusi või tähelepanekuid..."
                      class="form-textarea note-input pr-12"
                    />
                    <span class="absolute right-4 top-4 material-symbols-outlined text-muted-foreground" aria-hidden="true">
                      edit_note
                    </span>
                  </div>
                  <p v-if="form.errors.body" class="text-red-600 dark:text-red-400 text-xs mt-1">
                    {{ form.errors.body }}
                  </p>
                </div>

                <!-- Bed + Plant (no duplicate frequency here) -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label class="form-label note-label" for="bed">Seo peenraga</label>
                    <!-- TODO: replace options with real props later -->
                    <select id="bed" v-model.number="form.bed_id" class="form-input note-input">
                      <option :value="null">Ei ole seotud</option>
                      <option :value="1">Peenar A</option>
                      <option :value="2">Kasvuhoone</option>
                      <option :value="3">Ürdikast</option>
                    </select>
                    <p v-if="form.errors.bed_id" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.bed_id }}
                    </p>
                  </div>

                  <div>
                    <label class="form-label note-label" for="plant">Seo taimega</label>
                    <!-- TODO: replace options with real props later -->
                    <select id="plant" v-model.number="form.plant_id" class="form-input note-input">
                      <option :value="null">Ei ole seotud</option>
                      <option :value="1">Tomat 'Moneymaker'</option>
                      <option :value="2">Basiilik 'Genovese'</option>
                      <option :value="3">Lavendel</option>
                    </select>
                    <p v-if="form.errors.plant_id" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.plant_id }}
                    </p>
                  </div>
                </div>

                <!-- Toggle Reminder -->
                <div class="pt-1">
                  <label class="flex items-center justify-between gap-4 cursor-pointer select-none">
                    <span class="text-sm font-medium text-foreground/90">Lisa meeldetuletus</span>

                    <span class="toggle">
                      <input v-model="form.reminder_enabled" class="toggle__input" type="checkbox" />
                      <span class="toggle__track"></span>
                      <span class="toggle__thumb"></span>
                    </span>
                  </label>
                </div>

                <!-- Conditional Fields -->
                <div v-if="form.reminder_enabled" class="pt-5 border-t border-border space-y-4">
                  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                      <label class="note-sub-label" for="time">Kellaaeg</label>
                      <input
                        id="time"
                        v-model="form.reminder_time"
                        type="time"
                        class="form-input note-input note-input--compact"
                      />
                      <p v-if="form.errors.reminder_time" class="text-red-600 dark:text-red-400 text-xs mt-1">
                        {{ form.errors.reminder_time }}
                      </p>
                    </div>

                    <div>
                      <label class="note-sub-label" for="freq2">Sagedus</label>
                      <select id="freq2" v-model="form.reminder_frequency" class="form-input note-input note-input--compact">
                        <option value="once">Ühekordne</option>
                        <option value="daily">Iga päev</option>
                        <option value="weekly">Kord nädalas</option>
                        <option value="monthly">Kord kuus</option>
                      </select>
                      <p v-if="form.errors.reminder_frequency" class="text-red-600 dark:text-red-400 text-xs mt-1">
                        {{ form.errors.reminder_frequency }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Actions -->
                <div class="pt-2 space-y-3">
                  <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    Salvesta märge
                  </button>

                  <button type="button" class="btn-outline w-full" @click="cancel">
                    Tühista
                  </button>

                  <p v-if="form.recentlySuccessful" class="text-sm text-primary font-semibold text-center">
                    Salvestatud ✅
                  </p>
                </div>
              </form>
            </div>

            <p class="mt-4 text-xs text-muted-foreground text-center hidden sm:block">
              Tipp: pealkiri lühidalt (nt “Kastmine”), detailid märges.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
