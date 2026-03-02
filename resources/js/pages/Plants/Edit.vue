<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref } from "vue";

type Plant = {
  id: number;
  name?: string | null;
  subtitle?: string | null;
  notes?: string | null;
  tags?: string[] | null;
  image_url?: string | null;
  watering_in_days?: number | null;
  fertilizing_frequency?: string | null;

  bed_id?: number | null;
  position_in_bed?: string | null;
};

const props = defineProps<{
  plant: Plant;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const localPreview = ref<string | null>(null);
const pickedFile = ref<File | null>(null);

const form = useForm({
  name: props.plant.name ?? "",
  subtitle: props.plant.subtitle ?? "",
  notes: props.plant.notes ?? "",
  watering_in_days: props.plant.watering_in_days ?? null,
  fertilizing_frequency: props.plant.fertilizing_frequency ?? "",

  bed_id: props.plant.bed_id ?? null,
  position_in_bed: props.plant.position_in_bed ?? "",

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
  form.post(`/plants/${props.plant.id}`, {
    forceFormData: true,
    preserveScroll: true,
    onBefore: () => {
      (form as any)._method = "put";
    },
  });
}


onBeforeUnmount(() => {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value);
});
</script>

<template>
  <div class="mx-auto w-full max-w-5xl px-4 py-6">
    <div class="mb-5 flex items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold leading-tight">Muuda taime</h1>
        <p class="text-sm opacity-70">Muuda andmeid ja salvesta.</p>
      </div>

    <Link
  :href="`/plants/${plant.id}`"
  class="rounded-xl border px-3 py-2 text-sm hover:bg-black/5"
>
        Tagasi
      </Link>
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
          <div>
            <label class="mb-1 block text-sm font-medium">Nimi</label>
            <input
              v-model="form.name"
              class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
              placeholder="Nt. Kurgid"
            />
            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
              {{ form.errors.name }}
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium">Sort / alampealkiri</label>
            <input
              v-model="form.subtitle"
              class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
              placeholder="Nt. Mehiko minikurk"
            />
            <div v-if="form.errors.subtitle" class="mt-1 text-sm text-red-600">
              {{ form.errors.subtitle }}
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium">Kastmine (päeva)</label>
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
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium">Märkmed</label>
            <textarea
              v-model="form.notes"
              rows="5"
              class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
              placeholder="Kirjuta siia..."
            />
            <div v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
              {{ form.errors.notes }}
            </div>
          </div>

          <!-- Peenar -->
          <div class="rounded-2xl border p-3">
            <div class="text-sm font-semibold mb-2">Peenar</div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium">Bed ID</label>
                <input
                  type="number"
                  v-model="form.bed_id"
                  class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
                  placeholder="Nt. 1"
                  min="1"
                />
                <div v-if="form.errors.bed_id" class="mt-1 text-sm text-red-600">
                  {{ form.errors.bed_id }}
                </div>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium">Asukoht peenras</label>
                <input
                  v-model="form.position_in_bed"
                  class="w-full rounded-xl border px-3 py-2 outline-none focus:ring-2 focus:ring-black/10"
                  placeholder="Nt. vasak äär"
                />
                <div v-if="form.errors.position_in_bed" class="mt-1 text-sm text-red-600">
                  {{ form.errors.position_in_bed }}
                </div>
              </div>
            </div>

            <p class="mt-2 text-xs opacity-70">
              (Kui tahad, teen selle Bed ID asemel rippmenüüks, kui saadad beds listi propsina.)
            </p>
          </div>

          <div class="flex flex-col-reverse gap-3 md:flex-row md:items-center md:justify-end">
            <Link
              :href="`/plants/${plant.id}`"
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