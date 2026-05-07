<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CardActionsMenu from '@/components/CardActionsMenu.vue';
import DesktopSearchField from '@/components/DesktopSearchField.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import EditCategoryModal from '@/components/EditCategoryModal.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';

import CreateCategoryModal from '../../components/CreateCategoryModal.vue';
import BottomNav from '../BottomNav.vue';

import SearchModal from './SearchModal.vue';

const breadcrumbs = [{ title: 'Aed', href: '/plants' }];

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
const categoryNames = computed(() => props.categories.map((c) => c.name));

type TabKey = 'all' | 'favorites' | 'recent';

const fallbackLogo = '/logo.png';

function categoryImage(src?: string | null) {
    return src && src.trim() !== '' ? src : fallbackLogo;
}

function onCategoryImageError(event: Event) {
    const img = event.target as HTMLImageElement;

    if (img.src.endsWith('/logo.png')) return;

    img.src = fallbackLogo;
}

const activeTab = ref<TabKey>('all');
const showCreateCategory = ref(false);
const showSearch = ref(false);
const searchQuery = ref('');

const filteredCategories = computed(() => {
    let list = localCategories.value ?? [];

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

    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((c) => c.name.toLowerCase().includes(q));
    }

    return list;
});

const localCategories = ref<Category[]>([...props.categories]);

watch(
    () => props.categories,
    (next) => {
        localCategories.value = [...next];
    },
);

const showDeleteModal = ref(false);
const categoryToDelete = ref<number | null>(null);
const showEditCategory = ref(false);
const editingCategory = ref<Category | null>(null);

const openEditCategory = (category: Category) => {
    editingCategory.value = category;
    showEditCategory.value = true;
};
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

const toggleFavorite = (id: number) => {
    const idx = localCategories.value.findIndex((c) => c.id === id);
    if (idx === -1) return;

    // Optimistic update (kohe UI-s)
    const prev = localCategories.value[idx].is_favorite === true;
    localCategories.value[idx] = {
        ...localCategories.value[idx],
        is_favorite: !prev,
    };

    router.patch(
        `/plants/categories/${id}/favorite`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                // Kui server ütleb ei -> pane tagasi
                localCategories.value[idx] = {
                    ...localCategories.value[idx],
                    is_favorite: prev,
                };
            },
        },
    );
};

const tabClass = (key: TabKey) => {
    const base =
        'flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full px-5 transition-colors';
    if (activeTab.value === key) {
        return `${base} bg-primary text-white`;
    }
    return `${base} bg-primary/10 text-primary hover:bg-primary/15`;
};

const resetToAll = () => {
    activeTab.value = 'all';
    searchQuery.value = '';
};
</script>

<template>
    <Head title="Minu Taimed" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title="Taimed"
                        header-class="pt-6"
                        top-row-class="mb-3"
                        bottom-row-class="mb-4"
                    >
                        <template #leading>
                            <BackIconButton
                                href="/dashboard"
                                aria-label="Tagasi avalehele"
                            />
                        </template>
                        <template #actions>
                            <DesktopSearchField
                                v-model="searchQuery"
                                placeholder="Otsi taimi..."
                            />
                            <button
                                class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 lg:hidden"
                                type="button"
                                @click="showSearch = true"
                            >
                                <span class="material-symbols-outlined text-xl"
                                    >search</span
                                >
                            </button>
                        </template>

                        <!-- Horizontal Quick Categories -->
                        <div
                            class="no-scrollbar flex gap-3 overflow-x-auto pb-2"
                        >
                            <button
                                :class="tabClass('all')"
                                type="button"
                                @click="resetToAll"
                            >
                                <p class="text-sm font-semibold">
                                    Kõik kategooriad
                                </p>
                            </button>
                            <button
                                :class="tabClass('favorites')"
                                type="button"
                                @click="activeTab = 'favorites'"
                            >
                                <p class="text-sm font-medium">Lemmikud</p>
                            </button>
                            <button
                                :class="tabClass('recent')"
                                type="button"
                                @click="activeTab = 'recent'"
                            >
                                <p class="text-sm font-medium">
                                    Hiljuti lisatud
                                </p>
                            </button>
                        </div>
                    </DiaryHeader>

                    <!-- Category Grid -->
                    <main class="flex-1 px-6 py-4 md:px-8">
                        <div
                            v-if="filteredCategories.length === 0"
                            class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-6 py-12 text-center"
                        >
                            <div
                                class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10"
                            >
                                <span
                                    class="material-symbols-outlined text-primary"
                                    >favorite</span
                                >
                            </div>
                            <h2 class="text-lg font-semibold text-foreground">
                                {{
                                    activeTab === 'favorites'
                                        ? 'Lemmikuid veel ei ole'
                                        : 'Kategooriaid veel ei ole'
                                }}
                            </h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{
                                    activeTab === 'favorites'
                                        ? 'Märgi mõni kategooria lemmikuks, et see siin kuvataks.'
                                        : 'Lisa uus taime kategooria, et alustada.'
                                }}
                            </p>
                        </div>
                        <div
                            v-else
                            class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6"
                        >
                            <!-- TAIMEKATEGOORIA NIMI -->
                            <Link
                                :href="`/plants/category/${cat.slug}`"
                                v-for="cat in filteredCategories"
                                :key="cat.id"
                                class="group relative aspect-[1/1] overflow-hidden rounded-2xl shadow-lg"
                            >
                                <img
                                    :alt="cat.name"
                                    class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                    :src="categoryImage(cat.image)"
                                    @error="onCategoryImageError"
                                />
                                <div
                                    class="matte-overlay absolute inset-0"
                                ></div>
                                <div
                                    class="absolute bottom-0 left-0 w-full p-4 lg:p-3"
                                >
                                    <span
                                        class="mb-1 inline-block rounded-md bg-card/30 px-2 py-0.5 text-[10px] font-bold text-white uppercase backdrop-blur-md dark:bg-black/30 lg:text-[9px]"
                                    >
                                        {{ cat.count }} Sorti
                                    </span>
                                    <h3 class="text-lg font-bold text-white lg:text-base">
                                        {{ cat.name }}
                                    </h3>
                                </div>
                                <CardActionsMenu
                                    @edit="openEditCategory(cat)"
                                    @delete="openDeleteModal(cat.id)"
                                />
                                <button
                                    type="button"
                                    class="absolute top-2 left-2 z-10 flex h-8 w-8 items-center justify-center rounded-full shadow-sm backdrop-blur-md transition hover:scale-105"
                                    :class="
                                        cat.is_favorite
                                            ? 'bg-rose-50 ring-1 ring-rose-200'
                                            : 'bg-card/70 ring-1 ring-border hover:bg-card'
                                    "
                                    @click.prevent.stop="toggleFavorite(cat.id)"
                                    aria-label="Lisa lemmikuks"
                                >
                                    <span
                                        class="material-symbols-outlined text-[18px] transition"
                                        :class="
                                            cat.is_favorite
                                                ? 'text-rose-600 drop-shadow-sm'
                                                : 'text-foreground/45'
                                        "
                                        :style="
                                            cat.is_favorite
                                                ? {
                                                      fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24`,
                                                  }
                                                : {
                                                      fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24`,
                                                  }
                                        "
                                    >
                                        favorite
                                    </span>
                                </button>
                            </Link>

                            <button
                                v-if="activeTab !== 'favorites'"
                                type="button"
                                class="group relative hidden aspect-[1/1] items-center justify-center rounded-2xl border-2 border-dashed border-primary/20 bg-primary/5 text-primary transition hover:border-primary/35 hover:bg-primary/10 lg:flex"
                                @click="showCreateCategory = true"
                            >
                                <div class="text-center">
                                    <span
                                        class="material-symbols-outlined text-5xl leading-none opacity-70 transition group-hover:opacity-100 lg:text-4xl"
                                        >add</span
                                    >
                                    <p class="mt-2 text-sm font-semibold lg:text-xs">
                                        Lisa uus taime kategooria
                                    </p>
                                </div>
                            </button>
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
                    <CreateCategoryModal
                        v-model:open="showCreateCategory"
                        @created="router.reload({ only: ['categories'] })"
                    />

                    <EditCategoryModal
                        v-model:open="showEditCategory"
                        :category="editingCategory"
                        submit-url-base="/plants/categories"
                        @updated="router.reload({ only: ['categories'] })"
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
                            <div
                                class="relative w-full max-w-sm rounded-3xl bg-card p-6 text-center shadow-xl ring-1 ring-border"
                            >
                                <div
                                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-muted"
                                >
                                    <span
                                        class="material-symbols-outlined text-2xl text-primary"
                                    >
                                        delete
                                    </span>
                                </div>

                                <h3
                                    class="text-lg font-semibold text-foreground"
                                >
                                    Kustuta kategooria?
                                </h3>

                                <p class="mt-2 text-sm text-muted-foreground">
                                    Seda tegevust ei saa tagasi võtta.
                                </p>

                                <div class="mt-6 flex flex-col gap-3">
                                    <button
                                        class="rounded-2xl bg-primary py-3 font-medium text-primary-foreground transition hover:bg-primary/90"
                                        @click="confirmDelete"
                                    >
                                        Jah, kustuta
                                    </button>

                                    <button
                                        class="text-sm text-muted-foreground hover:text-foreground"
                                        @click="showDeleteModal = false"
                                    >
                                        Tühista
                                    </button>
                                </div>
                            </div>
                        </div>
                    </transition>
                    <FloatingPlusButton
                        class="lg:hidden"
                        aria-label="Lisa taime kategooria"
                        :size-px="52"
                        :icon-size-px="30"
                        @click="showCreateCategory = true"
                    />
                </div>

                <BottomNav active="plants" />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.matte-overlay {
    background: linear-gradient(
        to top,
        rgba(79, 106, 82, 0.8) 0%,
        rgba(79, 106, 82, 0) 60%
    );
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
