<!-- resources/js/Pages/Calendar/NoteForm.vue -->
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type ReminderPreset = 'none' | 'same_day_morning' | 'day_before_evening' | 'custom';

const props = defineProps<{
  note?: {
    id: number;
    note_date: string;
    title: string | null;
    body: string;
    media_urls?: string[];
    due_date?: string | null;
    due_time?: string | null;
    bed_id?: number | null;
    plant_id?: number | null;
  };
  beds?: { id: number; name: string; location?: string | null }[];
  plants?: { id: number; name: string }[];
  editMode?: boolean;
  initialBedId?: number | null;
  initialDate?: string | null;
}>();

const calendarUrl = calendar().url;
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Kalender', href: calendarUrl },
  { title: props.editMode ? 'Muuda märget' : 'Lisa märge', href: props.editMode ? '#' : '/calendar/note-form' },
];

const form = useForm<{
  note_date: string;
  title: string;
  body: string;
  type: 'note';

  due_date: string;
  due_time: string;

  bed_id: number | null;
  plant_id: number | null;

  photos: File[];
}>({
  note_date: props.note?.note_date ?? props.initialDate ?? new Date().toISOString().slice(0, 10),
  title: props.note?.title ?? '',
  body: props.note?.body ?? '',
  type: 'note',

  due_date: props.note?.due_date ?? new Date().toISOString().slice(0, 10),
  due_time: props.note?.due_time ?? '09:00',

  bed_id: props.note?.bed_id ?? props.initialBedId ?? null,
  plant_id: props.note?.plant_id ?? null,

  photos: [],
});

const reminderPreset = ref<ReminderPreset>(
  props.note?.due_date || props.note?.due_time ? 'custom' : 'none',
);

const reminderOptions: { value: ReminderPreset; label: string }[] = [
  { value: 'none', label: 'Ei soovi meeldetuletust' },
  { value: 'same_day_morning', label: 'Sama päeva hommikul (09:00)' },
  { value: 'day_before_evening', label: 'Eelmisel õhtul (18:00)' },
  { value: 'custom', label: 'Määra ise' },
];

const showCustomReminderFields = computed(() => reminderPreset.value === 'custom');

function shiftDays(isoDate: string, delta: number): string {
  const d = new Date(`${isoDate}T00:00:00`);
  d.setDate(d.getDate() + delta);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function applyReminderPreset() {
  if (reminderPreset.value === 'none') {
    form.due_date = '';
    form.due_time = '';
    return;
  }
  if (reminderPreset.value === 'same_day_morning') {
    form.due_date = form.note_date;
    form.due_time = '09:00';
    return;
  }
  if (reminderPreset.value === 'day_before_evening') {
    form.due_date = shiftDays(form.note_date, -1);
    form.due_time = '18:00';
  }
}

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
  applyReminderPreset();
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
    <div class="bg-background text-foreground font-display min-h-screen antialiased">
      <div class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-6 sm:items-center sm:pt-4">
        <button
          type="button"
          class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
          aria-label="Sulge"
          @click="cancel"
        />

        <div class="relative w-full max-w-2xl max-h-[92vh] overflow-hidden rounded-3xl bg-card shadow-xl ring-1 ring-border">
          <div class="max-h-[92vh] overflow-y-auto p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-foreground">{{ props.editMode ? 'Muuda märget' : 'Lisa märge' }}</h3>
                <p class="mt-1 text-sm text-foreground/70">
                  {{ props.editMode ? 'Uuenda märkme sisu.' : 'Lisa märkus või meeldetuletus.' }}
                </p>
              </div>
              <button
                type="button"
                class="rounded-full p-2 text-foreground/60 hover:bg-black/5 hover:text-foreground"
                @click="cancel"
                aria-label="Sulge"
              >
                ✕
              </button>
            </div>

            <form class="mt-5 space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                  <div>
                    <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="note_date">Kuupäev</label>
                    <input id="note_date" v-model="form.note_date" type="date" class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    <p v-if="form.errors.note_date" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.note_date }}
                    </p>
                  </div>

                  <div>
                    <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="title">Pealkiri</label>
                    <div class="relative">
                      <input
                        id="title"
                        v-model="form.title"
                        type="text"
                        placeholder="nt. Väetasin tomatid"
                        class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 pr-12 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
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
                  <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="body">Märkme sisu</label>
                  <div class="relative">
                    <textarea
                      id="body"
                      v-model="form.body"
                      rows="7"
                      placeholder="Kirjelda lähemalt oma tegevusi või tähelepanekuid..."
                      class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 pr-12 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                    <span class="absolute right-4 top-4 material-symbols-outlined text-muted-foreground" aria-hidden="true">
                      edit_note
                    </span>
                  </div>
                  <p v-if="form.errors.body" class="text-red-600 dark:text-red-400 text-xs mt-1">
                    {{ form.errors.body }}
                  </p>
                </div>

                <div>
                  <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="reminder_preset">Meeldetuletus</label>
                  <select
                    id="reminder_preset"
                    v-model="reminderPreset"
                    class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                  >
                    <option
                      v-for="opt in reminderOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </option>
                  </select>
                </div>

                <div v-if="showCustomReminderFields" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="due_date">Meeldetuletuse kuupäev</label>
                    <input
                      id="due_date"
                      v-model="form.due_date"
                      type="date"
                      class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                    <p v-if="form.errors.due_date" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.due_date }}
                    </p>
                  </div>
                  <div>
                    <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="due_time">Meeldetuletus kell</label>
                    <input
                      id="due_time"
                      v-model="form.due_time"
                      type="time"
                      class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                    <p v-if="form.errors.due_time" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.due_time }}
                    </p>
                  </div>
                </div>

                <!-- Photos -->
                <div>
                  <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">Fotod</label>
                  <input
                    type="file"
                    accept="image/*"
                    multiple
                    class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-sm text-foreground shadow-sm outline-none file:mr-3 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-primary file:font-medium"
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
                    <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="bed">Seo peenraga</label>
                    <select id="bed" v-model.number="form.bed_id" class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                      <option :value="null">Ei ole seotud</option>
                      <option
                        v-for="bed in (props.beds ?? [])"
                        :key="bed.id"
                        :value="bed.id"
                      >
                        {{ bed.name }}
                      </option>
                    </select>
                    <p v-if="form.errors.bed_id" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.bed_id }}
                    </p>
                  </div>

                  <div>
                    <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase" for="plant">Seo taimega</label>
                    <select id="plant" v-model.number="form.plant_id" class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                      <option :value="null">Ei ole seotud</option>
                      <option
                        v-for="plant in (props.plants ?? [])"
                        :key="plant.id"
                        :value="plant.id"
                      >
                        {{ plant.name }}
                      </option>
                    </select>
                    <p v-if="form.errors.plant_id" class="text-red-600 dark:text-red-400 text-xs mt-1">
                      {{ form.errors.plant_id }}
                    </p>
                  </div>
                </div>

                <!-- Actions -->
                <div class="sticky bottom-0 mt-6 space-y-3 bg-card pt-4 pb-1">
                  <button type="submit" class="w-full rounded-2xl bg-primary px-4 py-3 font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-60" :disabled="form.processing">
                    {{ props.editMode ? 'Salvesta muudatused' : 'Salvesta märge' }}
                  </button>

                  <button type="button" class="w-full rounded-2xl border border-border bg-background px-4 py-3 font-medium text-foreground/80 transition hover:bg-black/5" @click="cancel">
                    Tühista
                  </button>

                  <p v-if="form.recentlySuccessful" class="text-sm text-primary font-semibold text-center">
                    Salvestatud ✅
                  </p>
                </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
