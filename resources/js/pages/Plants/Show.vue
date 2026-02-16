<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref } from "vue"

import PlantDetail from "../../components/PlantDetail.vue"

type Plant = {
  id: number
  name: string
  subtitle?: string
  image_url?: string
  notes?: string
  tags?: string[]
  watering_in_days?: number
  fertilizing_frequency?: string | null
  next_fertilizing_label?: string | null
}

const showMenu =ref(false)
function toggleMenu () {
  showMenu.value = !showMenu.value
}

function closeMenu () {
  showMenu.value = false
}

const { plant }= defineProps<{ plant: Plant }>()

const markingWatered = ref(false)
const justWatered = ref(false)

function markWatered() {
  markingWatered.value = true

  router.post(`/plants/${plant.id}/waterings`, {}, {
    onSuccess: () => {
      justWatered.value = true
    },
    onFinish: () => {
      markingWatered.value = false
    },
  })
}
</script>
<template>
  <PlantDetail
    :plant="plant"
    @menu="toggleMenu"
    :marking-watered="markingWatered"
    :just-watered="justWatered"
    @mark-watered="markWatered"
  />

  
<!-- MENU -->
<div v-if="showMenu" class="fixed inset-0 z-50">
  
  <!-- overlay -->
  <div
    class="absolute inset-0"
    @click="closeMenu"
  ></div>

  <!-- dropdown -->
  <div
    class="absolute right-6 top-20 w-56 rounded-2xl bg-white dark:bg-surface-dark shadow-xl border border-black/5 dark:border-white/10 overflow-hidden"
    @click.stop
  >
    <button class="w-full text-left px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5">
      Muuda taim
    </button>

    <button class="w-full text-left px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5">
      Dubleeri
    </button>

    <button class="w-full text-left px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 text-red-600">
      Kustuta
    </button>
  </div>

</div>

</template>



