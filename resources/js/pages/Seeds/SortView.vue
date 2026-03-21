<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

import AddSeed from './AddSeed.vue';
import DeleteConfirmModal from './DeleteConfirmModal.vue';
import EditSeedModal from './EditSeedModal.vue';
import SearchModal from './SearchModal.vue';

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
const showDeleteSeed = ref(false);
const searchQuery = ref('');
const editingSeed = ref<SeedItem | null>(null);
const deletingSeed = ref<SeedItem | null>(null);
const deleteProcessing = ref(false);
const menuOpenForId = ref<number | null>(null);

const localSeeds = ref<SeedItem[]>([...props.seeds]);
const seedNames = computed(() => localSeeds.value.map((s) => s.name));

watch(
    () => props.seeds,
    (next) => {
        localSeeds.value = [...next];
    },
);

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

const openDeleteSeed = (seed: SeedItem) => {
    deletingSeed.value = seed;
    showDeleteSeed.value = true;
};

const closeDeleteSeed = () => {
    showDeleteSeed.value = false;
    deletingSeed.value = null;
    deleteProcessing.value = false;
};

const confirmDeleteSeed = () => {
    if (!deletingSeed.value || deleteProcessing.value) return;
    deleteProcessing.value = true;
    router.delete(`/seeds/${deletingSeed.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            localSeeds.value = localSeeds.value.filter((seed) => seed.id !== deletingSeed.value?.id);
            closeDeleteSeed();
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};

const goBack = () => router.visit('/seeds');
const openSeedEdit = (seed: SeedItem) => {
    editingSeed.value = seed;
    showEditSeed.value = true;
    menuOpenForId.value = null;
};

const openSeed = (id: number) => {
    router.visit(`/seeds/${id}`);
};

const toggleMenu = (id: number) => {
    menuOpenForId.value = menuOpenForId.value === id ? null : id;
};

const onDocClick = (e: MouseEvent) => {
    if (!menuOpenForId.value) return;
    const t = e.target as HTMLElement | null;
    if (t?.closest?.('[data-seed-menu]')) return;
    menuOpenForId.value = null;
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
});
</script>

<template>
    <Head :title="`Seemned - ${props.category.name}`" />

    <AppLayout :breadcrumbs="[{ title: 'Aed', href: '/seeds' }, { title: props.category.name, href: `/seeds/category/${props.category.slug}` }]">
        <div class="page page-with-bottomnav">
            <div class="bg-background-light text-forest font-display min-h-screen antialiased">
                <div class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
                    <DiaryHeader
                        :title="props.category.name"
                        title-class="text-forest text-3xl font-bold tracking-tight max-w-[12rem] truncate sm:max-w-none"
                        header-class="pt-6"
                    >
                        <template #leading>
                            <button class="flex items-center gap-1 font-medium text-primary" type="button" @click="goBack">
                                <span class="material-symbols-outlined text-[24px]">chevron_left</span>
                                <span class="hidden text-sm sm:inline">Kategooriad</span>
                            </button>
                        </template>
                        <template #actions>
                                <button class="flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 sm:h-10 sm:w-10" type="button" @click="showSearch = true">
                                    <span class="material-symbols-outlined text-[24px]">search</span>
                                </button>
                        </template>

                        <div class="no-scrollbar flex gap-3 overflow-x-auto pb-2">
                            <button type="button" :class="tabClass('all')" @click="activeTab = 'all'">Kõik</button>
                            <button type="button" :class="tabClass('favorites')" @click="activeTab = 'favorites'">Lemmikud</button>
                            <button type="button" :class="tabClass('recent')" @click="activeTab = 'recent'">Hiljuti lisatud</button>
                        </div>
                    </DiaryHeader>

                    <main class="flex-1 px-6 py-6 md:px-8">
                        <div v-if="filteredSeeds.length === 0" class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-6 py-12 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                                <span class="material-symbols-outlined text-primary">potted_plant</span>
                            </div>
                            <h2 class="text-lg font-semibold text-forest">Selles kategoorias seemneid pole</h2>
                            <p class="mt-2 text-sm text-forest/70">Vajuta üleval paremal <strong>+</strong>, et lisada seeme.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="seed in filteredSeeds"
                                :key="seed.id"
                                class="rounded-2xl border border-primary/10 bg-white p-4 shadow-sm transition hover:shadow-md"
                                role="button"
                                tabindex="0"
                                @click="openSeed(seed.id)"
                                @keydown.enter.prevent="openSeed(seed.id)"
                            >
                                <div class="flex items-center gap-4">
                                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-primary/10 bg-gray-100">
                                        <img
                                            v-if="seed.image_url"
                                            :src="seed.image_url"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full w-full items-center justify-center text-primary/70">
                                            <span class="material-symbols-outlined text-2xl">potted_plant</span>
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate text-lg font-semibold">{{ seed.name }}</h3>
                                        <p class="mt-1 text-sm text-text-muted">Ostetud: {{ seed.year ?? '-' }}</p>
                                        <p class="text-sm text-text-muted">Aegub: {{ seed.expires_at ?? '-' }}</p>
                                    </div>

                                    <div class="relative shrink-0" data-seed-menu @click.stop>
                                        <button
                                            type="button"
                                            class="flex h-10 w-10 items-center justify-center rounded-full text-text-muted transition hover:bg-primary/10"
                                            aria-label="Menüü"
                                            @click.stop.prevent="toggleMenu(seed.id)"
                                        >
                                            <span class="material-symbols-outlined text-[22px]">more_horiz</span>
                                        </button>

                                        <div
                                            v-if="menuOpenForId === seed.id"
                                            class="absolute right-0 top-12 z-20 w-48 overflow-hidden rounded-xl border border-primary/10 bg-white shadow-lg"
                                            @click.stop
                                        >
                                            <button
                                                type="button"
                                                class="w-full px-4 py-3 text-left text-sm hover:bg-primary/5"
                                                @click.stop="openSeedEdit(seed)"
                                            >
                                                Muuda
                                            </button>
                                            <button
                                                type="button"
                                                class="w-full px-4 py-3 text-left text-sm hover:bg-primary/5"
                                                @click.stop="toggleFavorite(seed.id); menuOpenForId = null"
                                            >
                                                {{ seed.is_favorite ? 'Eemalda lemmikutest' : 'Lisa lemmikuks' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="w-full px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50"
                                                @click.stop="openDeleteSeed(seed); menuOpenForId = null"
                                            >
                                                Kustuta
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                <DeleteConfirmModal
                    :open="showDeleteSeed"
                    :title="'Kustuta seeme?'"
                    :message="`${deletingSeed?.name ?? 'See seeme'} eemaldatakse jäädavalt.`"
                    :processing="deleteProcessing"
                    @close="closeDeleteSeed"
                    @confirm="confirmDeleteSeed"
                />
                <FloatingPlusButton aria-label="Lisa varu" :size-px="52" :icon-size-px="30" @click="showAddSeed = true" />
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