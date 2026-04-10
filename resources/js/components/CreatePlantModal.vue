<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
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
  watering_in_days: string;
  fertilizing_frequency: string;
  notes: string;
  image: File | null;
};

const form = useForm<PlantForm>({
  category_id: props.initialCategoryId ?? (props.categories?.[0]?.id ?? null),
  subtitle: "",
  planted_at: "",
  watering_in_days: "",
  fertilizing_frequency: "",
  notes: "",
  image: null,
});

/** --- PILT --- */
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

  form.category_id = props.initialCategoryId ?? (props.categories?.[0]?.id ?? null);
  form.subtitle = "";
  form.planted_at = "";
  form.watering_in_days = "";
  form.fertilizing_frequency = "";
  form.notes = "";
  form.image = null;
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
        <button
          type="button"
          class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
          aria-label="Sulge taustal"
          @click="close"
        />

        <div class="relative w-full max-w-lg rounded-3xl bg-card shadow-xl ring-1 ring-border">
          <div class="pointer-events-none absolute -top-3 -left-3 opacity-20">
            <div class="h-10 w-10 rounded-full bg-muted" />
          </div>

          <div class="max-h-[90vh] overflow-y-auto p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-foreground">Lisa taim</h3>
                <p class="mt-1 text-sm text-foreground/70">
                  Vali kategooria, lisa sort, kuupäev ja soovi korral hooldusinfo ning foto.
                </p>
              </div>

              <button
                type="button"
                class="rounded-full p-2 text-foreground/60 hover:bg-black/5 hover:text-foreground"
                @click="close"
                aria-label="Sulge"
              >
                ✕
              </button>
            </div>

            <main class="mt-5 flex flex-col gap-6">
              <!-- FOTO -->
              <div>
                <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">
                  Foto
                </label>

                <input
                  ref="fileInputRef"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="onFileChange"
                />

                <button
                  type="button"
                  class="mt-3 w-full overflow-hidden rounded-2xl border-2 border-dashed px-6 py-8 text-center transition"
                  :class="isDragging ? 'border-primary bg-primary/10' : 'border-border bg-background hover:bg-muted/50'"
                  @click="openPicker"
                  @dragenter.prevent="isDragging = true"
                  @dragover.prevent="isDragging = true"
                  @dragleave.prevent="isDragging = false"
                  @drop.prevent="onDrop"
                >
                  <template v-if="!hasImage">
                    <div class="flex flex-col items-center gap-3 text-foreground/60">
                      <span class="material-symbols-outlined text-5xl text-primary">add_a_photo</span>
                      <span class="text-base">Lohistage foto siia või klõpsake</span>
                      <span class="text-xs text-foreground/40">PNG, JPG, WEBP</span>
                    </div>
                  </template>

                  <template v-else>
                    <div class="flex flex-col items-center gap-4">
                      <div class="aspect-[16/10] w-full overflow-hidden rounded-2xl border border-border bg-background shadow-sm">
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
                          class="rounded-2xl border border-border bg-background px-4 py-2 text-sm text-foreground shadow-sm hover:bg-black/5"
                          @click.stop="openPicker"
                        >
                          Vaheta
                        </button>

                        <button
                          type="button"
                          class="rounded-2xl border border-border bg-background px-4 py-2 text-sm text-foreground shadow-sm hover:bg-black/5"
                          @click.stop="setFile(null)"
                        >
                          Eemalda
                        </button>
                      </div>

                      <p v-if="form.errors.image" class="text-sm text-red-600">
                        {{ form.errors.image }}
                      </p>
                    </div>
                  </template>
                </button>

                <div v-if="form.progress" class="mt-5">
                  <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div class="h-2 rounded-full bg-primary" :style="{ width: `${form.progress.percentage}%` }" />
                  </div>
                  <p class="mt-2 text-xs text-foreground/60">
                    Üleslaadimine: {{ form.progress.percentage }}%
                  </p>
                </div>
              </div>

              <!-- KATEGOORIA -->
              <div>
                <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">
                  Kategooria
                </label>

                <select
                  ref="categorySelectRef"
                  v-model="form.category_id"
                  @change="form.clearErrors('category_id')"
                  class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                >
                  <option :value="null" disabled>Vali kategooria…</option>
                  <option v-for="c in props.categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </option>
                </select>

                <p v-if="form.errors.category_id" class="mt-2 text-sm text-red-600">
                  {{ form.errors.category_id }}
                </p>
              </div>

              <!-- SORT -->
              <div>
                <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">
                  Sort
                </label>

                <input
                  v-model="form.subtitle"
                  @input="form.clearErrors('subtitle')"
                  class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 placeholder:text-foreground/40"
                  placeholder="nt. Mehiko minikurk"
                  type="text"
                />

                <p v-if="form.errors.subtitle" class="mt-2 text-sm text-red-600">
                  {{ form.errors.subtitle }}
                </p>
              </div>

              <!-- ISTUTAMISE KUUPÄEV -->
              <div>
                <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">
                  Istutamise kuupäev
                </label>

                <input
                  v-model="form.planted_at"
                  type="date"
                  @change="form.clearErrors('planted_at')"
                  @click="($event.target as HTMLInputElement).showPicker?.()"
                  class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />

                <p v-if="form.errors.planted_at" class="mt-2 text-sm text-red-600">
                  {{ form.errors.planted_at }}
                </p>
              </div>

              <!-- KASTMINE -->
              <div>
                <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">
                  Kastmine
                </label>

                <input
                  v-model="form.watering_in_days"
                  type="text"
                  @input="form.clearErrors('watering_in_days')"
                  class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 placeholder:text-foreground/40"
                  placeholder="nt. iga nädal"
                />

                <p class="mt-2 text-xs text-foreground/50">
                  Näide: iga nädal, 2x nädalas, kui muld on kuiv
                </p>

                <p v-if="form.errors.watering_in_days" class="mt-2 text-sm text-red-600">
                  {{ form.errors.watering_in_days }}
                </p>
              </div>

              <!-- VÄETAMINE -->
              <div>
                <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">
                  Väetamine
                </label>

                <input
                  v-model="form.fertilizing_frequency"
                  @input="form.clearErrors('fertilizing_frequency')"
                  class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 placeholder:text-foreground/40"
                  placeholder="nt. iga 2 nädala tagant"
                  type="text"
                />

                <p class="mt-2 text-xs text-foreground/50">
                  Valikuline. Kirjuta siia väetamise sagedus või juhis.
                </p>

                <p v-if="form.errors.fertilizing_frequency" class="mt-2 text-sm text-red-600">
                  {{ form.errors.fertilizing_frequency }}
                </p>
              </div>

              <!-- MÄRKMED -->
              <div>
                <label class="text-sm font-semibold tracking-widest text-foreground/70 uppercase">
                  Märkmed
                </label>

                <textarea
                  v-model="form.notes"
                  rows="4"
                  @input="form.clearErrors('notes')"
                  class="mt-3 w-full rounded-2xl border border-border bg-background px-4 py-3 text-foreground shadow-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 placeholder:text-foreground/40"
                  placeholder="Soovi korral lisa meelespea või lisainfo..."
                />

                <p v-if="form.errors.notes" class="mt-2 text-sm text-red-600">
                  {{ form.errors.notes }}
                </p>
              </div>

              <!-- ACTIONS -->
              <div class="mt-1 flex flex-col gap-3">
                <button
                  type="button"
                  @click="submit"
                  :disabled="form.processing || !form.category_id"
                  class="rounded-2xl px-4 py-3 font-medium shadow-sm transition disabled:opacity-50"
                  :class="form.category_id ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'bg-black/10 text-foreground'"
                >
                  <span class="inline-flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">potted_plant</span>
                    {{ form.processing ? "Salvestan..." : "Salvesta taim" }}
                  </span>
                </button>

                <button
                  type="button"
                  class="text-sm text-foreground/60 hover:text-foreground"
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
</style>