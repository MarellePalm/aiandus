<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    Bird,
    Box,
    BrickWall,
    Droplet,
    Droplets,
    Fence,
    Flame,
    Footprints,
    Home,
    House,
    LandPlot,
    LayoutGrid,
    Leaf,
    Logs,
    Pencil,
    Recycle,
    Route,
    Shapes,
    Shovel,
    Shrub,
    Sofa,
    Sprout,
    Tent,
    TreeDeciduous,
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
import SortDropdown from '@/components/SortDropdown.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import SearchModal from '@/pages/Seeds/SearchModal.vue';
import type { AppPageProps } from '@/types';

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
    is_favorite?: boolean;
    sort_order?: number;
    created_at?: string | null;
    rows: number;
    columns: number;
    garden_x: number;
    garden_y: number;
    cell_size_cm: number;
    layout?: number[][] | null;
    plants: PlantInBed[];
};

type BedListTabKey = 'all' | 'favorites';

const bedSortOptions = [
    { label: 'Järjekord aiaplaanil', value: 'plan_order' },
    { label: 'Nimi A–Z', value: 'name_asc' },
    { label: 'Nimi Z–A', value: 'name_desc' },
    { label: 'Loodud: uuemad enne', value: 'created_desc' },
    { label: 'Loodud: vanemad enne', value: 'created_asc' },
] as { label: string; value: string }[];

function parseBedCreatedAt(bed: Bed): number {
    if (!bed.created_at) return 0;
    const t = new Date(bed.created_at).getTime();
    return Number.isNaN(t) ? 0 : t;
}

function sortBedsForPlanner(beds: Bed[], sortKey: string): Bed[] {
    const list = [...beds];
    switch (sortKey) {
        case 'name_desc':
            return list.sort((a, b) =>
                (b.name ?? '').localeCompare(a.name ?? '', 'et', {
                    sensitivity: 'base',
                }),
            );
        case 'created_desc':
            return list.sort(
                (a, b) => parseBedCreatedAt(b) - parseBedCreatedAt(a),
            );
        case 'created_asc':
            return list.sort(
                (a, b) => parseBedCreatedAt(a) - parseBedCreatedAt(b),
            );
        case 'name_asc':
            return list.sort((a, b) =>
                (a.name ?? '').localeCompare(b.name ?? '', 'et', {
                    sensitivity: 'base',
                }),
            );
        case 'plan_order':
        default:
            return list.sort((a, b) => {
                const ao = a.sort_order ?? 0;
                const bo = b.sort_order ?? 0;
                if (ao !== bo) return ao - bo;
                return (a.name ?? '').localeCompare(b.name ?? '', 'et', {
                    sensitivity: 'base',
                });
            });
    }
}
type GardenObjectType = 'greenhouse' | 'pond' | 'shed' | 'compost' | 'other';
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
type ToolCategoryId = 'buildings' | 'water' | 'paths' | 'maintenance';
type PlannerListView = 'beds' | 'objects';
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

type MapPageProps = AppPageProps<{
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    errors?: Record<string, string>;
}>;

type DimensionFormErrors = Record<'width' | 'height', string | undefined>;

const page = usePage<MapPageProps>();
const breadcrumbs = computed(() => [
    { title: 'Aiaplaan', href: `/map/${props.gardenPlan.id}` },
]);
const showOnboardingHint = computed(() => props.beds.length === 0);
const showSearch = ref(false);
const searchQuery = ref('');

const localBeds = ref<Bed[]>([...props.beds]);
watch(
    () => props.beds,
    (next) => {
        localBeds.value = [...next];
    },
);

const mobileBedListTab = ref<BedListTabKey>('all');
const selectedBedSort = ref('plan_order');

function bedListTabClass(key: BedListTabKey) {
    const base =
        'px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition';

    return mobileBedListTab.value === key
        ? `${base} bg-primary text-primary-foreground`
        : `${base} bg-primary/10 text-primary`;
}

function toggleBedFavorite(bedId: number) {
    const idx = localBeds.value.findIndex((b) => b.id === bedId);
    if (idx === -1) return;

    const prev = localBeds.value[idx].is_favorite === true;

    localBeds.value[idx] = {
        ...localBeds.value[idx],
        is_favorite: !prev,
    };

    router.patch(
        `/beds/${bedId}/favorite`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                localBeds.value[idx] = {
                    ...localBeds.value[idx],
                    is_favorite: prev,
                };
            },
        },
    );
}

const GARDEN_PADDING = 24;
const CM_TO_PX = 0.5;
const GARDEN_GRID_CELL_CM = 30;
const MIN_ZOOM = 0.1;
/** "Mahuta sisu" / "Kogu aed" may go lower so large plans actually fit on screen. */
const FIT_VIEW_MIN_ZOOM = 0.001;
/** When fitting content, zoom must be at least high enough that items are still visible pixels. */
const CONTENT_FIT_MIN_VISIBLE_ITEM_PX = 14;
const MAX_ZOOM = 4.5;
const ZOOM_STEP = 0.25;
const FOCUSED_BED_MIN_ZOOM = 0.75;
const MIN_PINCH_DISTANCE_PX = 10;
const CONTENT_FIT_PADDING = 160;
const CONTENT_FIT_SIDE_PADDING = 40;
const INITIAL_GARDEN_MIN_VIEWPORT_WIDTH = 0.86;
const INITIAL_GARDEN_MIN_VIEWPORT_HEIGHT = 0.82;
const MIN_BED_VISUAL_SIZE = 44;
const MIN_OBJECT_VISUAL_SIZE = 48;
const MAX_GARDEN_SURFACE_WIDTH = 3200;
const MAX_GARDEN_SURFACE_HEIGHT = 2200;

const viewportPointerMap = new Map<number, { x: number; y: number }>();
type ViewportPinchState = {
    startDistance: number;
    startZoom: number;
    gx: number;
    gy: number;
};

const localPositions = ref<Record<number, { x: number; y: number }>>({});
const localObjectPositions = ref<Record<number, { x: number; y: number }>>({});
const draggingBedId = ref<number | null>(null);
const draggingObjectId = ref<number | null>(null);
const dragMoved = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const dragPointerId = ref<number | null>(null);
const dragCaptureTarget = ref<HTMLElement | null>(null);
const panPointerId = ref<number | null>(null);
const pinchState = ref<ViewportPinchState | null>(null);
const hoveredBedId = ref<number | null>(null);
const hoveredObjectId = ref<number | null>(null);
const initialFocusedBedId = getInitialFocusedBedId();
const selectedBedId = ref<number | null>(initialFocusedBedId);
const selectedObjectId = ref<number | null>(null);
const isLayoutEditing = ref(initialFocusedBedId !== null);
const plannerListView = ref<PlannerListView | null>(null);
const activeTool = ref<GardenObjectType | null>(null);
const activeToolVariant = ref<ToolVariant | null>(null);
const activeToolCategoryId = ref<ToolCategoryId | null>(null);
const createMenuOpen = ref(false);
const isMdUp = useMediaQuery('(min-width: 768px)');
const mobileBedListPanelRef = ref<HTMLElement | null>(null);
const mobileBedListPanelInView = ref(false);
const toolDrawerOpen = ref(false);
const plannerControlsOpen = ref(false);
const createGardenPlanModalOpen = ref(false);
const confirmDialogOpen = ref(false);
const toolSearch = ref('');
const otherObjectNameDraft = ref('');
const otherObjectNameError = ref('');
const createGardenPlanNameInput = ref<HTMLInputElement | null>(null);
const confirmDialogTitle = ref('');
const confirmDialogMessage = ref('');
const confirmDialogConfirmLabel = ref('Kinnita');
let confirmDialogAction: (() => void) | null = null;
const showBedsLayer = ref(true);
const showStructuresLayer = ref(true);
const showWaterLayer = ref(true);
const selectedQuickCategoryId = ref<ToolCategoryId | null>(null);
const toolCategories: ToolCategory[] = [
    {
        id: 'buildings',
        label: 'Hooned',
        icon: 'house',
        description: 'Maja, kasvuhoone, kuur ja varjualune.',
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
                id: 'shed-tool',
                type: 'shed',
                label: 'Kuur',
                description: 'Panipaik tööriistadele ja varustusele.',
                width: 240,
                height: 180,
                icon: 'wrench',
            },
            {
                id: 'other-shelter',
                type: 'other',
                label: 'Varjualune',
                description: 'Paviljon, pergola või lihtne katusealune.',
                width: 320,
                height: 220,
                icon: 'tent',
            },
        ],
    },
    {
        id: 'water',
        label: 'Vesi',
        icon: 'droplets',
        description: 'Tiik, kraav, oja ja vee kogumine.',
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
                id: 'ditch',
                type: 'pond',
                label: 'Kraav',
                description: 'Pikk kuivendus- või piirdekraav.',
                width: 520,
                height: 80,
                icon: 'route',
            },
            {
                id: 'stream',
                type: 'pond',
                label: 'Oja / jõgi',
                description: 'Voolav veejoon aia servas või sees.',
                width: 620,
                height: 120,
                icon: 'waves',
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
            {
                id: 'pond-fountain',
                type: 'pond',
                label: 'Purskkaev',
                description: 'Väike purskkaev või dekoratiivne veepunkt.',
                width: 50,
                height: 50,
                icon: 'droplet',
            },
        ],
    },
    {
        id: 'paths',
        label: 'Teed ja piirid',
        icon: 'route',
        description: 'Rajad, aiad, väravad ja hekid.',
        variants: [
            {
                id: 'other-path',
                type: 'other',
                label: 'Tee / rada',
                description: 'Käigurada peenarde või hoonete vahel.',
                width: 520,
                height: 90,
                icon: 'footprints',
            },
            {
                id: 'other-fence',
                type: 'other',
                label: 'Aed / piire',
                description: 'Piirdeaed, võrk või peenarde eraldus.',
                width: 620,
                height: 70,
                icon: 'fence',
            },
            {
                id: 'other-gate',
                type: 'other',
                label: 'Värav',
                description: 'Sissepääs või läbipääs aiapiirdes.',
                width: 140,
                height: 70,
                icon: 'land-plot',
            },
            {
                id: 'other-hedge',
                type: 'other',
                label: 'Hekk',
                description: 'Elav piire või tuulekaitse.',
                width: 520,
                height: 90,
                icon: 'shrub',
            },
        ],
    },
    {
        id: 'maintenance',
        label: 'Hooldus ja puud',
        icon: 'shovel',
        description: 'Kompost, puuriit, puud ja vabalt nimetatud objektid.',
        variants: [
            {
                id: 'compost-bin',
                type: 'compost',
                label: 'Kompost',
                description: 'Üks kompostikoht või kompostikast.',
                width: 160,
                height: 140,
                icon: 'recycle',
            },
            {
                id: 'other-woodpile',
                type: 'other',
                label: 'Puuriit',
                description: 'Küttepuude või okste hoiukoht.',
                width: 240,
                height: 120,
                icon: 'logs',
            },
            {
                id: 'other-tree',
                type: 'other',
                label: 'Puu / põõsas',
                description: 'Olemasolev puu, marjapõõsas või istutusala.',
                width: 160,
                height: 160,
                icon: 'tree-deciduous',
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
const objectWidthInput = ref<HTMLInputElement | null>(null);
const viewportSize = ref({ width: 0, height: 0 });
/** First fit after mount/plan change; retries when ResizeObserver gets non-zero size. */
const plannerInitialViewportFitDone = ref(false);
const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const panGestureMoved = ref(false);
const suppressPlannerSurfaceClick = ref(false);
const panStart = ref({ x: 0, y: 0, originX: 0, originY: 0 });
const PAN_CLICK_SUPPRESS_PX = 8;
let resizeObserver: ResizeObserver | null = null;
let mobileBedListIntersectionObserver: IntersectionObserver | null = null;
const highlightSelectedObjectPanel = ref(false);
let objectPanelHighlightTimeout: ReturnType<typeof setTimeout> | null = null;
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
const gardenFormDimensionErrors = computed(
    () => gardenForm.errors as typeof gardenForm.errors & DimensionFormErrors,
);
const createGardenPlanForm = useForm({
    name: '',
});
const objectForm = useForm({
    name: '',
    widthMeters: 0,
    heightMeters: 0,
});
const objectFormDimensionErrors = computed(
    () => objectForm.errors as typeof objectForm.errors & DimensionFormErrors,
);

function openGardenPlanEditor() {
    plannerControlsOpen.value = true;
}

function deleteGardenPlan() {
    openConfirmDialog(
        'Kustuta aiaplaan?',
        'Kustutada see aiaplaan koos selle peenarde ja aiaobjektidega? Sidumata taimed jäävad alles.',
        'Kustuta aiaplaan',
        () => {
            router.delete(`/garden-plans/${props.gardenPlan.id}`);
        },
    );
}

function onGardenPlanSelect(event: Event) {
    const el = event.target as HTMLSelectElement;
    const id = Number(el.value);
    if (!id || id === props.gardenPlan.id) return;
    router.visit(`/map/${id}`);
}

function openCreateGardenPlanModal() {
    createMenuOpen.value = false;
    createGardenPlanForm.reset();
    createGardenPlanForm.clearErrors();
    createGardenPlanModalOpen.value = true;
    nextTick(() => createGardenPlanNameInput.value?.focus());
}

function closeCreateGardenPlanModal() {
    if (createGardenPlanForm.processing) return;
    createGardenPlanModalOpen.value = false;
}

function submitCreateGardenPlan() {
    createGardenPlanForm
        .transform((data) => ({
            name: data.name.trim() || undefined,
        }))
        .post('/garden-plans', {
            preserveScroll: true,
            onSuccess: () => {
                createGardenPlanModalOpen.value = false;
                createGardenPlanForm.reset();
            },
            onError: () => {
                nextTick(() => createGardenPlanNameInput.value?.focus());
            },
        });
}

function openConfirmDialog(
    title: string,
    message: string,
    confirmLabel: string,
    action: () => void,
) {
    confirmDialogTitle.value = title;
    confirmDialogMessage.value = message;
    confirmDialogConfirmLabel.value = confirmLabel;
    confirmDialogAction = action;
    confirmDialogOpen.value = true;
}

function closeConfirmDialog() {
    confirmDialogOpen.value = false;
    confirmDialogAction = null;
}

function runConfirmDialogAction() {
    const action = confirmDialogAction;
    closeConfirmDialog();
    action?.();
}

watch(
    () => props.gardenPlan.id,
    (id, prev) => {
        syncPositionsFromProps();
        if (prev !== undefined && id !== prev) {
            plannerInitialViewportFitDone.value = false;
            selectedBedId.value = null;
            selectedObjectId.value = null;
            plannerControlsOpen.value = false;
            searchQuery.value = '';
            showBedsLayer.value = true;
            showStructuresLayer.value = true;
            showWaterLayer.value = true;
            nextTick(() => {
                if (plannerViewport.value) {
                    viewportSize.value = {
                        width: plannerViewport.value.clientWidth,
                        height: plannerViewport.value.clientHeight,
                    };
                }
                runPlannerInitialViewportFit();
            });
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

watch(
    mobileBedListPanelRef,
    (el) => {
        mobileBedListIntersectionObserver?.disconnect();
        mobileBedListIntersectionObserver = null;
        mobileBedListPanelInView.value = false;
        if (!el || typeof IntersectionObserver === 'undefined') return;
        const observer = new IntersectionObserver(
            (entries) => {
                const entry = entries[0];
                mobileBedListPanelInView.value = Boolean(
                    entry?.isIntersecting && entry.intersectionRatio > 0.12,
                );
            },
            { root: null, threshold: [0, 0.12, 0.2, 0.35, 0.5, 0.75, 1] },
        );
        observer.observe(el);
        mobileBedListIntersectionObserver = observer;
    },
    { flush: 'post' },
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
        runPlannerInitialViewportFit();
    });

    if (typeof ResizeObserver !== 'undefined' && plannerViewport.value) {
        resizeObserver = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (!entry) return;
            viewportSize.value = {
                width: entry.contentRect.width,
                height: entry.contentRect.height,
            };
            nextTick(() => {
                runPlannerInitialViewportFit();
            });
        });
        resizeObserver.observe(plannerViewport.value);
    }
});

function getInitialFocusedBedId(): number | null {
    if (typeof window === 'undefined') return null;

    const value = new URLSearchParams(window.location.search).get('bed');
    if (!value) return null;

    const bedId = Number(value);
    if (!Number.isInteger(bedId)) return null;

    return props.beds.some((bed) => bed.id === bedId) ? bedId : null;
}

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
    window.removeEventListener('pointermove', onPlannerWindowPointerMove);
    window.removeEventListener('pointerup', stopPanning);
    window.removeEventListener('pointercancel', stopPanning);
    window.removeEventListener('pointerup', onPinchPointerUp);
    window.removeEventListener('pointercancel', onPinchPointerUp);
    viewportPointerMap.clear();
    pinchState.value = null;
    resizeObserver?.disconnect();
    mobileBedListIntersectionObserver?.disconnect();
    if (objectPanelHighlightTimeout) clearTimeout(objectPanelHighlightTimeout);
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

const bedNames = computed(() => localBeds.value.map((b) => b.name));

const filteredBeds = computed(() => {
    let list = sortBedsForPlanner(localBeds.value, selectedBedSort.value);

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

const mobileListBeds = computed(() => {
    let list = [...filteredBeds.value];

    if (mobileBedListTab.value === 'favorites') {
        list = list.filter((b) => b.is_favorite === true);
    }

    return list;
});

const mobileBedCountSuffix = computed(() => {
    const n = mobileListBeds.value.length;
    if (mobileBedListTab.value === 'favorites') {
        return n === 1 ? 'lemmik' : 'lemmikut';
    }
    return n === 1 ? 'peenar' : 'peenart';
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

/** Arvutil on lohistamine mõistlik; mobiilis ei kuva seda teadet. */
const isBedAddedDragHintFlash = computed(() => {
    const fb = plannerFeedback.value;
    if (!fb || fb.tone !== 'success') return false;
    return fb.message.includes('Lohista see aiaplaanil');
});

const gardenWidthCm = computed(() =>
    Math.max(1, Math.round(Number(gardenForm.widthMeters || 0) * 100)),
);
const gardenHeightCm = computed(() =>
    Math.max(1, Math.round(Number(gardenForm.heightMeters || 0) * 100)),
);
const gardenSurfaceWidth = computed(() =>
    Math.min(
        MAX_GARDEN_SURFACE_WIDTH,
        Math.max(320, Math.round(gardenWidthCm.value * CM_TO_PX)),
    ),
);
const gardenSurfaceHeight = computed(() =>
    Math.min(
        MAX_GARDEN_SURFACE_HEIGHT,
        Math.max(240, Math.round(gardenHeightCm.value * CM_TO_PX)),
    ),
);
const zoomPercent = computed(() => `${Math.round(zoom.value * 100)}%`);
const gardenDimensionLabel = computed(() => {
    const fmt = (v: number) => {
        const n = Math.round(v * 100) / 100;
        return Number.isInteger(n)
            ? `${n}`
            : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    };
    const w = Number(gardenForm.widthMeters || 0);
    const h = Number(gardenForm.heightMeters || 0);
    return `${fmt(w)} m × ${fmt(h)} m`;
});
const plannerGridSizePx = computed(() =>
    Math.max(10, Math.round(GARDEN_GRID_CELL_CM * CM_TO_PX)),
);
const scaleBarWidthPx = computed(() => Math.round(100 * CM_TO_PX));
const plannerBeds = computed(() => (showBedsLayer.value ? props.beds : []));
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
                const haystack =
                    `${category.label} ${variant.label} ${variant.description}`.toLowerCase();
                return haystack.includes(query);
            }),
        }))
        .filter((category) => category.variants.length > 0);
});
const selectedToolDisplayLabel = computed(
    () =>
        activeToolVariant.value?.label ??
        (activeTool.value ? objectTypeLabel(activeTool.value) : ''),
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

watch(plannerBeds, (beds) => {
    if (
        selectedBedId.value !== null &&
        !beds.some((bed) => bed.id === selectedBedId.value)
    ) {
        selectedBedId.value = beds[0]?.id ?? null;
    }
});

watch(
    () =>
        props.beds.map((bed) => [
            bed.id,
            bed.garden_x ?? GARDEN_PADDING,
            bed.garden_y ?? GARDEN_PADDING,
        ]),
    () => {
        const current = localPositions.value;
        const next: Record<number, { x: number; y: number }> = {};

        props.beds.forEach((bed) => {
            next[bed.id] = current[bed.id] ?? {
                x: bed.garden_x ?? GARDEN_PADDING,
                y: bed.garden_y ?? GARDEN_PADDING,
            };
        });

        localPositions.value = next;
    },
    { deep: true },
);

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
        paths: 'text-stone-600',
        maintenance: 'text-amber-700',
    }[categoryId];
};
const categoryButtonToneClass = (
    categoryId: ToolCategoryId,
    active: boolean,
) => {
    if (active) {
        return {
            buildings: 'border-primary/30 bg-primary/12 text-primary shadow-sm',
            water: 'border-primary/30 bg-primary/12 text-primary shadow-sm',
            paths: 'border-primary/30 bg-primary/12 text-primary shadow-sm',
            maintenance:
                'border-primary/30 bg-primary/12 text-primary shadow-sm',
        }[categoryId];
    }

    return {
        buildings:
            'border-border/70 bg-background text-foreground hover:bg-muted/70',
        water: 'border-border/70 bg-background text-foreground hover:bg-muted/70',
        paths: 'border-border/70 bg-background text-foreground hover:bg-muted/70',
        maintenance:
            'border-border/70 bg-background text-foreground hover:bg-muted/70',
    }[categoryId];
};

function deleteBed(id: number, name: string) {
    openConfirmDialog(
        'Eemalda peenar?',
        `Eemaldada peenar "${name}"? Taimed jäävad peenrata.`,
        'Eemalda peenar',
        () => {
            router.delete(`/beds/${id}`, { preserveScroll: true });
        },
    );
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

function getBedPreviewImage(bed: Bed): string | null {
    if (bed.image_url) return bed.image_url;
    return bed.plants.find((plant) => plant.image_url)?.image_url ?? null;
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

function bedPlantsCount(bed: Bed): number {
    return bed.plants?.length ?? 0;
}

function bedPlantSummaryLine(bed: Bed): string {
    const n = bedPlantsCount(bed);
    if (n === 0) return 'Taimi veel ei ole';
    if (n === 1) return '1 taim';
    return `${n} taime`;
}

function bedDimensionsLabel(bed: Bed): string {
    return `${formatCentimeters(getBedPhysicalWidthCm(bed))} × ${formatCentimeters(getBedPhysicalHeightCm(bed))}`;
}

function bedPreviewGridStyle(bed: Bed) {
    const cols = getBedWidthInCells(bed);
    const rows = getBedHeightInCells(bed);

    return {
        width: '100%',
        height: '100%',
        gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))`,
        gridTemplateRows: `repeat(${rows}, minmax(0, 1fr))`,
    };
}

function mapPlannerBedCellClass(
    cell: number,
    bed: Bed,
    visibleRow: number,
    visibleCol: number,
): string {
    if (cell === -1) {
        return 'bed-cell bed-cell--walkway min-h-0 min-w-0';
    }
    if (cell === 0) {
        return 'pointer-events-none min-h-0 min-w-0 opacity-0';
    }
    const plant = getPlantAtVisibleCell(bed, visibleRow, visibleCol);
    if (plant?.image_url) {
        return 'bed-cell bed-cell--planted bed-cell--photo min-h-0 min-w-0';
    }
    if (plant) {
        return 'bed-cell bed-cell--planted min-h-0 min-w-0';
    }
    const warm = (visibleRow + visibleCol) % 2 === 0;
    return (
        (warm
            ? 'bed-cell bed-cell--empty bed-cell--warm'
            : 'bed-cell bed-cell--empty') + ' min-h-0 min-w-0'
    );
}

function bedCardSize(bed: Bed) {
    return {
        width: Math.max(
            MIN_BED_VISUAL_SIZE,
            Math.round(getBedPhysicalWidthCm(bed) * CM_TO_PX),
        ),
        height: Math.max(
            MIN_BED_VISUAL_SIZE,
            Math.round(getBedPhysicalHeightCm(bed) * CM_TO_PX),
        ),
    };
}

function getObjectPixelWidth(object: GardenObject): number {
    if (isIconOnlyObject(object)) return MIN_OBJECT_VISUAL_SIZE;

    return Math.max(
        MIN_OBJECT_VISUAL_SIZE,
        Math.round(object.width * CM_TO_PX),
    );
}

function getObjectPixelHeight(object: GardenObject): number {
    if (isIconOnlyObject(object)) return MIN_OBJECT_VISUAL_SIZE;

    return Math.max(
        MIN_OBJECT_VISUAL_SIZE,
        Math.round(object.height * CM_TO_PX),
    );
}

function isIconOnlyObject(object: GardenObject): boolean {
    const variantId =
        object.meta && typeof object.meta === 'object'
            ? (object.meta['variant_id'] as string | undefined)
            : undefined;

    return (
        variantId === 'pond-fountain' ||
        object.name.trim().toLowerCase() === 'purskkaev'
    );
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

function getPlannerLocalPoint(event: PointerEvent | WheelEvent | MouseEvent) {
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

function openBedEditPage(bedId: number) {
    if (dragMoved.value) return;
    router.get(`/beds/${bedId}/edit`);
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
    isLayoutEditing.value = true;
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

function togglePlannerListView(view: PlannerListView) {
    plannerListView.value = plannerListView.value === view ? null : view;
}

function startDragging(bed: Bed, event: PointerEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-no-drag="true"]')) return;
    event.preventDefault();
    event.stopPropagation();
    isLayoutEditing.value = true;

    const current = getBedPosition(bed);
    const pointer = getPlannerLocalPoint(event);
    draggingBedId.value = bed.id;
    dragMoved.value = false;
    dragPointerId.value = event.pointerId;
    const captureEl = event.currentTarget as HTMLElement | null;
    dragCaptureTarget.value = captureEl;
    if (captureEl) {
        try {
            captureEl.setPointerCapture(event.pointerId);
        } catch {
            /* noop */
        }
    }
    dragOffset.value = {
        x: pointer.x - current.x,
        y: pointer.y - current.y,
    };

    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', stopDragging);
    window.addEventListener('pointercancel', stopDragging);
}

function onPointerMove(event: PointerEvent) {
    if (
        draggingBedId.value === null ||
        event.pointerId !== dragPointerId.value
    ) {
        return;
    }

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
    const moved = dragMoved.value;
    if (dragCaptureTarget.value && dragPointerId.value !== null) {
        try {
            dragCaptureTarget.value.releasePointerCapture(dragPointerId.value);
        } catch {
            /* noop */
        }
    }
    dragCaptureTarget.value = null;
    draggingBedId.value = null;
    dragPointerId.value = null;

    if (!moved) return;

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
    if (!isLayoutEditing.value) return;

    const current = getObjectPosition(object);
    const pointer = getPlannerLocalPoint(event);
    draggingObjectId.value = object.id;
    dragMoved.value = false;
    dragPointerId.value = event.pointerId;
    const captureEl = event.currentTarget as HTMLElement | null;
    dragCaptureTarget.value = captureEl;
    if (captureEl) {
        try {
            captureEl.setPointerCapture(event.pointerId);
        } catch {
            /* noop */
        }
    }
    dragOffset.value = {
        x: pointer.x - current.x,
        y: pointer.y - current.y,
    };

    window.addEventListener('pointermove', onObjectPointerMove);
    window.addEventListener('pointerup', stopObjectDragging);
    window.addEventListener('pointercancel', stopObjectDragging);
}

function onObjectPointerMove(event: PointerEvent) {
    if (
        draggingObjectId.value === null ||
        event.pointerId !== dragPointerId.value
    ) {
        return;
    }

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
    const moved = dragMoved.value;
    if (dragCaptureTarget.value && dragPointerId.value !== null) {
        try {
            dragCaptureTarget.value.releasePointerCapture(dragPointerId.value);
        } catch {
            /* noop */
        }
    }
    dragCaptureTarget.value = null;
    draggingObjectId.value = null;
    dragPointerId.value = null;

    if (!moved) return;

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

function isPlannerPanBlockedTarget(target: EventTarget | null): boolean {
    if (!target || !(target instanceof Element)) {
        return false;
    }
    return Boolean(
        target.closest(
            '[data-bed-shape="true"], [data-object-shape="true"], [data-no-drag="true"], [data-ui-overlay="true"], button, a, input, select, textarea, label',
        ),
    );
}

function endPanGestureForPinch() {
    if (!isPanning.value) {
        return;
    }
    window.removeEventListener('pointermove', onPlannerWindowPointerMove);
    window.removeEventListener('pointerup', stopPanning);
    window.removeEventListener('pointercancel', stopPanning);
    const vp = plannerViewport.value;
    if (vp && panPointerId.value !== null) {
        try {
            vp.releasePointerCapture(panPointerId.value);
        } catch {
            /* noop */
        }
    }
    panPointerId.value = null;
    isPanning.value = false;
    panGestureMoved.value = false;
}

function beginPinchGesture(vp: HTMLElement) {
    const rect = vp.getBoundingClientRect();
    const pts = [...viewportPointerMap.values()];
    if (pts.length < 2) {
        return;
    }
    const [p1, p2] = pts;
    const dist = Math.hypot(p2.x - p1.x, p2.y - p1.y);
    if (dist < MIN_PINCH_DISTANCE_PX) {
        return;
    }
    const mx = (p1.x + p2.x) / 2 - rect.left;
    const my = (p1.y + p2.y) / 2 - rect.top;
    const gx = (mx - panX.value) / zoom.value;
    const gy = (my - panY.value) / zoom.value;
    pinchState.value = {
        startDistance: dist,
        startZoom: zoom.value,
        gx,
        gy,
    };
    for (const id of viewportPointerMap.keys()) {
        try {
            vp.setPointerCapture(id);
        } catch {
            /* noop */
        }
    }
    window.addEventListener('pointermove', onPlannerWindowPointerMove);
    window.addEventListener('pointerup', onPinchPointerUp);
    window.addEventListener('pointercancel', onPinchPointerUp);
}

function applyPinchFromMap() {
    const state = pinchState.value;
    const vp = plannerViewport.value;
    if (!state || !vp || viewportPointerMap.size < 2) {
        return;
    }
    const pts = [...viewportPointerMap.values()];
    if (pts.length < 2) {
        return;
    }
    const [a, b] = pts;
    const dist = Math.hypot(b.x - a.x, b.y - a.y);
    if (dist < 1) {
        return;
    }
    const ratio = dist / state.startDistance;
    const newZoom = Math.min(
        MAX_ZOOM,
        Math.max(MIN_ZOOM, Number((state.startZoom * ratio).toFixed(3))),
    );
    zoom.value = newZoom;
    panX.value = 0;
    panY.value = 0;
}

function onPlannerWindowPointerMove(event: PointerEvent) {
    if (viewportPointerMap.has(event.pointerId)) {
        viewportPointerMap.set(event.pointerId, {
            x: event.clientX,
            y: event.clientY,
        });
    }

    if (pinchState.value) {
        applyPinchFromMap();
        return;
    }

    if (!isPanning.value || event.pointerId !== panPointerId.value) {
        return;
    }

    const dx = event.clientX - panStart.value.x;
    const dy = event.clientY - panStart.value.y;
    if (
        !panGestureMoved.value &&
        (Math.abs(dx) > PAN_CLICK_SUPPRESS_PX ||
            Math.abs(dy) > PAN_CLICK_SUPPRESS_PX)
    ) {
        panGestureMoved.value = true;
    }

    panX.value = panStart.value.originX + dx;
    panY.value = panStart.value.originY + dy;
}

function onPinchPointerUp(event: PointerEvent) {
    viewportPointerMap.delete(event.pointerId);
    if (!pinchState.value) {
        if (viewportPointerMap.size === 0) {
            window.removeEventListener('pointerup', onPinchPointerUp);
            window.removeEventListener('pointercancel', onPinchPointerUp);
        }
        return;
    }
    const vp = plannerViewport.value;
    if (vp) {
        try {
            vp.releasePointerCapture(event.pointerId);
        } catch {
            /* noop */
        }
    }
    if (viewportPointerMap.size < 2) {
        window.removeEventListener('pointermove', onPlannerWindowPointerMove);
        window.removeEventListener('pointerup', onPinchPointerUp);
        window.removeEventListener('pointercancel', onPinchPointerUp);
        pinchState.value = null;
        suppressPlannerSurfaceClick.value = true;
        nextTick(() => {
            suppressPlannerSurfaceClick.value = false;
        });
    }
}

function handleViewportPointerDown(event: PointerEvent) {
    if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
    }
    if (isPlannerPanBlockedTarget(event.target)) {
        return;
    }

    const vp = plannerViewport.value;
    if (!vp) {
        return;
    }

    viewportPointerMap.set(event.pointerId, {
        x: event.clientX,
        y: event.clientY,
    });

    if (viewportPointerMap.size === 2) {
        const pts = [...viewportPointerMap.values()];
        const dist = Math.hypot(pts[1].x - pts[0].x, pts[1].y - pts[0].y);
        if (dist < MIN_PINCH_DISTANCE_PX) {
            const ids = [...viewportPointerMap.keys()];
            viewportPointerMap.delete(ids[ids.length - 1]);
            return;
        }
        endPanGestureForPinch();
        beginPinchGesture(vp);
        return;
    }

    if (viewportPointerMap.size !== 1) {
        return;
    }
    window.addEventListener('pointerup', onPinchPointerUp);
    window.addEventListener('pointercancel', onPinchPointerUp);
}

function getDefaultToolVariant(type: GardenObjectType): ToolVariant {
    const match = allToolVariants.find((variant) => variant.type === type);
    return match ?? allToolVariants[0];
}

function chooseToolVariant(variant: ToolVariant) {
    isLayoutEditing.value = true;
    activeTool.value = variant.type;
    activeToolVariant.value = variant;
    const category = toolCategories.find((item) =>
        item.variants.some((entry) => entry.id === variant.id),
    );
    activeToolCategoryId.value = category?.id ?? null;
    selectedQuickCategoryId.value = null;
    otherObjectNameError.value = '';
    if (variant.type === 'other') {
        otherObjectNameDraft.value = variant.requiresCustomName
            ? ''
            : variant.label;
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
    createMenuOpen.value = false;
    router.get(`/map/${props.gardenPlan.id}/beds/new`);
}

function onFloatingPlusClick() {
    const inBedListContext =
        plannerListView.value === 'beds' ||
        (!isMdUp.value && mobileBedListPanelInView.value);
    if (inBedListContext) {
        openCreateBed();
        return;
    }
    createMenuOpen.value = !createMenuOpen.value;
}

function resetPlannerFilters() {
    showBedsLayer.value = true;
    showStructuresLayer.value = true;
    showWaterLayer.value = true;
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
    if (!isLayoutEditing.value) return;
    if (
        isPanning.value ||
        dragMoved.value ||
        suppressPlannerSurfaceClick.value
    ) {
        return;
    }

    const target = event.target as HTMLElement | null;
    if (
        target?.closest(
            '[data-bed-shape="true"], [data-object-shape="true"], button, input, label, a',
        )
    )
        return;

    if (
        activeTool.value === 'other' &&
        activeToolVariant.value?.requiresCustomName
    ) {
        if (!otherObjectNameDraft.value.trim()) {
            otherObjectNameError.value = 'Palun sisesta objekti nimi.';
            return;
        }
        otherObjectNameError.value = '';
    }

    const config =
        activeToolVariant.value ?? getDefaultToolVariant(activeTool.value);
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
            ? otherObjectNameDraft.value.trim() || config.label
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
    const target = selectedObject.value;
    openConfirmDialog(
        'Eemalda aiaelement?',
        `Eemaldada aiaelement "${target.name}"?`,
        'Eemalda element',
        () => {
            router.delete(`/garden-objects/${target.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    selectedObjectId.value = null;
                    hoveredObjectId.value = null;
                },
            });
        },
    );
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
    'brick-wall': BrickWall,
    fence: Fence,
    footprints: Footprints,
    wrench: Wrench,
    logs: Logs,
    waves: Waves,
    droplet: Droplet,
    droplets: Droplets,
    bird: Bird,
    recycle: Recycle,
    leaf: Leaf,
    'land-plot': LandPlot,
    route: Route,
    shovel: Shovel,
    shrub: Shrub,
    sofa: Sofa,
    flame: Flame,
    'tree-deciduous': TreeDeciduous,
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

function stopPanning(event?: PointerEvent) {
    if (
        event &&
        panPointerId.value !== null &&
        event.pointerId !== panPointerId.value
    ) {
        return;
    }

    if (!isPanning.value) {
        return;
    }

    window.removeEventListener('pointermove', onPlannerWindowPointerMove);
    window.removeEventListener('pointerup', stopPanning);
    window.removeEventListener('pointercancel', stopPanning);

    const vp = plannerViewport.value;
    if (vp && panPointerId.value !== null) {
        try {
            vp.releasePointerCapture(panPointerId.value);
        } catch {
            /* noop */
        }
        viewportPointerMap.delete(panPointerId.value);
    }

    panPointerId.value = null;
    const moved = panGestureMoved.value;
    isPanning.value = false;

    if (moved) {
        suppressPlannerSurfaceClick.value = true;
        nextTick(() => {
            suppressPlannerSurfaceClick.value = false;
        });
    }
    panGestureMoved.value = false;
}

function plannerSurfaceStyle() {
    return {
        width: `${gardenSurfaceWidth.value}px`,
        height: `${gardenSurfaceHeight.value}px`,
        transform: `scale(${zoom.value})`,
        transformOrigin: 'top left',
        backgroundImage: [
            'linear-gradient(180deg, rgba(243, 251, 234, 0.98), rgba(222, 238, 208, 0.99))',
            'radial-gradient(circle at 50% 0%, rgba(185, 214, 160, 0.22), transparent 42%)',
        ].join(', '),
    };
}

function clampFitViewZoom(fitWidth: number, fitHeight: number): number {
    const raw = Math.min(fitWidth, fitHeight);
    if (!Number.isFinite(raw) || raw <= 0) {
        return MIN_ZOOM;
    }
    return Math.min(MAX_ZOOM, Math.max(FIT_VIEW_MIN_ZOOM, raw));
}

function getMinZoomSoContentReadable(): number {
    let maxItemPx = Math.max(MIN_BED_VISUAL_SIZE, MIN_OBJECT_VISUAL_SIZE);
    for (const bed of props.beds) {
        const s = bedCardSize(bed);
        maxItemPx = Math.max(maxItemPx, s.width, s.height);
    }
    for (const object of props.gardenObjects) {
        maxItemPx = Math.max(
            maxItemPx,
            getObjectPixelWidth(object),
            getObjectPixelHeight(object),
        );
    }
    const z = CONTENT_FIT_MIN_VISIBLE_ITEM_PX / maxItemPx;
    if (!Number.isFinite(z) || z <= 0) {
        return FIT_VIEW_MIN_ZOOM;
    }
    return Math.min(MAX_ZOOM, Math.max(FIT_VIEW_MIN_ZOOM, z));
}

function getPlannerContentBounds() {
    const items: { x: number; y: number; width: number; height: number }[] = [];

    props.beds.forEach((bed) => {
        const position = getBedPosition(bed);
        const size = bedCardSize(bed);
        items.push({ ...position, ...size });
    });

    props.gardenObjects.forEach((object) => {
        const position = getObjectPosition(object);
        items.push({
            ...position,
            width: getObjectPixelWidth(object),
            height: getObjectPixelHeight(object),
        });
    });

    if (!items.length) return null;

    return {
        minX: Math.min(...items.map((item) => item.x)),
        minY: Math.min(...items.map((item) => item.y)),
        maxX: Math.max(...items.map((item) => item.x + item.width)),
        maxY: Math.max(...items.map((item) => item.y + item.height)),
    };
}

function applyFitContentZoom() {
    if (!viewportSize.value.width || !viewportSize.value.height) return;

    const bounds = getPlannerContentBounds();
    if (!bounds) {
        applyFitGardenZoom();
        return;
    }

    const contentWidth = Math.max(
        1,
        bounds.maxX - bounds.minX + CONTENT_FIT_PADDING * 2,
    );
    const contentHeight = Math.max(
        1,
        bounds.maxY - bounds.minY + CONTENT_FIT_PADDING * 2,
    );
    const fitWidth = viewportSize.value.width / contentWidth;
    const fitHeight = viewportSize.value.height / contentHeight;
    const fitZoom = clampFitViewZoom(fitWidth, fitHeight);
    const readabilityZoom = getMinZoomSoContentReadable();
    const nextZoom = Math.min(MAX_ZOOM, Math.max(readabilityZoom, fitZoom));
    zoom.value = Number.isFinite(nextZoom) ? nextZoom : 1;
    panX.value = CONTENT_FIT_SIDE_PADDING - bounds.minX * zoom.value;
    panY.value = CONTENT_FIT_SIDE_PADDING - bounds.minY * zoom.value;
}

function applyFitGardenZoom() {
    if (!viewportSize.value.width || !viewportSize.value.height) return;

    const fitWidth = viewportSize.value.width / gardenSurfaceWidth.value;
    const fitHeight = viewportSize.value.height / gardenSurfaceHeight.value;
    const nextZoom = clampFitViewZoom(fitWidth, fitHeight);
    zoom.value = Number.isFinite(nextZoom) ? nextZoom : 1;
    panX.value = 0;
    panY.value = 0;
}

function applyInitialGardenZoom() {
    if (!viewportSize.value.width || !viewportSize.value.height) return;

    const fitWidth = viewportSize.value.width / gardenSurfaceWidth.value;
    const fitHeight = viewportSize.value.height / gardenSurfaceHeight.value;
    const fullGardenZoom = clampFitViewZoom(fitWidth, fitHeight);
    const minUsefulZoom = Math.max(
        (viewportSize.value.width * INITIAL_GARDEN_MIN_VIEWPORT_WIDTH) /
            gardenSurfaceWidth.value,
        (viewportSize.value.height * INITIAL_GARDEN_MIN_VIEWPORT_HEIGHT) /
            gardenSurfaceHeight.value,
    );
    const nextZoom = Math.min(
        MAX_ZOOM,
        Math.max(fullGardenZoom, minUsefulZoom),
    );

    zoom.value = Number.isFinite(nextZoom) ? nextZoom : 1;
    panX.value = 0;
    panY.value = 0;
}

function centerBedInViewport(bed: Bed) {
    if (!viewportSize.value.width || !viewportSize.value.height) return;

    zoom.value = Math.min(MAX_ZOOM, Math.max(zoom.value, FOCUSED_BED_MIN_ZOOM));
    const position = getBedPosition(bed);
    const size = bedCardSize(bed);
    const bedCenterX = position.x + size.width / 2;
    const bedCenterY = position.y + size.height / 2;

    panX.value = viewportSize.value.width / 2 - bedCenterX * zoom.value;
    panY.value = viewportSize.value.height / 2 - bedCenterY * zoom.value;
}

function runPlannerInitialViewportFit() {
    if (plannerInitialViewportFitDone.value) return;
    const { width, height } = viewportSize.value;
    if (!width || !height) return;
    if (props.beds.length === 0 && props.gardenObjects.length === 0) {
        return;
    }

    applyInitialGardenZoom();
    plannerInitialViewportFitDone.value = true;
    const focusedBed = selectedBed.value;
    if (focusedBed) {
        centerBedInViewport(focusedBed);
    }

    nextTick(() => {
        nextTick(() => {
            if (
                !plannerInitialViewportFitDone.value ||
                !viewportSize.value.width ||
                !viewportSize.value.height ||
                !plannerViewport.value
            ) {
                return;
            }
            const currentFocusedBed = selectedBed.value;
            if (currentFocusedBed) {
                centerBedInViewport(currentFocusedBed);
                return;
            }
            applyInitialGardenZoom();
        });
    });
}

watch(
    () =>
        [
            props.gardenPlan.id,
            props.beds.length,
            props.gardenObjects.length,
        ] as const,
    () => {
        nextTick(() => {
            const focusedBedId = getInitialFocusedBedId();
            if (focusedBedId !== null) {
                selectedObjectId.value = null;
                selectedBedId.value = focusedBedId;
                isLayoutEditing.value = true;
                const focusedBed = props.beds.find(
                    (bed) => bed.id === focusedBedId,
                );
                if (focusedBed) {
                    centerBedInViewport(focusedBed);
                }
            }
            runPlannerInitialViewportFit();
        });
    },
);

function changeZoom(delta: number) {
    zoom.value = Math.min(
        MAX_ZOOM,
        Math.max(MIN_ZOOM, Number((zoom.value + delta).toFixed(2))),
    );
}

function onPlannerWheel(event: WheelEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-ui-overlay="true"]')) return;
    event.preventDefault();
    const delta = event.deltaY > 0 ? -0.12 : 0.12;
    const nextZoom = Math.min(
        MAX_ZOOM,
        Math.max(MIN_ZOOM, Number((zoom.value + delta).toFixed(2))),
    );

    if (nextZoom === zoom.value) return;

    zoom.value = nextZoom;
    panX.value = 0;
    panY.value = 0;
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
        <div class="page page-with-bottomnav flex min-h-0 flex-col">
            <div
                class="font-display flex min-h-0 flex-1 flex-col bg-background text-foreground antialiased"
            >
                <div
                    class="border-beige/50 relative mx-auto flex min-h-0 w-full max-w-[480px] flex-1 flex-col overflow-x-hidden border-x bg-background shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none"
                >
                    <DiaryHeader
                        title="Aiaplaan"
                        header-class="pt-3 md:pt-6"
                        top-row-class="mb-2 md:mb-3"
                        bottom-row-class="mb-2 md:mb-4"
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

                    <main
                        class="flex min-h-0 flex-1 flex-col px-4 pt-1 pb-3 sm:px-6 sm:pt-2 md:px-8 md:py-3"
                    >
                        <div
                            class="flex min-h-0 flex-1 flex-col space-y-3 sm:space-y-4 md:space-y-6"
                        >
                            <section
                                class="flex min-h-0 flex-1 flex-col space-y-3 sm:space-y-4 md:space-y-6"
                            >
                                <div
                                    v-if="plannerFeedback"
                                    class="rounded-[1.5rem] border px-4 py-3 shadow-sm"
                                    :class="[
                                        plannerFeedback.tone === 'error'
                                            ? 'border-rose-200 bg-rose-50 text-rose-700'
                                            : 'border-amber-200 bg-amber-50 text-amber-800',
                                        isBedAddedDragHintFlash
                                            ? 'hidden md:block'
                                            : '',
                                    ]"
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
                                            <p
                                                class="text-sm font-semibold text-foreground"
                                            >
                                                Aiaplaan töötab paremini laiemas
                                                vaates
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-6 text-muted-foreground"
                                            >
                                                Lohistamine ja paigutus on
                                                mugavam, kui kasutad
                                                horisontaalrežiimi või avad akna
                                                laiemaks.
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
                                    class="flex min-h-0 min-w-0 flex-1 flex-col"
                                >
                                    <div
                                        class="mb-2 shrink-0 border-b border-border/70 pb-2 md:mb-4 md:pb-4"
                                    >
                                        <div
                                            class="flex flex-wrap items-end justify-between gap-3"
                                        >
                                            <div
                                                class="min-w-0 flex-1 md:flex-initial"
                                            >
                                                <label
                                                    class="sr-only"
                                                    for="garden-plan-select"
                                                >
                                                    Vali aiaplaan. Peenraid
                                                    loendis näidatakse vastavalt
                                                    vahekaardile (Kõik või
                                                    Lemmikud) ja otsingule.
                                                </label>
                                                <div
                                                    class="flex min-w-0 flex-wrap items-end gap-x-2.5 gap-y-1.5 md:block"
                                                >
                                                    <div
                                                        class="relative inline-block w-max max-w-full min-w-0 md:block md:w-full"
                                                    >
                                                        <select
                                                            id="garden-plan-select"
                                                            class="block w-max max-w-full min-w-0 appearance-none truncate bg-transparent pr-8 text-2xl leading-tight font-semibold text-foreground transition outline-none hover:text-primary focus:text-primary max-md:[field-sizing:content] md:block md:w-full [&_option]:text-sm [&_option]:font-medium"
                                                            :value="
                                                                gardenPlan.id
                                                            "
                                                            @change="
                                                                onGardenPlanSelect
                                                            "
                                                        >
                                                            <option
                                                                v-for="p in gardenPlans"
                                                                :key="p.id"
                                                                :value="p.id"
                                                            >
                                                                {{ p.name }}
                                                            </option>
                                                        </select>
                                                        <span
                                                            class="material-symbols-outlined pointer-events-none absolute top-1/2 right-0 -translate-y-1/2 text-xl text-muted-foreground"
                                                            >expand_more</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex shrink-0 items-center md:hidden"
                                                    >
                                                        <span
                                                            class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-primary/20 bg-gradient-to-br from-primary/14 via-primary/8 to-transparent px-2.5 py-1 shadow-sm ring-1 ring-primary/10 dark:border-primary/25 dark:from-primary/18 dark:via-primary/10 dark:ring-primary/15"
                                                            :aria-label="`${mobileListBeds.length} ${mobileBedCountSuffix}`"
                                                        >
                                                            <span
                                                                class="material-symbols-outlined shrink-0 text-[18px] leading-none text-primary"
                                                                aria-hidden="true"
                                                                >grid_view</span
                                                            >
                                                            <span
                                                                class="text-lg leading-none font-bold text-foreground tabular-nums"
                                                                >{{
                                                                    mobileListBeds.length
                                                                }}</span
                                                            >
                                                            <span
                                                                class="max-w-[7rem] truncate text-[11px] leading-tight font-semibold text-muted-foreground"
                                                                >{{
                                                                    mobileBedCountSuffix
                                                                }}</span
                                                            >
                                                        </span>
                                                    </div>
                                                </div>
                                                <p
                                                    v-if="searchQuery.trim()"
                                                    class="mt-1.5 text-xs leading-snug font-normal text-muted-foreground/85 md:hidden"
                                                >
                                                    Otsingule vastavalt
                                                </p>
                                                <div
                                                    class="mt-2 hidden flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-muted-foreground md:flex"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-h-8 items-center gap-0.5 rounded-full transition hover:text-primary"
                                                        :class="
                                                            plannerListView ===
                                                            'beds'
                                                                ? 'text-primary'
                                                                : ''
                                                        "
                                                        @click="
                                                            togglePlannerListView(
                                                                'beds',
                                                            )
                                                        "
                                                    >
                                                        {{ plannerBeds.length }}
                                                        peenart
                                                        <span
                                                            class="material-symbols-outlined text-sm transition-transform"
                                                            :class="
                                                                plannerListView ===
                                                                'beds'
                                                                    ? 'rotate-180'
                                                                    : ''
                                                            "
                                                            >expand_more</span
                                                        >
                                                    </button>
                                                    <span
                                                        class="h-1 w-1 rounded-full bg-muted-foreground/35"
                                                    ></span>
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-h-8 items-center gap-0.5 rounded-full transition hover:text-primary"
                                                        :class="
                                                            plannerListView ===
                                                            'objects'
                                                                ? 'text-primary'
                                                                : ''
                                                        "
                                                        @click="
                                                            togglePlannerListView(
                                                                'objects',
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            visibleObjectsCount
                                                        }}
                                                        objekti
                                                        <span
                                                            class="material-symbols-outlined text-sm transition-transform"
                                                            :class="
                                                                plannerListView ===
                                                                'objects'
                                                                    ? 'rotate-180'
                                                                    : ''
                                                            "
                                                            >expand_more</span
                                                        >
                                                    </button>
                                                    <span
                                                        class="h-1 w-1 rounded-full bg-muted-foreground/35"
                                                    ></span>
                                                    <span>
                                                        {{
                                                            gardenDimensionLabel
                                                        }}
                                                    </span>
                                                    <div
                                                        class="ml-auto flex shrink-0 items-center"
                                                    >
                                                        <SortDropdown
                                                            v-model="
                                                                selectedBedSort
                                                            "
                                                            :options="
                                                                bedSortOptions
                                                            "
                                                            compact
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="flex shrink-0 items-center gap-1"
                                            >
                                                <div
                                                    class="hidden items-center md:flex"
                                                >
                                                    <div
                                                        class="inline-flex items-center gap-0.5 rounded-full bg-muted/45 p-1 ring-1 ring-border/70"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-foreground transition hover:bg-background"
                                                            @click="
                                                                changeZoom(
                                                                    -ZOOM_STEP,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="material-symbols-outlined text-base"
                                                                >remove</span
                                                            >
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex h-8 min-w-11 items-center justify-center rounded-full bg-background px-2 text-xs font-semibold text-foreground shadow-xs"
                                                            @click="
                                                                applyFitContentZoom
                                                            "
                                                        >
                                                            {{ zoomPercent }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-foreground transition hover:bg-background"
                                                            @click="
                                                                changeZoom(
                                                                    ZOOM_STEP,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="material-symbols-outlined text-base"
                                                                >add</span
                                                            >
                                                        </button>
                                                    </div>
                                                </div>
                                                <div
                                                    class="hidden shrink-0 md:flex"
                                                >
                                                    <CardActionsMenu
                                                        placement="inline"
                                                        @edit="
                                                            openGardenPlanEditor
                                                        "
                                                        @delete="
                                                            deleteGardenPlan
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="plannerListView"
                                        class="mb-3 hidden rounded-2xl bg-background/60 p-2 ring-1 ring-border/70 md:block"
                                    >
                                        <div
                                            class="mb-1 flex items-center justify-between px-2 py-1"
                                        >
                                            <p
                                                class="text-xs font-semibold text-foreground"
                                            >
                                                {{
                                                    plannerListView === 'beds'
                                                        ? 'Peenrad kaardil'
                                                        : 'Objektid kaardil'
                                                }}
                                            </p>
                                            <button
                                                type="button"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                @click="plannerListView = null"
                                            >
                                                <span
                                                    class="material-symbols-outlined text-sm"
                                                    >close</span
                                                >
                                            </button>
                                        </div>

                                        <div
                                            v-if="plannerListView === 'beds'"
                                            class="max-h-56 space-y-1 overflow-y-auto pr-1"
                                        >
                                            <div
                                                v-for="bed in filteredBeds"
                                                :key="`bed-list-${bed.id}`"
                                                class="flex items-center gap-1 rounded-xl transition hover:bg-muted"
                                                :class="
                                                    selectedBed?.id === bed.id
                                                        ? 'bg-primary/8'
                                                        : ''
                                                "
                                            >
                                                <button
                                                    type="button"
                                                    class="min-w-0 flex-1 px-3 py-2 text-left"
                                                    @click="
                                                        focusBedDetails(bed.id)
                                                    "
                                                >
                                                    <span
                                                        class="block min-w-0 truncate text-sm font-semibold text-foreground"
                                                        >{{ bed.name }}</span
                                                    >
                                                    <span
                                                        class="mt-0.5 block truncate text-xs text-muted-foreground"
                                                        >{{
                                                            bedPlantSummaryLine(
                                                                bed,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        v-if="bed.location"
                                                        class="mt-0.5 block truncate text-xs text-muted-foreground"
                                                        >{{
                                                            bed.location
                                                        }}</span
                                                    >
                                                    <span
                                                        class="mt-0.5 block truncate text-xs text-muted-foreground"
                                                        >{{
                                                            bedDimensionsLabel(
                                                                bed,
                                                            )
                                                        }}</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="mr-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-background hover:text-foreground"
                                                    @click="openBedPage(bed.id)"
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >arrow_forward</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="mr-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-rose-50 hover:text-rose-700"
                                                    :aria-label="`Kustuta ${bed.name}`"
                                                    @click.stop="
                                                        deleteBed(
                                                            bed.id,
                                                            bed.name,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base"
                                                        >delete</span
                                                    >
                                                </button>
                                            </div>
                                            <p
                                                v-if="filteredBeds.length === 0"
                                                class="px-3 py-4 text-center text-sm text-muted-foreground"
                                            >
                                                Peenraid ei ole nähtaval.
                                            </p>
                                        </div>

                                        <div
                                            v-if="plannerListView === 'objects'"
                                            class="max-h-56 space-y-1 overflow-y-auto pr-1"
                                        >
                                            <button
                                                v-for="object in plannerObjects"
                                                :key="`object-list-${object.id}`"
                                                type="button"
                                                class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-muted"
                                                :class="
                                                    selectedObject?.id ===
                                                    object.id
                                                        ? 'bg-primary/8'
                                                        : ''
                                                "
                                                @click="
                                                    focusObjectDetails(
                                                        object.id,
                                                    )
                                                "
                                            >
                                                <span class="min-w-0">
                                                    <span
                                                        class="block truncate text-sm font-semibold text-foreground"
                                                        >{{ object.name }}</span
                                                    >
                                                    <span
                                                        class="mt-0.5 block truncate text-xs text-muted-foreground"
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
                                                    </span>
                                                </span>
                                                <span
                                                    class="material-symbols-outlined shrink-0 text-base text-muted-foreground"
                                                    >my_location</span
                                                >
                                            </button>
                                            <p
                                                v-if="
                                                    plannerObjects.length === 0
                                                "
                                                class="px-3 py-4 text-center text-sm text-muted-foreground"
                                            >
                                                Objekte ei ole nähtaval.
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="plannerControlsOpen"
                                        class="mb-4 space-y-3"
                                    >
                                        <div class="flex justify-end">
                                            <button
                                                type="button"
                                                class="text-xs font-semibold text-muted-foreground underline-offset-2 transition hover:text-foreground hover:underline"
                                                @click="
                                                    plannerControlsOpen = false
                                                "
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
                                                                >100 × 30
                                                                m</span
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
                                                        gardenForm.errors
                                                            .name ||
                                                        gardenFormDimensionErrors.width ||
                                                        gardenFormDimensionErrors.height
                                                    "
                                                    class="mt-3 space-y-1 rounded-2xl border border-red-200 bg-red-50/90 px-3 py-2 text-sm text-red-700"
                                                >
                                                    <p
                                                        v-if="
                                                            gardenForm.errors
                                                                .name
                                                        "
                                                    >
                                                        {{
                                                            gardenForm.errors
                                                                .name
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            gardenFormDimensionErrors.width
                                                        "
                                                    >
                                                        {{
                                                            gardenFormDimensionErrors.width
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            gardenFormDimensionErrors.height
                                                        "
                                                    >
                                                        {{
                                                            gardenFormDimensionErrors.height
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
                                        class="mb-3 flex min-h-0 flex-1 flex-col md:mb-3 md:block md:flex-none"
                                    >
                                        <div
                                            ref="mobileBedListPanelRef"
                                            class="flex min-h-0 flex-1 flex-col md:hidden"
                                        >
                                            <div
                                                class="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-[1.65rem] border border-border/65 bg-card shadow-[0_20px_44px_-18px_rgba(47,67,44,0.14)] dark:border-border/80 dark:shadow-[0_20px_40px_-16px_rgba(0,0,0,0.45)]"
                                            >
                                                <div
                                                    class="flex shrink-0 items-center justify-between gap-2 border-b border-border/60 px-3 py-2"
                                                >
                                                    <div
                                                        class="no-scrollbar flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto"
                                                    >
                                                        <button
                                                            type="button"
                                                            :class="
                                                                bedListTabClass(
                                                                    'all',
                                                                )
                                                            "
                                                            @click="
                                                                mobileBedListTab =
                                                                    'all'
                                                            "
                                                        >
                                                            Kõik
                                                        </button>
                                                        <button
                                                            type="button"
                                                            :class="
                                                                bedListTabClass(
                                                                    'favorites',
                                                                )
                                                            "
                                                            @click="
                                                                mobileBedListTab =
                                                                    'favorites'
                                                            "
                                                        >
                                                            Lemmikud
                                                        </button>
                                                    </div>
                                                    <div class="shrink-0">
                                                        <SortDropdown
                                                            v-model="
                                                                selectedBedSort
                                                            "
                                                            :options="
                                                                bedSortOptions
                                                            "
                                                            compact
                                                        />
                                                    </div>
                                                </div>

                                                <div
                                                    class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-y-contain px-2 pt-1.5 pb-3"
                                                >
                                                    <div
                                                        v-for="bed in mobileListBeds"
                                                        :key="`bed-list-mobile-${bed.id}`"
                                                        class="relative rounded-2xl border bg-card p-3 shadow-sm transition hover:shadow-md"
                                                        :class="
                                                            selectedBed?.id ===
                                                            bed.id
                                                                ? 'border-primary/40 ring-2 ring-primary/25 ring-offset-2 ring-offset-background'
                                                                : 'border-primary/10'
                                                        "
                                                    >
                                                        <div
                                                            class="flex items-center gap-3"
                                                        >
                                                            <button
                                                                type="button"
                                                                class="flex min-w-0 flex-1 items-center gap-3 text-left"
                                                                @click="
                                                                    openBedPage(
                                                                        bed.id,
                                                                    )
                                                                "
                                                            >
                                                                <div
                                                                    class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-primary/10 bg-muted/40"
                                                                >
                                                                    <img
                                                                        v-if="
                                                                            getBedPreviewImage(
                                                                                bed,
                                                                            )
                                                                        "
                                                                        :src="
                                                                            getBedPreviewImage(
                                                                                bed,
                                                                            )!
                                                                        "
                                                                        :alt="
                                                                            bed.name
                                                                        "
                                                                        class="h-full w-full object-cover"
                                                                    />
                                                                    <div
                                                                        v-else
                                                                        class="flex h-full w-full items-center justify-center text-primary/70"
                                                                    >
                                                                        <span
                                                                            class="material-symbols-outlined text-2xl"
                                                                            >yard</span
                                                                        >
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    class="min-w-0 flex-1"
                                                                >
                                                                    <div
                                                                        class="min-w-0 truncate text-base leading-snug font-bold text-text-main"
                                                                    >
                                                                        {{
                                                                            bed.name
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="text-text-muted mt-0.5 truncate text-sm"
                                                                    >
                                                                        {{
                                                                            bedPlantSummaryLine(
                                                                                bed,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        v-if="
                                                                            bed.location
                                                                        "
                                                                        class="text-text-muted mt-0.5 truncate text-sm"
                                                                    >
                                                                        {{
                                                                            bed.location
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="text-text-muted mt-0.5 truncate text-sm"
                                                                    >
                                                                        {{
                                                                            bedDimensionsLabel(
                                                                                bed,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </div>
                                                            </button>

                                                            <div
                                                                class="ml-1 flex shrink-0 items-center gap-1.5"
                                                                @click.stop
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-primary/10 bg-background transition hover:scale-105 hover:bg-primary/5"
                                                                    :class="
                                                                        bed.is_favorite
                                                                            ? 'text-rose-600 shadow-sm'
                                                                            : 'text-foreground/45'
                                                                    "
                                                                    @click.prevent.stop="
                                                                        toggleBedFavorite(
                                                                            bed.id,
                                                                        )
                                                                    "
                                                                    aria-label="Lisa lemmikuks"
                                                                    :title="
                                                                        bed.is_favorite
                                                                            ? 'Eemalda lemmikutest'
                                                                            : 'Lisa lemmikuks'
                                                                    "
                                                                >
                                                                    <span
                                                                        class="material-symbols-outlined text-[20px] leading-none transition"
                                                                        :style="
                                                                            bed.is_favorite
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

                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger
                                                                        as-child
                                                                    >
                                                                        <button
                                                                            type="button"
                                                                            class="text-text-muted flex h-9 w-9 items-center justify-center rounded-full border border-transparent transition hover:bg-primary/10"
                                                                            aria-label="Menüü"
                                                                        >
                                                                            <span
                                                                                class="material-symbols-outlined text-[22px]"
                                                                                >more_horiz</span
                                                                            >
                                                                        </button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent
                                                                        align="end"
                                                                        side="bottom"
                                                                        :side-offset="
                                                                            6
                                                                        "
                                                                        class="min-w-[10.75rem] rounded-xl border border-primary/10 p-1 shadow-lg"
                                                                    >
                                                                        <DropdownMenuItem
                                                                            class="cursor-pointer gap-2 py-2.5"
                                                                            @click="
                                                                                openBedEditPage(
                                                                                    bed.id,
                                                                                )
                                                                            "
                                                                        >
                                                                            <span
                                                                                class="material-symbols-outlined text-base text-primary"
                                                                                >edit</span
                                                                            >
                                                                            Muuda
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuItem
                                                                            variant="destructive"
                                                                            class="cursor-pointer gap-2 py-2.5"
                                                                            @click="
                                                                                deleteBed(
                                                                                    bed.id,
                                                                                    bed.name,
                                                                                )
                                                                            "
                                                                        >
                                                                            <span
                                                                                class="material-symbols-outlined text-base"
                                                                                >delete</span
                                                                            >
                                                                            Kustuta
                                                                        </DropdownMenuItem>
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        v-if="
                                                            mobileListBeds.length ===
                                                            0
                                                        "
                                                        class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border/70 bg-muted/20 px-4 py-10 text-center"
                                                    >
                                                        <template
                                                            v-if="
                                                                mobileBedListTab ===
                                                                    'favorites' &&
                                                                filteredBeds.length >
                                                                    0
                                                            "
                                                        >
                                                            <span
                                                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-background text-muted-foreground shadow-inner ring-1 ring-border/50"
                                                            >
                                                                <span
                                                                    class="material-symbols-outlined text-2xl text-rose-500/80"
                                                                    >favorite</span
                                                                >
                                                            </span>
                                                            <p
                                                                class="text-sm font-medium text-foreground"
                                                            >
                                                                Lemmikpeenraid
                                                                pole veel
                                                                valitud
                                                            </p>
                                                            <p
                                                                class="max-w-[16rem] text-xs leading-relaxed text-muted-foreground"
                                                            >
                                                                Puuduta südant
                                                                peenra juures,
                                                                et see kuvataks
                                                                siin.
                                                            </p>
                                                        </template>
                                                        <template v-else>
                                                            <span
                                                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-background text-muted-foreground shadow-inner ring-1 ring-border/50"
                                                            >
                                                                <span
                                                                    class="material-symbols-outlined text-2xl"
                                                                    >search_off</span
                                                                >
                                                            </span>
                                                            <p
                                                                class="text-sm font-medium text-foreground"
                                                            >
                                                                Siin pole praegu
                                                                midagi
                                                            </p>
                                                            <p
                                                                class="max-w-[16rem] text-xs leading-relaxed text-muted-foreground"
                                                            >
                                                                Proovi otsingut
                                                                muuta või lisa
                                                                uus peenar
                                                                plussnupust.
                                                            </p>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="grid hidden min-h-0 gap-3 md:grid"
                                        >
                                            <button
                                                type="button"
                                                class="hidden items-center justify-between rounded-xl border border-border/80 bg-white px-4 py-3 text-left shadow-sm dark:bg-card"
                                                @click="
                                                    toolDrawerOpen =
                                                        !toolDrawerOpen
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
                                                class="hidden rounded-xl border border-border/80 bg-white p-4 shadow-sm xl:sticky xl:top-6 xl:self-start dark:bg-card"
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
                                                            Vali allpool tüüp,
                                                            siis klõpsa
                                                            <span
                                                                class="font-medium text-foreground/85"
                                                                >rohelisel
                                                                plaanil</span
                                                            >
                                                            kohta. Uue peenra
                                                            jaoks kasuta paremal
                                                            all olevat + nuppu.
                                                        </p>
                                                    </div>
                                                    <button
                                                        v-if="activeTool"
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background text-foreground transition hover:bg-muted"
                                                        @click="
                                                            clearToolSelection
                                                        "
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
                                                            @click="
                                                                toolSearch = ''
                                                            "
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
                                                        <p
                                                            class="px-2 py-1 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                                                        >
                                                            {{ category.label }}
                                                        </p>
                                                        <div
                                                            class="space-y-1.5"
                                                        >
                                                            <button
                                                                v-for="variant in category.variants"
                                                                :key="
                                                                    variant.id
                                                                "
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
                                                                <span
                                                                    class="flex items-start gap-2"
                                                                >
                                                                    <component
                                                                        :is="
                                                                            iconFor(
                                                                                variant.icon,
                                                                            )
                                                                        "
                                                                        :size="
                                                                            18
                                                                        "
                                                                        :stroke-width="
                                                                            1.75
                                                                        "
                                                                        class="mt-0.5 shrink-0"
                                                                        :class="
                                                                            toolVariantIconToneClass(
                                                                                variant.type,
                                                                            )
                                                                        "
                                                                    />
                                                                    <span
                                                                        class="min-w-0"
                                                                    >
                                                                        <span
                                                                            class="block text-sm font-semibold text-foreground"
                                                                        >
                                                                            {{
                                                                                variant.label
                                                                            }}
                                                                        </span>
                                                                        <span
                                                                            class="mt-0.5 block text-xs text-muted-foreground"
                                                                        >
                                                                            {{
                                                                                variant.description
                                                                            }}
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
                                                        {{
                                                            selectedToolDisplayLabel
                                                        }}
                                                    </div>
                                                    <p
                                                        v-if="
                                                            selectedToolDisplayDescription
                                                        "
                                                        class="mt-1 text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            selectedToolDisplayDescription
                                                        }}
                                                    </p>
                                                    <div
                                                        v-if="
                                                            activeTool ===
                                                                'other' &&
                                                            activeToolVariant?.requiresCustomName
                                                        "
                                                        class="mt-3 space-y-1"
                                                    >
                                                        <label class="block">
                                                            <span
                                                                class="mb-1 block text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                                                >Objekti
                                                                nimi</span
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
                                                            v-if="
                                                                otherObjectNameError
                                                            "
                                                            class="text-xs text-rose-600"
                                                        >
                                                            {{
                                                                otherObjectNameError
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div
                                                        class="mt-1 leading-6 text-muted-foreground"
                                                        :class="
                                                            activeTool ===
                                                                'other' &&
                                                            activeToolVariant?.requiresCustomName
                                                                ? 'mt-3'
                                                                : ''
                                                        "
                                                    >
                                                        <template
                                                            v-if="
                                                                activeTool ===
                                                                    'other' &&
                                                                activeToolVariant?.requiresCustomName
                                                            "
                                                        >
                                                            Seejärel klõpsa aias
                                                            kohta, kuhu objekt
                                                            tuleb.
                                                        </template>
                                                        <template v-else>
                                                            Klõpsa nüüd aias
                                                            kohta, kuhu soovid
                                                            selle lisada.
                                                        </template>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="mt-3 inline-flex items-center gap-1 rounded-full border border-border bg-background px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                                                        @click="
                                                            clearToolSelection
                                                        "
                                                    >
                                                        Tühista valik
                                                    </button>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90 xl:hidden"
                                                    @click="
                                                        toolDrawerOpen = false
                                                    "
                                                >
                                                    Sulge tööriistad
                                                </button>
                                            </aside>

                                            <div class="relative min-w-0">
                                                <aside
                                                    v-if="isLayoutEditing"
                                                    data-ui-overlay="true"
                                                    class="pointer-events-auto absolute top-3 left-3 z-30 flex flex-col items-center gap-1.5 rounded-xl border border-white/70 bg-white/82 px-1.5 py-1.5 shadow-lg shadow-emerald-950/10 backdrop-blur-xl dark:border-emerald-200/10 dark:bg-card/82"
                                                >
                                                    <button
                                                        v-for="category in toolCategories"
                                                        :key="`quick-tool-${category.id}`"
                                                        type="button"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border transition"
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
                                                            :is="
                                                                iconFor(
                                                                    category.icon,
                                                                )
                                                            "
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
                                                        @click.stop="
                                                            clearToolSelection
                                                        "
                                                    >
                                                        <span
                                                            class="material-symbols-outlined text-sm"
                                                            >close</span
                                                        >
                                                    </button>
                                                </aside>

                                                <div
                                                    v-if="
                                                        isLayoutEditing &&
                                                        selectedQuickCategoryId
                                                    "
                                                    data-ui-overlay="true"
                                                    class="pointer-events-auto absolute top-3 left-[64px] z-30 w-[min(17rem,calc(100%-4.75rem))] rounded-xl border border-white/70 bg-white/94 p-3 shadow-lg shadow-emerald-950/10 backdrop-blur-xl dark:border-emerald-200/10 dark:bg-card/94"
                                                >
                                                    <div
                                                        class="mb-2 flex items-center justify-between"
                                                    >
                                                        <p
                                                            class="text-sm font-semibold text-foreground"
                                                        >
                                                            {{
                                                                toolCategories.find(
                                                                    (item) =>
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
                                                                selectedQuickCategoryId =
                                                                    null
                                                            "
                                                        >
                                                            <span
                                                                class="material-symbols-outlined text-sm"
                                                                >close</span
                                                            >
                                                        </button>
                                                    </div>
                                                    <div
                                                        class="max-h-[min(68vh,26rem)] space-y-1.5 overflow-y-auto pr-1"
                                                    >
                                                        <button
                                                            v-for="variant in toolCategories.find(
                                                                (item) =>
                                                                    item.id ===
                                                                    selectedQuickCategoryId,
                                                            )?.variants ?? []"
                                                            :key="variant.id"
                                                            type="button"
                                                            class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-left transition hover:border-primary/20 hover:bg-primary/6"
                                                            @click.stop="
                                                                chooseToolVariant(
                                                                    variant,
                                                                )
                                                            "
                                                        >
                                                            <div
                                                                class="flex items-start gap-2"
                                                            >
                                                                <component
                                                                    :is="
                                                                        iconFor(
                                                                            variant.icon,
                                                                        )
                                                                    "
                                                                    :size="18"
                                                                    :stroke-width="
                                                                        1.75
                                                                    "
                                                                    class="mt-0.5 shrink-0"
                                                                    :class="
                                                                        toolVariantIconToneClass(
                                                                            variant.type,
                                                                        )
                                                                    "
                                                                />
                                                                <span
                                                                    class="min-w-0"
                                                                >
                                                                    <span
                                                                        class="block text-sm font-semibold text-foreground"
                                                                    >
                                                                        {{
                                                                            variant.label
                                                                        }}
                                                                    </span>
                                                                    <span
                                                                        class="mt-0.5 block text-xs text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            variant.description
                                                                        }}
                                                                    </span>
                                                                </span>
                                                            </div>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div
                                                    ref="plannerViewport"
                                                    class="relative h-[min(62vh,620px)] w-full max-w-full min-w-0 cursor-default touch-none overflow-hidden rounded-[1.5rem] border bg-[linear-gradient(180deg,rgba(251,248,241,0.98),rgba(241,247,235,0.98))] p-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.8),0_14px_34px_rgba(47,67,44,0.10)] transition sm:p-3 md:h-[min(72vh,920px)] dark:bg-[linear-gradient(180deg,rgba(30,38,32,0.98),rgba(22,29,24,0.98))]"
                                                    :class="
                                                        isLayoutEditing
                                                            ? 'border-primary/25 ring-2 ring-primary/10'
                                                            : 'border-border/80'
                                                    "
                                                    @pointerdown="
                                                        handleViewportPointerDown(
                                                            $event,
                                                        )
                                                    "
                                                    @wheel.prevent="
                                                        onPlannerWheel($event)
                                                    "
                                                >
                                                    <div
                                                        class="relative overflow-hidden rounded-[1.45rem] border border-emerald-900/10 bg-emerald-50/80 dark:border-emerald-200/15 dark:bg-emerald-950/35"
                                                        :style="
                                                            plannerSurfaceStyle()
                                                        "
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
                                                                >{{
                                                                    isLayoutEditing
                                                                        ? 'Lohista peenraid'
                                                                        : 'Vaatamise režiim'
                                                                }}</span
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
                                                            class="group absolute touch-none transition-transform duration-150"
                                                            :class="[
                                                                draggingBedId ===
                                                                bed.id
                                                                    ? 'z-30 scale-[1.02]'
                                                                    : 'z-10 hover:z-20',
                                                                isLayoutEditing
                                                                    ? 'cursor-grab hover:-translate-y-1 active:cursor-grabbing'
                                                                    : 'cursor-pointer',
                                                            ]"
                                                            :style="
                                                                plannerBedStyle(
                                                                    bed,
                                                                )
                                                            "
                                                            data-bed-shape="true"
                                                            @pointerdown="
                                                                startDragging(
                                                                    bed,
                                                                    $event,
                                                                )
                                                            "
                                                            @mouseenter="
                                                                showBedInfo(
                                                                    bed.id,
                                                                )
                                                            "
                                                            @mouseleave="
                                                                hideBedInfo(
                                                                    bed.id,
                                                                )
                                                            "
                                                            @click="
                                                                openBedPage(
                                                                    bed.id,
                                                                )
                                                            "
                                                        >
                                                            <div
                                                                class="relative z-10 h-full w-full"
                                                            >
                                                                <div
                                                                    class="bed-grid-frame bed-grid-frame--map relative z-10 grid h-full w-full place-content-center gap-0.5 overflow-hidden rounded-[0.9rem] border border-emerald-900/10 p-0.5 transition"
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
                                                                            rowData,
                                                                            r
                                                                        ) in getBedVisibleLayout(
                                                                            bed,
                                                                        )"
                                                                        :key="`plan-row-${bed.id}-${r}`"
                                                                    >
                                                                        <span
                                                                            v-for="(
                                                                                _,
                                                                                c
                                                                            ) in rowData"
                                                                            :key="`plan-cell-${bed.id}-${r}-${c}`"
                                                                            class="block"
                                                                            :class="
                                                                                mapPlannerBedCellClass(
                                                                                    rowData[
                                                                                        c
                                                                                    ],
                                                                                    bed,
                                                                                    r,
                                                                                    c,
                                                                                )
                                                                            "
                                                                            :style="
                                                                                rowData[
                                                                                    c
                                                                                ] ===
                                                                                    1 &&
                                                                                getPlantAtVisibleCell(
                                                                                    bed,
                                                                                    r,
                                                                                    c,
                                                                                )
                                                                                    ?.image_url
                                                                                    ? {
                                                                                          backgroundImage: `linear-gradient(180deg,rgba(32,44,30,0.18),rgba(32,44,30,0.32)), url('${getPlantAtVisibleCell(
                                                                                              bed,
                                                                                              r,
                                                                                              c,
                                                                                          )!
                                                                                              .image_url!}')`,
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
                                                                        hoveredBedId ===
                                                                        bed.id
                                                                    "
                                                                    class="absolute top-full left-1/2 z-30 mt-3 hidden w-48 -translate-x-1/2 rounded-xl border border-border/70 bg-card/95 p-2.5 text-left shadow-lg backdrop-blur-sm md:block"
                                                                >
                                                                    <div
                                                                        class="flex items-center gap-2.5"
                                                                    >
                                                                        <div
                                                                            class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/70 bg-muted/50"
                                                                        >
                                                                            <img
                                                                                v-if="
                                                                                    getBedPreviewImage(
                                                                                        bed,
                                                                                    )
                                                                                "
                                                                                :src="
                                                                                    getBedPreviewImage(
                                                                                        bed,
                                                                                    )!
                                                                                "
                                                                                :alt="
                                                                                    bed.name
                                                                                "
                                                                                class="h-full w-full object-cover"
                                                                            />
                                                                            <span
                                                                                v-else
                                                                                class="material-symbols-outlined text-lg text-muted-foreground"
                                                                                >yard</span
                                                                            >
                                                                        </div>
                                                                        <p
                                                                            class="min-w-0 truncate text-sm font-semibold text-foreground"
                                                                        >
                                                                            {{
                                                                                bed.name
                                                                            }}
                                                                        </p>
                                                                    </div>
                                                                    <button
                                                                        type="button"
                                                                        class="mt-2.5 inline-flex w-full items-center justify-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
                                                                        @click.stop="
                                                                            openBedPage(
                                                                                bed.id,
                                                                            )
                                                                        "
                                                                    >
                                                                        Ava
                                                                        vaade
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </article>

                                                        <article
                                                            v-for="object in plannerObjects"
                                                            :key="`object-${object.id}`"
                                                            class="group absolute touch-none transition-transform duration-150"
                                                            :class="[
                                                                draggingObjectId ===
                                                                object.id
                                                                    ? 'z-30 scale-[1.02]'
                                                                    : 'z-10 hover:z-20',
                                                                isLayoutEditing
                                                                    ? 'cursor-grab hover:-translate-y-1 active:cursor-grabbing'
                                                                    : 'cursor-pointer',
                                                            ]"
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
                                                                        :is="
                                                                            iconFor(
                                                                                objectVariantIcon(
                                                                                    object,
                                                                                ),
                                                                            )
                                                                        "
                                                                        :size="
                                                                            objectIconSize(
                                                                                object,
                                                                            )
                                                                        "
                                                                        :stroke-width="
                                                                            1.5
                                                                        "
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
                                                                            hoveredObjectId ===
                                                                                object.id &&
                                                                            selectedObject?.id !==
                                                                                object.id
                                                                        "
                                                                        class="z-30 hidden w-40 max-w-[min(12rem,calc(100vw-2rem))] rounded-xl border border-border/70 bg-card/95 p-2.5 text-left shadow-lg backdrop-blur-sm md:block"
                                                                    >
                                                                        <p
                                                                            class="truncate text-sm font-semibold text-foreground"
                                                                        >
                                                                            {{
                                                                                object.name
                                                                            }}
                                                                        </p>
                                                                        <button
                                                                            type="button"
                                                                            class="mt-2.5 inline-flex w-full items-center justify-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
                                                                            @click.stop="
                                                                                focusObjectDetails(
                                                                                    object.id,
                                                                                )
                                                                            "
                                                                        >
                                                                            Muuda
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </article>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="selectedObject"
                                        ref="selectedObjectPanel"
                                        class="xl:backdrop-blur-0 sticky bottom-20 z-20 mt-3 rounded-[1.25rem] border border-border/80 bg-card/95 p-3 shadow-lg backdrop-blur xl:static xl:bottom-auto xl:z-auto xl:p-4 xl:shadow-sm"
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
                                                    class="mt-1 text-xs leading-snug font-semibold break-words text-foreground"
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
                                                    class="mt-1 text-xs leading-snug font-semibold text-foreground"
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
                                                    class="mt-1 text-xs font-semibold text-foreground tabular-nums"
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
                                                        objectFormDimensionErrors.width ||
                                                        objectFormDimensionErrors.height ||
                                                        objectForm.errors.name
                                                    "
                                                    class="text-xs text-rose-600"
                                                >
                                                    {{
                                                        objectForm.errors
                                                            .name ||
                                                        objectFormDimensionErrors.width ||
                                                        objectFormDimensionErrors.height
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

                <div
                    v-if="createMenuOpen"
                    class="fixed right-6 bottom-[176px] z-40 w-44 rounded-2xl border border-border/80 bg-card/96 p-2 shadow-xl shadow-emerald-950/10 backdrop-blur"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-semibold text-foreground transition hover:bg-muted"
                        @click="openCreateBed"
                    >
                        <span
                            class="material-symbols-outlined text-base text-primary"
                            >grid_view</span
                        >
                        Uus peenar
                    </button>
                    <button
                        type="button"
                        class="hidden w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-semibold text-foreground transition hover:bg-muted md:flex"
                        @click="openCreateGardenPlanModal"
                    >
                        <span
                            class="material-symbols-outlined text-base text-primary"
                            >map</span
                        >
                        Uus aed
                    </button>
                </div>

                <FloatingPlusButton
                    aria-label="Lisa"
                    :size-px="52"
                    :icon-size-px="30"
                    :bottom-px="112"
                    @click="onFloatingPlusClick"
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

        <div
            v-if="createGardenPlanModalOpen"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/45 px-4"
            @click.self="closeCreateGardenPlanModal"
        >
            <form
                class="w-full max-w-md rounded-3xl border border-border/70 bg-background p-5 shadow-2xl"
                @submit.prevent="submitCreateGardenPlan"
            >
                <div class="mb-4">
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Uus aiaplaan
                    </p>
                    <h3 class="mt-1 text-lg font-semibold text-foreground">
                        Lisa uue aia nimi
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tühja nime korral kasutatakse vaikimisi nime "Minu aed".
                    </p>
                </div>

                <label class="block">
                    <span class="sr-only">Uue aia nimi</span>
                    <input
                        ref="createGardenPlanNameInput"
                        v-model="createGardenPlanForm.name"
                        type="text"
                        class="w-full rounded-2xl border border-border bg-card px-4 py-3 text-sm text-foreground ring-primary/25 outline-none placeholder:text-muted-foreground focus:ring-2"
                        placeholder="Minu aed"
                        maxlength="120"
                        @keydown.esc.prevent="closeCreateGardenPlanModal"
                    />
                </label>

                <p
                    v-if="createGardenPlanForm.errors.name"
                    class="mt-2 text-sm text-red-600"
                >
                    {{ createGardenPlanForm.errors.name }}
                </p>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition hover:bg-muted"
                        :disabled="createGardenPlanForm.processing"
                        @click="closeCreateGardenPlanModal"
                    >
                        Tühista
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="createGardenPlanForm.processing"
                    >
                        {{
                            createGardenPlanForm.processing
                                ? 'Loon aiaplaani...'
                                : 'Loo aiaplaan'
                        }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="confirmDialogOpen"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/45 px-4"
            @click.self="closeConfirmDialog"
        >
            <form
                class="w-full max-w-md rounded-3xl border border-border/70 bg-background p-5 shadow-2xl"
                @submit.prevent="runConfirmDialogAction"
            >
                <div class="mb-4">
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Kinnitus
                    </p>
                    <h3 class="mt-1 text-lg font-semibold text-foreground">
                        {{ confirmDialogTitle }}
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ confirmDialogMessage }}
                    </p>
                </div>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition hover:bg-muted"
                        @click="closeConfirmDialog"
                    >
                        Tühista
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
                    >
                        {{ confirmDialogConfirmLabel }}
                    </button>
                </div>
            </form>
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
