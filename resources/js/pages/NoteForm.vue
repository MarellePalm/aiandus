<!-- resources/js/Pages/Calendar/NoteForm.vue -->
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Kalender', href: calendar().url },
  { title: 'Lisa märge', href: '/calendar/note-form' },
];

const form = useForm({
  note_date: new Date().toISOString().slice(0, 10),
  title: '',
  body: '',
});

function submit() {
  form.post('/calendar/notes', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('title', 'body');
      router.visit('/calendar');
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
      <div class="page-container">
        <div class="py-8 sm:py-10">
          <div class="w-full">
            <div class="card overflow-hidden">
              <!-- Top bar -->
              <div class="sticky top-0 z-10 bg-card/90 backdrop-blur border-b border-border px-5 sm:px-6 py-4">
                <div class="flex items-center justify-between gap-3">
                  <button type="button" class="icon-btn" @click="cancel" aria-label="Tagasi kalendrisse">
                    <span class="material-symbols-outlined">close</span>
                  </button>

                  <div class="min-w-0 text-center">
                    <h1 class="text-base sm:text-lg font-bold tracking-tight truncate">Lisa märge</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">
                      Salvesta tähelepanekud konkreetse päeva kohta
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
                <div>
                  <label class="form-label">Kuupäev</label>
                  <input v-model="form.note_date" type="date" class="form-input" />
                  <p v-if="form.errors.note_date" class="text-red-600 dark:text-red-400 text-xs mt-1">
                    {{ form.errors.note_date }}
                  </p>
                </div>

                <div>
                  <label class="form-label">Pealkiri</label>
                  <div class="relative">
                    <input
                      v-model="form.title"
                      type="text"
                      placeholder="nt. Väetasin tomatid"
                      class="form-input pr-12"
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

                <div>
                  <label class="form-label">Märge</label>
                  <div class="relative">
                    <textarea
                      v-model="form.body"
                      rows="7"
                      placeholder="Kirjuta siia…"
                      class="form-textarea pr-12"
                    />
                    <span class="absolute right-4 top-4 material-symbols-outlined text-muted-foreground" aria-hidden="true">
                      edit_note
                    </span>
                  </div>
                  <p v-if="form.errors.body" class="text-red-600 dark:text-red-400 text-xs mt-1">
                    {{ form.errors.body }}
                  </p>
                </div>

                <div class="pt-2 space-y-3">
                  <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    Salvesta märge
                  </button>

                  <button type="button" class="btn-ghost" @click="cancel">
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
