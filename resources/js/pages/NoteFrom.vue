<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'

const form = useForm({
  note_date: new Date().toISOString().slice(0, 10),
  title: '',
  body: '',
})

function submit() {
  form.post('/calendar/notes', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('title', 'body')
      router.visit('/calendar')
    },
  })
}

function cancel() {
  router.visit('/calendar')
}
</script>

<template>
  <div class="min-h-screen bg-background-light dark:bg-background-dark">
    <div class="min-h-screen p-4 flex items-center justify-center">
      <div class="w-full max-w-sm card-glass p-4">
        <!-- Top bar -->
        <div class="flex items-center justify-between mb-3">
          <button type="button" class="icon-btn" @click="cancel" aria-label="Sulge">
            <span class="material-symbols-outlined">close</span>
          </button>

          <h1 class="text-[#141514] dark:text-white text-lg font-bold tracking-tight">
            Lisa märge
          </h1>

          <button type="button" class="icon-btn-solid" @click="submit" :disabled="form.processing" aria-label="Salvesta">
            <span class="material-symbols-outlined">check</span>
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <div>
            <label class="form-label">Kuupäev</label>
            <input v-model="form.note_date" type="date" class="input-base" />
            <p v-if="form.errors.note_date" class="text-red-600 text-xs mt-1">
              {{ form.errors.note_date }}
            </p>
          </div>

          <div>
            <label class="form-label">Pealkiri (valikuline)</label>
            <div class="relative">
              <input v-model="form.title" type="text" placeholder="nt. Väetasin tomatid" class="input-base pr-12" />
              <span class="absolute right-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/40">
                label
              </span>
            </div>
            <p v-if="form.errors.title" class="text-red-600 text-xs mt-1">
              {{ form.errors.title }}
            </p>
          </div>

          <div>
            <label class="form-label">Märge</label>
            <div class="relative">
              <textarea v-model="form.body" rows="5" placeholder="Kirjuta siia…" class="textarea-base pr-12" />
              <span class="absolute right-4 top-4 material-symbols-outlined text-primary/40">
                edit_note
              </span>
            </div>
            <p v-if="form.errors.body" class="text-red-600 text-xs mt-1">
              {{ form.errors.body }}
            </p>
          </div>

          <button type="submit" class="btn-primary" :disabled="form.processing">
            Salvesta märge
          </button>

          <button type="button" class="btn-ghost" @click="cancel">
            Tühista
          </button>

          <p v-if="form.recentlySuccessful" class="text-sm text-primary font-semibold">
            Salvestatud ✅
          </p>
        </form>
      </div>
    </div>
  </div>
</template>