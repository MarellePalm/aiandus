import { INAADRESS_GAZETTEER_URL } from '@/pages/map/constants';
import type { GardenAddressSearchResult } from '@/pages/map/types';

type InAddressApiItem = {
    pikkaadress?: string;
    ipikkaadress?: string;
    aadresstekst?: string;
    maakond?: string;
    viitepunkt_b?: string;
    viitepunkt_l?: string;
    adr_id?: string | number;
    tunnus?: string;
    g_boundingbox?: string;
    liikVal?: string;
};

export async function fetchGardenAddressResults(
    query: string,
): Promise<GardenAddressSearchResult[]> {
    const url = `${INAADRESS_GAZETTEER_URL}?address=${encodeURIComponent(query)}&results=5&appartment=0`;
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error('Address search failed');
    }

    const data = (await response.json()) as {
        addresses?: InAddressApiItem[];
    };

    return (data.addresses ?? [])
        .map((item, index) => {
            const lat = Number(item.viitepunkt_b);
            const lng = Number(item.viitepunkt_l);

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return null;
            }

            const label = String(
                item.pikkaadress ||
                    item.ipikkaadress ||
                    item.aadresstekst ||
                    'Aadress',
            );

            return {
                id: `${item.adr_id ?? index}-${item.tunnus ?? 'addr'}`,
                label,
                county: String(item.maakond ?? ''),
                lat,
                lng,
                gBoundingBox: item.g_boundingbox?.trim() || null,
            };
        })
        .filter((item): item is GardenAddressSearchResult => item !== null);
}

export function gardenSetupChoiceClass(active: boolean): string {
    const base =
        'flex flex-col gap-1.5 rounded-2xl border p-4 text-left transition';

    if (active) {
        return `${base} border-primary/30 bg-primary/5 ring-2 ring-primary/15`;
    }

    return `${base} border-border/70 bg-card/85 hover:border-primary/20 hover:bg-muted/40`;
}
