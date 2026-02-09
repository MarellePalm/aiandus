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
    :marking-watered="markingWatered"
    :just-watered="justWatered"
    @mark-watered="markWatered"
  />
</template>



