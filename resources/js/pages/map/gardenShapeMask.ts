import { GARDEN_SHAPE_CELL_CM } from '@/pages/map/constants';

const SHAPE_MASK_CELL_PRESETS_CM = [25, 50, 100, 200, 500, 1000] as const;

export function gardenShapeMaskCols(
    widthCm: number,
    cellCm = GARDEN_SHAPE_CELL_CM,
): number {
    return Math.max(1, Math.round(widthCm / cellCm));
}

export function gardenShapeMaskRows(
    heightCm: number,
    cellCm = GARDEN_SHAPE_CELL_CM,
): number {
    return Math.max(1, Math.round(heightCm / cellCm));
}

export function snapShapeMaskCellCm(rawCm: number): number {
    if (!Number.isFinite(rawCm) || rawCm <= 0) {
        return GARDEN_SHAPE_CELL_CM;
    }

    let best = SHAPE_MASK_CELL_PRESETS_CM[0];
    let bestDiff = Math.abs(rawCm - best);

    for (const preset of SHAPE_MASK_CELL_PRESETS_CM) {
        const diff = Math.abs(rawCm - preset);
        if (diff < bestDiff) {
            best = preset;
            bestDiff = diff;
        }
    }

    if (bestDiff / Math.max(best, 1) <= 0.12) {
        return best;
    }

    return Math.max(10, Math.min(1000, Math.round(rawCm)));
}

export function inferShapeMaskCellCm(
    widthCm: number,
    heightCm: number,
    shapeMask: number[][] | null | undefined,
    fallbackCm = GARDEN_SHAPE_CELL_CM,
): number {
    const cols = shapeMask?.[0]?.length ?? 0;
    const rows = shapeMask?.length ?? 0;

    if (cols < 1 || rows < 1 || widthCm < 1 || heightCm < 1) {
        return fallbackCm;
    }

    for (const preset of SHAPE_MASK_CELL_PRESETS_CM) {
        if (preset > 1000) {
            continue;
        }

        const fitCols = Math.round(widthCm / preset);
        const fitRows = Math.round(heightCm / preset);

        if (fitCols < 1 || fitRows < 1) {
            continue;
        }

        const widthErr = Math.abs(fitCols * preset - widthCm);
        const heightErr = Math.abs(fitRows * preset - heightCm);

        if (widthErr <= preset * 0.06 && heightErr <= preset * 0.06) {
            return preset;
        }
    }

    const wCell = widthCm / cols;
    const hCell = heightCm / rows;

    return snapShapeMaskCellCm(Math.min(wCell, hCell));
}

export function createDefaultGardenShapeMask(
    widthCm: number,
    heightCm: number,
    cellCm = GARDEN_SHAPE_CELL_CM,
): number[][] {
    const rows = gardenShapeMaskRows(heightCm, cellCm);
    const cols = gardenShapeMaskCols(widthCm, cellCm);

    return Array.from({ length: rows }, () =>
        Array.from({ length: cols }, () => 1),
    );
}

/** Ühtlustab kuju maski mõõtmetega (nt 16 ruutu laiusel 2 m → 4 ruutu 50 cm juures). */
export function fitShapeMaskToDimensions(
    mask: number[][] | null | undefined,
    widthCm: number,
    heightCm: number,
    cellCm: number,
): number[][] {
    const targetCols = gardenShapeMaskCols(widthCm, cellCm);
    const targetRows = gardenShapeMaskRows(heightCm, cellCm);

    if (!mask?.length || !mask[0]?.length) {
        return createDefaultGardenShapeMask(widthCm, heightCm, cellCm);
    }

    const srcRows = mask.length;
    const srcCols = mask[0]?.length ?? 0;

    if (srcCols === targetCols && srcRows === targetRows) {
        return mask.map((row) => row.map((value) => (value === 1 ? 1 : 0)));
    }

    const next = Array.from({ length: targetRows }, () =>
        Array.from({ length: targetCols }, () => 0),
    );

    for (let ty = 0; ty < targetRows; ty += 1) {
        const y0 = Math.floor((ty * srcRows) / targetRows);
        const y1 = Math.min(
            srcRows,
            Math.ceil(((ty + 1) * srcRows) / targetRows),
        );

        for (let tx = 0; tx < targetCols; tx += 1) {
            const x0 = Math.floor((tx * srcCols) / targetCols);
            const x1 = Math.min(
                srcCols,
                Math.ceil(((tx + 1) * srcCols) / targetCols),
            );

            let active = false;
            for (let y = y0; y < y1; y += 1) {
                for (let x = x0; x < x1; x += 1) {
                    if (mask[y]?.[x] === 1) {
                        active = true;
                        break;
                    }
                }
                if (active) {
                    break;
                }
            }

            next[ty][tx] = active ? 1 : 0;
        }
    }

    return next;
}

export function resizeGardenShapeMask(
    mask: number[][] | null | undefined,
    widthCm: number,
    heightCm: number,
    cellCm = GARDEN_SHAPE_CELL_CM,
): number[][] {
    return fitShapeMaskToDimensions(mask, widthCm, heightCm, cellCm);
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

export function gardenShapeMaskCellLabel(cellCm: number): string {
    return cellCm >= 100 ? `${cellCm / 100} m` : `${cellCm} cm`;
}

export type ShapeMaskActiveCellBounds = {
    minRow: number;
    maxRow: number;
    minCol: number;
    maxCol: number;
};

export function getShapeMaskActiveCellBounds(
    mask: number[][] | null | undefined,
): ShapeMaskActiveCellBounds | null {
    if (!mask?.length) {
        return null;
    }

    let minRow = Number.POSITIVE_INFINITY;
    let maxRow = Number.NEGATIVE_INFINITY;
    let minCol = Number.POSITIVE_INFINITY;
    let maxCol = Number.NEGATIVE_INFINITY;

    mask.forEach((row, rowIndex) => {
        row.forEach((value, colIndex) => {
            if (value !== 1) {
                return;
            }

            minRow = Math.min(minRow, rowIndex);
            maxRow = Math.max(maxRow, rowIndex);
            minCol = Math.min(minCol, colIndex);
            maxCol = Math.max(maxCol, colIndex);
        });
    });

    if (!Number.isFinite(minRow) || !Number.isFinite(minCol)) {
        return null;
    }

    return { minRow, maxRow, minCol, maxCol };
}

export function shapeMaskHasInactiveCells(
    mask: number[][] | null | undefined,
): boolean {
    if (!mask?.length) {
        return false;
    }

    return mask.some((row) => row.some((value) => value === 0));
}
