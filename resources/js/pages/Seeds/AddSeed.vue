<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3"

const form = useForm({
  name: "",
  subtitle: "",
  planted_at: "", 
  image_url:"",
})

function goBack() {
  router.visit("/dashboard") // või /plants kui sul on list. Saame pärast õigeks.
}
function submit() {
  form.post("/plants")
}

</script>

<template>
  <!-- Phone Form Factor Container -->
  <div class="bg-background-light dark:bg-background-dark font-display text-charcoal dark:text-gray-100 min-h-screen flex justify-center">
    <div class="w-full max-w-107.5 min-h-screen flex flex-col relative overflow-hidden">

      <!-- TopAppBar -->
      <header class="flex items-center px-6 pt-12 pb-6 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-md sticky top-0 z-10">
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

      <main class="flex-1 px-6 pb-24 flex flex-col gap-8">
        <!-- EmptyState / Photo Placeholder -->
        <div class="mt-4">
          <div class="group relative flex flex-col items-center justify-center aspect-square w-full rounded-[3rem] border-2 border-dashed border-[#dfe2df] dark:border-gray-700 bg-white/50 dark:bg-white/5 transition-all hover:border-primary/50 overflow-hidden ios-shadow"
          :style="form.image_url
            ? { backgroundImage: `url(${form.image_url})`, backgroundSize: 'cover', backgroundPosition: 'center' }
    :       {}"
          >
            <div class="flex flex-col items-center gap-4 p-8 text-center">
              <div v-if="!form.image_url" class="size-16 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-3xl!">add_a_photo</span>
              </div>
              <div class="flex flex-col gap-1">
                <p class="text-charcoal dark:text-white text-lg font-bold leading-tight">Lisa foto</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm font-normal">Sinu taime portree</p>
              </div>
              <button type="button" class="mt-2 flex min-w-30 cursor-pointer items-center justify-center rounded-full h-11 px-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-charcoal dark:text-white text-sm font-bold shadow-sm active:scale-95 transition-transform">
                Vali fail
              </button>
            </div>
          </div>
        </div>
         <!-- ✅ Samm 4: Pildi URL input (SIIN) -->
  <div class="mt-4 flex flex-col gap-2">
    <label class="text-charcoal dark:text-gray-300 text-sm font-semibold px-1">
      Pildi URL
    </label>

    <input
      v-model="form.image_url"
      @input="form.clearErrors('image_url')"
      type="url"
      placeholder="https://..."
      class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 ios-shadow focus:ring-2 focus:ring-primary/20 text-charcoal dark:text-white transition-all"
    />

    <p v-if="form.errors.image_url" class="text-red-600 dark:text-red-400 text-sm px-1">
      {{ form.errors.image_url }}
    </p>
  </div>

        <!-- Form Fields -->
        <div class="flex flex-col gap-6">
          <!-- TextField: Taime nimi -->
          <div class="flex flex-col gap-2">
            <label class="text-charcoal dark:text-gray-300 text-sm font-semibold px-1">Taime nimi</label>
            <div class="relative">
              <input
                v-model="form.name"
                @input="form.clearErrors('name')"
                class="w-full  h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 ios-shadow focus:ring-2 focus:ring-primary/20 placeholder:font-serif-journal placeholder:italic placeholder:text-gray-400 text-charcoal dark:text-white transition-all"
                placeholder="Taime nimi"
                type="text"
              />
            </div>
            <p v-if="form.errors.name" class="text-red-600 dark:text-red-400 text-sm px-1">
              {{ form.errors.name }}
            </p>

          </div>

          <!-- TextField: Sort -->
          <div class="flex flex-col gap-2">
            <label class="text-charcoal dark:text-gray-300 text-sm font-semibold px-1">Sort</label>
            <div class="relative">
              <input
                v-model="form.subtitle"
                @input="form.clearErrors('subtitle')"
                class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 ios-shadow focus:ring-2 focus:ring-primary/20 placeholder:text-gray-400 text-charcoal dark:text-white transition-all"
                placeholder="nt. Monstera Deliciosa"
                type="text"
              />
            </div>
            <p v-if="form.errors.subtitle" class="text-red-600 dark:text-red-400 text-sm px-1">
              {{ form.errors.subtitle }}
            </p>

          </div>

          <!-- Select: Istutamise kuupäev -->
          <div class="flex flex-col gap-2">
            <label class="text-charcoal dark:text-gray-300 text-sm font-semibold px-1">Istutamise kuupäev</label>
            <div class="relative">
              <input
                v-model="form.planted_at"
                type="date"
                @change="form.clearErrors('planted_at')"
                @click="($event.target as HTMLInputElement).showPicker?.()"
                class="w-full h-14 px-5 rounded-xl border-none bg-white dark:bg-gray-800 ios-shadow focus:ring-2 focus:ring-primary/20 text-charcoal dark:text-white transition-all cursor-pointer"
                />
            </div>
            <p v-if="form.errors.planted_at" class="text-red-600 dark:text-red-400 text-sm px-1">
              {{ form.errors.planted_at }}
            </p>

          </div>
        </div>

        
      </main>

      <!-- Bottom Primary Button -->
      <div class="px-6 pb-10">
        <button
            type="button"
            @click="submit"
            :disabled="form.processing"
            class="w-full h-16 bg-primary text-white font-bold text-lg rounded-xl shadow-lg shadow-primary/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2"
        >
        <span class="material-symbols-outlined">potted_plant</span>
        Salvesta taim
        </button>
      </div>


    </div>
  </div>
</template>

<style scoped>
.ios-shadow {
  box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
}

.custom-select {
  appearance: none;
  background-image: url(https://lh3.googleusercontent.com/aida-public/AB6AXuCCWcvFg7YxXPT-pfRujK0x1ZwQpRTCr7z74wbHLbRNojRY7kJXqiLM_7dpPHRoiML25gjJKffbNwrqNobfyUwtXSkwGAHamh2r3F7E3_EGbjuyVozRtpsQUPjbmOEPFGLxI49I9s37MaxnHyTLJQxWMCaMZWWrxtLglmUKVp-B8VqNzzKd22ti8u3XcbrpAT-vs6r9IJCI45gA4huH0Rju5FERGW2gKsrK63U05C6Y7cnqNhfyAVkYQ8yXV0XD-b-S6m_TlwpJJ8I);
  background-position: right 1rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
}
</style>
