<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import BackIconButton from '@/components/BackIconButton.vue';
import DiaryHeader from '@/components/DiaryHeader.vue';
import FloatingPlusButton from '@/components/FloatingPlusButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BottomNav from '@/pages/BottomNav.vue';
import SearchModal from '@/pages/Seeds/SearchModal.vue';

type PlantInBed = { id: number; name: string; image_url: string | null; position_in_bed: string | null };
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
type GardenObjectType = 'greenhouse' | 'pond' | 'shed' | 'compost';
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
type PlantWithoutBed = { id: number; name: string; image_url: string | null; category?: { name: string; slug: string } | null };
type GardenPlan = { id: number; name: string; width: number; height: number; unit: string };

const props = defineProps<{
  gardenPlan: GardenPlan;
  beds: Bed[];
  gardenObjects: GardenObject[];
  plantsWithoutBed: PlantWithoutBed[];
}>();

const breadcrumbs = [{ title: 'Aiaplaan', href: '/map' }];
const showOnboardingHint = computed(() => props.beds.length === 0 && props.plantsWithoutBed.length === 0);
const showSearch = ref(false);
const searchQuery = ref('');
const FAVORITE_BED_IDS_KEY = 'favoriteBedIds';
const favoriteBedIds = ref<number[]>([]);

type TabKey = 'all' | 'favorites';
const activeTab = ref<TabKey>('all');
const recentFirst = ref(false);

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
const toolDrawerOpen = ref(false);
const plannerControlsOpen = ref(false);
const toolSearch = ref('');
const showBedsLayer = ref(true);
const showStructuresLayer = ref(true);
const showWaterLayer = ref(true);
const showPlannerLabels = ref(true);
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

onMounted(() => {
  try {
    const raw = localStorage.getItem(FAVORITE_BED_IDS_KEY);
    if (!raw) return;
    const parsed = JSON.parse(raw);
    if (Array.isArray(parsed)) {
      favoriteBedIds.value = parsed.map((v) => Number(v)).filter((v) => Number.isInteger(v));
    }
  } catch {
    favoriteBedIds.value = [];
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

  if (activeTab.value === 'favorites') {
    list = list.filter((b) => favoriteBedIds.value.includes(b.id));
  }

  if (recentFirst.value) {
    list = list.slice().sort((a, b) => b.id - a.id);
  } else {
    list = list.slice().sort((a, b) => a.name.localeCompare(b.name, 'et'));
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter((b) =>
      b.name.toLowerCase().includes(q) || (b.location ?? '').toLowerCase().includes(q),
    );
  }

  return list;
});

const gardenWidthCm = computed(() => Math.max(200, Math.round(Number(gardenForm.widthMeters || 0) * 100)));
const gardenHeightCm = computed(() => Math.max(200, Math.round(Number(gardenForm.heightMeters || 0) * 100)));
const gardenSurfaceWidth = computed(() => Math.max(320, Math.round(gardenWidthCm.value * CM_TO_PX)));
const gardenSurfaceHeight = computed(() => Math.max(240, Math.round(gardenHeightCm.value * CM_TO_PX)));
const zoomPercent = computed(() => `${Math.round(zoom.value * 100)}%`);
const gardenDimensionLabel = computed(() => `${Number(gardenForm.widthMeters || 0).toFixed(1)} m × ${Number(gardenForm.heightMeters || 0).toFixed(1)} m`);
const plannerGridSizePx = computed(() => Math.max(10, Math.round(GARDEN_GRID_CELL_CM * CM_TO_PX)));
const plannerMajorGridSizePx = computed(() => plannerGridSizePx.value * 5);
const scaleBarWidthPx = computed(() => Math.round(100 * CM_TO_PX));
const toolTypes: GardenObjectType[] = ['greenhouse', 'pond', 'shed', 'compost'];
const plannerBeds = computed(() => (showBedsLayer.value ? filteredBeds.value : []));
const plannerObjects = computed(() =>
  props.gardenObjects.filter((object) => {
    if (object.type === 'pond') return showWaterLayer.value;
    return showStructuresLayer.value;
  }),
);
const visibleObjectsCount = computed(() => plannerObjects.value.length);
const filteredToolTypes = computed(() => {
  const query = toolSearch.value.trim().toLowerCase();
  if (!query) return toolTypes;

  return toolTypes.filter((type) => {
    return (
      objectTypeLabel(type).toLowerCase().includes(query)
      || toolDescription(type).toLowerCase().includes(query)
    );
  });
});

const selectedBed = computed(() => {
  if (selectedBedId.value === null) return null;
  return props.beds.find((bed) => bed.id === selectedBedId.value) ?? null;
});

const selectedObject = computed(() => {
  if (selectedObjectId.value === null) return null;
  return props.gardenObjects.find((object) => object.id === selectedObjectId.value) ?? null;
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
      if (objectPanelHighlightTimeout) clearTimeout(objectPanelHighlightTimeout);
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
  if (selectedBedId.value !== null && !beds.some((bed) => bed.id === selectedBedId.value)) {
    selectedBedId.value = beds[0]?.id ?? null;
  }
});

watch(plannerObjects, (objects) => {
  if (selectedObjectId.value !== null && !objects.some((object) => object.id === selectedObjectId.value)) {
    selectedObjectId.value = null;
    hoveredObjectId.value = null;
  }
});

const tabClass = (active: boolean) => {
  const base = 'flex h-9 shrink-0 items-center justify-center rounded-full px-4 text-sm font-medium transition-colors';
  if (active) return `${base} bg-primary text-white`;
  return `${base} bg-primary/10 text-primary hover:bg-primary/15`;
};

const layerButtonClass = (active: boolean) => {
  const base = 'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition';
  if (active) return `${base} border-primary/20 bg-primary/10 text-primary`;
  return `${base} border-border bg-background text-muted-foreground hover:bg-muted`;
};

const gardenPresetButtonClass = (active: boolean) => {
  const base = 'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition';
  if (active) return `${base} border-primary/20 bg-primary/10 text-primary`;
  return `${base} border-border/70 bg-card/85 text-foreground hover:bg-muted`;
};

const toolMenuItemClass = (type: GardenObjectType) => {
  const active = activeTool.value === type;
  return active
    ? 'border-primary/25 bg-primary/8 ring-2 ring-primary/15'
    : 'border-border/70 bg-background/80 hover:border-primary/20 hover:bg-muted/60';
};

function resetToAll() {
  activeTab.value = 'all';
  searchQuery.value = '';
}

function toggleFavoriteBed(id: number) {
  const isFavorite = favoriteBedIds.value.includes(id);
  favoriteBedIds.value = isFavorite
    ? favoriteBedIds.value.filter((x) => x !== id)
    : [...favoriteBedIds.value, id];

  try {
    localStorage.setItem(FAVORITE_BED_IDS_KEY, JSON.stringify(favoriteBedIds.value));
  } catch {
    // ignore storage failures
  }
}

function isFavoriteBed(id: number): boolean {
  return favoriteBedIds.value.includes(id);
}

function editBed(id: number) {
  router.get(`/beds/${id}/edit`);
}

function deleteBed(id: number, name: string) {
  if (!confirm(`Eemaldada peenar "${name}"? Taimed jäävad peenrata.`)) return;
  router.delete(`/beds/${id}`, { preserveScroll: true });
}

function getBedLayout(bed: Bed): number[][] {
  const L = bed.layout;
  if (L && Array.isArray(L) && L.length > 0 && L.some((row) => Array.isArray(row) && row.length > 0)) {
    return L as number[][];
  }
  return Array.from({ length: bed.rows || 1 }, () => Array.from({ length: bed.columns || 1 }, () => 1));
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
    height: Math.max(24, Math.round(getBedPhysicalHeightCm(bed) * CM_TO_PX)),
  };
}

function getObjectPixelWidth(object: GardenObject): number {
  return Math.max(30, Math.round(object.width * CM_TO_PX));
}

function getObjectPixelHeight(object: GardenObject): number {
  return Math.max(30, Math.round(object.height * CM_TO_PX));
}

function snapToGardenGrid(value: number): number {
  return Math.round(value / plannerGridSizePx.value) * plannerGridSizePx.value;
}

function clampBedPosition(bed: Bed, x: number, y: number) {
  const size = bedCardSize(bed);
  return {
    x: Math.max(GARDEN_PADDING, Math.min(x, gardenSurfaceWidth.value - size.width - GARDEN_PADDING)),
    y: Math.max(GARDEN_PADDING, Math.min(y, gardenSurfaceHeight.value - size.height - GARDEN_PADDING)),
  };
}

function clampObjectPosition(object: GardenObject, x: number, y: number) {
  const width = getObjectPixelWidth(object);
  const height = getObjectPixelHeight(object);

  return {
    x: Math.max(GARDEN_PADDING, Math.min(x, gardenSurfaceWidth.value - width - GARDEN_PADDING)),
    y: Math.max(GARDEN_PADDING, Math.min(y, gardenSurfaceHeight.value - height - GARDEN_PADDING)),
  };
}

function getBedPosition(bed: Bed) {
  const stored = localPositions.value[bed.id] ?? { x: bed.garden_x ?? GARDEN_PADDING, y: bed.garden_y ?? GARDEN_PADDING };
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
  const stored = localObjectPositions.value[object.id] ?? { x: object.x, y: object.y };
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

  const object = props.gardenObjects.find((item) => item.id === draggingObjectId.value);
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
  const target = event.target as HTMLElement | null;
  if (target?.closest('[data-bed-shape="true"]')) return;
  if (target?.closest('[data-object-shape="true"]')) return;
  if (target?.closest('button, input, label, a')) return;

  isPanning.value = true;
  panStart.value = {
    x: event.clientX,
    y: event.clientY,
    originX: panX.value,
    originY: panY.value,
  };

  window.addEventListener('pointermove', onPanMove);
  window.addEventListener('pointerup', stopPanning);
  window.addEventListener('pointercancel', stopPanning);
}

function getDefaultObjectConfig(type: GardenObjectType) {
  return {
    greenhouse: {
      name: 'Kasvuhoone',
      width: 300,
      height: 200,
    },
    pond: {
      name: 'Tiik',
      width: 250,
      height: 180,
    },
    shed: {
      name: 'Kuur',
      width: 220,
      height: 180,
    },
    compost: {
      name: 'Kompost',
      width: 140,
      height: 140,
    },
  }[type];
}

function chooseTool(type: GardenObjectType) {
  activeTool.value = type;
  toolDrawerOpen.value = false;
}

function clearToolSelection() {
  activeTool.value = null;
}

function toolDescription(type: GardenObjectType): string {
  return {
    greenhouse: 'Soe ja kaitstud ala tomatitele, kurkidele ja teistele õrnadele taimedele.',
    pond: 'Veesilm või tiik, mis toob aeda rohkem elu ja aitab plaani ruumiliselt tasakaalustada.',
    shed: 'Kuur tööriistade, pottide ja muude tarvikute jaoks.',
    compost: 'Kompostiala orgaanilise materjali kogumiseks ja mulla parandamiseks.',
  }[type];
}

function applyGardenPreset(widthMeters: number, heightMeters: number, suggestedName?: string) {
  gardenForm.widthMeters = widthMeters;
  gardenForm.heightMeters = heightMeters;

  if (!gardenForm.name.trim() && suggestedName) {
    gardenForm.name = suggestedName;
  }
}

function isGardenPresetActive(widthMeters: number, heightMeters: number): boolean {
  return Number(gardenForm.widthMeters) === widthMeters && Number(gardenForm.heightMeters) === heightMeters;
}

function openCreateBed() {
  router.get('/map/beds/new');
}

function handlePlannerSurfaceClick(event: MouseEvent) {
  if (!activeTool.value) return;
  if (isPanning.value || dragMoved.value) return;

  const target = event.target as HTMLElement | null;
  if (target?.closest('[data-bed-shape="true"], [data-object-shape="true"], button, input, label, a')) return;

  const config = getDefaultObjectConfig(activeTool.value);
  const point = getPlannerLocalPoint(event);
  const widthPx = Math.round(config.width * CM_TO_PX);
  const heightPx = Math.round(config.height * CM_TO_PX);
  const rawX = snapToGardenGrid(point.x - widthPx / 2);
  const rawY = snapToGardenGrid(point.y - heightPx / 2);
  const clampedX = Math.max(GARDEN_PADDING, Math.min(rawX, gardenSurfaceWidth.value - widthPx - GARDEN_PADDING));
  const clampedY = Math.max(GARDEN_PADDING, Math.min(rawY, gardenSurfaceHeight.value - heightPx - GARDEN_PADDING));

  router.post(
    '/garden-objects',
    {
      garden_plan_id: props.gardenPlan.id,
      type: activeTool.value,
      name: config.name,
      x: clampedX,
      y: clampedY,
      width: config.width,
      height: config.height,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        activeTool.value = null;
      },
    },
  );
}

function deleteSelectedObject() {
  if (!selectedObject.value) return;

  if (!confirm(`Eemaldada aiaelement "${selectedObject.value.name}"?`)) return;

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

  router.post(`/garden-objects/${selectedObject.value.id}/duplicate`, {}, {
    preserveScroll: true,
    preserveState: true,
  });
}

function rotateSelectedObject() {
  if (!selectedObject.value) return;

  router.post(`/garden-objects/${selectedObject.value.id}/rotate`, {}, {
    preserveScroll: true,
    preserveState: true,
  });
}

function saveSelectedObject() {
  if (!selectedObject.value) return;

  objectForm.transform((data) => ({
    name: data.name.trim() || selectedObject.value?.name || 'Aiaelement',
    width: Math.round(Number(data.widthMeters || 0) * 100),
    height: Math.round(Number(data.heightMeters || 0) * 100),
  })).put(`/garden-objects/${selectedObject.value.id}`, {
    preserveScroll: true,
    preserveState: true,
  });
}

function revealSelectedObjectEditor() {
  nextTick(() => {
    selectedObjectPanel.value?.scrollIntoView({
      block: 'nearest',
      behavior: 'smooth',
    });
    objectWidthInput.value?.focus();
    objectWidthInput.value?.select();
  });
}

function nudgeSelectedObjectSize(dimension: 'width' | 'height', deltaMeters: number) {
  if (!selectedObject.value) return;

  const nextWidth = dimension === 'width'
    ? Math.max(0.5, Number((objectForm.widthMeters + deltaMeters).toFixed(1)))
    : objectForm.widthMeters;
  const nextHeight = dimension === 'height'
    ? Math.max(0.5, Number((objectForm.heightMeters + deltaMeters).toFixed(1)))
    : objectForm.heightMeters;

  objectForm.widthMeters = nextWidth;
  objectForm.heightMeters = nextHeight;
  saveSelectedObject();
}

function objectTypeLabel(type: GardenObjectType): string {
  return {
    greenhouse: 'Kasvuhoone',
    pond: 'Tiik',
    shed: 'Kuur',
    compost: 'Kompost',
  }[type];
}

function objectTypeIcon(type: GardenObjectType): string {
  return {
    greenhouse: 'home_work',
    pond: 'water',
    shed: 'warehouse',
    compost: 'compost',
  }[type];
}

function objectClass(type: GardenObjectType): string {
  return {
    greenhouse:
      'rounded-[1.25rem] border border-emerald-700/35 bg-[linear-gradient(180deg,rgba(197,229,210,0.92),rgba(136,187,153,0.95))] shadow-md',
    pond:
      'rounded-[999px] border border-sky-700/30 bg-[radial-gradient(circle_at_top,rgba(217,242,255,0.96),rgba(94,182,223,0.92))] shadow-md',
    shed:
      'rounded-[1rem] border border-stone-700/30 bg-[linear-gradient(180deg,rgba(202,176,145,0.96),rgba(133,101,72,0.96))] shadow-md',
    compost:
      'rounded-[1rem] border border-lime-900/30 bg-[linear-gradient(180deg,rgba(130,102,64,0.96),rgba(85,65,38,0.98))] shadow-md',
  }[type];
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
    backgroundSize: `${plannerGridSizePx.value}px ${plannerGridSizePx.value}px, ${plannerGridSizePx.value}px ${plannerGridSizePx.value}px, ${plannerMajorGridSizePx.value}px ${plannerMajorGridSizePx.value}px, ${plannerMajorGridSizePx.value}px ${plannerMajorGridSizePx.value}px`,
    backgroundImage:
      'linear-gradient(to right, rgba(95,135,80,0.06) 1px, transparent 1px), linear-gradient(to bottom, rgba(95,135,80,0.06) 1px, transparent 1px), linear-gradient(to right, rgba(95,135,80,0.12) 1px, transparent 1px), linear-gradient(to bottom, rgba(95,135,80,0.12) 1px, transparent 1px)',
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
  const nextZoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.min(fitWidth, fitHeight)));
  zoom.value = Number.isFinite(nextZoom) ? nextZoom : 1;
  panX.value = 0;
  panY.value = 0;
}

function changeZoom(delta: number) {
  zoom.value = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Number((zoom.value + delta).toFixed(2))));
}

function resetZoom() {
  zoom.value = 1;
  panX.value = 0;
  panY.value = 0;
}

function onPlannerWheel(event: WheelEvent) {
  event.preventDefault();
  const pointer = getPlannerLocalPoint(event);
  const delta = event.deltaY > 0 ? -0.12 : 0.12;
  const nextZoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Number((zoom.value + delta).toFixed(2))));

  if (nextZoom === zoom.value) return;

  panX.value -= pointer.x * (nextZoom - zoom.value);
  panY.value -= pointer.y * (nextZoom - zoom.value);
  zoom.value = nextZoom;
}

function saveGardenPlan() {
  gardenForm.transform((data) => ({
    name: data.name.trim() || 'Minu aed',
    width: Math.round(Number(data.widthMeters || 0) * 100),
    height: Math.round(Number(data.heightMeters || 0) * 100),
  })).put('/garden-plan', {
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
      <div class="bg-background text-foreground font-display min-h-screen antialiased">
        <div class="bg-background border-beige/50 relative mx-auto min-h-screen w-full max-w-[480px] overflow-x-hidden border-x shadow-2xl md:mx-0 md:max-w-none md:border-0 md:shadow-none">
          <DiaryHeader
            title="Aiaplaan"
            header-class="pt-6"
            top-row-class="mb-3"
            bottom-row-class="mb-4"
          >
            <template #leading>
              <BackIconButton href="/dashboard" aria-label="Tagasi avalehele" />
            </template>
            <template #actions>
              <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary/10 sm:h-10 sm:w-10"
                @click="showSearch = true"
              >
                <span class="material-symbols-outlined text-xl">search</span>
              </button>
            </template>

            <div class="space-y-3">
              <div class="rounded-[1.75rem] border border-primary/15 bg-linear-to-br from-primary/12 via-background to-secondary/35 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <p class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">
                      Minu aed
                    </p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-foreground">
                      Sätti peenrad aias täpselt sinna, kuhu need päriselt kuuluvad.
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-foreground/75">
                      Lohista peenraid ringi nagu miniatuurses aiaplaani tööriistas. Ava peenar, et selle kuju ja taimed täpsemalt paika panna.
                    </p>
                  </div>
                  <div class="hidden shrink-0 rounded-[1.5rem] border border-primary/15 bg-card/80 p-3 shadow-sm sm:flex sm:flex-col sm:items-center sm:justify-center">
                    <span class="material-symbols-outlined text-[2.4rem] text-primary">yard</span>
                    <span class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-primary/80">Planeeri</span>
                  </div>
                </div>
              </div>

              <div class="no-scrollbar flex gap-2 overflow-x-auto pb-2">
                <button :class="tabClass(activeTab === 'all')" type="button" @click="resetToAll">Kõik</button>
                <button :class="tabClass(activeTab === 'favorites')" type="button" @click="activeTab = 'favorites'">Lemmikud</button>
                <button :class="tabClass(recentFirst)" type="button" @click="recentFirst = !recentFirst">Hiljuti lisatud</button>
              </div>
            </div>
          </DiaryHeader>

          <main class="flex-1 px-6 py-4 md:px-8">
            <div class="space-y-6 sm:space-y-8">
              <section class="space-y-6">
                <div
                  v-if="showOnboardingHint"
                  class="rounded-2xl border border-primary/20 bg-linear-to-r from-primary/10 via-primary/5 to-transparent px-4 py-3 shadow-sm"
                >
                  <p class="text-sm leading-relaxed text-foreground/85">
                    Lisa esmalt peenar, seejärel saad teda aias ringi tõsta ja hiljem avada, et ruudustik ning taimed täpsemalt paika panna.
                  </p>
                </div>

                <div
                  class="rounded-[2rem] border border-border/70 bg-card/75 p-4 shadow-soft sm:p-5"
                >
                  <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <h3 class="text-lg font-semibold text-foreground">Miniatuurne aiaplaan</h3>
                      <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        Lohista peenraid ja lisa aeda ka teisi objekte. Paigutus salvestatakse automaatselt.
                      </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                      <div class="flex items-center gap-2 rounded-2xl border border-border/70 bg-background/80 px-3 py-2 text-xs text-muted-foreground shadow-xs">
                        <span class="material-symbols-outlined rounded-xl bg-primary/10 p-2 text-primary">forest</span>
                        <div>
                          Peenraid aias
                          <div class="text-base font-semibold text-foreground">{{ plannerBeds.length }}</div>
                        </div>
                      </div>
                      <div class="flex items-center gap-2 rounded-2xl border border-border/70 bg-background/80 px-3 py-2 text-xs text-muted-foreground shadow-xs">
                        <span class="material-symbols-outlined rounded-xl bg-primary/10 p-2 text-primary">architecture</span>
                        <div>
                          Aiaobjekte
                          <div class="text-base font-semibold text-foreground">{{ visibleObjectsCount }}</div>
                        </div>
                      </div>
                      <div class="flex items-center gap-1 rounded-2xl border border-border/70 bg-background/85 px-2 py-2 shadow-xs">
                        <button
                          type="button"
                          class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-card text-foreground transition hover:bg-muted"
                          @click="changeZoom(-ZOOM_STEP)"
                        >
                          <span class="material-symbols-outlined text-base">remove</span>
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
                          @click="changeZoom(ZOOM_STEP)"
                        >
                          <span class="material-symbols-outlined text-base">add</span>
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

                  <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-[1.5rem] border border-border/70 bg-background/75 px-3 py-3">
                    <div class="min-w-0">
                      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Aed</p>
                      <p class="mt-1 text-sm font-medium text-foreground">{{ gardenForm.name || 'Minu aed' }}</p>
                      <p class="text-xs text-muted-foreground">{{ gardenDimensionLabel }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">

                      <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                        @click="plannerControlsOpen = !plannerControlsOpen"
                      >
                        <span class="material-symbols-outlined text-sm">tune</span>
                        {{ plannerControlsOpen ? 'Peida seaded' : 'Muudaaga ' }}
                      </button>
                    </div>
                  </div>

                  <div
                    v-if="plannerControlsOpen"
                    class="mb-4 grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]"
                  >
                    <div class="rounded-[1.5rem] border border-border/70 bg-background/75 p-3">
                      <div class="mb-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Kiirvalikud</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                          <button
                            type="button"
                            :class="gardenPresetButtonClass(isGardenPresetActive(10, 10))"
                            @click="applyGardenPreset(10, 10, 'Väike aed')"
                          >
                            Väike aed
                            <span class="text-[11px] text-current/80">10 × 10 m</span>
                          </button>
                          <button
                            type="button"
                            :class="gardenPresetButtonClass(isGardenPresetActive(20, 10))"
                            @click="applyGardenPreset(20, 10, 'Köögiviljaaed')"
                          >
                            Köögiviljaaed
                            <span class="text-[11px] text-current/80">20 × 10 m</span>
                          </button>
                          <button
                            type="button"
                            :class="gardenPresetButtonClass(isGardenPresetActive(50, 20))"
                            @click="applyGardenPreset(50, 20, 'Suur aed')"
                          >
                            Suur aed
                            <span class="text-[11px] text-current/80">50 × 20 m</span>
                          </button>
                          <button
                            type="button"
                            :class="gardenPresetButtonClass(isGardenPresetActive(100, 30))"
                            @click="applyGardenPreset(100, 30, 'Pikk aiamaa')"
                          >
                            Pikk aiamaa
                            <span class="text-[11px] text-current/80">100 × 30 m</span>
                          </button>
                        </div>
                      </div>

                      <div class="grid gap-3 sm:grid-cols-3">
                        <label class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm">
                          <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Aia nimi</span>
                          <input
                            v-model="gardenForm.name"
                            type="text"
                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                            placeholder="Minu aed"
                          />
                        </label>
                        <label class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm">
                          <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Laius (m)</span>
                          <input
                            v-model="gardenForm.widthMeters"
                            type="number"
                            min="2"
                            max="1000"
                            step="0.1"
                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                          />
                        </label>
                        <label class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm">
                          <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Kõrgus (m)</span>
                          <input
                            v-model="gardenForm.heightMeters"
                            type="number"
                            min="2"
                            max="1000"
                            step="0.1"
                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                          />
                        </label>
                      </div>

                      <div v-if="gardenForm.errors.name || gardenForm.errors.width || gardenForm.errors.height" class="mt-3 space-y-1 rounded-2xl border border-red-200 bg-red-50/90 px-3 py-2 text-sm text-red-700">
                        <p v-if="gardenForm.errors.name">{{ gardenForm.errors.name }}</p>
                        <p v-if="gardenForm.errors.width">{{ gardenForm.errors.width }}</p>
                        <p v-if="gardenForm.errors.height">{{ gardenForm.errors.height }}</p>
                      </div>

                      <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/15"
                          :disabled="gardenForm.processing"
                          @click="saveGardenPlan"
                        >
                          Salvesta aed
                        </button>
                      </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-border/70 bg-background/75 p-3">
                      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Vaate kihid</p>
                      <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" :class="layerButtonClass(showBedsLayer)" @click="showBedsLayer = !showBedsLayer">
                          <span class="material-symbols-outlined text-sm">grid_view</span>
                          Peenrad
                        </button>
                        <button type="button" :class="layerButtonClass(showStructuresLayer)" @click="showStructuresLayer = !showStructuresLayer">
                          <span class="material-symbols-outlined text-sm">home_work</span>
                          Hooned
                        </button>
                        <button type="button" :class="layerButtonClass(showWaterLayer)" @click="showWaterLayer = !showWaterLayer">
                          <span class="material-symbols-outlined text-sm">water</span>
                          Vesi
                        </button>
                        <button type="button" :class="layerButtonClass(showPlannerLabels)" @click="showPlannerLabels = !showPlannerLabels">
                          <span class="material-symbols-outlined text-sm">label</span>
                          Nimed
                        </button>
                      </div>
                    </div>
                  </div>

                  <div
                    v-if="!props.beds.length && !props.gardenObjects.length"
                    class="rounded-[1.75rem] border-2 border-dashed border-primary/30 bg-linear-to-br from-muted/25 to-primary/6 p-8 text-center text-muted-foreground"
                  >
                    Lisa esimene peenar või aiaelement ja sellest saab sinu aiaplaani esimene ehitusklots.
                  </div>

                  <div
                    v-else-if="plannerBeds.length === 0 && visibleObjectsCount === 0"
                    class="rounded-[1.75rem] border border-dashed border-primary/30 bg-primary/5 px-6 py-8 text-center"
                  >
                    <p class="text-sm text-muted-foreground">Praeguste filtrite ja kihtidega pole aias midagi nähtaval.</p>
                  </div>

                  <div
                    v-else
                    class="mb-3 grid gap-3 xl:grid-cols-[18rem_minmax(0,1fr)]"
                  >
                    <button
                      type="button"
                      class="inline-flex items-center justify-between rounded-[1.25rem] border border-border/80 bg-card/92 px-4 py-3 text-left shadow-sm xl:hidden"
                      @click="toolDrawerOpen = !toolDrawerOpen"
                    >
                      <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Tööriistad</p>
                        <p class="mt-1 text-sm font-semibold text-foreground">
                          {{ activeTool ? objectTypeLabel(activeTool) : 'Lisa aeda objekt' }}
                        </p>
                      </div>
                      <div class="flex items-center gap-2">
                        <span
                          v-if="activeTool"
                          class="rounded-full border border-primary/15 bg-primary/8 px-2.5 py-1 text-[11px] font-semibold text-primary"
                        >
                          Valitud
                        </span>
                        <span class="material-symbols-outlined text-primary">
                          {{ toolDrawerOpen ? 'expand_less' : 'expand_more' }}
                        </span>
                      </div>
                    </button>

                    <aside
                      class="rounded-[1.75rem] border border-border/80 bg-card/92 p-4 shadow-sm xl:sticky xl:top-6 xl:self-start"
                      :class="toolDrawerOpen ? 'block' : 'hidden xl:block'"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Tööriistad</p>
                          <h4 class="mt-1 text-lg font-semibold text-foreground">Lisa aeda</h4>
                        </div>
                        <button
                          v-if="activeTool"
                          type="button"
                          class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background text-foreground transition hover:bg-muted"
                          @click="clearToolSelection"
                        >
                          <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                      </div>

                      <label class="mt-4 block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Objektiotsing</span>
                        <div class="relative">
                          <input
                            v-model="toolSearch"
                            type="text"
                            class="w-full rounded-2xl border border-border/70 bg-background px-4 py-3 pr-11 text-sm text-foreground shadow-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            placeholder="Otsi: kuur, tiik..."
                          />
                          <button
                            v-if="toolSearch"
                            type="button"
                            class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            @click="toolSearch = ''"
                          >
                            <span class="material-symbols-outlined text-sm">close</span>
                          </button>
                        </div>
                      </label>

                      <div class="mt-4 space-y-2">
                        <button
                          v-for="type in filteredToolTypes"
                          :key="type"
                          type="button"
                          class="w-full rounded-[1.15rem] border p-3 text-left transition"
                          :class="toolMenuItemClass(type)"
                          @click="chooseTool(type)"
                        >
                          <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                              <p class="text-sm font-semibold text-foreground">{{ objectTypeLabel(type) }}</p>
                              <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ toolDescription(type) }}</p>
                            </div>
                            <span
                              class="material-symbols-outlined rounded-xl p-2"
                              :class="{
                                'bg-emerald-100 text-emerald-800': type === 'greenhouse',
                                'bg-sky-100 text-sky-800': type === 'pond',
                                'bg-stone-100 text-stone-700': type === 'shed',
                                'bg-lime-100 text-lime-800': type === 'compost',
                              }"
                            >
                              {{ objectTypeIcon(type) }}
                            </span>
                          </div>
                        </button>

                        <div
                          v-if="!filteredToolTypes.length"
                          class="rounded-[1.15rem] border border-dashed border-border/80 bg-background/70 px-4 py-5 text-sm text-muted-foreground"
                        >
                          Selle otsinguga sobivat aiaelementi ei leitud.
                        </div>
                      </div>

                      <div
                        v-if="activeTool"
                        class="mt-4 rounded-[1.25rem] border border-primary/15 bg-primary/6 px-3 py-3 text-xs text-primary"
                      >
                        <div class="font-semibold">{{ objectTypeLabel(activeTool) }}</div>
                        <div class="mt-1 text-primary/85">Klõpsa nüüd aias kohta, kuhu soovid selle lisada.</div>
                      </div>

                      <button
                        type="button"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted xl:hidden"
                        @click="toolDrawerOpen = false"
                      >
                        Sulge tööriistad
                      </button>

                      <div class="mt-4 rounded-[1.25rem] border border-border/70 bg-background/70 px-3 py-3 text-xs text-muted-foreground">
                        Lohista peenraid või objekte nende kuju pealt. Kui aed on zoomitud, liiguta tühja ala lohistades kogu vaadet või kasuta hiireratta zoomi.
                      </div>

                    </aside>

                    <div
                      ref="plannerViewport"
                      class="overflow-auto rounded-[1.75rem] border border-border/80 bg-[linear-gradient(180deg,rgba(251,248,241,0.98),rgba(244,239,229,0.98))] p-3 shadow-inner sm:p-4"
                      :class="isPanning ? 'cursor-grabbing' : 'cursor-grab'"
                      :style="{ height: 'min(72vh, 920px)' }"
                      @pointerdown="startPanning($event)"
                      @wheel="onPlannerWheel($event)"
                    >
                      <div
                        class="relative overflow-hidden rounded-[1.6rem] border border-emerald-900/10 bg-[radial-gradient(circle_at_top,_rgba(185,214,160,0.22),_transparent_32%),linear-gradient(180deg,rgba(239,247,232,0.96),rgba(228,239,219,0.98))]"
                        :style="plannerSurfaceStyle()"
                        @click="handlePlannerSurfaceClick($event)"
                      >
                      <div class="pointer-events-none absolute inset-x-4 top-3 z-20 flex items-center justify-between text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-900/45">
                        <span class="rounded-full bg-white/70 px-2.5 py-1 backdrop-blur-sm">Aed</span>
                        <span class="rounded-full bg-white/70 px-2.5 py-1 backdrop-blur-sm">Lohista peenraid</span>
                      </div>

                      <div class="pointer-events-none absolute bottom-4 left-4 z-20 rounded-2xl border border-emerald-900/10 bg-white/78 px-3 py-2 text-[11px] font-medium text-emerald-950/75 shadow-sm backdrop-blur-sm">
                        <div class="mb-1 uppercase tracking-[0.16em] text-emerald-900/55">Mõõtkava</div>
                        <div class="flex items-center gap-2">
                          <div
                            class="relative h-2 rounded-full bg-emerald-700/75"
                            :style="{ width: `${scaleBarWidthPx}px` }"
                          >
                            <span class="absolute -left-px -top-1.5 h-5 w-px bg-emerald-900/55"></span>
                            <span class="absolute -right-px -top-1.5 h-5 w-px bg-emerald-900/55"></span>
                          </div>
                          <span class="font-semibold text-foreground">1 m</span>
                        </div>
                      </div>

                      <article
                        v-for="bed in plannerBeds"
                        :key="bed.id"
                        class="group absolute transition-transform duration-150"
                        :class="[
                          draggingBedId === bed.id ? 'z-30 scale-[1.02]' : 'z-10 hover:z-20 hover:-translate-y-1',
                        ]"
                        :style="plannerBedStyle(bed)"
                        data-bed-shape="true"
                        @pointerdown="startDragging(bed, $event)"
                        @mouseenter="showBedInfo(bed.id)"
                        @mouseleave="hideBedInfo(bed.id)"
                        @click="focusBedDetails(bed.id)"
                      >
                        <div class="relative z-10">
                          <div
                            class="grid place-content-center gap-[3px] rounded-[0.9rem] transition"
                            :class="selectedBed?.id === bed.id ? 'bg-emerald-200/30 ring-2 ring-emerald-400/55 ring-offset-4 ring-offset-[#eef4e6]' : ''"
                            :style="bedPreviewGridStyle(bed)"
                          >
                            <template v-for="(rowData, r) in getBedVisibleLayout(bed)" :key="`plan-row-${bed.id}-${r}`">
                              <span
                                v-for="(_, c) in rowData"
                                :key="`plan-cell-${bed.id}-${r}-${c}`"
                                class="rounded-[4px] border"
                                :class="rowData[c] === 1 ? 'bg-[linear-gradient(180deg,rgba(145,101,67,0.95),rgba(109,76,49,0.98))] border-amber-900/15' : 'bg-transparent border-transparent'"
                              />
                            </template>
                          </div>

                          <div
                            v-if="showPlannerLabels"
                            class="pointer-events-none absolute left-1/2 top-full z-20 mt-2 -translate-x-1/2 rounded-full bg-white/88 px-2.5 py-1 text-[11px] font-semibold text-foreground shadow-sm backdrop-blur-sm"
                          >
                            {{ bed.name }}
                          </div>

                          <div
                            v-if="hoveredBedId === bed.id"
                            class="absolute left-1/2 top-full z-30 mt-3 hidden w-44 -translate-x-1/2 rounded-[1.2rem] border border-emerald-900/10 bg-white/92 p-3 text-left shadow-lg backdrop-blur-sm md:block"
                          >
                            <p class="truncate text-sm font-semibold text-foreground">{{ bed.name }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ bed.location || 'Asukoht lisamata' }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-[11px]">
                              <div class="rounded-xl bg-muted/60 px-2 py-2">
                                <div class="text-muted-foreground">Mõõt</div>
                                <div class="mt-1 font-semibold text-foreground">{{ formatCentimeters(getBedPhysicalWidthCm(bed)) }} × {{ formatCentimeters(getBedPhysicalHeightCm(bed)) }}</div>
                              </div>
                              <div class="rounded-xl bg-muted/60 px-2 py-2">
                                <div class="text-muted-foreground">Taimi</div>
                                <div class="mt-1 font-semibold text-foreground">{{ bed.plants.length }}</div>
                              </div>
                            </div>
                            <div class="mt-3 grid gap-2">
                              <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-[11px] font-semibold text-primary transition hover:bg-primary/15"
                                @click.stop="focusBedDetails(bed.id)"
                              >
                                Ava vaade
                              </button>
                              <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-border/70 bg-background px-3 py-2 text-[11px] font-semibold text-foreground transition hover:bg-muted"
                                @click.stop="openBedPage(bed.id)"
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
                        :class="draggingObjectId === object.id ? 'z-30 scale-[1.02]' : 'z-10 hover:z-20 hover:-translate-y-1'"
                        :style="plannerObjectStyle(object)"
                        data-object-shape="true"
                        @pointerdown="startObjectDragging(object, $event)"
                        @mouseenter="showObjectInfo(object.id)"
                        @mouseleave="hideObjectInfo(object.id)"
                        @click.stop="focusObjectDetails(object.id)"
                      >
                        <div
                          class="relative flex h-full w-full items-center justify-center"
                          :class="[
                            objectClass(object.type),
                            selectedObject?.id === object.id ? 'ring-2 ring-primary/50 ring-offset-4 ring-offset-[#eef4e6]' : '',
                          ]"
                        >
                          <span class="material-symbols-outlined text-[1.45rem] text-white/95">
                            {{ objectTypeIcon(object.type) }}
                          </span>

                          <div
                            v-if="showPlannerLabels"
                            class="pointer-events-none absolute left-1/2 top-full z-20 mt-2 -translate-x-1/2 rounded-full bg-white/88 px-2.5 py-1 text-[11px] font-semibold text-foreground shadow-sm backdrop-blur-sm"
                          >
                            {{ object.name }}
                          </div>

                          <div
                            v-if="selectedObject?.id === object.id"
                            class="absolute left-1/2 top-full z-30 mt-3 hidden w-56 -translate-x-1/2 rounded-[1.2rem] border border-primary/15 bg-white/95 p-3 text-left shadow-lg backdrop-blur-sm lg:block"
                            @click.stop
                          >
                            <div class="flex items-start justify-between gap-2">
                              <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-foreground">{{ object.name }}</p>
                                <p class="mt-1 text-[11px] text-muted-foreground">{{ formatCentimeters(object.width) }} × {{ formatCentimeters(object.height) }}</p>
                              </div>
                              <span class="material-symbols-outlined rounded-full bg-primary/10 p-1.5 text-[18px] text-primary">
                                {{ objectTypeIcon(object.type) }}
                              </span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-[11px]">
                              <button
                                type="button"
                                class="rounded-xl border border-border/70 bg-background px-2 py-2 text-left transition hover:bg-muted"
                                @click.stop="nudgeSelectedObjectSize('width', -0.1)"
                              >
                                <div class="text-muted-foreground">Laius</div>
                                <div class="mt-1 font-semibold text-foreground">- 0.1 m</div>
                              </button>
                              <button
                                type="button"
                                class="rounded-xl border border-border/70 bg-background px-2 py-2 text-left transition hover:bg-muted"
                                @click.stop="nudgeSelectedObjectSize('width', 0.1)"
                              >
                                <div class="text-muted-foreground">Laius</div>
                                <div class="mt-1 font-semibold text-foreground">+ 0.1 m</div>
                              </button>
                              <button
                                type="button"
                                class="rounded-xl border border-border/70 bg-background px-2 py-2 text-left transition hover:bg-muted"
                                @click.stop="nudgeSelectedObjectSize('height', -0.1)"
                              >
                                <div class="text-muted-foreground">Kõrgus</div>
                                <div class="mt-1 font-semibold text-foreground">- 0.1 m</div>
                              </button>
                              <button
                                type="button"
                                class="rounded-xl border border-border/70 bg-background px-2 py-2 text-left transition hover:bg-muted"
                                @click.stop="nudgeSelectedObjectSize('height', 0.1)"
                              >
                                <div class="text-muted-foreground">Kõrgus</div>
                                <div class="mt-1 font-semibold text-foreground">+ 0.1 m</div>
                              </button>
                            </div>

                            <button
                              type="button"
                              class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
                              @click.stop="revealSelectedObjectEditor"
                            >
                              <span class="material-symbols-outlined text-sm">straighten</span>
                              Muuda mõõte
                            </button>

                            <div class="mt-2 grid grid-cols-2 gap-2">
                              <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-full border border-border/70 bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                                @click.stop="duplicateSelectedObject"
                              >
                                <span class="material-symbols-outlined text-sm">content_copy</span>
                                Dubleeri
                              </button>
                              <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-full border border-border/70 bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                                @click.stop="rotateSelectedObject"
                              >
                                <span class="material-symbols-outlined text-sm">rotate_90_degrees_ccw</span>
                                Pööra
                              </button>
                            </div>
                          </div>

                          <div
                            v-if="hoveredObjectId === object.id && selectedObject?.id !== object.id"
                            class="absolute left-1/2 top-full z-30 mt-3 hidden w-44 -translate-x-1/2 rounded-[1.2rem] border border-emerald-900/10 bg-white/92 p-3 text-left shadow-lg backdrop-blur-sm md:block"
                          >
                            <p class="truncate text-sm font-semibold text-foreground">{{ object.name }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ objectTypeLabel(object.type) }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-[11px]">
                              <div class="rounded-xl bg-muted/60 px-2 py-2">
                                <div class="text-muted-foreground">Mõõt</div>
                                <div class="mt-1 font-semibold text-foreground">{{ formatCentimeters(object.width) }} × {{ formatCentimeters(object.height) }}</div>
                              </div>
                              <div class="rounded-xl bg-muted/60 px-2 py-2">
                                <div class="text-muted-foreground">Tüüp</div>
                                <div class="mt-1 font-semibold text-foreground">{{ objectTypeLabel(object.type) }}</div>
                              </div>
                            </div>
                            <button
                              type="button"
                              class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-[11px] font-semibold text-primary transition hover:bg-primary/15"
                              @click.stop="focusObjectDetails(object.id)"
                            >
                              Ava vaade
                            </button>
                          </div>
                        </div>
                      </article>
                      </div>
                    </div>
                  </div>

                  <div
                    v-if="selectedObject"
                    ref="selectedObjectPanel"
                    class="mt-4 rounded-[1.5rem] border border-border/80 bg-card/95 p-4 shadow-lg backdrop-blur sticky bottom-20 z-20 xl:static xl:bottom-auto xl:z-auto xl:shadow-sm xl:backdrop-blur-0"
                    :class="highlightSelectedObjectPanel ? 'ring-2 ring-primary/35 ring-offset-2 ring-offset-background transition' : ''"
                  >
                    <div class="mb-3 flex justify-center xl:hidden">
                      <span class="h-1.5 w-12 rounded-full bg-foreground/15"></span>
                    </div>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Valitud aiaelement</p>
                        <h4 class="mt-1 text-lg font-semibold text-foreground">{{ selectedObject.name }}</h4>
                        <p class="mt-1 text-sm text-muted-foreground">{{ objectTypeLabel(selectedObject.type) }}</p>
                      </div>
                      <div class="flex flex-wrap items-center gap-2" data-no-drag="true">
                        <button
                          type="button"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background text-foreground transition hover:bg-muted xl:hidden"
                          @click="clearSelectionDetails"
                        >
                          <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                          @click="duplicateSelectedObject"
                        >
                          <span class="material-symbols-outlined text-sm">content_copy</span>
                          Dubleeri
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                          @click="rotateSelectedObject"
                        >
                          <span class="material-symbols-outlined text-sm">rotate_90_degrees_ccw</span>
                          Pööra
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                          @click="deleteSelectedObject"
                        >
                          Eemalda
                        </button>
                      </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Tüüp</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">{{ objectTypeLabel(selectedObject.type) }}</p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Mõõt</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">{{ formatCentimeters(selectedObject.width) }} × {{ formatCentimeters(selectedObject.height) }}</p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Asukoht</p>
                        <p class="mt-2 text-sm font-semibold text-foreground">{{ getObjectPosition(selectedObject).x }}, {{ getObjectPosition(selectedObject).y }}</p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3 sm:col-span-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Kiirtoimingud</p>
                        <p class="mt-2 text-sm text-foreground/80">Dubleeri sarnaste objektide loomiseks või pööra see 90 kraadi võrra, et paigutus läheks aias kiiremini paika.</p>
                      </div>
                    </div>

                    <div class="mt-4 rounded-[1.35rem] border border-border/70 bg-background/70 p-4">
                      <div class="mb-3">
                        <p class="text-sm font-semibold text-foreground">Muuda mõõte</p>
                        <p class="mt-1 text-xs text-muted-foreground">Sisesta mõõdud meetrites. Süsteem salvestab need taustal sentimeetrites.</p>
                      </div>

                      <div class="grid gap-3 md:grid-cols-3">
                        <label class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm">
                          <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Nimi</span>
                          <input
                            v-model="objectForm.name"
                            type="text"
                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                            maxlength="120"
                          />
                        </label>

                        <label class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm">
                          <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Laius (m)</span>
                          <input
                            ref="objectWidthInput"
                            v-model="objectForm.widthMeters"
                            type="number"
                            min="0.5"
                            max="50"
                            step="0.1"
                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                          />
                        </label>

                        <label class="rounded-2xl border border-border/70 bg-card/85 px-3 py-3 text-sm">
                          <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Kõrgus (m)</span>
                          <input
                            v-model="objectForm.heightMeters"
                            type="number"
                            min="0.5"
                            max="50"
                            step="0.1"
                            class="w-full bg-transparent text-sm font-medium text-foreground outline-none"
                          />
                        </label>
                      </div>

                      <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/15"
                          :disabled="objectForm.processing"
                          @click="saveSelectedObject"
                        >
                          Salvesta mõõdud
                        </button>
                        <p v-if="objectForm.errors.width || objectForm.errors.height || objectForm.errors.name" class="text-xs text-rose-600">
                          {{ objectForm.errors.name || objectForm.errors.width || objectForm.errors.height }}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div
                    v-else-if="selectedBed"
                    ref="selectedBedPanel"
                    class="mt-4 rounded-[1.5rem] border border-border/80 bg-card/95 p-4 shadow-lg backdrop-blur sticky bottom-20 z-20 xl:static xl:bottom-auto xl:z-auto xl:shadow-sm xl:backdrop-blur-0"
                    :class="highlightSelectedBedPanel ? 'ring-2 ring-primary/35 ring-offset-2 ring-offset-background transition' : ''"
                  >
                    <div class="mb-3 flex justify-center xl:hidden">
                      <span class="h-1.5 w-12 rounded-full bg-foreground/15"></span>
                    </div>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Valitud peenar</p>
                        <h4 class="mt-1 text-lg font-semibold text-foreground">{{ selectedBed.name }}</h4>
                        <p class="mt-1 text-sm text-muted-foreground">
                          {{ selectedBed.location || 'Asukoht lisamata' }}
                        </p>
                      </div>
                      <div class="flex items-center gap-2" data-no-drag="true">
                        <button
                          type="button"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background text-foreground transition hover:bg-muted xl:hidden"
                          @click="clearSelectionDetails"
                        >
                          <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                        <button
                          type="button"
                          class="flex h-10 w-10 items-center justify-center rounded-full border border-primary/10 bg-white/90 transition hover:scale-105 hover:bg-primary/5"
                          :class="isFavoriteBed(selectedBed.id) ? 'text-rose-600 shadow-sm' : 'text-foreground/45'"
                          @click.prevent.stop="toggleFavoriteBed(selectedBed.id)"
                        >
                          <span
                            class="material-symbols-outlined text-[20px] leading-none"
                            :style="
                              isFavoriteBed(selectedBed.id)
                                ? { fontVariationSettings: `'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24` }
                                : { fontVariationSettings: `'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24` }
                            "
                          >
                            favorite
                          </span>
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
                          @click="goToSelectedBed"
                        >
                          Ava peenar
                          <span class="material-symbols-outlined text-base">arrow_forward</span>
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                          @click="editBed(selectedBed.id)"
                        >
                          Muuda
                        </button>
                        <button
                          type="button"
                          class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                          @click="deleteBed(selectedBed.id, selectedBed.name)"
                        >
                          Kustuta
                        </button>
                      </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Kuju</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">
                          {{ getBedHeightInCells(selectedBed) }} × {{ getBedWidthInCells(selectedBed) }} ruutu
                        </p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Päris mõõt</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">
                          {{ formatCentimeters(getBedPhysicalWidthCm(selectedBed)) }} × {{ formatCentimeters(getBedPhysicalHeightCm(selectedBed)) }}
                        </p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Taimi</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">{{ selectedBed.plants.length }}</p>
                      </div>
                      <div class="rounded-2xl border border-border/70 bg-background/70 px-3 py-3 sm:col-span-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Järgmine samm</p>
                        <p class="mt-2 text-sm text-foreground/80">Ava peenar ja paiguta taimed ruutudesse. Aiaplaanil kuvatakse peenar nüüd sama ruudu mõõdu järgi nagu selle tegelik kuju.</p>
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
          @click="router.visit('/map/beds/new')"
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
