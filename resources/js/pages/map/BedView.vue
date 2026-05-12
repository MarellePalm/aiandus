<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import type { AppPageProps } from '@/types';

type PlantInBed = {
    id: number;
    name: string;
    image_url: string | null;
    position_in_bed: string | null;
    quantity: number;
};
type Bed = {
    id: number;
    garden_plan_id: number;
    name: string;
    location: string | null;
    image_url?: string | null;
    rows: number;
    columns: number;
    layout?: number[][] | null;
    plants: PlantInBed[];
};
type PlantWithoutBed = {
    id: number;
    name: string;
    image_url: string | null;
    quantity: number;
    category?: { name: string; slug: string } | null;
};
type BedNote = {
    id: number;
    note_date: string | null;
    title: string | null;
    body: string | null;
    type: string | null;
    done: boolean | null;
};

const props = defineProps<{
    bed: Bed;
    plantsWithoutBed: PlantWithoutBed[];
    bedNotes?: BedNote[];
}>();

type BedPageProps = AppPageProps<{
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}>;

const page = usePage<BedPageProps>();
const mapHref = `/map/${props.bed.garden_plan_id}`;

const breadcrumbs = [
    { title: 'Aiaplaan', href: mapHref },
    { title: props.bed.name, href: `/beds/${props.bed.id}` },
];

const cellModal = ref<{ row: number; col: number } | null>(null);
const selectedPlantQuantity = ref(1);
const coverTick = ref(0);
const inlineFeedback = ref<{
    tone: 'success' | 'error';
    message: string;
} | null>(null);
let coverTimer: ReturnType<typeof setInterval> | null = null;
let inlineFeedbackTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    coverTimer = setInterval(() => {
        coverTick.value += 1;
    }, 3500);
});

onBeforeUnmount(() => {
    if (coverTimer) clearInterval(coverTimer);
    if (inlineFeedbackTimer) clearTimeout(inlineFeedbackTimer);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

watch(cellModal, (value) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = value ? 'hidden' : '';
    }
    if (!value) {
        return;
    }
    const existing = plantAt(value.row, value.col);
    selectedPlantQuantity.value = existing?.quantity ?? 1;
});

function bedCoverImage(): string | null {
    if (props.bed.image_url) return props.bed.image_url;
    const images = props.bed.plants
        .map((p) => p.image_url)
        .filter((x): x is string => Boolean(x));
    if (!images.length) return null;
    return images[coverTick.value % images.length];
}

function range(n: number): number[] {
    return Array.from({ length: n }, (_, i) => i);
}

function getBedLayout(): number[][] {
    const L = props.bed.layout;
    if (
        L &&
        Array.isArray(L) &&
        L.length > 0 &&
        L.some((row) => Array.isArray(row) && row.length > 0)
    ) {
        return L as number[][];
    }
    return Array.from({ length: props.bed.rows || 1 }, () =>
        Array.from({ length: props.bed.columns || 1 }, () => 1),
    );
}

function getBedColumns(): number {
    const layout = getBedLayout();
    if (layout.length === 0) return 1;
    return Math.max(...layout.map((r) => r.length), 1);
}

const bedCellSize = computed(() => {
    const cols = getBedColumns();
    if (cols >= 10) return 42;
    if (cols >= 8) return 48;
    if (cols >= 6) return 56;
    return 64;
});

function plantAt(row: number, col: number): PlantInBed | undefined {
    const key = `${row},${col}`;
    return props.bed.plants.find((p) => p.position_in_bed === key);
}

function plantsWithoutCell(): PlantInBed[] {
    return props.bed.plants.filter(
        (p) => !p.position_in_bed || !/^\d+,\d+$/.test(p.position_in_bed),
    );
}

function removePlantFromBed(plant: PlantInBed) {
    router.put(
        `/plants/${plant.id}`,
        { bed_id: null, position_in_bed: null },
        {
            preserveScroll: true,
            onSuccess: () => {
                setInlineFeedback('success', 'Taim eemaldati peenralt.');
            },
            onError: () => {
                setInlineFeedback(
                    'error',
                    'Taime eemaldamine ei õnnestunud. Proovi uuesti.',
                );
            },
        },
    );
}

function assignPlantToCell(plantId: number, row: number, col: number) {
    const key = `${row},${col}`;
    router.put(
        `/plants/${plantId}`,
        {
            bed_id: props.bed.id,
            position_in_bed: key,
            quantity: Math.max(1, Math.round(selectedPlantQuantity.value || 1)),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                cellModal.value = null;
                selectedPlantQuantity.value = 1;
                setInlineFeedback('success', 'Taim lisati valitud ruutu.');
            },
            onError: () => {
                setInlineFeedback(
                    'error',
                    'Taime lisamine ei õnnestunud. Proovi uuesti.',
                );
            },
        },
    );
}

function updatePlantQuantityInCell(plantId: number, row: number, col: number) {
    const key = `${row},${col}`;
    router.put(
        `/plants/${plantId}`,
        {
            bed_id: props.bed.id,
            position_in_bed: key,
            quantity: Math.max(1, Math.round(selectedPlantQuantity.value || 1)),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                cellModal.value = null;
                selectedPlantQuantity.value = 1;
                setInlineFeedback('success', 'Taime kogus uuendatud.');
            },
            onError: () => {
                setInlineFeedback(
                    'error',
                    'Taime kogust ei õnnestunud uuendada. Proovi uuesti.',
                );
            },
        },
    );
}

const plantsWithoutBedByCategory = computed(() => {
    const map = new Map<string, PlantWithoutBed[]>();
    for (const p of props.plantsWithoutBed) {
        const key = p.category?.name ?? 'Kategooriata';
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(p);
    }
    return Array.from(map.entries()).sort((a, b) =>
        a[0].localeCompare(b[0], 'et'),
    );
});
const availablePlantsCount = computed(() => props.plantsWithoutBed.length);
const cellModalLabel = computed(() => {
    if (!cellModal.value) return '';
    return `Rida ${cellModal.value.row + 1}, veerg ${cellModal.value.col + 1}`;
});
const modalPlant = computed(() => {
    if (!cellModal.value) return null;
    return plantAt(cellModal.value.row, cellModal.value.col) ?? null;
});
const totalBedCells = computed(() =>
    getBedLayout().reduce(
        (sum, row) => sum + row.filter((cell) => cell === 1).length,
        0,
    ),
);
const plantedCellCount = computed(() => {
    const positions = new Set(
        props.bed.plants
            .map((plant) => plant.position_in_bed)
            .filter((position): position is string => Boolean(position)),
    );

    return positions.size;
});
const emptyCellCount = computed(() =>
    Math.max(0, totalBedCells.value - plantedCellCount.value),
);
const bedStatus = computed(() => {
    if (!hasPlantsInBed.value) {
        return {
            title: 'Alusta esimesest taimest',
            description:
                'Peenar on valmis. Järgmine hea samm on paigutada esimene taim sobivasse ruutu.',
            actionLabel: availablePlantsCount.value
                ? 'Lisa taim peenrasse'
                : 'Loo uus taim',
            actionType: availablePlantsCount.value
                ? 'add-first-plant'
                : 'create-plant',
        };
    }

    if (emptyCellCount.value > 0) {
        return {
            title: 'Peenras on veel vaba ruumi',
            description: `Vabu ruute on ${emptyCellCount.value}. Saad jätkata istutamist või jätta ruumi järgmise külvi jaoks.`,
            actionLabel: availablePlantsCount.value
                ? 'Täida järgmine ruut'
                : 'Loo uus taim',
            actionType: availablePlantsCount.value
                ? 'fill-next-cell'
                : 'create-plant',
        };
    }

    return {
        title: 'Peenar on praegu täidetud',
        description:
            'Järgmine mõistlik samm on lisada märge, kontrollida taimede seisu või avada kalender järgmiste tööde jaoks.',
        actionLabel: 'Lisa märge',
        actionType: 'create-note',
    };
});
const latestBedNote = computed(() => props.bedNotes?.[0] ?? null);

function formatNoteDate(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('et-EE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const gridLayout = computed(() => getBedLayout());
const hasPlantsInBed = computed(() => props.bed.plants.length > 0);
const pageFeedback = computed(() => {
    if (inlineFeedback.value) return inlineFeedback.value;
    if (page.props.flash?.error) {
        return { tone: 'error' as const, message: page.props.flash.error };
    }
    if (page.props.flash?.success) {
        return { tone: 'success' as const, message: page.props.flash.success };
    }
    return null;
});

function setInlineFeedback(tone: 'success' | 'error', message: string) {
    inlineFeedback.value = { tone, message };
    if (inlineFeedbackTimer) clearTimeout(inlineFeedbackTimer);
    inlineFeedbackTimer = setTimeout(() => {
        inlineFeedback.value = null;
    }, 2600);
}

function openFirstAvailableCell() {
    const layout = getBedLayout();

    for (let row = 0; row < layout.length; row += 1) {
        for (let col = 0; col < (layout[row]?.length ?? 0); col += 1) {
            if ((layout[row]?.[col] ?? 0) === 1 && !plantAt(row, col)) {
                cellModal.value = { row, col };
                return;
            }
        }
    }
}

function openCellModal(row: number, col: number) {
    cellModal.value = { row, col };
}

function plantedCellClass(row: number, col: number): string {
    const plant = plantAt(row, col);
    if (!plant) return '';

    if (plant.image_url) {
        return 'border-emerald-700/25 shadow-[0_8px_20px_rgba(52,93,63,0.22)]';
    }

    return 'border-emerald-700/35 bg-[radial-gradient(circle_at_top,rgba(208,234,198,0.95),rgba(143,184,124,0.95))] shadow-[0_8px_20px_rgba(58,102,67,0.2)]';
}

function emptyCellClass(row: number, col: number): string {
    const checker = (row + col) % 2 === 0;
    return checker
        ? 'border-emerald-900/20 bg-[linear-gradient(145deg,rgba(236,247,228,0.98),rgba(219,236,208,0.95))]'
        : 'border-emerald-900/15 bg-[linear-gradient(145deg,rgba(231,243,222,0.98),rgba(212,230,200,0.94))]';
}

function changeSelectedPlantQuantity(delta: number) {
    selectedPlantQuantity.value = Math.max(
        1,
        Math.min(99, Math.round(selectedPlantQuantity.value + delta)),
    );
}

function handleBedStatusAction() {
    if (bedStatus.value.actionType === 'add-first-plant') {
        openFirstAvailableCell();
        return;
    }

    if (bedStatus.value.actionType === 'fill-next-cell') {
        openFirstAvailableCell();
        return;
    }

    if (bedStatus.value.actionType === 'create-note') {
        router.get(
            `/calendar/note-form?bed_id=${props.bed.id}&return_to=${encodeURIComponent(`/beds/${props.bed.id}`)}`,
        );
        return;
    }

    router.get('/plants/create');
}
</script>

<template>
    <Head :title="bed.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title=""
                        header-class="pt-6"
                        top-row-class="mb-2"
                        bottom-row-class="mb-4"
                    >
                        <template #leading>
                            <BackIconButton
                                :href="mapHref"
                                aria-label="Tagasi peenarde loendisse"
                            />
                        </template>
                        <template #actions>
                            <Link
                                :href="`/beds/${bed.id}/edit`"
                                class="inline-flex min-h-11 max-w-full items-center justify-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 text-sm font-semibold text-primary transition hover:bg-primary/15 sm:h-10 sm:min-h-0 sm:gap-2 sm:px-4"
                                aria-label="Muuda peenart"
                            >
                                <span
                                    class="material-symbols-outlined shrink-0 text-xl leading-none"
                                    >edit</span
                                >
                                <span class="truncate sm:inline"
                                    >Muuda peenart</span
                                >
                            </Link>
                        </template>
                    </DiaryHeader>

                    <main class="flex-1 space-y-4 px-4 py-3 sm:px-6 md:px-8">
                        <div
                            v-if="pageFeedback"
                            class="rounded-[1.5rem] border px-4 py-3 shadow-sm"
                            :class="
                                pageFeedback.tone === 'error'
                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            "
                        >
                            <p class="text-sm font-medium">
                                {{ pageFeedback.message }}
                            </p>
                        </div>

                        <section
                            class="relative overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm"
                        >
                            <div
                                v-if="bed.image_url"
                                class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-[0.18]"
                                :style="{
                                    backgroundImage: `url('${bed.image_url}')`,
                                }"
                                aria-hidden="true"
                            />
                            <div
                                v-if="bed.image_url"
                                class="pointer-events-none absolute inset-0 bg-background/62"
                                aria-hidden="true"
                            />
                            <div
                                class="relative z-10 h-32 overflow-hidden bg-cover bg-center ring-1 ring-black/10 sm:h-36"
                                :style="
                                    bedCoverImage()
                                        ? {
                                              backgroundImage: `url('${bedCoverImage()}')`,
                                          }
                                        : {}
                                "
                            >
                                <div
                                    v-if="!bedCoverImage()"
                                    class="absolute inset-0 flex items-center justify-center bg-linear-to-br from-primary/20 via-muted/30 to-primary/10"
                                >
                                    <span
                                        class="material-symbols-outlined text-5xl text-primary"
                                        >grass</span
                                    >
                                </div>
                                <div
                                    class="pointer-events-none absolute inset-0 bg-linear-to-b from-black/18 via-transparent to-black/35"
                                />
                                <div
                                    class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full border border-white/30 bg-black/25 px-2.5 py-1 text-[11px] font-semibold text-white/95 backdrop-blur-[2px]"
                                >
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        >yard</span
                                    >
                                    Peenar
                                </div>
                                <div
                                    class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/85 via-black/52 to-transparent p-3.5"
                                >
                                    <h2
                                        class="text-xl font-semibold tracking-tight text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.45)]"
                                    >
                                        {{ bed.name }}
                                    </h2>
                                    <p
                                        v-if="bed.location"
                                        class="mt-1 inline-flex items-center gap-1 text-sm text-white/90"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[15px]"
                                            >location_on</span
                                        >
                                        {{ bed.location }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="!hasPlantsInBed"
                            class="rounded-2xl border border-primary/20 bg-primary/6 p-4 shadow-sm"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="max-w-2xl">
                                    <h3
                                        class="text-base font-semibold text-foreground"
                                    >
                                        Peenar on veel tühi.
                                    </h3>
                                    <p
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        Lisa esimene taim sobivasse ruutu, et
                                        peenar hakkaks päriselt kuju võtma.
                                    </p>
                                </div>
                                <button
                                    v-if="plantsWithoutBed.length"
                                    type="button"
                                    class="inline-flex min-h-11 items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0 sm:py-2"
                                    @click="openFirstAvailableCell"
                                >
                                    Lisa esimene taim
                                </button>
                                <Link
                                    v-else
                                    href="/plants/create"
                                    class="inline-flex min-h-11 items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0 sm:py-2"
                                >
                                    Loo uus taim
                                </Link>
                            </div>
                        </section>

                        <section
                            class="rounded-2xl border border-border/70 bg-card/95 p-4 shadow-sm"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        Peenra kokkuvõte
                                    </p>
                                    <h3
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ bedStatus.title }}
                                    </h3>
                                    <p
                                        class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ bedStatus.description }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-primary bg-primary px-3.5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0 sm:w-auto"
                                    @click="handleBedStatusAction"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >arrow_forward</span
                                    >
                                    {{ bedStatus.actionLabel }}
                                </button>
                            </div>

                            <div
                                class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Taimi peenras
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ bed.plants.length }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Täidetud ruudud
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ plantedCellCount }} /
                                        {{ totalBedCells }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Vabad ruudud
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold text-foreground"
                                    >
                                        {{ emptyCellCount }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Viimane märge
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            latestBedNote?.title ||
                                            'Märkmeid veel ei ole'
                                        }}
                                    </p>
                                    <p
                                        v-if="latestBedNote?.note_date"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatNoteDate(
                                                latestBedNote.note_date,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-2xl border border-border/70 bg-card p-3 shadow-sm sm:p-4"
                        >
                            <div
                                class="mb-3 flex flex-col gap-3 px-1 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Peenra ruudustik
                                    </h3>
                                    <p
                                        class="mt-0.5 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        Puuduta ruutu, et lisada, muuta või
                                        eemaldada taim.
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl border border-border/70 bg-background/70 px-3 py-2 text-center shadow-xs sm:min-w-[5.5rem] sm:text-right"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Vabu taimi
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-foreground"
                                    >
                                        {{ availablePlantsCount }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="custom-scrollbar -mx-1 touch-pan-x overflow-x-auto overflow-y-visible overscroll-x-contain px-1 pb-1"
                            >
                                <div
                                    class="inline-grid w-max min-w-0 gap-2 rounded-2xl border border-primary/20 bg-linear-to-br from-primary/6 via-background to-muted/20 p-3 ring-1 shadow-soft ring-primary/10 sm:gap-2.5 sm:p-4"
                                    :style="{
                                        gridTemplateColumns: `repeat(${getBedColumns()}, ${bedCellSize}px)`,
                                        gridTemplateRows: `repeat(${gridLayout.length}, ${bedCellSize}px)`,
                                    }"
                                >
                                    <template
                                        v-for="r in range(gridLayout.length)"
                                        :key="r"
                                    >
                                        <template
                                            v-for="c in range(getBedColumns())"
                                            :key="`${r}-${c}`"
                                        >
                                            <div
                                                v-if="plantAt(r, c)"
                                                role="button"
                                                tabindex="0"
                                                class="group relative cursor-pointer touch-manipulation overflow-hidden rounded-xl border-2 text-left transition-transform duration-200 outline-none hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:ring-offset-2 active:scale-[0.98]"
                                                :class="plantedCellClass(r, c)"
                                                :style="{
                                                    width: `${bedCellSize}px`,
                                                    height: `${bedCellSize}px`,
                                                }"
                                                :aria-label="`Ruut: ${plantAt(r, c)?.name ?? 'taim'}. Ava üksikasjad.`"
                                                @click="openCellModal(r, c)"
                                                @keydown.enter.prevent="
                                                    openCellModal(r, c)
                                                "
                                                @keydown.space.prevent="
                                                    openCellModal(r, c)
                                                "
                                            >
                                                <div
                                                    class="absolute inset-0 bg-cover bg-center"
                                                    :style="
                                                        plantAt(r, c)?.image_url
                                                            ? {
                                                                  backgroundImage: `url('${plantAt(r, c)?.image_url}')`,
                                                              }
                                                            : {}
                                                    "
                                                />
                                                <div
                                                    class="absolute inset-0 bg-linear-to-t from-black/75 via-black/20 to-transparent"
                                                />
                                                <div
                                                    class="pointer-events-none absolute inset-0 opacity-[0.22]"
                                                    style="
                                                        background-image:
                                                            radial-gradient(
                                                                circle at 18%
                                                                    22%,
                                                                rgba(
                                                                        255,
                                                                        255,
                                                                        255,
                                                                        0.45
                                                                    )
                                                                    0,
                                                                rgba(
                                                                        255,
                                                                        255,
                                                                        255,
                                                                        0.02
                                                                    )
                                                                    38%
                                                            ),
                                                            radial-gradient(
                                                                circle at 84%
                                                                    76%,
                                                                rgba(
                                                                        255,
                                                                        255,
                                                                        255,
                                                                        0.24
                                                                    )
                                                                    0,
                                                                rgba(
                                                                        255,
                                                                        255,
                                                                        255,
                                                                        0.02
                                                                    )
                                                                    42%
                                                            );
                                                    "
                                                />
                                                <span
                                                    class="absolute right-1.5 bottom-1.5 left-1.5 truncate text-[11px] font-semibold text-white"
                                                    >{{
                                                        plantAt(r, c)?.name
                                                    }}</span
                                                >
                                                <span
                                                    class="absolute top-1.5 left-1.5 rounded-full bg-white/92 px-1.5 py-0.5 text-[10px] font-semibold text-foreground shadow-sm dark:bg-card/92"
                                                >
                                                    {{
                                                        plantAt(r, c)
                                                            ?.quantity ?? 1
                                                    }}
                                                    tk
                                                </span>
                                                <button
                                                    type="button"
                                                    class="absolute top-1 right-1 z-20 flex min-h-9 min-w-9 items-center justify-center rounded-full bg-black/60 p-1.5 text-white opacity-100 shadow-sm md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                                                    aria-label="Eemalda taim ruudust"
                                                    @click.stop="
                                                        removePlantFromBed(
                                                            plantAt(r, c)!,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >close</span
                                                    >
                                                </button>
                                            </div>
                                            <div
                                                v-else-if="
                                                    (gridLayout[r]?.[c] ??
                                                        0) === -1
                                                "
                                                class="rounded-xl border border-border/70 bg-muted/35"
                                                :style="{
                                                    width: `${bedCellSize}px`,
                                                    height: `${bedCellSize}px`,
                                                }"
                                            />
                                            <div
                                                v-else-if="
                                                    (gridLayout[r]?.[c] ??
                                                        1) === 0
                                                "
                                                class="pointer-events-none rounded-xl opacity-0"
                                                :style="{
                                                    width: `${bedCellSize}px`,
                                                    height: `${bedCellSize}px`,
                                                }"
                                            />
                                            <button
                                                v-else
                                                type="button"
                                                class="flex touch-manipulation items-center justify-center rounded-xl border border-dashed text-emerald-800/75 transition hover:-translate-y-0.5 hover:text-emerald-900 active:scale-[0.98]"
                                                :class="emptyCellClass(r, c)"
                                                :style="{
                                                    width: `${bedCellSize}px`,
                                                    height: `${bedCellSize}px`,
                                                }"
                                                :title="`Lisa taim ruutu (rida ${r + 1}, veerg ${c + 1})`"
                                                :aria-label="`Lisa taim ruutu, rida ${r + 1}, veerg ${c + 1}`"
                                                @click="openCellModal(r, c)"
                                            >
                                                <span
                                                    class="material-symbols-outlined text-xl"
                                                    >add</span
                                                >
                                            </button>
                                        </template>
                                    </template>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="!plantsWithoutBed.length"
                            class="rounded-[1.75rem] border border-border/70 bg-card/95 p-4 shadow-[0_8px_20px_rgba(16,24,40,0.05)]"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="max-w-2xl">
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Praegu pole ühtegi vaba taime.
                                    </h3>
                                    <p
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        Kui soovid sellesse peenrasse midagi
                                        lisada, loo esmalt uus taim või eemalda
                                        taim mõnest teisest peenrast.
                                    </p>
                                </div>
                                <Link
                                    href="/plants/create"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-primary bg-primary px-3.5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0"
                                >
                                    <span class="material-symbols-outlined"
                                        >add</span
                                    >
                                    Loo uus taim
                                </Link>
                            </div>
                        </section>

                        <section
                            v-if="plantsWithoutCell().length"
                            class="rounded-3xl border border-border/70 bg-card/95 p-4 shadow-[0_8px_20px_rgba(16,24,40,0.05)]"
                        >
                            <p
                                class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Muud taimed peenral
                            </p>
                            <ul class="flex flex-wrap gap-2">
                                <li
                                    v-for="p in plantsWithoutCell()"
                                    :key="p.id"
                                    class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-muted/35 py-1.5 pr-1.5 pl-3"
                                >
                                    <span
                                        class="text-sm font-medium text-foreground"
                                        >{{ p.name }}</span
                                    >
                                    <span
                                        class="rounded-full bg-background px-2 py-0.5 text-xs font-semibold text-muted-foreground"
                                    >
                                        {{ p.quantity ?? 1 }} tk
                                    </span>
                                    <button
                                        type="button"
                                        class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        aria-label="Eemalda taim peenralt"
                                        @click="removePlantFromBed(p)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-xl"
                                            >close</span
                                        >
                                    </button>
                                </li>
                            </ul>
                        </section>

                        <section
                            class="rounded-3xl border border-border/70 bg-card/95 p-4 shadow-[0_10px_24px_rgba(16,24,40,0.06)]"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Märkmed
                                </h3>
                                <Link
                                    :href="`/calendar/note-form?bed_id=${bed.id}&return_to=${encodeURIComponent(`/beds/${bed.id}`)}`"
                                    class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-primary bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0"
                                >
                                    <span
                                        class="material-symbols-outlined text-base"
                                        >add</span
                                    >
                                    Lisa
                                </Link>
                            </div>

                            <div
                                v-if="props.bedNotes?.length"
                                class="space-y-2.5"
                            >
                                <article
                                    v-for="note in props.bedNotes"
                                    :key="note.id"
                                    class="rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3 shadow-[0_2px_10px_rgba(16,24,40,0.04)]"
                                >
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <p
                                            class="line-clamp-1 text-sm font-medium text-foreground"
                                        >
                                            {{ note.title || 'Märge' }}
                                        </p>
                                        <span
                                            class="shrink-0 text-xs text-muted-foreground"
                                        >
                                            {{ formatNoteDate(note.note_date) }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="note.body"
                                        class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                                    >
                                        {{ note.body }}
                                    </p>
                                </article>
                            </div>
                            <p
                                v-else
                                class="rounded-2xl border border-dashed border-border/60 bg-background/60 px-3.5 py-4 text-sm text-muted-foreground"
                            >
                                Selle peenra kohta märkmeid veel pole.
                            </p>
                        </section>
                    </main>
                </div>
                <BottomNav active="map" />
            </div>

            <Teleport to="body">
                <div
                    v-if="cellModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur-sm sm:items-center sm:pb-4"
                    @click.self="cellModal = null"
                >
                    <div
                        class="flex max-h-[min(85dvh,32rem)] w-full max-w-sm flex-col overflow-hidden rounded-2xl bg-card shadow-xl ring-1 ring-black/5 sm:max-h-[min(70vh,36rem)]"
                        @click.stop
                    >
                        <div
                            class="flex items-center justify-between gap-3 border-b border-border p-4"
                        >
                            <div>
                                <h3 class="font-semibold">
                                    Lisa taim valitud ruutu
                                </h3>
                                <p
                                    v-if="cellModal"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ cellModalLabel }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl hover:bg-muted"
                                aria-label="Sulge"
                                @click="cellModal = null"
                            >
                                <span class="material-symbols-outlined text-xl"
                                    >close</span
                                >
                            </button>
                        </div>
                        <div
                            class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain p-2"
                        >
                            <template v-if="modalPlant">
                                <div
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border/70 bg-muted/40"
                                        >
                                            <img
                                                v-if="modalPlant.image_url"
                                                :src="modalPlant.image_url"
                                                :alt="modalPlant.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <span
                                                v-else
                                                class="material-symbols-outlined text-lg text-muted-foreground"
                                                >spa</span
                                            >
                                        </span>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-foreground"
                                            >
                                                {{ modalPlant.name }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Muuda selle ruudu kogust või
                                                eemalda taim peenralt.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3"
                                >
                                    <p
                                        class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Kogus selles ruudus
                                    </p>
                                    <div
                                        class="mt-2 flex items-center justify-between gap-3"
                                    >
                                        <div
                                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-2 py-1"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex h-11 w-11 touch-manipulation items-center justify-center rounded-full text-foreground transition hover:bg-muted sm:h-8 sm:w-8"
                                                aria-label="Vähenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        -1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >remove</span
                                                >
                                            </button>
                                            <span
                                                class="min-w-[3rem] text-center text-sm font-semibold text-foreground"
                                            >
                                                {{ selectedPlantQuantity }} tk
                                            </span>
                                            <button
                                                type="button"
                                                class="inline-flex h-11 w-11 touch-manipulation items-center justify-center rounded-full text-foreground transition hover:bg-muted sm:h-8 sm:w-8"
                                                aria-label="Suurenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >add</span
                                                >
                                            </button>
                                        </div>
                                        <p
                                            class="text-xs leading-5 text-muted-foreground"
                                        >
                                            Muuda kogust ilma taime ruudust
                                            eemaldamata.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-primary bg-primary px-3.5 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 sm:min-h-0"
                                        @click="
                                            cellModal &&
                                            updatePlantQuantityInCell(
                                                modalPlant.id,
                                                cellModal.row,
                                                cellModal.col,
                                            )
                                        "
                                    >
                                        <span
                                            class="material-symbols-outlined text-base"
                                            >check</span
                                        >
                                        Salvesta kogus
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-background px-3.5 py-3 text-sm font-semibold text-foreground transition hover:bg-muted sm:min-h-0"
                                        @click="removePlantFromBed(modalPlant)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-base"
                                            >delete</span
                                        >
                                        Eemalda taim
                                    </button>
                                </div>
                            </template>
                            <template v-else-if="!plantsWithoutBed.length">
                                <p
                                    class="py-3 text-center text-sm leading-6 text-muted-foreground"
                                >
                                    Vabu taimi praegu ei ole. Kõik taimed on
                                    juba peenardesse paigutatud või pole veel
                                    ühtegi taime lisatud.
                                </p>
                                <Link
                                    href="/plants/create"
                                    class="flex min-h-11 w-full touch-manipulation items-center justify-center gap-2 rounded-xl border-2 border-primary/40 bg-primary/10 py-3.5 font-semibold text-primary sm:min-h-0"
                                >
                                    <span class="material-symbols-outlined"
                                        >add_circle</span
                                    >
                                    Lisa uus taim
                                </Link>
                            </template>
                            <template v-else>
                                <div
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3 text-sm text-muted-foreground"
                                >
                                    Vali taim, mis paigutatakse ruutu
                                    <span class="font-semibold text-foreground">
                                        {{ cellModalLabel }}
                                    </span>
                                    .
                                </div>
                                <div
                                    class="mb-3 rounded-2xl border border-border/60 bg-background/70 px-3.5 py-3"
                                >
                                    <p
                                        class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Kogus selles ruudus
                                    </p>
                                    <div
                                        class="mt-2 flex items-center justify-between gap-3"
                                    >
                                        <div
                                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-2 py-1"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex h-11 w-11 touch-manipulation items-center justify-center rounded-full text-foreground transition hover:bg-muted sm:h-8 sm:w-8"
                                                aria-label="Vähenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        -1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >remove</span
                                                >
                                            </button>
                                            <span
                                                class="min-w-[3rem] text-center text-sm font-semibold text-foreground"
                                            >
                                                {{ selectedPlantQuantity }} tk
                                            </span>
                                            <button
                                                type="button"
                                                class="inline-flex h-11 w-11 touch-manipulation items-center justify-center rounded-full text-foreground transition hover:bg-muted sm:h-8 sm:w-8"
                                                aria-label="Suurenda kogust"
                                                @click="
                                                    changeSelectedPlantQuantity(
                                                        1,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="material-symbols-outlined text-base"
                                                    >add</span
                                                >
                                            </button>
                                        </div>
                                        <p
                                            class="text-xs leading-5 text-muted-foreground"
                                        >
                                            Kasuta seda, kui ühes ruudus on mitu
                                            sama taime.
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-for="[
                                        categoryName,
                                        plants,
                                    ] in plantsWithoutBedByCategory"
                                    :key="categoryName"
                                    class="mb-4 last:mb-0"
                                >
                                    <p
                                        class="mb-1.5 px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ categoryName }}
                                    </p>
                                    <ul class="space-y-1">
                                        <li
                                            v-for="plant in plants"
                                            :key="plant.id"
                                        >
                                            <button
                                                type="button"
                                                class="flex min-h-[3.25rem] w-full touch-manipulation items-center gap-3 rounded-xl border border-transparent p-3.5 text-left transition hover:bg-primary/10 sm:min-h-0 sm:p-3"
                                                @click="
                                                    cellModal &&
                                                    assignPlantToCell(
                                                        plant.id,
                                                        cellModal.row,
                                                        cellModal.col,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border/70 bg-muted/40"
                                                >
                                                    <img
                                                        v-if="plant.image_url"
                                                        :src="plant.image_url"
                                                        :alt="plant.name"
                                                        class="h-full w-full object-cover"
                                                    />
                                                    <span
                                                        v-else
                                                        class="material-symbols-outlined text-lg text-muted-foreground"
                                                        >spa</span
                                                    >
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span
                                                        class="block truncate font-medium text-foreground"
                                                    >
                                                        {{ plant.name }}
                                                    </span>
                                                    <span
                                                        class="mt-0.5 block text-xs text-muted-foreground"
                                                    >
                                                        Praegu kokku
                                                        {{
                                                            plant.quantity ?? 1
                                                        }}
                                                        tk
                                                    </span>
                                                </span>
                                                <span
                                                    class="material-symbols-outlined text-base text-primary"
                                                    >arrow_forward</span
                                                >
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>
