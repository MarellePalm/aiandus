<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

import CreatePlantModal from "@/components/CreatePlantModal.vue";
import DeletePlantModal from "@/components/DeletePlantModal.vue";
import DiaryHeader from "@/components/DiaryHeader.vue";
import FloatingPlusButton from "@/components/FloatingPlusButton.vue";
import AppLayout from "@/layouts/AppLayout.vue";

import BottomNav from "../BottomNav.vue";


import SearchModal from "./SearchModal.vue";

type PlantItem = {
  id: number;
  subtitle: string; // sort
  planted_at: string; // kuupäev (string)
  image_url?: string | null;
};

type CategoryItem = { id: number; name: string; slug: string };

const props = defineProps<{
  category: { name: string; slug: string };
  plants: PlantItem[];
  categories: CategoryItem[];
}>();

const showSearch = ref(false);
const searchQuery = ref("");
const plantNames = computed(() => props.plants.map((p) => p.subtitle));

/** Tabs */
type TabKey = "all" | "recent";
const activeTab = ref<TabKey>("all");

const tabClass = (key: TabKey) => {
  const base = "px-5 py-2 rounded-full text-sm font-medium whitespace-nowrap transition";
  return activeTab.value === key
    ? `${base} bg-primary text-white`
    : `${base} bg-primary/10 text-primary`;
};

const resetToAll = () => {
  activeTab.value = "all";
  searchQuery.value = "";
};

/** Sorteeringud */
const allSorted = computed(() => {
  return [...props.plants].sort((a, b) =>
    (a.subtitle ?? "").localeCompare(b.subtitle ?? "", "et", { sensitivity: "base" })
  );
});

const recent5 = computed(() => {
  return [...props.plants]
    .sort((a, b) => {
      const da = new Date(a.planted_at).getTime();
      const db = new Date(b.planted_at).getTime();
      return db - da;
    })
    .slice(0, 5);
});

const basePlants = computed(() =>
  activeTab.value === "recent" ? recent5.value : allSorted.value
);

const visiblePlants = computed(() => {
  let list = basePlants.value;

  if (searchQuery.value.trim() !== "") {
    const q = searchQuery.value.toLowerCase();
    list = list.filter((p) => (p.subtitle ?? "").toLowerCase().includes(q));
  }

  return list;
});
/** Kuupäeva formaat */
const formatDateEE = (iso: string) => {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const yyyy = d.getFullYear();
  return `${dd}.${mm}.${yyyy}`;
};

/** Add plant modal */
const isAddPlantOpen = ref(false);
const openAddPlant = () => (isAddPlantOpen.value = true);
const onPlantCreated = () => router.reload({ only: ["plants"] });

/** Menu + delete */
const menuOpenForId = ref<number | null>(null);
const toggleMenu = (id: number) => (menuOpenForId.value = menuOpenForId.value === id ? null : id);

const deleteOpen = ref(false);
const deleteTarget = ref<PlantItem | null>(null);
const deleting = ref(false);

/** Hoia alles “tagasi siia vaatesse” URL */
const returnUrl = ref<string>("");

const askDelete = (p: PlantItem) => {
  menuOpenForId.value = null;
  deleteTarget.value = p;
  deleteOpen.value = true;
};

const closeDelete = () => {
  deleteOpen.value = false;
  deleteTarget.value = null;
  deleting.value = false;
};

const editPlant = (p: PlantItem) => {
  menuOpenForId.value = null;
  router.visit(`/plants/${p.id}/edit`);
};

const doDelete = () => {
  if (!deleteTarget.value || deleting.value) return;
  deleting.value = true;

  const backTo = returnUrl.value || `/plants/category/${props.category.slug}`;

  router.delete(`/plants/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      closeDelete();
      router.visit(backTo, {
        preserveScroll: true,
        only: ["plants"],
      });
    },
    onFinish: () => (deleting.value = false),
  });
};

/** click-outside dropdown */
const onDocClick = (e: MouseEvent) => {
  if (!menuOpenForId.value) return;
  const t = e.target as HTMLElement | null;
  if (t?.closest?.("[data-plant-menu]")) return;
  menuOpenForId.value = null;
};

onMounted(() => {
  returnUrl.value = window.location.pathname + window.location.search;
  document.addEventListener("click", onDocClick);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", onDocClick);
});

const goBack = () => router.visit("/plants");

/** fallback image */
const fallbackImage = "https://picsum.photos/200/200";
</script>

<template>
  <Head :title="`Minu Taimed - ${props.category.name}`" />

  <AppLayout
    :breadcrumbs="[
      { title: 'Aed', href: '/plants' },
      { title: props.category.name, href: `/plants/category/${props.category.slug}` },
    ]"
  >
    <div class="page page-with-bottomnav">
      <div class="bg-background-light text-text-main font-display min-h-screen">
        <div
          class="bg-background-light relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x border-primary/10 shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
        >
          <DiaryHeader
            :title="props.category.name"
            title-class="text-forest text-3xl font-bold tracking-tight absolute left-1/2 -translate-x-1/2"
            header-class="pt-6 border-b border-primary/10"
            top-row-class="mb-2"
            bottom-row-class="mb-0"
          >
            <template #leading>
              <button class="flex items-center gap-1 font-medium text-primary" type="button" @click="goBack">
                <span class="material-symbols-outlined text-[24px]">chevron_left</span>
                <span class="text-sm">Kategooriad</span>
              </button>
            </template>
            <template #actions>
              <button
                class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10"
                type="button"
                aria-label="Otsi"
                @click="showSearch = true"
              >
                <span class="material-symbols-outlined text-[24px]">search</span>
              </button>

              
            </template>
          </DiaryHeader>

          <!-- MODAL -->
          <CreatePlantModal
            v-model:open="isAddPlantOpen"
            :categories="props.categories"
            :initialCategoryId="props.categories.find((c) => c.slug === props.category.slug)?.id ?? null"
            @created="onPlantCreated"
          />
          <SearchModal
  v-model:open="showSearch"
  :initialQuery="searchQuery"
  :suggestions="plantNames"
  title="Otsi sorti"
  @search="(q) => (searchQuery = q)"
  @clear="searchQuery = ''"
/>

          <main class="pb-24">
            <!-- Tabs -->
            <div class="px-4 py-6">
              <div class="no-scrollbar flex gap-3 overflow-x-auto pb-2">
                <button type="button" :class="tabClass('all')" @click="resetToAll">Kõik sordid</button>
                <button type="button" :class="tabClass('recent')" @click="activeTab = 'recent'">Viimati lisatud</button>
              </div>
            </div>

            <!-- List -->
            <div class="px-4">
              <div v-if="visiblePlants.length === 0" class="rounded-2xl border border-primary/10 bg-white p-6 text-sm text-text-muted">
                Sorte veel pole. Vajuta “+”, et lisada esimene.
              </div>

              <div v-else class="space-y-4">
                <div
                  v-for="p in visiblePlants"
                  :key="p.id"
                  @click="router.visit(`/plants/${p.id}`)"
                  class="rounded-2xl border border-primary/10 bg-white p-4 shadow-sm"
                >
                  <div class="flex items-center gap-4">
                    <img
                      class="h-16 w-16 rounded-xl object-cover border border-primary/10"
                      :src="p.image_url || fallbackImage"
                      alt=""
                    />

                    <div class="min-w-0 flex-1">
                      <div class="truncate text-lg font-bold text-text-main">
                        {{ p.subtitle }}
                      </div>
                      <div class="mt-1 text-sm text-text-muted">
                        Istutatud: {{ formatDateEE(p.planted_at) }}
                      </div>
                    </div>

                    <div class="relative" data-plant-menu @click.stop>
                      <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-text-muted transition hover:bg-primary/10"
                        aria-label="Menüü"
                        @click.stop.prevent="toggleMenu(p.id)"
                      >
                        <span class="material-symbols-outlined text-[22px]">more_horiz</span>
                      </button>

                      <div
                        v-if="menuOpenForId === p.id"
                        class="absolute right-0 top-12 z-20 w-44 overflow-hidden rounded-xl border border-primary/10 bg-white shadow-lg"
                        @click.stop
                      >
                        <button
                          type="button"
                          class="w-full px-4 py-3 text-left text-sm hover:bg-primary/5"
                          @click.stop="editPlant(p)"
                        >
                          Muuda
                        </button>
                        <button
                          type="button"
                          class="w-full px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50"
                          @click.stop="askDelete(p)"
                        >
                          Kustuta
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </main>
        </div>

        <FloatingPlusButton
  aria-label="Lisa taim"
  :size-px="52"
  :icon-size-px="30"
  @click="openAddPlant"
/>

        <BottomNav active="plants" />
      </div>
    </div>

    <DeletePlantModal
      :open="deleteOpen"
      :plant="deleteTarget ? { id: deleteTarget.id, name: deleteTarget.subtitle } : null"
      :processing="deleting"
      @close="closeDelete"
      @confirm="doDelete"
    />
  </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>