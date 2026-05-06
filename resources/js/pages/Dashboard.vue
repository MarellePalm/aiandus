<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import DashboardWeather from '@/components/DashboardWeather.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import Moon from '@/pages/calendarNotes/moon.vue';
import { dashboard, map } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const page = usePage();
const flash = computed(
    () =>
        (page.props.flash as
            | { success?: string | null; error?: string | null }
            | undefined) ?? {},
);

type RecentNote = {
    id: number;
    note_date: string;
    title?: string | null;
    type?: string;
    done?: boolean | null;
    media_urls?: string[];
};
const recentNotes = computed<RecentNote[]>(
    () => (page.props.recentNotes as RecentNote[] | undefined) ?? [],
);

type RecentPlant = {
    id: number;
    name: string;
    image_url?: string | null;
    created_at?: string | null;
    category?: { name: string; slug: string } | null;
};
const recentPlants = computed<RecentPlant[]>(
    () => (page.props.recentPlants as RecentPlant[] | undefined) ?? [],
);

type RecentSeed = {
    id: number;
    name: string;
    image_url?: string | null;
    created_at?: string | null;
    category?: { name: string; slug: string } | null;
};
const recentSeeds = computed<RecentSeed[]>(
    () => (page.props.recentSeeds as RecentSeed[] | undefined) ?? [],
);

type TodayTask = {
    id: number;
    note_date: string;
    title?: string | null;
    type?: string | null;
};
const todayTasks = computed<TodayTask[]>(
    () => (page.props.todayTasks as TodayTask[] | undefined) ?? [],
);

type DashboardSummary = {
    gardensCount: number;
    bedsCount: number;
    plantsCount: number;
    seedsCount: number;
    emptyBedsCount: number;
    plantsWithoutBedCount: number;
    openTasksCount: number;
    todayTasksCount: number;
    overdueTasksCount: number;
    notesCount: number;
};
const dashboardSummary = computed<DashboardSummary>(() => {
    const fallbackNotes = recentNotes.value.filter((note) => note.done === false)
        .length;

    return {
        gardensCount: 0,
        bedsCount: 0,
        plantsCount: recentPlants.value.length,
        seedsCount: recentSeeds.value.length,
        emptyBedsCount: 0,
        plantsWithoutBedCount: 0,
        openTasksCount: fallbackNotes,
        todayTasksCount: 0,
        overdueTasksCount: 0,
        notesCount: recentNotes.value.length,
        ...(page.props.dashboardSummary as Partial<DashboardSummary> | undefined),
    };
});

const overviewStats = computed(() => {
    const { gardensCount, bedsCount, plantsCount, seedsCount } =
        dashboardSummary.value;

    return [
        {
            id: 'gardens',
            label: 'Aiad',
            value: gardensCount,
            href: '/map',
            icon: 'yard',
            hint: gardensCount > 0 ? 'Ava aiaplaanid' : 'Lisa esimene aed',
        },
        {
            id: 'beds',
            label: 'Peenrad',
            value: bedsCount,
            href: map().url,
            icon: 'map',
            hint:
                bedsCount > 0
                    ? 'Vaata peenarde kaarti'
                    : 'Lisa esimene peenar kaardilt',
        },
        {
            id: 'plants',
            label: 'Taimed',
            value: plantsCount,
            href: '/plants',
            icon: 'local_florist',
            hint:
                plantsCount > 0
                    ? 'Ava taimede nimekiri'
                    : 'Aed ootab taimi',
        },
        {
            id: 'seeds',
            label: 'Varud',
            value: seedsCount,
            href: '/seeds',
            icon: 'shelves',
            hint:
                seedsCount > 0
                    ? 'Vaata varusid'
                    : 'Lisa varusid',
        },
    ];
});

const todayWorkSummary = computed(() => {
    if (dashboardSummary.value.todayTasksCount > 0) {
        return {
            title: 'Tänased aiatööd',
            value: dashboardSummary.value.todayTasksCount,
            showValue: true,
            body:
                dashboardSummary.value.todayTasksCount === 1
                    ? 'Üks tegevus ootab täna lõpetamist.'
                    : `${dashboardSummary.value.todayTasksCount} tegevust ootab täna lõpetamist.`,
            icon: 'today',
            cta: 'Ava kalender',
            href: '/calendar',
            tone: 'primary',
        };
    }

    if (dashboardSummary.value.overdueTasksCount > 0) {
        return {
            title: 'Täna saab järje peale',
            value: dashboardSummary.value.overdueTasksCount,
            showValue: true,
            body:
                dashboardSummary.value.overdueTasksCount === 1
                    ? 'Üks varasem aiatöö on veel lõpetamata.'
                    : `${dashboardSummary.value.overdueTasksCount} varasemat aiatööd on veel lõpetamata.`,
            icon: 'assignment_late',
            cta: 'Vaata tegevusi',
            href: '/calendar',
            tone: 'amber',
        };
    }

    return {
        value: 0,
        showValue: false,
        icon: 'eco',
        cta: 'Lisa märge',
        href: '/calendar/note-form',
        tone: 'green',
    };
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const todayLabel = computed(() => {
    const date = new Date();

    const weekday = new Intl.DateTimeFormat('et-EE', {
        weekday: 'long',
    }).format(date);
    const weekdayCap = weekday.charAt(0).toUpperCase() + weekday.slice(1);

    const day = new Intl.DateTimeFormat('et-EE', { day: 'numeric' }).format(
        date,
    );
    const month = new Intl.DateTimeFormat('et-EE', { month: 'long' }).format(
        date,
    );

    const time = new Intl.DateTimeFormat('et-EE', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);

    return `${weekdayCap} ${day}. ${month} • ${time}`;
});

// Järjekord: salvestatakse localStorage'i, kasutaja saab plokke üles/alla tõsta
const STORAGE_KEY = 'dashboardSectionOrder';
const COLLAPSED_KEY = 'dashboardSectionCollapsed';
type SectionId = 'garden' | 'notes' | 'weather' | 'moon';
const DEFAULT_ORDER: SectionId[] = [
    'garden',
    'notes',
    'weather',
    'moon',
];
/** Ainult need kaks on kokku-volditavad; ülejäänud plokid on alati “lahti”. */
const COLLAPSIBLE_SECTION_IDS: SectionId[] = ['weather', 'moon'];

const sectionOrder = ref<SectionId[]>([...DEFAULT_ORDER]);
const collapsedSectionIds = ref<Set<SectionId>>(new Set());
const editLayout = ref(false);
const isDesktop = ref(false);
let desktopMediaQuery: MediaQueryList | null = null;
let handleDesktopChange: ((event: MediaQueryListEvent) => void) | null = null;

const DESKTOP_SECTION_ORDER: SectionId[] = ['weather', 'moon', 'notes', 'garden'];

const displaySectionOrder = computed(() => {
    if (editLayout.value || !isDesktop.value) return sectionOrder.value;

    return DESKTOP_SECTION_ORDER.filter((id) => sectionOrder.value.includes(id));
});

function persistCollapsed(next: Iterable<SectionId>) {
    const arr = [...next];
    collapsedSectionIds.value = new Set(arr);
    try {
        localStorage.setItem(COLLAPSED_KEY, JSON.stringify(arr));
    } catch {
        /* ignore */
    }
}

function applyDefaultCollapsed() {
    // Lahti: esimesed 2 plokki (kasutaja järjekorra järgi). Kinni: ainult Ilm/Kuu, kui nad pole esimese kahe seas.
    const open = new Set(sectionOrder.value.slice(0, 2));
    const collapsed = COLLAPSIBLE_SECTION_IDS.filter((id) => !open.has(id));
    persistCollapsed(collapsed);
}

function ensureFirstTwoExpanded() {
    const mustBeOpen = new Set(sectionOrder.value.slice(0, 2));
    if (mustBeOpen.size === 0) return;

    const nextCollapsed = new Set(collapsedSectionIds.value);
    let changed = false;
    for (const id of mustBeOpen) {
        if (nextCollapsed.delete(id)) changed = true;
    }
    if (!changed) return;
    persistCollapsed(nextCollapsed);
}

function persistOrder(nextOrder: SectionId[]) {
    sectionOrder.value = [...nextOrder];
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(nextOrder));
    } catch {
        /* ignore */
    }
}

function arrayMove<T>(arr: T[], from: number, to: number) {
    if (from === to) return arr;
    const copy = [...arr];
    const [item] = copy.splice(from, 1);
    copy.splice(to, 0, item);
    return copy;
}

function canMoveSectionUp(id: SectionId): boolean {
    return sectionOrder.value.indexOf(id) > 0;
}

function canMoveSectionDown(id: SectionId): boolean {
    const index = sectionOrder.value.indexOf(id);
    return index !== -1 && index < sectionOrder.value.length - 1;
}

function moveSection(id: SectionId, direction: 'up' | 'down') {
    const from = sectionOrder.value.indexOf(id);
    if (from === -1) return;

    const to = direction === 'up' ? from - 1 : from + 1;
    if (to < 0 || to >= sectionOrder.value.length) return;

    persistOrder(arrayMove(sectionOrder.value, from, to));
}

function isSectionExpanded(id: SectionId): boolean {
    return !collapsedSectionIds.value.has(id);
}
function toggleSectionCollapsed(id: SectionId) {
    const next = new Set(collapsedSectionIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    collapsedSectionIds.value = next;
    try {
        localStorage.setItem(COLLAPSED_KEY, JSON.stringify([...next]));
    } catch {
        /* ignore */
    }
}
function sectionTitle(id: SectionId): string {
    const titles: Record<SectionId, string> = {
        garden: 'Kokkuvõte',
        notes: 'Viimased märkmed',
        weather: 'Ilm',
        moon: 'Kuufaas täna',
    };
    return titles[id];
}

onMounted(() => {
    try {
        const params = new URLSearchParams(window.location.search);
        editLayout.value = params.get('layout') === 'edit';
    } catch {
        /* ignore */
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw) as unknown;
            if (Array.isArray(parsed)) {
                const ordered = parsed.filter((id): id is SectionId =>
                    DEFAULT_ORDER.includes(id as SectionId),
                );
                const seen = new Set<SectionId>();
                const unique: SectionId[] = [];
                for (const id of ordered) {
                    if (!seen.has(id)) {
                        seen.add(id);
                        unique.push(id);
                    }
                }
                const missing = DEFAULT_ORDER.filter((id) => !seen.has(id));
                sectionOrder.value = [...missing, ...unique];
            }
        }
        const collapsedRaw = localStorage.getItem(COLLAPSED_KEY);
        if (collapsedRaw) {
            const arr = JSON.parse(collapsedRaw) as unknown;
            if (Array.isArray(arr)) {
                const valid = arr.filter(
                    (id): id is SectionId =>
                        id === 'weather' || id === 'moon',
                );
                collapsedSectionIds.value = new Set(valid);
            }
        } else {
            // Esimesel avamisel jätame lahti 2 esimest plokki (järjekorra järgi).
            const open = new Set(sectionOrder.value.slice(0, 2));
            const defaultCollapsed: SectionId[] =
                COLLAPSIBLE_SECTION_IDS.filter((id) => !open.has(id));
            collapsedSectionIds.value = new Set(defaultCollapsed);
            try {
                localStorage.setItem(
                    COLLAPSED_KEY,
                    JSON.stringify(defaultCollapsed),
                );
            } catch {
                /* ignore */
            }
        }

        // Igal avamisel hoiame 2 esimest plokki lahti (isegi kui localStorage ütleb teisiti).
        ensureFirstTwoExpanded();
    } catch {
        /* ignore */
    }

    try {
        desktopMediaQuery = window.matchMedia('(min-width: 1024px)');
        isDesktop.value = desktopMediaQuery.matches;
        handleDesktopChange = (event: MediaQueryListEvent) => {
            isDesktop.value = event.matches;
        };
        desktopMediaQuery.addEventListener('change', handleDesktopChange);
    } catch {
        /* ignore */
    }
});

onUnmounted(() => {
    if (desktopMediaQuery && handleDesktopChange) {
        desktopMediaQuery.removeEventListener('change', handleDesktopChange);
    }
});

// Lumememma-stiilis + nupp: väikesed ümarad kiirtegevused selle kohal
const showFabMenu = ref(false);
const fabActions = [
    { href: '/calendar/note-form', icon: 'edit_note', label: 'Lisa märkmed' },
    { href: '/plants/create', icon: 'local_florist', label: 'Lisa taim' },
    { href: '/seeds/create', icon: 'shelves', label: 'Lisa varu' },
    { href: map().url, icon: 'map', label: 'Lisa peenar' },
];
function closeFabMenu() {
    showFabMenu.value = false;
}
function goToFabAction(href: string) {
    closeFabMenu();
    router.visit(href);
}

/** Ühine päiseriba: ilm + kuu (sama primary/muted gradient). */
const dashboardSectionHeaderStrip =
    'from-primary/22 via-primary/10 to-muted/50 dark:from-primary/18 dark:via-muted/25 dark:to-card/95';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav flex min-h-0 flex-col bg-muted/30">
            <div
                class="border-beige/50 relative mx-auto w-full max-w-[480px] overflow-x-clip border-x bg-muted/20 shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
            >
                <DiaryHeader
                    title="Minu Aiapäevik"
                    :diary-label="todayLabel"
                    :show-diary-label="false"
                    header-class="pt-4"
                    top-row-class="mb-2"
                    bottom-row-class="mb-1"
                    footer-padding-class="pb-2"
                >
                    <div class="space-y-4">
                        <div
                            v-if="flash.success || flash.error"
                            class="space-y-2"
                        >
                            <div
                                v-if="flash.success"
                                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                            >
                                {{ flash.success }}
                            </div>
                            <div
                                v-if="flash.error"
                                class="rounded-2xl border border-destructive/25 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                            >
                                {{ flash.error }}
                            </div>
                        </div>

                        <div
                            class="rounded-[1.75rem] border border-primary/15 bg-linear-to-br from-primary/12 via-background to-secondary/35 px-3.5 py-2 shadow-sm sm:px-4 sm:py-2.5 lg:px-5 lg:py-3"
                        >
                            <p class="text-sm text-muted-foreground lg:text-[13px]">
                                {{ todayLabel }}
                            </p>

                            <div
                                v-if="todayWorkSummary.showValue || todayTasks.length"
                                class="mt-3 lg:mt-2.5"
                            >
                                <Link
                                    :href="todayWorkSummary.href"
                                    class="block rounded-[1.5rem] border border-border/70 bg-card/90 p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md lg:p-3.5"
                                    :class="{
                                        'ring-1 ring-primary/20': todayWorkSummary.tone === 'primary',
                                        'ring-1 ring-amber-200/80': todayWorkSummary.tone === 'amber',
                                        'ring-1 ring-emerald-200/80': todayWorkSummary.tone === 'green',
                                    }"
                                >
                                    <div class="flex items-start justify-between gap-3 lg:items-center">
                                        <div class="min-w-0">
                                            <p
                                                class="font-semibold text-foreground"
                                                :class="
                                                    todayWorkSummary.showValue
                                                        ? 'text-[11px] tracking-[0.16em] text-muted-foreground uppercase'
                                                        : 'text-base tracking-normal'
                                                "
                                            >
                                                {{ todayWorkSummary.title }}
                                            </p>
                                            <p
                                                v-if="todayWorkSummary.showValue"
                                                class="mt-2 text-3xl font-bold tracking-tight text-foreground lg:text-2xl"
                                            >
                                                {{ todayWorkSummary.value }}
                                            </p>
                                            <p
                                                class="text-sm text-muted-foreground"
                                                :class="
                                                    todayWorkSummary.showValue
                                                        ? 'mt-2'
                                                        : 'mt-3'
                                                "
                                            >
                                                {{ todayWorkSummary.body }}
                                            </p>
                                        </div>
                                        <span
                                            class="material-symbols-outlined rounded-2xl bg-primary/10 p-2 text-primary"
                                        >
                                            {{ todayWorkSummary.icon }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="todayTasks.length"
                                        class="mt-4 space-y-2 lg:mt-3 lg:grid lg:grid-cols-2 lg:gap-2 lg:space-y-0"
                                    >
                                        <div
                                            v-for="task in todayTasks"
                                            :key="task.id"
                                            class="flex items-center justify-between gap-3 rounded-2xl bg-muted/60 px-3 py-2"
                                        >
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-foreground">
                                                    {{ task.title || 'Tänane aiatöö' }}
                                                </p>
                                                <p class="text-xs text-muted-foreground">
                                                    {{ task.note_date }}
                                                </p>
                                            </div>
                                            <span
                                                class="material-symbols-outlined text-base text-muted-foreground"
                                            >
                                                chevron_right
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </DiaryHeader>

                <div v-if="editLayout" class="px-6 pt-3 md:px-8">
                    <div
                        class="rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
                    >
                        <p class="min-w-0">
                            <span class="font-semibold text-foreground/90"
                                >Muuda plokkide järjekorda kasutades nooli:</span
                            >
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full bg-card px-3 py-1.5 text-xs font-semibold text-foreground ring-1 ring-border transition hover:bg-muted"
                                aria-label="Taasta vaikimisi (2 esimest plokki lahti)"
                                @click="applyDefaultCollapsed"
                            >
                                <span
                                    class="material-symbols-outlined text-base"
                                    >restart_alt</span
                                >
                                Taasta
                            </button>
                            <button
                                type="button"
                                class="ml-auto inline-flex items-center gap-2 rounded-full bg-card px-3 py-1.5 text-xs font-semibold text-foreground ring-1 ring-border transition hover:bg-muted"
                                aria-label="Valmis (välju muutmisrežiimist)"
                                @click="router.visit('/dashboard')"
                            >
                                <span
                                    class="material-symbols-outlined text-base"
                                    >check</span
                                >
                                Valmis
                            </button>
                        </div>
                    </div>
                </div>

                <div class="px-6 pt-4 pb-24 md:px-8">
                    <div
                        class="space-y-4"
                        :class="
                            editLayout
                                ? ''
                                : 'lg:columns-2 lg:space-y-0 lg:[column-gap:1rem]'
                        "
                    >
                        <template v-for="id in displaySectionOrder" :key="id">
                            <!-- Aia seis -->
                            <section
                                v-if="id === 'garden'"
                                class="overflow-hidden rounded-[1.6rem] border border-border bg-card/90 shadow-sm lg:mb-4 lg:break-inside-avoid"
                                :class="editLayout ? 'ring-1 ring-primary/25' : ''"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 border-b border-border bg-linear-to-r px-4 py-3"
                                    :class="dashboardSectionHeaderStrip"
                                >
                                    <h3
                                        class="min-w-0 flex-1 text-sm font-semibold text-foreground"
                                    >
                                        {{ sectionTitle('garden') }}
                                    </h3>
                                    <div
                                        v-if="editLayout"
                                        class="flex shrink-0 items-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                            :disabled="
                                                !canMoveSectionUp('garden')
                                            "
                                            @click.stop="
                                                moveSection('garden', 'up')
                                            "
                                        >
                                            <span
                                                class="material-symbols-outlined text-sm"
                                                >arrow_upward</span
                                            >
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                            :disabled="
                                                !canMoveSectionDown('garden')
                                            "
                                            @click.stop="
                                                moveSection('garden', 'down')
                                            "
                                        >
                                            <span
                                                class="material-symbols-outlined text-sm"
                                                >arrow_downward</span
                                            >
                                        </button>
                                    </div>
                                </div>
                                <div class="p-3 lg:p-3.5">
                                    <div class="grid grid-cols-4 gap-1.5 sm:gap-2.5 lg:gap-2.5">
                                        <div
                                            v-for="stat in overviewStats"
                                            :key="stat.id"
                                            class="h-[5.5rem] min-h-0 sm:aspect-square lg:aspect-auto lg:h-28"
                                        >
                                            <Link
                                                :href="stat.href"
                                                class="flex h-full min-h-0 flex-col rounded-xl border border-border/70 bg-muted/25 p-2 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/25 hover:bg-card hover:shadow-md sm:rounded-2xl sm:p-2.5 lg:justify-between"
                                            >
                                                <div
                                                    class="flex items-start justify-between gap-2"
                                                >
                                                    <p
                                                        class="text-center text-[9px] font-semibold tracking-[0.12em] text-muted-foreground uppercase leading-tight sm:text-[11px] sm:tracking-[0.16em] lg:text-left"
                                                    >
                                                        {{ stat.label }}
                                                    </p>
                                                    <span
                                                        v-if="isDesktop"
                                                        aria-hidden="true"
                                                        class="material-symbols-outlined inline-block shrink-0 rounded-2xl bg-primary/10 p-1.5 text-[18px] leading-none text-primary"
                                                    >
                                                        {{ stat.icon }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="flex min-h-0 flex-1 items-center justify-center"
                                                >
                                                    <p
                                                        class="text-center text-lg font-bold tracking-tight text-foreground tabular-nums sm:text-xl lg:text-2xl"
                                                    >
                                                        {{ stat.value }}
                                                    </p>
                                                </div>
                                                <p
                                                    class="hidden text-center text-[11px] leading-snug text-muted-foreground lg:block"
                                                >
                                                    {{ stat.hint }}
                                                </p>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Viimased märkmed -->
                            <section
                                v-if="id === 'notes'"
                                class="overflow-hidden rounded-[1.6rem] border border-border bg-card/90 shadow-sm lg:mb-4 lg:break-inside-avoid"
                                :class="editLayout ? 'ring-1 ring-primary/25' : ''"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 border-b border-border bg-linear-to-r px-4 py-3"
                                    :class="dashboardSectionHeaderStrip"
                                >
                                    <h3
                                        class="min-w-0 flex-1 text-sm font-semibold text-foreground"
                                    >
                                        {{ sectionTitle('notes') }}
                                    </h3>
                                    <div
                                        class="flex shrink-0 items-center gap-1"
                                    >
                                        <div
                                            v-if="editLayout"
                                            class="flex items-center gap-1"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                                :disabled="
                                                    !canMoveSectionUp('notes')
                                                "
                                                @click.stop="
                                                    moveSection('notes', 'up')
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-sm"
                                                    >arrow_upward</span
                                                >
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                                :disabled="
                                                    !canMoveSectionDown(
                                                        'notes',
                                                    )
                                                "
                                                @click.stop="
                                                    moveSection(
                                                        'notes',
                                                        'down',
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-sm"
                                                    >arrow_downward</span
                                                >
                                            </button>
                                        </div>
                                        <Link
                                            v-if="!editLayout"
                                            href="/calendar/overview"
                                            class="text-sm font-medium text-primary transition hover:text-primary/80"
                                        >
                                            Ava kõik
                                        </Link>
                                    </div>
                                </div>

                                <div
                                    v-if="recentNotes.length"
                                    class="space-y-0 p-4 lg:space-y-3"
                                >
                                    <Link
                                        v-for="note in recentNotes.slice(0, 5)"
                                        :key="note.id"
                                        href="/calendar/overview"
                                        class="block border-b border-border/60 py-2.5 transition last:border-b-0 lg:rounded-2xl lg:border lg:border-border/70 lg:bg-muted/35 lg:px-3 lg:py-3 lg:hover:-translate-y-0.5 lg:hover:border-primary/25 lg:hover:bg-card"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-foreground">
                                                    {{ note.title || 'Pealkirjata märge' }}
                                                </p>
                                                <p class="mt-1 text-xs text-muted-foreground">
                                                    {{ note.note_date }}
                                                </p>
                                            </div>
                                            <div
                                                v-if="note.media_urls?.[0]"
                                                class="h-12 w-12 shrink-0 rounded-lg border border-border/70 bg-cover bg-center lg:h-14 lg:w-14"
                                                :style="{
                                                    backgroundImage: `url('${note.media_urls[0]}')`,
                                                }"
                                            />
                                        </div>
                                    </Link>
                                </div>

                                <div
                                    v-else
                                    class="m-4 rounded-2xl border border-dashed border-border bg-muted/30 px-4 py-4"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        Esimene märge aitab hiljem paremini meenutada, mis peenras toimus ja mida tasub järgmisel hooajal korrata.
                                    </p>
                                    <Link
                                        href="/calendar/note-form"
                                        class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm"
                                    >
                                        Lisa esimene märge
                                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                                    </Link>
                                </div>
                            </section>

                            <!-- Weather -->
                                <section
                                    v-if="id === 'weather'"
                                    class="overflow-hidden rounded-[1.6rem] border border-border bg-card/95 shadow-sm lg:mb-4 lg:break-inside-avoid"
                                    :class="editLayout ? 'ring-1 ring-primary/25' : ''"
                                >
                                    <div
                                        class="group flex cursor-pointer items-center justify-between gap-3 border-b border-border bg-linear-to-r px-4 py-3"
                                        :class="dashboardSectionHeaderStrip"
                                        @click="
                                            toggleSectionCollapsed('weather')
                                        "
                                    >
                                        <h3
                                            class="min-w-0 flex-1 text-sm font-semibold text-foreground"
                                        >
                                            {{ sectionTitle('weather') }}
                                        </h3>
                                        <div
                                            class="flex shrink-0 items-center gap-1"
                                        >
                                            <span
                                                v-if="editLayout"
                                                class="material-symbols-outlined text-lg text-muted-foreground transition"
                                                :class="[
                                                    'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100',
                                                    {
                                                        'rotate-180':
                                                            isSectionExpanded(
                                                                'weather',
                                                            ),
                                                    },
                                                ]"
                                                aria-hidden="true"
                                            >
                                                expand_more
                                            </span>
                                            <div
                                                v-if="editLayout"
                                                class="flex items-center gap-1"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                                    :disabled="
                                                        !canMoveSectionUp(
                                                            'weather',
                                                        )
                                                    "
                                                    @click.stop="
                                                        moveSection(
                                                            'weather',
                                                            'up',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_upward</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                                    :disabled="
                                                        !canMoveSectionDown(
                                                            'weather',
                                                        )
                                                    "
                                                    @click.stop="
                                                        moveSection(
                                                            'weather',
                                                            'down',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_downward</span
                                                    >
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-show="isSectionExpanded('weather')"
                                        class="p-3 sm:p-4"
                                    >
                                        <DashboardWeather />
                                    </div>
                                </section>

                                <!-- Moon -->
                                <section
                                    v-if="id === 'moon'"
                                    class="overflow-hidden rounded-[1.6rem] border border-border bg-card/95 shadow-sm lg:mb-4 lg:break-inside-avoid"
                                    :class="editLayout ? 'ring-1 ring-primary/25' : ''"
                                >
                                    <div
                                        class="group flex cursor-pointer items-center justify-between gap-3 border-b border-border bg-linear-to-r px-4 py-3"
                                        :class="dashboardSectionHeaderStrip"
                                        @click="toggleSectionCollapsed('moon')"
                                    >
                                        <h3
                                            class="min-w-0 flex-1 text-sm font-semibold text-foreground"
                                        >
                                            {{ sectionTitle('moon') }}
                                        </h3>
                                        <div
                                            class="flex shrink-0 items-center gap-1"
                                        >
                                            <span
                                                v-if="editLayout"
                                                class="material-symbols-outlined text-lg text-muted-foreground transition"
                                                :class="[
                                                    'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100',
                                                    {
                                                        'rotate-180':
                                                            isSectionExpanded(
                                                                'moon',
                                                            ),
                                                    },
                                                ]"
                                                aria-hidden="true"
                                            >
                                                expand_more
                                            </span>
                                            <div
                                                v-if="editLayout"
                                                class="flex items-center gap-1"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                                    :disabled="
                                                        !canMoveSectionUp(
                                                            'moon',
                                                        )
                                                    "
                                                    @click.stop="
                                                        moveSection(
                                                            'moon',
                                                            'up',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_upward</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-border bg-background/70 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                                    :disabled="
                                                        !canMoveSectionDown(
                                                            'moon',
                                                        )
                                                    "
                                                    @click.stop="
                                                        moveSection(
                                                            'moon',
                                                            'down',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >arrow_downward</span
                                                    >
                                                </button>
                                            </div>
                                            <Link
                                                v-if="!editLayout"
                                                href="/calendar/moon"
                                                class="text-sm font-medium text-primary transition hover:text-primary/80"
                                                @click.stop
                                            >
                                                Ava kõik
                                            </Link>
                                        </div>
                                    </div>
                                    <div
                                        v-show="isSectionExpanded('moon')"
                                        class="p-4 sm:p-5"
                                    >
                                        <Moon />
                                    </div>
                                </section>
                            </template>
                    </div>
                </div>
            </div>

            <!-- Lumememma-stiilis + nupp: kiirtegevused ümardatud ikoonidena selle kohal -->
            <div
                class="fixed right-4 bottom-24 z-30 flex flex-col-reverse items-end gap-3"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-2"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 translate-y-2"
                >
                    <div
                        v-if="showFabMenu"
                        class="flex flex-col-reverse items-center gap-2"
                    >
                        <button
                            v-for="action in fabActions"
                            :key="action.href"
                            type="button"
                            :aria-label="action.label"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-border bg-card text-primary shadow-md transition hover:scale-110 hover:bg-muted active:scale-95"
                            @click="goToFabAction(action.href)"
                        >
                            <span class="material-symbols-outlined text-xl">{{
                                action.icon
                            }}</span>
                        </button>
                    </div>
                </Transition>
                <button
                    type="button"
                    aria-label="Lisa (märkmed, taim, varud, peenar)"
                    :aria-expanded="showFabMenu"
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:scale-105 active:scale-95"
                    @click="showFabMenu = !showFabMenu"
                >
                    <span class="material-symbols-outlined text-3xl font-light"
                        >add</span
                    >
                </button>
            </div>
            <div
                v-if="showFabMenu"
                class="fixed inset-0 z-20 bg-black/20"
                aria-hidden="true"
                @click="closeFabMenu"
            />

            <BottomNav active="dashboard" />
        </div>
    </AppLayout>
</template>
