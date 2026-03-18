<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

type Plant = {
  id: number;
  name: string;
  subtitle?: string;
  image_url?: string;
  notes?: string;
  tags?: string[];
  watering_in_days?: string | null;
  fertilizing_frequency?: string | null;
  next_fertilizing_label?: string | null;
  category_slug?: string;
};

const props = withDefaults(
  defineProps<{
    plant: Plant;
    markingWatered?: boolean;
    justWatered?: boolean;
    backUrl?: string | null;
  }>(),
  {
    markingWatered: false,
    justWatered: false,
    backUrl: null,
  }
);

const emit = defineEmits<{
  (e: "back"): void;
  (e: "edit-notes"): void;
  (e: "mark-watered"): void;
}>();

const fallbackImage = "https://picsum.photos/900/1200";

function goBack() {
  if (props.plant.category_slug) {
    router.visit(`/plants/category/${props.plant.category_slug}`);
  } else {
    router.visit("/plants");
  }
}

const hasWateringInfo = computed(() => !!props.plant.watering_in_days?.trim());

const hasFertilizingInfo = computed(() => {
  return !!props.plant.fertilizing_frequency || !!props.plant.next_fertilizing_label;
});

const wateringText = computed(() => {
  return props.plant.watering_in_days || "";
});

const wateringDueSoon = computed(() => {
  const d = props.plant.watering_in_days;
  return typeof d === "number" && d <= 2;
});

const menuOpen = ref(false);
const deleteOpen = ref(false);
const deleting = ref(false);

const openDelete = () => {
  menuOpen.value = false;
  deleteOpen.value = true;
};

const closeDelete = () => {
  deleteOpen.value = false;
  deleting.value = false;
};

const editPlant = () => {
  menuOpen.value = false;

  const url = props.backUrl
    ? `/plants/${props.plant.id}/edit?back=${encodeURIComponent(props.backUrl)}`
    : `/plants/${props.plant.id}/edit`;

  router.visit(url);
};

const doDelete = () => {
  if (deleting.value) return;
  deleting.value = true;

  router.delete(`/plants/${props.plant.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      closeDelete();
      goBack();
    },
    onFinish: () => {
      deleting.value = false;
    },
  });
};

const onDocClick = (e: MouseEvent) => {
  if (!menuOpen.value) return;
  const t = e.target as HTMLElement | null;
  if (t?.closest?.("[data-plant-menu]")) return;
  menuOpen.value = false;
};

onMounted(() => document.addEventListener("click", onDocClick));
onBeforeUnmount(() => document.removeEventListener("click", onDocClick));
</script>

<style scoped>
.material-symbols-outlined {
  font-variation-settings: "FILL" 0, "wght" 300, "GRAD" 0, "opsz" 24;
}

.matte-overlay {
  background: linear-gradient(
    to bottom,
    rgba(250, 248, 244, 0) 0%,
    rgba(250, 248, 244, 0.1) 70%,
    rgba(250, 248, 244, 1) 100%
  );
}

:global(.dark) .matte-overlay {
  background: linear-gradient(
    to bottom,
    rgba(36, 42, 34, 0) 0%,
    rgba(36, 42, 34, 0.1) 70%,
    rgba(36, 42, 34, 1) 100%
  );
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

<template>
  <div
    class="bg-background-light dark:bg-background-dark font-display text-[#141514] dark:text-gray-100 antialiased min-h-screen"
  >
    <div class="relative w-full flex flex-col pt-20">
      <!-- Top App Bar -->
      <div class="fixed top-0 left-0 right-0 z-50">
        <div class="w-full flex items-center justify-between px-4 py-3 md:px-6">
          <button
            type="button"
            class="bg-white/60 dark:bg-black/20 backdrop-blur-md rounded-full p-2 flex items-center justify-center transition-colors"
            @click="goBack"
            aria-label="Tagasi"
          >
            <span class="material-symbols-outlined">arrow_back</span>
          </button>

          <div class="relative" data-plant-menu>
            <button
              type="button"
              class="bg-white/60 dark:bg-black/20 backdrop-blur-md rounded-full p-2 flex items-center justify-center transition-colors"
              @click.stop="menuOpen = !menuOpen"
              aria-label="Menüü"
            >
              <span class="material-symbols-outlined">more_vert</span>
            </button>

            <div
              v-if="menuOpen"
              class="absolute right-0 top-12 z-50 w-44 overflow-hidden rounded-2xl border border-black/10 bg-[#FAF8F4] shadow-xl ring-1 ring-black/5"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-[#2E2E2E] hover:bg-black/5"
                @click="editPlant"
              >
                <span class="material-symbols-outlined text-[20px] text-[#6B8C68]">edit</span>
                Muuda taime
              </button>

              <button
                type="button"
                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-[#2E2E2E] hover:bg-black/5"
                @click="openDelete"
              >
                <span class="material-symbols-outlined text-[20px] text-red-600">delete</span>
                Kustuta
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- HERO -->
      <div class="w-full px-4 md:px-0">
        <div class="overflow-hidden rounded-3xl md:mx-auto md:max-w-4xl">
          <div class="relative h-[42vh] w-full md:h-[46vh] lg:h-[420px]">
            <img
              :src="props.plant.image_url || fallbackImage"
              alt="Taime pilt"
              class="absolute inset-0 h-full w-full object-contain"
              loading="lazy"
            />
            <div class="absolute inset-0 matte-overlay"></div>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="relative z-10 -mt-12 px-6 md:-mt-16 md:px-12">
        <div class="mb-8 md:mb-10">
          <h1
            class="mb-1 font-serif text-4xl italic tracking-tight text-[#2d3a2a] dark:text-primary md:text-5xl"
          >
            {{ props.plant.subtitle }}
          </h1>
        </div>

        <div class="flex flex-col gap-8 md:grid md:grid-cols-2 md:items-start md:gap-10">
          <!-- LEFT = MÄRKMED -->
          <div class="md:col-start-1">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="text-lg font-bold tracking-tight">Märkmed</h3>
              <button
                type="button"
                class="text-sm font-semibold text-primary"
                @click="emit('edit-notes')"
              >
                Muuda
              </button>
            </div>

            <div
              class="rounded-2xl border border-[#e6e2d5]/50 bg-white/50 p-6 dark:border-white/5 dark:bg-surface-dark/40"
            >
              <p class="font-body leading-relaxed text-[#4a524a] dark:text-gray-300">
                {{ props.plant.notes || "Märkmeid veel pole." }}
              </p>

              <div v-if="props.plant.tags?.length" class="mt-4 flex flex-wrap gap-2">
                <span
                  v-for="tag in props.plant.tags"
                  :key="tag"
                  class="rounded-full bg-primary/5 px-3 py-1 text-[11px] font-bold uppercase tracking-tighter text-primary dark:bg-primary/10"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>

          <!-- RIGHT = KASTMINE + VÄETAMINE -->
          <div class="flex flex-col gap-4 md:col-start-2">
            <!-- Watering Card -->
            <div
              v-if="hasWateringInfo"
              class="rounded-2xl border border-[#e6e2d5] bg-surface-light p-5 shadow-sm dark:border-white/5 dark:bg-surface-dark"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <div class="rounded-xl bg-primary/10 p-3 text-primary dark:bg-primary/20">
                    <span class="material-symbols-outlined">opacity</span>
                  </div>

                  <div class="flex flex-col">
                    <span
                      class="mb-0.5 text-xs font-bold uppercase tracking-wider text-gray-400"
                    >
                      Kastmine
                    </span>
                    <span class="font-body text-base font-medium leading-tight">
                      {{ wateringText }}
                    </span>
                  </div>
                </div>

                <div v-if="wateringDueSoon" class="relative flex h-3 w-3 shrink-0">
                  <span
                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-20"
                  ></span>
                  <span class="relative inline-flex h-3 w-3 rounded-full bg-primary"></span>
                </div>

                <div v-else class="shrink-0 text-[#717a71]">
                  <span class="material-symbols-outlined text-[20px]">check_circle</span>
                </div>
              </div>
            </div>

            <!-- Fertilizing Card -->
            <div
              v-if="hasFertilizingInfo"
              class="rounded-2xl border border-[#e6e2d5] bg-surface-light p-5 shadow-sm dark:border-white/5 dark:bg-surface-dark"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <div class="rounded-xl bg-primary/10 p-3 text-primary dark:bg-primary/20">
                    <span class="material-symbols-outlined">potted_plant</span>
                  </div>

                  <div class="flex flex-col">
                    <span
                      class="mb-0.5 text-xs font-bold uppercase tracking-wider text-gray-400"
                    >
                      Väetamine
                    </span>

                    <span class="font-body text-base font-medium leading-tight">
                      <template v-if="props.plant.fertilizing_frequency">
                        {{ props.plant.fertilizing_frequency }}
                      </template>

                      <span
                        v-if="props.plant.next_fertilizing_label"
                        class="ml-1 text-sm text-[#717a71]"
                      >
                        (Järgmine: {{ props.plant.next_fertilizing_label }})
                      </span>
                    </span>
                  </div>
                </div>

                <div class="shrink-0 text-[#717a71]">
                  <span class="material-symbols-outlined text-[20px]">calendar_today</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CTA -->
        <div class="mt-10 pb-10">
          <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-5 text-lg font-bold text-white shadow-lg shadow-primary/20 transition-all active:scale-[0.98] hover:bg-[#5a8056] disabled:opacity-50"
            :disabled="props.markingWatered"
            @click="emit('mark-watered')"
          >
            <span class="material-symbols-outlined">water_drop</span>
            <span v-if="props.markingWatered">Salvestan…</span>
            <span v-else-if="props.justWatered">Kastetud</span>
            <span v-else>Märgi kastetuks</span>
          </button>
        </div>
      </div>
    </div>

    <!-- DELETE CONFIRM MODAL -->
    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="deleteOpen"
          class="fixed inset-0 z-[70] flex items-center justify-center p-4"
          aria-modal="true"
          role="dialog"
        >
          <button
            type="button"
            class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
            aria-label="Sulge taustal"
            @click="closeDelete"
          />

          <div class="relative w-full max-w-sm rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5">
            <div class="pointer-events-none absolute -top-3 -left-3 opacity-20">
              <div class="h-10 w-10 rounded-full bg-[#E6E2D5]" />
            </div>

            <div class="p-5 sm:p-6">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="text-lg font-semibold text-[#2E2E2E]">Kustuta taim?</h3>
                  <p class="mt-1 text-sm text-[#2E2E2E]/70">
                    {{ props.plant.name }} eemaldatakse jäädavalt.
                  </p>
                </div>

                <button
                  type="button"
                  class="rounded-full p-2 text-[#2E2E2E]/60 hover:bg-black/5 hover:text-[#2E2E2E]"
                  aria-label="Sulge"
                  @click="closeDelete"
                >
                  ✕
                </button>
              </div>

              <div class="mt-6 flex flex-col gap-3">
                <button
                  type="button"
                  class="rounded-2xl bg-red-600 px-4 py-3 font-medium text-white shadow-sm transition hover:bg-red-700 disabled:opacity-50"
                  :disabled="deleting"
                  @click="doDelete"
                >
                  {{ deleting ? "Kustutan..." : "Jah, kustuta" }}
                </button>

                <button
                  type="button"
                  class="text-sm text-[#2E2E2E]/60 hover:text-[#2E2E2E]"
                  :disabled="deleting"
                  @click="closeDelete"
                >
                  Tühista
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>