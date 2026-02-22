<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';

import BottomNav from '../BottomNav.vue';

type PlantStatus = 'SAAGIKORISTUS' | 'ÕITSEB' | 'ISTIK' | 'PUHKEPERIOOD';

type PlantItem = {
    id: number;
    name: string;
    planted_at: string; // "dd.mm.yyyy"
    status: PlantStatus;
    image_url?: string | null;
};

const props = defineProps<{
    category: { name: string; slug: string };
    plants: PlantItem[];
}>();

type TabKey = 'all' | 'growing' | 'harvest';
const activeTab = ref<TabKey>('all');

const filteredPlants = computed(() => {
    let list = props.plants ?? [];

    if (activeTab.value === 'growing') {
        list = list.filter(
            (p) => p.status !== 'SAAGIKORISTUS' && p.status !== 'PUHKEPERIOOD',
        );
    }

    if (activeTab.value === 'harvest') {
        list = list.filter((p) => p.status === 'SAAGIKORISTUS');
    }

    return list;
});

const tabClass = (key: TabKey) => {
    const base =
        'px-5 py-2 rounded-full text-sm font-medium whitespace-nowrap transition';
    if (activeTab.value === key) return `${base} bg-primary text-white`;
    return `${base} bg-primary/10 text-primary`;
};

const statusStyles = (s: PlantStatus) => {
    switch (s) {
        case 'SAAGIKORISTUS':
            return { dot: 'bg-primary', text: 'text-primary' };
        case 'ÕITSEB':
            return { dot: 'bg-amber-400', text: 'text-amber-600' };
        case 'ISTIK':
            return { dot: 'bg-blue-400', text: 'text-blue-500' };
        default:
            return { dot: 'bg-gray-400', text: 'text-gray-500' };
    }
};

const goBack = () => router.visit('/plants');

// suuna sinu olemasolevale "sordi lisamise" lehele.
// muuda URL ära, kui sul on teistsugune.
const openAddPlant = () =>
    router.visit(`/plants/create?category=${props.category.slug}`);
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
                    <!-- Header -->
                    <header
                        class="bg-background-light/80 sticky top-0 z-30 border-b border-primary/10 px-4 py-3 backdrop-blur-md"
                    >
                        <div class="flex items-center justify-between">
                            <button
                                class="flex items-center gap-1 font-medium text-primary"
                                type="button"
                                @click="goBack"
                            >
                                <span
                                    class="material-symbols-outlined text-[24px]"
                                    >chevron_left</span
                                >
                                <span class="text-sm">Kategooriad</span>
                            </button>

                            <h1 class="text-lg font-bold tracking-tight">
                                {{ props.category.name }}
                            </h1>

                            <div class="flex items-center gap-5">
                                <!-- Search -->
                                <button
                                    class="flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/10"
                                    type="button"
                                    aria-label="Otsi"
                                >
                                    <span
                                        class="material-symbols-outlined text-[24px]"
                                        >search</span
                                    >
                                </button>

                                <!-- Add -->
                                <button
                                    type="button"
                                    @click="openAddPlant"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white shadow-sm transition hover:scale-105 active:scale-95"
                                    aria-label="Lisa sort"
                                >
                                    <span
                                        class="material-symbols-outlined text-[20px]"
                                        >add</span
                                    >
                                </button>
                            </div>
                        </div>
                    </header>

                    <main class="pb-24">
                        <!-- Tabs -->
                        <div class="px-4 py-6">
                            <div
                                class="no-scrollbar flex gap-3 overflow-x-auto pb-2"
                            >
                                <button
                                    type="button"
                                    :class="tabClass('all')"
                                    @click="activeTab = 'all'"
                                >
                                    Kõik taimed
                                </button>
                                <button
                                    type="button"
                                    :class="tabClass('growing')"
                                    @click="activeTab = 'growing'"
                                >
                                    Kasvavad
                                </button>
                                <button
                                    type="button"
                                    :class="tabClass('harvest')"
                                    @click="activeTab = 'harvest'"
                                >
                                    Saagikoristus
                                </button>
                            </div>
                        </div>

                        <!-- Cards -->
                        <div class="space-y-4 px-4">
                            <div
                                v-for="p in filteredPlants"
                                :key="p.id"
                                class="flex gap-4 rounded-xl border border-primary/5 bg-white/90 p-4 shadow-[0_4px_20px_rgba(107,141,104,0.08)]"
                            >
                                <div
                                    class="h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100"
                                >
                                    <img
                                        v-if="p.image_url"
                                        class="h-full w-full object-cover opacity-90 contrast-[0.9] saturate-[0.8]"
                                        :src="p.image_url"
                                        :alt="p.name"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-xs text-[#2E2E2E]/40"
                                    >
                                        Pole pilti
                                    </div>
                                </div>

                                <div
                                    class="flex min-w-0 flex-1 flex-col justify-between py-0.5"
                                >
                                    <div>
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <h2
                                                class="serif-italic text-text-main truncate text-xl"
                                            >
                                                {{ p.name }}
                                            </h2>

                                            <button
                                                class="text-sm text-gray-300 hover:text-gray-400"
                                                type="button"
                                                aria-label="Rohkem"
                                            >
                                                <span
                                                    class="material-symbols-outlined"
                                                    >more_horiz</span
                                                >
                                            </button>
                                        </div>

                                        <p
                                            class="mt-1 text-[13px] text-gray-500"
                                        >
                                            Istutatud: {{ p.planted_at }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="statusStyles(p.status).dot"
                                        ></span>
                                        <span
                                            class="text-xs font-semibold tracking-wider uppercase"
                                            :class="statusStyles(p.status).text"
                                        >
                                            {{ p.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <p
                                v-if="filteredPlants.length === 0"
                                class="px-1 text-sm text-[#2E2E2E]/60"
                            >
                                Siin kategoorias pole veel taimi.
                            </p>
                        </div>
                    </main>
                </div>

                <BottomNav active="plants" />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.serif-italic {
    font-family: 'Playfair Display', serif;
    font-style: italic;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
