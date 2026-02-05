
<script setup lang="ts">
type Plant = {
  id: number
  name: string
  subtitle?: string
  image_url?: string
  notes?: string
  tags?: string[]
  watering_in_days?: number
  fertilizing_frequency?: string
  next_fertilizing_label?: string
}

const props = withDefaults(defineProps<{
  plant: Plant
  markingWatered?: boolean
  justWatered? : boolean
}>(), {
  markingWatered: false,
  justWatered: false,
})


import { computed } from "vue"

const fallbackImage = "https://picsum.photos/900/1200"

const wateringText = computed(() => {
  const d = props.plant.watering_in_days
  if (d === 0) return "Vajab kastmist täna"
  if (d === 1) return "Vajab kastmist 1 päeva pärast"
  if (typeof d === "number") return `Vajab kastmist ${d} päeva pärast`
  return "Kastmise info puudub"
})

const wateringDueSoon = computed(() => {
  const d = props.plant.watering_in_days
  return typeof d === "number" && d <= 2
})


const emit = defineEmits<{
  (e: "back"): void
  (e: "menu"): void
  (e: "edit-notes"): void
  (e: "mark-watered"): void
}>()
</script>

<style scoped>
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
}

.matte-overlay {
  background: linear-gradient(to bottom, rgba(250,248,244,0) 0%, rgba(250,248,244,0.1) 70%, rgba(250,248,244,1) 100%);
}

:global(.dark) .matte-overlay {
  background: linear-gradient(to bottom, rgba(36,42,34,0) 0%, rgba(36,42,34,0.1) 70%, rgba(36,42,34,1) 100%);
}

</style>
<template>
  <div class="bg-background-light dark:bg-background-dark font-display text-[#141514] dark:text-gray-100 antialiased min-h-screen">
    <div class="max-w-md mx-auto relative min-h-screen flex flex-col pt-20">

      <!-- Top App Bar -->
      <div class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between p-6 max-w-md mx-auto">
        <button
          type="button"
          class="bg-white/60 dark:bg-black/20 backdrop-blur-md rounded-full p-2 flex items-center justify-center transition-colors"
          @click="emit('back')"
        >
          <span class="material-symbols-outlined">arrow_back</span>
        </button>

        <button
          type="button"
          class="bg-white/60 dark:bg-black/20 backdrop-blur-md rounded-full p-2 flex items-center justify-center transition-colors"
          @click="emit('menu')"
        >
          <span class="material-symbols-outlined">more_vert</span>
        </button>
      </div>

      <!-- Hero Section -->
      <div class="relative w-full h-[45vh] overflow-hidden">
        <div
          class="w-full h-full bg-cover bg-center transition-transform duration-700 hover:scale-105"
          :style="{ backgroundImage: `url('${props.plant.image_url || fallbackImage}')` }"
        ></div>
        <div class="absolute inset-0 matte-overlay"></div>
      </div>

      <!-- Content Container -->
      <div class="px-6 -mt-12 relative z-10">

        <!-- Header Text -->
        <div class="mb-8">
          <h1 class="font-serif italic text-4xl tracking-tight text-[#2d3a2a] dark:text-primary mb-1">
            {{ props.plant.name }}
          </h1>
          <p class="text-[#717a71] dark:text-gray-400 text-sm font-body uppercase tracking-widest">
            {{ props.plant.subtitle || '' }}
          </p>
        </div>

        <!-- Status Cards Grid -->
        <div class="grid grid-cols-1 gap-4 mb-10">

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

            <!-- ping ainult kui varsti vaja -->
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
                  {{ props.plant.fertilizing_frequency || '—' }}
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

        <!-- Notes Section -->
        <div class="mb-10">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold tracking-tight">Märkmed</h3>
            <button type="button" class="text-primary text-sm font-semibold" @click="emit('edit-notes')">
              Muuda
            </button>
          </div>

          <div class="bg-white/50 dark:bg-surface-dark/40 rounded-2xl p-6 border border-[#e6e2d5]/50 dark:border-white/5">
            <p class="text-[#4a524a] dark:text-gray-300 font-body leading-relaxed">
              {{ props.plant.notes || 'Märkmeid veel pole.' }}
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

      
<div class="px-6 pb-10">
  <button
    type="button"
    class="w-full bg-primary hover:bg-[#5a8056] text-white py-5 rounded-2xl font-bold text-lg shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2 active:scale-[0.98]"
    :disabled="props.markingWatered"
    @click="emit('mark-watered')"
  >
    <span class="material-symbols-outlined">water_drop</span>
    <span v-if="props.markingWatered">
  Salvestan…
</span>
<span v-else-if="props.justWatered">
  Kastetud
</span>
<span v-else>
  Märgi kastetuks
</span>

  </button>
</div>



    </div>
  </div>
</template>


