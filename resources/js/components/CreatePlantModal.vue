<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";

type Category = {
  id: number;
  name: string;
};

const props = defineProps<{
  open: boolean;
  categories: Category[];
  initialCategoryId?: number | null;
}>();

const emit = defineEmits<{
  (e: "update:open", v: boolean): void;
  (e: "created"): void;
}>();

const close = () => emit("update:open", false);

type PlantForm = {
  category_id: number | null;
  subtitle: string;
  planted_at: string;
  image: File | null;
};

const form = useForm<PlantForm>({
  category_id: props.initialCategoryId ?? (props.categories?.[0]?.id ?? null),
  subtitle: "",
  planted_at: "",
  image: null,
});

/** --- PILT (nagu CreateCategoryModal.vue) --- */
const fileInputRef = ref<HTMLInputElement | null>(null);
const categorySelectRef = ref<HTMLSelectElement | null>(null);

const previewUrl = ref<string | null>(null);
const isDragging = ref(false);

const revokePreview = () => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = null;
};

const setFile = (file: File | null) => {
  revokePreview();
  form.image = file;
  form.clearErrors("image");
  if (file) previewUrl.value = URL.createObjectURL(file);
};

const openPicker = () => fileInputRef.value?.click();

const onFileChange = (e: Event) => {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;

  if (file && !file.type.startsWith("image/")) return;

  setFile(file);
  input.value = "";
};

const onDrop = (e: DragEvent) => {
  isDragging.value = false;
  const file = e.dataTransfer?.files?.[0] ?? null;
  if (!file) return;
  if (!file.type.startsWith("image/")) return;

  setFile(file);
};

const hasImage = computed(() => !!form.image);

const reset = () => {
  form.reset();
  form.clearErrors();
  revokePreview();
  isDragging.value = false;

  // pane default kategooria tagasi
  form.category_id = props.initialCategoryId ?? (props.categories?.[0]?.id ?? null);
};

watch(
  () => props.open,
  async (val) => {
    if (val) {
      document.body.style.overflow = "hidden";
      await nextTick();
      categorySelectRef.value?.focus();
    } else {
      document.body.style.overflow = "";
      reset();
    }
  },
  { immediate: true }
);

function submit() {
  form.post("/plants", {
    forceFormData: true,
    onSuccess: () => {
      emit("created");
      close();
    },
  });
}

function goBack() {
  // modalis = sulge
  close();
}

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
        <!-- overlay -->
        <button
          type="button"
          class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
          aria-label="Sulge taustal"
          @click="close"
        />

        <!-- MODAL CARD -->
        <div class="relative w-full max-w-md rounded-[2.75rem] bg-background-light dark:bg-background-dark shadow-xl ring-1 ring-black/5 overflow-hidden">
          <!-- Phone Form Factor Container (Create.vue vibe) -->
          <div class="font-display text-charcoal dark:text-gray-100">
            <!-- TopAppBar -->
            <header class="flex items-center px-6 pt-6 pb-4 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-md sticky top-0 z-10">
              <button
                type="button"
                class="flex items-center justify-center size-10 rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                @click="goBack"
              >
                <span class="material-symbols-outlined text-charcoal dark:text-white">arrow_back_ios_new</span>
              </button>

              <h1 class="flex-1 text-center text-lg font-bold tracking-tight text-charcoal dark:text-white pr-10">
                Lisa Taim
              </h1>
            </header>

            <main class="px-6 pb-6 flex flex-col gap-6">
              <!-- FOTO: sama loogika nagu kategoorias -->
              <div class="mt-2">
                <input
                  ref="fileInputRef"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="onFileChange"
                />

                <button
                  type="button"
                  class="group relative flex flex-col items-center justify-center aspect-square w-full rounded-[3rem] border-2 border-dashed border-[#dfe2df] dark:border-gray-700 bg-white/50 dark:bg-white/5 transition-all hover:border-primary/50 overflow-hidden ios-shadow text-left"
                  :class="isDragging ? 'border-primary/60 bg-primary/5' : ''"
                  @click="openPicker"
                  @dragenter.prevent="isDragging = true"
                  @dragover.prevent="isDragging = true"
                  @dragleave.prevent="isDragging = false"
                  @drop.prevent="onDrop"
                >
                  <template v-if="!hasImage">
                    <div class="flex flex-col items-center gap-4 p-8 text-center">
                      <div class="size-16 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-3xl!">add_a_photo</span>
                      </div>
                      <div class="flex flex-col gap-1">
                        <p class="text-charcoal dark:text-white text-lg font-bold leading-tight">Lisa foto</p>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-normal">
                          Lohista siia või klõpsa
                        </p>
                      </div>

                      <div
                        class="mt-2 flex min-w-30 items-center justify-center rounded-full h-11 px-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-charcoal dark:text-white text-sm font-bold shadow-sm"
                      >
                        Vali fail
                      </div>
                    </div>
                  </template>

                  <template v-else>
                    <div class="w-full h-full relative">
                      <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Eelvaade"
                        class="absolute inset-0 h-full w-full object-cover"
                      />
                      <div class="absolute inset-0 bg-black/20"></div>

                      <div class="absolute bottom-4 left-4 right-4 flex flex-col gap-3">
                        <div class="flex gap-2">
                          <button
                            type="button"
                            class="flex-1 rounded-full h-11 px-5 bg-white/90 dark:bg-gray-800/90 border border-white/30 text-charcoal dark:text-white text-sm font-bold shadow-sm active:scale-95 transition-transform"
                            @click.stop="openPicker"
                          >
                            Vaheta
                          </button>

                          <button
                            type="button"
                            class="flex-1 rounded-full h-11 px-5 bg-white/90 dark:bg-gray-800/90 border border-white/30 text-charcoal dark:text-white text-sm font-bold shadow-sm active:scale-95 transition-transform"
                            @click.stop="setFile(null)"
                          >
                            Eemalda
                          </button>
                        </div>

                        <p v-if="form.errors.image" class="text-red-200 text-sm px-1">
                          {{ form.errors.image }}
                        </p>
                      </div>
                    </div>
                  </template>
                </button>

                <!-- progress -->
                <div v-if="form.progress" class="mt-4">
                  <div class="h-2 w-full overflow-hidden rounded-full bg-black/10 dark:bg-white/10">
                    <div class="h-2 rounded-full bg-primary" :style="{ width: `${form.progress.percentage}%` }" />
                  </div>
                  <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Üleslaadimine: {{ form.progress.percentage }}%
                  </p>
                </div>
              </div>

              <!-- KATEGOORIA SELECT (Taime nime asemel) -->
              <div class="flex flex-col gap-2">
                <label class="text-charcoal dark:text-gray-300 text-sm font-semibold px-1">
                  Kategooria
                </label>

                <select
                  ref="categorySelectRef"
                  v-model="form.category_id"
                  @change="form.clearErrors('category_id')"
                  class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 ios-shadow focus:ring-2 focus:ring-primary/20 text-charcoal dark:text-white transition-all cursor-pointer"
                >
                  <option :value="null" disabled>Vali kategooria…</option>
                  <option v-for="c in props.categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </option>
                </select>

                <p v-if="form.errors.category_id" class="text-red-600 dark:text-red-400 text-sm px-1">
                  {{ form.errors.category_id }}
                </p>
              </div>

              <!-- Sort (subtitle) -->
              <div class="flex flex-col gap-2">
                <label class="text-charcoal dark:text-gray-300 text-sm font-semibold px-1">Sort</label>
                <input
                  v-model="form.subtitle"
                  @input="form.clearErrors('subtitle')"
                  class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 ios-shadow focus:ring-2 focus:ring-primary/20 placeholder:text-gray-400 text-charcoal dark:text-white transition-all"
                  placeholder="nt. Monstera Deliciosa"
                  type="text"
                />
                <p v-if="form.errors.subtitle" class="text-red-600 dark:text-red-400 text-sm px-1">
                  {{ form.errors.subtitle }}
                </p>
              </div>

              <!-- Istutamise kuupäev -->
              <div class="flex flex-col gap-2">
                <label class="text-charcoal dark:text-gray-300 text-sm font-semibold px-1">Istutamise kuupäev</label>
                <input
                  v-model="form.planted_at"
                  type="date"
                  @change="form.clearErrors('planted_at')"
                  @click="($event.target as HTMLInputElement).showPicker?.()"
                  class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 ios-shadow focus:ring-2 focus:ring-primary/20 text-charcoal dark:text-white transition-all cursor-pointer"
                />
                <p v-if="form.errors.planted_at" class="text-red-600 dark:text-red-400 text-sm px-1">
                  {{ form.errors.planted_at }}
                </p>
              </div>

              <!-- ACTIONS -->
              <div class="pt-2">
                <button
                  type="button"
                  @click="submit"
                  :disabled="form.processing || !form.category_id"
                  class="w-full h-16 bg-primary text-white font-bold text-lg rounded-xl shadow-lg shadow-primary/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  <span class="material-symbols-outlined">potted_plant</span>
                  {{ form.processing ? "Salvestan..." : "Salvesta taim" }}
                </button>

                <button
                  type="button"
                  class="mt-3 w-full text-sm text-charcoal/60 dark:text-gray-300 hover:text-charcoal dark:hover:text-white"
                  :disabled="form.processing"
                  @click="close"
                >
                  Tühista
                </button>
              </div>
            </main>
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
.ios-shadow {
  box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
}
</style>