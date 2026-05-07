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
    due_at?: string | null;
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

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 11) return 'Tere hommikust';
    if (hour < 17) return 'Tere päevast';
    return 'Tere õhtust';
});

const dashboardHighlights = computed(() => {
    const summary = dashboardSummary.value;
    const totalBeds = Math.max(1, summary.bedsCount);
    const activeBeds = Math.max(0, summary.bedsCount - summary.emptyBedsCount);
    const bedCoverage = Math.min(100, Math.round((activeBeds / totalBeds) * 100));
    const plantsPlaced = Math.max(
        0,
        summary.plantsCount - summary.plantsWithoutBedCount,
    );
    const plantPlacement = summary.plantsCount
        ? Math.min(100, Math.round((plantsPlaced / summary.plantsCount) * 100))
        : 0;
    const tasksClosed = Math.max(
        0,
        summary.openTasksCount - summary.overdueTasksCount,
    );
    const taskPace = summary.openTasksCount
        ? Math.min(100, Math.round((tasksClosed / summary.openTasksCount) * 100))
        : 100;

    return [
        {
            id: 'beds',
            label: 'Peenrad aktiivsed',
            value: `${bedCoverage}%`,
            hint: `${activeBeds}/${summary.bedsCount} kasutuses`,
            icon: 'grid_view',
            progress: bedCoverage,
        },
        {
            id: 'plants',
            label: 'Taimed paigas',
            value: `${plantPlacement}%`,
            hint: `${plantsPlaced}/${summary.plantsCount} peenardes`,
            icon: 'local_florist',
            progress: plantPlacement,
        },
        {
            id: 'tasks',
            label: 'Töötempo',
            value: `${taskPace}%`,
            hint:
                summary.openTasksCount > 0
                    ? `${summary.overdueTasksCount} vajab tähelepanu`
                    : 'Kõik tegevused kontrolli all',
            icon: 'task_alt',
            progress: taskPace,
        },
    ];
});

function noteRelativeDate(noteDate?: string | null): string {
    if (!noteDate) return '';
    const d = new Date(noteDate);
    if (Number.isNaN(d.getTime())) return noteDate;

    const startOfDay = (date: Date) =>
        new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const today = startOfDay(new Date());
    const that = startOfDay(d);
    const diffDays = Math.round((today - that) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Täna';
    if (diffDays === 1) return 'Eile';
    if (diffDays === -1) return 'Homme';
    if (diffDays > 1 && diffDays < 7) return `${diffDays} päeva tagasi`;
    if (diffDays >= 7 && diffDays < 14) return 'Eelmisel nädalal';
    if (diffDays < 0 && diffDays > -7) return `${Math.abs(diffDays)} päeva pärast`;

    return new Intl.DateTimeFormat('et-EE', {
        day: 'numeric',
        month: 'short',
    }).format(d);
}

function noteAccentClass(note: RecentNote): string {
    if (note.done === true) return 'bg-emerald-400';
    if (note.done === false) return 'bg-amber-400';
    return 'bg-primary/60';
}

function noteDateChipClass(noteDate?: string | null): string {
    if (!noteDate) return 'bg-muted text-muted-foreground';
    const d = new Date(noteDate);
    if (Number.isNaN(d.getTime())) return 'bg-muted text-muted-foreground';
    const startOfDay = (date: Date) =>
        new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const diffDays = Math.round(
        (startOfDay(new Date()) - startOfDay(d)) / (1000 * 60 * 60 * 24),
    );
    if (diffDays === 0)
        return 'bg-primary/15 text-primary ring-1 ring-primary/25';
    if (diffDays === 1)
        return 'bg-amber-500/10 text-amber-700 ring-1 ring-amber-500/20 dark:text-amber-300';
    if (diffDays >= 2 && diffDays < 7)
        return 'bg-sky-500/10 text-sky-700 ring-1 ring-sky-500/20 dark:text-sky-300';
    return 'bg-muted/80 text-muted-foreground ring-1 ring-border/60';
}

function noteRowToneClass(note: RecentNote): string {
    if (!note.note_date) return '';
    const d = new Date(note.note_date);
    if (Number.isNaN(d.getTime())) return '';
    const startOfDay = (date: Date) =>
        new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const diffDays = Math.round(
        (startOfDay(new Date()) - startOfDay(d)) / (1000 * 60 * 60 * 24),
    );
    if (diffDays === 0) {
        return 'bg-linear-to-r from-primary/12 via-primary/5 to-transparent border-primary/30';
    }
    if (diffDays === 1) {
        return 'bg-linear-to-r from-amber-300/15 via-amber-200/6 to-transparent border-amber-400/25';
    }
    if (diffDays >= 2 && diffDays < 7) {
        return 'bg-linear-to-r from-sky-300/12 via-sky-200/5 to-transparent border-sky-400/25';
    }
    if (diffDays >= 7 && diffDays < 21) {
        return 'bg-linear-to-r from-violet-300/10 via-violet-200/4 to-transparent border-violet-400/20';
    }
    return 'bg-linear-to-r from-muted/40 to-transparent';
}

const recentNotePhotos = computed(() =>
    recentNotes.value
        .flatMap((note) => note.media_urls ?? [])
        .filter((url) => typeof url === 'string' && url.length > 0)
        .slice(0, 6),
);

const notePhotoTimeline = computed(() =>
    recentNotes.value
        .flatMap((note) =>
            (note.media_urls ?? []).map((url, index) => ({
                url,
                noteDate: note.note_date,
                key: `${note.id}-${index}-${url}`,
            })),
        )
        .filter((item) => typeof item.url === 'string' && item.url.length > 0)
        .slice(0, 6),
);

// Wow-efekt: ühine sissetulekuanimatsiooni progress (0 → 1) – animeerib
// numbreid ja edenemisribasid sujuvalt sisse esimesel laadimisel.
const appearProgress = ref(0);
let appearRaf = 0;
const animatedHighlights = computed(() =>
    dashboardHighlights.value.map((item) => ({
        ...item,
        animatedProgress: item.progress * appearProgress.value,
        animatedLabel: `${Math.round(item.progress * appearProgress.value)}%`,
    })),
);
const animatedTodayValue = computed(() =>
    Math.round(Number(todayWorkSummary.value.value) * appearProgress.value),
);

// Järjekord: salvestatakse localStorage'i, kasutaja saab plokke üles/alla tõsta
const STORAGE_KEY = 'dashboardSectionOrder';
const COLLAPSED_KEY = 'dashboardSectionCollapsed';
type SectionId = 'notes' | 'weather' | 'moon';
const DEFAULT_ORDER: SectionId[] = [
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

const DESKTOP_SECTION_ORDER: SectionId[] = ['weather', 'moon', 'notes'];

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
        notes: 'Viimased märkmed',
        weather: 'Ilm',
        moon: 'Kuufaas täna',
    };
    return titles[id];
}

function startAppearAnimation() {
    try {
        const reduce = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        if (reduce) {
            appearProgress.value = 1;
            return;
        }
    } catch {
        appearProgress.value = 1;
        return;
    }

    const start = performance.now();
    const duration = 1400;
    const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);

    const tick = (now: number) => {
        const elapsed = now - start;
        const t = Math.min(1, elapsed / duration);
        appearProgress.value = easeOutCubic(t);
        if (t < 1) appearRaf = requestAnimationFrame(tick);
    };
    appearRaf = requestAnimationFrame(tick);
}

onMounted(() => {
    startAppearAnimation();

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
    if (appearRaf) cancelAnimationFrame(appearRaf);
});

// Õrnad hõljuvad lehed tervituskaardil (ambient).
const wowLeaves = [
    { icon: 'eco', left: '6%', top: '18%', size: 'text-base', delay: '0s', duration: '7s' },
    { icon: 'spa', left: '88%', top: '24%', size: 'text-lg', delay: '1.2s', duration: '9s' },
    { icon: 'local_florist', left: '22%', top: '68%', size: 'text-sm', delay: '2.5s', duration: '8s' },
    { icon: 'eco', left: '72%', top: '78%', size: 'text-xl', delay: '0.8s', duration: '11s' },
    { icon: 'spa', left: '52%', top: '6%', size: 'text-sm', delay: '3.2s', duration: '7.5s' },
    { icon: 'local_florist', left: '14%', top: '88%', size: 'text-base', delay: '1.8s', duration: '10s' },
] as const;

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
    <Head title="Töölaud" />

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
                            class="wow-hero wow-fade-up relative overflow-hidden rounded-[1.7rem] border border-primary/20 bg-linear-to-br from-primary/16 via-background to-primary/6 px-3.5 py-3 shadow-[0_12px_24px_rgba(43,74,52,0.1)] sm:px-4 sm:py-3.5"
                            style="--wow-delay: 0ms"
                        >
                            <div class="wow-blob wow-blob-a pointer-events-none absolute -top-12 -right-10 h-32 w-32 rounded-full bg-primary/15 blur-2xl" />
                            <div class="wow-blob wow-blob-b pointer-events-none absolute -bottom-14 -left-10 h-28 w-28 rounded-full bg-emerald-300/20 blur-2xl" />

                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="(leaf, leafIndex) in wowLeaves"
                                    :key="`leaf-${leafIndex}`"
                                    class="wow-leaf material-symbols-outlined absolute"
                                    :class="leaf.size"
                                    :style="{
                                        left: leaf.left,
                                        top: leaf.top,
                                        animationDelay: leaf.delay,
                                        animationDuration: leaf.duration,
                                    }"
                                >
                                    {{ leaf.icon }}
                                </span>
                            </div>

                            <div class="wow-shimmer pointer-events-none absolute inset-0" aria-hidden="true" />

                            <div class="relative z-10">
                                <p class="text-xs font-semibold tracking-[0.16em] text-primary/75 uppercase">
                                    {{ greeting }}
                                </p>
                                <h2 class="mt-1 text-lg font-bold tracking-tight text-foreground sm:text-xl">
                                    Sinu aiapäeviku tänane ülevaade
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground lg:text-[13px]">
                                    {{ todayLabel }}
                                </p>
                                <div class="mt-3 grid grid-cols-3 gap-1.5 sm:gap-2">
                                    <div
                                        v-for="(item, itemIndex) in animatedHighlights"
                                        :key="item.id"
                                        class="wow-fade-up rounded-xl border border-border/60 bg-card/85 px-2.5 py-2 shadow-sm"
                                        :style="{ '--wow-delay': `${120 + itemIndex * 90}ms` }"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-[10px] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
                                                {{ item.label }}
                                            </p>
                                            <span
                                                v-if="isDesktop"
                                                class="material-symbols-outlined text-sm text-primary/80"
                                            >
                                                {{ item.icon }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-lg font-bold tracking-tight text-foreground tabular-nums">
                                            {{ item.animatedLabel }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                                            {{ item.hint }}
                                        </p>
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted/70">
                                            <div
                                                class="h-full rounded-full bg-linear-to-r from-primary/70 via-primary to-emerald-400/80"
                                                :style="{ width: `${item.animatedProgress}%` }"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="recentNotePhotos.length"
                                    class="mt-3 rounded-2xl border border-border/60 bg-card/85 p-3"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <p class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                            Viimased aiapildid
                                        </p>
                                        <Link
                                            href="/calendar/overview"
                                            class="text-xs font-medium text-primary transition hover:text-primary/80"
                                        >
                                            Ava märkmed
                                        </Link>
                                    </div>
                                    <div class="flex gap-2.5 overflow-x-auto pb-1">
                                        <div
                                            v-for="(photo, index) in notePhotoTimeline"
                                            :key="photo.key"
                                            class="relative shrink-0 overflow-hidden rounded-xl border border-border/70 bg-muted/30"
                                            :class="
                                                index === 0
                                                    ? 'h-24 w-28 sm:h-28 sm:w-36'
                                                    : 'h-20 w-20 sm:h-24 sm:w-24'
                                            "
                                        >
                                            <img
                                                :src="photo.url"
                                                alt="Aiapilt"
                                                class="h-full w-full object-cover transition-transform duration-300 ease-out hover:scale-105"
                                                loading="lazy"
                                            />
                                            <span
                                                class="absolute bottom-1 left-1 rounded-full bg-black/45 px-1.5 py-0.5 text-[10px] font-medium text-white"
                                            >
                                                {{ photo.noteDate }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="todayWorkSummary.showValue || todayTasks.length"
                                    class="mt-3"
                                >
                                    <Link
                                        :href="todayWorkSummary.href"
                                        class="block rounded-[1.2rem] border border-border/70 bg-card/90 p-3.5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                    {{ todayWorkSummary.title }}
                                                </p>
                                                <p
                                                    v-if="todayWorkSummary.showValue"
                                                    class="mt-1.5 text-2xl font-bold tracking-tight text-foreground tabular-nums"
                                                >
                                                    {{ animatedTodayValue }}
                                                </p>
                                                <p class="mt-1.5 text-sm text-muted-foreground">
                                                    {{ todayWorkSummary.body }}
                                                </p>
                                            </div>
                                            <span
                                                v-if="isDesktop"
                                                class="material-symbols-outlined rounded-2xl bg-primary/10 p-2 text-primary"
                                            >
                                                {{ todayWorkSummary.icon }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="todayTasks.length"
                                            class="mt-4 space-y-2 lg:grid lg:grid-cols-2 lg:gap-2 lg:space-y-0"
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

                <div class="px-6 pt-4 pb-4 md:px-8">
                    <div
                        class="space-y-4"
                        :class="
                            editLayout
                                ? ''
                                : 'lg:columns-2 lg:space-y-0 lg:[column-gap:1rem]'
                        "
                    >
                        <template v-for="(id, sectionIndex) in displaySectionOrder" :key="id">
                            <!-- Viimased märkmed -->
                            <section
                                v-if="id === 'notes'"
                                class="wow-fade-up overflow-hidden rounded-[1.6rem] border border-border bg-card/90 shadow-sm lg:mb-4 lg:break-inside-avoid"
                                :class="editLayout ? 'ring-1 ring-primary/25' : ''"
                                :style="{ '--wow-delay': `${260 + sectionIndex * 110}ms` }"
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
                                    class="note-paper space-y-1.5 p-2.5 lg:space-y-2 lg:p-3"
                                >
                                    <Link
                                        v-for="(note, noteIndex) in recentNotes.slice(0, 5)"
                                        :key="note.id"
                                        href="/calendar/overview"
                                        class="note-row group relative block overflow-hidden rounded-xl border border-border/60 bg-card/85 px-2.5 py-2 transition hover:-translate-y-0.5 hover:border-primary/30 hover:bg-card hover:shadow-[0_4px_14px_rgba(43,74,52,0.12)] lg:rounded-2xl lg:px-3 lg:py-2.5"
                                        :class="noteRowToneClass(note)"
                                        :style="{
                                            '--note-row-delay': `${noteIndex * 60}ms`,
                                        }"
                                    >
                                        <span
                                            class="absolute inset-y-0 left-0 w-0.5 rounded-r-full sm:w-1"
                                            :class="noteAccentClass(note)"
                                            aria-hidden="true"
                                        />

                                        <div
                                            class="flex items-center gap-2.5 pl-1 sm:pl-1.5"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex min-w-0 items-center gap-1.5"
                                                >
                                                    <span
                                                        v-if="note.due_at"
                                                        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary ring-1 ring-primary/20 sm:h-7 sm:w-7"
                                                        title="Meeldetuletus"
                                                        aria-label="Meeldetuletus"
                                                    >
                                                        <span
                                                            class="material-symbols-outlined text-[16px] sm:text-[18px]"
                                                            aria-hidden="true"
                                                        >
                                                            notifications
                                                        </span>
                                                    </span>
                                                    <span
                                                        class="min-w-0 truncate text-[13px] leading-tight font-semibold text-foreground sm:text-sm"
                                                        :class="
                                                            note.done === true
                                                                ? 'line-through decoration-emerald-500/50 decoration-1'
                                                                : ''
                                                        "
                                                    >
                                                        {{
                                                            note.title ||
                                                            'Pealkirjata märge'
                                                        }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="mt-1 flex items-center gap-1.5"
                                                >
                                                    <span
                                                        class="inline-flex shrink-0 items-center rounded-full px-1.5 py-px text-[10px] font-semibold tracking-wide"
                                                        :class="
                                                            noteDateChipClass(
                                                                note.note_date,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            noteRelativeDate(
                                                                note.note_date,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="note.done === true"
                                                        class="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-emerald-500/10 px-1.5 py-px text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-300"
                                                    >
                                                        <span
                                                            class="material-symbols-outlined text-[11px]"
                                                            aria-hidden="true"
                                                            >check</span
                                                        >
                                                        Tehtud
                                                    </span>
                                                </div>
                                            </div>
                                            <div
                                                v-if="note.media_urls?.[0]"
                                                class="note-thumb relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border-2 border-card bg-cover bg-center shadow-md ring-1 ring-border/50 sm:h-14 sm:w-14"
                                                :style="{
                                                    backgroundImage: `url('${note.media_urls[0]}')`,
                                                }"
                                            >
                                                <span
                                                    class="absolute inset-0 bg-linear-to-t from-black/30 via-transparent to-transparent"
                                                    aria-hidden="true"
                                                />
                                            </div>
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
                                    class="wow-fade-up overflow-hidden rounded-[1.6rem] border border-border bg-card/95 shadow-sm lg:mb-4 lg:break-inside-avoid"
                                    :class="editLayout ? 'ring-1 ring-primary/25' : ''"
                                    :style="{ '--wow-delay': `${260 + sectionIndex * 110}ms` }"
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
                                    class="wow-fade-up overflow-hidden rounded-[1.6rem] border border-border bg-card/95 shadow-sm lg:mb-4 lg:break-inside-avoid"
                                    :class="editLayout ? 'ring-1 ring-primary/25' : ''"
                                    :style="{ '--wow-delay': `${260 + sectionIndex * 110}ms` }"
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
                                    <div v-show="isSectionExpanded('moon')">
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
                    class="wow-fab relative flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:scale-105 active:scale-95"
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

<style scoped>
.wow-fade-up {
    opacity: 0;
    transform: translate3d(0, 14px, 0);
    animation: wow-fade-up 700ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--wow-delay, 0ms);
    will-change: opacity, transform;
}

@keyframes wow-fade-up {
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.wow-leaf {
    color: rgb(34 110 79 / 0.38);
    animation-name: wow-leaf-float;
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
    will-change: transform, opacity;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.04));
}

:global(.dark) .wow-leaf {
    color: rgb(134 200 165 / 0.32);
}

@keyframes wow-leaf-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0) rotate(-4deg);
        opacity: 0.45;
    }
    50% {
        transform: translate3d(6px, -18px, 0) rotate(10deg);
        opacity: 0.75;
    }
}

.wow-blob {
    animation: wow-blob-breath 9s ease-in-out infinite;
    will-change: transform, opacity;
}
.wow-blob-b {
    animation-duration: 12s;
    animation-delay: 1.4s;
}
@keyframes wow-blob-breath {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.85;
    }
    50% {
        transform: scale(1.12);
        opacity: 1;
    }
}

.wow-shimmer::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        110deg,
        transparent 0%,
        transparent 35%,
        rgba(255, 255, 255, 0.55) 50%,
        transparent 65%,
        transparent 100%
    );
    transform: translateX(-150%);
    animation: wow-shimmer 1700ms ease-out 650ms 1 forwards;
    mix-blend-mode: overlay;
    pointer-events: none;
}
@keyframes wow-shimmer {
    to {
        transform: translateX(150%);
    }
}

.note-paper {
    background-image:
        radial-gradient(
            circle at 1px 1px,
            rgba(43, 74, 52, 0.05) 1px,
            transparent 0
        );
    background-size: 14px 14px;
    background-position: 0 0;
}
:global(.dark) .note-paper {
    background-image: radial-gradient(
        circle at 1px 1px,
        rgba(255, 255, 255, 0.04) 1px,
        transparent 0
    );
}

.note-row {
    opacity: 0;
    transform: translate3d(0, 8px, 0);
    animation: note-row-in 520ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: calc(var(--note-row-delay, 0ms) + 320ms);
    will-change: opacity, transform;
}
.note-row:hover .note-thumb {
    transform: rotate(-3deg) scale(1.06);
}
.note-thumb {
    transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes note-row-in {
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.wow-fab::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    box-shadow: 0 0 0 0 rgba(74, 124, 89, 0.55);
    animation: wow-fab-pulse 2.6s ease-out infinite;
    pointer-events: none;
}
@keyframes wow-fab-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(74, 124, 89, 0.45);
    }
    70% {
        box-shadow: 0 0 0 14px rgba(74, 124, 89, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(74, 124, 89, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .wow-fade-up,
    .wow-leaf,
    .wow-blob,
    .wow-shimmer::after,
    .wow-fab::before,
    .note-row {
        animation: none !important;
    }
    .note-row {
        opacity: 1;
        transform: none;
    }
    .wow-fade-up {
        opacity: 1;
        transform: none;
    }
}
</style>
