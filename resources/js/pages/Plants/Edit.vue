<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref } from "vue";

type Plant = {
  id: number;
  subtitle?: string | null;
  notes?: string | null;
  image_url?: string | null;
  watering_in_days?: number | null;
  fertilizing_frequency?: string | null;
};

const props = defineProps<{ plant: Plant }>();

const fileInput = ref<HTMLInputElement | null>(null);
const localPreview = ref<string | null>(null);
const pickedFile = ref<File | null>(null);

const form = useForm({
  subtitle: props.plant.subtitle ?? "",
  watering_in_days: props.plant.watering_in_days ?? null,
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
  <!-- 2) Ekraan täislaiusega: eemaldas max-w-5xl -->
  <div class="w-full px-4 py-6 lg:px-8">
    <!-- Header: tagasi vasakul noolega -->
    <div class="mb-5 flex items-start justify-between gap-3">
      <div class="flex items-start gap-3">
        <!-- 1) Tagasi nupp vasakul ja noolega -->
        <Link
          :href="`/plants/${props.plant.id}`"
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl border hover:bg-black/5"
          aria-label="Tagasi"
          title="Tagasi"
        >
          <!-- simple arrow-left icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="h-5 w-5"
          >
            <path d="M15 18l-6-6 6-6" />
          </svg>
        </Link>

        <div>
          <h1 class="text-2xl font-semibold leading-tight">Muuda taime</h1>
          <p class="text-sm opacity-70">Muuda sorti, kastmist, väetamist, märkmeid ja pilti.</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Pilt -->
      <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold">Pilt</h2>
          <button
            type="button"
            class="text-sm underline opacity-80 hover:opacity-100"
            @click="pickFile"
          >
            Vali uus pilt
          </button>
        </div>

        <!-- (pilt jääb nagu sul oli; kui tahad, saan hiljem teha "tõe pildi nähtavaks" variandi) -->
        <div class="mt-3 overflow-hidden rounded-2xl border bg-black/5">
          <div v-if="currentImage" class="aspect-[4/3] w-full">
            <img :src="currentImage" alt="Taime pilt" class="h-full w-full object-contain" />
          </div>
          <div v-else class="flex aspect-[4/3] w-full items-center justify-center text-sm opacity-70">
            Pilti pole
          </div>
        </div>

        <input ref="fileInput" type="file" class="hidden" accept="image/*" @change="onFileChange" />

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <button
            type="button"
            class="rounded-xl border px-3 py-2 text-sm hover:bg-black/5"
            @click="pickFile"
          >
            Laadi pilt
          </button>

          <button
            v-if="pickedFile"
            type="button"
            class="rounded-xl border px-3 py-2 text-sm hover:bg-black/5"
            @click="clearFile"
          >
            Tühista valik
          </button>
        </div>

        <div v-if="form.errors.image" class="mt-2 text-sm text-red-600">
          {{ form.errors.image }}
        </div>
      </div>

      <!-- Vorm -->
      <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <h2 class="text-base font-semibold">Andmed</h2>

        <form class="mt-4 space-y-4" @submit.prevent="submit">
          <!-- 1) Sort -->
          <div>
            <label class="mb-1 block text-sm font-medium">Sort</label>
            <input
              v-model="form.subtitle"
              class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
              placeholder="Nt. Mehiko minikurk"
            />
            <div v-if="form.errors.subtitle" class="mt-1 text-sm text-red-600">
              {{ form.errors.subtitle }}
            </div>
          </div>

          <!-- 2) Kastmine -->
          <div>
            <label class="mb-1 block text-sm font-medium">Kastmine (päeva pärast)</label>
            <input
              type="number"
              v-model="form.watering_in_days"
              class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
              placeholder="Nt. 3"
              min="0"
            />
            <div v-if="form.errors.watering_in_days" class="mt-1 text-sm text-red-600">
              {{ form.errors.watering_in_days }}
            </div>
          </div>

          <!-- 3) Väetamine -->
          <div>
            <label class="mb-1 block text-sm font-medium">Väetamine</label>
            <input
              v-model="form.fertilizing_frequency"
              class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
              placeholder="Nt. iga 2 nädala tagant"
            />
            <div v-if="form.errors.fertilizing_frequency" class="mt-1 text-sm text-red-600">
              {{ form.errors.fertilizing_frequency }}
            </div>
          </div>

          <!-- 3) Märkmed ära võta -->
          <div>
            <label class="mb-1 block text-sm font-medium">Märkmed</label>
            <textarea
              v-model="form.notes"
              rows="6"
              class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
              placeholder="Kirjuta siia..."
            />
            <div v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
              {{ form.errors.notes }}
            </div>

            <!-- siia saab hiljem lisada kalender-vaate sidumise -->
            <!-- nt: "Lisa märge kuupäevaga" / "Ava kalender" -->
          </div>

          <div class="flex flex-col-reverse gap-3 md:flex-row md:items-center md:justify-end">
            <Link
              :href="`/plants/${props.plant.id}`"
              class="rounded-xl border px-4 py-2 text-center text-sm hover:bg-black/5"
            >
              Loobu
            </Link>

            <button
              type="submit"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
              :disabled="form.processing"
            >
              Salvesta
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>