<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';

type Plant = {
    id: number;
    name: string;
    subtitle?: string;
    image_url?: string;
    notes?: string;
    tags?: string[];
    watering_frequency?: string | null;
    fertilizing_frequency?: string | null;
    next_fertilizing_label?: string | null;
    category_slug?: string;
    quantity?: number | null;
};

const props = withDefaults(
    defineProps<{
        plant: Plant;
        backUrl?: string | null;
    }>(),
    {
        backUrl: null,
    },
);

const fallbackImage = 'https://picsum.photos/900/1200';

function goBack() {
    if (props.plant.category_slug) {
        router.visit(`/plants/category/${props.plant.category_slug}`);
    } else {
        router.visit('/plants');
    }
}

const backHref = computed(() => {
    if (props.plant.category_slug) {
        return `/plants/category/${props.plant.category_slug}`;
    }
    return '/plants';
});

const hasFertilizingInfo = computed(() => {
    return (
        !!props.plant.fertilizing_frequency ||
        !!props.plant.next_fertilizing_label
    );
});

const wateringText = computed(() => {
    return props.plant.watering_frequency?.trim() || 'Pole määratud';
});

const menuOpen = ref(false);
const deleteOpen = ref(false);
const deleting = ref(false);

const openDelete = () => {
    menuOpen.value = false;
    deleteOpen.value = true;
};

const closeDelete = () => {
    deleteOpen.value = false;
    deleting.value = false;
};

const editPlant = () => {
    menuOpen.value = false;

    const url = props.backUrl
        ? `/plants/${props.plant.id}/edit?back=${encodeURIComponent(props.backUrl)}`
        : `/plants/${props.plant.id}/edit`;

    router.visit(url);
};

const editNotes = () => {
    router.visit(`/plants/${props.plant.id}/edit`);
};
const doDelete = () => {
    if (deleting.value) return;
    deleting.value = true;

    router.delete(`/plants/${props.plant.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeDelete();
            goBack();
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};

const onDocClick = (e: MouseEvent) => {
    if (!menuOpen.value) return;
    const t = e.target as HTMLElement | null;
    if (t?.closest?.('[data-plant-menu]')) return;
    menuOpen.value = false;
};

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<style scoped>
.material-symbols-outlined {
    font-variation-settings:
        'FILL' 0,
        'wght' 300,
        'GRAD' 0,
        'opsz' 24;
}

.matte-overlay {
    background: linear-gradient(
        to bottom,
        rgba(250, 248, 244, 0) 0%,
        rgba(250, 248, 244, 0.1) 70%,
        rgba(250, 248, 244, 1) 100%
    );
}

:global(.dark) .matte-overlay {
    background: linear-gradient(
        to bottom,
        rgba(36, 42, 34, 0) 0%,
        rgba(36, 42, 34, 0.1) 70%,
        rgba(36, 42, 34, 1) 100%
    );
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>

<template>
    <div
        class="bg-background-light dark:bg-background-dark font-display min-h-screen text-[#141514] antialiased dark:text-gray-100"
    >
        <div class="relative flex w-full flex-col pt-20">
            <!-- Top App Bar -->
            <div
                class="bg-background-light/80 fixed top-0 right-0 left-0 z-50 backdrop-blur-md"
            >
                <div
                    class="flex w-full items-center justify-between px-4 py-3 md:px-6"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <BackIconButton
                            :href="backHref"
                            aria-label="Tagasi kategooriasse"
                        />
                        <h1
                            class="text-forest max-w-[14rem] truncate text-left text-xl font-bold tracking-tight sm:max-w-[20rem]"
                        >
                            {{ props.plant.subtitle }}
                        </h1>
                    </div>

                    <div class="relative" data-plant-menu>
                        <button
                            type="button"
                            class="flex items-center justify-center rounded-full bg-card/70 p-2 backdrop-blur-md transition-colors"
                            @click.stop="menuOpen = !menuOpen"
                            aria-label="Menüü"
                        >
                            <span class="material-symbols-outlined"
                                >more_vert</span
                            >
                        </button>

                        <div
                            v-if="menuOpen"
                            class="absolute top-12 right-0 z-50 w-44 overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-border"
                        >
                            <button
                                type="button"
                                class="w-full px-4 py-3 text-left text-sm text-foreground hover:bg-black/5"
                                @click="editPlant"
                            >
                                Muuda taime
                            </button>

                            <button
                                type="button"
                                class="w-full px-4 py-3 text-left text-sm text-foreground hover:bg-black/5"
                                @click="openDelete"
                            >
                                Kustuta
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HERO -->
            <div class="w-full px-4 md:px-0">
                <div
                    class="overflow-hidden rounded-3xl md:mx-auto md:max-w-4xl"
                >
                    <div
                        class="relative h-[42vh] w-full md:h-[46vh] lg:h-[420px]"
                    >
                        <img
                            :src="props.plant.image_url || fallbackImage"
                            alt="Taime pilt"
                            class="absolute inset-0 h-full w-full object-contain"
                            loading="lazy"
                        />
                        <div class="matte-overlay absolute inset-0"></div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="relative z-10 px-6 pt-6 md:px-12 md:pt-8">
                <div class="md:mx-auto md:max-w-4xl">
                    <div
                        class="flex flex-col gap-6 md:grid md:grid-cols-2 md:gap-6"
                    >
                        <!-- MÄRKMED -->
                        <div class="md:col-span-2">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-bold tracking-tight">
                                    Märkmed
                                </h3>
                                <button
                                    type="button"
                                    class="text-sm font-semibold text-primary"
                                    @click="editNotes"
                                >
                                    Muuda
                                </button>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-card/60 p-6"
                            >
                                <p
                                    class="font-body leading-relaxed text-[#4a524a] dark:text-gray-300"
                                >
                                    {{
                                        props.plant.notes ||
                                        'Märkmeid veel pole.'
                                    }}
                                </p>

                                <div
                                    v-if="props.plant.tags?.length"
                                    class="mt-4 flex flex-wrap gap-2"
                                >
                                    <span
                                        v-for="tag in props.plant.tags"
                                        :key="tag"
                                        class="rounded-full bg-primary/5 px-3 py-1 text-[11px] font-bold tracking-tighter text-primary uppercase dark:bg-primary/10"
                                    >
                                        {{ tag }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- TAIMEDE ARV -->
                        <div
                            class="bg-surface-light dark:bg-surface-dark rounded-2xl border border-[#e6e2d5] p-5 shadow-sm dark:border-white/5"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="rounded-xl bg-primary/10 p-3 text-primary dark:bg-primary/20"
                                    >
                                        <span class="material-symbols-outlined"
                                            >inventory_2</span
                                        >
                                    </div>

                                    <div class="flex flex-col">
                                        <span
                                            class="mb-0.5 text-xs font-bold tracking-wider text-gray-400 uppercase"
                                        >
                                            Taimede arv
                                        </span>
                                        <span
                                            class="font-body text-base leading-tight font-medium"
                                        >
                                            {{ props.plant.quantity ?? 1 }} tk
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KASTMINE -->
                        <div
                            class="bg-surface-light dark:bg-surface-dark rounded-2xl border border-[#e6e2d5] p-5 shadow-sm dark:border-white/5"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="rounded-xl bg-primary/10 p-3 text-primary dark:bg-primary/20"
                                    >
                                        <span class="material-symbols-outlined"
                                            >opacity</span
                                        >
                                    </div>

                                    <div class="flex flex-col">
                                        <span
                                            class="mb-0.5 text-xs font-bold tracking-wider text-gray-400 uppercase"
                                        >
                                            Kastmine
                                        </span>
                                        <span
                                            class="font-body text-base leading-tight font-medium"
                                        >
                                            {{ wateringText }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VÄETAMINE -->
                        <div
                            v-if="hasFertilizingInfo"
                            class="bg-surface-light dark:bg-surface-dark rounded-2xl border border-[#e6e2d5] p-5 shadow-sm dark:border-white/5"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="rounded-xl bg-primary/10 p-3 text-primary dark:bg-primary/20"
                                    >
                                        <span class="material-symbols-outlined"
                                            >potted_plant</span
                                        >
                                    </div>

                                    <div class="flex flex-col">
                                        <span
                                            class="mb-0.5 text-xs font-bold tracking-wider text-gray-400 uppercase"
                                        >
                                            Väetamine
                                        </span>

                                        <span
                                            class="font-body text-base leading-tight font-medium"
                                        >
                                            <template
                                                v-if="
                                                    props.plant
                                                        .fertilizing_frequency
                                                "
                                            >
                                                {{
                                                    props.plant
                                                        .fertilizing_frequency
                                                }}
                                            </template>

                                            <span
                                                v-if="
                                                    props.plant
                                                        .next_fertilizing_label
                                                "
                                                class="ml-1 text-sm text-[#717a71]"
                                            >
                                                (Järgmine:
                                                {{
                                                    props.plant
                                                        .next_fertilizing_label
                                                }})
                                            </span>
                                        </span>
                                    </div>
                                </div>

                                <div class="shrink-0 text-[#717a71]">
                                    <span
                                        class="material-symbols-outlined text-[20px]"
                                        >calendar_today</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DELETE CONFIRM MODAL -->
        <Teleport to="body">
            <transition name="fade">
                <div
                    v-if="deleteOpen"
                    class="fixed inset-0 z-[70] flex items-center justify-center p-4"
                    aria-modal="true"
                    role="dialog"
                >
                    <button
                        type="button"
                        class="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
                        aria-label="Sulge taustal"
                        @click="closeDelete"
                    />

                    <div
                        class="relative w-full max-w-sm rounded-3xl bg-card shadow-xl ring-1 ring-border"
                    >
                        <div
                            class="pointer-events-none absolute -top-3 -left-3 opacity-20"
                        >
                            <div class="h-10 w-10 rounded-full bg-[#E6E2D5]" />
                        </div>

                        <div class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3
                                        class="text-lg font-semibold text-foreground"
                                    >
                                        Kustuta taim?
                                    </h3>
                                    <p class="mt-1 text-sm text-foreground/70">
                                        {{ props.plant.name }} eemaldatakse
                                        jäädavalt.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-full p-2 text-foreground/60 hover:bg-black/5 hover:text-foreground"
                                    aria-label="Sulge"
                                    @click="closeDelete"
                                >
                                    ✕
                                </button>
                            </div>

                            <div class="mt-6 flex flex-col gap-3">
                                <button
                                    type="button"
                                    class="rounded-2xl bg-red-600 px-4 py-3 font-medium text-white shadow-sm transition hover:bg-red-700 disabled:opacity-50"
                                    :disabled="deleting"
                                    @click="doDelete"
                                >
                                    {{
                                        deleting
                                            ? 'Kustutan...'
                                            : 'Jah, kustuta'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    class="text-sm text-foreground/60 hover:text-foreground"
                                    :disabled="deleting"
                                    @click="closeDelete"
                                >
                                    Tühista
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
