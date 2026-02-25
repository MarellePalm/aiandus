<script setup lang="ts">
import { router } from "@inertiajs/vue3";

type PlantStatus = "SAAGIKORISTUS" | "ÕITSEB" | "ISTIK" | "PUHKEPERIOOD";
type PlantItem = {
  id: number;
  name: string;
  planted_at: string;
  status: PlantStatus;
  image_url?: string | null;
};

const props = defineProps<{ plant: PlantItem; menuOpen: boolean }>();

const emit = defineEmits<{
  (e: "toggleMenu", id: number): void;
  (e: "edit", plant: PlantItem): void;
  (e: "delete", plant: PlantItem): void;
}>();

const goToPlant = () => router.visit(`/plants/${props.plant.id}`);

const statusStyles = (s: PlantStatus) => {
  switch (s) {
    case "SAAGIKORISTUS":
      return { dot: "bg-primary", text: "text-primary" };
    case "ÕITSEB":
      return { dot: "bg-amber-400", text: "text-amber-600" };
    case "ISTIK":
      return { dot: "bg-blue-400", text: "text-blue-500" };
    default:
      return { dot: "bg-gray-400", text: "text-gray-500" };
  }
};
</script>

<template>
  <div
    class="relative flex gap-4 rounded-xl border border-primary/5 bg-white/90 p-4 shadow-[0_4px_20px_rgba(107,141,104,0.08)]"
    role="button"
    tabindex="0"
    @click="goToPlant"
    @keydown.enter.prevent="goToPlant"
  >
    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100">
      <img
        v-if="plant.image_url"
        class="h-full w-full object-cover opacity-90 contrast-[0.9] saturate-[0.8]"
        :src="plant.image_url"
        :alt="plant.name"
      />
      <div v-else class="flex h-full w-full items-center justify-center text-xs text-[#2E2E2E]/40">
        Pole pilti
      </div>
    </div>

    <div class="flex min-w-0 flex-1 flex-col justify-between py-0.5">
      <div>
        <div class="flex items-start justify-between gap-3">
          <h2 class="serif-italic text-text-main truncate text-xl">
            {{ plant.name }}
          </h2>

          <!-- menu wrapper: STOP nav click -->
          <div class="relative" data-plant-menu @click.stop>
            <button
              class="rounded-full p-2 text-gray-300 hover:bg-black/5 hover:text-gray-500"
              type="button"
              aria-label="Rohkem"
              @click.stop="emit('toggleMenu', plant.id)"
            >
              <span class="material-symbols-outlined">more_horiz</span>
            </button>

            <div
              v-if="menuOpen"
              class="absolute right-0 top-10 z-20 w-44 overflow-hidden rounded-2xl border border-black/10 bg-[#FAF8F4] shadow-xl ring-1 ring-black/5"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-[#2E2E2E] hover:bg-black/5"
                @click.stop="emit('edit', plant)"
              >
                <span class="material-symbols-outlined text-[20px] text-[#6B8C68]">edit</span>
                Muuda
              </button>

              <button
                type="button"
                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-[#2E2E2E] hover:bg-black/5"
                @click.stop="emit('delete', plant)"
              >
                <span class="material-symbols-outlined text-[20px] text-red-600">delete</span>
                Kustuta
              </button>
            </div>
          </div>
        </div>

        <p class="mt-1 text-[13px] text-gray-500">Istutatud: {{ plant.planted_at }}</p>
      </div>

      <div class="flex items-center gap-2">
        <span class="h-2 w-2 rounded-full" :class="statusStyles(plant.status).dot"></span>
        <span class="text-xs font-semibold tracking-wider uppercase" :class="statusStyles(plant.status).text">
          {{ plant.status }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.serif-italic {
  font-family: "Playfair Display", serif;
  font-style: italic;
}
</style>