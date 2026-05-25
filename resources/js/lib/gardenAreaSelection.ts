export type ParcelBounds = {
    centerLat: number;
    centerLng: number;
    widthMeters: number;
    heightMeters: number;
};

export type NormalizedSelection = {
    u0: number;
    v0: number;
    u1: number;
    v1: number;
};

export type NormalizedPoint = { u: number; v: number };

export type LatLngPoint = { lat: number; lng: number };

export type GardenBoundsFromSelection = {
    centerLat: number;
    centerLng: number;
    widthMeters: number;
    heightMeters: number;
};

export type GardenAreaApplyResult = {
    bounds: GardenBoundsFromSelection;
    shapeMask: number[][] | null;
};

export type AreaPickerMode = 'rectangle' | 'polygon';

const MIN_GARDEN_DIM_M = 5;
const SHAPE_CELL_CM = 1000;
/** Ortofoto algvaate min/max ulatus keskpunkti ümber (m). */
const ORTOPHOTO_FOCUS_MIN_SPAN_M = 22;
const ORTOPHOTO_FOCUS_MAX_SPAN_M = 56;

/** Suumi ortofoto nii, et aed oleks loetav, mitte kogu krunt ühe ekraaniga. */
export function ortophotoFocusSpanMeters(
    widthMeters: number,
    heightMeters: number,
): number {
    const maxDim = Math.max(widthMeters, heightMeters, MIN_GARDEN_DIM_M);
    const minDim = Math.min(
        Math.max(widthMeters, MIN_GARDEN_DIM_M),
        Math.max(heightMeters, MIN_GARDEN_DIM_M),
    );
    const padded = Math.max(
        maxDim * 1.3,
        minDim * 1.55,
        ORTOPHOTO_FOCUS_MIN_SPAN_M,
    );

    return Math.min(
        ORTOPHOTO_FOCUS_MAX_SPAN_M,
        Math.round(padded * 10) / 10,
    );
}

function metersPerDegreeLng(lat: number): number {
    return 111_320 * Math.max(0.01, Math.cos((lat * Math.PI) / 180));
}

function parcelNorthWest(parcel: ParcelBounds) {
    const metersPerLng = metersPerDegreeLng(parcel.centerLat);
    return {
        lat: parcel.centerLat + parcel.heightMeters / 2 / 111_320,
        lng: parcel.centerLng - parcel.widthMeters / 2 / metersPerLng,
    };
}

export function clampNormalizedSelection(
    sel: NormalizedSelection,
    minSpan = 0.04,
): NormalizedSelection {
    let u0 = Math.max(0, Math.min(1, Math.min(sel.u0, sel.u1)));
    let u1 = Math.max(0, Math.min(1, Math.max(sel.u0, sel.u1)));
    let v0 = Math.max(0, Math.min(1, Math.min(sel.v0, sel.v1)));
    let v1 = Math.max(0, Math.min(1, Math.max(sel.v0, sel.v1)));

    if (u1 - u0 < minSpan) {
        const mid = (u0 + u1) / 2;
        u0 = Math.max(0, mid - minSpan / 2);
        u1 = Math.min(1, mid + minSpan / 2);
    }

    if (v1 - v0 < minSpan) {
        const mid = (v0 + v1) / 2;
        v0 = Math.max(0, mid - minSpan / 2);
        v1 = Math.min(1, mid + minSpan / 2);
    }

    return { u0, v0, u1, v1 };
}

/** Anchor (aadress/geolokatsioon) parceli koordinaatides 0…1 (ülevalt vasakult). */
export function anchorToNormalized(
    parcel: ParcelBounds,
    anchorLat: number,
    anchorLng: number,
): { u: number; v: number } {
    const nw = parcelNorthWest(parcel);
    const metersPerLng = metersPerDegreeLng(parcel.centerLat);
    const u = (anchorLng - nw.lng) / (parcel.widthMeters / metersPerLng);
    const v = (nw.lat - anchorLat) / (parcel.heightMeters / 111_320);

    return {
        u: Math.max(0, Math.min(1, u)),
        v: Math.max(0, Math.min(1, v)),
    };
}

/** Vaikimisi ~sizeM × sizeM ala ümber aadressi punkti. */
/** Ristküliku valik → neli nurka (polügoon). */
export function selectionToPolygonPoints(
    sel: NormalizedSelection,
): NormalizedPoint[] {
    const { u0, v0, u1, v1 } = clampNormalizedSelection(sel);

    return [
        { u: u0, v: v0 },
        { u: u1, v: v0 },
        { u: u1, v: v1 },
        { u: u0, v: v1 },
    ];
}

export function defaultGardenSelectionAroundAnchor(
    parcel: ParcelBounds,
    anchorLat: number,
    anchorLng: number,
    sizeM = 40,
): NormalizedSelection {
    const { u, v } = anchorToNormalized(parcel, anchorLat, anchorLng);
    const minU = Math.max(0, sizeM / parcel.widthMeters);
    const minV = Math.max(0, sizeM / parcel.heightMeters);

    return clampNormalizedSelection({
        u0: u - minU / 2,
        v0: v - minV / 2,
        u1: u + minU / 2,
        v1: v + minV / 2,
    });
}

export function parcelUvToLatLng(
    parcel: ParcelBounds,
    u: number,
    v: number,
): { lat: number; lng: number } {
    const nw = parcelNorthWest(parcel);
    const metersPerLng = metersPerDegreeLng(parcel.centerLat);

    return {
        lat: nw.lat - v * (parcel.heightMeters / 111_320),
        lng: nw.lng + u * (parcel.widthMeters / metersPerLng),
    };
}

export function latLngToParcelUv(
    parcel: ParcelBounds,
    lat: number,
    lng: number,
): NormalizedPoint {
    const nw = parcelNorthWest(parcel);
    const metersPerLng = metersPerDegreeLng(parcel.centerLat);

    return {
        u: Math.max(
            0,
            Math.min(1, (lng - nw.lng) / (parcel.widthMeters / metersPerLng)),
        ),
        v: Math.max(
            0,
            Math.min(1, (nw.lat - lat) / (parcel.heightMeters / 111_320)),
        ),
    };
}

function gardenNorthWest(garden: GardenBoundsFromSelection) {
    const metersPerLng = metersPerDegreeLng(garden.centerLat);

    return {
        lat: garden.centerLat + garden.heightMeters / 2 / 111_320,
        lng: garden.centerLng - garden.widthMeters / 2 / metersPerLng,
    };
}

export function pointInPolygon(
    u: number,
    v: number,
    ring: NormalizedPoint[],
): boolean {
    if (ring.length < 3) {
        return false;
    }

    let inside = false;

    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const xi = ring[i].u;
        const yi = ring[i].v;
        const xj = ring[j].u;
        const yj = ring[j].v;
        const dy = yj - yi;
        const intersects =
            yi > v !== yj > v &&
            (dy === 0 ? false : u < ((xj - xi) * (v - yi)) / dy + xi);

        if (intersects) {
            inside = !inside;
        }
    }

    return inside;
}

export function polygonToGardenBounds(
    parcel: ParcelBounds,
    points: NormalizedPoint[],
): GardenBoundsFromSelection {
    const coords = points.map((p) => parcelUvToLatLng(parcel, p.u, p.v));
    const lats = coords.map((c) => c.lat);
    const lngs = coords.map((c) => c.lng);
    const minLat = Math.min(...lats);
    const maxLat = Math.max(...lats);
    const minLng = Math.min(...lngs);
    const maxLng = Math.max(...lngs);
    const centerLat = (minLat + maxLat) / 2;
    const metersPerLng = metersPerDegreeLng(centerLat);

    const widthMeters = Math.max(
        MIN_GARDEN_DIM_M,
        Math.round((maxLng - minLng) * metersPerLng * 100) / 100,
    );
    const heightMeters = Math.max(
        MIN_GARDEN_DIM_M,
        Math.round((maxLat - minLat) * 111_320 * 100) / 100,
    );

    return {
        centerLat,
        centerLng: (minLng + maxLng) / 2,
        widthMeters,
        heightMeters,
    };
}

export function polygonToShapeMask(
    parcel: ParcelBounds,
    points: NormalizedPoint[],
): number[][] | null {
    if (points.length < 3) {
        return null;
    }

    const bounds = polygonToGardenBounds(parcel, points);
    const widthCm = Math.max(1, Math.round(bounds.widthMeters * 100));
    const heightCm = Math.max(1, Math.round(bounds.heightMeters * 100));
    const rows = Math.max(1, Math.ceil(heightCm / SHAPE_CELL_CM));
    const cols = Math.max(1, Math.ceil(widthCm / SHAPE_CELL_CM));
    const gNw = gardenNorthWest(bounds);
    const gMetersPerLng = metersPerDegreeLng(bounds.centerLat);
    const mask: number[][] = [];
    let hasInactive = false;

    for (let y = 0; y < rows; y += 1) {
        const row: number[] = [];
        for (let x = 0; x < cols; x += 1) {
            const lat =
                gNw.lat - ((y + 0.5) / rows) * (bounds.heightMeters / 111_320);
            const lng =
                gNw.lng +
                ((x + 0.5) / cols) * (bounds.widthMeters / gMetersPerLng);
            const uv = latLngToParcelUv(parcel, lat, lng);
            const active = pointInPolygon(uv.u, uv.v, points) ? 1 : 0;
            if (active === 0) {
                hasInactive = true;
            }
            row.push(active);
        }
        mask.push(row);
    }

    return hasInactive ? mask : null;
}

/** Regulaarne hulknurk (nt kuusnurk) keskpunkti ümber parceli koordinaatides. */
export function regularPolygonInParcel(
    centerU: number,
    centerV: number,
    radiusM: number,
    parcel: ParcelBounds,
    sides: number,
): NormalizedPoint[] {
    const center = parcelUvToLatLng(parcel, centerU, centerV);
    const metersPerLng = metersPerDegreeLng(center.lat);
    const latRadius = radiusM / 111_320;
    const lngRadius = radiusM / metersPerLng;
    const points: NormalizedPoint[] = [];

    for (let i = 0; i < sides; i += 1) {
        const angle = (Math.PI * 2 * i) / sides - Math.PI / 2;
        const lat = center.lat + Math.sin(angle) * latRadius;
        const lng = center.lng + Math.cos(angle) * lngRadius;
        points.push(latLngToParcelUv(parcel, lat, lng));
    }

    return points;
}

export function rectangleToApplyResult(
    parcel: ParcelBounds,
    sel: NormalizedSelection,
): GardenAreaApplyResult {
    return {
        bounds: selectionToGardenBounds(parcel, sel),
        shapeMask: null,
    };
}

export function polygonToApplyResult(
    parcel: ParcelBounds,
    points: NormalizedPoint[],
): GardenAreaApplyResult | null {
    if (points.length < 3) {
        return null;
    }

    return {
        bounds: polygonToGardenBounds(parcel, points),
        shapeMask: polygonToShapeMask(parcel, points),
    };
}

/** Ortofoto eelvaate raam ümber valitud asukoha (mitte kogu krunt). */
export function localMapFrameAroundAnchor(
    anchorLat: number,
    anchorLng: number,
    spanMeters = 180,
): ParcelBounds {
    const span = Math.max(MIN_GARDEN_DIM_M * 4, spanMeters);

    return {
        centerLat: anchorLat,
        centerLng: anchorLng,
        widthMeters: span,
        heightMeters: span,
    };
}

export function defaultPolygonAroundAnchor(
    anchorLat: number,
    anchorLng: number,
    sizeM = 40,
): LatLngPoint[] {
    const half = sizeM / 2;
    const metersPerLng = metersPerDegreeLng(anchorLat);
    const dLat = half / 111_320;
    const dLng = half / metersPerLng;

    return [
        { lat: anchorLat + dLat, lng: anchorLng - dLng },
        { lat: anchorLat + dLat, lng: anchorLng + dLng },
        { lat: anchorLat - dLat, lng: anchorLng + dLng },
        { lat: anchorLat - dLat, lng: anchorLng - dLng },
    ];
}

export function latLngRingToGardenBounds(
    points: LatLngPoint[],
): GardenBoundsFromSelection {
    const lats = points.map((p) => p.lat);
    const lngs = points.map((p) => p.lng);
    const minLat = Math.min(...lats);
    const maxLat = Math.max(...lats);
    const minLng = Math.min(...lngs);
    const maxLng = Math.max(...lngs);
    const centerLat = (minLat + maxLat) / 2;
    const metersPerLng = metersPerDegreeLng(centerLat);

    const widthMeters = Math.max(
        MIN_GARDEN_DIM_M,
        Math.round((maxLng - minLng) * metersPerLng * 100) / 100,
    );
    const heightMeters = Math.max(
        MIN_GARDEN_DIM_M,
        Math.round((maxLat - minLat) * 111_320 * 100) / 100,
    );

    return {
        centerLat,
        centerLng: (minLng + maxLng) / 2,
        widthMeters,
        heightMeters,
    };
}

export function pointInLatLngPolygon(
    lat: number,
    lng: number,
    ring: LatLngPoint[],
): boolean {
    if (ring.length < 3) {
        return false;
    }

    const normalized = ring.map((p) => ({ u: p.lng, v: p.lat }));

    return pointInPolygon(lng, lat, normalized);
}

export function latLngPolygonToShapeMask(
    points: LatLngPoint[],
): number[][] | null {
    if (points.length < 3) {
        return null;
    }

    const bounds = latLngRingToGardenBounds(points);
    const widthCm = Math.max(1, Math.round(bounds.widthMeters * 100));
    const heightCm = Math.max(1, Math.round(bounds.heightMeters * 100));
    const rows = Math.max(1, Math.ceil(heightCm / SHAPE_CELL_CM));
    const cols = Math.max(1, Math.ceil(widthCm / SHAPE_CELL_CM));
    const gNw = gardenNorthWest(bounds);
    const gMetersPerLng = metersPerDegreeLng(bounds.centerLat);
    const mask: number[][] = [];
    let hasInactive = false;

    for (let y = 0; y < rows; y += 1) {
        const row: number[] = [];
        for (let x = 0; x < cols; x += 1) {
            const lat =
                gNw.lat - ((y + 0.5) / rows) * (bounds.heightMeters / 111_320);
            const lng =
                gNw.lng +
                ((x + 0.5) / cols) * (bounds.widthMeters / gMetersPerLng);
            const active = pointInLatLngPolygon(lat, lng, points) ? 1 : 0;
            if (active === 0) {
                hasInactive = true;
            }
            row.push(active);
        }
        mask.push(row);
    }

    return hasInactive ? mask : null;
}

/** Ristküliku nurgad geograafilistes koordinaatides (tagasiühilduvus). */
export function gardenBoundsToLatLngRing(
    centerLat: number,
    centerLng: number,
    widthMeters: number,
    heightMeters: number,
): LatLngPoint[] {
    const bounds: GardenBoundsFromSelection = {
        centerLat,
        centerLng,
        widthMeters,
        heightMeters,
    };
    const nw = gardenNorthWest(bounds);
    const metersPerLng = metersPerDegreeLng(centerLat);
    const dLat = heightMeters / 111_320;
    const dLng = widthMeters / metersPerLng;

    return [
        { lat: nw.lat, lng: nw.lng },
        { lat: nw.lat, lng: nw.lng + dLng },
        { lat: nw.lat - dLat, lng: nw.lng + dLng },
        { lat: nw.lat - dLat, lng: nw.lng },
    ];
}

export function latLngPolygonToApplyResult(
    points: LatLngPoint[],
): GardenAreaApplyResult | null {
    if (points.length < 3) {
        return null;
    }

    return {
        bounds: latLngRingToGardenBounds(points),
        shapeMask: latLngPolygonToShapeMask(points),
    };
}

export function selectionToGardenBounds(
    parcel: ParcelBounds,
    sel: NormalizedSelection,
): GardenBoundsFromSelection {
    const { u0, v0, u1, v1 } = clampNormalizedSelection(sel);
    const nw = parcelNorthWest(parcel);
    const metersPerLng = metersPerDegreeLng(parcel.centerLat);

    const widthMeters = Math.max(
        MIN_GARDEN_DIM_M,
        Math.round((u1 - u0) * parcel.widthMeters * 100) / 100,
    );
    const heightMeters = Math.max(
        MIN_GARDEN_DIM_M,
        Math.round((v1 - v0) * parcel.heightMeters * 100) / 100,
    );

    const lngWest = nw.lng + u0 * (parcel.widthMeters / metersPerLng);
    const lngEast = nw.lng + u1 * (parcel.widthMeters / metersPerLng);
    const latNorth = nw.lat - v0 * (parcel.heightMeters / 111_320);
    const latSouth = nw.lat - v1 * (parcel.heightMeters / 111_320);

    return {
        centerLat: (latNorth + latSouth) / 2,
        centerLng: (lngWest + lngEast) / 2,
        widthMeters,
        heightMeters,
    };
}
