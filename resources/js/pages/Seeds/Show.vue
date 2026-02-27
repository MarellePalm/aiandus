<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref } from "vue"

import PlantDetail from "../../components/PlantDetail.vue"

type Seed = {
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

function goHome() {
  closeMenu()
  router.visit("/dashboard") // kui sul avaleht on muu, muuda siin
}
function editSeed() {
  closeMenu()
  alert("Muuda taime: pole veel tehtud 🙂")
}

function goSeedsView() {
  closeMenu()
  alert("Taimede vaade: pole veel tehtud 🙂")
}

const showDeleteModal = ref(false)

function openDeleteModal() {
  closeMenu()
  showDeleteModal.value = true
}

function closeDeleteModal() {
  showDeleteModal.value = false
}

function confirmDelete() {
  router.delete(`/seeds/${seed.id}`, {
    onSuccess: () => {
      showDeleteModal.value = false
      router.visit("/dashboard")
    }
  })
}




function closeMenu () {
  showMenu.value = false
}

const { seed }= defineProps<{ seed: Seed }>()

const markingWatered = ref(false)
const justWatered = ref(false)

function markWatered() {
  markingWatered.value = true

  router.post(`/seeds/${seed.id}/waterings`, {}, {
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
     <button class="w-full text-left px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5"
     @click="goHome">
      Avalehele
    </button>

    <button class="w-full text-left px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5"
    @click="goPlantsView">
      Taimede vaade
    </button>

    <button class="w-full text-left px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5"
    @click="editSeed">
      Muuda taime
    </button>

    <button class="w-full text-left px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 text-red-600"
    @click="openDeleteModal">
      Kustuta
    </button>
  </div>

</div>

<!-- DELETE MODAL -->
<div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
  
  <!-- overlay -->
  <div 
    class="absolute inset-0 bg-black/40 backdrop-blur-sm"
    @click="closeDeleteModal"
  ></div>

  <!-- modal box -->
  <div class="relative bg-white dark:bg-surface-dark rounded-3xl p-8 w-80 shadow-2xl border border-black/5 dark:border-white/10 text-center">

    <h3 class="text-lg font-bold mb-3">
      Kustuta taim?
    </h3>

    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
      Seda tegevust ei saa tagasi võtta.
    </p>

    <div class="flex gap-3">
      <button
        class="flex-1 py-2 rounded-xl bg-gray-100 dark:bg-white/10"
        @click="closeDeleteModal"
      >
        Tühista
      </button>

      <button
        class="flex-1 py-2 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-700"
        @click="confirmDelete"
      >
        Kustuta
      </button>
    </div>

  </div>
</div>


</template>



