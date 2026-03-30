<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref } from "vue";

import BackIconButton from "@/components/BackIconButton.vue";

type Plant = {
  id: number;
  subtitle?: string | null;
  notes?: string | null;
  image_url?: string | null;
  watering_frequency?: string | null;
  fertilizing_frequency?: string | null;
};

const props = defineProps<{ plant: Plant }>();

const fileInput = ref<HTMLInputElement | null>(null);
const localPreview = ref<string | null>(null);
const pickedFile = ref<File | null>(null);

const form = useForm({
  subtitle: props.plant.subtitle ?? "",
  watering_frequency: props.plant.watering_frequency ?? "",
  fertilizing_frequency: props.plant.fertilizing_frequency ?? "",
  notes: props.plant.notes ?? "",
  image: null as File | null,
});

const currentImage = computed(() => localPreview.value ?? props.plant.image_url ?? null);

function pickFile() {
  fileInput.value?.click();
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;

  pickedFile.value = file;
  form.image = file;

  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
  localPreview.value = file ? URL.createObjectURL(file) : null;
}

function clearFile() {
  pickedFile.value = null;
  form.image = null;

  if (fileInput.value) fileInput.value.value = "";

  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
  localPreview.value = null;
}

function submit() {
  form.put(`/plants/${props.plant.id}`, {
    forceFormData: true,
    preserveScroll: true,
  });
}

onBeforeUnmount(() => {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
});
</script>

<template>
  <Head title="Muuda taime" />
  <div class="min-h-screen bg-background-light text-forest font-display antialiased">
    <div class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-6 sm:items-center sm:pt-4">
      <Link
        :href="`/plants/${props.plant.id}`"
        class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
        aria-label="Sulge"
      />

      <div class="relative w-full max-w-lg max-h-[92vh] overflow-hidden rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5">
        <div class="max-h-[92vh] overflow-y-auto p-5 sm:p-6">
          <div class="mb-5 flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <BackIconButton :href="`/plants/${props.plant.id}`" aria-label="Tagasi taime vaatesse" />
              <div>
                <h1 class="text-lg font-semibold text-[#2E2E2E]">Muuda taime</h1>
                <p class="mt-1 text-sm text-[#2E2E2E]/70">Muuda sorti, kastmist, väetamist, märkmeid ja pilti.</p>
              </div>
            </div>
            <Link
              :href="`/plants/${props.plant.id}`"
              class="rounded-full p-2 text-[#2E2E2E]/60 hover:bg-black/5 hover:text-[#2E2E2E]"
              aria-label="Sulge"
            >
              ✕
            </Link>
          </div>

          <form class="space-y-5" @submit.prevent="submit">
            <div>
              <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Pilt</label>
              <div class="mt-3 overflow-hidden rounded-2xl border border-black/10 bg-white/70">
                <div v-if="currentImage" class="aspect-[4/3] w-full">
                  <img :src="currentImage" alt="Taime pilt" class="h-full w-full object-contain" />
                </div>
                <div v-else class="flex aspect-[4/3] w-full items-center justify-center text-sm text-[#2E2E2E]/70">
                  Pilti pole
                </div>
              </div>

              <input ref="fileInput" type="file" class="hidden" accept="image/*" @change="onFileChange" />

              <div class="mt-4 flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  class="rounded-2xl border border-black/10 bg-white px-4 py-2 text-sm font-medium text-[#2E2E2E]/80 transition hover:bg-black/5"
                  @click="pickFile"
                >
                  Laadi pilt
                </button>

                <button
                  v-if="pickedFile"
                  type="button"
                  class="rounded-2xl border border-black/10 bg-white px-4 py-2 text-sm font-medium text-[#2E2E2E]/80 transition hover:bg-black/5"
                  @click="clearFile"
                >
                  Tühista valik
                </button>
              </div>

              <div v-if="form.errors.image" class="mt-2 text-sm text-red-600">
                {{ form.errors.image }}
              </div>
            </div>

            <div>
              <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Sort</label>
              <input
                v-model="form.subtitle"
                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                placeholder="Nt. Mehiko minikurk"
              />
              <div v-if="form.errors.subtitle" class="mt-1 text-sm text-red-600">
                {{ form.errors.subtitle }}
              </div>
            </div>

            <div>
              <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Kastmine</label>
              <input
                v-model="form.watering_frequency"
                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                placeholder="Nt. iga nädal"
              />
              <p class="mt-1 text-xs text-[#2E2E2E]/60">
                Näide: iga nädal, 2x nädalas, kui muld on kuiv
              </p>
              <div v-if="form.errors.watering_frequency" class="mt-1 text-sm text-red-600">
                {{ form.errors.watering_frequency }}
              </div>
            </div>

            <div>
              <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Väetamine</label>
              <input
                v-model="form.fertilizing_frequency"
                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                placeholder="Nt. iga 2 nädala tagant"
              />
              <div v-if="form.errors.fertilizing_frequency" class="mt-1 text-sm text-red-600">
                {{ form.errors.fertilizing_frequency }}
              </div>
            </div>

            <div>
              <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">Märkmed</label>
              <textarea
                v-model="form.notes"
                rows="6"
                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                placeholder="Kirjuta siia..."
              />
              <div v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                {{ form.errors.notes }}
              </div>
            </div>

            <div class="sticky bottom-0 mt-6 flex flex-col gap-3 bg-[#FAF8F4] pt-4 pb-1">
              <button
                type="submit"
                class="w-full rounded-2xl bg-[#6B8C68] px-4 py-3 font-medium text-white transition hover:bg-[#4F6A52] disabled:opacity-60"
                :disabled="form.processing"
              >
                Salvesta
              </button>

              <Link
                :href="`/plants/${props.plant.id}`"
                class="w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-center text-sm font-medium text-[#2E2E2E]/80 transition hover:bg-black/5"
              >
                Loobu
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>