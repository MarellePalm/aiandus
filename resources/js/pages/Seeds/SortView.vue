<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import SortDropdown from '@/components/SortDropdown.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';

import AddSeed from './AddSeed.vue';
import DeleteConfirmModal from './DeleteConfirmModal.vue';
import EditSeedModal from './EditSeedModal.vue';
import SearchModal from './SearchModal.vue';

type SeedItem = {
    id: number;
    name: string;
    year?: number | string | null;
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

type TabKey = 'all' | 'favorites';
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
const selectedSort = ref('name_asc');

watch(
    () => props.seeds,
    (next) => {
        localSeeds.value = [...next];
    },
);

const sortOptions = [
    { label: 'Nimi A–Z', value: 'name_asc' },
    { label: 'Nimi Z–A', value: 'name_desc' },
    { label: 'Ostuaasta: uuemad enne', value: 'year_desc' },
    { label: 'Ostuaasta: vanemad enne', value: 'year_asc' },
    { label: 'Aegub: uuemad enne', value: 'expires_desc' },
    { label: 'Aegub: vanemad enne', value: 'expires_asc' },
];

const parseYear = (value?: number | string | null) => {
    if (typeof value === 'number') {
        return Number.isNaN(value) ? 0 : value;
    }
    if (typeof value === 'string') {
        const parsed = Number.parseInt(value, 10);
        return Number.isNaN(parsed) ? 0 : parsed;
    }
    return 0;
};

const parseExpiresAt = (value?: string | null) => {
    if (!value) return 0;

    const trimmed = value.trim();

    // dd.mm.yyyy (või d.m.yyyy) -> võrdleme päris kuupäeva järgi
    const dotParts = trimmed.split('.');
    if (dotParts.length === 3) {
        const [day, month, year] = dotParts.map((part) =>
            Number.parseInt(part, 10),
        );
        if (!Number.isNaN(day) && !Number.isNaN(month) && !Number.isNaN(year)) {
            return new Date(year, month - 1, day).getTime();
        }
    }

    // ISO-kuupäevad jms
    const t = new Date(trimmed).getTime();
    return Number.isNaN(t) ? 0 : t;
};

const formatDateEE = (value?: string | null) => {
    if (!value) return '';
    const trimmed = value.trim();

    // Kui backend saadab ainult aasta (nt "2028"), kuva detailselt.
    if (/^\d{4}$/.test(trimmed)) return `01.01.${trimmed}`;

    // Kui juba dd.mm.yyyy formaadis, tagasta ühtlaselt 2-kohalise päeva/kuuga.
    const dotParts = trimmed.split('.');
    if (dotParts.length === 3) {
        const [day, month, year] = dotParts.map((part) =>
            Number.parseInt(part, 10),
        );
        if (!Number.isNaN(day) && !Number.isNaN(month) && !Number.isNaN(year)) {
            const dd = String(day).padStart(2, '0');
            const mm = String(month).padStart(2, '0');
            return `${dd}.${mm}.${year}`;
        }
    }

    const d = new Date(trimmed);
    if (Number.isNaN(d.getTime())) return trimmed;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}.${mm}.${yyyy}`;
};

const baseSeeds = computed(() => {
    let list = [...localSeeds.value];
    if (activeTab.value === 'favorites') {
        list = list.filter((s) => s.is_favorite === true);
    }

    switch (selectedSort.value) {
        case 'name_desc':
            return [...list].sort((a, b) =>
                (b.name ?? '').localeCompare(a.name ?? '', 'et', {
                    sensitivity: 'base',
                }),
            );
        case 'year_desc':
            return [...list].sort(
                (a, b) => parseYear(b.year) - parseYear(a.year),
            );
        case 'year_asc':
            return [...list].sort(
                (a, b) => parseYear(a.year) - parseYear(b.year),
            );
        case 'expires_desc':
            return [...list].sort(
                (a, b) =>
                    parseExpiresAt(b.expires_at) - parseExpiresAt(a.expires_at),
            );
        case 'expires_asc':
            return [...list].sort(
                (a, b) =>
                    parseExpiresAt(a.expires_at) - parseExpiresAt(b.expires_at),
            );
        case 'name_asc':
        default:
            return [...list].sort((a, b) =>
                (a.name ?? '').localeCompare(b.name ?? '', 'et', {
                    sensitivity: 'base',
                }),
            );
    }
});

const filteredSeeds = computed(() => {
    let list = baseSeeds.value;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((s) => s.name.toLowerCase().includes(q));
    }
    return list;
});

const tabClass = (key: TabKey) => {
    const base =
        'flex h-9 shrink-0 items-center justify-center rounded-full px-4 text-sm font-medium transition-colors';
    if (activeTab.value === key) return `${base} bg-primary text-white`;
    return `${base} bg-primary/10 text-primary hover:bg-primary/15`;
};

const toggleFavorite = (id: number) => {
    const idx = localSeeds.value.findIndex((seed) => seed.id === id);
    if (idx === -1) return;
    const prev = localSeeds.value[idx].is_favorite === true;
    localSeeds.value[idx] = { ...localSeeds.value[idx], is_favorite: !prev };

    router.patch(
        `/seeds/${id}/favorite`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                localSeeds.value[idx] = {
                    ...localSeeds.value[idx],
                    is_favorite: prev,
                };
            },
        },
    );
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
            localSeeds.value = localSeeds.value.filter(
                (seed) => seed.id !== deletingSeed.value?.id,
            );
            closeDeleteSeed();
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};

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
    <Head :title="`Varud - ${props.category.name}`" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Aed', href: '/seeds' },
            {
                title: props.category.name,
                href: `/seeds/category/${props.category.slug}`,
            },
        ]"
    >
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        :title="props.category.name"
                        title-class="text-foreground text-3xl font-bold tracking-tight max-w-[12rem] truncate sm:max-w-none"
                        header-class="pt-6"
                    >
                        <template #leading>
                            <BackIconButton
                                href="/seeds"
                                aria-label="Tagasi kategooriatesse"
                            />
                        </template>
                        <template #actions>
                            <button
                                class="flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 sm:h-10 sm:w-10"
                                type="button"
                                @click="showSearch = true"
                            >
                                <span
                                    class="material-symbols-outlined text-[24px]"
                                    >search</span
                                >
                            </button>
                        </template>

                        <div
                            class="no-scrollbar flex items-center gap-2 overflow-x-auto overflow-y-visible pb-2"
                        >
                            <button
                                type="button"
                                :class="tabClass('all')"
                                @click="activeTab = 'all'"
                            >
                                Kõik
                            </button>
                            <button
                                type="button"
                                :class="tabClass('favorites')"
                                @click="activeTab = 'favorites'"
                            >
                                Lemmikud
                            </button>
                            <div class="ml-auto shrink-0">
                                <SortDropdown
                                    v-model="selectedSort"
                                    :options="sortOptions"
                                    compact
                                />
                            </div>
                        </div>
                    </DiaryHeader>

                    <main class="flex-1 px-6 py-6 md:px-8">
                        <div
                            v-if="filteredSeeds.length === 0"
                            class="rounded-2xl border border-dashed border-primary/30 bg-primary/5 px-6 py-12 text-center"
                        >
                            <div
                                class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10"
                            >
                                <span
                                    class="material-symbols-outlined text-primary"
                                    >potted_plant</span
                                >
                            </div>
                            <h2 class="text-lg font-semibold text-foreground">
                                Selles kategoorias varusi ei ole
                            </h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Vajuta plussi, et lisada uus varu.
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="seed in filteredSeeds"
                                :key="seed.id"
                                class="rounded-2xl border border-primary/10 bg-card p-4 shadow-sm transition hover:shadow-md"
                                role="button"
                                tabindex="0"
                                @click="openSeed(seed.id)"
                                @keydown.enter.prevent="openSeed(seed.id)"
                            >
                                <div class="flex items-center gap-4">
                                    <div
                                        class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-primary/10 bg-muted/40"
                                    >
                                        <img
                                            v-if="seed.image_url"
                                            :src="seed.image_url"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center text-primary/70"
                                        >
                                            <span
                                                class="material-symbols-outlined text-2xl"
                                                >potted_plant</span
                                            >
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <h3
                                            class="truncate text-lg font-semibold"
                                        >
                                            {{ seed.name }}
                                        </h3>
                                        <p
                                            v-if="seed.year"
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Ostetud: {{ seed.year }}
                                        </p>
                                        <p
                                            v-if="seed.expires_at"
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Aegub:
                                            {{ formatDateEE(seed.expires_at) }}
                                        </p>
                                    </div>

                                    <div
                                        class="ml-2 flex shrink-0 items-center gap-2"
                                        data-seed-menu
                                        @click.stop
                                    >
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 items-center justify-center rounded-full border border-primary/10 bg-white transition hover:scale-105 hover:bg-primary/5"
                                            :class="
                                                seed.is_favorite
                                                    ? 'text-rose-600 shadow-sm'
                                                    : 'text-[#2E2E2E]/45'
                                            "
                                            @click.prevent.stop="
                                                toggleFavorite(seed.id)
                                            "
                                            aria-label="Lisa lemmikuks"
                                            :title="
                                                seed.is_favorite
                                                    ? 'Eemalda lemmikutest'
                                                    : 'Lisa lemmikuks'
                                            "
                                        >
                                            <span
                                                class="material-symbols-outlined text-[20px] leading-none transition"
                                                :style="
                                                    seed.is_favorite
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
                                                class="flex h-10 w-10 items-center justify-center rounded-full text-muted-foreground transition hover:bg-primary/10"
                                                aria-label="Menüü"
                                                @click.stop.prevent="
                                                    toggleMenu(seed.id)
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-[22px]"
                                                    >more_horiz</span
                                                >
                                            </button>

                                            <div
                                                v-if="menuOpenForId === seed.id"
                                                class="absolute top-12 right-0 z-20 w-48 overflow-hidden rounded-xl border border-primary/10 bg-card shadow-lg"
                                                @click.stop
                                            >
                                                <button
                                                    type="button"
                                                    class="w-full px-4 py-3 text-left text-sm hover:bg-primary/5"
                                                    @click.stop="
                                                        openSeedEdit(seed)
                                                    "
                                                >
                                                    Muuda
                                                </button>
                                                <button
                                                    type="button"
                                                    class="w-full px-4 py-3 text-left text-sm text-red-600 hover:bg-red-500/10"
                                                    @click.stop="
                                                        openDeleteSeed(seed);
                                                        menuOpenForId = null;
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
                <FloatingPlusButton
                    aria-label="Lisa varu"
                    :size-px="52"
                    :icon-size-px="30"
                    @click="showAddSeed = true"
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
