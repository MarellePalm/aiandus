import type { LatLngPoint } from '@/lib/gardenAreaSelection';

export type PlantInBed = {
    id: number;
    name: string;
    image_url: string | null;
    position_in_bed: string | null;
};

export type BedCellBrick = {
    x: number;
    y: number;
    w?: number;
    h?: number;
    width_cm?: number;
    height_cm?: number;
    left_cm?: number;
    top_cm?: number;
    kind?: 'plantable' | 'walkway' | 'empty';
};

export type Bed = {
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
    cell_bricks?: BedCellBrick[] | null;
    plants: PlantInBed[];
};

export type BedListTabKey = 'all' | 'favorites';

export type PlantWithoutBed = {
    id: number;
    name: string;
    image_url: string | null;
    category?: { name: string; slug: string } | null;
};

export type GardenPlan = {
    id: number;
    name: string;
    width: number;
    height: number;
    unit: string;
    shape_mask: number[][] | null;
    shape_mask_cell_cm?: number;
    center_lat: number | null;
    center_lng: number | null;
    boundary_polygon: LatLngPoint[] | null;
};

export type GardenPlanSummary = {
    id: number;
    name: string;
};

export type MapViewProps = {
    gardenPlans: GardenPlanSummary[];
    gardenPlan: GardenPlan;
    beds: Bed[];
    plantsWithoutBed: PlantWithoutBed[];
};

export type DimensionFormErrors = Record<
    'width' | 'height',
    string | undefined
>;

export type ViewportPinchState = {
    startDistance: number;
    startZoom: number;
    gx: number;
    gy: number;
};

export type GardenSetupMode = 'ortophoto' | 'manual';

export type GardenAddressSearchResult = {
    id: string;
    label: string;
    county: string;
    lat: number;
    lng: number;
    gBoundingBox: string | null;
};
