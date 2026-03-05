<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import CreateCategoryModal from '@/components/CreateCategoryModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

import CategoryCardActions from './CategoryCardActions.vue';
import EditCategoryModal from './EditCategoryModal.vue';
import SearchModal from './SearchModal.vue';

const breadcrumbs = [{ title: 'Aed', href: '/seeds' }];

type Category = {
    id: number;
    name: string;
    slug: string;
    count: number;
    image?: string | null;
    is_favorite?: boolean;
    created_at?: string | null;
};

const props = defineProps<{
    categories: Category[];
}>();

type TabKey = 'all' | 'favorites' | 'recent';

const activeTab = ref<TabKey>('all');
const showCreateCategory = ref(false);
const showEditCategory = ref(false);
const showSearch = ref(false);
const searchQuery = ref('');
const localCategories = ref<Category[]>([...props.categories]);
const categoryNames = computed(() => localCategories.value.map((c) => c.name));
const editingCategory = ref<Category | null>(null);

watch(
    () => props.categories,
    (next) => {
        localCategories.value = [...next];
    },
);

const filteredCategories = computed(() => {
    let list = [...localCategories.value];

    if (activeTab.value === 'favorites') {
        list = list.filter((c) => c.is_favorite === true);
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
        list = list.filter((c) => c.name.toLowerCase().includes(q));
    }

    return list;
});

const openDeleteModal = (id: number) => {
    if (!confirm('Kustuta kategooria? Selle kategooria seemned kustuvad ka.')) return;
    router.delete(`/plants/categories/${id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['categories'] }),
    });
};

const toggleFavorite = (id: number) => {
    const idx = localCategories.value.findIndex((c) => c.id === id);
    if (idx === -1) return;

    const prev = localCategories.value[idx].is_favorite === true;
    localCategories.value[idx] = { ...localCategories.value[idx], is_favorite: !prev };

    router.patch(`/plants/categories/${id}/favorite`, {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            localCategories.value[idx] = { ...localCategories.value[idx], is_favorite: prev };
        },
    });
};

const tabClass = (key: TabKey) => {
    const base = 'flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full px-5 border transition-colors';
    if (activeTab.value === key) {
        return `${base} bg-primary text-white border-primary shadow-sm`;
    }
    return `${base} bg-beige/60 text-forest border-beige hover:bg-beige`;
};

const resetToAll = () => {
    activeTab.value = 'all';
    searchQuery.value = '';
};

const openEditCategory = (category: Category) => {
    editingCategory.value = category;
    showEditCategory.value = true;
};
</script>

<template>
    <Head title="Seemnevarud" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div class="bg-background-light text-forest font-display min-h-screen antialiased">
                <div class="bg-background-light border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
                    <header class="bg-background-light/80 sticky top-0 z-20 px-6 pt-12 pb-4 backdrop-blur-md md:px-8">
                        <div class="mb-6 flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="mb-1 text-xs font-semibold tracking-widest text-primary uppercase">Minu Aia Paevik</span>
                                <h1 class="text-forest text-3xl font-bold tracking-tight">Seemnevarud</h1>
                            </div>

                            <div class="flex gap-5">
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10" @click="showSearch = true">
                                    <span class="material-symbols-outlined text-xl">search</span>
                                </button>
                                <button type="button" @click="showCreateCategory = true" class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white shadow-sm transition hover:scale-105 active:scale-95">
                                    <span class="material-symbols-outlined text-[20px]">add</span>
                                </button>
                            </div>
                        </div>

                        <div class="no-scrollbar flex gap-3 overflow-x-auto pb-2">
                            <button :class="tabClass('all')" type="button" @click="resetToAll">
                                <p class="text-sm font-semibold">Koik kategooriad</p>
                            </button>
                            <button :class="tabClass('favorites')" type="button" @click="activeTab = 'favorites'">
                                <p class="text-sm font-medium">Lemmikud</p>
                            </button>
                            <button :class="tabClass('recent')" type="button" @click="activeTab = 'recent'">
                                <p class="text-sm font-medium">Hiljuti lisatud</p>
                            </button>
                        </div>
                    </header>

                    <main class="flex-1 px-6 py-4 md:px-8">
                        <div v-if="filteredCategories.length === 0" class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-6 py-12 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                                <span class="material-symbols-outlined text-primary">category</span>
                            </div>
                            <h2 class="text-lg font-semibold text-forest">Seemnekategooriad puuduvad</h2>
                            <p class="mt-2 text-sm text-forest/70">
                                Vajuta uleval paremal <strong>+</strong>, et lisada esimene kategooria.
                            </p>
                        </div>

                        <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                            <Link
                                v-for="cat in filteredCategories"
                                :key="cat.id"
                                :href="`/seeds/category/${cat.slug}`"
                                class="group relative aspect-[1/1] overflow-hidden rounded-2xl shadow-lg"
                            >
                                <img
                                    v-if="cat.image"
                                    :src="cat.image"
                                    alt=""
                                    class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 flex h-full w-full items-center justify-center bg-primary/10 text-primary"
                                >
                                    <span class="material-symbols-outlined text-4xl">category</span>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />

                                <CategoryCardActions
                                    :is-favorite="cat.is_favorite === true"
                                    @delete="openDeleteModal(cat.id)"
                                    @favorite="toggleFavorite(cat.id)"
                                    @edit="openEditCategory(cat)"
                                />

                                <div class="absolute bottom-0 left-0 w-full p-4 text-white">
                                    <span class="mb-1 inline-block rounded-md bg-white/20 px-2 py-0.5 text-[10px] font-bold uppercase backdrop-blur-md">
                                        {{ cat.count }} seemet
                                    </span>
                                    <h3 class="text-lg font-bold">{{ cat.name }}</h3>
                                </div>
                            </Link>
                        </div>
                    </main>
                </div>

                <SearchModal
                    v-model:open="showSearch"
                    :initialQuery="searchQuery"
                    :suggestions="categoryNames"
                    title="Otsi seemneid"
                    @search="(q) => (searchQuery = q)"
                    @clear="searchQuery = ''"
                />
                <CreateCategoryModal
                    v-model:open="showCreateCategory"
                    @created="router.reload({ only: ['categories'] })"
                />
                <EditCategoryModal
                    v-model:open="showEditCategory"
                    :category="editingCategory"
                    @updated="router.reload({ only: ['categories'] })"
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