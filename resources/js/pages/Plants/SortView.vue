<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CreatePlantModal from '@/components/CreatePlantModal.vue';
import DeletePlantModal from '@/components/DeletePlantModal.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import SortDropdown from '@/components/SortDropdown.vue';
import AppLayout from '@/layouts/AppLayout.vue';

import BottomNav from '../BottomNav.vue';

import SearchModal from './SearchModal.vue';

type PlantItem = {
    id: number;
    subtitle: string;
    planted_at: string;
    quantity?: number | null;
    image_url?: string | null;
    is_favorite?: boolean;
};

type CategoryItem = { id: number; name: string; slug: string };

const props = defineProps<{
    category: { name: string; slug: string };
    plants: PlantItem[];
    categories: CategoryItem[];
}>();

const showSearch = ref(false);
const searchQuery = ref('');
const plantNames = computed(() => props.plants.map((p) => p.subtitle));

type TabKey = 'all' | 'favorites';
const activeTab = ref<TabKey>('all');

const localPlants = ref<PlantItem[]>([...props.plants]);

watch(
    () => props.plants,
    (next) => {
        localPlants.value = [...next];
    },
);

const selectedSort = ref('name_asc');

const sortOptions = [
    { label: 'Nimi A–Z', value: 'name_asc' },
    { label: 'Nimi Z–A', value: 'name_desc' },
    { label: 'Istutamise kuupäev: uuemad enne', value: 'planted_desc' },
    { label: 'Istutamise kuupäev: vanemad enne', value: 'planted_asc' },
];

const tabClass = (key: TabKey) => {
    const base =
        'px-5 py-2 rounded-full text-sm font-medium whitespace-nowrap transition';

    return activeTab.value === key
        ? `${base} bg-primary text-white`
        : `${base} bg-primary/10 text-primary`;
};

const resetToAll = () => {
    activeTab.value = 'all';
    searchQuery.value = '';
};

const parseEeDate = (dateStr: string) => {
    if (!dateStr) return 0;

    const parts = dateStr.split('.');
    if (parts.length !== 3) return 0;

    const [day, month, year] = parts.map(Number);

    if (!day || !month || !year) return 0;

    return new Date(year, month - 1, day).getTime();
};

const toggleFavorite = (id: number) => {
    const idx = localPlants.value.findIndex((p) => p.id === id);
    if (idx === -1) return;

    const prev = localPlants.value[idx].is_favorite === true;

    localPlants.value[idx] = {
        ...localPlants.value[idx],
        is_favorite: !prev,
    };

    router.patch(
        `/plants/${id}/favorite`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                localPlants.value[idx] = {
                    ...localPlants.value[idx],
                    is_favorite: prev,
                };
            },
        },
    );
};

const basePlants = computed(() => {
    let list = [...localPlants.value];

    if (activeTab.value === 'favorites') {
        list = list.filter((p) => p.is_favorite === true);
    }

    switch (selectedSort.value) {
        case 'name_desc':
            return [...list].sort((a, b) =>
                (b.subtitle ?? '').localeCompare(a.subtitle ?? '', 'et', {
                    sensitivity: 'base',
                }),
            );

        case 'planted_desc':
            return [...list].sort(
                (a, b) => parseEeDate(b.planted_at) - parseEeDate(a.planted_at),
            );

        case 'planted_asc':
            return [...list].sort(
                (a, b) => parseEeDate(a.planted_at) - parseEeDate(b.planted_at),
            );

        case 'name_asc':
        default:
            return [...list].sort((a, b) =>
                (a.subtitle ?? '').localeCompare(b.subtitle ?? '', 'et', {
                    sensitivity: 'base',
                }),
            );
    }
});

const visiblePlants = computed(() => {
    let list = basePlants.value;

    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((p) => (p.subtitle ?? '').toLowerCase().includes(q));
    }

    return list;
});

const formatDateEE = (iso: string) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}.${mm}.${yyyy}`;
};

const isAddPlantOpen = ref(false);
const openAddPlant = () => (isAddPlantOpen.value = true);
const onPlantCreated = () => router.reload({ only: ['plants'] });

const menuOpenForId = ref<number | null>(null);
const toggleMenu = (id: number) =>
    (menuOpenForId.value = menuOpenForId.value === id ? null : id);

const deleteOpen = ref(false);
const deleteTarget = ref<PlantItem | null>(null);
const deleting = ref(false);

const returnUrl = ref<string>('');

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
                only: ['plants'],
            });
        },
        onFinish: () => (deleting.value = false),
    });
};

const onDocClick = (e: MouseEvent) => {
    if (!menuOpenForId.value) return;
    const t = e.target as HTMLElement | null;
    if (t?.closest?.('[data-plant-menu]')) return;
    menuOpenForId.value = null;
};

onMounted(() => {
    returnUrl.value = window.location.pathname + window.location.search;
    document.addEventListener('click', onDocClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
});

const fallbackImage = 'https://picsum.photos/200/200';
</script>

<template>
    <Head :title="`Minu Taimed - ${props.category.name}`" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Aed', href: '/plants' },
            {
                title: props.category.name,
                href: `/plants/category/${props.category.slug}`,
            },
        ]"
    >
        <div class="page page-with-bottomnav">
            <div
                class="bg-background-light text-text-main font-display min-h-screen"
            >
                <div
                    class="bg-background-light relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x border-primary/10 shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        :title="props.category.name"
                        title-class="text-forest text-3xl font-bold tracking-tight"
                        header-class="pt-6"
                        top-row-class="mb-2"
                        bottom-row-class="mb-0"
                    >
                        <template #leading>
                            <BackIconButton
                                href="/plants"
                                aria-label="Tagasi kategooriatesse"
                            />
                        </template>
                        <template #actions>
                            <button
                                class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10"
                                type="button"
                                aria-label="Otsi"
                                @click="showSearch = true"
                            >
                                <span
                                    class="material-symbols-outlined text-[24px]"
                                >
                                    search
                                </span>
                            </button>
                        </template>
                    </DiaryHeader>

                    <CreatePlantModal
                        v-model:open="isAddPlantOpen"
                        :categories="props.categories"
                        :initialCategoryId="
                            props.categories.find(
                                (c) => c.slug === props.category.slug,
                            )?.id ?? null
                        "
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
                        <div class="px-4 py-6">
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div
                                    class="no-scrollbar flex gap-3 overflow-x-auto overflow-y-visible pb-2 sm:pb-0"
                                >
                                    <button
                                        type="button"
                                        :class="tabClass('all')"
                                        @click="resetToAll"
                                    >
                                        Kõik sordid
                                    </button>
                                    <button
                                        type="button"
                                        :class="tabClass('favorites')"
                                        @click="activeTab = 'favorites'"
                                    >
                                        Lemmikud
                                    </button>
                                </div>

                                <div class="ml-auto shrink-0 self-end sm:self-auto">
                                    <SortDropdown
                                        v-model="selectedSort"
                                        :options="sortOptions"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="px-4">
                            <div
                                v-if="visiblePlants.length === 0"
                                class="text-text-muted rounded-2xl border border-primary/10 bg-card p-6 text-sm"
                            >
                                Sorte veel pole. Vajuta “+”, et lisada esimene.
                            </div>

                            <div v-else class="space-y-4">
                                <div
                                    v-for="p in visiblePlants"
                                    :key="p.id"
                                    class="relative rounded-2xl border border-primary/10 bg-card p-4 shadow-sm transition hover:shadow-md"
                                    @click="router.visit(`/plants/${p.id}`)"
                                >
                                    <div class="flex items-center gap-4">
                                        <img
                                            class="h-16 w-16 shrink-0 rounded-xl border border-primary/10 object-cover"
                                            :src="p.image_url || fallbackImage"
                                            alt=""
                                        />

                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="text-text-main truncate text-lg font-bold"
                                            >
                                                {{ p.subtitle }}
                                            </div>
                                            <div
                                                class="text-text-muted mt-1 text-sm"
                                            >
                                                Istutatud:
                                                {{ formatDateEE(p.planted_at) }}
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm text-foreground/70"
                                                >
                                                    Taimede arv:
                                                    {{ p.quantity }} tk
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="ml-2 flex shrink-0 items-center gap-2"
                                            data-plant-menu
                                            @click.stop
                                        >
                                            <button
                                                type="button"
                                                class="flex h-9 w-9 items-center justify-center rounded-full border border-primary/10 bg-background transition hover:scale-105 hover:bg-primary/5"
                                                :class="
                                                    p.is_favorite
                                                        ? 'text-rose-600 shadow-sm'
                                                        : 'text-foreground/45'
                                                "
                                                @click.prevent.stop="
                                                    toggleFavorite(p.id)
                                                "
                                                aria-label="Lisa lemmikuks"
                                                :title="
                                                    p.is_favorite
                                                        ? 'Eemalda lemmikutest'
                                                        : 'Lisa lemmikuks'
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-[20px] leading-none transition"
                                                    :style="
                                                        p.is_favorite
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

                                            <div class="relative">
                                                <button
                                                    type="button"
                                                    class="text-text-muted flex h-9 w-9 items-center justify-center rounded-full border border-transparent transition hover:bg-primary/10"
                                                    aria-label="Menüü"
                                                    @click.stop.prevent="
                                                        toggleMenu(p.id)
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-[22px]"
                                                    >
                                                        more_horiz
                                                    </span>
                                                </button>

                                                <div
                                                    v-if="
                                                        menuOpenForId === p.id
                                                    "
                                                    class="absolute top-11 right-0 z-20 w-44 overflow-hidden rounded-xl border border-primary/10 bg-card shadow-lg"
                                                    @click.stop
                                                >
                                                    <button
                                                        type="button"
                                                        class="w-full px-4 py-3 text-left text-sm text-foreground hover:bg-primary/5"
                                                        @click.stop="
                                                            editPlant(p)
                                                        "
                                                    >
                                                        Muuda
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="w-full px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                        @click.stop="
                                                            askDelete(p)
                                                        "
                                                    >
                                                        Kustuta
                                                    </button>
                                                </div>
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
            :plant="
                deleteTarget
                    ? { id: deleteTarget.id, name: deleteTarget.subtitle }
                    : null
            "
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
