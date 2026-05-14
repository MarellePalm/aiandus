<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
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
    quantity: number;
    quantity_in_stock?: number;
    quantity_on_beds?: number;
    bed_locations?: {
        bed_id: number;
        bed_name: string;
        garden_plan_id: number;
        image_url?: string | null;
        quantity: number;
    }[];
    calendar_notes?: {
        id: number;
        note_date: string;
        title: string | null;
        body: string;
    }[];
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

const fallbackImage = '/logo.png';

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

const bedLocations = computed(() => props.plant.bed_locations ?? []);

/** Kokku sama sorti taimi (varu + peenrad), nagu varem üks number. */
const quantityCardShow = computed(() => {
    if (
        typeof props.plant.quantity_in_stock === 'number' &&
        typeof props.plant.quantity_on_beds === 'number'
    ) {
        return (
            props.plant.quantity_in_stock + props.plant.quantity_on_beds
        );
    }

    return props.plant.quantity;
});

const hasNotes = computed(
    () =>
        !!props.plant.notes?.trim() ||
        ((props.plant.calendar_notes?.length ?? 0) > 0),
);

const calendarNotesList = computed(
    () => props.plant.calendar_notes ?? [],
);

function formatCalendarNoteDate(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return iso;
    }
    return d.toLocaleDateString('et-EE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function calendarNoteHref(noteId: number): string {
    return `/calendar/notes/${noteId}?return_to=${encodeURIComponent(`/plants/${props.plant.id}`)}`;
}

function calendarNoteTitle(cn: {
    title: string | null;
    body: string;
}): string {
    const t = cn.title?.trim();
    if (t) {
        return t;
    }
    const b = cn.body?.trim();
    if (b) {
        return b.length > 72 ? `${b.slice(0, 69).trimEnd()}…` : b;
    }
    return 'Märge';
}

const wateringText = computed(() => {
    return props.plant.watering_frequency?.trim() || 'Pole määratud';
});

const menuOpen = ref(false);
const bedMenuOpenId = ref<number | null>(null);
const deleteOpen = ref(false);
const deleting = ref(false);

function togglePlantMenu() {
    menuOpen.value = !menuOpen.value;
    if (menuOpen.value) {
        bedMenuOpenId.value = null;
    }
}

function toggleBedMenu(bedId: number) {
    const next = bedMenuOpenId.value === bedId ? null : bedId;
    bedMenuOpenId.value = next;
    if (next !== null) {
        menuOpen.value = false;
    }
}

function goToBedFromMenu(loc: {
    bed_id: number;
    garden_plan_id: number;
}) {
    bedMenuOpenId.value = null;
    router.visit(`/beds/${loc.bed_id}`);
}

function goToGardenPlanFromMenu(loc: { garden_plan_id: number }) {
    bedMenuOpenId.value = null;
    router.visit(`/map/${loc.garden_plan_id}`);
}

function onBedImageError(event: Event) {
    const img = event.target as HTMLImageElement;
    if (img.src.endsWith(fallbackImage) || img.src.includes('/logo.png')) {
        return;
    }
    img.src = fallbackImage;
}

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
    const t = e.target as HTMLElement | null;
    if (menuOpen.value && !t?.closest?.('[data-plant-menu]')) {
        menuOpen.value = false;
    }
    if (
        bedMenuOpenId.value !== null &&
        !t?.closest?.('[data-bed-card-menu]')
    ) {
        bedMenuOpenId.value = null;
    }
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
        class="bg-background-light dark:bg-background-dark font-display min-h-screen text-foreground antialiased"
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
                            @click.stop="togglePlantMenu"
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
                            @error="
                                ($event.target as HTMLImageElement).src =
                                    fallbackImage
                            "
                        />
                        <div class="matte-overlay absolute inset-0"></div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div
                class="relative z-10 px-6 pt-6 pb-16 md:px-12 md:pt-8 md:pb-12"
            >
                <div class="md:mx-auto md:max-w-4xl">
                    <div
                        class="flex flex-col gap-6 md:grid md:grid-cols-2 md:gap-6"
                    >
                        <!-- MÄRKMED -->
                        <div class="md:col-span-2">
                            <div
                                v-if="hasNotes"
                                class="bg-surface-light dark:bg-surface-dark rounded-2xl border border-[#e6e2d5] p-5 shadow-sm dark:border-white/5"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-start gap-4"
                                    >
                                        <div
                                            class="rounded-xl bg-primary/10 p-3 text-primary shrink-0 dark:bg-primary/20"
                                        >
                                            <span
                                                class="material-symbols-outlined"
                                                >edit_note</span
                                            >
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span
                                                class="mb-0.5 block text-xs font-bold tracking-wider text-gray-400 uppercase"
                                            >
                                                Märkmed
                                            </span>
                                            <p
                                                v-if="props.plant.notes?.trim()"
                                                class="font-body mt-1 text-base leading-relaxed text-[#4a524a] dark:text-gray-300"
                                            >
                                                {{ props.plant.notes }}
                                            </p>
                                            <div
                                                v-if="props.plant.tags?.length"
                                                class="mt-3 flex flex-wrap gap-2"
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
                                    <button
                                        type="button"
                                        class="shrink-0 text-sm font-semibold text-primary transition hover:text-primary/80"
                                        @click="editNotes"
                                    >
                                        Muuda
                                    </button>
                                </div>
                            </div>

                            <button
                                v-else
                                type="button"
                                class="bg-surface-light dark:bg-surface-dark w-full rounded-2xl border border-[#e6e2d5] p-5 text-left shadow-sm transition hover:border-primary/30 hover:bg-primary/5 dark:border-white/5 dark:hover:bg-primary/10"
                                @click="editNotes"
                            >
                                <div class="flex items-center gap-4">
                                    <div
                                        class="rounded-xl bg-primary/10 p-3 text-primary dark:bg-primary/20"
                                    >
                                        <span class="material-symbols-outlined"
                                            >edit_note</span
                                        >
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <span
                                            class="mb-0.5 block text-xs font-bold tracking-wider text-gray-400 uppercase"
                                        >
                                            Märkmed
                                        </span>
                                        <span
                                            class="font-body block text-base leading-tight font-medium text-foreground"
                                        >
                                            Lisa märge
                                        </span>
                                        <span
                                            class="font-body mt-1 block text-xs leading-snug text-muted-foreground"
                                        >
                                            Meenuta hiljem kasvu ja järgmise
                                            hooaja nippe.
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <!-- KALENDRI MÄRKMED (sama ridade stiil kui peenrad) -->
                        <div
                            v-if="calendarNotesList.length"
                            class="md:col-span-2"
                        >
                            <div
                                class="rounded-2xl border border-[#e6e2d5] bg-surface-light shadow-sm dark:border-white/5 dark:bg-surface-dark"
                            >
                                <p
                                    class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-muted-foreground sm:px-4"
                                >
                                    Kalendri märkmed
                                </p>
                                <ul
                                    class="divide-y divide-[#e6e2d5] dark:divide-white/10"
                                >
                                    <li
                                        v-for="cn in calendarNotesList"
                                        :key="cn.id"
                                    >
                                        <Link
                                            :href="calendarNoteHref(cn.id)"
                                            class="flex items-center justify-between gap-2 px-3 py-2.5 transition hover:bg-muted/35 sm:gap-3 sm:px-4 sm:py-3"
                                        >
                                            <div
                                                class="flex min-w-0 flex-1 items-center gap-3"
                                            >
                                                <div
                                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary sm:h-12 sm:w-12 dark:bg-primary/20"
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-[22px] sm:text-[24px]"
                                                        >calendar_month</span
                                                    >
                                                </div>
                                                <div
                                                    class="min-w-0 flex flex-col text-left"
                                                >
                                                    <span
                                                        class="font-body truncate text-[15px] leading-snug font-medium text-foreground"
                                                        >{{
                                                            calendarNoteTitle(cn)
                                                        }}</span
                                                    >
                                                    <span
                                                        class="font-body mt-0.5 text-xs leading-tight text-[#717a71] dark:text-gray-400"
                                                        >{{
                                                            formatCalendarNoteDate(
                                                                cn.note_date,
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="pointer-events-none flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border/80 bg-white shadow-sm ring-1 ring-black/[0.04] sm:h-10 sm:w-10 dark:border-white/15 dark:bg-card dark:ring-white/10"
                                            >
                                                <span
                                                    class="material-symbols-outlined text-[22px] text-primary sm:text-[24px]"
                                                    aria-hidden="true"
                                                    >more_horiz</span
                                                >
                                            </div>
                                        </Link>
                                    </li>
                                </ul>
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
                                            {{ quantityCardShow }} tk
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

                        <!-- PEENRAD -->
                        <div
                            v-if="bedLocations.length > 0"
                            class="md:col-span-2"
                        >
                            <div
                                class="rounded-2xl border border-[#e6e2d5] bg-surface-light shadow-sm dark:border-white/5 dark:bg-surface-dark"
                            >
                                <ul
                                    class="divide-y divide-[#e6e2d5] dark:divide-white/10"
                                >
                                    <li
                                        v-for="loc in bedLocations"
                                        :key="loc.bed_id"
                                        class="flex items-center justify-between gap-2 px-3 py-2.5 last:pb-3 sm:gap-3 sm:px-4 sm:last:pb-4"
                                    >
                                        <div
                                            class="flex min-w-0 flex-1 items-center gap-3"
                                        >
                                            <div
                                                v-if="
                                                    loc.image_url &&
                                                    loc.image_url.trim() !== ''
                                                "
                                                class="h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-[#e6e2d5] bg-muted/20 dark:border-white/10 sm:h-12 sm:w-12"
                                            >
                                                <img
                                                    :src="loc.image_url"
                                                    :alt="loc.bed_name"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                    @error="onBedImageError"
                                                />
                                            </div>
                                            <div
                                                v-else
                                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary sm:h-12 sm:w-12 dark:bg-primary/20"
                                            >
                                                <span
                                                    class="material-symbols-outlined text-[22px] sm:text-[24px]"
                                                    >yard</span
                                                >
                                            </div>
                                            <div class="min-w-0 flex flex-col">
                                                <span
                                                    class="font-body text-[15px] leading-snug font-medium text-foreground"
                                                >
                                                    {{ loc.bed_name }}
                                                </span>
                                                <span
                                                    class="font-body mt-0.5 text-xs leading-tight text-[#717a71] dark:text-gray-400"
                                                >
                                                    {{ loc.quantity }} tk
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            class="relative shrink-0"
                                            data-bed-card-menu
                                        >
                                            <button
                                                type="button"
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border/80 bg-white shadow-sm ring-1 ring-black/[0.04] transition hover:border-primary/40 hover:bg-primary/8 sm:h-10 sm:w-10 dark:border-white/15 dark:bg-card dark:ring-white/10 dark:hover:bg-primary/15"
                                                :aria-label="`Menüü: ${loc.bed_name}`"
                                                @click.stop="
                                                    toggleBedMenu(loc.bed_id)
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-[22px] text-primary sm:text-[24px]"
                                                    >more_horiz</span
                                                >
                                            </button>

                                            <div
                                                v-if="
                                                    bedMenuOpenId ===
                                                    loc.bed_id
                                                "
                                                class="absolute top-10 right-0 z-50 w-48 overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-border sm:top-11"
                                                @click.stop
                                            >
                                                <button
                                                    type="button"
                                                    class="w-full px-4 py-2.5 text-left text-sm text-foreground hover:bg-black/5 dark:hover:bg-white/5"
                                                    @click="
                                                        goToBedFromMenu(loc)
                                                    "
                                                >
                                                    Ava peenar
                                                </button>
                                                <button
                                                    type="button"
                                                    class="w-full px-4 py-2.5 text-left text-sm text-foreground hover:bg-black/5 dark:hover:bg-white/5"
                                                    @click="
                                                        goToGardenPlanFromMenu(
                                                            loc,
                                                        )
                                                    "
                                                >
                                                    Aiaplaan
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
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
