export type GardenLocationDimensions = {
    centerLat: number;
    centerLng: number;
    widthMeters: number;
    heightMeters: number;
    source: 'cadastral' | 'address_bbox';
    detail?: string;
};

const CADASTRAL_WFS_URL = 'https://gsavalik.envir.ee/geoserver/kataster/wfs';
const MIN_GARDEN_DIM_M = 5;
const MAX_GARDEN_DIM_M = 1000;
/** Aadressi bbox suurem kui see → ei kasuta (tänav jms). */
const MAX_ADDRESS_BBOX_DIM_M = 250;

type LatLng = { lat: number; lng: number };

function clampDim(value: number): number {
    return Math.min(
        MAX_GARDEN_DIM_M,
        Math.max(MIN_GARDEN_DIM_M, Math.round(value * 100) / 100),
    );
}

function metersPerDegreeLng(lat: number): number {
    return 111_320 * Math.max(0.01, Math.cos((lat * Math.PI) / 180));
}

function boundsFromRing(ring: LatLng[]) {
    let minLat = Infinity;
    let maxLat = -Infinity;
    let minLng = Infinity;
    let maxLng = -Infinity;

    for (const p of ring) {
        minLat = Math.min(minLat, p.lat);
        maxLat = Math.max(maxLat, p.lat);
        minLng = Math.min(minLng, p.lng);
        maxLng = Math.max(maxLng, p.lng);
    }

    return { minLat, maxLat, minLng, maxLng };
}

function dimensionsFromBounds(bounds: ReturnType<typeof boundsFromRing>) {
    const centerLat = (bounds.minLat + bounds.maxLat) / 2;
    const centerLng = (bounds.minLng + bounds.maxLng) / 2;
    const heightMeters = (bounds.maxLat - bounds.minLat) * 111_320;
    const widthMeters =
        (bounds.maxLng - bounds.minLng) * metersPerDegreeLng(centerLat);

    return {
        centerLat,
        centerLng,
        widthMeters: clampDim(widthMeters),
        heightMeters: clampDim(heightMeters),
    };
}

function parseEstoniaLatLngPair(a: number, b: number): LatLng {
    const aLooksLat = a >= 57 && a <= 61 && b >= 21 && b <= 29;
    const bLooksLat = b >= 57 && b <= 61 && a >= 21 && a <= 29;

    if (aLooksLat) {
        return { lat: a, lng: b };
    }

    if (bLooksLat) {
        return { lat: b, lng: a };
    }

    return { lat: a, lng: b };
}

function parsePosList(posList: string): LatLng[] {
    const nums = posList
        .trim()
        .split(/[\s,]+/)
        .map(Number)
        .filter(Number.isFinite);

    const ring: LatLng[] = [];
    for (let i = 0; i + 1 < nums.length; i += 2) {
        ring.push(parseEstoniaLatLngPair(nums[i], nums[i + 1]));
    }

    return ring;
}

function pointInRing(lng: number, lat: number, ring: LatLng[]): boolean {
    let inside = false;

    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const xi = ring[i].lng;
        const yi = ring[i].lat;
        const xj = ring[j].lng;
        const yj = ring[j].lat;

        const dy = yj - yi;
        const intersects =
            yi > lat !== yj > lat &&
            (dy === 0 ? false : lng < ((xj - xi) * (lat - yi)) / dy + xi);

        if (intersects) {
            inside = !inside;
        }
    }

    return inside;
}

function ringAreaApprox(ring: LatLng[]): number {
    const b = boundsFromRing(ring);
    return (
        (b.maxLat - b.minLat) *
        111_320 *
        ((b.maxLng - b.minLng) * metersPerDegreeLng((b.minLat + b.maxLat) / 2))
    );
}

function parseCadastralWfsXml(
    xml: string,
): { ring: LatLng[]; tunnus: string | null; lAddress: string | null }[] {
    const doc = new DOMParser().parseFromString(xml, 'text/xml');
    const features: {
        ring: LatLng[];
        tunnus: string | null;
        lAddress: string | null;
    }[] = [];

    for (const posList of doc.querySelectorAll('posList')) {
        const ring = parsePosList(posList.textContent ?? '');
        if (ring.length < 3) {
            continue;
        }

        let featureRoot: Element | null = posList;
        while (
            featureRoot &&
            featureRoot.localName !== 'ky_kehtiv' &&
            featureRoot.parentElement
        ) {
            featureRoot = featureRoot.parentElement;
        }

        const tunnus =
            featureRoot?.querySelector('tunnus')?.textContent?.trim() ?? null;
        const lAddress =
            featureRoot?.querySelector('l_aadress')?.textContent?.trim() ??
            null;

        features.push({ ring, tunnus, lAddress });
    }

    return features;
}

export async function fetchCadastralGardenDimensions(
    lat: number,
    lng: number,
): Promise<GardenLocationDimensions | null> {
    const buffer = 0.0018;
    const params = new URLSearchParams({
        service: 'WFS',
        version: '2.0.0',
        request: 'GetFeature',
        typeNames: 'kataster:ky_kehtiv',
        count: '25',
        srsName: 'EPSG:4326',
        bbox: `${lng - buffer},${lat - buffer},${lng + buffer},${lat + buffer},EPSG:4326`,
    });

    const response = await fetch(`${CADASTRAL_WFS_URL}?${params.toString()}`);
    if (!response.ok) {
        return null;
    }

    const features = parseCadastralWfsXml(await response.text());
    const containing = features.filter((f) => pointInRing(lng, lat, f.ring));

    const pick =
        containing.length > 0
            ? containing.sort(
                  (a, b) => ringAreaApprox(a.ring) - ringAreaApprox(b.ring),
              )[0]
            : null;

    if (!pick) {
        return null;
    }

    const dims = dimensionsFromBounds(boundsFromRing(pick.ring));

    return {
        ...dims,
        source: 'cadastral',
        detail: [pick.lAddress, pick.tunnus].filter(Boolean).join(' · '),
    };
}

/** In-ADS g_boundingbox: "lat,lng lat,lng ..." */
export function dimensionsFromAddressBoundingBox(
    gBoundingBox: string,
): GardenLocationDimensions | null {
    const parts = gBoundingBox
        .trim()
        .split(/\s+/)
        .map((pair) => pair.split(',').map(Number))
        .filter((pair) => pair.length === 2 && pair.every(Number.isFinite));

    if (parts.length < 4) {
        return null;
    }

    const ring: LatLng[] = parts.map(([lat, lng]) => ({ lat, lng }));
    const bounds = boundsFromRing(ring);
    const dims = dimensionsFromBounds(bounds);

    if (
        dims.widthMeters > MAX_ADDRESS_BBOX_DIM_M ||
        dims.heightMeters > MAX_ADDRESS_BBOX_DIM_M
    ) {
        return null;
    }

    return {
        ...dims,
        centerLat: dims.centerLat,
        centerLng: dims.centerLng,
        source: 'address_bbox',
    };
}
