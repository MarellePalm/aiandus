import { GARDEN_SHAPE_CELL_CM } from '@/pages/map/constants';

export function gardenShapeMaskCols(widthCm: number): number {
    return Math.max(1, Math.ceil(widthCm / GARDEN_SHAPE_CELL_CM));
}

export function gardenShapeMaskRows(heightCm: number): number {
    return Math.max(1, Math.ceil(heightCm / GARDEN_SHAPE_CELL_CM));
}

export function createDefaultGardenShapeMask(
    widthCm: number,
    heightCm: number,
): number[][] {
    const rows = gardenShapeMaskRows(heightCm);
    const cols = gardenShapeMaskCols(widthCm);

    return Array.from({ length: rows }, () =>
        Array.from({ length: cols }, () => 1),
    );
}

export function resizeGardenShapeMask(
    mask: number[][] | null | undefined,
    widthCm: number,
    heightCm: number,
): number[][] {
    const rows = gardenShapeMaskRows(heightCm);
    const cols = gardenShapeMaskCols(widthCm);
    const next = createDefaultGardenShapeMask(widthCm, heightCm);

    if (!mask?.length) {
        return next;
    }

    for (let y = 0; y < rows; y += 1) {
        for (let x = 0; x < cols; x += 1) {
            if (mask[y]?.[x] !== undefined) {
                next[y][x] = mask[y][x] === 1 ? 1 : 0;
            }
        }
    }

    return next;
}

export function gardenShapeMaskCmFromForm(
    widthMeters: number,
    heightMeters: number,
) {
    return {
        widthCm: Math.max(1, Math.round(Number(widthMeters || 0) * 100)),
        heightCm: Math.max(1, Math.round(Number(heightMeters || 0) * 100)),
    };
}
