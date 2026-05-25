import type { Bed } from '@/pages/map/types';

export function parseBedCreatedAt(bed: Bed): number {
    if (!bed.created_at) return 0;
    const t = new Date(bed.created_at).getTime();
    return Number.isNaN(t) ? 0 : t;
}

export function sortBedsForPlanner(beds: Bed[], sortKey: string): Bed[] {
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

export function getInitialFocusedBedId(beds: Bed[]): number | null {
    if (typeof window === 'undefined') return null;

    const value = new URLSearchParams(window.location.search).get('bed');
    if (!value) return null;

    const bedId = Number(value);
    if (!Number.isInteger(bedId)) return null;

    return beds.some((bed) => bed.id === bedId) ? bedId : null;
}
