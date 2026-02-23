<script setup lang="ts">

import { useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: "update:open", v: boolean): void;
  (e: "created"): void;
}>();

const close = () => emit("update:open", false);

const form = useForm<{
  name: string;
  image: File | null;
}>({
  name: "",
  image: null,
});

const nameInputRef = ref<HTMLInputElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const previewUrl = ref<string | null>(null);
const isDragging = ref(false);

const hasImage = computed(() => !!form.image);

const revokePreview = () => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = null;
};

const setFile = (file: File | null) => {
  revokePreview();
  form.image = file;
  if (file) previewUrl.value = URL.createObjectURL(file);
};

const openPicker = () => fileInputRef.value?.click();

const onFileChange = (e: Event) => {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;

  if (file && !file.type.startsWith("image/")) return;

  setFile(file);
  input.value = ""; // sama faili uuesti valides käivituks change
};

const onDrop = (e: DragEvent) => {
  isDragging.value = false;
  const file = e.dataTransfer?.files?.[0] ?? null;
  if (!file) return;
  if (!file.type.startsWith("image/")) return;

  setFile(file);
};

const reset = () => {
  form.reset();
  form.clearErrors();
  revokePreview();
  isDragging.value = false;
};

watch(
  () => props.open,
  async (val) => {
    if (val) {
      document.body.style.overflow = "hidden";
      await nextTick();
      nameInputRef.value?.focus();
    } else {
      document.body.style.overflow = "";
      reset();
    }
  },
  { immediate: true }
);

const submit = () => {
  form.post("/plants/categories", {
    forceFormData: true,
    onSuccess: () => {
      emit("created");
      close();
    },
  });
};

onBeforeUnmount(() => {
  document.body.style.overflow = "";
  revokePreview();
});
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="props.open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        aria-modal="true"
        role="dialog"
      >
        <!-- overlay (SAMA MIS SEARCHMODAL) -->
        <button
          type="button"
          class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
          aria-label="Sulge taustal"
          @click="close"
        />

        <!-- modal card (SAMA MIS SEARCHMODAL) -->
        <div class="relative w-full max-w-lg rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5">
          <!-- väike ornament (nagu searchil) -->
          <div class="pointer-events-none absolute -top-3 -left-3 opacity-20">
            <div class="h-10 w-10 rounded-full bg-[#E6E2D5]" />
          </div>

          <div class="p-5 sm:p-6">
            <!-- header -->
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-[#2E2E2E]">Lisa uus kategooria</h3>
                <p class="mt-1 text-sm text-[#2E2E2E]/70">
                  Lisa nimi ja vali foto.
                </p>
              </div>

              <button
                type="button"
                class="rounded-full p-2 text-[#2E2E2E]/60 hover:bg-black/5 hover:text-[#2E2E2E]"
                @click="close"
                aria-label="Sulge"
              >
                ✕
              </button>
            </div>

            <!-- KATEGOORIA NIMI -->
            <div class="mt-5">
              <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">
                Kategooria nimi
              </label>

              <input
                ref="nameInputRef"
                v-model="form.name"
                type="text"
                placeholder="nt: Maitsetaimed, Roosiaed…"
                class="mt-3 w-full rounded-2xl border border-black/10 bg-white px-4 py-3 text-[#2E2E2E] shadow-sm outline-none focus:border-[#6B8C68] focus:ring-2 focus:ring-[#6B8C68]/20"
                @keydown.enter.prevent="submit"
              />

              <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">
                {{ form.errors.name }}
              </p>
            </div>

            <!-- PILT -->
            <div class="mt-6">
              <label class="text-sm font-semibold tracking-widest text-[#2E2E2E]/70 uppercase">
                Vali esindusfoto
              </label>

              <input
                ref="fileInputRef"
                type="file"
                accept="image/*"
                class="hidden"
                @change="onFileChange"
              />

              <!-- Dropzone -->
              <button
                type="button"
                class="mt-3 w-full overflow-hidden rounded-2xl border-2 border-dashed px-6 py-8 text-center transition"
                :class="isDragging ? 'border-[#6B8C68] bg-[#6B8C68]/10' : 'border-black/10 bg-white/50 hover:bg-white/70'"
                @click="openPicker"
                @dragenter.prevent="isDragging = true"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
              >
                <template v-if="!hasImage">
                  <div class="flex flex-col items-center gap-3 text-[#2E2E2E]/60">
                    <span class="material-symbols-outlined text-5xl text-[#6B8C68]">add_a_photo</span>
                    <span class="text-base">Lohistage foto siia või klõpsake</span>
                    <span class="text-xs text-[#2E2E2E]/40">PNG, JPG, WEBP</span>
                  </div>
                </template>

                <template v-else>
                  <div class="flex flex-col items-center gap-4">
                    <div class="w-full aspect-[16/10] overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm">
                      <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Eelvaade"
                        class="h-full w-full object-cover"
                      />
                    </div>

                    <div class="flex flex-wrap justify-center gap-2">
                      <button
                        type="button"
                        class="rounded-2xl border border-black/10 bg-white px-4 py-2 text-sm text-[#2E2E2E] shadow-sm hover:bg-black/5"
                        @click.stop="openPicker"
                      >
                        Vaheta
                      </button>

                      <button
                        type="button"
                        class="rounded-2xl border border-black/10 bg-white px-4 py-2 text-sm text-[#2E2E2E] shadow-sm hover:bg-black/5"
                        @click.stop="setFile(null)"
                      >
                        Eemalda
                      </button>
                    </div>
                  </div>
                </template>
              </button>

              <p v-if="form.errors.image" class="mt-2 text-sm text-red-600">
                {{ form.errors.image }}
              </p>
            </div>

            <!-- progress -->
            <div v-if="form.progress" class="mt-5">
              <div class="h-2 w-full overflow-hidden rounded-full bg-black/10">
                <div class="h-2 rounded-full bg-[#6B8C68]" :style="{ width: `${form.progress.percentage}%` }" />
              </div>
              <p class="mt-2 text-xs text-[#2E2E2E]/60">
                Üleslaadimine: {{ form.progress.percentage }}%
              </p>
            </div>

            <!-- actions -->
            <div class="mt-6 flex flex-col gap-3">
              <button
                type="button"
                class="rounded-2xl px-4 py-3 font-medium shadow-sm transition disabled:opacity-50"
                :class="form.name.trim().length ? 'bg-[#6B8C68] text-white hover:bg-[#4F6A52]' : 'bg-black/10 text-[#2E2E2E]'"
                :disabled="!form.name.trim().length || form.processing"
                @click="submit"
              >
                {{ form.processing ? "Salvestan..." : "Salvesta kategooria" }}
              </button>

              <button
                type="button"
                class="text-sm text-[#2E2E2E]/60 hover:text-[#2E2E2E]"
                :disabled="form.processing"
                @click="close"
              >
                Tühista
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>