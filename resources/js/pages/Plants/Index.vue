<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed, } from "vue";

import AppLayout from "@/layouts/AppLayout.vue";

import CreateCategoryModal from "../../components/CreateCategoryModal.vue";
import BottomNav from "../BottomNav.vue";


import SearchModal from "./SearchModal.vue";


const breadcrumbs = [{ title: "Aed", href: "/plants" }];

type Category = {
  id: number;
  name: string;
  slug: string;
  count: number;
  image: string;
  // need võivad hiljem tulla DB-st; praegu optional
  is_favorite?: boolean;
  created_at?: string;
};

const props = defineProps<{
  categories: Category[];
}>();
const categoryNames = computed(() => props.categories.map(c => c.name));




type TabKey = "all" | "favorites" | "recent";

const activeTab = ref<TabKey>("all");
const showCreateCategory = ref(false);
const showSearch = ref(false);
const searchQuery = ref("");



const filteredCategories = computed(() => {
  let list = props.categories ?? [];

  if (activeTab.value === "favorites") {
    list = list.filter((c) => c.is_favorite === true);
  }

  if (activeTab.value === "recent") {
    list = list
      .slice()
      .sort((a, b) => {
        const ad = new Date(a.created_at ?? 0).getTime();
        const bd = new Date(b.created_at ?? 0).getTime();
        return bd - ad;
      });
  }

  if (searchQuery.value.trim() !== "") {
    const q = searchQuery.value.toLowerCase();
    list = list.filter((c) => c.name.toLowerCase().includes(q));
  }

  return list;
});

const showDeleteModal = ref(false);
const categoryToDelete = ref<number | null>(null);

const openDeleteModal = (id: number) => {
  categoryToDelete.value = id;
  showDeleteModal.value = true;
};

const confirmDelete = () => {
  if (!categoryToDelete.value) return;

  router.delete(`/plants/categories/${categoryToDelete.value}`, {
    onSuccess: () => {
      showDeleteModal.value = false;
      categoryToDelete.value = null;
      router.reload();
    },
  });
};



const tabClass = (key: TabKey) => {
  const base =
    "flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full px-5 border transition-colors";
  if (activeTab.value === key) {
    return `${base} bg-primary text-white border-primary shadow-sm`;
  }
  return `${base} bg-beige/60 text-forest border-beige hover:bg-beige`;
};

const resetToAll = () => {
  activeTab.value = "all";
  searchQuery.value = "";
};


const toggleFavorite = (id: number) => {
  router.patch(`/plants/categories/${id}/favorite`, {}, {
    onSuccess: () => router.reload({ only: ["categories"] }),
  });
};
</script>


<template>
  <Head title="Minu Taimed" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page page-with-bottomnav">
      <div class="bg-background-light text-forest antialiased font-display min-h-screen">
        <!-- Botanical Line Art Accents (Decorative) -->
        <div class="fixed top-0 left-0 w-32 h-32 opacity-10 pointer-events-none select-none">
          <span class="material-symbols-outlined text-[120px] -rotate-45 translate-x-[-20%] translate-y-[-20%]">eco</span>
        </div>
        <div class="fixed bottom-20 right-0 w-32 h-32 opacity-10 pointer-events-none select-none">
          <span class="material-symbols-outlined text-[120px] rotate-12 translate-x-[20%]">potted_plant</span>
        </div>

     
        <div
          class="
            relative min-h-screen w-full
            bg-background-light overflow-x-hidden
            max-w-[480px] mx-auto shadow-2xl border-x border-beige/50
            md:max-w-none md:mx-0 md:shadow-none md:border-0
          "
        >
          <!-- Header -->
          <header class="sticky top-0 z-20 bg-background-light/80 backdrop-blur-md px-6 md:px-8 pt-12 pb-4">
            <div class="flex items-center justify-between mb-6">
              <div class="flex flex-col">
                <span class="text-xs font-semibold uppercase tracking-widest text-primary mb-1">
                  Minu Aia Päevik
                </span>
                <h1 class="text-3xl font-bold tracking-tight text-forest">Minu Taimed</h1>
              </div>
              <div class="flex gap-3">
                <button
                type="button"
                 @click="showCreateCategory = true"
                  class=" w-10 h-10 rounded-full bg-primary text-white shadow-lg flex items-center justify-center text-2xl font-light transition-all duration-200 hover:shadow-[0_10px_30px_rgba(0,0,0,0.25)] hover:scale-105 active:scale-95">
                  <span class="-mt-0.5">+</span>
                </button>
                <button
                  class="size-10 flex items-center justify-center rounded-full bg-beige/50 text-forest hover:bg-beige transition-colors"
                  type="button"
                  @click="showSearch = true"
                >
                  <span class="material-symbols-outlined text-xl">search</span>
                </button>
                <button
                  class="size-10 flex items-center justify-center rounded-full bg-beige/50 text-forest hover:bg-beige transition-colors"
                  type="button"
                >
                  <span class="material-symbols-outlined text-xl">tune</span>
                </button>
              </div>
            </div>

            <!-- Horizontal Quick Categories -->
            <div class="flex gap-3 overflow-x-auto pb-2 no-scrollbar">
              <button :class="tabClass('all')" type="button" @click="resetToAll">
                <p class="text-sm font-semibold">Kõik kategooriad</p>
              </button>
              <button :class="tabClass('favorites')" type="button" @click="activeTab = 'favorites'">
                <p class="text-sm font-medium">Lemmikud</p>
              </button>
              <button :class="tabClass('recent')" type="button" @click="activeTab = 'recent'">
                <p class="text-sm font-medium">Hiljuti lisatud</p>
              </button>
            </div>
          </header>

          <!-- Category Grid -->
          <main class="flex-1 px-6 md:px-8 py-4">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <!-- TAIMEKATEGOORIA NIMI -->
              <Link :href="`/plants/category/${cat.slug}`"
                v-for="cat in filteredCategories"
                :key="cat.id"
                class="relative aspect-[1/1] rounded-2xl overflow-hidden shadow-lg group">
                <img
                  alt="Tomatoes"
                  class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  :src="cat.image"
                />
                <div class="matte-overlay absolute inset-0"></div>
                <div class="absolute bottom-0 left-0 p-4 w-full">
                  <span class="inline-block px-2 py-0.5 rounded-md bg-white/20 backdrop-blur-md text-[10px] font-bold text-white uppercase mb-1">
                    {{ cat.count }} Sorti
                  </span>
                  <h3 class="text-white text-lg font-bold">{{ cat.name }}</h3>
                  
                </div>
                <button
                type="button"
                class="absolute top-2 right-2 z-10 h-8 w-8 rounded-full bg-white/70 backdrop-blur-md text-[#6B8C68] flex items-center justify-center shadow-sm transition hover:bg-white hover:scale-105"
                @click.prevent.stop="openDeleteModal(cat.id)">
                <span class="material-symbols-outlined text-[18px]">
                  delete
                </span>
                </button>
                <button
  type="button"
  class="absolute top-2 left-2 z-10 h-8 w-8 rounded-full
         backdrop-blur-md flex items-center justify-center
         shadow-sm transition hover:scale-105"
  :class="cat.is_favorite
    ? 'bg-rose-50 ring-1 ring-rose-200'
    : 'bg-white/70 ring-1 ring-black/10 hover:bg-white'"
  @click.prevent.stop="toggleFavorite(cat.id)"
  aria-label="Lisa lemmikuks"
>
  <span
    class="material-symbols-outlined text-[18px] transition"
    :class="cat.is_favorite ? 'text-rose-600 drop-shadow-sm' : 'text-[#2E2E2E]/45'"
    :style="cat.is_favorite
      ? { fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24` }
      : { fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24` }"
  >
    favorite
  </span>
</button>
              
              </Link>
            </div>

        
            
            
          </main>
          <SearchModal
          v-model:open="showSearch"
          :initialQuery="searchQuery"
          :suggestions="categoryNames"
          title="Otsi kategooriat"
          @search="(q) => (searchQuery = q)"
          @clear="searchQuery = ''"
          />
          <CreateCategoryModal v-model:open="showCreateCategory"
          @created="router.reload({ only: ['categories'] })"
           />
           <transition name="fade">
  <div
    v-if="showDeleteModal"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
  >
    <!-- overlay -->
    <div
      class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
      @click="showDeleteModal = false"
    />

    <!-- card -->
    <div class="relative w-full max-w-sm rounded-3xl bg-[#FAF8F4] shadow-xl ring-1 ring-black/5 p-6 text-center">

      <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-[#E6E2D5] flex items-center justify-center">
        <span class="material-symbols-outlined text-2xl text-[#6B8C68]">
          delete
        </span>
      </div>

      <h3 class="text-lg font-semibold text-[#2E2E2E]">
        Kustuta kategooria?
      </h3>

      <p class="mt-2 text-sm text-[#2E2E2E]/70">
        Seda tegevust ei saa tagasi võtta.
      </p>

      <div class="mt-6 flex flex-col gap-3">
        <button
          class="rounded-2xl bg-[#6B8C68] text-white py-3 font-medium hover:bg-[#4F6A52] transition"
          @click="confirmDelete"
        >
          Jah, kustuta
        </button>

        <button
          class="text-sm text-[#2E2E2E]/60 hover:text-[#2E2E2E]"
          @click="showDeleteModal = false"
        >
          Tühista
        </button>
      </div>
    </div>
  </div>
</transition>


        </div>

        



        <BottomNav active="plants" />
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.matte-overlay {
  background: linear-gradient(to top, rgba(79, 106, 82, 0.8) 0%, rgba(79, 106, 82, 0) 60%);
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
