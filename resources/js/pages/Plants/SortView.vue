<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

import CreatePlantModal from "@/components/CreatePlantModal.vue";
import DeletePlantModal from "@/components/DeletePlantModal.vue";
import PlantCard from "@/components/PlantCard.vue";
import AppLayout from "@/layouts/AppLayout.vue";

import BottomNav from "../BottomNav.vue";

type PlantStatus = "SAAGIKORISTUS" | "ÕITSEB" | "ISTIK" | "PUHKEPERIOOD";

type PlantItem = {
  id: number;
  name: string;
  planted_at: string;
  status: PlantStatus;
  image_url?: string | null;
};

type CategoryItem = { id: number; name: string; slug: string };

const props = defineProps<{
  category: { name: string; slug: string };
  plants: PlantItem[];
  categories: CategoryItem[];
}>();

/** Tabs */
type TabKey = "all" | "growing" | "harvest";
const activeTab = ref<TabKey>("all");

const filteredPlants = computed(() => {
  let list = props.plants ?? [];
  if (activeTab.value === "growing") {
    list = list.filter((p) => p.status !== "SAAGIKORISTUS" && p.status !== "PUHKEPERIOOD");
  }
  if (activeTab.value === "harvest") {
    list = list.filter((p) => p.status === "SAAGIKORISTUS");
  }
  return list;
});

const tabClass = (key: TabKey) => {
  const base = "px-5 py-2 rounded-full text-sm font-medium whitespace-nowrap transition";
  return activeTab.value === key ? `${base} bg-primary text-white` : `${base} bg-primary/10 text-primary`;
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

/** Hoia alles “tagasi siia vaatesse” URL (et pärast kustutamist ei viskaks dashboardi) */
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

  // Kui sinu route on teistsugune, muuda ainult seda:
  router.delete(`/plants/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      closeDelete();

      // backend võib redirectida /plants (dashboardi) -> viime kohe tagasi siia vaatesse
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
          <!-- Header -->
          <header class="bg-background-light/80 sticky top-0 z-30 border-b border-primary/10 px-4 py-3 backdrop-blur-md">
            <div class="flex items-center justify-between">
              <button class="flex items-center gap-1 font-medium text-primary" type="button" @click="goBack">
                <span class="material-symbols-outlined text-[24px]">chevron_left</span>
                <span class="text-sm">Kategooriad</span>
              </button>

              <h1 class="text-lg font-bold tracking-tight">
                {{ props.category.name }}
              </h1>

              <div class="flex items-center gap-5">
                <button
                  class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10"
                  type="button"
                  aria-label="Otsi"
                >
                  <span class="material-symbols-outlined text-[24px]">search</span>
                </button>

                <button
                  type="button"
                  @click="openAddPlant"
                  class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white shadow-sm transition hover:scale-105 active:scale-95"
                  aria-label="Lisa taim"
                >
                  <span class="material-symbols-outlined text-[20px]">add</span>
                </button>
              </div>
            </div>
          </header>

          <!-- MODAL -->
          <CreatePlantModal
            v-model:open="isAddPlantOpen"
            :categories="props.categories"
            :initialCategoryId="props.categories.find((c) => c.slug === props.category.slug)?.id ?? null"
            @created="onPlantCreated"
          />

          <main class="pb-24">
            <!-- Tabs -->
            <div class="px-4 py-6">
              <div class="no-scrollbar flex gap-3 overflow-x-auto pb-2">
                <button type="button" :class="tabClass('all')" @click="activeTab = 'all'">Kõik taimed</button>
                <button type="button" :class="tabClass('growing')" @click="activeTab = 'growing'">Kasvavad</button>
                <button type="button" :class="tabClass('harvest')" @click="activeTab = 'harvest'">Saagikoristus</button>
              </div>
            </div>

            <!-- Cards -->
            <div class="space-y-4 px-4">
              <PlantCard
                v-for="p in filteredPlants"
                :key="p.id"
                :plant="p"
                :menuOpen="menuOpenForId === p.id"
                @toggleMenu="toggleMenu"
                @edit="editPlant"
                @delete="askDelete"
              />

              <p v-if="filteredPlants.length === 0" class="px-1 text-sm text-[#2E2E2E]/60">
                Siin kategoorias pole veel taimi.
              </p>
            </div>
          </main>
        </div>

        <BottomNav active="plants" />
      </div>
    </div>

    <DeletePlantModal
      :open="deleteOpen"
      :plant="deleteTarget ? { id: deleteTarget.id, name: deleteTarget.name } : null"
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