<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

import AddSeed from './AddSeed.vue';
import EditSeedModal from './EditSeedModal.vue';
import SearchModal from './SearchModal.vue';
import SeedCardActions from './SeedCardActions.vue';

type SeedItem = {
    id: number;
    name: string;
    year?: number | null;
    expires_at?: string | null;
    image_url?: string | null;
    notes?: string | null;
    is_favorite?: boolean;
    created_at?: string | null;
};

type CategoryItem = { id: number; name: string; slug: string };

const props = defineProps<{
    category: { id: number; name: string; slug: string };
    seeds: SeedItem[];
    categories: CategoryItem[];
}>();

type TabKey = 'all' | 'favorites' | 'recent';
const activeTab = ref<TabKey>('all');
const showAddSeed = ref(false);
const showSearch = ref(false);
const showEditSeed = ref(false);
const searchQuery = ref('');
const editingSeed = ref<SeedItem | null>(null);

const localSeeds = ref<SeedItem[]>([...props.seeds]);
const seedNames = computed(() => localSeeds.value.map((s) => s.name));

const filteredSeeds = computed(() => {
    let list = [...localSeeds.value];
    if (activeTab.value === 'favorites') {
        list = list.filter((s) => s.is_favorite === true);
    }
    if (activeTab.value === 'recent') {
        list = list.slice().sort((a, b) => {
            const ad = new Date(a.created_at ?? 0).getTime();
            const bd = new Date(b.created_at ?? 0).getTime();
            return bd - ad;
        });
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((s) => s.name.toLowerCase().includes(q));
    }
    return list;
});

const tabClass = (key: TabKey) => {
    const base = 'flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full px-5 border transition-colors';
    if (activeTab.value === key) return `${base} bg-primary text-white border-primary shadow-sm`;
    return `${base} bg-beige/60 text-forest border-beige hover:bg-beige`;
};

const toggleFavorite = (id: number) => {
    const idx = localSeeds.value.findIndex((seed) => seed.id === id);
    if (idx === -1) return;
    const prev = localSeeds.value[idx].is_favorite === true;
    localSeeds.value[idx] = { ...localSeeds.value[idx], is_favorite: !prev };

    router.patch(`/seeds/${id}/favorite`, {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            localSeeds.value[idx] = { ...localSeeds.value[idx], is_favorite: prev };
        },
    });
};

const deleteSeed = (id: number) => {
    router.delete(`/seeds/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            localSeeds.value = localSeeds.value.filter((seed) => seed.id !== id);
        },
    });
};

const goBack = () => router.visit('/seeds');
const openSeedEdit = (seed: SeedItem) => {
    editingSeed.value = seed;
    showEditSeed.value = true;
};
</script>

<template>
    <Head :title="`Seemned - ${props.category.name}`" />

    <AppLayout :breadcrumbs="[{ title: 'Aed', href: '/seeds' }, { title: props.category.name, href: `/seeds/category/${props.category.slug}` }]">
        <div class="page page-with-bottomnav">
            <div class="bg-background-light text-forest font-display min-h-screen antialiased">
                <div class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
                    <header class="bg-background-light/80 sticky top-0 z-20 px-6 pt-6 pb-4 backdrop-blur-md md:px-8">
                        <div class="mb-6 flex items-center justify-between">
                            <button class="flex items-center gap-1 font-medium text-primary" type="button" @click="goBack">
                                <span class="material-symbols-outlined text-[24px]">chevron_left</span>
                                <span class="text-sm">Kategooriad</span>
                            </button>
                            <h1 class="text-xl font-bold tracking-tight">{{ props.category.name }}</h1>
                            <div class="flex items-center gap-5">
                                <button class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10" type="button" @click="showSearch = true">
                                    <span class="material-symbols-outlined text-[24px]">search</span>
                                </button>
                                <button type="button" @click="showAddSeed = true" class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white shadow-sm transition hover:scale-105 active:scale-95">
                                    <span class="material-symbols-outlined text-[20px]">add</span>
                                </button>
                            </div>
                        </div>

                        <div class="no-scrollbar flex gap-3 overflow-x-auto pb-2">
                            <button type="button" :class="tabClass('all')" @click="activeTab = 'all'">Koik</button>
                            <button type="button" :class="tabClass('favorites')" @click="activeTab = 'favorites'">Lemmikud</button>
                            <button type="button" :class="tabClass('recent')" @click="activeTab = 'recent'">Hiljuti lisatud</button>
                        </div>
                    </header>

                    <main class="flex-1 px-6 py-6 md:px-8">
                        <div v-if="filteredSeeds.length === 0" class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-6 py-12 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                                <span class="material-symbols-outlined text-primary">potted_plant</span>
                            </div>
                            <h2 class="text-lg font-semibold text-forest">Selles kategoorias seemneid pole</h2>
                            <p class="mt-2 text-sm text-forest/70">Vajuta uleval paremal <strong>+</strong>, et lisada seeme.</p>
                        </div>

                        <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                            <Link
                                v-for="seed in filteredSeeds"
                                :key="seed.id"
                                :href="`/seeds/${seed.id}`"
                                class="group relative aspect-[1/1] overflow-hidden rounded-2xl shadow-lg"
                            >
                                <img
                                    v-if="seed.image_url"
                                    :src="seed.image_url"
                                    alt=""
                                    class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                />
                                <div v-else class="absolute inset-0 flex h-full w-full items-center justify-center bg-primary/10 text-primary">
                                    <span class="material-symbols-outlined text-3xl">potted_plant</span>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />

                                <SeedCardActions
                                    :is-favorite="seed.is_favorite === true"
                                    @delete="deleteSeed(seed.id)"
                                    @favorite="toggleFavorite(seed.id)"
                                    @edit="openSeedEdit(seed)"
                                />

                                <div class="absolute bottom-0 left-0 w-full p-4 text-white">
                                    <h3 class="text-lg font-bold">{{ seed.name }}</h3>
                                    <p class="mt-1 text-xs">Ostetud: {{ seed.year ?? '-' }}</p>
                                    <p class="text-xs">Aegub: {{ seed.expires_at ?? '-' }}</p>
                                </div>
                            </Link>
                        </div>
                    </main>
                </div>

                <AddSeed
                    v-model:open="showAddSeed"
                    :categories="props.categories"
                    :initialCategoryId="props.category.id"
                    @created="router.reload({ only: ['seeds'] })"
                />
                <SearchModal
                    v-model:open="showSearch"
                    :initialQuery="searchQuery"
                    :suggestions="seedNames"
                    title="Otsi seemneid"
                    @search="(q) => (searchQuery = q)"
                    @clear="searchQuery = ''"
                />
                <EditSeedModal
                    v-model:open="showEditSeed"
                    :seed="editingSeed"
                    @updated="router.reload({ only: ['seeds'] })"
                />
                <BottomNav active="seeds" />
            </div>
        </div>
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