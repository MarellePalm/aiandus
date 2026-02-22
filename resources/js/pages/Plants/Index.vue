<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
//import { router } from "@inertiajs/vue3";
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
          <CreateCategoryModal v-model:open="showCreateCategory" />


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
