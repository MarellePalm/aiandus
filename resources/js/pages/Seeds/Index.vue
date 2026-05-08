<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CardActionsMenu from '@/components/CardActionsMenu.vue';
import CreateCategoryModal from '@/components/CreateCategoryModal.vue';
import DesktopSearchField from '@/components/DesktopSearchField.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import EditCategoryModal from '@/components/EditCategoryModal.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

import DeleteConfirmModal from './DeleteConfirmModal.vue';
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

const fallbackLogo = '/logo.png';

function categoryImage(src?: string | null) {
    return src && src.trim() !== '' ? src : fallbackLogo;
}

function onCategoryImageError(event: Event) {
    const img = event.target as HTMLImageElement;

    if (img.src.endsWith('/logo.png')) return;

    img.src = fallbackLogo;
}

type TabKey = 'all' | 'favorites';

const activeTab = ref<TabKey>('all');
const showCreateCategory = ref(false);
const showEditCategory = ref(false);
const showDeleteCategory = ref(false);
const showSearch = ref(false);
const searchQuery = ref('');
const localCategories = ref<Category[]>([...props.categories]);
const categoryNames = computed(() => localCategories.value.map((c) => c.name));
const editingCategory = ref<Category | null>(null);
const deletingCategory = ref<Category | null>(null);
const deleteProcessing = ref(false);

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

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((c) => c.name.toLowerCase().includes(q));
    }

    return list;
});

const openDeleteModal = (id: number) => {
    const category = localCategories.value.find((c) => c.id === id) ?? null;
    if (!category) return;
    deletingCategory.value = category;
    showDeleteCategory.value = true;
};

const closeDeleteCategory = () => {
    showDeleteCategory.value = false;
    deletingCategory.value = null;
    deleteProcessing.value = false;
};

const confirmDeleteCategory = () => {
    if (!deletingCategory.value || deleteProcessing.value) return;
    deleteProcessing.value = true;
    router.delete(`/seeds/categories/${deletingCategory.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteCategory();
            router.reload({ only: ['categories'] });
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};

const toggleFavorite = (id: number) => {
    const idx = localCategories.value.findIndex((c) => c.id === id);
    if (idx === -1) return;

    const prev = localCategories.value[idx].is_favorite === true;
    localCategories.value[idx] = {
        ...localCategories.value[idx],
        is_favorite: !prev,
    };

    router.patch(
        `/seeds/categories/${id}/favorite`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
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
        'flex h-8 shrink-0 items-center justify-center rounded-full px-3 text-xs font-semibold transition-colors';
    if (activeTab.value === key) {
        return `${base} bg-primary text-white`;
    }
    return `${base} bg-primary/10 text-primary hover:bg-primary/15`;
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
    <Head title="Varud" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title="Varud"
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
                                placeholder="Otsi varusid..."
                            />
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 sm:h-10 sm:w-10 lg:hidden"
                                @click="showSearch = true"
                            >
                                <span class="material-symbols-outlined text-xl"
                                    >search</span
                                >
                            </button>
                        </template>

                        <div
                            class="no-scrollbar flex gap-2 overflow-x-auto pb-2"
                        >
                            <button
                                :class="tabClass('all')"
                                type="button"
                                @click="resetToAll"
                            >
                                Kõik
                            </button>
                            <button
                                :class="tabClass('favorites')"
                                type="button"
                                @click="activeTab = 'favorites'"
                            >
                                Lemmikud
                            </button>
                        </div>
                    </DiaryHeader>

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
                                    >category</span
                                >
                            </div>
                            <h2 class="text-lg font-semibold text-foreground">
                                {{
                                    activeTab === 'favorites'
                                        ? 'Lemmikuid veel ei ole'
                                        : 'Varud puuduvad'
                                }}
                            </h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{
                                    activeTab === 'favorites'
                                        ? 'Märgi mõni kategooria lemmikuks, et see siin kuvataks.'
                                        : 'Vajuta plussi, et lisada uus kategooria varude lisamiseks.'
                                }}
                            </p>
                            <button
                                v-if="activeTab !== 'favorites'"
                                type="button"
                                class="mt-5 inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
                                @click="showCreateCategory = true"
                            >
                                <span class="material-symbols-outlined text-[18px]">add</span>
                                Lisa uus varu kategooria
                            </button>
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 lg:gap-5 xl:grid-cols-5"
                        >
                            <Link
                                v-for="cat in filteredCategories"
                                :key="cat.id"
                                :href="`/seeds/category/${cat.slug}`"
                                class="group relative aspect-square overflow-hidden rounded-2xl shadow-lg"
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

                                <CardActionsMenu
                                    @edit="openEditCategory(cat)"
                                    @delete="openDeleteModal(cat.id)"
                                />
                                <button
                                    type="button"
                                    class="absolute top-2 left-2 z-10 flex h-8 w-8 items-center justify-center rounded-full shadow-sm backdrop-blur-md transition hover:scale-105"
                                    :class="
                                        cat.is_favorite === true
                                            ? 'bg-rose-50 ring-1 ring-rose-200'
                                            : 'bg-card/70 ring-1 ring-border hover:bg-card'
                                    "
                                    @click.prevent.stop="toggleFavorite(cat.id)"
                                    aria-label="Lisa lemmikuks"
                                >
                                    <span
                                        class="material-symbols-outlined text-[18px] transition"
                                        :class="
                                            cat.is_favorite === true
                                                ? 'text-rose-600 drop-shadow-sm'
                                                : 'text-foreground/45'
                                        "
                                        :style="
                                            cat.is_favorite === true
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

                                <div
                                    class="absolute bottom-0 left-0 w-full p-4 text-white lg:p-3"
                                >
                                    <span
                                        class="mb-1 inline-block rounded-md bg-card/30 px-2 py-0.5 text-[10px] font-bold uppercase backdrop-blur-md dark:bg-black/30 lg:text-[9px]"
                                    >
                                        {{ cat.count }} varu
                                    </span>
                                    <h3 class="text-lg font-bold lg:text-base">
                                        {{ cat.name }}
                                    </h3>
                                </div>
                            </Link>
                            <button
                                type="button"
                                class="group relative hidden aspect-square items-center justify-center rounded-2xl border-2 border-dashed border-primary/20 bg-primary/5 text-primary transition hover:border-primary/35 hover:bg-primary/10 lg:flex"
                                @click="showCreateCategory = true"
                            >
                                <div class="text-center">
                                    <span
                                        class="material-symbols-outlined text-5xl leading-none opacity-70 transition group-hover:opacity-100 lg:text-4xl"
                                        >add</span
                                    >
                                    <p class="mt-2 text-sm font-semibold lg:text-xs">
                                        Lisa uus varu kategooria
                                    </p>
                                </div>
                            </button>
                        </div>
                    </main>
                </div>

                <SearchModal
                    v-model:open="showSearch"
                    :initialQuery="searchQuery"
                    :suggestions="categoryNames"
                    title="Otsi varusid"
                    @search="(q) => (searchQuery = q)"
                    @clear="searchQuery = ''"
                />
                <CreateCategoryModal
                    v-model:open="showCreateCategory"
                    post-url="/seeds/categories"
                    @created="router.reload({ only: ['categories'] })"
                />
                <EditCategoryModal
                    v-model:open="showEditCategory"
                    :category="editingCategory"
                    submit-url-base="/seeds/categories"
                    @updated="router.reload({ only: ['categories'] })"
                />
                <DeleteConfirmModal
                    :open="showDeleteCategory"
                    :title="'Kustuta kategooria?'"
                    :message="`${deletingCategory?.name ?? 'See kategooria'} eemaldatakse jäädavalt.`"
                    :processing="deleteProcessing"
                    @close="closeDeleteCategory"
                    @confirm="confirmDeleteCategory"
                />
                <FloatingPlusButton
                    class="lg:hidden"
                    aria-label="Lisa varu"
                    :size-px="52"
                    :icon-size-px="30"
                    @click="showCreateCategory = true"
                />
                <BottomNav active="seeds" />
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
