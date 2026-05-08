<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Bird,
    Box,
    Columns3,
    Droplet,
    Droplets,
    Flame,
    Home,
    House,
    LayoutGrid,
    Leaf,
    Logs,
    Pencil,
    Recycle,
    Shapes,
    Sofa,
    Sprout,
    Tent,
    ToyBrick,
    Trees,
    Warehouse,
    Waves,
    Wrench,
    type LucideIcon,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import CardActionsMenu from '@/components/CardActionsMenu.vue';
import DesktopSearchField from '@/components/DesktopSearchField.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import SearchModal from '@/pages/Seeds/SearchModal.vue';

type PlantInBed = {
    id: number;
    name: string;
    image_url: string | null;
    position_in_bed: string | null;
};
type Bed = {
    id: number;
    name: string;
    location: string | null;
    image_url?: string | null;
    rows: number;
    columns: number;
    garden_x: number;
    garden_y: number;
    cell_size_cm: number;
    layout?: number[][] | null;
    plants: PlantInBed[];
};
type GardenObjectType =
    | 'greenhouse'
    | 'pond'
    | 'shed'
    | 'compost'
    | 'other';
type GardenObject = {
    id: number;
    type: GardenObjectType;
    name: string;
    x: number;
    y: number;
    width: number;
    height: number;
    meta?: Record<string, unknown> | null;
};
type PlantWithoutBed = {
    id: number;
    name: string;
    image_url: string | null;
    category?: { name: string; slug: string } | null;
};
type GardenPlan = {
    id: number;
    name: string;
    width: number;
    height: number;
    unit: string;
};
type GardenPlanSummary = {
    id: number;
    name: string;
};
type ToolVariant = {
    id: string;
    type: GardenObjectType;
    label: string;
    description: string;
    width: number;
    height: number;
    icon: string;
    requiresCustomName?: boolean;
};
type ToolCategoryId = 'buildings' | 'water' | 'compost' | 'zones';
type ToolCategory = {
    id: ToolCategoryId;
    label: string;
    icon: string;
    description: string;
    variants: ToolVariant[];
};

const props = defineProps<{
    gardenPlans: GardenPlanSummary[];
    gardenPlan: GardenPlan;
    beds: Bed[];
    gardenObjects: GardenObject[];
    plantsWithoutBed: PlantWithoutBed[];
}>();

const page = usePage<{
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    errors?: Record<string, string>;
}>();
const breadcrumbs = computed(() => [
    { title: 'Aiaplaan', href: `/map/${props.gardenPlan.id}` },
]);
const showOnboardingHint = computed(() => props.beds.length === 0);
const showSearch = ref(false);
const searchQuery = ref('');

const GARDEN_PADDING = 24;
const CM_TO_PX = 0.5;
const GARDEN_GRID_CELL_CM = 30;
const MIN_ZOOM = 0.55;
const MAX_ZOOM = 4.5;
const ZOOM_STEP = 0.25;

const localPositions = ref<Record<number, { x: number; y: number }>>({});
const localObjectPositions = ref<Record<number, { x: number; y: number }>>({});
const draggingBedId = ref<number | null>(null);
const draggingObjectId = ref<number | null>(null);
const dragMoved = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const dragPointerId = ref<number | null>(null);
const hoveredBedId = ref<number | null>(null);
const hoveredObjectId = ref<number | null>(null);
const selectedBedId = ref<number | null>(null);
const selectedObjectId = ref<number | null>(null);
const activeTool = ref<GardenObjectType | null>(null);
const activeToolVariant = ref<ToolVariant | null>(null);
const activeToolCategoryId = ref<ToolCategoryId | null>(null);
const toolDrawerOpen = ref(false);
const plannerControlsOpen = ref(false);
const toolSearch = ref('');
const otherObjectNameDraft = ref('');
const otherObjectNameError = ref('');
const showBedsLayer = ref(true);
const showStructuresLayer = ref(true);
const showWaterLayer = ref(true);
const showPlannerLabels = ref(true);
const selectedQuickCategoryId = ref<ToolCategoryId | null>(null);
const toolCategories: ToolCategory[] = [
    {
        id: 'buildings',
        label: 'Hooned',
        icon: 'house',
        description: 'Maja, kasvuhoone, kuur ja muud ehitised.',
        variants: [
            {
                id: 'other-house',
                type: 'other',
                label: 'Maja',
                description: 'Põhihoone või elamu asukoht aias.',
                width: 520,
                height: 360,
                icon: 'house',
            },
            {
                id: 'greenhouse-classic',
                type: 'greenhouse',
                label: 'Kasvuhoone',
                description: 'Klassikaline kasvuhoone köögiviljadele.',
                width: 300,
                height: 200,
                icon: 'warehouse',
            },
            {
                id: 'greenhouse-tunnel',
                type: 'greenhouse',
                label: 'Tunnelkasvuhoone',
                description: 'Pikem tunnel varaseks kasvatuseks.',
                width: 420,
                height: 180,
                icon: 'tent',
            },
            {
                id: 'greenhouse-mini',
                type: 'greenhouse',
                label: 'Mini-kasvuhoone',
                description: 'Kompaktne kasvuhoone väiksemasse aeda.',
                width: 220,
                height: 150,
                icon: 'sprout',
            },
            {
                id: 'greenhouse-raised-bed-cover',
                type: 'greenhouse',
                label: 'Kastipeenra kate',
                description: 'Madal kate tõstetud peenrale.',
                width: 260,
                height: 120,
                icon: 'box',
            },
            {
                id: 'shed-tool',
                type: 'shed',
                label: 'Tööriistakuur',
                description: 'Panipaik tööriistadele ja varustusele.',
                width: 240,
                height: 180,
                icon: 'wrench',
            },
            {
                id: 'shed-wood',
                type: 'shed',
                label: 'Puukuur',
                description: 'Kuiv hoiukoht puudele.',
                width: 200,
                height: 160,
                icon: 'logs',
            },
            {
                id: 'shed-gazebo',
                type: 'shed',
                label: 'Aiapaviljon',
                description: 'Katusega varjualune istumiseks.',
                width: 320,
                height: 260,
                icon: 'tent',
            },
            {
                id: 'shed-sauna',
                type: 'shed',
                label: 'Aiasaun',
                description: 'Väike kõrvalhoone puhkealale.',
                width: 300,
                height: 220,
                icon: 'home',
            },
        ],
    },
    {
        id: 'water',
        label: 'Veealad',
        icon: 'droplets',
        description: 'Tiigid, purskkaevud ja vee kogumine.',
        variants: [
            {
                id: 'pond',
                type: 'pond',
                label: 'Tiik',
                description: 'Rahulik veesilm aeda.',
                width: 250,
                height: 180,
                icon: 'waves',
            },
            {
                id: 'fountain',
                type: 'pond',
                label: 'Purskkaev',
                description: 'Väiksem dekoratiivne veeobjekt.',
                width: 140,
                height: 140,
                icon: 'droplet',
            },
            {
                id: 'pond-bird-bath',
                type: 'pond',
                label: 'Linnuvann',
                description: 'Väike veeallikas lindudele.',
                width: 110,
                height: 110,
                icon: 'bird',
            },
            {
                id: 'pond-rain-barrel',
                type: 'pond',
                label: 'Vihmaveetünn',
                description: 'Vee kogumine kastmiseks.',
                width: 120,
                height: 120,
                icon: 'droplets',
            },
        ],
    },
    {
        id: 'compost',
        label: 'Kompost',
        icon: 'recycle',
        description: 'Kompostikastid ja orgaanika alad.',
        variants: [
            {
                id: 'compost-bin',
                type: 'compost',
                label: 'Kompostikast',
                description: 'Kompaktne kompostikast biojäätmetele.',
                width: 120,
                height: 120,
                icon: 'recycle',
            },
            {
                id: 'compost-area',
                type: 'compost',
                label: 'Kompostiala',
                description: 'Suurem avatud kompostiala.',
                width: 220,
                height: 160,
                icon: 'sprout',
            },
            {
                id: 'compost-leaf-area',
                type: 'compost',
                label: 'Lehekomposti ala',
                description: 'Lehtedele ja pehmemale orgaanikale.',
                width: 180,
                height: 140,
                icon: 'leaf',
            },
            {
                id: 'compost-multibox',
                type: 'compost',
                label: 'Mitmekambriline komposter',
                description: 'Tõhusam komposteerimine mitmes sektsioonis.',
                width: 260,
                height: 120,
                icon: 'columns-3',
            },
        ],
    },
    {
        id: 'zones',
        label: 'Alad',
        icon: 'trees',
        description: 'Puhkealad ja muud tsoonid.',
        variants: [
            {
                id: 'other-pergola',
                type: 'other',
                label: 'Pergola',
                description: 'Varjuline puhkeala.',
                width: 280,
                height: 220,
                icon: 'tent',
            },
            {
                id: 'other-terrace',
                type: 'other',
                label: 'Terrass',
                description: 'Istumis- ja puhkeala.',
                width: 360,
                height: 240,
                icon: 'sofa',
            },
            {
                id: 'other-fireplace',
                type: 'other',
                label: 'Lõkkeplats',
                description: 'Ümar puhkeala lõkke jaoks.',
                width: 220,
                height: 220,
                icon: 'flame',
            },
            {
                id: 'other-play-area',
                type: 'other',
                label: 'Laste mänguala',
                description: 'Muru- või liivapind mängimiseks.',
                width: 320,
                height: 240,
                icon: 'toy-brick',
            },
            {
                id: 'other-herb-corner',
                type: 'other',
                label: 'Ürdinurk',
                description: 'Eraldi ala maitsetaimedele.',
                width: 220,
                height: 160,
                icon: 'leaf',
            },
            {
                id: 'other-custom',
                type: 'other',
                label: 'Muu (oma nimi)',
                description: 'Sisesta ise objekti nimi.',
                width: 200,
                height: 150,
                icon: 'pencil',
                requiresCustomName: true,
            },
        ],
    },
];
const allToolVariants = toolCategories.flatMap((category) => category.variants);
const plannerViewport = ref<HTMLElement | null>(null);
const selectedObjectPanel = ref<HTMLElement | null>(null);
const selectedBedPanel = ref<HTMLElement | null>(null);
const objectWidthInput = ref<HTMLInputElement | null>(null);
const viewportSize = ref({ width: 0, height: 0 });
const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const panStart = ref({ x: 0, y: 0, originX: 0, originY: 0 });
let resizeObserver: ResizeObserver | null = null;
const highlightSelectedObjectPanel = ref(false);
const highlightSelectedBedPanel = ref(false);
let objectPanelHighlightTimeout: ReturnType<typeof setTimeout> | null = null;
let bedPanelHighlightTimeout: ReturnType<typeof setTimeout> | null = null;
const plannerLandscapeHintDismissed = ref(false);
const PLANNER_LANDSCAPE_HINT_KEY = 'planner-landscape-hint-dismissed';

const shouldShowLandscapeHint = computed(
    () =>
        !plannerLandscapeHintDismissed.value &&
        (viewportSize.value.width < 860 || viewportSize.value.height < 560),
);

const gardenForm = useForm({
    name: props.gardenPlan.name,
    widthMeters: props.gardenPlan.width / 100,
    heightMeters: props.gardenPlan.height / 100,
});
const objectForm = useForm({
    name: '',
    widthMeters: 0,
    heightMeters: 0,
});

function openGardenPlanEditor() {
    plannerControlsOpen.value = true;
}

function deleteGardenPlan() {
    if (
        !confirm(
            'Kustutada see aiaplaan koos selle peenarde ja aiaobjektidega? Sidumata taimed jäävad alles.',
        )
    ) {
        return;
    }
    router.delete(`/garden-plans/${props.gardenPlan.id}`);
}

function onGardenPlanSelect(event: Event) {
    const el = event.target as HTMLSelectElement;
    const id = Number(el.value);
    if (!id || id === props.gardenPlan.id) return;
    router.visit(`/map/${id}`);
}

function createGardenPlan() {
    const raw = window.prompt('Uue aiaplaani nimi (tühi = „Minu aed“):', '');
    if (raw === null) return;
    router.post('/garden-plans', {
        name: raw.trim() || undefined,
    });
}

watch(
    () => props.gardenPlan.id,
    (id, prev) => {
        syncPositionsFromProps();
        if (prev !== undefined && id !== prev) {
            selectedBedId.value = null;
            selectedObjectId.value = null;
            plannerControlsOpen.value = false;
        }
    },
);

watch(
    () => ({
        name: props.gardenPlan.name,
        width: props.gardenPlan.width,
        height: props.gardenPlan.height,
    }),
    (plan) => {
        gardenForm.name = plan.name;
        gardenForm.widthMeters = plan.width / 100;
        gardenForm.heightMeters = plan.height / 100;
    },
    { deep: true },
);

onMounted(() => {
    if (typeof window !== 'undefined') {
        plannerLandscapeHintDismissed.value =
            window.localStorage.getItem(PLANNER_LANDSCAPE_HINT_KEY) === '1';
    }

    syncPositionsFromProps();
    nextTick(() => {
        if (plannerViewport.value) {
            viewportSize.value = {
                width: plannerViewport.value.clientWidth,
                height: plannerViewport.value.clientHeight,
            };
        }
        applyFitZoom();
    });

    if (typeof ResizeObserver !== 'undefined' && plannerViewport.value) {
        resizeObserver = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (!entry) return;
            viewportSize.value = {
                width: entry.contentRect.width,
                height: entry.contentRect.height,
            };
        });
        resizeObserver.observe(plannerViewport.value);
    }
});

function dismissLandscapeHint() {
    plannerLandscapeHintDismissed.value = true;
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(PLANNER_LANDSCAPE_HINT_KEY, '1');
    }
}

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', stopDragging);
    window.removeEventListener('pointercancel', stopDragging);
    window.removeEventListener('pointermove', onObjectPointerMove);
    window.removeEventListener('pointerup', stopObjectDragging);
    window.removeEventListener('pointercancel', stopObjectDragging);
    window.removeEventListener('pointermove', onPanMove);
    window.removeEventListener('pointerup', stopPanning);
    window.removeEventListener('pointercancel', stopPanning);
    resizeObserver?.disconnect();
    if (objectPanelHighlightTimeout) clearTimeout(objectPanelHighlightTimeout);
    if (bedPanelHighlightTimeout) clearTimeout(bedPanelHighlightTimeout);
});

function syncPositionsFromProps() {
    const next: Record<number, { x: number; y: number }> = {};
    props.beds.forEach((bed) => {
        next[bed.id] = {
            x: bed.garden_x ?? GARDEN_PADDING,
            y: bed.garden_y ?? GARDEN_PADDING,
        };
    });
    localPositions.value = next;

    const objectPositions: Record<number, { x: number; y: number }> = {};
    props.gardenObjects.forEach((object) => {
        objectPositions[object.id] = {
            x: object.x,
            y: object.y,
        };
    });
    localObjectPositions.value = objectPositions;
}

const bedNames = computed(() => props.beds.map((b) => b.name));

const filteredBeds = computed(() => {
    let list = [...props.beds];
    list = list.slice().sort((a, b) => a.name.localeCompare(b.name, 'et'));

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (b) =>
                b.name.toLowerCase().includes(q) ||
                (b.location ?? '').toLowerCase().includes(q),
        );
    }

    return list;
});
const plannerFeedback = computed(() => {
    const gardenPlanError = page.props.errors?.garden_plan;
    if (gardenPlanError) {
        return { tone: 'error' as const, message: gardenPlanError };
    }

    if (page.props.flash?.error) {
        return { tone: 'error' as const, message: page.props.flash.error };
    }

    if (page.props.flash?.success) {
        return { tone: 'success' as const, message: page.props.flash.success };
    }

    if (gardenForm.recentlySuccessful) {
        return {
            tone: 'success' as const,
            message: 'Aia andmed on salvestatud.',
        };
    }

    if (objectForm.recentlySuccessful) {
        return {
            tone: 'success' as const,
            message: 'Aiaelement on uuendatud.',
        };
    }

    return null;
});

const gardenWidthCm = computed(() =>
    Math.max(1, Math.round(Number(gardenForm.widthMeters || 0) * 100)),
);
const gardenHeightCm = computed(() =>
    Math.max(1, Math.round(Number(gardenForm.heightMeters || 0) * 100)),
);
const gardenSurfaceWidth = computed(() =>
    Math.max(320, Math.round(gardenWidthCm.value * CM_TO_PX)),
);
const gardenSurfaceHeight = computed(() =>
    Math.max(240, Math.round(gardenHeightCm.value * CM_TO_PX)),
);
const zoomPercent = computed(() => `${Math.round(zoom.value * 100)}%`);
const gardenDimensionLabel = computed(() => {
    const fmt = (v: number) => {
        const n = Math.round(v * 100) / 100;
        return Number.isInteger(n) ? `${n}` : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    };
    const w = Number(gardenForm.widthMeters || 0);
    const h = Number(gardenForm.heightMeters || 0);
    return `${fmt(w)} m × ${fmt(h)} m`;
});
const plannerGridSizePx = computed(() =>
    Math.max(10, Math.round(GARDEN_GRID_CELL_CM * CM_TO_PX)),
);
const scaleBarWidthPx = computed(() => Math.round(100 * CM_TO_PX));
const plannerBeds = computed(() =>
    showBedsLayer.value ? filteredBeds.value : [],
);
const plannerObjects = computed(() =>
    props.gardenObjects.filter((object) => {
        if (object.type === 'pond') return showWaterLayer.value;
        return showStructuresLayer.value;
    }),
);
const visibleObjectsCount = computed(() => plannerObjects.value.length);
const filteredToolCategories = computed(() => {
    const query = toolSearch.value.trim().toLowerCase();
    if (!query) return toolCategories;

    return toolCategories
        .map((category) => ({
            ...category,
            variants: category.variants.filter((variant) => {
                const haystack = `${category.label} ${variant.label} ${variant.description}`.toLowerCase();
                return haystack.includes(query);
            }),
        }))
        .filter((category) => category.variants.length > 0);
});
const selectedToolDisplayLabel = computed(
    () => activeToolVariant.value?.label ?? (activeTool.value ? objectTypeLabel(activeTool.value) : ''),
);
const selectedToolDisplayDescription = computed(
    () => activeToolVariant.value?.description ?? '',
);

const selectedBed = computed(() => {
    if (selectedBedId.value === null) return null;
    return props.beds.find((bed) => bed.id === selectedBedId.value) ?? null;
});

const selectedObject = computed(() => {
    if (selectedObjectId.value === null) return null;
    return (
        props.gardenObjects.find(
            (object) => object.id === selectedObjectId.value,
        ) ?? null
    );
});

watch(
    selectedObject,
    (object) => {
        if (!object) {
            objectForm.reset();
            objectForm.clearErrors();
            return;
        }

        objectForm.name = object.name;
        objectForm.widthMeters = Number((object.width / 100).toFixed(1));
        objectForm.heightMeters = Number((object.height / 100).toFixed(1));
        objectForm.clearErrors();

        nextTick(() => {
            selectedObjectPanel.value?.scrollIntoView({
                block: 'nearest',
                behavior: 'smooth',
            });
            highlightSelectedObjectPanel.value = true;
            if (objectPanelHighlightTimeout)
                clearTimeout(objectPanelHighlightTimeout);
            objectPanelHighlightTimeout = setTimeout(() => {
                highlightSelectedObjectPanel.value = false;
            }, 1200);
        });
    },
    { immediate: true },
);

watch(selectedBed, (bed) => {
    if (!bed) return;

    nextTick(() => {
        selectedBedPanel.value?.scrollIntoView({
            block: 'nearest',
            behavior: 'smooth',
        });
        highlightSelectedBedPanel.value = true;
        if (bedPanelHighlightTimeout) clearTimeout(bedPanelHighlightTimeout);
        bedPanelHighlightTimeout = setTimeout(() => {
            highlightSelectedBedPanel.value = false;
        }, 1200);
    });
});

watch(plannerBeds, (beds) => {
    if (
        selectedBedId.value !== null &&
        !beds.some((bed) => bed.id === selectedBedId.value)
    ) {
        selectedBedId.value = beds[0]?.id ?? null;
    }
});

watch(plannerObjects, (objects) => {
    if (
        selectedObjectId.value !== null &&
        !objects.some((object) => object.id === selectedObjectId.value)
    ) {
        selectedObjectId.value = null;
        hoveredObjectId.value = null;
    }
});

const layerButtonClass = (active: boolean) => {
    const base =
        'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition';
    if (active) return `${base} border-primary/20 bg-primary/10 text-primary`;
    return `${base} border-border bg-background text-muted-foreground hover:bg-muted`;
};

const gardenPresetButtonClass = (active: boolean) => {
    const base =
        'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition';
    if (active) return `${base} border-primary/20 bg-primary/10 text-primary`;
    return `${base} border-border/70 bg-card/85 text-foreground hover:bg-muted`;
};

const toolVariantButtonClass = (variant: ToolVariant) => {
    const active = activeToolVariant.value?.id === variant.id;
    return active
        ? 'border-primary/25 bg-primary/8 ring-2 ring-primary/15'
        : 'border-border/70 bg-background/80 hover:border-primary/20 hover:bg-muted/60';
};
const toolVariantIconToneClass = (type: GardenObjectType) => {
    return {
        greenhouse: 'text-slate-600',
        shed: 'text-slate-600',
        pond: 'text-sky-600',
        compost: 'text-amber-700',
        other: 'text-violet-400',
    }[type];
};
const categoryIconToneClass = (categoryId: ToolCategoryId, active: boolean) => {
    if (active) {
        return 'text-primary';
    }

    return {
        buildings: 'text-slate-600',
        water: 'text-sky-600',
        compost: 'text-amber-700',
        zones: 'text-violet-400',
    }[categoryId];
};
const categoryButtonToneClass = (categoryId: ToolCategoryId, active: boolean) => {
    if (active) {
        return {
            buildings: 'border-primary/30 bg-primary/12 text-primary shadow-sm',
            water: 'border-primary/30 bg-primary/12 text-primary shadow-sm',
            compost: 'border-primary/30 bg-primary/12 text-primary shadow-sm',
            zones: 'border-primary/30 bg-primary/12 text-primary shadow-sm',
        }[categoryId];
    }

    return {
        buildings:
            'border-border/70 bg-background text-foreground hover:bg-muted/70',
        water: 'border-border/70 bg-background text-foreground hover:bg-muted/70',
        compost:
            'border-border/70 bg-background text-foreground hover:bg-muted/70',
        zones: 'border-border/70 bg-background text-foreground hover:bg-muted/70',
    }[categoryId];
};

function editBed(id: number) {
    router.get(`/beds/${id}/edit`);
}

function deleteBed(id: number, name: string) {
    if (!confirm(`Eemaldada peenar "${name}"? Taimed jäävad peenrata.`)) return;
    router.delete(`/beds/${id}`, { preserveScroll: true });
}

function getBedLayout(bed: Bed): number[][] {
    const L = bed.layout;
    if (
        L &&
        Array.isArray(L) &&
        L.length > 0 &&
        L.some((row) => Array.isArray(row) && row.length > 0)
    ) {
        return L as number[][];
    }
    return Array.from({ length: bed.rows || 1 }, () =>
        Array.from({ length: bed.columns || 1 }, () => 1),
    );
}

function getBedColumns(bed: Bed): number {
    const layout = getBedLayout(bed);
    if (layout.length === 0) return 1;
    return Math.max(...layout.map((r) => r.length), 1);
}

function getBedRows(bed: Bed): number {
    return Math.max(getBedLayout(bed).length, 1);
}

function getBedActiveBounds(bed: Bed) {
    const layout = getBedLayout(bed);
    let minRow = Number.POSITIVE_INFINITY;
    let maxRow = Number.NEGATIVE_INFINITY;
    let minCol = Number.POSITIVE_INFINITY;
    let maxCol = Number.NEGATIVE_INFINITY;

    layout.forEach((row, rowIndex) => {
        row.forEach((cell, colIndex) => {
            if (cell !== 1) return;
            minRow = Math.min(minRow, rowIndex);
            maxRow = Math.max(maxRow, rowIndex);
            minCol = Math.min(minCol, colIndex);
            maxCol = Math.max(maxCol, colIndex);
        });
    });

    if (!Number.isFinite(minRow) || !Number.isFinite(minCol)) {
        return {
            minRow: 0,
            maxRow: getBedRows(bed) - 1,
            minCol: 0,
            maxCol: getBedColumns(bed) - 1,
        };
    }

    return { minRow, maxRow, minCol, maxCol };
}

function getBedWidthInCells(bed: Bed): number {
    const bounds = getBedActiveBounds(bed);
    return Math.max(1, bounds.maxCol - bounds.minCol + 1);
}

function getBedHeightInCells(bed: Bed): number {
    const bounds = getBedActiveBounds(bed);
    return Math.max(1, bounds.maxRow - bounds.minRow + 1);
}

function getBedCellSizeCm(bed: Bed): number {
    return Math.max(10, bed.cell_size_cm || 30);
}

function getBedVisibleLayout(bed: Bed): number[][] {
    const layout = getBedLayout(bed);
    const bounds = getBedActiveBounds(bed);

    return layout
        .slice(bounds.minRow, bounds.maxRow + 1)
        .map((row) => row.slice(bounds.minCol, bounds.maxCol + 1));
}

function getPlantAtVisibleCell(
    bed: Bed,
    visibleRow: number,
    visibleCol: number,
): PlantInBed | null {
    const bounds = getBedActiveBounds(bed);
    const absoluteRow = bounds.minRow + visibleRow;
    const absoluteCol = bounds.minCol + visibleCol;
    const key = `${absoluteRow},${absoluteCol}`;
    return bed.plants.find((item) => item.position_in_bed === key) ?? null;
}

function getPlantPreviewImageAtVisibleCell(
    bed: Bed,
    visibleRow: number,
    visibleCol: number,
): string | null {
    const plant = getPlantAtVisibleCell(bed, visibleRow, visibleCol);
    if (!plant) return null;
    return plant.image_url || '/logo.png';
}

function getBedPhysicalWidthCm(bed: Bed): number {
    return getBedWidthInCells(bed) * getBedCellSizeCm(bed);
}

function getBedPhysicalHeightCm(bed: Bed): number {
    return getBedHeightInCells(bed) * getBedCellSizeCm(bed);
}

function formatCentimeters(cm: number): string {
    return `${(cm / 100).toFixed(1)} m`;
}

function bedPreviewGridStyle(bed: Bed) {
    const cols = getBedWidthInCells(bed);
    const rows = getBedHeightInCells(bed);
    const cellPx = Math.max(10, Math.round(getBedCellSizeCm(bed) * CM_TO_PX));

    return {
        gridTemplateColumns: `repeat(${cols}, ${cellPx}px)`,
        gridTemplateRows: `repeat(${rows}, ${cellPx}px)`,
    };
}

function bedCardSize(bed: Bed) {
    return {
        width: Math.max(24, Math.round(getBedPhysicalWidthCm(bed) * CM_TO_PX)),
        height: Math.max(
            24,
            Math.round(getBedPhysicalHeightCm(bed) * CM_TO_PX),
        ),
    };
}

function getObjectPixelWidth(object: GardenObject): number {
    return Math.max(30, Math.round(object.width * CM_TO_PX));
}

function getObjectPixelHeight(object: GardenObject): number {
    return Math.max(30, Math.round(object.height * CM_TO_PX));
}

function snapToGardenGrid(value: number): number {
    return (
        Math.round(value / plannerGridSizePx.value) * plannerGridSizePx.value
    );
}

function clampBedPosition(bed: Bed, x: number, y: number) {
    const size = bedCardSize(bed);
    return {
        x: Math.max(
            GARDEN_PADDING,
            Math.min(x, gardenSurfaceWidth.value - size.width - GARDEN_PADDING),
        ),
        y: Math.max(
            GARDEN_PADDING,
            Math.min(
                y,
                gardenSurfaceHeight.value - size.height - GARDEN_PADDING,
            ),
        ),
    };
}

function clampObjectPosition(object: GardenObject, x: number, y: number) {
    const width = getObjectPixelWidth(object);
    const height = getObjectPixelHeight(object);

    return {
        x: Math.max(
            GARDEN_PADDING,
            Math.min(x, gardenSurfaceWidth.value - width - GARDEN_PADDING),
        ),
        y: Math.max(
            GARDEN_PADDING,
            Math.min(y, gardenSurfaceHeight.value - height - GARDEN_PADDING),
        ),
    };
}

function getBedPosition(bed: Bed) {
    const stored = localPositions.value[bed.id] ?? {
        x: bed.garden_x ?? GARDEN_PADDING,
        y: bed.garden_y ?? GARDEN_PADDING,
    };
    return clampBedPosition(bed, stored.x, stored.y);
}

function plannerBedStyle(bed: Bed) {
    const position = getBedPosition(bed);
    const size = bedCardSize(bed);

    return {
        left: `${position.x}px`,
        top: `${position.y}px`,
        width: `${size.width}px`,
        height: `${size.height}px`,
    };
}

function getObjectPosition(object: GardenObject) {
    const stored = localObjectPositions.value[object.id] ?? {
        x: object.x,
        y: object.y,
    };
    return clampObjectPosition(object, stored.x, stored.y);
}

function plannerObjectStyle(object: GardenObject) {
    const position = getObjectPosition(object);

    return {
        left: `${position.x}px`,
        top: `${position.y}px`,
        width: `${getObjectPixelWidth(object)}px`,
        height: `${getObjectPixelHeight(object)}px`,
    };
}

function getPlannerLocalPoint(event: PointerEvent | WheelEvent) {
    const viewport = plannerViewport.value;
    if (!viewport) {
        return { x: 0, y: 0 };
    }

    const rect = viewport.getBoundingClientRect();

    return {
        x: (event.clientX - rect.left - panX.value) / zoom.value,
        y: (event.clientY - rect.top - panY.value) / zoom.value,
    };
}

function openBedPage(bedId: number) {
    if (dragMoved.value) return;
    router.get(`/beds/${bedId}`);
}

function showBedInfo(bedId: number) {
    hoveredBedId.value = bedId;
}

function hideBedInfo(bedId: number) {
    if (hoveredBedId.value === bedId) {
        hoveredBedId.value = null;
    }
}

function showObjectInfo(objectId: number) {
    hoveredObjectId.value = objectId;
}

function hideObjectInfo(objectId: number) {
    if (hoveredObjectId.value === objectId) {
        hoveredObjectId.value = null;
    }
}

function focusBedDetails(bedId: number) {
    if (dragMoved.value) return;
    selectedObjectId.value = null;
    selectedBedId.value = bedId;
}

function focusObjectDetails(objectId: number) {
    if (dragMoved.value) return;
    selectedBedId.value = null;
    selectedObjectId.value = objectId;
}

function clearSelectionDetails() {
    selectedBedId.value = null;
    selectedObjectId.value = null;
    hoveredBedId.value = null;
    hoveredObjectId.value = null;
}

function startDragging(bed: Bed, event: PointerEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-no-drag="true"]')) return;

    const current = getBedPosition(bed);
    const pointer = getPlannerLocalPoint(event);
    draggingBedId.value = bed.id;
    dragMoved.value = false;
    dragPointerId.value = event.pointerId;
    dragOffset.value = {
        x: pointer.x - current.x,
        y: pointer.y - current.y,
    };

    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', stopDragging);
    window.addEventListener('pointercancel', stopDragging);
}

function onPointerMove(event: PointerEvent) {
    if (draggingBedId.value === null) return;

    const bed = props.beds.find((item) => item.id === draggingBedId.value);
    if (!bed) return;

    dragMoved.value = true;
    const pointer = getPlannerLocalPoint(event);
    const next = clampBedPosition(
        bed,
        snapToGardenGrid(pointer.x - dragOffset.value.x),
        snapToGardenGrid(pointer.y - dragOffset.value.y),
    );

    localPositions.value = {
        ...localPositions.value,
        [bed.id]: next,
    };
}

function stopDragging() {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', stopDragging);
    window.removeEventListener('pointercancel', stopDragging);

    if (draggingBedId.value === null) return;

    const bedId = draggingBedId.value;
    draggingBedId.value = null;
    dragPointerId.value = null;

    const position = localPositions.value[bedId];
    if (!position) return;

    router.put(
        `/beds/${bedId}`,
        {
            garden_x: Math.round(position.x),
            garden_y: Math.round(position.y),
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => {
                setTimeout(() => {
                    dragMoved.value = false;
                }, 80);
            },
        },
    );
}

function startObjectDragging(object: GardenObject, event: PointerEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-no-drag="true"]')) return;

    const current = getObjectPosition(object);
    const pointer = getPlannerLocalPoint(event);
    draggingObjectId.value = object.id;
    dragMoved.value = false;
    dragPointerId.value = event.pointerId;
    dragOffset.value = {
        x: pointer.x - current.x,
        y: pointer.y - current.y,
    };

    window.addEventListener('pointermove', onObjectPointerMove);
    window.addEventListener('pointerup', stopObjectDragging);
    window.addEventListener('pointercancel', stopObjectDragging);
}

function onObjectPointerMove(event: PointerEvent) {
    if (draggingObjectId.value === null) return;

    const object = props.gardenObjects.find(
        (item) => item.id === draggingObjectId.value,
    );
    if (!object) return;

    dragMoved.value = true;
    const pointer = getPlannerLocalPoint(event);
    const next = clampObjectPosition(
        object,
        snapToGardenGrid(pointer.x - dragOffset.value.x),
        snapToGardenGrid(pointer.y - dragOffset.value.y),
    );

    localObjectPositions.value = {
        ...localObjectPositions.value,
        [object.id]: next,
    };
}

function stopObjectDragging() {
    window.removeEventListener('pointermove', onObjectPointerMove);
    window.removeEventListener('pointerup', stopObjectDragging);
    window.removeEventListener('pointercancel', stopObjectDragging);

    if (draggingObjectId.value === null) return;

    const objectId = draggingObjectId.value;
    draggingObjectId.value = null;
    dragPointerId.value = null;

    const position = localObjectPositions.value[objectId];
    if (!position) return;

    router.put(
        `/garden-objects/${objectId}`,
        {
            x: Math.round(position.x),
            y: Math.round(position.y),
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => {
                setTimeout(() => {
                    dragMoved.value = false;
                }, 80);
            },
        },
    );
}

function startPanning(event: PointerEvent) {
    void event;
    // Planner surface panning is intentionally disabled:
    // the grid stays fixed and only objects are moved.
    return;
}

function getDefaultToolVariant(type: GardenObjectType): ToolVariant {
    const match = allToolVariants.find((variant) => variant.type === type);
    return match ?? allToolVariants[0];
}

function chooseToolVariant(variant: ToolVariant) {
    activeTool.value = variant.type;
    activeToolVariant.value = variant;
    const category = toolCategories.find((item) =>
        item.variants.some((entry) => entry.id === variant.id),
    );
    activeToolCategoryId.value = category?.id ?? null;
    selectedQuickCategoryId.value = null;
    otherObjectNameError.value = '';
    if (variant.type === 'other') {
        otherObjectNameDraft.value = variant.requiresCustomName ? '' : variant.label;
        return;
    }
    otherObjectNameDraft.value = '';
}

function toggleQuickToolMenu(categoryId: ToolCategoryId) {
    const category = toolCategories.find((item) => item.id === categoryId);
    const variants = category?.variants ?? [];
    if (variants.length <= 1 && variants[0]) {
        chooseToolVariant(variants[0]);
        return;
    }
    selectedQuickCategoryId.value =
        selectedQuickCategoryId.value === categoryId ? null : categoryId;
}

function clearToolSelection() {
    activeTool.value = null;
    activeToolVariant.value = null;
    activeToolCategoryId.value = null;
    selectedQuickCategoryId.value = null;
    otherObjectNameDraft.value = '';
    otherObjectNameError.value = '';
    toolDrawerOpen.value = false;
}

function openCreateBed() {
    router.get(`/map/${props.gardenPlan.id}/beds/new`);
}

function resetPlannerFilters() {
    showBedsLayer.value = true;
    showStructuresLayer.value = true;
    showWaterLayer.value = true;
    showPlannerLabels.value = true;
    toolSearch.value = '';
    searchQuery.value = '';
}

function applyGardenPreset(
    widthMeters: number,
    heightMeters: number,
    suggestedName?: string,
) {
    gardenForm.widthMeters = widthMeters;
    gardenForm.heightMeters = heightMeters;

    if (!gardenForm.name.trim() && suggestedName) {
        gardenForm.name = suggestedName;
    }
}

function isGardenPresetActive(
    widthMeters: number,
    heightMeters: number,
): boolean {
    return (
        Number(gardenForm.widthMeters) === widthMeters &&
        Number(gardenForm.heightMeters) === heightMeters
    );
}

function handlePlannerSurfaceClick(event: MouseEvent) {
    if (!activeTool.value) return;
    if (isPanning.value || dragMoved.value) return;

    const target = event.target as HTMLElement | null;
    if (
        target?.closest(
            '[data-bed-shape="true"], [data-object-shape="true"], button, input, label, a',
        )
    )
        return;

    if (activeTool.value === 'other' && activeToolVariant.value?.requiresCustomName) {
        if (!otherObjectNameDraft.value.trim()) {
            otherObjectNameError.value = 'Palun sisesta objekti nimi.';
            return;
        }
        otherObjectNameError.value = '';
    }

    const config = activeToolVariant.value ?? getDefaultToolVariant(activeTool.value);
    const point = getPlannerLocalPoint(event);
    const widthPx = Math.round(config.width * CM_TO_PX);
    const heightPx = Math.round(config.height * CM_TO_PX);
    const rawX = snapToGardenGrid(point.x - widthPx / 2);
    const rawY = snapToGardenGrid(point.y - heightPx / 2);
    const clampedX = Math.max(
        GARDEN_PADDING,
        Math.min(rawX, gardenSurfaceWidth.value - widthPx - GARDEN_PADDING),
    );
    const clampedY = Math.max(
        GARDEN_PADDING,
        Math.min(rawY, gardenSurfaceHeight.value - heightPx - GARDEN_PADDING),
    );

    const objectName =
        activeTool.value === 'other'
            ? (otherObjectNameDraft.value.trim() || config.label)
            : config.label;

    router.post(
        '/garden-objects',
        {
            garden_plan_id: props.gardenPlan.id,
            type: activeTool.value,
            name: objectName,
            x: clampedX,
            y: clampedY,
            width: config.width,
            height: config.height,
            meta: {
                variant_id: config.id,
            },
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                activeTool.value = null;
                activeToolVariant.value = null;
                activeToolCategoryId.value = null;
                otherObjectNameDraft.value = '';
                otherObjectNameError.value = '';
                toolDrawerOpen.value = false;
                selectedQuickCategoryId.value = null;
            },
        },
    );
}

function deleteSelectedObject() {
    if (!selectedObject.value) return;

    if (!confirm(`Eemaldada aiaelement "${selectedObject.value.name}"?`))
        return;

    router.delete(`/garden-objects/${selectedObject.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedObjectId.value = null;
            hoveredObjectId.value = null;
        },
    });
}

function duplicateSelectedObject() {
    if (!selectedObject.value) return;

    router.post(
        `/garden-objects/${selectedObject.value.id}/duplicate`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function rotateSelectedObject() {
    if (!selectedObject.value) return;

    router.post(
        `/garden-objects/${selectedObject.value.id}/rotate`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function saveSelectedObject() {
    if (!selectedObject.value) return;

    objectForm
        .transform((data) => ({
            name:
                data.name.trim() || selectedObject.value?.name || 'Aiaelement',
            width: Math.round(Number(data.widthMeters || 0) * 100),
            height: Math.round(Number(data.heightMeters || 0) * 100),
        }))
        .put(`/garden-objects/${selectedObject.value.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
}

function objectTypeLabel(type: GardenObjectType): string {
    return {
        greenhouse: 'Kasvuhoone',
        pond: 'Tiik',
        shed: 'Kuur',
        compost: 'Kompost',
        other: 'Muu',
    }[type];
}

const iconLibrary: Record<string, LucideIcon> = {
    house: House,
    home: Home,
    warehouse: Warehouse,
    tent: Tent,
    sprout: Sprout,
    box: Box,
    wrench: Wrench,
    logs: Logs,
    waves: Waves,
    droplet: Droplet,
    droplets: Droplets,
    bird: Bird,
    recycle: Recycle,
    leaf: Leaf,
    'columns-3': Columns3,
    sofa: Sofa,
    flame: Flame,
    'toy-brick': ToyBrick,
    pencil: Pencil,
    shapes: Shapes,
    'layout-grid': LayoutGrid,
    trees: Trees,
};

function iconFor(name: string | undefined | null): LucideIcon {
    if (!name) return Shapes;
    return iconLibrary[name] ?? Shapes;
}

function objectTypeIcon(type: GardenObjectType): string {
    return {
        greenhouse: 'warehouse',
        pond: 'waves',
        shed: 'home',
        compost: 'recycle',
        other: 'shapes',
    }[type];
}

function objectTypeDescription(type: GardenObjectType): string {
    return {
        greenhouse: 'Püsiv koht soojalembeste taimede kasvatamiseks.',
        pond: 'Rahulik veesilm või väike tiik aia keskmesse.',
        shed: 'Panipaik tööriistadele ja aiatarvikutele.',
        compost: 'Kompostiala orgaanilise materjali kogumiseks.',
        other: 'Oma nimega näiteks maja, puuriit, kiviaed või terrass.',
    }[type];
}


function objectIconSize(object: GardenObject): number {
    const base = Math.min(
        getObjectPixelWidth(object),
        getObjectPixelHeight(object),
    );
    const type = objectVariantType(object);
    const multiplier = type === 'pond' ? 0.56 : 0.42;
    const maxSize = type === 'pond' ? 120 : 96;
    return Math.max(14, Math.min(maxSize, Math.round(base * multiplier)));
}

function objectVariantIcon(object: GardenObject): string {
    const variantId =
        object.meta && typeof object.meta === 'object'
            ? (object.meta['variant_id'] as string | undefined)
            : undefined;

    if (variantId) {
        const variant = allToolVariants.find((item) => item.id === variantId);
        if (variant?.icon) return variant.icon;
    }

    return objectTypeIcon(object.type);
}

function objectVariantType(object: GardenObject): GardenObjectType {
    const variantId =
        object.meta && typeof object.meta === 'object'
            ? (object.meta['variant_id'] as string | undefined)
            : undefined;

    if (variantId) {
        const variant = allToolVariants.find((item) => item.id === variantId);
        if (variant?.type) return variant.type;
    }

    return object.type;
}

function onPanMove(event: PointerEvent) {
    if (!isPanning.value) return;

    panX.value = panStart.value.originX + (event.clientX - panStart.value.x);
    panY.value = panStart.value.originY + (event.clientY - panStart.value.y);
}

function stopPanning() {
    isPanning.value = false;
    window.removeEventListener('pointermove', onPanMove);
    window.removeEventListener('pointerup', stopPanning);
    window.removeEventListener('pointercancel', stopPanning);
}

function plannerSurfaceStyle() {
    return {
        width: `${gardenSurfaceWidth.value}px`,
        height: `${gardenSurfaceHeight.value}px`,
        transform: `translate(${panX.value}px, ${panY.value}px) scale(${zoom.value})`,
        transformOrigin: 'top left',
        backgroundImage: [
            'linear-gradient(180deg, rgba(243, 251, 234, 0.98), rgba(222, 238, 208, 0.99))',
            'radial-gradient(circle at 50% 0%, rgba(185, 214, 160, 0.22), transparent 42%)',
        ].join(', '),
    };
}

function goToSelectedBed() {
    if (!selectedBed.value) return;
    router.get(`/beds/${selectedBed.value.id}`);
}

function applyFitZoom() {
    if (!viewportSize.value.width || !viewportSize.value.height) return;

    const fitWidth = viewportSize.value.width / gardenSurfaceWidth.value;
    const fitHeight = viewportSize.value.height / gardenSurfaceHeight.value;
    const nextZoom = Math.min(
        MAX_ZOOM,
        Math.max(MIN_ZOOM, Math.min(fitWidth, fitHeight)),
    );
    zoom.value = Number.isFinite(nextZoom) ? nextZoom : 1;
    panX.value = 0;
    panY.value = 0;
}

function changeZoom(delta: number) {
    zoom.value = Math.min(
        MAX_ZOOM,
        Math.max(MIN_ZOOM, Number((zoom.value + delta).toFixed(2))),
    );
}

function resetZoom() {
    zoom.value = 1;
    panX.value = 0;
    panY.value = 0;
}

function onPlannerWheel(event: WheelEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-ui-overlay="true"]')) return;
    event.preventDefault();
    const pointer = getPlannerLocalPoint(event);
    const delta = event.deltaY > 0 ? -0.12 : 0.12;
    const nextZoom = Math.min(
        MAX_ZOOM,
        Math.max(MIN_ZOOM, Number((zoom.value + delta).toFixed(2))),
    );

    if (nextZoom === zoom.value) return;

    panX.value -= pointer.x * (nextZoom - zoom.value);
    panY.value -= pointer.y * (nextZoom - zoom.value);
    zoom.value = nextZoom;
}

function saveGardenPlan() {
    gardenForm
        .transform((data) => ({
            name: data.name.trim() || 'Minu aed',
            width: Math.round(Number(data.widthMeters || 0) * 100),
            height: Math.round(Number(data.heightMeters || 0) * 100),
        }))
        .put(`/garden-plans/${props.gardenPlan.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const plan = page.props.gardenPlan as GardenPlan | undefined;
                if (!plan) return;
                gardenForm.defaults({
                    name: plan.name,
                    widthMeters: plan.width / 100,
                    heightMeters: plan.height / 100,
                });
            },
        });
}
</script>

<template>
    <Head title="Aiaplaan" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="page page-with-bottomnav">
            <div
                class="font-display min-h-screen bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title="Aiaplaan"
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
                                placeholder="Otsi peenraid..."
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
                    </DiaryHeader>

                    <main class="flex-1 px-6 py-4 md:px-8">
                        <div class="space-y-6 sm:space-y-8">
                            <section class="space-y-6">
                                <div
                                    v-if="plannerFeedback"
                                    class="rounded-[1.5rem] border px-4 py-3 shadow-sm"
                                    :class="
                                        plannerFeedback.tone === 'error'
                                            ? 'border-rose-200 bg-rose-50 text-rose-700'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    "
                                >
                                    <p class="text-sm font-medium">
                                        {{ plannerFeedback.message }}
                                    </p>
                                </div>

                                <div
                                    v-if="shouldShowLandscapeHint"
                                    class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 shadow-sm"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div>
                                            <p class="text-sm font-semibold text-foreground">
                                                Aiaplaan töötab paremini laiemas vaates
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-6 text-muted-foreground"
                                            >
                                                Lohistamine ja paigutus on mugavam, kui kasutad
                                                horisontaalrežiimi või avad akna laiemaks.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex shrink-0 items-center justify-center rounded-full border border-primary/20 bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90"
                                            @click="dismissLandscapeHint"
                                        >
                                            Sain aru
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="showOnboardingHint"
                                    class="rounded-xl border border-primary/20 bg-primary/5 p-5 shadow-sm"
                                >
                                    <div
                                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div class="max-w-2xl">
                                            <p
                                                class="text-base font-semibold text-foreground"
                                            >
                                                Alusta esimesest peenrast.
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-6 text-muted-foreground"
                                            >
                                                Kui peenar on loodud, saad selle
                                                siia aeda paigutada ja hiljem
                                                avada, et ruudustik ning taimed
                                                täpsemalt paika panna.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                                            @click="openCreateBed"
                                        >
                                            Loo esimene peenar
                                        </button>
                                    </div>
                                </div>

                                <div
                                    class="rounded-xl border border-border/70 bg-white p-4 shadow-[0_8px_24px_rgba(0,0,0,0.05)] dark:bg-card sm:p-5"
                                >
                                    <div
                                        class="mb-4 flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <h3
                                                class="text-lg font-semibold text-foreground"
                                            >
                                                Aiaplaan
                                            </h3>
                                            <p
                                                class="mt-1 text-sm leading-6 text-muted-foreground"
                                            >
                                                Paiguta peenrad rahulikult oma
                                                kohale. Muudatused salvestuvad
                                                kohe ja peenra detailid avanevad
                                                ühe vajutusega.
                                            </p>
                                        </div>
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <div
                                                class="flex items-center gap-2 rounded-2xl border border-border/70 bg-background/80 px-3 py-2 text-xs text-muted-foreground shadow-xs"
                                            >
                                                <span
                                                    class="material-symbols-outlined rounded-xl bg-primary/10 p-2 text-primary"
                                                    >forest</span
                                                >
                                                <div>
                                                    Peenraid aias
                                                    <div
                                                        class="text-base font-semibold text-foreground"
                                                    >
                                                        {{ plannerBeds.length }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="flex items-center gap-2 rounded-2xl border border-border/70 bg-background/80 px-3 py-2 text-xs text-muted-foreground shadow-xs"
                                            >
                                                <span
                                                    class="material-symbols-outlined rounded-xl bg-primary/10 p-2 text-primary"
                                                    >architecture</span
                                                >
                                                <div>
                                                    Aiaobjekte
                                                    <div
                                                        class="text-base font-semibold text-foreground"
                                                    >
                                                        {{
                                                            visibleObjectsCount
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="flex items-center gap-1 rounded-2xl border border-border/70 bg-background/85 px-2 py-2 shadow-xs"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-card text-foreground transition hover:bg-muted"
                                                    @click="
                                                        changeZoom(-ZOOM_STEP)
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >remove</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex rounded-full border border-border bg-card px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                                                    @click="resetZoom"
                                                >
                                                    {{ zoomPercent }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-card text-foreground transition hover:bg-muted"
                                                    @click="
                                                        changeZoom(ZOOM_STEP)
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >add</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="ml-1 inline-flex rounded-full border border-primary/20 bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/15"
                                                    @click="applyFitZoom"
                                                >
                                                    Mahuta
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="mb-4 flex flex-col gap-2 rounded-xl border border-border/70 bg-background px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <label
                                                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                for="garden-plan-select"
                                            >
                                                Aiaplaan
                                            </label>
                                            <select
                                                id="garden-plan-select"
                                                class="mt-1.5 block w-full max-w-full rounded-xl border border-border bg-card px-3 py-2 text-sm font-medium text-foreground sm:max-w-xs"
                                                :value="gardenPlan.id"
                                                @change="onGardenPlanSelect"
                                            >
                                                <option
                                                    v-for="p in gardenPlans"
                                                    :key="p.id"
                                                    :value="p.id"
                                                >
                                                    {{ p.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full border border-primary/25 bg-primary/10 px-4 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
                                            @click="createGardenPlan"
                                        >
                                            <span
                                                class="material-symbols-outlined text-base"
                                                >add</span
                                            >
                                            Uus plaan
                                        </button>
                                    </div>

                                    <div
                                        class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-[1.5rem] border border-border/70 bg-background/75 px-3 py-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                            >
                                                Aed
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-medium text-foreground"
                                            >
                                                {{
                                                    gardenForm.name ||
                                                    'Minu aed'
                                                }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ gardenDimensionLabel }}
                                            </p>
                                        </div>
                                        <CardActionsMenu
                                            placement="inline"
                                            @edit="openGardenPlanEditor"
                                            @delete="deleteGardenPlan"
                                        />
                                    </div>

                                    <div
                                        v-if="plannerControlsOpen"
                                        class="mb-4 space-y-3"
                                    >
                                        <div class="flex justify-end">
                                            <button
                                                type="button"
                                                class="text-xs font-semibold text-muted-foreground underline-offset-2 transition hover:text-foreground hover:underline"
                                                @click="plannerControlsOpen = false"
                                            >
                                                Sulge
                                            </button>
                                        </div>
                                        <div
                                            class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]"
                                        >
                                        <div
                                            class="rounded-[1.5rem] border border-border/70 bg-background/75 p-3"
                                        >
                                            <div class="mb-3">
                                                <p
                                                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                >
                                                    Kiirvalikud
                                                </p>
                                                <div
                                                    class="mt-2 flex flex-wrap gap-2"
                                                >
                                                    <button
                                                        type="button"
                                                        :class="
                                                            gardenPresetButtonClass(
                                                                isGardenPresetActive(
                                                                    10,
                                                                    10,
                                                                ),
                                                            )
                                                        "
                                                        @click="
                                                            applyGardenPreset(
                                                                10,
                                                                10,
                                                                'Väike aed',
                                                            )
                                                        "
                                                    >
                                                        Väike aed
                                                        <span
                                                            class="text-[11px] text-current/80"
                                                            >10 × 10 m</span
                                                        >
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :class="
                                                            gardenPresetButtonClass(
                                                                isGardenPresetActive(
                                                                    20,
                                                                    10,
                                                                ),
                                                            )
                                                        "
                                                        @click="
                                                            applyGardenPreset(
                                                                20,
                                                                10,
                                                                'Köögiviljaaed',
                                                            )
                                                        "
                                                    >
                                                        Köögiviljaaed
                                                        <span
                                                            class="text-[11px] text-current/80"
                                                            >20 × 10 m</span
                                                        >
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :class="
                                                            gardenPresetButtonClass(
                                                                isGardenPresetActive(
                                                                    50,
                                                                    20,
                                                                ),
                                                            )
                                                        "
                                                        @click="
                                                            applyGardenPreset(
                                                                50,
                                                                20,
                                                                'Suur aed',
                                                            )
                                                        "
                                                    >
                                                        Suur aed
                                                        <span
                                                            class="text-[11px] text-current/80"
                                                            >50 × 20 m</span
                                                        >
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :class="
                                                            gardenPresetButtonClass(
                                                                isGardenPresetActive(
                                                                    100,
                                                                    30,
                                                                ),
                                                            )
                                                        "
                                                        @click="
                                                            applyGardenPreset(
                                                                100,
                                                                30,
                                                                'Pikk aiamaa',
                                                            )
                                                        "
                                                    >
                                                        Pikk aiamaa
                                                        <span
                                                            class="text-[11px] text-current/80"
                                                            >100 × 30 m</span
                                                        >
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                class="grid gap-3 sm:grid-cols-3"
                                            >
                                                <label
                                                    class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                                                >
                                                    <span
                                                        class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                        >Aia nimi</span
                                                    >
                                                    <input
                                                        v-model="
                                                            gardenForm.name
                                                        "
                                                        type="text"
                                                        class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                                                        placeholder="Minu aed"
                                                    />
                                                </label>
                                                <label
                                                    class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                                                >
                                                    <span
                                                        class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                        >Laius (m)</span
                                                    >
                                                    <input
                                                        v-model="
                                                            gardenForm.widthMeters
                                                        "
                                                        type="number"
                                                        min="0.01"
                                                        max="1000"
                                                        step="0.01"
                                                        class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                                                    />
                                                </label>
                                                <label
                                                    class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                                                >
                                                    <span
                                                        class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                        >Sügavus (m)</span
                                                    >
                                                    <input
                                                        v-model="
                                                            gardenForm.heightMeters
                                                        "
                                                        type="number"
                                                        min="0.01"
                                                        max="1000"
                                                        step="0.01"
                                                        class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                                                    />
                                                </label>
                                            </div>

                                            <div
                                                v-if="
                                                    gardenForm.errors.name ||
                                                    gardenForm.errors.width ||
                                                    gardenForm.errors.height
                                                "
                                                class="mt-3 space-y-1 rounded-2xl border border-red-200 bg-red-50/90 px-3 py-2 text-sm text-red-700"
                                            >
                                                <p
                                                    v-if="
                                                        gardenForm.errors.name
                                                    "
                                                >
                                                    {{ gardenForm.errors.name }}
                                                </p>
                                                <p
                                                    v-if="
                                                        gardenForm.errors.width
                                                    "
                                                >
                                                    {{
                                                        gardenForm.errors.width
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        gardenForm.errors.height
                                                    "
                                                >
                                                    {{
                                                        gardenForm.errors.height
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="mt-3 flex flex-wrap items-center gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/15"
                                                    :disabled="
                                                        gardenForm.processing
                                                    "
                                                    @click="saveGardenPlan"
                                                >
                                                    Salvesta aed
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            class="rounded-[1.5rem] border border-border/70 bg-background/75 p-3"
                                        >
                                            <p
                                                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                            >
                                                Vaate kihid
                                            </p>
                                            <div
                                                class="mt-3 flex flex-wrap gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    :class="
                                                        layerButtonClass(
                                                            showBedsLayer,
                                                        )
                                                    "
                                                    @click="
                                                        showBedsLayer =
                                                            !showBedsLayer
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >grid_view</span
                                                    >
                                                    Peenrad
                                                </button>
                                                <button
                                                    type="button"
                                                    :class="
                                                        layerButtonClass(
                                                            showStructuresLayer,
                                                        )
                                                    "
                                                    @click="
                                                        showStructuresLayer =
                                                            !showStructuresLayer
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >home_work</span
                                                    >
                                                    Hooned
                                                </button>
                                                <button
                                                    type="button"
                                                    :class="
                                                        layerButtonClass(
                                                            showWaterLayer,
                                                        )
                                                    "
                                                    @click="
                                                        showWaterLayer =
                                                            !showWaterLayer
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >water</span
                                                    >
                                                    Vesi
                                                </button>
                                                <button
                                                    type="button"
                                                    :class="
                                                        layerButtonClass(
                                                            showPlannerLabels,
                                                        )
                                                    "
                                                    @click="
                                                        showPlannerLabels =
                                                            !showPlannerLabels
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >label</span
                                                    >
                                                    Nimed
                                                </button>
                                            </div>
                                        </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            !props.beds.length &&
                                            !props.gardenObjects.length
                                        "
                                        class="rounded-xl border-2 border-dashed border-primary/25 bg-background p-8 text-center"
                                    >
                                        <p
                                            class="text-base font-semibold text-foreground"
                                        >
                                            Aiaplaan on veel tühi.
                                        </p>
                                        <p
                                            class="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                                        >
                                            Loo kõigepealt peenar. Kui peenar on
                                            olemas, saad selle siia paigutada ja
                                            selle sees taimi hallata.
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-4 inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                                            @click="openCreateBed"
                                        >
                                            Loo esimene peenar
                                        </button>
                                    </div>

                                    <div
                                        v-else-if="
                                            plannerBeds.length === 0 &&
                                            visibleObjectsCount === 0
                                        "
                                        class="rounded-xl border border-dashed border-primary/25 bg-background px-6 py-8 text-center"
                                    >
                                        <p
                                            class="text-base font-semibold text-foreground"
                                        >
                                            Praegu ei ole midagi nähtaval.
                                        </p>
                                        <p
                                            class="mt-2 text-sm leading-6 text-muted-foreground"
                                        >
                                            Vaata üle kihid ja otsing, et näha
                                            jälle kõiki peenraid ning
                                            aiaelemente.
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-4 inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
                                            @click="resetPlannerFilters"
                                        >
                                            Näita kõike
                                        </button>
                                    </div>

                                    <div
                                        v-else
                                        class="mb-3 grid gap-3"
                                    >
                                        <button
                                            type="button"
                                            class="hidden items-center justify-between rounded-xl border border-border/80 bg-white px-4 py-3 text-left shadow-sm dark:bg-card"
                                            @click="
                                                toolDrawerOpen = !toolDrawerOpen
                                            "
                                        >
                                            <div>
                                                <p
                                                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                >
                                                    Tööriistad
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-semibold text-foreground"
                                                >
                                                    {{
                                                        activeTool
                                                            ? selectedToolDisplayLabel
                                                            : 'Lisa aeda objekt'
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    v-if="activeTool"
                                                    class="rounded-full border border-primary/15 bg-primary/8 px-2.5 py-1 text-[11px] font-semibold text-primary"
                                                >
                                                    Valitud
                                                </span>
                                                <span
                                                    class="material-symbols-outlined text-primary"
                                                >
                                                    {{
                                                        toolDrawerOpen
                                                            ? 'expand_less'
                                                            : 'expand_more'
                                                    }}
                                                </span>
                                            </div>
                                        </button>

                                        <aside
                                            class="hidden rounded-xl border border-border/80 bg-white p-4 shadow-sm dark:bg-card xl:sticky xl:top-6 xl:self-start"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-3"
                                            >
                                                <div>
                                                    <p
                                                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                    >
                                                        Tööriistad
                                                    </p>
                                                    <h4
                                                        class="mt-1 text-lg font-semibold text-foreground"
                                                    >
                                                        Lisa aeda
                                                    </h4>
                                                    <p
                                                        v-if="!activeTool"
                                                        class="mt-2 text-xs leading-relaxed text-muted-foreground"
                                                    >
                                                        Vali allpool tüüp, siis
                                                        klõpsa
                                                        <span
                                                            class="font-medium text-foreground/85"
                                                            >rohelisel plaanil</span
                                                        >
                                                        kohta. Uue peenra jaoks
                                                        kasuta paremal all
                                                        olevat + nuppu.
                                                    </p>
                                                </div>
                                                <button
                                                    v-if="activeTool"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background text-foreground transition hover:bg-muted"
                                                    @click="clearToolSelection"
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >close</span
                                                    >
                                                </button>
                                            </div>

                                            <label class="mt-4 block">
                                                <span
                                                    class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                    >Objektiotsing</span
                                                >
                                                <div class="relative">
                                                    <input
                                                        v-model="toolSearch"
                                                        type="text"
                                                        class="w-full rounded-xl border border-border/70 bg-background px-4 py-3 pr-11 text-sm text-foreground shadow-xs transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                        placeholder="Otsi: kuur, tiik, muu..."
                                                    />
                                                    <button
                                                        v-if="toolSearch"
                                                        type="button"
                                                        class="absolute top-1/2 right-2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                        @click="toolSearch = ''"
                                                    >
                                                        <span
                                                            class="material-symbols-outlined text-sm"
                                                            >close</span
                                                        >
                                                    </button>
                                                </div>
                                            </label>

                                            <div class="mt-4 space-y-3">
                                                <div
                                                    v-for="category in filteredToolCategories"
                                                    :key="`tool-category-${category.id}`"
                                                    class="rounded-xl border border-border/70 bg-background/60 p-2"
                                                >
                                                    <p class="px-2 py-1 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                                        {{ category.label }}
                                                    </p>
                                                    <div class="space-y-1.5">
                                                        <button
                                                            v-for="variant in category.variants"
                                                            :key="variant.id"
                                                            type="button"
                                                            class="w-full rounded-xl border p-3 text-left transition"
                                                            :class="
                                                                toolVariantButtonClass(
                                                                    variant,
                                                                )
                                                            "
                                                            @click="
                                                                chooseToolVariant(
                                                                    variant,
                                                                )
                                                            "
                                                        >
                                                            <span class="flex items-start gap-2">
                                                                <component
                                                                    :is="iconFor(variant.icon)"
                                                                    :size="18"
                                                                    :stroke-width="1.75"
                                                                    class="mt-0.5 shrink-0"
                                                                    :class="
                                                                        toolVariantIconToneClass(
                                                                            variant.type,
                                                                        )
                                                                    "
                                                                />
                                                                <span class="min-w-0">
                                                                    <span class="block text-sm font-semibold text-foreground">
                                                                        {{ variant.label }}
                                                                    </span>
                                                                    <span class="mt-0.5 block text-xs text-muted-foreground">
                                                                        {{ variant.description }}
                                                                    </span>
                                                                </span>
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="
                                                        !filteredToolCategories.length
                                                    "
                                                    class="rounded-xl border border-dashed border-border/80 bg-background px-4 py-5 text-sm text-muted-foreground"
                                                >
                                                    Selle otsinguga sobivat
                                                    aiaelementi ei leitud.
                                                </div>
                                            </div>

                                            <div
                                                v-if="activeTool"
                                                class="mt-4 rounded-[1.25rem] border border-primary/15 bg-primary/6 px-3 py-3 text-sm"
                                            >
                                                <div
                                                    class="font-semibold text-primary"
                                                >
                                                    {{ selectedToolDisplayLabel }}
                                                </div>
                                                <p
                                                    v-if="selectedToolDisplayDescription"
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    {{ selectedToolDisplayDescription }}
                                                </p>
                                                <div
                                                    v-if="
                                                        activeTool === 'other' &&
                                                        activeToolVariant?.requiresCustomName
                                                    "
                                                    class="mt-3 space-y-1"
                                                >
                                                    <label class="block">
                                                        <span
                                                            class="mb-1 block text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                                            >Objekti nimi</span
                                                        >
                                                        <input
                                                            v-model="
                                                                otherObjectNameDraft
                                                            "
                                                            type="text"
                                                            maxlength="120"
                                                            class="w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-sm text-foreground shadow-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                            placeholder="Nt maja, puuriit, kiviaed"
                                                            @input="
                                                                otherObjectNameError =
                                                                    ''
                                                            "
                                                        />
                                                    </label>
                                                    <p
                                                        v-if="otherObjectNameError"
                                                        class="text-xs text-rose-600"
                                                    >
                                                        {{ otherObjectNameError }}
                                                    </p>
                                                </div>
                                                <div
                                                    class="mt-1 leading-6 text-muted-foreground"
                                                    :class="
                                                        activeTool === 'other' &&
                                                        activeToolVariant?.requiresCustomName
                                                            ? 'mt-3'
                                                            : ''
                                                    "
                                                >
                                                    <template
                                                        v-if="
                                                            activeTool === 'other' &&
                                                            activeToolVariant?.requiresCustomName
                                                        "
                                                    >
                                                        Seejärel klõpsa aias
                                                        kohta, kuhu objekt
                                                        tuleb.
                                                    </template>
                                                    <template v-else>
                                                        Klõpsa nüüd aias kohta,
                                                        kuhu soovid selle
                                                        lisada.
                                                    </template>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="mt-3 inline-flex items-center gap-1 rounded-full border border-border bg-background px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                                                    @click="clearToolSelection"
                                                >
                                                    Tühista valik
                                                </button>
                                            </div>

                                            <button
                                                type="button"
                                                class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90 xl:hidden"
                                                @click="toolDrawerOpen = false"
                                            >
                                                Sulge tööriistad
                                            </button>

                                        </aside>

                                        <div class="relative flex gap-3">
                                            <aside
                                                data-ui-overlay="true"
                                                class="pointer-events-auto z-30 flex w-14 shrink-0 flex-col items-center gap-2 self-start rounded-2xl border border-border/80 bg-white/95 px-2 py-2 shadow-lg backdrop-blur dark:bg-card/95"
                                            >
                                                <button
                                                    v-for="category in toolCategories"
                                                    :key="`quick-tool-${category.id}`"
                                                    type="button"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border transition"
                                                    :class="
                                                        categoryButtonToneClass(
                                                            category.id,
                                                            activeToolCategoryId ===
                                                                category.id,
                                                        )
                                                    "
                                                    :title="`Vali: ${category.label}`"
                                                    @click.stop="
                                                        toggleQuickToolMenu(
                                                            category.id,
                                                        )
                                                    "
                                                >
                                                    <component
                                                        :is="iconFor(category.icon)"
                                                        :size="20"
                                                        :stroke-width="1.75"
                                                        :class="
                                                            categoryIconToneClass(
                                                                category.id,
                                                                activeToolCategoryId ===
                                                                    category.id,
                                                            )
                                                        "
                                                    />
                                                </button>
                                                <button
                                                    v-if="activeTool"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border/70 bg-background text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                    title="Tühista valik"
                                                    @click.stop="clearToolSelection"
                                                >
                                                    <span class="material-symbols-outlined text-sm">close</span>
                                                </button>
                                            </aside>

                                            <div
                                                v-if="selectedQuickCategoryId"
                                                data-ui-overlay="true"
                                                class="pointer-events-auto absolute top-0 left-[68px] z-30 w-72 rounded-2xl border border-border/80 bg-white/96 p-3 shadow-xl backdrop-blur dark:bg-card/96"
                                            >
                                                <div class="mb-2 flex items-center justify-between">
                                                    <p class="text-sm font-semibold text-foreground">
                                                        {{
                                                            toolCategories.find(
                                                                (
                                                                    item,
                                                                ) =>
                                                                    item.id ===
                                                                    selectedQuickCategoryId,
                                                            )?.label
                                                        }}
                                                        variandid
                                                    </p>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                        @click.stop="
                                                            selectedQuickCategoryId = null
                                                        "
                                                    >
                                                        <span class="material-symbols-outlined text-sm">close</span>
                                                    </button>
                                                </div>
                                                <div class="max-h-[min(68vh,26rem)] space-y-1.5 overflow-y-auto pr-1">
                                                    <button
                                                        v-for="variant in toolCategories.find((item) => item.id === selectedQuickCategoryId)?.variants ?? []"
                                                        :key="variant.id"
                                                        type="button"
                                                        class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-left transition hover:border-primary/20 hover:bg-primary/6"
                                                        @click.stop="
                                                            chooseToolVariant(variant)
                                                        "
                                                    >
                                                        <div class="flex items-start gap-2">
                                                            <component
                                                                :is="iconFor(variant.icon)"
                                                                :size="18"
                                                                :stroke-width="1.75"
                                                                class="mt-0.5 shrink-0"
                                                                :class="
                                                                    toolVariantIconToneClass(
                                                                        variant.type,
                                                                    )
                                                                "
                                                            />
                                                            <span class="min-w-0">
                                                                <span class="block text-sm font-semibold text-foreground">
                                                                    {{ variant.label }}
                                                                </span>
                                                                <span class="mt-0.5 block text-xs text-muted-foreground">
                                                                    {{ variant.description }}
                                                                </span>
                                                            </span>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                ref="plannerViewport"
                                                class="relative flex-1 min-w-0 overflow-auto rounded-[1.75rem] border border-border/80 bg-[linear-gradient(180deg,rgba(251,248,241,0.98),rgba(244,239,229,0.98))] p-3 shadow-inner dark:bg-[linear-gradient(180deg,rgba(30,38,32,0.98),rgba(22,29,24,0.98))] sm:p-4 cursor-default"
                                                :style="{
                                                    height: 'min(72vh, 920px)',
                                                }"
                                                @pointerdown="startPanning($event)"
                                                @wheel="onPlannerWheel($event)"
                                            >
                                            <div
                                                class="relative overflow-hidden rounded-[1.6rem] border border-emerald-900/10 bg-emerald-50/80 dark:border-emerald-200/15 dark:bg-emerald-950/35"
                                                :style="plannerSurfaceStyle()"
                                                @click="
                                                    handlePlannerSurfaceClick(
                                                        $event,
                                                    )
                                                "
                                            >
                                                <div
                                                    class="pointer-events-none absolute inset-x-4 top-3 z-20 flex items-center justify-between text-[11px] font-semibold tracking-[0.18em] text-emerald-900/45 uppercase"
                                                >
                                                    <span
                                                        class="rounded-full bg-white/70 px-2.5 py-1 backdrop-blur-sm dark:bg-card/70"
                                                        >Aed</span
                                                    >
                                                    <span
                                                        class="rounded-full bg-white/70 px-2.5 py-1 backdrop-blur-sm dark:bg-card/70"
                                                        >Lohista peenraid</span
                                                    >
                                                </div>

                                                <div
                                                    class="pointer-events-none absolute bottom-4 left-4 z-20 rounded-2xl border border-emerald-900/10 bg-white/78 px-3 py-2 text-[11px] font-medium text-emerald-950/75 shadow-sm backdrop-blur-sm dark:border-emerald-200/20 dark:bg-card/78 dark:text-emerald-100/80"
                                                >
                                                    <div
                                                        class="mb-1 tracking-[0.16em] text-emerald-900/55 uppercase"
                                                    >
                                                        Mõõtkava
                                                    </div>
                                                    <div
                                                        class="flex items-center gap-2"
                                                    >
                                                        <div
                                                            class="relative h-2 rounded-full bg-emerald-700/75"
                                                            :style="{
                                                                width: `${scaleBarWidthPx}px`,
                                                            }"
                                                        >
                                                            <span
                                                                class="absolute -top-1.5 -left-px h-5 w-px bg-emerald-900/55"
                                                            ></span>
                                                            <span
                                                                class="absolute -top-1.5 -right-px h-5 w-px bg-emerald-900/55"
                                                            ></span>
                                                        </div>
                                                        <span
                                                            class="font-semibold text-foreground"
                                                            >1 m</span
                                                        >
                                                    </div>
                                                </div>

                                                <article
                                                    v-for="bed in plannerBeds"
                                                    :key="bed.id"
                                                    class="group absolute transition-transform duration-150"
                                                    :class="[
                                                        draggingBedId === bed.id
                                                            ? 'z-30 scale-[1.02]'
                                                            : 'z-10 hover:z-20 hover:-translate-y-1',
                                                    ]"
                                                    :style="
                                                        plannerBedStyle(bed)
                                                    "
                                                    data-bed-shape="true"
                                                    @pointerdown="
                                                        startDragging(
                                                            bed,
                                                            $event,
                                                        )
                                                    "
                                                    @mouseenter="
                                                        showBedInfo(bed.id)
                                                    "
                                                    @mouseleave="
                                                        hideBedInfo(bed.id)
                                                    "
                                                    @click="
                                                        focusBedDetails(bed.id)
                                                    "
                                                >
                                                    <div class="relative z-10">
                                                        <div
                                                            class="grid place-content-center gap-[2px] rounded-[0.9rem] border border-emerald-900/10 bg-[linear-gradient(180deg,rgba(241,250,232,0.96),rgba(228,240,217,0.98))] p-1 transition"
                                                            :class="
                                                                selectedBed?.id ===
                                                                bed.id
                                                                    ? 'ring-2 ring-emerald-400/55 ring-offset-4 ring-offset-[#eef4e6]'
                                                                    : ''
                                                            "
                                                            :style="
                                                                bedPreviewGridStyle(
                                                                    bed,
                                                                )
                                                            "
                                                        >
                                                            <template
                                                                v-for="(
                                                                    rowData, r
                                                                ) in getBedVisibleLayout(
                                                                    bed,
                                                                )"
                                                                :key="`plan-row-${bed.id}-${r}`"
                                                            >
                                                                <span
                                                                    v-for="(
                                                                        _, c
                                                                    ) in rowData"
                                                                    :key="`plan-cell-${bed.id}-${r}-${c}`"
                                                                    class="rounded-[3px] border"
                                                                    :class="
                                                                        rowData[
                                                                            c
                                                                        ] === 1
                                                                            ? 'border-emerald-900/15 bg-[linear-gradient(180deg,rgba(131,171,116,0.95),rgba(95,139,84,0.98))]'
                                                                            : 'border-emerald-700/15 bg-emerald-100/55'
                                                                    "
                                                                    :style="
                                                                        rowData[
                                                                            c
                                                                        ] === 1 &&
                                                                        getPlantPreviewImageAtVisibleCell(
                                                                            bed,
                                                                            r,
                                                                            c,
                                                                        )
                                                                            ? {
                                                                                  backgroundImage: `linear-gradient(180deg,rgba(32,44,30,0.18),rgba(32,44,30,0.32)), url('${getPlantPreviewImageAtVisibleCell(
                                                                                      bed,
                                                                                      r,
                                                                                      c,
                                                                                  )}')`,
                                                                                  backgroundSize:
                                                                                      'cover',
                                                                                  backgroundPosition:
                                                                                      'center',
                                                                              }
                                                                            : undefined
                                                                    "
                                                                />
                                                            </template>
                                                        </div>

                                                        <div
                                                            v-if="
                                                                showPlannerLabels
                                                            "
                                                            class="pointer-events-none absolute top-full left-1/2 z-20 mt-2 -translate-x-1/2 rounded-full bg-white/88 px-2.5 py-1 text-[11px] font-semibold text-foreground shadow-sm backdrop-blur-sm dark:bg-card/88"
                                                        >
                                                            {{ bed.name }}
                                                        </div>

                                                        <div
                                                            v-if="
                                                                hoveredBedId ===
                                                                bed.id
                                                            "
                                                            class="absolute top-full left-1/2 z-30 mt-3 hidden w-44 -translate-x-1/2 rounded-[1.2rem] border border-emerald-900/10 bg-white/92 p-3 text-left shadow-lg backdrop-blur-sm dark:border-emerald-200/20 dark:bg-card/92 md:block"
                                                        >
                                                            <p
                                                                class="truncate text-sm font-semibold text-foreground"
                                                            >
                                                                {{ bed.name }}
                                                            </p>
                                                            <p
                                                                class="mt-1 text-xs text-muted-foreground"
                                                            >
                                                                {{
                                                                    bed.location ||
                                                                    'Asukoht lisamata'
                                                                }}
                                                            </p>
                                                            <div
                                                                class="mt-3 grid grid-cols-2 gap-2 text-[11px]"
                                                            >
                                                                <div
                                                                    class="rounded-xl bg-muted/60 px-2 py-2"
                                                                >
                                                                    <div
                                                                        class="text-muted-foreground"
                                                                    >
                                                                        Mõõt
                                                                    </div>
                                                                    <div
                                                                        class="mt-1 font-semibold text-foreground"
                                                                    >
                                                                        {{
                                                                            formatCentimeters(
                                                                                getBedPhysicalWidthCm(
                                                                                    bed,
                                                                                ),
                                                                            )
                                                                        }}
                                                                        ×
                                                                        {{
                                                                            formatCentimeters(
                                                                                getBedPhysicalHeightCm(
                                                                                    bed,
                                                                                ),
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    class="rounded-xl bg-muted/60 px-2 py-2"
                                                                >
                                                                    <div
                                                                        class="text-muted-foreground"
                                                                    >
                                                                        Taimi
                                                                    </div>
                                                                    <div
                                                                        class="mt-1 font-semibold text-foreground"
                                                                    >
                                                                        {{
                                                                            bed
                                                                                .plants
                                                                                .length
                                                                        }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="mt-3 grid gap-2"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-[11px] font-semibold text-primary transition hover:bg-primary/15"
                                                                    @click.stop="
                                                                        focusBedDetails(
                                                                            bed.id,
                                                                        )
                                                                    "
                                                                >
                                                                    Ava vaade
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-border/70 bg-background px-3 py-2 text-[11px] font-semibold text-foreground transition hover:bg-muted"
                                                                    @click.stop="
                                                                        openBedPage(
                                                                            bed.id,
                                                                        )
                                                                    "
                                                                >
                                                                    Ava peenar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </article>

                                                <article
                                                    v-for="object in plannerObjects"
                                                    :key="`object-${object.id}`"
                                                    class="group absolute transition-transform duration-150"
                                                    :class="
                                                        draggingObjectId ===
                                                        object.id
                                                            ? 'z-30 scale-[1.02]'
                                                            : 'z-10 hover:z-20 hover:-translate-y-1'
                                                    "
                                                    :style="
                                                        plannerObjectStyle(
                                                            object,
                                                        )
                                                    "
                                                    data-object-shape="true"
                                                    @pointerdown="
                                                        startObjectDragging(
                                                            object,
                                                            $event,
                                                        )
                                                    "
                                                    @mouseenter="
                                                        showObjectInfo(
                                                            object.id,
                                                        )
                                                    "
                                                    @mouseleave="
                                                        hideObjectInfo(
                                                            object.id,
                                                        )
                                                    "
                                                    @click.stop="
                                                        focusObjectDetails(
                                                            object.id,
                                                        )
                                                    "
                                                >
                                                    <div
                                                        class="relative z-10 flex h-full w-full items-center justify-center p-1"
                                                    >
                                                        <div
                                                            class="flex max-w-full flex-col items-center gap-1"
                                                        >
                                                            <component
                                                                :is="iconFor(objectVariantIcon(object))"
                                                                :size="objectIconSize(object)"
                                                                :stroke-width="1.5"
                                                                class="relative shrink-0"
                                                                :class="
                                                                    toolVariantIconToneClass(
                                                                        objectVariantType(
                                                                            object,
                                                                        ),
                                                                    )
                                                                "
                                                            />

                                                            <div
                                                                v-if="
                                                                    showPlannerLabels
                                                                "
                                                                class="pointer-events-none max-w-[min(14rem,100%)] truncate rounded-full bg-white/88 px-2.5 py-0.5 text-center text-[11px] font-semibold text-foreground shadow-sm backdrop-blur-sm dark:bg-card/88"
                                                            >
                                                                {{ object.name }}
                                                            </div>

                                                            <div
                                                                v-if="
                                                                    hoveredObjectId ===
                                                                        object.id &&
                                                                    selectedObject?.id !==
                                                                        object.id
                                                                "
                                                            class="z-30 hidden w-44 max-w-[min(14rem,calc(100vw-2rem))] rounded-[1.2rem] border border-emerald-900/10 bg-white/92 p-3 text-left shadow-lg backdrop-blur-sm dark:border-emerald-200/20 dark:bg-card/92 md:block"
                                                        >
                                                            <p
                                                                class="truncate text-sm font-semibold text-foreground"
                                                            >
                                                                {{
                                                                    object.name
                                                                }}
                                                            </p>
                                                            <p
                                                                class="mt-1 text-xs text-muted-foreground"
                                                            >
                                                                {{
                                                                    objectTypeLabel(
                                                                        object.type,
                                                                    )
                                                                }}
                                                            </p>
                                                            <div
                                                                class="mt-3 grid grid-cols-2 gap-2 text-[11px]"
                                                            >
                                                                <div
                                                                    class="rounded-xl bg-muted/60 px-2 py-2"
                                                                >
                                                                    <div
                                                                        class="text-muted-foreground"
                                                                    >
                                                                        Mõõt
                                                                    </div>
                                                                    <div
                                                                        class="mt-1 font-semibold text-foreground"
                                                                    >
                                                                        {{
                                                                            formatCentimeters(
                                                                                object.width,
                                                                            )
                                                                        }}
                                                                        ×
                                                                        {{
                                                                            formatCentimeters(
                                                                                object.height,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    class="rounded-xl bg-muted/60 px-2 py-2"
                                                                >
                                                                    <div
                                                                        class="text-muted-foreground"
                                                                    >
                                                                        Tüüp
                                                                    </div>
                                                                    <div
                                                                        class="mt-1 font-semibold text-foreground"
                                                                    >
                                                                        {{
                                                                            objectTypeLabel(
                                                                                object.type,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-[11px] font-semibold text-primary transition hover:bg-primary/15"
                                                                @click.stop="
                                                                    focusObjectDetails(
                                                                        object.id,
                                                                    )
                                                                "
                                                            >
                                                                Ava vaade
                                                            </button>
                                                        </div>
                                                        </div>
                                                    </div>
                                                </article>
                                            </div>
                                        </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="selectedObject"
                                        ref="selectedObjectPanel"
                                        class="xl:backdrop-blur-0 sticky bottom-20 z-20 mt-3 rounded-[1.5rem] border border-border/80 bg-card/95 p-4 shadow-lg backdrop-blur xl:static xl:bottom-auto xl:z-auto xl:shadow-sm"
                                        :class="
                                            highlightSelectedObjectPanel
                                                ? 'ring-2 ring-primary/35 ring-offset-2 ring-offset-background transition'
                                                : ''
                                        "
                                    >
                                        <div
                                            class="mb-2 flex justify-center xl:hidden"
                                        >
                                            <span
                                                class="h-1 w-10 rounded-full bg-foreground/15"
                                            ></span>
                                        </div>
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-2"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                                >
                                                    Valitud aiaelement
                                                </p>
                                                <h4
                                                    class="mt-0.5 text-base font-semibold text-foreground"
                                                >
                                                    {{ selectedObject.name }}
                                                </h4>
                                                <p
                                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                                >
                                                    {{
                                                        objectTypeDescription(
                                                            selectedObject.type,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                                data-no-drag="true"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background text-foreground transition hover:bg-muted xl:hidden"
                                                    @click="
                                                        clearSelectionDetails
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >close</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-full border border-border bg-background px-2.5 py-1.5 text-[11px] font-semibold text-foreground transition hover:bg-muted"
                                                    @click="
                                                        duplicateSelectedObject
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >content_copy</span
                                                    >
                                                    Dubleeri
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-full border border-border bg-background px-2.5 py-1.5 text-[11px] font-semibold text-foreground transition hover:bg-muted"
                                                    @click="
                                                        rotateSelectedObject
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >rotate_90_degrees_ccw</span
                                                    >
                                                    Pööra
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-100"
                                                    @click="
                                                        deleteSelectedObject
                                                    "
                                                >
                                                    Eemalda
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-2.5 grid grid-cols-3 gap-2"
                                        >
                                            <div
                                                class="min-w-0 rounded-lg border border-border/70 bg-background/70 px-2 py-2"
                                            >
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                >
                                                    Tüüp
                                                </p>
                                                <p
                                                    class="mt-1 break-words text-xs font-semibold leading-snug text-foreground"
                                                >
                                                    {{
                                                        objectTypeLabel(
                                                            selectedObject.type,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                class="min-w-0 rounded-lg border border-border/70 bg-background/70 px-2 py-2"
                                            >
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                >
                                                    Mõõt
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-semibold leading-snug text-foreground"
                                                >
                                                    {{
                                                        formatCentimeters(
                                                            selectedObject.width,
                                                        )
                                                    }}
                                                    ×
                                                    {{
                                                        formatCentimeters(
                                                            selectedObject.height,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                class="min-w-0 rounded-lg border border-border/70 bg-background/70 px-2 py-2"
                                            >
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                >
                                                    Asukoht
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-semibold tabular-nums text-foreground"
                                                >
                                                    {{
                                                        getObjectPosition(
                                                            selectedObject,
                                                        ).x
                                                    }},
                                                    {{
                                                        getObjectPosition(
                                                            selectedObject,
                                                        ).y
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                            <div
                                                class="mt-3 rounded-[1.1rem] border border-border/70 bg-background/70 p-3"
                                            >
                                            <div class="mb-2">
                                                <p
                                                    class="text-xs font-semibold text-foreground"
                                                >
                                                    Muuda mõõte
                                                </p>
                                                <p
                                                    class="mt-0.5 text-[11px] leading-snug text-muted-foreground"
                                                >
                                                    Meetrites; salvestatakse
                                                    sentimeetrites.
                                                </p>
                                            </div>

                                            <div
                                                class="grid gap-2 sm:grid-cols-3"
                                            >
                                                <label
                                                    class="rounded-lg border border-border/70 bg-card/85 px-2 py-2 text-sm"
                                                >
                                                    <span
                                                        class="mb-0.5 block text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                        >Nimi</span
                                                    >
                                                    <input
                                                        v-model="
                                                            objectForm.name
                                                        "
                                                        type="text"
                                                        class="w-full bg-transparent text-xs font-medium text-foreground outline-none"
                                                        maxlength="120"
                                                    />
                                                </label>

                                                <label
                                                    class="rounded-lg border border-border/70 bg-card/85 px-2 py-2 text-sm"
                                                >
                                                    <span
                                                        class="mb-0.5 block text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                        >Laius (m)</span
                                                    >
                                                    <input
                                                        ref="objectWidthInput"
                                                        v-model="
                                                            objectForm.widthMeters
                                                        "
                                                        type="number"
                                                        min="0.5"
                                                        max="50"
                                                        step="0.1"
                                                        class="w-full bg-transparent text-xs font-medium text-foreground outline-none"
                                                    />
                                                </label>

                                                <label
                                                    class="rounded-lg border border-border/70 bg-card/85 px-2 py-2 text-sm"
                                                >
                                                    <span
                                                        class="mb-0.5 block text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                        >Kõrgus (m)</span
                                                    >
                                                    <input
                                                        v-model="
                                                            objectForm.heightMeters
                                                        "
                                                        type="number"
                                                        min="0.5"
                                                        max="50"
                                                        step="0.1"
                                                        class="w-full bg-transparent text-xs font-medium text-foreground outline-none"
                                                    />
                                                </label>
                                            </div>

                                            <div
                                                class="mt-2 flex flex-wrap items-center gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/15"
                                                    :disabled="
                                                        objectForm.processing
                                                    "
                                                    @click="saveSelectedObject"
                                                >
                                                    Salvesta mõõdud
                                                </button>
                                                <p
                                                    v-if="
                                                        objectForm.errors
                                                            .width ||
                                                        objectForm.errors
                                                            .height ||
                                                        objectForm.errors.name
                                                    "
                                                    class="text-xs text-rose-600"
                                                >
                                                    {{
                                                        objectForm.errors
                                                            .name ||
                                                        objectForm.errors
                                                            .width ||
                                                        objectForm.errors.height
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="selectedBed"
                                        ref="selectedBedPanel"
                                        class="xl:backdrop-blur-0 sticky bottom-20 z-20 mt-3 rounded-[1.5rem] border border-border/80 bg-card/95 p-4 shadow-lg backdrop-blur xl:static xl:bottom-auto xl:z-auto xl:shadow-sm"
                                        :class="
                                            highlightSelectedBedPanel
                                                ? 'ring-2 ring-primary/35 ring-offset-2 ring-offset-background transition'
                                                : ''
                                        "
                                    >
                                        <div
                                            class="mb-2 flex justify-center xl:hidden"
                                        >
                                            <span
                                                class="h-1 w-10 rounded-full bg-foreground/15"
                                            ></span>
                                        </div>
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-2"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                                >
                                                    Valitud peenar
                                                </p>
                                                <h4
                                                    class="mt-0.5 text-base font-semibold text-foreground"
                                                >
                                                    {{ selectedBed.name }}
                                                </h4>
                                                <p
                                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                                >
                                                    {{
                                                        selectedBed.location ||
                                                        'Asukoht lisamata'
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                                >
                                                    Ava peenar, et taimi ruutudesse
                                                    paigutada ja märkmeid hallata.
                                                </p>
                                            </div>
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                                data-no-drag="true"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background text-foreground transition hover:bg-muted xl:hidden"
                                                    @click="
                                                        clearSelectionDetails
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-sm"
                                                        >close</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1.5 text-[11px] font-semibold text-primary transition hover:bg-primary/15"
                                                    @click="goToSelectedBed"
                                                >
                                                    Ava peenar
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >arrow_forward</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-full border border-border bg-background px-2.5 py-1.5 text-[11px] font-semibold text-foreground transition hover:bg-muted"
                                                    @click="
                                                        editBed(selectedBed.id)
                                                    "
                                                >
                                                    Muuda peenart
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-100"
                                                    @click="
                                                        deleteBed(
                                                            selectedBed.id,
                                                            selectedBed.name,
                                                        )
                                                    "
                                                >
                                                    Kustuta
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-2.5 grid grid-cols-3 gap-2"
                                        >
                                            <div
                                                class="min-w-0 rounded-lg border border-border/70 bg-background/70 px-2 py-2"
                                            >
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                >
                                                    Kuju
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-semibold leading-snug text-foreground"
                                                >
                                                    {{
                                                        getBedHeightInCells(
                                                            selectedBed,
                                                        )
                                                    }}
                                                    ×
                                                    {{
                                                        getBedWidthInCells(
                                                            selectedBed,
                                                        )
                                                    }}
                                                    ruutu
                                                </p>
                                            </div>
                                            <div
                                                class="min-w-0 rounded-lg border border-border/70 bg-background/70 px-2 py-2"
                                            >
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                >
                                                    Päris mõõt
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-semibold leading-snug text-foreground"
                                                >
                                                    {{
                                                        formatCentimeters(
                                                            getBedPhysicalWidthCm(
                                                                selectedBed,
                                                            ),
                                                        )
                                                    }}
                                                    ×
                                                    {{
                                                        formatCentimeters(
                                                            getBedPhysicalHeightCm(
                                                                selectedBed,
                                                            ),
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                class="min-w-0 rounded-lg border border-border/70 bg-background/70 px-2 py-2"
                                            >
                                                <p
                                                    class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                >
                                                    Taimi
                                                </p>
                                                <p
                                                    class="mt-1 text-xs font-semibold text-foreground"
                                                >
                                                    {{
                                                        selectedBed.plants
                                                            .length
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </main>
                </div>

                <FloatingPlusButton
                    aria-label="Lisa peenar"
                    :size-px="52"
                    :icon-size-px="30"
                    :bottom-px="112"
                    @click="
                        router.visit(
                            `/map/${gardenPlan.id}/beds/new`,
                        )
                    "
                />

                <BottomNav active="map" />
            </div>
        </div>

        <SearchModal
            v-model:open="showSearch"
            :initial-query="searchQuery"
            :suggestions="bedNames"
            title="Otsi peenraid"
            placeholder="Nt: ürdid, tagaaed..."
            @search="(q) => (searchQuery = q)"
            @clear="searchQuery = ''"
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
