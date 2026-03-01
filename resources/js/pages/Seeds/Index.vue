<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';

import BottomNav from '../BottomNav.vue';

import AddSeed from './AddSeed.vue';
import SearchModal from './SearchModal.vue';

const breadcrumbs = [{ title: 'Aed', href: '/seeds' }];

type SeedItem = {
    id: number;
    name: string;
    year?: number | null;
    expires_at?: string | null;
    image_url?: string | null;
    is_favorite?: boolean;
    created_at?: string | null;
};

const props = withDefaults(
    defineProps<{
        seeds?: SeedItem[];
    }>(),
    {
        seeds: () => [],
    },
);

const showAddSeed = ref(false);
const showSearch = ref(false);
const searchQuery = ref('');
const localSeeds = ref<SeedItem[]>([...props.seeds]);
type TabKey = 'all' | 'favorites' | 'recent';
const activeTab = ref<TabKey>('all');

watch(
    () => props.seeds,
    (next) => {
        localSeeds.value = [...next];
    },
);

const deleteSeed = (id: number) => {
    router.delete(`/seeds/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            localSeeds.value = localSeeds.value.filter((seed) => seed.id !== id);
        },
    });
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

const filteredSeeds = computed(() => {
    let list = [...localSeeds.value];

    if (activeTab.value === 'favorites') {
        list = list.filter((seed) => seed.is_favorite === true);
    }

    if (activeTab.value === 'recent') {
        list = list.sort((a, b) => {
            const ad = new Date(a.created_at ?? 0).getTime();
            const bd = new Date(b.created_at ?? 0).getTime();
            return bd - ad;
        });
    }

    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((seed) => seed.name.toLowerCase().includes(q));
    }

    return list;
});

const seedNames = computed(() => localSeeds.value.map((seed) => seed.name));

const tabClass = (key: TabKey) => {
    const base =
        'flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full px-5 border transition-colors';
    if (activeTab.value === key) {
        return `${base} bg-primary text-white border-primary shadow-sm`;
    }
    return `${base} bg-beige/60 text-forest border-beige hover:bg-beige`;
};

const resetToAll = () => {
    activeTab.value = 'all';
    searchQuery.value = '';
};
</script>

<template>
    <Head title="Seemnevarud" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div class="bg-background-light text-forest font-display min-h-screen antialiased">
                <div
                    class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <header
                        class="bg-background-light/80 sticky top-0 z-20 px-6 pt-12 pb-4 backdrop-blur-md md:px-8"
                    >
                        <div class="mb-6 flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="mb-1 text-xs font-semibold tracking-widest text-primary uppercase">
                                    Minu Aia Päevik
                                </span>
                                <h1 class="text-forest text-3xl font-bold tracking-tight">Seemnevarud</h1>
                            </div>

                            <div class="flex gap-5">
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10"
                                    aria-label="Otsi seemet"
                                    @click="showSearch = true"
                                >
                                    <span class="material-symbols-outlined text-xl">search</span>
                                </button>

                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white shadow-sm transition hover:scale-105 active:scale-95"
                                    aria-label="Lisa seeme"
                                    @click="showAddSeed = true"
                                >
                                    <span class="material-symbols-outlined text-[20px]">add</span>
                                </button>
                            </div>
                        </div>

                        <div class="no-scrollbar flex gap-3 overflow-x-auto pb-2">
                            <button :class="tabClass('all')" type="button" @click="resetToAll">
                                <p class="text-sm font-semibold">Kõik</p>
                            </button>
                            <button :class="tabClass('favorites')" type="button" @click="activeTab = 'favorites'">
                                <p class="text-sm font-medium">Lemmikud</p>
                            </button>
                            <button :class="tabClass('recent')" type="button" @click="activeTab = 'recent'">
                                <p class="text-sm font-medium">Hiljuti lisatud</p>
                            </button>
                        </div>

                        <div v-if="searchQuery" class="mt-3 text-xs text-forest/70">
                            Otsing: <span class="font-semibold">{{ searchQuery }}</span>
                            <button class="ml-2 underline" type="button" @click="searchQuery = ''">
                                tühista
                            </button>
                        </div>
                    </header>

                    <main class="flex-1 px-6 py-6 md:px-8">
                        <div
                            v-if="filteredSeeds.length === 0"
                            class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-6 py-12 text-center"
                        >
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                                <span class="material-symbols-outlined text-primary">potted_plant</span>
                            </div>
                            <h2 class="text-lg font-semibold text-forest">Seemnevarud on veel tühjad</h2>
                            <p class="mt-2 text-sm text-forest/70">
                                Vajuta üleval paremas nurgas <strong>+</strong>, et lisada esimene seeme.
                            </p>
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
                                <div
                                    v-else
                                    class="absolute inset-0 flex h-full w-full items-center justify-center bg-primary/10 text-primary"
                                >
                                    <span class="material-symbols-outlined text-3xl">potted_plant</span>
                                </div>

                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />

                                <div class="absolute top-2 right-2 z-10">
                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-white/70 text-[#6B8C68] shadow-sm backdrop-blur-md transition hover:scale-105 hover:bg-white"
                                        @click.prevent.stop="deleteSeed(seed.id)"
                                        aria-label="Kustuta seeme"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>

                                <div class="absolute top-2 left-2 z-10">
                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-full shadow-sm backdrop-blur-md transition hover:scale-105"
                                        :class="
                                            seed.is_favorite
                                                ? 'bg-rose-50 ring-1 ring-rose-200'
                                                : 'bg-white/70 ring-1 ring-black/10 hover:bg-white'
                                        "
                                        @click.prevent.stop="toggleFavorite(seed.id)"
                                        aria-label="Lisa lemmikuks"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[18px] transition"
                                            :class="seed.is_favorite ? 'text-rose-600 drop-shadow-sm' : 'text-[#2E2E2E]/45'"
                                            :style="
                                                seed.is_favorite
                                                    ? { fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24` }
                                                    : { fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24` }
                                            "
                                        >
                                            favorite
                                        </span>
                                    </button>
                                </div>

                                <div class="absolute bottom-0 left-0 w-full p-4 text-white">
                                    <h3 class="text-lg font-bold">{{ seed.name }}</h3>
                                    <p class="mt-1 text-xs">Ostetud: {{ seed.year ?? '—' }}</p>
                                    <p class="text-xs">Aegub: {{ seed.expires_at ?? '—' }}</p>
                                </div>
                            </Link>
                        </div>
                    </main>
                </div>

                <AddSeed
                    v-model:open="showAddSeed"
                    @created="() => router.reload({ only: ['seeds'] })"
                />
                <SearchModal
                    v-model:open="showSearch"
                    :initialQuery="searchQuery"
                    :suggestions="seedNames"
                    @search="(q) => (searchQuery = q)"
                    @clear="searchQuery = ''"
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