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
  watering_in_days?: number;
  fertilizing_frequency?: string | null;
  next_fertilizing_label?: string | null;
};

const props = withDefaults(
  defineProps<{
    plant: Plant;
    markingWatered?: boolean;
    justWatered?: boolean;

    /** Soovi korral saad serverist ette anda, kuhu “tagasi” minna (nt SortView URL). */
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

/** BACK: mitte dashboard, vaid tagasi eelmisele lehele (SortView) */
function goBack() {
  // kui backend annab backUrl, kasuta seda
  if (props.backUrl) return router.visit(props.backUrl);

  // muidu kõige kindlam: tagasi browseri ajaloos (enamasti SortView)
  if (window.history.length > 1) window.history.back();
  else router.visit("/plants");
}

/** Watering */
const wateringText = computed(() => {
  const d = props.plant.watering_in_days;
  if (d === 0) return "Vajab kastmist täna";
  if (d === 1) return "Vajab kastmist 1 päeva pärast";
  if (typeof d === "number") return `Vajab kastmist ${d} päeva pärast`;
  return "Kastmise info puudub";
});

const wateringDueSoon = computed(() => {
  const d = props.plant.watering_in_days;
  return typeof d === "number" && d <= 2;
});

/** MENU (sama stiil mis SortView) + DELETE */
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
  router.visit(`/plants/${props.plant.id}/edit`);
};

const doDelete = () => {
  if (deleting.value) return;
  deleting.value = true;

  router.delete(`/plants/${props.plant.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      closeDelete();
      // pärast kustutamist tagasi SortView'sse (või eelmine leht)
      goBack();
    },
    onFinish: () => {
      deleting.value = false;
    },
  });
};

/** click-outside menüü sulgemiseks */
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
  <div class="bg-background-light dark:bg-background-dark font-display text-[#141514] dark:text-gray-100 antialiased min-h-screen">
    <!-- Desktop: laiem container -->
    <div class="relative min-h-screen mx-auto w-full max-w-md md:max-w-5xl flex flex-col pt-20">
      <!-- Top App Bar (laiemal ekraanil hoiab keskel) -->
      <div class="fixed top-0 left-0 right-0 z-50">
        <div class="mx-auto w-full max-w-md md:max-w-5xl flex items-center justify-between p-6">
          <button
            type="button"
            class="bg-white/60 dark:bg-black/20 backdrop-blur-md rounded-full p-2 flex items-center justify-center transition-colors"
            @click="goBack"
            aria-label="Tagasi"
          >
            <span class="material-symbols-outlined">arrow_back</span>
          </button>

          <!-- “…” menüü nagu SortView -->
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

      <!-- Hero Section -->
      <div class="relative w-full h-[45vh] md:h-[52vh] overflow-hidden rounded-none md:rounded-3xl md:mx-6">
        <div
          class="w-full h-full bg-cover bg-center transition-transform duration-700 hover:scale-105"
          :style="{ backgroundImage: `url('${props.plant.image_url || fallbackImage}')` }"
        />
        <div class="absolute inset-0 matte-overlay"></div>
      </div>

      <!-- Content -->
      <div class="px-6 -mt-12 relative z-10 md:px-12 md:-mt-16">
        <!-- Header Text -->
        <div class="mb-8 md:mb-10">
          <h1 class="font-serif italic text-4xl md:text-5xl tracking-tight text-[#2d3a2a] dark:text-primary mb-1">
            {{ props.plant.name }}
          </h1>
          <p class="text-[#717a71] dark:text-gray-400 text-sm font-body uppercase tracking-widest">
            {{ props.plant.subtitle || "" }}
          </p>
        </div>

        <!-- Desktop: 2 veergu (vasakul kaardid, paremal märkmed) -->
        <div class="md:grid md:grid-cols-2 md:gap-8">
          <!-- Status Cards -->
          <div class="grid grid-cols-1 gap-4 mb-10 md:mb-0">
            <!-- Watering Card -->
            <div class="bg-surface-light dark:bg-surface-dark p-5 rounded-2xl flex items-center justify-between shadow-sm border border-[#e6e2d5] dark:border-white/5">
              <div class="flex items-center gap-4">
                <div class="bg-primary/10 dark:bg-primary/20 p-3 rounded-xl text-primary">
                  <span class="material-symbols-outlined">opacity</span>
                </div>
                <div class="flex flex-col">
                  <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Kastmine</span>
                  <span class="text-base font-medium font-body leading-tight">
                    {{ wateringText }}
                  </span>
                </div>
              </div>

              <div v-if="wateringDueSoon" class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-20"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
              </div>
              <div v-else class="text-[#717a71]">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
              </div>
            </div>

            <!-- Fertilizing Card -->
            <div class="bg-surface-light dark:bg-surface-dark p-5 rounded-2xl flex items-center justify-between shadow-sm border border-[#e6e2d5] dark:border-white/5">
              <div class="flex items-center gap-4">
                <div class="bg-primary/10 dark:bg-primary/20 p-3 rounded-xl text-primary">
                  <span class="material-symbols-outlined">potted_plant</span>
                </div>
                <div class="flex flex-col">
                  <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Väetamine</span>
                  <span class="text-base font-medium font-body leading-tight">
                    {{ props.plant.fertilizing_frequency || "—" }}
                    <span v-if="props.plant.next_fertilizing_label" class="text-[#717a71] text-sm ml-1">
                      (Järgmine: {{ props.plant.next_fertilizing_label }})
                    </span>
                  </span>
                </div>
              </div>

              <div class="text-[#717a71]">
                <span class="material-symbols-outlined text-[20px]">calendar_today</span>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="mb-10 md:mb-0">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-bold tracking-tight">Märkmed</h3>
              <button type="button" class="text-primary text-sm font-semibold" @click="emit('edit-notes')">
                Muuda
              </button>
            </div>

            <div class="bg-white/50 dark:bg-surface-dark/40 rounded-2xl p-6 border border-[#e6e2d5]/50 dark:border-white/5">
              <p class="text-[#4a524a] dark:text-gray-300 font-body leading-relaxed">
                {{ props.plant.notes || "Märkmeid veel pole." }}
              </p>

              <div v-if="props.plant.tags?.length" class="mt-4 flex gap-2 flex-wrap">
                <span
                  v-for="tag in props.plant.tags"
                  :key="tag"
                  class="px-3 py-1 bg-primary/5 dark:bg-primary/10 text-primary text-[11px] font-bold rounded-full uppercase tracking-tighter"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- CTA -->
        <div class="pb-10 mt-10">
          <button
            type="button"
            class="w-full bg-primary hover:bg-[#5a8056] text-white py-5 rounded-2xl font-bold text-lg shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2 active:scale-[0.98] disabled:opacity-50"
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

    <!-- DELETE CONFIRM MODAL (sama stiil mis SortView) -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="deleteOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-4" aria-modal="true" role="dialog">
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
                  class="rounded-2xl px-4 py-3 font-medium shadow-sm transition disabled:opacity-50 bg-red-600 text-white hover:bg-red-700"
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