<!-- resources/js/Pages/Calendar/NoteForm.vue -->
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type NoteType = 'note' | 'task';

const props = defineProps<{
  note?: {
    id: number;
    note_date: string;
    title: string | null;
    body: string;
    media_urls?: string[];
    type?: NoteType;
    due_date?: string | null;
    due_time?: string | null;
  };
  editMode?: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Kalender', href: calendar().url },
  { title: props.editMode ? 'Muuda märget' : 'Lisa märge', href: props.editMode ? '#' : '/calendar/note-form' },
];

const form = useForm<{
  note_date: string;
  title: string;
  body: string;
  type: NoteType;

  due_date: string;
  due_time: string;

  bed_id: number | null;
  plant_id: number | null;

  photos: File[];
}>({
  note_date: props.note?.note_date ?? new Date().toISOString().slice(0, 10),
  title: props.note?.title ?? '',
  body: props.note?.body ?? '',
  type: (props.note?.type === 'task' ? 'task' : 'note') as NoteType,

  due_date: props.note?.due_date ?? new Date().toISOString().slice(0, 10),
  due_time: props.note?.due_time ?? '09:00',

  bed_id: null,
  plant_id: null,

  photos: [],
});

const typeOptions: { value: NoteType; label: string; icon: string }[] = [
  { value: 'note', label: 'Märge', icon: 'edit_note' },
  { value: 'task', label: 'Ülesanne', icon: 'check_circle' },
];

const photoPreviews = ref<string[]>([]);

function onPhotosChange(e: Event) {
  const input = e.target as HTMLInputElement;
  const files = input.files ? Array.from(input.files) : [];
  form.photos = files;
  photoPreviews.value = files.map((f) => URL.createObjectURL(f));
}

function removePhoto(index: number) {
  form.photos = form.photos.filter((_, i) => i !== index);
  if (photoPreviews.value[index]) URL.revokeObjectURL(photoPreviews.value[index]);
  photoPreviews.value = photoPreviews.value.filter((_, i) => i !== index);
}

function submit() {
  const onSuccess = () => {
    form.reset('title', 'body', 'photos');
    photoPreviews.value = [];
    router.visit(`/calendar?month=${new Date(form.note_date).getMonth() + 1}&year=${new Date(form.note_date).getFullYear()}`);
  };
  if (props.editMode && props.note) {
    form.transform((data) => ({ ...data, _method: 'PUT' }));
    form.post(`/calendar/notes/${props.note.id}`, {
      preserveScroll: true,
      forceFormData: true,
      onSuccess,
    });
  } else {
    form.post('/calendar/notes', {
      preserveScroll: true,
      forceFormData: true,
      onSuccess,
    });
  }
}

function cancel() {
  router.visit('/calendar');
}
</script>

<template>
  <Head :title="props.editMode ? 'Muuda märget' : 'Lisa märge'" />

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
                    <h1 class="text-base sm:text-lg font-bold tracking-tight truncate">
                      {{ props.editMode ? 'Muuda märget' : 'Lisa märge' }}
                    </h1>
                    <p class="text-xs text-muted-foreground mt-0.5">
                      {{ props.editMode ? 'Uuenda märkme sisu' : 'Pane kirja tähelepanekud või hooldustegevused 🌱' }}
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
                  <label class="form-label note-label mb-2 block">Mida lisad?</label>
                  <div class="flex gap-2 p-1 rounded-xl bg-muted/60">
                    <button
                      v-for="opt in typeOptions"
                      :key="opt.value"
                      type="button"
                      :class="[
                        'flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-lg text-sm font-medium transition cursor-pointer',
                        form.type === opt.value
                          ? 'bg-primary text-primary-foreground shadow-sm'
                          : 'text-muted-foreground hover:text-foreground',
                      ]"
                      @click="form.type = opt.value"
                    >
                      <span class="material-symbols-outlined text-lg">{{ opt.icon }}</span>
                      {{ opt.label }}
                    </button>
                  </div>
                </div>

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

                <!-- Ülesande tähtaeg ja meeldetuletus (ainult ülesandel) -->
                <div v-if="form.type === 'task'" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label class="form-label note-label" for="due_date">Tähtaeg</label>
                    <input
                      id="due_date"
                      v-model="form.due_date"
                      type="date"
                      class="form-input note-input"
                    />
                    <p v-if="form.errors.due_date" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.due_date }}
                    </p>
                  </div>
                  <div>
                    <label class="form-label note-label" for="due_time">Meeldetuletus kell</label>
                    <input
                      id="due_time"
                      v-model="form.due_time"
                      type="time"
                      class="form-input note-input"
                    />
                    <p v-if="form.errors.due_time" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.due_time }}
                    </p>
                  </div>
                </div>

                <!-- Photos -->
                <div>
                  <label class="form-label note-label">Fotod</label>
                  <input
                    type="file"
                    accept="image/*"
                    multiple
                    class="form-input note-input text-sm file:mr-3 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-primary file:font-medium"
                    @change="onPhotosChange"
                  />
                  <p v-if="form.errors.photos" class="text-red-600 dark:text-red-400 text-xs mt-1">
                    {{ form.errors.photos }}
                  </p>
                  <div v-if="props.note?.media_urls?.length" class="mt-3 flex flex-wrap gap-2">
                    <img
                      v-for="(url, i) in props.note.media_urls"
                      :key="'existing-' + i"
                      :src="url"
                      :alt="`Olemasolev foto ${i + 1}`"
                      class="w-20 h-20 object-cover rounded-lg border border-border"
                    />
                  </div>
                  <div v-if="photoPreviews.length" class="mt-3 flex flex-wrap gap-2">
                    <div
                      v-for="(url, i) in photoPreviews"
                      :key="'new-' + i"
                      class="relative group"
                    >
                      <img
                        :src="url"
                        :alt="`Uus foto ${i + 1}`"
                        class="w-20 h-20 object-cover rounded-lg border border-border"
                      />
                      <button
                        type="button"
                        class="absolute -top-1.5 -right-1.5 size-6 rounded-full bg-destructive text-destructive-foreground flex items-center justify-center shadow opacity-90 group-hover:opacity-100"
                        aria-label="Eemalda foto"
                        @click="removePhoto(i)"
                      >
                        <span class="material-symbols-outlined text-sm">close</span>
                      </button>
                    </div>
                  </div>
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

                <!-- Actions -->
                <div class="pt-2 space-y-3">
                  <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    {{ props.editMode ? 'Salvesta muudatused' : 'Salvesta märge' }}
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
