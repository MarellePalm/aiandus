'
<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DesktopSearchField from '@/components/DesktopSearchField.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import GardenMapBackground from '@/components/GardenMapBackground.vue';
import GardenShapeEditor from '@/components/GardenShapeEditor.vue';
import MapConfirmDialog from '@/components/map/MapConfirmDialog.vue';
import MapGardenSetupSection from '@/components/map/MapGardenSetupSection.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dimensionsFromAddressBoundingBox } from '@/composables/useGardenParcelDimensions';
import { useGeolocation } from '@/composables/useGeolocation';
import AppLayout from '@/layouts/AppLayout.vue';
import { ortophotoFocusSpanMeters } from '@/lib/gardenAreaSelection';
import BottomNav from '@/pages/BottomNav.vue';
import {
    bedBoundsCmFromBricks,
    bedFootprintRectsPx,
    repairLegacySubgridBrick,
    resolveBedBricksCm,
    type BedCellBrick,
} from '@/pages/map/bedBrickFootprint';
import {
    getInitialFocusedBedId,
    sortBedsForPlanner,
} from '@/pages/map/bedPlannerUtils';
import {
    CM_TO_PX,
    DEFAULT_BED_CELL_SIZE_CM,
    DEFAULT_CREATE_GARDEN_HEIGHT_M,
    DEFAULT_CREATE_GARDEN_WIDTH_M,
    FIT_VIEW_MIN_ZOOM,
    FOCUSED_BED_MIN_ZOOM,
    GARDEN_GRID_CELL_CM,
    GARDEN_GRID_EDGE_CLASS,
    GARDEN_GRID_MAJOR_LINE,
    GARDEN_GRID_MINOR_LINE,
    GARDEN_SHAPE_CELL_CM,
    MAX_ZOOM,
    MIN_BED_VISUAL_SIZE,
    MIN_PINCH_DISTANCE_PX,
    MIN_ZOOM,
    ORTOPHOTO_MAX_ZOOM_FACTOR,
    ORTOPHOTO_MIN_ZOOM_FACTOR,
    PAN_CLICK_SUPPRESS_PX,
    PLANNER_FIT_VIEW_PADDING_PX,
    PLANNER_LANDSCAPE_HINT_KEY,
} from '@/pages/map/constants';
import {
    coordinateInputValue,
    optionalDimensionInputValue,
    parseCoordinateInput,
    parseOptionalDimensionInput,
    roundGardenCoordinate,
} from '@/pages/map/coordinateInput';
import {
    fetchGardenAddressResults,
    gardenSetupChoiceClass,
} from '@/pages/map/gardenAddressSearch';
import {
    gardenShapeMaskCmFromForm,
    gardenShapeMaskCols,
    gardenShapeMaskRows,
    getShapeMaskActiveCellBounds,
    inferShapeMaskCellCm,
    resizeGardenShapeMask,
    shapeMaskHasInactiveCells,
} from '@/pages/map/gardenShapeMask';
import { computeGardenSurfacePx } from '@/pages/map/gardenSurface';
import type {
    Bed,
    BedListTabKey,
    DimensionFormErrors,
    GardenAddressSearchResult,
    GardenSetupMode,
    MapViewProps,
    ViewportPinchState,
} from '@/pages/map/types';
import SearchModal from '@/pages/Seeds/SearchModal.vue';
import type { AppPageProps } from '@/types';

const props = defineProps<MapViewProps>();

type MapPageProps = AppPageProps<{
    flash?: {
        success?: string | null;
        error?: string | null;
        created_bed_id?: number | null;
    };
    errors?: Record<string, string>;
}>;

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

const viewportPointerMap = new Map<number, { x: number; y: number }>();

const localPositions = ref<Record<number, { x: number; y: number }>>({});
const draggingBedId = ref<number | null>(null);
const dragMoved = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const dragPointerId = ref<number | null>(null);
const dragCaptureTarget = ref<HTMLElement | null>(null);
const activeBedCardId = ref<number | null>(null);
const panPointerId = ref<number | null>(null);
const pinchState = ref<ViewportPinchState | null>(null);
const hoveredBedId = ref<number | null>(null);
const initialFocusedBedId = getInitialFocusedBedId(props.beds);
const selectedBedId = ref<number | null>(initialFocusedBedId);
const isLayoutEditing = ref(initialFocusedBedId !== null);
const plannerBedListOpen = ref(false);
const createMenuOpen = ref(false);
const mobileBedListPanelRef = ref<HTMLElement | null>(null);
const mobileBedListPanelInView = ref(false);
const plannerControlsOpen = ref(false);
const createGardenPlanModalOpen = ref(false);
const confirmDialogOpen = ref(false);
const createGardenPlanNameInput = ref<HTMLInputElement | null>(null);
const plannerLeafletZoom = ref<number | null>(null);

function onPlannerLeafletZoomChange(zoom: number) {
    plannerLeafletZoom.value = zoom;
}

/** Ortofoto nähtav kui aial on koordinaadid; üle zoom 18 skaleerib Leaflet viimast kachi. */
const showMapBackground = computed(
    () =>
        hasGardenCoordinates.value &&
        viewportSize.value.width > 0 &&
        viewportSize.value.height > 0,
);
const confirmDialogTitle = ref('');
const confirmDialogMessage = ref('');
const confirmDialogConfirmLabel = ref('Kinnita');
let confirmDialogAction: (() => void) | null = null;
const showBedsLayer = ref(true);
const plannerViewport = ref<HTMLElement | null>(null);
const viewportSize = ref({ width: 0, height: 0 });
/** First fit after mount/plan change; retries when ResizeObserver gets non-zero size. */
const plannerInitialViewportFitDone = ref(false);
/** After manual pan/zoom, skip auto-fit on resize until user taps "fit". */
const userAdjustedViewport = ref(false);
const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const panGestureMoved = ref(false);
const suppressPlannerSurfaceClick = ref(false);
const panStart = ref({ x: 0, y: 0, originX: 0, originY: 0 });
let resizeObserver: ResizeObserver | null = null;
let mobileBedListIntersectionObserver: IntersectionObserver | null = null;
const plannerLandscapeHintDismissed = ref(false);

const shouldShowLandscapeHint = computed(
    () =>
        !plannerLandscapeHintDismissed.value &&
        (viewportSize.value.width < 860 || viewportSize.value.height < 560),
);

const addressSearchQuery = ref('');
const addressSearchResults = ref<GardenAddressSearchResult[]>([]);
const addressSearchLoading = ref(false);
const addressSearchError = ref(false);
const addressSearchOpen = ref(false);
const selectedGardenAddressLabel = ref<string | null>(null);
const geolocationRequested = ref(false);
const geolocationFailed = ref(false);
const gardenDimensionsLoading = ref(false);
const gardenDimensionsMessage = ref<string | null>(null);

const createAddressSearchQuery = ref('');
const createAddressSearchResults = ref<GardenAddressSearchResult[]>([]);
const createAddressSearchLoading = ref(false);
const createAddressSearchError = ref(false);
const createAddressSearchOpen = ref(false);
const createSelectedGardenAddressLabel = ref<string | null>(null);
const createGeolocationRequested = ref(false);
const createGeolocationFailed = ref(false);
const createGardenDimensionsLoading = ref(false);
const createGardenDimensionsMessage = ref<string | null>(null);
const createGardenShapeMask = ref<number[][] | null>(null);
const createSetupMode = ref<'ortophoto' | 'manual'>('ortophoto');
const createManualShapeMask = ref<number[][]>([]);
const createManualCellSizeCm = ref(50);
const gardenSetupMode = ref<GardenSetupMode | null>(null);
const gardenManualShapeMask = ref<number[][]>([]);
const gardenManualCellSizeCm = ref(50);
const gardenPlannerShapeCellCm = ref(planShapeMaskCellCm(props.gardenPlan));
const skipShapeMaskResizeFromDimensions = ref(false);

function planShapeMaskCellCm(plan: GardenPlan): number {
    const stored = plan.shape_mask_cell_cm;
    const isSmallGarden =
        plan.width <= 2000 &&
        plan.height <= 2000 &&
        !(
            plan.center_lat != null &&
            plan.center_lng != null &&
            Number.isFinite(Number(plan.center_lat)) &&
            Number.isFinite(Number(plan.center_lng))
        );

    if (stored != null && stored >= 10 && (stored < 1000 || !isSmallGarden)) {
        return stored;
    }

    return inferShapeMaskCellCm(
        plan.width,
        plan.height,
        plan.shape_mask,
        stored && stored >= 10 ? stored : GARDEN_SHAPE_CELL_CM,
    );
}

const {
    coords: geolocationCoords,
    loading: geolocationLoading,
    error: geolocationError,
    requestPosition: requestGeolocationPosition,
} = useGeolocation();

const runGardenAddressSearch = useDebounceFn(async (query: string) => {
    const trimmed = query.trim();

    if (trimmed.length < 2) {
        addressSearchResults.value = [];
        addressSearchLoading.value = false;
        addressSearchError.value = false;
        addressSearchOpen.value = false;
        return;
    }

    addressSearchLoading.value = true;
    addressSearchError.value = false;

    try {
        const results = await fetchGardenAddressResults(trimmed);
        addressSearchResults.value = results;
        addressSearchError.value = results.length === 0;
        addressSearchOpen.value = true;
    } catch {
        addressSearchResults.value = [];
        addressSearchError.value = true;
        addressSearchOpen.value = true;
    } finally {
        addressSearchLoading.value = false;
    }
}, 400);

function onGardenAddressSearchInput() {
    selectedGardenAddressLabel.value = null;
    gardenDimensionsMessage.value = null;
    void runGardenAddressSearch(addressSearchQuery.value);
}

async function applyGardenLocationDimensions(
    lat: number,
    lng: number,
    addressBoundingBox: string | null = null,
) {
    gardenDimensionsLoading.value = true;
    gardenDimensionsMessage.value = null;

    const anchorLat = roundGardenCoordinate(lat);
    const anchorLng = roundGardenCoordinate(lng);
    gardenForm.center_lat = anchorLat;
    gardenForm.center_lng = anchorLng;
    gardenFormLocationChosen.value = true;
    gardenForm.clearErrors('center_lat');
    gardenForm.clearErrors('center_lng');

    try {
        const dims = addressBoundingBox
            ? dimensionsFromAddressBoundingBox(addressBoundingBox)
            : null;

        if (dims) {
            gardenForm.widthMeters = dims.widthMeters;
            gardenForm.heightMeters = dims.heightMeters;
            gardenForm.clearErrors('widthMeters');
            gardenForm.clearErrors('heightMeters');
            gardenDimensionsMessage.value =
                gardenSetupMode.value === 'ortophoto'
                    ? `Aadressi piirkond: ${dims.widthMeters} × ${dims.heightMeters} m. Võid mõõte enne salvestamist muuta.`
                    : `Aadressi piirkond: ${dims.widthMeters} × ${dims.heightMeters} m. Võid mõõte käsitsi muuta.`;
            return;
        }

        if (
            !Number(gardenForm.widthMeters) ||
            !Number(gardenForm.heightMeters)
        ) {
            gardenForm.widthMeters = gardenForm.widthMeters || 12;
            gardenForm.heightMeters = gardenForm.heightMeters || 8;
        }

        gardenDimensionsMessage.value =
            gardenSetupMode.value === 'ortophoto'
                ? 'Asukoht on määratud. Sisesta aia laius ja sügavus meetrites.'
                : 'Asukoht on määratud. Sisesta laius ja sügavus meetrites.';
    } catch {
        gardenDimensionsMessage.value =
            'Asukoht on määratud; mõõtete automaatne päring ebaõnnestus.';
    } finally {
        gardenDimensionsLoading.value = false;
    }
}

async function selectGardenAddress(result: GardenAddressSearchResult) {
    selectedGardenAddressLabel.value = result.county
        ? `${result.label} (${result.county})`
        : result.label;
    addressSearchQuery.value = result.label;
    addressSearchOpen.value = false;
    addressSearchError.value = false;
    geolocationFailed.value = false;
    gardenForm.clearErrors('center_lat');
    gardenForm.clearErrors('center_lng');

    await applyGardenLocationDimensions(
        result.lat,
        result.lng,
        result.gBoundingBox,
    );
}

function requestGardenGeolocation() {
    if (!('geolocation' in navigator)) {
        geolocationFailed.value = true;
        return;
    }

    geolocationRequested.value = true;
    geolocationFailed.value = false;
    addressSearchError.value = false;
    requestGeolocationPosition();
}

function clearGardenAddressSelectionFeedback() {
    selectedGardenAddressLabel.value = null;
    geolocationFailed.value = false;
    gardenDimensionsMessage.value = null;
}

watch(geolocationLoading, (loading, prevLoading) => {
    if (loading || !prevLoading) {
        return;
    }

    if (createGeolocationRequested.value) {
        createGeolocationRequested.value = false;

        if (geolocationError.value) {
            createGeolocationFailed.value = true;
            return;
        }

        createGeolocationFailed.value = false;
        const lat = geolocationCoords.value.latitude;
        const lng = geolocationCoords.value.longitude;
        createSelectedGardenAddressLabel.value = 'Minu asukoht (brauser)';
        createAddressSearchOpen.value = false;
        void applyCreateGardenLocationDimensions(lat, lng);
        return;
    }

    if (!geolocationRequested.value) {
        return;
    }

    geolocationRequested.value = false;

    if (geolocationError.value) {
        geolocationFailed.value = true;
        return;
    }

    const lat = geolocationCoords.value.latitude;
    const lng = geolocationCoords.value.longitude;
    selectedGardenAddressLabel.value = 'Minu asukoht (brauser)';
    addressSearchOpen.value = false;
    void applyGardenLocationDimensions(lat, lng);
});

const gardenForm = useForm<{
    name: string;
    widthMeters: number;
    heightMeters: number;
    shape_mask: number[][];
    center_lat: number | null;
    center_lng: number | null;
}>({
    name: props.gardenPlan.name,
    widthMeters: props.gardenPlan.width / 100,
    heightMeters: props.gardenPlan.height / 100,
    shape_mask: resizeGardenShapeMask(
        props.gardenPlan.shape_mask,
        props.gardenPlan.width,
        props.gardenPlan.height,
        planShapeMaskCellCm(props.gardenPlan),
    ),
    center_lat: props.gardenPlan.center_lat,
    center_lng: props.gardenPlan.center_lng,
});
const hasGardenCoordinates = computed(
    () =>
        props.gardenPlan.center_lat != null &&
        props.gardenPlan.center_lng != null &&
        Number.isFinite(Number(props.gardenPlan.center_lat)) &&
        Number.isFinite(Number(props.gardenPlan.center_lng)),
);
/** Ortofoto esmase seadistuse ajal pole vaja käsitsi koordinaate, kiirvalikuid ega kuju ruute. */
const gardenOrtophotoSetupActive = computed(
    () => !hasGardenCoordinates.value && gardenSetupMode.value === 'ortophoto',
);
const hideGardenQuickPresets = computed(
    () =>
        gardenSetupMode.value === 'ortophoto' ||
        (gardenSetupMode.value === 'manual' && !hasGardenCoordinates.value),
);
const gardenOrtophotoSetupReady = computed(() => {
    const lat = gardenForm.center_lat;
    const lng = gardenForm.center_lng;
    const w = Number(gardenForm.widthMeters);
    const h = Number(gardenForm.heightMeters);

    return (
        gardenFormLocationChosen.value &&
        lat != null &&
        lng != null &&
        Number.isFinite(Number(lat)) &&
        Number.isFinite(Number(lng)) &&
        Number.isFinite(w) &&
        w > 0 &&
        Number.isFinite(h) &&
        h > 0
    );
});
const gardenManualSetupReady = computed(() => {
    const hasShape = gardenManualShapeMask.value.some((row) => row.includes(1));
    if (hasShape) {
        return true;
    }

    const lat = gardenForm.center_lat;
    const lng = gardenForm.center_lng;
    const w = Number(gardenForm.widthMeters);
    const h = Number(gardenForm.heightMeters);

    return (
        lat != null &&
        lng != null &&
        Number.isFinite(Number(lat)) &&
        Number.isFinite(Number(lng)) &&
        Number.isFinite(w) &&
        w > 0 &&
        Number.isFinite(h) &&
        h > 0
    );
});

/** Käsitsi mõõdud salvestatud ilma geo-koordinaatideta (nt rõdu plaan). */
function manualLayoutStorageKey(planId: number): string {
    return `aiandus_manual_layout_${planId}`;
}

function readManualLayoutActive(planId: number): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.sessionStorage.getItem(manualLayoutStorageKey(planId)) === '1'
    );
}

function writeManualLayoutActive(planId: number, active: boolean): void {
    if (typeof window === 'undefined') {
        return;
    }

    const key = manualLayoutStorageKey(planId);
    if (active) {
        window.sessionStorage.setItem(key, '1');
    } else {
        window.sessionStorage.removeItem(key);
    }
}

const manualLayoutActive = ref(readManualLayoutActive(props.gardenPlan.id));
const gardenLocationEditorOpen = ref(false);
const gardenFormLocationChosen = ref(
    props.gardenPlan.center_lat != null &&
        props.gardenPlan.center_lng != null &&
        Number.isFinite(Number(props.gardenPlan.center_lat)) &&
        Number.isFinite(Number(props.gardenPlan.center_lng)),
);

/** Plaan on seadistatud ilma geo-koordinaatideta (nt modaalist käsitsi kuju). */
const gardenPlanConfiguredWithoutGeo = computed(() => {
    if (hasGardenCoordinates.value) {
        return false;
    }

    if (
        (props.gardenPlan.shape_mask?.length ?? 0) > 0 &&
        props.gardenPlan.shape_mask!.some((row) => row.includes(0))
    ) {
        return true;
    }

    return props.gardenPlan.width !== 1200 || props.gardenPlan.height !== 800;
});

/** Käsitsi plaan ilma salvestatud asukohata (nt rõdu, terrass). */
const isManualGardenWithoutGeo = computed(
    () =>
        !hasGardenCoordinates.value &&
        (manualLayoutActive.value || gardenPlanConfiguredWithoutGeo.value),
);

const showGardenLocationSection = computed(() => {
    if (hasGardenCoordinates.value) {
        return true;
    }

    if (gardenSetupMode.value === 'manual') {
        return false;
    }

    if (gardenOrtophotoSetupActive.value) {
        return true;
    }

    return gardenLocationEditorOpen.value;
});

const showGardenCoordinateInputs = computed(
    () => hasGardenCoordinates.value || gardenFormLocationChosen.value,
);

/** Tühi aiaplaan: pole koordinaate, peenraid ega salvestatud käsitsi paigutust. */
const needsGardenInitialSetup = computed(
    () =>
        !hasGardenCoordinates.value &&
        props.beds.length === 0 &&
        !manualLayoutActive.value &&
        !gardenPlanConfiguredWithoutGeo.value,
);

function syncGardenInitialSetupUi(): void {
    if (needsGardenInitialSetup.value) {
        plannerControlsOpen.value = false;
    }
}

function isGardenPresetActive(widthMeters: number, heightMeters: number) {
    const w = Number(gardenForm.widthMeters);
    const h = Number(gardenForm.heightMeters);

    return (
        Number.isFinite(w) &&
        Number.isFinite(h) &&
        Math.abs(w - widthMeters) < 0.01 &&
        Math.abs(h - heightMeters) < 0.01
    );
}

function applyGardenPreset(
    widthMeters: number,
    heightMeters: number,
    name?: string,
) {
    if (name) {
        gardenForm.name = name;
    }
    gardenForm.widthMeters = widthMeters;
    gardenForm.heightMeters = heightMeters;
    gardenForm.clearErrors('widthMeters');
    gardenForm.clearErrors('heightMeters');
}

function chooseGardenSetupMode(mode: GardenSetupMode) {
    gardenSetupMode.value = mode;
    gardenDimensionsMessage.value = null;

    if (mode === 'manual') {
        gardenManualShapeMask.value = [];
        gardenManualCellSizeCm.value = planShapeMaskCellCm(props.gardenPlan);
        gardenForm.center_lat = null;
        gardenForm.center_lng = null;
        gardenDimensionsMessage.value = null;
        return;
    }

    gardenDimensionsMessage.value =
        'Otsi aia aadress või kasuta geolokatsiooni, seejärel sisesta mõõdud meetrites.';
}
const gardenFormDimensionErrors = computed(
    () => gardenForm.errors as typeof gardenForm.errors & DimensionFormErrors,
);
type CreateGardenPlanForm = {
    name: string;
    widthMeters: number | null;
    heightMeters: number | null;
    center_lat: number | null;
    center_lng: number | null;
    shape_mask: number[][] | null;
};

const createGardenPlanForm = useForm<CreateGardenPlanForm>({
    name: '',
    widthMeters: null,
    heightMeters: null,
    center_lat: null,
    center_lng: null,
    shape_mask: null,
});

const createOrtophotoSetupReady = computed(() => {
    const lat = createGardenPlanForm.center_lat;
    const lng = createGardenPlanForm.center_lng;
    const w = Number(createGardenPlanForm.widthMeters);
    const h = Number(createGardenPlanForm.heightMeters);

    return (
        lat != null &&
        lng != null &&
        Number.isFinite(Number(lat)) &&
        Number.isFinite(Number(lng)) &&
        Number.isFinite(w) &&
        w > 0 &&
        Number.isFinite(h) &&
        h > 0
    );
});

function resetCreateGardenPlanModalState() {
    createGardenPlanForm.reset();
    createGardenPlanForm.clearErrors();
    createAddressSearchQuery.value = '';
    createAddressSearchResults.value = [];
    createAddressSearchLoading.value = false;
    createAddressSearchError.value = false;
    createAddressSearchOpen.value = false;
    createSelectedGardenAddressLabel.value = null;
    createGeolocationRequested.value = false;
    createGeolocationFailed.value = false;
    createGardenDimensionsLoading.value = false;
    createGardenDimensionsMessage.value = null;
    createGardenShapeMask.value = null;
    createSetupMode.value = 'ortophoto';
    createManualShapeMask.value = [];
    createManualCellSizeCm.value = 50;
}

const runCreateGardenAddressSearch = useDebounceFn(async (query: string) => {
    const trimmed = query.trim();

    if (trimmed.length < 2) {
        createAddressSearchResults.value = [];
        createAddressSearchLoading.value = false;
        createAddressSearchError.value = false;
        createAddressSearchOpen.value = false;
        return;
    }

    createAddressSearchLoading.value = true;
    createAddressSearchError.value = false;

    try {
        const results = await fetchGardenAddressResults(trimmed);
        createAddressSearchResults.value = results;
        createAddressSearchError.value = results.length === 0;
        createAddressSearchOpen.value = true;
    } catch {
        createAddressSearchResults.value = [];
        createAddressSearchError.value = true;
        createAddressSearchOpen.value = true;
    } finally {
        createAddressSearchLoading.value = false;
    }
}, 400);

function onCreateGardenAddressSearchInput() {
    createSelectedGardenAddressLabel.value = null;
    createGardenDimensionsMessage.value = null;
    void runCreateGardenAddressSearch(createAddressSearchQuery.value);
}

async function applyCreateGardenLocationDimensions(
    lat: number,
    lng: number,
    addressBoundingBox: string | null = null,
) {
    createGardenDimensionsLoading.value = true;
    createGardenDimensionsMessage.value = null;

    const anchorLat = roundGardenCoordinate(lat);
    const anchorLng = roundGardenCoordinate(lng);
    createGardenPlanForm.center_lat = anchorLat;
    createGardenPlanForm.center_lng = anchorLng;
    createGardenPlanForm.clearErrors('center_lat');
    createGardenPlanForm.clearErrors('center_lng');

    try {
        const dims = addressBoundingBox
            ? dimensionsFromAddressBoundingBox(addressBoundingBox)
            : null;

        createGardenPlanForm.shape_mask = null;

        if (dims) {
            createGardenPlanForm.widthMeters = dims.widthMeters;
            createGardenPlanForm.heightMeters = dims.heightMeters;
            createGardenDimensionsMessage.value = `Aadressi piirkond: ${dims.widthMeters} × ${dims.heightMeters} m. Võid mõõte enne salvestamist muuta.`;
            return;
        }

        createGardenPlanForm.widthMeters =
            createGardenPlanForm.widthMeters ?? DEFAULT_CREATE_GARDEN_WIDTH_M;
        createGardenPlanForm.heightMeters =
            createGardenPlanForm.heightMeters ?? DEFAULT_CREATE_GARDEN_HEIGHT_M;
        createGardenDimensionsMessage.value =
            'Asukoht on määratud. Sisesta aia laius ja sügavus meetrites.';
    } catch {
        createGardenDimensionsMessage.value =
            'Asukoht on määratud; mõõtete automaatne päring ebaõnnestus.';
    } finally {
        createGardenDimensionsLoading.value = false;
    }
}

async function selectCreateGardenAddress(result: GardenAddressSearchResult) {
    createSelectedGardenAddressLabel.value = result.county
        ? `${result.label} (${result.county})`
        : result.label;
    createAddressSearchQuery.value = result.label;
    createAddressSearchOpen.value = false;
    createAddressSearchError.value = false;
    createGeolocationFailed.value = false;

    await applyCreateGardenLocationDimensions(
        result.lat,
        result.lng,
        result.gBoundingBox,
    );
}

function requestCreateGardenGeolocation() {
    if (!('geolocation' in navigator)) {
        createGeolocationFailed.value = true;
        return;
    }

    createGeolocationRequested.value = true;
    createGeolocationFailed.value = false;
    createAddressSearchError.value = false;
    requestGeolocationPosition();
}

function clearCreateGardenAddressFeedback() {
    createSelectedGardenAddressLabel.value = null;
    createGeolocationFailed.value = false;
    createGardenDimensionsMessage.value = null;
}
function resetGardenFormLocationDraft() {
    if (hasGardenCoordinates.value) {
        return;
    }

    gardenForm.center_lat = null;
    gardenForm.center_lng = null;
    gardenFormLocationChosen.value = false;
    gardenLocationEditorOpen.value = false;
    selectedGardenAddressLabel.value = null;
    addressSearchQuery.value = '';
    gardenDimensionsMessage.value = null;
}

function openGardenLocationEditor() {
    gardenLocationEditorOpen.value = true;
}

function dismissGardenLocationEditor() {
    resetGardenFormLocationDraft();
}

function openGardenPlanEditor() {
    resetGardenFormLocationDraft();
    plannerControlsOpen.value = true;
}

function deleteGardenPlan() {
    openConfirmDialog(
        'Kustuta aiaplaan?',
        'Kustutada see aiaplaan koos selle peenardatega? Sidumata taimed jäävad alles.',
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

/** Lisa peenar: ava loomise viisard (nimi → kuju → aiaplaan). */
function startNewBedOnMap() {
    if (!canPlaceBedsOnMap.value) {
        return;
    }

    createMenuOpen.value = false;
    router.get(`/map/${props.gardenPlan.id}/beds/new`);
}

function highlightCreatedBedFromFlash() {
    const createdId = page.props.flash?.created_bed_id;
    if (!createdId || !props.beds.some((bed) => bed.id === createdId)) {
        return;
    }

    isLayoutEditing.value = true;
    showBedsLayer.value = true;
    activeBedCardId.value = createdId;
    selectedBedId.value = createdId;
}

function onPlannerCanvasClick(event: MouseEvent) {
    if (suppressPlannerSurfaceClick.value) {
        return;
    }

    const target = event.target as HTMLElement | null;
    if (
        target?.closest(
            '[data-bed-pin], [data-bed-footprint], [data-place-bed-dialog], [data-ui-overlay], button, a, input, select, textarea, label',
        )
    ) {
        return;
    }

    if (activeBedCardId.value !== null) {
        activeBedCardId.value = null;
    }

    if (hoveredBedId.value !== null) {
        hoveredBedId.value = null;
    }
}

function onFloatingPlusClick() {
    createMenuOpen.value = !createMenuOpen.value;
}

function openCreateGardenPlanModal() {
    createMenuOpen.value = false;
    resetCreateGardenPlanModalState();
    createGardenPlanModalOpen.value = true;
    nextTick(() => createGardenPlanNameInput.value?.focus());
}

function closeCreateGardenPlanModal() {
    if (createGardenPlanForm.processing) return;
    createGardenPlanModalOpen.value = false;
}

function submitCreateGardenPlan() {
    if (createSetupMode.value === 'ortophoto') {
        const lat = createGardenPlanForm.center_lat;
        const lng = createGardenPlanForm.center_lng;
        const w = Number(createGardenPlanForm.widthMeters);
        const h = Number(createGardenPlanForm.heightMeters);

        if (
            lat == null ||
            lng == null ||
            !Number.isFinite(Number(lat)) ||
            !Number.isFinite(Number(lng))
        ) {
            createGardenDimensionsMessage.value =
                'Vali aia asukoht (aadress või geolokatsioon).';
            return;
        }

        if (!Number.isFinite(w) || w <= 0 || !Number.isFinite(h) || h <= 0) {
            createGardenDimensionsMessage.value =
                'Sisesta aia laius ja sügavus meetrites.';
            return;
        }
    } else {
        const mask = createManualShapeMask.value;
        const hasActive = mask.some((row) => row.includes(1));
        if (!hasActive) {
            createGardenDimensionsMessage.value =
                'Märgi vähemalt üks ruut aia kuju määramiseks.';
            return;
        }

        let minR = Infinity;
        let maxR = -Infinity;
        let minC = Infinity;
        let maxC = -Infinity;

        mask.forEach((row, r) =>
            row.forEach((v, c) => {
                if (v === 1) {
                    minR = Math.min(minR, r);
                    maxR = Math.max(maxR, r);
                    minC = Math.min(minC, c);
                    maxC = Math.max(maxC, c);
                }
            }),
        );

        const widthCm = (maxC - minC + 1) * createManualCellSizeCm.value;
        const heightCm = (maxR - minR + 1) * createManualCellSizeCm.value;
        const trimmedMask = mask
            .slice(minR, maxR + 1)
            .map((row) => row.slice(minC, maxC + 1));

        createGardenPlanForm.widthMeters = widthCm / 100;
        createGardenPlanForm.heightMeters = heightCm / 100;
        createGardenPlanForm.shape_mask = trimmedMask;
        createGardenPlanForm.center_lat = null;
        createGardenPlanForm.center_lng = null;
        createGardenPlanForm.clearErrors('widthMeters');
        createGardenPlanForm.clearErrors('heightMeters');
    }

    createGardenPlanForm
        .transform((data) => {
            const widthMeters =
                data.widthMeters != null &&
                Number.isFinite(Number(data.widthMeters))
                    ? Number(data.widthMeters)
                    : DEFAULT_CREATE_GARDEN_WIDTH_M;
            const heightMeters =
                data.heightMeters != null &&
                Number.isFinite(Number(data.heightMeters))
                    ? Number(data.heightMeters)
                    : DEFAULT_CREATE_GARDEN_HEIGHT_M;

            return {
                name: data.name.trim() || undefined,
                width: Math.round(widthMeters * 100),
                height: Math.round(heightMeters * 100),
                center_lat: data.center_lat,
                center_lng: data.center_lng,
                shape_mask: data.shape_mask ?? undefined,
                shape_mask_cell_cm:
                    createSetupMode.value === 'manual'
                        ? createManualCellSizeCm.value
                        : GARDEN_SHAPE_CELL_CM,
                boundary_polygon: null,
            };
        })
        .post('/garden-plans', {
            preserveScroll: true,
            onSuccess: () => {
                createGardenPlanModalOpen.value = false;
                resetCreateGardenPlanModalState();
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
            plannerControlsOpen.value = false;
            manualLayoutActive.value = readManualLayoutActive(id);
            syncGardenInitialSetupUi();
            searchQuery.value = '';
            showBedsLayer.value = true;
            nextTick(() => {
                syncPlannerViewportSize();
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
        shape_mask: props.gardenPlan.shape_mask,
        shape_mask_cell_cm: props.gardenPlan.shape_mask_cell_cm,
        center_lat: props.gardenPlan.center_lat,
        center_lng: props.gardenPlan.center_lng,
    }),
    (plan) => {
        gardenForm.name = plan.name;
        gardenForm.widthMeters = plan.width / 100;
        gardenForm.heightMeters = plan.height / 100;
        gardenForm.shape_mask = resizeGardenShapeMask(
            plan.shape_mask,
            plan.width,
            plan.height,
            planShapeMaskCellCm(plan as GardenPlan),
        );
        gardenManualCellSizeCm.value = planShapeMaskCellCm(plan as GardenPlan);
        gardenForm.center_lat = plan.center_lat;
        gardenForm.center_lng = plan.center_lng;
        gardenLocationEditorOpen.value = false;
        gardenFormLocationChosen.value = hasGardenCoordinates.value;
    },
    { deep: true },
);

watch(hasGardenCoordinates, (has) => {
    if (has) {
        gardenFormLocationChosen.value = true;
        gardenLocationEditorOpen.value = true;
    }
});

watch(needsGardenInitialSetup, (needs) => {
    if (needs) {
        syncGardenInitialSetupUi();
    }
});

watch(hasGardenCoordinates, (available) => {
    if (!available) {
        plannerLeafletZoom.value = null;
        return;
    }

    plannerInitialViewportFitDone.value = false;
    userAdjustedViewport.value = false;
    nextTick(() => {
        syncPlannerViewportSize();
        runPlannerInitialViewportFit();
        refreshPlannerOrtophoto();
    });
});

watch(showMapBackground, (enabled) => {
    if (!enabled || !hasGardenCoordinates.value) {
        return;
    }

    refreshPlannerOrtophoto();
});

function refreshPlannerOrtophoto() {
    nextTick(() => {
        plannerMapBgRef.value?.scheduleMapRefresh();
    });
}

/** Üks samm nupu või rulliku klõpsul = 1% ekraanil näidatavast suumist. */
function plannerZoomOnePercentDelta(): number {
    const fit = Math.max(getFitGardenZoom(), 0.001);

    return fit * 0.01;
}

function plannerZoomStepDelta(): number {
    return plannerZoomOnePercentDelta();
}

function snapPlannerZoomToOnePercent(value: number): number {
    const fit = Math.max(getFitGardenZoom(), 0.001);

    if (usesOrtophotoSharpZoom.value) {
        const pct = Math.round((value / fit) * 100);

        return (pct / 100) * fit;
    }

    return Math.round(value * 100) / 100;
}

function wheelZoomDelta(event: WheelEvent): number {
    let direction = event.deltaY > 0 ? -1 : 1;
    if (event.deltaMode === WheelEvent.DOM_DELTA_LINE) {
        direction *= 0.35;
    } else if (event.deltaMode === WheelEvent.DOM_DELTA_PAGE) {
        direction *= 0.2;
    }

    const magnitude = plannerZoomOnePercentDelta() * (event.ctrlKey ? 3 : 1);

    return direction * magnitude;
}

watch(
    () => props.gardenPlan.id,
    () => {
        gardenPlannerShapeCellCm.value = planShapeMaskCellCm(props.gardenPlan);
    },
);

watch(
    () => [gardenForm.widthMeters, gardenForm.heightMeters] as const,
    ([widthMeters, heightMeters]) => {
        if (skipShapeMaskResizeFromDimensions.value) {
            return;
        }

        const { widthCm, heightCm } = gardenShapeMaskCmFromForm(
            widthMeters,
            heightMeters,
        );
        const cellCm = plannerShapeEditorCellCm.value;
        const resized = resizeGardenShapeMask(
            gardenForm.shape_mask,
            widthCm,
            heightCm,
            cellCm,
        );
        const unchanged =
            resized.length === gardenForm.shape_mask.length &&
            resized.every(
                (row, y) =>
                    row.length === gardenForm.shape_mask[y]?.length &&
                    row.every(
                        (value, x) => value === gardenForm.shape_mask[y]?.[x],
                    ),
            );

        if (!unchanged) {
            gardenForm.shape_mask = resized;
        }
    },
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

    syncGardenInitialSetupUi();
    syncPositionsFromProps();
    highlightCreatedBedFromFlash();
    nextTick(() => {
        syncPlannerViewportSize();
        runPlannerInitialViewportFit();
    });

    if (typeof ResizeObserver !== 'undefined' && plannerViewport.value) {
        resizeObserver = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (!entry?.target) return;
            syncPlannerViewportSize(entry.target as HTMLElement);
            nextTick(() => {
                if (!userAdjustedViewport.value) {
                    fitGardenToViewport();
                }
            });
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
    window.removeEventListener('pointermove', onPlannerWindowPointerMove);
    window.removeEventListener('pointerup', stopPanning);
    window.removeEventListener('pointercancel', stopPanning);
    window.removeEventListener('pointerup', onPinchPointerUp);
    window.removeEventListener('pointercancel', onPinchPointerUp);
    viewportPointerMap.clear();
    pinchState.value = null;
    resizeObserver?.disconnect();
    mobileBedListIntersectionObserver?.disconnect();
    if (hideBedInfoTimer) {
        clearTimeout(hideBedInfoTimer);
        hideBedInfoTimer = null;
    }
});

function syncPositionsFromProps() {
    const next: Record<number, { x: number; y: number }> = {};
    props.beds.forEach((bed) => {
        next[bed.id] = {
            x: bed.garden_x ?? 0,
            y: bed.garden_y ?? 0,
        };
    });
    localPositions.value = next;
}

const bedNames = computed(() => localBeds.value.map((b) => b.name));

const filteredBeds = computed(() => {
    let list = sortBedsForPlanner(localBeds.value, 'plan_order');

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

function resetPlannerFilters() {
    searchQuery.value = '';
    showBedsLayer.value = true;
}

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
const gardenSurfaceSize = computed(() =>
    computeGardenSurfacePx(gardenWidthCm.value, gardenHeightCm.value),
);
const gardenSurfaceWidth = computed(() => gardenSurfaceSize.value.width);
const gardenSurfaceHeight = computed(() => gardenSurfaceSize.value.height);
const gardenShapeMaskEditorCols = computed(() =>
    gardenShapeMaskCols(gardenWidthCm.value, plannerShapeEditorCellCm.value),
);
const gardenShapeMaskEditorRows = computed(() =>
    gardenShapeMaskRows(gardenHeightCm.value, plannerShapeEditorCellCm.value),
);
const gardenShapeClipPathId = computed(
    () => `garden-shape-clip-${props.gardenPlan.id}`,
);

/** Aktiivsed maski ruudud clip-path jaoks (mitte-ristkülikuline aed). */
const gardenMaskLayout = computed(() => {
    const fullW = gardenSurfaceWidth.value;
    const fullH = gardenSurfaceHeight.value;
    const mask = props.gardenPlan.shape_mask;
    const base = {
        fullW,
        fullH,
        displayW: fullW,
        displayH: fullH,
        offsetX: 0,
        offsetY: 0,
        useTightFrame: false,
    };

    if (usesOrtophotoSharpZoom.value || !mask?.length) {
        return base;
    }

    const cols = mask[0]?.length ?? 0;
    const rows = mask.length;
    if (!cols || !rows) {
        return base;
    }

    const bounds = getShapeMaskActiveCellBounds(mask);
    if (!bounds) {
        return base;
    }

    const cellW = fullW / cols;
    const cellH = fullH / rows;
    const offsetX = bounds.minCol * cellW;
    const offsetY = bounds.minRow * cellH;
    const displayW = (bounds.maxCol - bounds.minCol + 1) * cellW;
    const displayH = (bounds.maxRow - bounds.minRow + 1) * cellH;

    return {
        fullW,
        fullH,
        displayW,
        displayH,
        offsetX,
        offsetY,
        useTightFrame:
            displayW < fullW - 0.5 ||
            displayH < fullH - 0.5 ||
            offsetX > 0.5 ||
            offsetY > 0.5,
    };
});

const plannerDisplayWidth = computed(() => gardenMaskLayout.value.displayW);
const plannerDisplayHeight = computed(() => gardenMaskLayout.value.displayH);

const gardenShapeMaskClipRects = computed(() => {
    const mask = props.gardenPlan.shape_mask;
    if (!mask?.length || !shapeMaskHasInactiveCells(mask)) {
        return [];
    }

    const cols = mask[0]?.length ?? 0;
    if (!cols) {
        return [];
    }

    const rows = mask.length;
    const cellW = gardenSurfaceWidth.value / cols;
    const cellH = gardenSurfaceHeight.value / rows;
    const rects: { x: number; y: number; w: number; h: number }[] = [];

    for (let y = 0; y < rows; y += 1) {
        for (let x = 0; x < cols; x += 1) {
            if (mask[y]?.[x] === 1) {
                rects.push({
                    x: x * cellW,
                    y: y * cellH,
                    w: cellW,
                    h: cellH,
                });
            }
        }
    }

    return rects;
});

const useGardenShapeClip = computed(
    () => !showMapBackground.value && gardenShapeMaskClipRects.value.length > 0,
);

/** 100% = aed mahub vaates (fit), mitte sisemine skaalaarv. */
const zoomPercent = computed(() => {
    const fit = fitGardenZoom.value;
    const relative = fit > 0.001 ? Math.round((zoom.value / fit) * 100) : 100;

    return `${relative}%`;
});
const usesOrtophotoSharpZoom = computed(
    () => hasGardenCoordinates.value && showMapBackground.value,
);
/** Käsitsi ruudustikul peenar reaalses mõõtkavas; ortofotol tihv. */
const showBedsAsPlannerFootprints = computed(
    () => !usesOrtophotoSharpZoom.value,
);
const shapeMaskCellCm = computed(() => planShapeMaskCellCm(props.gardenPlan));
const plannerShapeEditorCellCm = computed(() =>
    !hasGardenCoordinates.value
        ? gardenPlannerShapeCellCm.value
        : shapeMaskCellCm.value,
);
const plannerShapeCellCm = computed(() =>
    usesOrtophotoSharpZoom.value ? GARDEN_GRID_CELL_CM : shapeMaskCellCm.value,
);
const plannerMajorGridCm = computed(() => {
    if (usesOrtophotoSharpZoom.value) {
        return GARDEN_SHAPE_CELL_CM;
    }

    const cell = shapeMaskCellCm.value;
    if (cell >= 100) {
        return cell;
    }

    return Math.min(100, Math.max(cell * 2, 50));
});
const fitGardenZoom = computed(() => getFitGardenZoom());

/** Ortofotol on pind fikseeritud; ruudustikul skaleerub zoomiga. */
function getPlannerCanvasSize(zoomLevel = zoom.value): {
    width: number;
    height: number;
} {
    const base = gardenSurfaceSize.value;

    if (usesOrtophotoSharpZoom.value) {
        // Kaader on fikseeritud (kaart suumib sisemiselt) — suurendus ei muuda
        // kaadri mõõtu, seega pan/tsentreerimine kasutab fikseeritud suurust.
        return {
            width: base.width,
            height: base.height,
        };
    }

    return {
        width: plannerDisplayWidth.value * zoomLevel,
        height: plannerDisplayHeight.value * zoomLevel,
    };
}

function plannerCanvasExceedsViewport(zoomLevel = zoom.value): boolean {
    const vw = viewportSize.value.width;
    const vh = viewportSize.value.height;
    if (!vw || !vh) {
        return false;
    }

    const canvas = getPlannerCanvasSize(zoomLevel);
    return canvas.width > vw + 1 || canvas.height > vh + 1;
}

const canPanPlannerViewport = computed(() => {
    if (!viewportSize.value.width || !viewportSize.value.height) {
        return false;
    }

    const fitZoom = getFitGardenZoom();

    return (
        plannerCanvasExceedsViewport() || Math.abs(zoom.value - fitZoom) > 0.001
    );
});
const plannerViewportCursorClass = computed(() => {
    if (isPanning.value) {
        return 'cursor-grabbing';
    }

    if (canPanPlannerViewport.value) {
        return 'cursor-grab';
    }

    return 'cursor-default';
});
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
/** Tegelik px/cm aia pinnal (võib erineda CM_TO_PX-st kui pind on kokku surutud). */
const plannerCmToPx = computed(() => {
    const wCm = gardenWidthCm.value;
    if (wCm <= 0) {
        return CM_TO_PX;
    }

    return gardenSurfaceWidth.value / wCm;
});
const plannerGridSizePx = computed(() =>
    Math.max(10, Math.round(plannerShapeCellCm.value * plannerCmToPx.value)),
);

const scaleBarWidthPx = computed(() => {
    const meterPx = 100 * plannerCmToPx.value;

    if (usesOrtophotoSharpZoom.value) {
        return Math.round(meterPx * plannerVisualScale(zoom.value));
    }

    // Käsivaade: mõõtkava on pan/zoom konteineris, mis juba rakendab scale(zoom).
    return Math.round(meterPx);
});
const plannerBeds = computed(() => (showBedsLayer.value ? props.beds : []));
const plannerMapBgRef = ref<{
    scheduleMapRefresh: () => void;
    getMap: () => import('leaflet').Map | null;
} | null>(null);
const gardenLooksOversized = computed(() => {
    const w = props.gardenPlan.width / 100;
    const h = props.gardenPlan.height / 100;

    return Math.max(w, h) > 300;
});
const plannerOrtophotoFocusSpanMeters = computed(() => {
    if (!hasGardenCoordinates.value) {
        return 0;
    }

    return ortophotoFocusSpanMeters(
        props.gardenPlan.width / 100,
        props.gardenPlan.height / 100,
    );
});

watch(plannerOrtophotoFocusSpanMeters, (span, prev) => {
    if (
        !hasGardenCoordinates.value ||
        userAdjustedViewport.value ||
        span <= 0 ||
        span === prev
    ) {
        return;
    }

    nextTick(() => {
        fitGardenToViewport();
        refreshPlannerOrtophoto();
    });
});

const showPlannerMapCanvas = computed(
    () =>
        hasGardenCoordinates.value ||
        props.beds.length > 0 ||
        manualLayoutActive.value ||
        gardenPlanConfiguredWithoutGeo.value,
);

/** Peenraid saab paigutada, kui aia plaan on seadistatud (sh käsitsi rõdu plaan ilma GPS-i). */
const canPlaceBedsOnMap = computed(() => showPlannerMapCanvas.value);

const showGardenSetupSection = computed(
    () =>
        needsGardenInitialSetup.value ||
        (!showPlannerMapCanvas.value && !plannerControlsOpen.value),
);

const showPlannerFiltersEmptyState = computed(
    () => plannerBeds.value.length === 0 && props.beds.length > 0,
);
const selectedBed = computed(() => {
    if (selectedBedId.value === null) return null;
    return props.beds.find((bed) => bed.id === selectedBedId.value) ?? null;
});

watch(plannerBeds, (beds) => {
    if (
        selectedBedId.value !== null &&
        !beds.some((bed) => bed.id === selectedBedId.value)
    ) {
        selectedBedId.value = beds[0]?.id ?? null;
    }
});

watch(
    () => page.props.flash?.created_bed_id,
    (createdId) => {
        if (createdId) {
            highlightCreatedBedFromFlash();
        }
    },
);

watch(
    () =>
        props.beds.map((bed) => [bed.id, bed.garden_x ?? 0, bed.garden_y ?? 0]),
    () => {
        const current = localPositions.value;
        const next: Record<number, { x: number; y: number }> = {};

        props.beds.forEach((bed) => {
            next[bed.id] = current[bed.id] ?? {
                x: bed.garden_x ?? 0,
                y: bed.garden_y ?? 0,
            };
        });

        localPositions.value = next;
    },
    { deep: true },
);

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
    return Math.max(10, bed.cell_size_cm || DEFAULT_BED_CELL_SIZE_CM);
}

function getBedPreviewImage(bed: Bed): string | null {
    if (bed.image_url) return bed.image_url;
    return bed.plants.find((plant) => plant.image_url)?.image_url ?? null;
}

function getBedCellBricks(bed: Bed): BedCellBrick[] {
    if (Array.isArray(bed.cell_bricks) && bed.cell_bricks.length > 0) {
        return bed.cell_bricks;
    }

    const layout = getBedLayout(bed);
    const unit = getBedCellSizeCm(bed);
    const bricks: BedCellBrick[] = [];

    layout.forEach((row, y) => {
        row.forEach((value, x) => {
            if (value === 1) {
                bricks.push({
                    x,
                    y,
                    w: 1,
                    h: 1,
                    width_cm: unit,
                    height_cm: unit,
                    kind: 'plantable',
                });
            } else if (value === -1) {
                bricks.push({
                    x,
                    y,
                    w: 1,
                    h: 1,
                    width_cm: unit,
                    height_cm: unit,
                    kind: 'walkway',
                });
            }
        });
    });

    return bricks;
}

function getBedPhysicalWidthCm(bed: Bed): number {
    const bricks = getBedCellBricks(bed);
    if (bricks.length > 0) {
        return bedBoundsCmFromBricks(
            resolveBedBricksCm(bricks, getBedCellSizeCm(bed)),
        ).widthCm;
    }

    return getBedWidthInCells(bed) * getBedCellSizeCm(bed);
}

function getBedPhysicalHeightCm(bed: Bed): number {
    const bricks = getBedCellBricks(bed);
    if (bricks.length > 0) {
        return bedBoundsCmFromBricks(
            resolveBedBricksCm(bricks, getBedCellSizeCm(bed)),
        ).heightCm;
    }

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

/** Tegelik peenra mõõt: cm väikese (<1 m) ja m suurema peenra puhul. */
function bedSizeLabel(bed: Bed): string {
    const widthCm = getBedPhysicalWidthCm(bed);
    const heightCm = getBedPhysicalHeightCm(bed);

    if (Math.max(widthCm, heightCm) >= 100) {
        const toM = (cm: number) => {
            const m = cm / 100;
            return Number.isInteger(m) ? `${m}` : m.toFixed(1);
        };
        return `${toM(widthCm)} × ${toM(heightCm)} m`;
    }

    return `${Math.round(widthCm)} × ${Math.round(heightCm)} cm`;
}

function isBedCardVisible(bedId: number): boolean {
    return hoveredBedId.value === bedId || activeBedCardId.value === bedId;
}

function bedCardSize(bed: Bed) {
    const pxPerCm = showBedsAsPlannerFootprints.value
        ? plannerCmToPx.value
        : CM_TO_PX;
    const width = Math.round(getBedPhysicalWidthCm(bed) * pxPerCm);
    const height = Math.round(getBedPhysicalHeightCm(bed) * pxPerCm);

    if (showBedsAsPlannerFootprints.value) {
        return {
            width: Math.max(8, width),
            height: Math.max(8, height),
        };
    }

    return {
        width: Math.max(MIN_BED_VISUAL_SIZE, width),
        height: Math.max(MIN_BED_VISUAL_SIZE, height),
    };
}

function getBedDisplayRect(bed: Bed) {
    const position = getBedPosition(bed);
    const size = bedCardSize(bed);

    return {
        x: position.x,
        y: position.y,
        width: size.width,
        height: size.height,
    };
}

function snapToGardenGrid(value: number): number {
    return (
        Math.round(value / plannerGridSizePx.value) * plannerGridSizePx.value
    );
}

function clampBedPosition(bed: Bed, x: number, y: number) {
    const size = bedCardSize(bed);
    return {
        x: Math.max(0, Math.min(x, gardenSurfaceWidth.value - size.width)),
        y: Math.max(0, Math.min(y, gardenSurfaceHeight.value - size.height)),
    };
}

function getBedPosition(bed: Bed) {
    const stored = localPositions.value[bed.id] ?? {
        x: bed.garden_x ?? 0,
        y: bed.garden_y ?? 0,
    };
    return clampBedPosition(bed, stored.x, stored.y);
}

function pinTipPosition(bed: Bed) {
    const rect = getBedDisplayRect(bed);

    return {
        x: rect.x + rect.width / 2,
        y: rect.y + rect.height,
    };
}

function bedCardAnchorPosition(bed: Bed) {
    const rect = getBedDisplayRect(bed);

    if (showBedsAsPlannerFootprints.value) {
        return {
            x: rect.x + rect.width / 2,
            y: rect.y,
        };
    }

    return pinTipPosition(bed);
}

/** Sama skaala mis peenrakihil (ortofoto: visualScale; ruudustik: zoom). */
function plannerCanvasScale(): number {
    if (usesOrtophotoSharpZoom.value) {
        return plannerVisualScale();
    }

    return Math.max(zoom.value, 0.01);
}

/** Vastukaal peenrakihi suumile — ~32px ring ekraanil. */
function bedPinScreenScale(): number {
    return 1 / Math.max(plannerCanvasScale(), 0.01);
}

function bedPinWrapperStyle(bed: Bed): Record<string, string> {
    const tip = pinTipPosition(bed);
    const scale = bedPinScreenScale();

    return {
        left: `${tip.x}px`,
        top: `${tip.y}px`,
        transform: `translate(-50%, -100%) scale(${scale})`,
        transformOrigin: 'bottom center',
    };
}

function bedFootprintWrapperStyle(bed: Bed): Record<string, string> {
    const pos = getBedPosition(bed);
    const size = bedCardSize(bed);

    return {
        left: `${pos.x}px`,
        top: `${pos.y}px`,
        width: `${size.width}px`,
        height: `${size.height}px`,
    };
}

type BedFootprintCell = {
    left: number;
    top: number;
    width: number;
    height: number;
    kind: 'plantable' | 'walkway' | 'empty';
    image_url?: string | null;
};

function plantImageForFootprintBrick(
    bed: Bed,
    brick: BedCellBrick,
): string | null {
    const repaired = repairLegacySubgridBrick(brick, getBedCellSizeCm(bed));
    const x = repaired.x ?? 0;
    const y = repaired.y ?? 0;
    const candidates = new Set([`${y},${x}`, `${y * 3},${x * 3}`]);

    for (const plant of bed.plants ?? []) {
        if (
            plant.position_in_bed &&
            candidates.has(plant.position_in_bed) &&
            plant.image_url
        ) {
            return plant.image_url;
        }
    }

    return null;
}

function getBedFootprintCells(bed: Bed): BedFootprintCell[] {
    const bricks = getBedCellBricks(bed);
    if (bricks.length > 0) {
        const unit = getBedCellSizeCm(bed);

        return bedFootprintRectsPx(bricks, unit).map((rect, index) => ({
            ...rect,
            image_url: plantImageForFootprintBrick(bed, bricks[index] ?? {}),
        }));
    }

    const layout = getBedLayout(bed);
    const bounds = getBedActiveBounds(bed);
    const cellPx = Math.round(getBedCellSizeCm(bed) * CM_TO_PX);
    const cells: BedFootprintCell[] = [];

    for (let r = bounds.minRow; r <= bounds.maxRow; r++) {
        const row = layout[r] ?? [];
        for (let c = bounds.minCol; c <= bounds.maxCol; c++) {
            if (row[c] !== 1) {
                continue;
            }
            cells.push({
                left: (c - bounds.minCol) * cellPx,
                top: (r - bounds.minRow) * cellPx,
                width: cellPx,
                height: cellPx,
                kind: 'plantable',
            });
        }
    }

    return cells;
}

/** Hover/tap kaart body-s (ei lõiku overflow-hidden). */
function bedCardFixedStyle(bed: Bed): Record<string, string> {
    const vp = plannerViewport.value;
    if (!vp) {
        return { display: 'none' };
    }

    const vpRect = vp.getBoundingClientRect();
    const cs = window.getComputedStyle(vp);
    const padLeft = parseFloat(cs.paddingLeft) || 0;
    const padTop = parseFloat(cs.paddingTop) || 0;
    const canvasScale = plannerCanvasScale();
    const anchor = bedCardAnchorPosition(bed);
    let anchorX: number;
    let anchorY: number;

    if (usesOrtophotoSharpZoom.value) {
        // Sisukiht skaleerub keskpunktist (transform-origin: 50% 50%), seega
        // arvestame keskpunkti nihke ekraaniankrut arvutades.
        const surfaceW = gardenSurfaceSize.value.width;
        const surfaceH = gardenSurfaceSize.value.height;
        anchorX =
            vpRect.left +
            padLeft +
            panX.value +
            (anchor.x - surfaceW / 2) * canvasScale +
            surfaceW / 2;
        anchorY =
            vpRect.top +
            padTop +
            panY.value +
            (anchor.y - surfaceH / 2) * canvasScale +
            surfaceH / 2;
    } else {
        anchorX = vpRect.left + padLeft + anchor.x * canvasScale + panX.value;
        anchorY = vpRect.top + padTop + anchor.y * canvasScale + panY.value;
    }
    const pinHeightPx = showBedsAsPlannerFootprints.value ? 0 : 42;
    const gap = 8;

    return {
        position: 'fixed',
        left: `${Math.round(anchorX)}px`,
        top: `${Math.round(anchorY - pinHeightPx - gap)}px`,
        transform: 'translate(-50%, -100%)',
        zIndex: '9999',
    };
}

function getPlannerLocalPoint(event: PointerEvent | WheelEvent | MouseEvent) {
    const viewport = plannerViewport.value;
    if (!viewport) {
        return { x: 0, y: 0 };
    }

    const rect = viewport.getBoundingClientRect();
    const cs = window.getComputedStyle(viewport);
    const padLeft = parseFloat(cs.paddingLeft) || 0;
    const padTop = parseFloat(cs.paddingTop) || 0;
    const scale = usesOrtophotoSharpZoom.value
        ? plannerVisualScale()
        : Math.max(zoom.value, 0.001);

    return {
        x: (event.clientX - rect.left - padLeft - panX.value) / scale,
        y: (event.clientY - rect.top - padTop - panY.value) / scale,
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

let hideBedInfoTimer: ReturnType<typeof setTimeout> | null = null;

function showBedInfo(bedId: number) {
    if (hideBedInfoTimer) {
        clearTimeout(hideBedInfoTimer);
        hideBedInfoTimer = null;
    }
    hoveredBedId.value = bedId;
}

function hideBedInfo(bedId: number) {
    if (activeBedCardId.value === bedId) {
        return;
    }

    if (hideBedInfoTimer) {
        clearTimeout(hideBedInfoTimer);
    }

    hideBedInfoTimer = setTimeout(() => {
        if (hoveredBedId.value === bedId && activeBedCardId.value !== bedId) {
            hoveredBedId.value = null;
        }
        hideBedInfoTimer = null;
    }, 320);
}

function onBedPointerEnter(bedId: number) {
    showBedInfo(bedId);
}

function onBedPointerLeave(bedId: number) {
    hideBedInfo(bedId);
}

function onBedCardPointerEnter(bedId: number) {
    showBedInfo(bedId);
}

function onBedCardPointerLeave(bedId: number) {
    if (activeBedCardId.value === bedId) {
        return;
    }

    hideBedInfo(bedId);
}

function toggleBedCard(bedId: number) {
    if (dragMoved.value) {
        return;
    }

    if (activeBedCardId.value === bedId) {
        activeBedCardId.value = null;
        hideBedInfo(bedId);
        return;
    }

    activeBedCardId.value = bedId;
    showBedInfo(bedId);
}

function togglePlannerBedList() {
    plannerBedListOpen.value = !plannerBedListOpen.value;
}

function focusBedDetails(bedId: number) {
    if (dragMoved.value) return;
    isLayoutEditing.value = true;
    selectedBedId.value = bedId;
}

function startDragging(bed: Bed, event: PointerEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-no-drag="true"]')) return;
    event.preventDefault();
    event.stopPropagation();
    isLayoutEditing.value = true;
    activeBedCardId.value = null;

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
    const rawX = pointer.x - dragOffset.value.x;
    const rawY = pointer.y - dragOffset.value.y;
    const next = clampBedPosition(
        bed,
        showBedsAsPlannerFootprints.value ? snapToGardenGrid(rawX) : rawX,
        showBedsAsPlannerFootprints.value ? snapToGardenGrid(rawY) : rawY,
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

function isPlannerPanBlockedTarget(target: EventTarget | null): boolean {
    if (!target || !(target instanceof Element)) {
        return false;
    }
    return Boolean(
        target.closest(
            '[data-bed-pin="true"], [data-bed-footprint="true"], [data-no-drag="true"], [data-ui-overlay="true"], button, a, input, select, textarea, label',
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
    const sharpen = plannerVisualScale();
    const gx = (mx - panX.value) / sharpen;
    const gy = (my - panY.value) / sharpen;
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
    const newZoom = clampUserZoom(state.startZoom * ratio);
    const rect = vp.getBoundingClientRect();
    const mx = (a.x + b.x) / 2 - rect.left;
    const my = (a.y + b.y) / 2 - rect.top;
    zoom.value = newZoom;
    const sharpen = plannerVisualScale(newZoom);
    panX.value = mx - state.gx * sharpen;
    panY.value = my - state.gy * sharpen;
    clampPlannerPan();
    userAdjustedViewport.value = true;
    refreshPlannerOrtophoto();
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
    clampPlannerPan();
    userAdjustedViewport.value = true;
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

    beginPanGesture(event, vp);
}

function beginPanGesture(event: PointerEvent, vp: HTMLElement) {
    if (!canPanPlannerViewport.value) {
        return;
    }

    isPanning.value = true;
    panGestureMoved.value = false;
    panPointerId.value = event.pointerId;
    panStart.value = {
        x: event.clientX,
        y: event.clientY,
        originX: panX.value,
        originY: panY.value,
    };

    try {
        vp.setPointerCapture(event.pointerId);
    } catch {
        /* noop */
    }

    window.addEventListener('pointermove', onPlannerWindowPointerMove);
    window.addEventListener('pointerup', stopPanning);
    window.addEventListener('pointercancel', stopPanning);
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
    clampPlannerPan();
}

function syncPlannerViewportSize(
    el: HTMLElement | null = plannerViewport.value,
) {
    if (!el) {
        return;
    }

    const style = window.getComputedStyle(el);
    const padX = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
    const padY = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);

    viewportSize.value = {
        width: Math.max(0, el.clientWidth - padX),
        height: Math.max(0, el.clientHeight - padY),
    };
}

function getFitGardenZoom(): number {
    if (!viewportSize.value.width || !viewportSize.value.height) {
        return 1;
    }

    const pad = PLANNER_FIT_VIEW_PADDING_PX * 2;
    const fitWidth =
        Math.max(1, viewportSize.value.width - pad) / plannerDisplayWidth.value;
    const fitHeight =
        Math.max(1, viewportSize.value.height - pad) /
        plannerDisplayHeight.value;

    return clampFitViewZoom(fitWidth, fitHeight);
}

function ortophotoMinZoom(fitZoom = getFitGardenZoom()): number {
    return Math.max(FIT_VIEW_MIN_ZOOM, fitZoom * ORTOPHOTO_MIN_ZOOM_FACTOR);
}

function clampUserZoom(value: number): number {
    const fitZoom = getFitGardenZoom();
    const snapped = snapPlannerZoomToOnePercent(value);

    if (usesOrtophotoSharpZoom.value) {
        return Math.min(
            fitZoom * ORTOPHOTO_MAX_ZOOM_FACTOR,
            Math.max(ortophotoMinZoom(fitZoom), snapped),
        );
    }

    const minZoom = Math.max(
        FIT_VIEW_MIN_ZOOM,
        fitZoom * ORTOPHOTO_MIN_ZOOM_FACTOR,
    );

    return Math.min(MAX_ZOOM, Math.max(minZoom, snapped));
}

function setZoomAtViewportPoint(
    viewportX: number,
    viewportY: number,
    nextZoom: number,
) {
    const clamped = clampUserZoom(nextZoom);

    if (usesOrtophotoSharpZoom.value) {
        // Ortofotol on kaader fikseeritud ja kaart suumib sisemiselt krundi
        // keskpunkti ümber. Suurendus käib keskelt — pani ei muudeta, et kaart
        // ei nihkuks. clampPlannerPan hoiab kaadri keskel/täidetuna.
        zoom.value = clamped;
        clampPlannerPan();
        refreshPlannerOrtophoto();
        return;
    }

    const scale = plannerVisualScale();
    const gx = (viewportX - panX.value) / scale;
    const gy = (viewportY - panY.value) / scale;
    zoom.value = clamped;
    const nextScale = plannerVisualScale(clamped);
    panX.value = viewportX - gx * nextScale;
    panY.value = viewportY - gy * nextScale;
    clampPlannerPan();
    refreshPlannerOrtophoto();
}

function getFitGardenPan(fitZoomLevel: number): { x: number; y: number } {
    const vw = viewportSize.value.width;
    const vh = viewportSize.value.height;
    const visualZoom = usesOrtophotoSharpZoom.value
        ? plannerVisualScale(fitZoomLevel)
        : fitZoomLevel;
    const scaledW = plannerDisplayWidth.value * visualZoom;
    const scaledH = plannerDisplayHeight.value * visualZoom;

    let x = (vw - scaledW) / 2;
    let y = (vh - scaledH) / 2;

    if (scaledW >= vw) {
        x = Math.min(0, Math.max(vw - scaledW, x));
    }

    if (scaledH >= vh) {
        y = Math.min(0, Math.max(vh - scaledH, y));
    }

    return { x, y };
}

function clampPlannerPan() {
    const vw = viewportSize.value.width;
    const vh = viewportSize.value.height;
    if (!vw || !vh) {
        return;
    }

    const canvas = getPlannerCanvasSize();
    const fitZoom = getFitGardenZoom();
    const atFitZoom = Math.abs(zoom.value - fitZoom) <= 0.001;

    if (!plannerCanvasExceedsViewport() && atFitZoom) {
        const pan = getFitGardenPan(zoom.value);
        panX.value = pan.x;
        panY.value = pan.y;
        return;
    }

    if (canvas.width >= vw) {
        panX.value = Math.min(0, Math.max(vw - canvas.width, panX.value));
    } else {
        panX.value = (vw - canvas.width) / 2;
    }

    if (canvas.height >= vh) {
        panY.value = Math.min(0, Math.max(vh - canvas.height, panY.value));
    } else {
        panY.value = (vh - canvas.height) / 2;
    }
}

function fitGardenToViewport() {
    if (!viewportSize.value.width || !viewportSize.value.height) {
        return;
    }

    const fitZoom = getFitGardenZoom();
    zoom.value = fitZoom;
    const pan = getFitGardenPan(fitZoom);
    panX.value = pan.x;
    panY.value = pan.y;
    refreshPlannerOrtophoto();
}

function plannerPanZoomStyle() {
    if (usesOrtophotoSharpZoom.value) {
        return {
            transform: `translate(${panX.value}px, ${panY.value}px)`,
            transformOrigin: 'top left',
            willChange: 'transform',
        };
    }

    return {
        transform: `translate(${panX.value}px, ${panY.value}px) scale(${zoom.value})`,
        transformOrigin: 'top left',
        willChange: 'transform',
    };
}

/** Ortofotol: hiire/koordinaatide skaala (ühiselt kaardi ja sisuga). */
function plannerVisualScale(zoomLevel = zoom.value): number {
    if (!usesOrtophotoSharpZoom.value) {
        return zoomLevel;
    }

    const fitZoom = getFitGardenZoom();
    return fitZoom > 0.001 ? zoomLevel / fitZoom : 1;
}

function plannerOrtophotoInnerStyle(): Record<string, string> {
    return { width: '100%', height: '100%' };
}

function plannerContentLayerStyle(): Record<string, string> {
    if (!usesOrtophotoSharpZoom.value) {
        return {};
    }
    return {
        transform: `scale(${plannerVisualScale()})`,
        transformOrigin: '50% 50%',
    };
}

function plannerGardenSurfaceFrameStyle(): Record<string, string> {
    const layout = gardenMaskLayout.value;

    if (hasGardenCoordinates.value && showMapBackground.value) {
        return {
            width: `${layout.fullW}px`,
            height: `${layout.fullH}px`,
        };
    }

    return {
        width: `${layout.displayW}px`,
        height: `${layout.displayH}px`,
    };
}

const showManualGardenGrid = computed(
    () => !(hasGardenCoordinates.value && showMapBackground.value),
);

function plannerGardenSurfaceInnerStyle(): Record<string, string> {
    const layout = gardenMaskLayout.value;

    if (hasGardenCoordinates.value && showMapBackground.value) {
        return {
            width: `${layout.fullW}px`,
            height: `${layout.fullH}px`,
            backgroundColor: 'transparent',
            backgroundImage: 'none',
        };
    }

    const minorPx = plannerGridSizePx.value;
    const majorPx = Math.max(
        minorPx,
        Math.round(plannerMajorGridCm.value * plannerCmToPx.value),
    );
    const minorLine = GARDEN_GRID_MINOR_LINE;
    const majorLine = GARDEN_GRID_MAJOR_LINE;

    const style: Record<string, string> = {
        width: `${layout.fullW}px`,
        height: `${layout.fullH}px`,
        backgroundColor: 'rgba(240, 250, 235, 0.98)',
        backgroundImage: [
            `linear-gradient(${minorLine} 1px, transparent 1px)`,
            `linear-gradient(90deg, ${minorLine} 1px, transparent 1px)`,
            `linear-gradient(${majorLine} 1.5px, transparent 1.5px)`,
            `linear-gradient(90deg, ${majorLine} 1.5px, transparent 1.5px)`,
        ].join(', '),
        backgroundSize: [
            `${minorPx}px ${minorPx}px`,
            `${minorPx}px ${minorPx}px`,
            `${majorPx}px ${majorPx}px`,
            `${majorPx}px ${majorPx}px`,
        ].join(', '),
    };

    if (layout.useTightFrame) {
        style.position = 'absolute';
        style.left = `${-layout.offsetX}px`;
        style.top = `${-layout.offsetY}px`;
    }

    if (useGardenShapeClip.value) {
        style.clipPath = `url(#${gardenShapeClipPathId.value})`;
    }

    return style;
}

function clampFitViewZoom(fitWidth: number, fitHeight: number): number {
    const raw = Math.min(fitWidth, fitHeight);
    if (!Number.isFinite(raw) || raw <= 0) {
        return MIN_ZOOM;
    }
    return Math.min(MAX_ZOOM, Math.max(FIT_VIEW_MIN_ZOOM, raw));
}

function applyFitGardenZoom() {
    userAdjustedViewport.value = false;
    fitGardenToViewport();
}

function centerBedInViewport(bed: Bed) {
    if (!viewportSize.value.width || !viewportSize.value.height) return;

    userAdjustedViewport.value = true;
    const fitZoom = getFitGardenZoom();
    zoom.value = clampUserZoom(
        showBedsAsPlannerFootprints.value
            ? fitZoom
            : Math.max(zoom.value, FOCUSED_BED_MIN_ZOOM, fitZoom),
    );

    const applyPan = () => {
        const position = getBedPosition(bed);
        const size = bedCardSize(bed);
        const focusX = position.x + size.width / 2;
        const focusY = showBedsAsPlannerFootprints.value
            ? position.y + size.height / 2
            : position.y + size.height;
        const sharpen = plannerVisualScale();

        panX.value = viewportSize.value.width / 2 - focusX * sharpen;
        panY.value = viewportSize.value.height / 2 - focusY * sharpen;
        clampPlannerPan();
    };

    refreshPlannerOrtophoto();
    nextTick(() => {
        nextTick(applyPan);
    });
}

function runPlannerInitialViewportFit() {
    if (plannerInitialViewportFitDone.value) return;
    const { width, height } = viewportSize.value;
    if (!width || !height) return;
    if (!showPlannerMapCanvas.value) {
        return;
    }

    fitGardenToViewport();
    plannerInitialViewportFitDone.value = true;

    const focusedBed = selectedBed.value;
    if (focusedBed && usesOrtophotoSharpZoom.value) {
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
            fitGardenToViewport();
            const currentFocusedBed = selectedBed.value;
            if (currentFocusedBed && usesOrtophotoSharpZoom.value) {
                centerBedInViewport(currentFocusedBed);
            }
        });
    });
}

watch(
    () => props.gardenPlan.id,
    () => {
        userAdjustedViewport.value = false;
        plannerInitialViewportFitDone.value = false;
        nextTick(() => runPlannerInitialViewportFit());
    },
);

function changeZoom(delta: number) {
    const vp = plannerViewport.value;
    if (!vp) {
        return;
    }

    userAdjustedViewport.value = true;
    const rect = vp.getBoundingClientRect();
    setZoomAtViewportPoint(rect.width / 2, rect.height / 2, zoom.value + delta);
}

function onPlannerWheel(event: WheelEvent) {
    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-ui-overlay="true"]')) return;

    const vp = plannerViewport.value;
    if (!vp) {
        return;
    }

    event.preventDefault();
    const delta = wheelZoomDelta(event);
    const rect = vp.getBoundingClientRect();
    const nextZoom = zoom.value + delta;

    if (Math.abs(nextZoom - zoom.value) < 0.001) {
        return;
    }

    userAdjustedViewport.value = true;
    setZoomAtViewportPoint(
        event.clientX - rect.left,
        event.clientY - rect.top,
        nextZoom,
    );
}

function onPlannerGardenShapeMaskUpdate(mask: number[][]) {
    gardenForm.shape_mask = mask;
    gardenForm.clearErrors('shape_mask');

    const bounds = getShapeMaskActiveCellBounds(mask);
    if (!bounds) {
        return;
    }

    const cell = plannerShapeEditorCellCm.value;
    const wCm = (bounds.maxCol - bounds.minCol + 1) * cell;
    const hCm = (bounds.maxRow - bounds.minRow + 1) * cell;

    skipShapeMaskResizeFromDimensions.value = true;
    gardenForm.widthMeters = wCm / 100;
    gardenForm.heightMeters = hCm / 100;
    nextTick(() => {
        skipShapeMaskResizeFromDimensions.value = false;
    });
}

function setGardenPlannerShapeCellSize(cm: number) {
    if (gardenPlannerShapeCellCm.value === cm) {
        return;
    }

    gardenPlannerShapeCellCm.value = cm;
    const { widthCm, heightCm } = gardenShapeMaskCmFromForm(
        gardenForm.widthMeters,
        gardenForm.heightMeters,
    );

    skipShapeMaskResizeFromDimensions.value = true;
    gardenForm.shape_mask = resizeGardenShapeMask(
        gardenForm.shape_mask,
        widthCm,
        heightCm,
        cm,
    );
    nextTick(() => {
        skipShapeMaskResizeFromDimensions.value = false;
    });
}

function saveGardenPlan(options?: {
    requireOrtophotoSetup?: boolean;
    requireManualDimensions?: boolean;
}) {
    if (options?.requireManualDimensions) {
        const mask = gardenManualShapeMask.value;
        const hasShape = mask.some((row) => row.includes(1));
        if (hasShape) {
            let minR = Infinity;
            let maxR = -Infinity;
            let minC = Infinity;
            let maxC = -Infinity;

            mask.forEach((row, r) =>
                row.forEach((v, c) => {
                    if (v === 1) {
                        minR = Math.min(minR, r);
                        maxR = Math.max(maxR, r);
                        minC = Math.min(minC, c);
                        maxC = Math.max(maxC, c);
                    }
                }),
            );

            const wCm = (maxC - minC + 1) * gardenManualCellSizeCm.value;
            const hCm = (maxR - minR + 1) * gardenManualCellSizeCm.value;
            const trimmedMask = mask
                .slice(minR, maxR + 1)
                .map((row) => row.slice(minC, maxC + 1));

            gardenForm.widthMeters = wCm / 100;
            gardenForm.heightMeters = hCm / 100;
            gardenForm.shape_mask = resizeGardenShapeMask(
                trimmedMask,
                wCm,
                hCm,
                gardenManualCellSizeCm.value,
            );
        }

        if (!gardenManualSetupReady.value) {
            gardenDimensionsMessage.value = hasShape
                ? 'Sisesta laius ja sügavus meetrites.'
                : 'Märgi vähemalt üks ruut aia kuju määramiseks.';
            return;
        }
        gardenForm.center_lat = null;
        gardenForm.center_lng = null;
    }

    if (options?.requireOrtophotoSetup && !gardenOrtophotoSetupReady.value) {
        gardenDimensionsMessage.value =
            'Vali asukoht ja sisesta aia laius ning sügavus meetrites.';
        return;
    }

    gardenForm
        .transform((data) => ({
            name: data.name.trim() || 'Minu aed',
            width: Math.round(Number(data.widthMeters || 0) * 100),
            height: Math.round(Number(data.heightMeters || 0) * 100),
            shape_mask: data.shape_mask,
            shape_mask_cell_cm: options?.requireManualDimensions
                ? gardenManualCellSizeCm.value
                : !hasGardenCoordinates.value
                  ? gardenPlannerShapeCellCm.value
                  : shapeMaskCellCm.value,
            center_lat:
                !hasGardenCoordinates.value &&
                isManualGardenWithoutGeo.value &&
                !gardenFormLocationChosen.value
                    ? null
                    : data.center_lat,
            center_lng:
                !hasGardenCoordinates.value &&
                isManualGardenWithoutGeo.value &&
                !gardenFormLocationChosen.value
                    ? null
                    : data.center_lng,
            boundary_polygon: null,
        }))
        .put(`/garden-plans/${props.gardenPlan.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const plan = page.props.gardenPlan as GardenPlan | undefined;
                if (!plan) return;
                gardenPlannerShapeCellCm.value = planShapeMaskCellCm(plan);
                gardenForm.defaults({
                    name: plan.name,
                    widthMeters: plan.width / 100,
                    heightMeters: plan.height / 100,
                    shape_mask: resizeGardenShapeMask(
                        plan.shape_mask,
                        plan.width,
                        plan.height,
                        planShapeMaskCellCm(plan),
                    ),
                    center_lat: plan.center_lat,
                    center_lng: plan.center_lng,
                });
                gardenSetupMode.value = null;
                if (
                    options?.requireManualDimensions &&
                    plan.center_lat == null &&
                    plan.center_lng == null
                ) {
                    writeManualLayoutActive(plan.id, true);
                    manualLayoutActive.value = true;
                } else if (plan.center_lat != null && plan.center_lng != null) {
                    writeManualLayoutActive(plan.id, false);
                    manualLayoutActive.value = false;
                }
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
                                    v-if="
                                        showOnboardingHint && canPlaceBedsOnMap
                                    "
                                    class="rounded-xl border border-primary/20 bg-primary/5 p-5 shadow-sm"
                                >
                                    <div
                                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div class="max-w-2xl">
                                            <p
                                                class="text-base font-semibold text-foreground"
                                            >
                                                Lisa esimene peenar
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-6 text-muted-foreground"
                                            >
                                                Lisa esimene peenar: nimeta,
                                                joonista kuju ja paiguta see
                                                aiaplaanile.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                                            @click="startNewBedOnMap"
                                        >
                                            Lisa peenar kaardile
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        gardenLooksOversized &&
                                        hasGardenCoordinates
                                    "
                                    class="rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm dark:border-amber-500/40 dark:bg-amber-950/40 dark:text-amber-100"
                                >
                                    <p class="font-semibold">
                                        Aia mõõdud võivad olla valed
                                    </p>
                                    <p class="mt-1 leading-6">
                                        Salvestatud mõõdud (
                                        {{ gardenDimensionLabel }}) on liiga
                                        suured. Muuda mõõte või loo uus aiaplaan
                                        õige asukoha ja suurusega.
                                    </p>
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
                                                            plannerBedListOpen
                                                                ? 'text-primary'
                                                                : ''
                                                        "
                                                        @click="
                                                            togglePlannerBedList()
                                                        "
                                                    >
                                                        {{ plannerBeds.length }}
                                                        peenart
                                                        <span
                                                            class="material-symbols-outlined text-sm transition-transform"
                                                            :class="
                                                                plannerBedListOpen
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
                                                </div>
                                            </div>
                                            <div
                                                class="flex shrink-0 flex-wrap items-center justify-end gap-2"
                                            >
                                                <div
                                                    v-if="showPlannerMapCanvas"
                                                    class="inline-flex items-center gap-0.5 rounded-full bg-muted/45 p-1 ring-1 ring-border/70"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-foreground transition hover:bg-background"
                                                        @click="
                                                            changeZoom(
                                                                -plannerZoomStepDelta(),
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
                                                            applyFitGardenZoom
                                                        "
                                                    >
                                                        {{ zoomPercent }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-foreground transition hover:bg-background"
                                                        @click="
                                                            changeZoom(
                                                                plannerZoomStepDelta(),
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            class="material-symbols-outlined text-base"
                                                            >add</span
                                                        >
                                                    </button>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 text-sm font-semibold text-primary transition hover:bg-primary/15 sm:h-10 sm:px-4"
                                                    aria-label="Muuda aeda"
                                                    @click="
                                                        openGardenPlanEditor
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined shrink-0 text-xl leading-none"
                                                        >edit</span
                                                    >
                                                    <span
                                                        class="hidden sm:inline"
                                                        >Muuda</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-full border border-border/70 bg-card px-3 text-sm font-semibold text-foreground shadow-sm ring-1 ring-border/70 transition hover:bg-muted sm:h-10 sm:px-4"
                                                    aria-label="Kustuta aiaplaan"
                                                    @click="deleteGardenPlan"
                                                >
                                                    <span
                                                        class="material-symbols-outlined shrink-0 text-xl leading-none"
                                                        >delete</span
                                                    >
                                                    <span
                                                        class="hidden sm:inline"
                                                        >Kustuta</span
                                                    >
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="plannerBedListOpen"
                                    class="mb-3 hidden rounded-2xl bg-background/60 p-2 ring-1 ring-border/70 md:block"
                                >
                                    <div
                                        class="mb-1 flex items-center justify-between px-2 py-1"
                                    >
                                        <p
                                            class="text-xs font-semibold text-foreground"
                                        >
                                            Peenrad kaardil
                                        </p>
                                        <button
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            @click="plannerBedListOpen = false"
                                        >
                                            <span
                                                class="material-symbols-outlined text-sm"
                                                >close</span
                                            >
                                        </button>
                                    </div>

                                    <div
                                        v-if="plannerBedListOpen"
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
                                                @click="focusBedDetails(bed.id)"
                                            >
                                                <span
                                                    class="block min-w-0 truncate text-sm font-semibold text-foreground"
                                                    >{{ bed.name }}</span
                                                >
                                                <span
                                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                                    >{{
                                                        bedPlantSummaryLine(bed)
                                                    }}</span
                                                >
                                                <span
                                                    v-if="bed.location"
                                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                                    >{{ bed.location }}</span
                                                >
                                                <span
                                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                                    >{{
                                                        bedDimensionsLabel(bed)
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
                                                class="mr-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                :aria-label="`Kustuta ${bed.name}`"
                                                @click.stop="
                                                    deleteBed(bed.id, bed.name)
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
                                </div>

                                <div
                                    v-if="
                                        plannerControlsOpen &&
                                        !needsGardenInitialSetup
                                    "
                                    class="mb-4 max-h-[min(50vh,28rem)] space-y-3 overflow-y-auto overscroll-y-contain rounded-[1.5rem] border border-border/70 bg-background/95 p-3 shadow-sm md:max-h-[min(55vh,32rem)]"
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
                                            <div
                                                v-if="
                                                    !hasGardenCoordinates &&
                                                    !isManualGardenWithoutGeo
                                                "
                                                class="mb-4 space-y-3"
                                            >
                                                <p
                                                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                >
                                                    Kuidas aed kaardile tuleb?
                                                </p>
                                                <div
                                                    class="grid gap-2 sm:grid-cols-2"
                                                >
                                                    <button
                                                        type="button"
                                                        :class="
                                                            gardenSetupChoiceClass(
                                                                gardenSetupMode ===
                                                                    'ortophoto',
                                                            )
                                                        "
                                                        @click="
                                                            chooseGardenSetupMode(
                                                                'ortophoto',
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            class="text-sm font-semibold text-foreground"
                                                            >Ortofoto</span
                                                        >
                                                        <span
                                                            class="text-xs leading-5 text-muted-foreground"
                                                            >Aadress või asukoht
                                                            + mõõdud
                                                            meetrites.</span
                                                        >
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :class="
                                                            gardenSetupChoiceClass(
                                                                gardenSetupMode ===
                                                                    'manual',
                                                            )
                                                        "
                                                        @click="
                                                            chooseGardenSetupMode(
                                                                'manual',
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            class="text-sm font-semibold text-foreground"
                                                            >Käsitsi</span
                                                        >
                                                        <span
                                                            class="text-xs leading-5 text-muted-foreground"
                                                            >Sisesta laius ja
                                                            sügavus meetrites +
                                                            asukoht.</span
                                                        >
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    !hideGardenQuickPresets &&
                                                    !plannerControlsOpen
                                                "
                                                class="mb-3"
                                            >
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
                                                :class="
                                                    gardenOrtophotoSetupActive
                                                        ? 'grid gap-3'
                                                        : 'grid gap-3 sm:grid-cols-3'
                                                "
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
                                                    v-if="
                                                        !gardenOrtophotoSetupActive
                                                    "
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
                                                    v-if="
                                                        !gardenOrtophotoSetupActive
                                                    "
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
                                                    isManualGardenWithoutGeo &&
                                                    !showGardenLocationSection
                                                "
                                                class="mt-4"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-card/85 px-4 py-2 text-sm font-semibold text-foreground transition hover:bg-muted"
                                                    @click="
                                                        openGardenLocationEditor
                                                    "
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-base text-primary"
                                                        >location_on</span
                                                    >
                                                    Lisa asukoht (valikuline)
                                                </button>
                                                <p
                                                    class="mt-2 text-xs leading-5 text-muted-foreground"
                                                >
                                                    Käsitsi sisestatud aial pole
                                                    asukohta vaja. Saad lisada
                                                    hiljem ortofoto jaoks.
                                                </p>
                                            </div>

                                            <div
                                                v-if="showGardenLocationSection"
                                                class="mt-4"
                                            >
                                                <div
                                                    class="flex flex-wrap items-center justify-between gap-2"
                                                >
                                                    <p
                                                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                    >
                                                        Aia asukoht
                                                    </p>
                                                    <button
                                                        v-if="
                                                            isManualGardenWithoutGeo &&
                                                            !hasGardenCoordinates
                                                        "
                                                        type="button"
                                                        class="text-xs font-semibold text-muted-foreground underline-offset-2 transition hover:text-foreground hover:underline"
                                                        @click="
                                                            dismissGardenLocationEditor
                                                        "
                                                    >
                                                        Eemalda
                                                    </button>
                                                </div>
                                                <p
                                                    class="mt-1 text-sm leading-6 text-muted-foreground"
                                                >
                                                    <template
                                                        v-if="
                                                            gardenOrtophotoSetupActive
                                                        "
                                                    >
                                                        Otsi aadress või kasuta
                                                        geolokatsiooni, seejärel
                                                        sisesta aia mõõdud
                                                        meetrites.
                                                    </template>
                                                    <template
                                                        v-else-if="
                                                            showGardenCoordinateInputs
                                                        "
                                                    >
                                                        Otsi aadressi või kasuta
                                                        geolokatsiooni — mõõdud
                                                        ja koordinaadid täituvad
                                                        automaatselt.
                                                    </template>
                                                    <template v-else>
                                                        Otsi aadressi või kasuta
                                                        geolokatsiooni.
                                                        Koordinaadid ilmuvad
                                                        pärast asukoha valimist.
                                                    </template>
                                                </p>
                                                <div
                                                    class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start"
                                                >
                                                    <div
                                                        class="relative min-w-0 flex-1"
                                                    >
                                                        <div
                                                            class="flex items-center rounded-2xl border border-border/70 bg-card/85 px-3 py-2 ring-offset-background focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                                                        >
                                                            <input
                                                                v-model="
                                                                    addressSearchQuery
                                                                "
                                                                type="search"
                                                                autocomplete="off"
                                                                class="min-w-0 flex-1 bg-transparent text-sm font-medium text-foreground outline-none"
                                                                placeholder="Otsi aadressi..."
                                                                @input="
                                                                    onGardenAddressSearchInput
                                                                "
                                                                @focus="
                                                                    addressSearchOpen =
                                                                        addressSearchResults.length >
                                                                            0 ||
                                                                        addressSearchError
                                                                "
                                                            />
                                                            <span
                                                                v-if="
                                                                    addressSearchLoading
                                                                "
                                                                class="material-symbols-outlined shrink-0 animate-spin text-base text-muted-foreground"
                                                                aria-hidden="true"
                                                                >progress_activity</span
                                                            >
                                                        </div>
                                                        <div
                                                            v-if="
                                                                addressSearchOpen &&
                                                                (addressSearchResults.length >
                                                                    0 ||
                                                                    addressSearchLoading ||
                                                                    addressSearchError)
                                                            "
                                                            class="absolute top-full right-0 left-0 z-30 mt-1 overflow-hidden rounded-xl border border-border/80 bg-card shadow-lg"
                                                        >
                                                            <div
                                                                v-if="
                                                                    addressSearchLoading
                                                                "
                                                                class="px-3 py-2.5 text-sm text-muted-foreground"
                                                            >
                                                                Otsin...
                                                            </div>
                                                            <div
                                                                v-else-if="
                                                                    addressSearchError
                                                                "
                                                                class="px-3 py-2.5 text-sm text-muted-foreground"
                                                            >
                                                                Aadress ei
                                                                leitud
                                                            </div>
                                                            <ul
                                                                v-else
                                                                class="max-h-52 overflow-y-auto py-1"
                                                            >
                                                                <li
                                                                    v-for="result in addressSearchResults"
                                                                    :key="
                                                                        result.id
                                                                    "
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="w-full px-3 py-2.5 text-left transition hover:bg-muted"
                                                                        @mousedown.prevent="
                                                                            selectGardenAddress(
                                                                                result,
                                                                            )
                                                                        "
                                                                    >
                                                                        <span
                                                                            class="block text-sm font-medium text-foreground"
                                                                            >{{
                                                                                result.label
                                                                            }}</span
                                                                        >
                                                                        <span
                                                                            v-if="
                                                                                result.county
                                                                            "
                                                                            class="mt-0.5 block text-xs text-muted-foreground"
                                                                            >{{
                                                                                result.county
                                                                            }}</span
                                                                        >
                                                                    </button>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-2xl border border-border/70 bg-card/85 px-3 text-sm font-semibold text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60 sm:h-[42px]"
                                                        :disabled="
                                                            geolocationLoading
                                                        "
                                                        @click="
                                                            requestGardenGeolocation
                                                        "
                                                    >
                                                        <span
                                                            v-if="
                                                                geolocationLoading
                                                            "
                                                            class="material-symbols-outlined animate-spin text-base"
                                                            >progress_activity</span
                                                        >
                                                        <span v-else>📍</span>
                                                        <span
                                                            >Minu asukoht</span
                                                        >
                                                    </button>
                                                </div>
                                                <p
                                                    v-if="
                                                        selectedGardenAddressLabel
                                                    "
                                                    class="mt-2 text-xs leading-5 text-muted-foreground"
                                                >
                                                    Valitud:
                                                    {{
                                                        selectedGardenAddressLabel
                                                    }}
                                                </p>
                                                <p
                                                    v-if="geolocationFailed"
                                                    class="mt-2 text-sm text-red-600"
                                                >
                                                    Asukoht pole saadaval
                                                </p>
                                                <p
                                                    v-if="
                                                        gardenDimensionsLoading
                                                    "
                                                    class="mt-2 text-xs text-muted-foreground"
                                                >
                                                    Laen andmeid…
                                                </p>
                                                <p
                                                    v-else-if="
                                                        gardenDimensionsMessage
                                                    "
                                                    class="mt-2 text-xs leading-5 text-muted-foreground"
                                                >
                                                    {{
                                                        gardenDimensionsMessage
                                                    }}
                                                </p>
                                                <div
                                                    v-if="
                                                        !gardenOrtophotoSetupActive &&
                                                        showGardenCoordinateInputs
                                                    "
                                                    class="mt-3 grid gap-3 sm:grid-cols-2"
                                                >
                                                    <label
                                                        class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                                                    >
                                                        <span
                                                            class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                            >Laiuskraad</span
                                                        >
                                                        <input
                                                            :value="
                                                                coordinateInputValue(
                                                                    gardenForm.center_lat,
                                                                )
                                                            "
                                                            type="number"
                                                            step="any"
                                                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                                                            placeholder="—"
                                                            @input="
                                                                clearGardenAddressSelectionFeedback();
                                                                gardenForm.center_lat =
                                                                    parseCoordinateInput(
                                                                        (
                                                                            $event.target as HTMLInputElement
                                                                        ).value,
                                                                    );
                                                                gardenFormLocationChosen.value = true;
                                                                gardenForm.clearErrors(
                                                                    'center_lat',
                                                                );
                                                                gardenForm.clearErrors(
                                                                    'center_lng',
                                                                );
                                                            "
                                                        />
                                                    </label>
                                                    <label
                                                        class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                                                    >
                                                        <span
                                                            class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                            >Pikkuskraad</span
                                                        >
                                                        <input
                                                            :value="
                                                                coordinateInputValue(
                                                                    gardenForm.center_lng,
                                                                )
                                                            "
                                                            type="number"
                                                            step="any"
                                                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                                                            placeholder="—"
                                                            @input="
                                                                clearGardenAddressSelectionFeedback();
                                                                gardenForm.center_lng =
                                                                    parseCoordinateInput(
                                                                        (
                                                                            $event.target as HTMLInputElement
                                                                        ).value,
                                                                    );
                                                                gardenFormLocationChosen.value = true;
                                                                gardenForm.clearErrors(
                                                                    'center_lat',
                                                                );
                                                                gardenForm.clearErrors(
                                                                    'center_lng',
                                                                );
                                                            "
                                                        />
                                                    </label>
                                                </div>
                                                <p
                                                    v-if="
                                                        (
                                                            gardenForm.errors as Record<
                                                                string,
                                                                string
                                                            >
                                                        ).center_lat ||
                                                        (
                                                            gardenForm.errors as Record<
                                                                string,
                                                                string
                                                            >
                                                        ).center_lng
                                                    "
                                                    class="mt-2 text-sm text-red-600"
                                                >
                                                    {{
                                                        (
                                                            gardenForm.errors as Record<
                                                                string,
                                                                string
                                                            >
                                                        ).center_lat ||
                                                        (
                                                            gardenForm.errors as Record<
                                                                string,
                                                                string
                                                            >
                                                        ).center_lng
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                v-if="
                                                    !hasGardenCoordinates &&
                                                    gardenSetupMode === 'manual'
                                                "
                                                class="mt-3"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center justify-center rounded-full border border-primary/20 bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                                                    :disabled="
                                                        gardenForm.processing ||
                                                        !gardenManualSetupReady
                                                    "
                                                    @click="
                                                        saveGardenPlan({
                                                            requireManualDimensions: true,
                                                        })
                                                    "
                                                >
                                                    Salvesta mõõdud ja ava kaart
                                                </button>
                                            </div>

                                            <div
                                                v-if="
                                                    !gardenOrtophotoSetupActive
                                                "
                                                class="mt-4"
                                            >
                                                <p
                                                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                >
                                                    Aia kuju
                                                </p>
                                                <p
                                                    class="mt-1 text-sm leading-6 text-muted-foreground"
                                                >
                                                    Klõpsa või lohista ruute.
                                                    Ruudustik laieneb
                                                    automaatselt, kui joonistad
                                                    serva.
                                                </p>
                                                <div
                                                    v-if="!hasGardenCoordinates"
                                                    class="mt-3"
                                                >
                                                    <p
                                                        class="mb-2 text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                    >
                                                        Ruudu suurus
                                                    </p>
                                                    <div
                                                        class="flex flex-wrap gap-1"
                                                    >
                                                        <button
                                                            v-for="size in [
                                                                25, 50, 100,
                                                                200, 500, 1000,
                                                            ]"
                                                            :key="size"
                                                            type="button"
                                                            class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                                            :class="
                                                                gardenPlannerShapeCellCm ===
                                                                size
                                                                    ? 'border-primary/30 bg-primary/10 text-primary'
                                                                    : 'border-border/70 bg-card text-foreground hover:bg-muted'
                                                            "
                                                            @click="
                                                                setGardenPlannerShapeCellSize(
                                                                    size,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                size >= 100
                                                                    ? size /
                                                                          100 +
                                                                      ' m'
                                                                    : size +
                                                                      ' cm'
                                                            }}
                                                        </button>
                                                    </div>
                                                </div>
                                                <div
                                                    class="mt-3 overflow-x-auto rounded-[1.15rem] bg-background/80 p-3 ring-1 ring-border/70"
                                                >
                                                    <GardenShapeEditor
                                                        :model-value="
                                                            gardenForm.shape_mask
                                                        "
                                                        :cell-size-cm="
                                                            plannerShapeEditorCellCm
                                                        "
                                                        @update:model-value="
                                                            onPlannerGardenShapeMaskUpdate
                                                        "
                                                    />
                                                </div>
                                                <p
                                                    class="mt-2 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        gardenShapeMaskEditorCols
                                                    }}
                                                    ×
                                                    {{
                                                        gardenShapeMaskEditorRows
                                                    }}
                                                    ruutu ({{
                                                        gardenDimensionLabel
                                                    }})
                                                </p>
                                                <p
                                                    v-if="
                                                        (
                                                            gardenForm.errors as Record<
                                                                string,
                                                                string
                                                            >
                                                        ).shape_mask
                                                    "
                                                    class="mt-2 text-sm text-red-600"
                                                >
                                                    {{
                                                        (
                                                            gardenForm.errors as Record<
                                                                string,
                                                                string
                                                            >
                                                        ).shape_mask
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                v-if="
                                                    gardenForm.errors.name ||
                                                    gardenFormDimensionErrors.width ||
                                                    gardenFormDimensionErrors.height
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
                                                v-if="
                                                    !gardenOrtophotoSetupActive
                                                "
                                                class="mt-3 flex flex-wrap items-center gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/15"
                                                    :disabled="
                                                        gardenForm.processing
                                                    "
                                                    @click="
                                                        () => saveGardenPlan()
                                                    "
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
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <MapGardenSetupSection
                                    v-if="showGardenSetupSection"
                                    :setup-mode="gardenSetupMode"
                                    :address-search-query="addressSearchQuery"
                                    :address-search-results="
                                        addressSearchResults
                                    "
                                    :address-search-loading="
                                        addressSearchLoading
                                    "
                                    :address-search-error="addressSearchError"
                                    :address-search-open="addressSearchOpen"
                                    :geolocation-loading="geolocationLoading"
                                    :garden-dimensions-message="
                                        gardenDimensionsMessage
                                    "
                                    :width-meters="gardenForm.widthMeters"
                                    :height-meters="gardenForm.heightMeters"
                                    :form-processing="gardenForm.processing"
                                    :ortophoto-setup-ready="
                                        gardenOrtophotoSetupReady
                                    "
                                    :manual-setup-ready="gardenManualSetupReady"
                                    :manual-shape-mask="gardenManualShapeMask"
                                    :manual-cell-size-cm="
                                        gardenManualCellSizeCm
                                    "
                                    @update:manual-shape-mask="
                                        gardenManualShapeMask = $event
                                    "
                                    @update:manual-cell-size-cm="
                                        gardenManualCellSizeCm = $event
                                    "
                                    @choose-mode="chooseGardenSetupMode"
                                    @reset-mode="gardenSetupMode = null"
                                    @update:address-search-query="
                                        addressSearchQuery = $event
                                    "
                                    @address-search-input="
                                        onGardenAddressSearchInput
                                    "
                                    @address-search-focus="
                                        addressSearchOpen =
                                            addressSearchResults.length > 0 ||
                                            addressSearchError
                                    "
                                    @select-address="selectGardenAddress"
                                    @request-geolocation="
                                        requestGardenGeolocation
                                    "
                                    @update:width-meters="
                                        gardenForm.widthMeters = $event
                                    "
                                    @update:height-meters="
                                        gardenForm.heightMeters = $event
                                    "
                                    @save-ortophoto="
                                        saveGardenPlan({
                                            requireOrtophotoSetup: true,
                                        })
                                    "
                                    @save-manual="
                                        saveGardenPlan({
                                            requireManualDimensions: true,
                                        })
                                    "
                                />

                                <div
                                    v-else-if="showPlannerFiltersEmptyState"
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
                                        Vaata üle kihid ja otsing, et näha jälle
                                        kõiki peenraid.
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
                                            </div>

                                            <div
                                                class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-y-contain px-2 pt-1.5 pb-36"
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
                                                            Lemmikpeenraid pole
                                                            veel valitud
                                                        </p>
                                                        <p
                                                            class="max-w-[16rem] text-xs leading-relaxed text-muted-foreground"
                                                        >
                                                            Puuduta südant
                                                            peenra juures, et
                                                            see kuvataks siin.
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
                                                            muuta või lisa uus
                                                            peenar plussnupust.
                                                        </p>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="relative hidden min-h-0 min-w-0 md:block"
                                    >
                                        <div
                                            ref="plannerViewport"
                                            class="relative h-[min(62vh,620px)] w-full max-w-full min-w-0 touch-none overflow-hidden rounded-[1.5rem] border bg-[linear-gradient(180deg,rgba(251,248,241,0.98),rgba(241,247,235,0.98))] p-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.8),0_14px_34px_rgba(47,67,44,0.10)] transition sm:p-3 md:h-[min(72vh,920px)] lg:h-[min(calc(100dvh-11rem),920px)] dark:bg-[linear-gradient(180deg,rgba(30,38,32,0.98),rgba(22,29,24,0.98))]"
                                            :class="[
                                                plannerViewportCursorClass,
                                                isLayoutEditing
                                                    ? 'border-primary/25 ring-2 ring-primary/10'
                                                    : 'border-border/80',
                                            ]"
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
                                                class="absolute top-0 left-0"
                                                :style="plannerPanZoomStyle()"
                                            >
                                                <div
                                                    class="flex flex-col items-start"
                                                >
                                                    <div
                                                        class="relative overflow-hidden rounded-none"
                                                        :class="
                                                            showMapBackground
                                                                ? 'border border-emerald-900/10 bg-transparent dark:border-emerald-200/15'
                                                                : useGardenShapeClip ||
                                                                    gardenMaskLayout.useTightFrame
                                                                  ? 'border-0 bg-transparent'
                                                                  : 'border border-emerald-900/10 bg-emerald-50/80 dark:border-emerald-200/15 dark:bg-emerald-950/35'
                                                        "
                                                        :style="
                                                            plannerGardenSurfaceFrameStyle()
                                                        "
                                                    >
                                                        <div
                                                            class="origin-top-left"
                                                            :class="
                                                                gardenMaskLayout.useTightFrame
                                                                    ? 'relative'
                                                                    : 'absolute inset-0'
                                                            "
                                                            :style="
                                                                plannerGardenSurfaceInnerStyle()
                                                            "
                                                        >
                                                            <svg
                                                                v-if="
                                                                    useGardenShapeClip
                                                                "
                                                                class="pointer-events-none absolute size-0 overflow-hidden"
                                                                aria-hidden="true"
                                                            >
                                                                <defs>
                                                                    <clipPath
                                                                        :id="
                                                                            gardenShapeClipPathId
                                                                        "
                                                                        clipPathUnits="userSpaceOnUse"
                                                                    >
                                                                        <rect
                                                                            v-for="(
                                                                                rect,
                                                                                rectIndex
                                                                            ) in gardenShapeMaskClipRects"
                                                                            :key="`garden-clip-${rectIndex}`"
                                                                            :x="
                                                                                rect.x
                                                                            "
                                                                            :y="
                                                                                rect.y
                                                                            "
                                                                            :width="
                                                                                rect.w
                                                                            "
                                                                            :height="
                                                                                rect.h
                                                                            "
                                                                        />
                                                                    </clipPath>
                                                                </defs>
                                                            </svg>
                                                            <div
                                                                class="absolute inset-0 origin-top-left"
                                                                :style="
                                                                    plannerOrtophotoInnerStyle()
                                                                "
                                                            >
                                                                <GardenMapBackground
                                                                    v-if="
                                                                        hasGardenCoordinates
                                                                    "
                                                                    class="transition-opacity duration-150"
                                                                    :class="
                                                                        showMapBackground
                                                                            ? ''
                                                                            : 'pointer-events-none opacity-0'
                                                                    "
                                                                    ref="plannerMapBgRef"
                                                                    :center-lat="
                                                                        Number(
                                                                            gardenPlan.center_lat,
                                                                        )
                                                                    "
                                                                    :center-lng="
                                                                        Number(
                                                                            gardenPlan.center_lng,
                                                                        )
                                                                    "
                                                                    :width-cm="
                                                                        gardenWidthCm
                                                                    "
                                                                    :height-cm="
                                                                        gardenHeightCm
                                                                    "
                                                                    :focus-anchor-lat="
                                                                        Number(
                                                                            gardenPlan.center_lat,
                                                                        )
                                                                    "
                                                                    :focus-anchor-lng="
                                                                        Number(
                                                                            gardenPlan.center_lng,
                                                                        )
                                                                    "
                                                                    :focus-span-meters="
                                                                        plannerOrtophotoFocusSpanMeters
                                                                    "
                                                                    :planner-zoom="
                                                                        zoom
                                                                    "
                                                                    :fit-zoom="
                                                                        fitGardenZoom
                                                                    "
                                                                    fractional-zoom
                                                                    @leaflet-zoom-change="
                                                                        onPlannerLeafletZoomChange
                                                                    "
                                                                />
                                                                <div
                                                                    class="absolute inset-0"
                                                                    :style="
                                                                        plannerContentLayerStyle()
                                                                    "
                                                                    @click="
                                                                        onPlannerCanvasClick
                                                                    "
                                                                >
                                                                    <template
                                                                        v-if="
                                                                            showBedsAsPlannerFootprints
                                                                        "
                                                                    >
                                                                        <div
                                                                            v-for="bed in plannerBeds"
                                                                            :key="
                                                                                bed.id
                                                                            "
                                                                            data-bed-footprint="true"
                                                                            class="bed-footprint-interactive absolute touch-none overflow-visible rounded-none transition"
                                                                            :class="[
                                                                                draggingBedId ===
                                                                                bed.id
                                                                                    ? 'z-30 cursor-grabbing'
                                                                                    : isBedCardVisible(
                                                                                            bed.id,
                                                                                        )
                                                                                      ? 'z-20 cursor-pointer'
                                                                                      : 'z-10 cursor-pointer hover:z-20',
                                                                            ]"
                                                                            :style="
                                                                                bedFootprintWrapperStyle(
                                                                                    bed,
                                                                                )
                                                                            "
                                                                            @pointerdown="
                                                                                startDragging(
                                                                                    bed,
                                                                                    $event,
                                                                                )
                                                                            "
                                                                            @click.stop="
                                                                                toggleBedCard(
                                                                                    bed.id,
                                                                                )
                                                                            "
                                                                            @pointerenter="
                                                                                onBedPointerEnter(
                                                                                    bed.id,
                                                                                )
                                                                            "
                                                                            @pointerleave="
                                                                                onBedPointerLeave(
                                                                                    bed.id,
                                                                                )
                                                                            "
                                                                        >
                                                                            <div
                                                                                v-for="(
                                                                                    cell,
                                                                                    cellIndex
                                                                                ) in getBedFootprintCells(
                                                                                    bed,
                                                                                )"
                                                                                :key="`${bed.id}-cell-${cellIndex}`"
                                                                                class="absolute overflow-hidden rounded-none"
                                                                                :class="
                                                                                    cell.kind ===
                                                                                    'walkway'
                                                                                        ? 'bg-stone-300/35 dark:bg-stone-600/28'
                                                                                        : ''
                                                                                "
                                                                                :style="{
                                                                                    left: `${cell.left}px`,
                                                                                    top: `${cell.top}px`,
                                                                                    width: `${cell.width}px`,
                                                                                    height: `${cell.height}px`,
                                                                                }"
                                                                                aria-hidden="true"
                                                                            >
                                                                                <img
                                                                                    v-if="
                                                                                        cell.image_url &&
                                                                                        cell.kind ===
                                                                                            'plantable'
                                                                                    "
                                                                                    :src="
                                                                                        cell.image_url
                                                                                    "
                                                                                    alt=""
                                                                                    class="size-full object-cover"
                                                                                />
                                                                                <div
                                                                                    v-else-if="
                                                                                        cell.kind ===
                                                                                        'plantable'
                                                                                    "
                                                                                    class="flex size-full items-center justify-center bg-emerald-50/40 dark:bg-emerald-950/20"
                                                                                >
                                                                                    <span
                                                                                        class="material-symbols-outlined leading-none text-emerald-800/45 dark:text-emerald-100/45"
                                                                                        :style="{
                                                                                            fontSize: `clamp(8px, ${Math.min(cell.width, cell.height) * 0.42}px, 20px)`,
                                                                                        }"
                                                                                        aria-hidden="true"
                                                                                        >potted_plant</span
                                                                                    >
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </template>
                                                                    <template
                                                                        v-else
                                                                    >
                                                                        <div
                                                                            v-for="bed in plannerBeds"
                                                                            :key="
                                                                                bed.id
                                                                            "
                                                                            data-bed-pin="true"
                                                                            class="absolute touch-none"
                                                                            :class="[
                                                                                draggingBedId ===
                                                                                bed.id
                                                                                    ? 'z-30 cursor-grabbing'
                                                                                    : 'z-10 cursor-pointer hover:z-20',
                                                                            ]"
                                                                            :style="
                                                                                bedPinWrapperStyle(
                                                                                    bed,
                                                                                )
                                                                            "
                                                                            @pointerdown="
                                                                                startDragging(
                                                                                    bed,
                                                                                    $event,
                                                                                )
                                                                            "
                                                                            @click.stop="
                                                                                toggleBedCard(
                                                                                    bed.id,
                                                                                )
                                                                            "
                                                                            @pointerenter="
                                                                                onBedPointerEnter(
                                                                                    bed.id,
                                                                                )
                                                                            "
                                                                            @pointerleave="
                                                                                onBedPointerLeave(
                                                                                    bed.id,
                                                                                )
                                                                            "
                                                                        >
                                                                            <div
                                                                                class="flex flex-col-reverse items-center"
                                                                            >
                                                                                <div
                                                                                    class="h-2 w-px rounded-full bg-primary/45"
                                                                                    aria-hidden="true"
                                                                                />
                                                                                <div
                                                                                    class="flex h-7 w-7 items-center justify-center rounded-full border border-primary/25 bg-card shadow-[0_2px_10px_rgba(47,67,44,0.14)] ring-2 ring-white/90 transition dark:border-primary/35 dark:bg-card dark:ring-background/80"
                                                                                    :class="
                                                                                        isBedCardVisible(
                                                                                            bed.id,
                                                                                        )
                                                                                            ? 'border-primary/50 bg-primary/8 ring-primary/20'
                                                                                            : ''
                                                                                    "
                                                                                    aria-hidden="true"
                                                                                >
                                                                                    <span
                                                                                        class="h-2.5 w-2.5 rounded-full bg-primary shadow-sm"
                                                                                        aria-hidden="true"
                                                                                    />
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </template>

                                                                    <Teleport
                                                                        to="body"
                                                                    >
                                                                        <div
                                                                            v-for="bed in plannerBeds"
                                                                            :key="`bed-card-${bed.id}`"
                                                                        >
                                                                            <div
                                                                                v-if="
                                                                                    isBedCardVisible(
                                                                                        bed.id,
                                                                                    )
                                                                                "
                                                                                data-no-drag="true"
                                                                                class="pointer-events-auto w-52 overflow-hidden rounded-xl border border-border/70 bg-card/95 text-left shadow-lg backdrop-blur-sm"
                                                                                :style="
                                                                                    bedCardFixedStyle(
                                                                                        bed,
                                                                                    )
                                                                                "
                                                                                @pointerenter="
                                                                                    onBedCardPointerEnter(
                                                                                        bed.id,
                                                                                    )
                                                                                "
                                                                                @pointerleave="
                                                                                    onBedCardPointerLeave(
                                                                                        bed.id,
                                                                                    )
                                                                                "
                                                                                @click.stop
                                                                            >
                                                                                <div
                                                                                    v-if="
                                                                                        getBedPreviewImage(
                                                                                            bed,
                                                                                        )
                                                                                    "
                                                                                    class="h-28 w-full overflow-hidden bg-muted/40"
                                                                                >
                                                                                    <img
                                                                                        :src="
                                                                                            getBedPreviewImage(
                                                                                                bed,
                                                                                            )!
                                                                                        "
                                                                                        :alt="
                                                                                            bed.name
                                                                                        "
                                                                                        class="size-full object-cover"
                                                                                    />
                                                                                </div>
                                                                                <div
                                                                                    class="p-2.5"
                                                                                >
                                                                                    <p
                                                                                        class="truncate text-sm font-bold text-foreground"
                                                                                    >
                                                                                        {{
                                                                                            bed.name
                                                                                        }}
                                                                                    </p>
                                                                                    <p
                                                                                        class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground"
                                                                                    >
                                                                                        <span
                                                                                            class="inline-flex items-center gap-1 font-medium text-foreground/80"
                                                                                        >
                                                                                            <span
                                                                                                class="material-symbols-outlined text-[14px] leading-none"
                                                                                                aria-hidden="true"
                                                                                                >potted_plant</span
                                                                                            >{{
                                                                                                bedPlantsCount(
                                                                                                    bed,
                                                                                                )
                                                                                            }}</span
                                                                                        >
                                                                                        <span
                                                                                            class="opacity-50"
                                                                                            aria-hidden="true"
                                                                                            >·</span
                                                                                        >
                                                                                        <span
                                                                                            >{{
                                                                                                bedSizeLabel(
                                                                                                    bed,
                                                                                                )
                                                                                            }}</span
                                                                                        >
                                                                                    </p>
                                                                                    <div
                                                                                        class="mt-2 flex gap-1.5"
                                                                                    >
                                                                                        <button
                                                                                            type="button"
                                                                                            class="inline-flex min-h-9 flex-1 items-center justify-center rounded-full border border-primary/20 bg-primary/10 px-2 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/15"
                                                                                            @click.stop="
                                                                                                openBedPage(
                                                                                                    bed.id,
                                                                                                )
                                                                                            "
                                                                                        >
                                                                                            Ava
                                                                                            peenar
                                                                                        </button>
                                                                                        <button
                                                                                            type="button"
                                                                                            class="inline-flex min-h-9 flex-1 items-center justify-center gap-1 rounded-full border border-border/70 bg-background px-2 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                                                                                            @click.stop="
                                                                                                deleteBed(
                                                                                                    bed.id,
                                                                                                    bed.name,
                                                                                                )
                                                                                            "
                                                                                        >
                                                                                            <span
                                                                                                class="material-symbols-outlined text-sm text-muted-foreground"
                                                                                                >delete</span
                                                                                            >
                                                                                            Kustuta
                                                                                        </button>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </Teleport>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div
                                                            v-if="
                                                                showManualGardenGrid
                                                            "
                                                            :class="
                                                                GARDEN_GRID_EDGE_CLASS
                                                            "
                                                            aria-hidden="true"
                                                        />
                                                    </div>
                                                    <div
                                                        v-if="
                                                            showMapBackground ||
                                                            showBedsAsPlannerFootprints
                                                        "
                                                        class="mt-2"
                                                    >
                                                        <div
                                                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-900/10 bg-white/88 px-2 py-1 text-[10px] text-emerald-950/70 shadow-sm backdrop-blur-sm dark:border-emerald-200/15 dark:bg-card/90 dark:text-emerald-100/75"
                                                            aria-label="Mõõtkava: 1 meetri pikkune joon"
                                                        >
                                                            <div
                                                                class="relative h-1.5 shrink-0 rounded-full bg-emerald-700/80"
                                                                :style="{
                                                                    width: `${scaleBarWidthPx}px`,
                                                                }"
                                                            >
                                                                <span
                                                                    class="absolute -top-1 -left-px h-3 w-px bg-emerald-900/50"
                                                                ></span>
                                                                <span
                                                                    class="absolute -top-1 -right-px h-3 w-px bg-emerald-900/50"
                                                                ></span>
                                                            </div>
                                                            <span
                                                                class="font-medium whitespace-nowrap text-foreground/80 tabular-nums"
                                                                >1 m</span
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
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
                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-semibold transition"
                        :class="
                            canPlaceBedsOnMap
                                ? 'text-foreground hover:bg-muted'
                                : 'cursor-not-allowed text-muted-foreground opacity-60'
                        "
                        :disabled="!canPlaceBedsOnMap"
                        :title="
                            canPlaceBedsOnMap
                                ? undefined
                                : 'Seadista kõigepealt aia suurus või asukoht'
                        "
                        @click="startNewBedOnMap"
                    >
                        <span
                            class="material-symbols-outlined text-base text-primary"
                            >add_box</span
                        >
                        Lisa peenar
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
                class="max-h-[min(92vh,720px)] w-full max-w-2xl overflow-y-auto rounded-3xl border border-border/70 bg-background p-5 shadow-2xl"
                @submit.prevent="submitCreateGardenPlan"
            >
                <div class="mb-4">
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Uus aiaplaan
                    </p>
                    <h3 class="mt-1 text-lg font-semibold text-foreground">
                        Lisa uus aed
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tühja nime korral kasutatakse vaikimisi nime „Minu aed”.
                        Mõõdud täituvad aadressi või asukoha järgi; tühjad
                        väljad → 12 × 8 m.
                    </p>
                </div>

                <label
                    class="block rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                >
                    <span
                        class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                        >Aia nimi</span
                    >
                    <input
                        ref="createGardenPlanNameInput"
                        v-model="createGardenPlanForm.name"
                        type="text"
                        class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
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

                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        class="flex-1 rounded-2xl border px-3 py-2.5 text-left transition"
                        :class="
                            createSetupMode === 'ortophoto'
                                ? 'border-primary/30 bg-primary/10 text-primary'
                                : 'border-border/70 bg-card/85 text-foreground hover:bg-muted'
                        "
                        @click="createSetupMode = 'ortophoto'"
                    >
                        <span class="block text-sm font-semibold"
                            >Ortofoto</span
                        >
                        <span
                            class="block text-xs leading-5 text-muted-foreground"
                            >Aadress või asukoht + mõõdud meetrites.</span
                        >
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-2xl border px-3 py-2.5 text-left transition"
                        :class="
                            createSetupMode === 'manual'
                                ? 'border-primary/30 bg-primary/10 text-primary'
                                : 'border-border/70 bg-card/85 text-foreground hover:bg-muted'
                        "
                        @click="createSetupMode = 'manual'"
                    >
                        <span class="block text-sm font-semibold">Käsitsi</span>
                        <span
                            class="block text-xs leading-5 text-muted-foreground"
                            >Joonista kuju ruudustikul — ei nõua asukohta.</span
                        >
                    </button>
                </div>

                <div v-if="createSetupMode === 'ortophoto'" class="mt-4">
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Aia asukoht
                    </p>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        Otsi aadressi või kasuta geolokatsiooni, seejärel
                        sisesta aia laius ja sügavus meetrites. Aadressi korral
                        täituvad mõõdud automaatselt.
                    </p>
                    <div
                        class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start"
                    >
                        <div class="relative min-w-0 flex-1">
                            <div
                                class="flex items-center rounded-2xl border border-border/70 bg-card/85 px-3 py-2 ring-offset-background focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                            >
                                <input
                                    v-model="createAddressSearchQuery"
                                    type="search"
                                    autocomplete="off"
                                    class="min-w-0 flex-1 bg-transparent text-sm font-medium text-foreground outline-none"
                                    placeholder="Otsi aadressi..."
                                    @input="onCreateGardenAddressSearchInput"
                                    @focus="
                                        createAddressSearchOpen =
                                            createAddressSearchResults.length >
                                                0 || createAddressSearchError
                                    "
                                />
                                <span
                                    v-if="createAddressSearchLoading"
                                    class="material-symbols-outlined shrink-0 animate-spin text-base text-muted-foreground"
                                    aria-hidden="true"
                                    >progress_activity</span
                                >
                            </div>
                            <div
                                v-if="
                                    createAddressSearchOpen &&
                                    (createAddressSearchResults.length > 0 ||
                                        createAddressSearchLoading ||
                                        createAddressSearchError)
                                "
                                class="absolute top-full right-0 left-0 z-30 mt-1 overflow-hidden rounded-xl border border-border/80 bg-card shadow-lg"
                            >
                                <div
                                    v-if="createAddressSearchLoading"
                                    class="px-3 py-2.5 text-sm text-muted-foreground"
                                >
                                    Otsin...
                                </div>
                                <div
                                    v-else-if="createAddressSearchError"
                                    class="px-3 py-2.5 text-sm text-muted-foreground"
                                >
                                    Aadress ei leitud
                                </div>
                                <ul
                                    v-else
                                    class="max-h-52 overflow-y-auto py-1"
                                >
                                    <li
                                        v-for="result in createAddressSearchResults"
                                        :key="result.id"
                                    >
                                        <button
                                            type="button"
                                            class="w-full px-3 py-2.5 text-left transition hover:bg-muted"
                                            @mousedown.prevent="
                                                selectCreateGardenAddress(
                                                    result,
                                                )
                                            "
                                        >
                                            <span
                                                class="block text-sm font-medium text-foreground"
                                                >{{ result.label }}</span
                                            >
                                            <span
                                                v-if="result.county"
                                                class="mt-0.5 block text-xs text-muted-foreground"
                                                >{{ result.county }}</span
                                            >
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-2xl border border-border/70 bg-card/85 px-3 text-sm font-semibold text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60 sm:h-[42px]"
                            :disabled="geolocationLoading"
                            @click="requestCreateGardenGeolocation"
                        >
                            <span
                                v-if="geolocationLoading"
                                class="material-symbols-outlined animate-spin text-base"
                                >progress_activity</span
                            >
                            <span v-else>📍</span>
                            <span>Minu asukoht</span>
                        </button>
                    </div>
                    <p
                        v-if="createSelectedGardenAddressLabel"
                        class="mt-2 text-xs leading-5 text-muted-foreground"
                    >
                        Valitud: {{ createSelectedGardenAddressLabel }}
                    </p>
                    <p
                        v-if="createGeolocationFailed"
                        class="mt-2 text-sm text-red-600"
                    >
                        Asukoht pole saadaval
                    </p>
                    <p
                        v-if="createGardenDimensionsLoading"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Laen andmeid…
                    </p>
                    <p
                        v-else-if="createGardenDimensionsMessage"
                        class="mt-2 text-xs leading-5 text-muted-foreground"
                    >
                        {{ createGardenDimensionsMessage }}
                    </p>
                </div>

                <template v-if="createSetupMode === 'ortophoto'">
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label
                            class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm"
                        >
                            <span
                                class="mb-1 block text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                >Laius (m)</span
                            >
                            <input
                                :value="
                                    optionalDimensionInputValue(
                                        createGardenPlanForm.widthMeters,
                                    )
                                "
                                type="number"
                                min="0.01"
                                max="1000"
                                step="0.01"
                                class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                                placeholder="12"
                                @input="
                                    clearCreateGardenAddressFeedback();
                                    createGardenPlanForm.widthMeters =
                                        parseOptionalDimensionInput(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        );
                                "
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
                                :value="
                                    optionalDimensionInputValue(
                                        createGardenPlanForm.heightMeters,
                                    )
                                "
                                type="number"
                                min="0.01"
                                max="1000"
                                step="0.01"
                                class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                                placeholder="8"
                                @input="
                                    clearCreateGardenAddressFeedback();
                                    createGardenPlanForm.heightMeters =
                                        parseOptionalDimensionInput(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        );
                                "
                            />
                        </label>
                    </div>
                </template>

                <div v-if="createSetupMode === 'manual'" class="mt-4">
                    <p
                        class="mb-2 text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Ruudu suurus
                    </p>
                    <div class="mb-3 flex flex-wrap gap-1">
                        <button
                            v-for="size in [50, 100, 200, 500, 1000]"
                            :key="size"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                            :class="
                                createManualCellSizeCm === size
                                    ? 'border-primary/30 bg-primary/10 text-primary'
                                    : 'border-border/70 bg-card text-foreground hover:bg-muted'
                            "
                            @click="createManualCellSizeCm = size"
                        >
                            {{ size >= 100 ? size / 100 + ' m' : size + ' cm' }}
                        </button>
                    </div>
                    <GardenShapeEditor
                        v-model="createManualShapeMask"
                        :cell-size-cm="createManualCellSizeCm"
                    />
                    <p
                        v-if="createGardenDimensionsMessage"
                        class="mt-2 text-xs leading-5 text-muted-foreground"
                    >
                        {{ createGardenDimensionsMessage }}
                    </p>
                </div>

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
                        :disabled="
                            createGardenPlanForm.processing ||
                            (createSetupMode === 'ortophoto' &&
                                !createOrtophotoSetupReady) ||
                            (createSetupMode === 'manual' &&
                                !createManualShapeMask.some((row) =>
                                    row.includes(1),
                                ))
                        "
                    >
                        {{
                            createGardenPlanForm.processing
                                ? 'Loon aiaplaani...'
                                : createSetupMode === 'ortophoto' &&
                                    !createOrtophotoSetupReady
                                  ? 'Vali asukoht ja mõõdud'
                                  : createSetupMode === 'manual' &&
                                      !createManualShapeMask.some((row) =>
                                          row.includes(1),
                                      )
                                    ? 'Märgi aia kuju ruudustikul'
                                    : 'Loo aiaplaan'
                        }}
                    </button>
                </div>
            </form>
        </div>

        <MapConfirmDialog
            :open="confirmDialogOpen"
            :title="confirmDialogTitle"
            :message="confirmDialogMessage"
            :confirm-label="confirmDialogConfirmLabel"
            @close="closeConfirmDialog"
            @confirm="runConfirmDialogAction"
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

.bed-footprint-interactive {
    pointer-events: auto;
}
</style>
