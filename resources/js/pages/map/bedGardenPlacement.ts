import {
    bedFootprintSizePx,
    type BedCellBrick,
} from '@/pages/map/bedBrickFootprint';
import {
    CM_TO_PX,
    DEFAULT_BED_CELL_SIZE_CM,
    GARDEN_PADDING,
} from '@/pages/map/constants';
import { computeGardenSurfacePx } from '@/pages/map/gardenSurface';

export type GardenPlacementPlan = {
    width: number;
    height: number;
    shape_mask: number[][] | null;
    shape_mask_cell_cm?: number;
    center_lat?: number | null;
    center_lng?: number | null;
};

export function planUsesPinPlacement(plan: GardenPlacementPlan): boolean {
    const lat = plan.center_lat;
    const lng = plan.center_lng;

    return (
        lat != null &&
        lng != null &&
        Number.isFinite(Number(lat)) &&
        Number.isFinite(Number(lng))
    );
}

export type GardenPlacementBed = {
    garden_x: number;
    garden_y: number;
    cell_size_cm: number;
    layout?: number[][] | null;
    cell_bricks?: BedCellBrick[] | null;
    rows?: number;
    columns?: number;
};

export function planShapeCellCm(plan: GardenPlacementPlan): number {
    const cm = plan.shape_mask_cell_cm;
    if (cm != null && cm >= 10 && cm <= 2000) {
        return cm;
    }
    return 100;
}

export function gardenSizeCm(plan: GardenPlacementPlan): {
    widthCm: number;
    heightCm: number;
} {
    return {
        widthCm: Math.max(1, Math.round(plan.width)),
        heightCm: Math.max(1, Math.round(plan.height)),
    };
}

export function gardenSurfacePx(plan: GardenPlacementPlan): {
    width: number;
    height: number;
} {
    const { widthCm, heightCm } = gardenSizeCm(plan);
    return computeGardenSurfacePx(widthCm, heightCm);
}

export function gridStepPx(plan: GardenPlacementPlan): number {
    return Math.max(8, Math.round(planShapeCellCm(plan) * CM_TO_PX));
}

export function snapGardenPx(value: number, plan: GardenPlacementPlan): number {
    const step = gridStepPx(plan);
    return Math.round(value / step) * step;
}

function getBedLayout(bed: GardenPlacementBed): number[][] {
    const layout = bed.layout;
    if (
        layout &&
        Array.isArray(layout) &&
        layout.length > 0 &&
        layout.some((row) => Array.isArray(row) && row.length > 0)
    ) {
        return layout as number[][];
    }
    return Array.from({ length: bed.rows || 1 }, () =>
        Array.from({ length: bed.columns || 1 }, () => 1),
    );
}

function getBedActiveBounds(layout: number[][]) {
    let minRow = Number.POSITIVE_INFINITY;
    let maxRow = Number.NEGATIVE_INFINITY;
    let minCol = Number.POSITIVE_INFINITY;
    let maxCol = Number.NEGATIVE_INFINITY;

    layout.forEach((row, rowIndex) => {
        row.forEach((cell, colIndex) => {
            if (cell !== 1) {
                return;
            }
            minRow = Math.min(minRow, rowIndex);
            maxRow = Math.max(maxRow, rowIndex);
            minCol = Math.min(minCol, colIndex);
            maxCol = Math.max(maxCol, colIndex);
        });
    });

    if (!Number.isFinite(minRow)) {
        return { minRow: 0, maxRow: 0, minCol: 0, maxCol: 0 };
    }

    return { minRow, maxRow, minCol, maxCol };
}

export function bedFootprintPx(
    bed: GardenPlacementBed,
): { width: number; height: number } {
    if (Array.isArray(bed.cell_bricks) && bed.cell_bricks.length > 0) {
        return bedFootprintSizePx(bed.cell_bricks, bed.cell_size_cm);
    }

    const layout = getBedLayout(bed);
    const bounds = getBedActiveBounds(layout);
    const cellCm = Math.max(10, bed.cell_size_cm || DEFAULT_BED_CELL_SIZE_CM);
    const cols = Math.max(1, bounds.maxCol - bounds.minCol + 1);
    const rows = Math.max(1, bounds.maxRow - bounds.minRow + 1);

    return {
        width: Math.round(cols * cellCm * CM_TO_PX),
        height: Math.round(rows * cellCm * CM_TO_PX),
    };
}

export function clampBedOnGarden(
    x: number,
    y: number,
    bedSize: { width: number; height: number },
    plan: GardenPlacementPlan,
): { x: number; y: number } {
    const surface = gardenSurfacePx(plan);

    return {
        x: Math.max(
            GARDEN_PADDING,
            Math.min(x, surface.width - bedSize.width - GARDEN_PADDING),
        ),
        y: Math.max(
            GARDEN_PADDING,
            Math.min(y, surface.height - bedSize.height - GARDEN_PADDING),
        ),
    };
}

export function gardenPositionFromClick(
    clickX: number,
    clickY: number,
    bedSize: { width: number; height: number },
    plan: GardenPlacementPlan,
): { x: number; y: number } {
    if (planUsesPinPlacement(plan)) {
        return gardenPositionFromPinClick(clickX, clickY, bedSize, plan);
    }

    const snapped = {
        x: snapGardenPx(clickX - bedSize.width / 2, plan),
        y: snapGardenPx(clickY - bedSize.height / 2, plan),
    };

    return clampBedOnGarden(snapped.x, snapped.y, bedSize, plan);
}

/** Ortofoto: klõps = tihvi ots (sama loogika mis MapView peenratihv). */
export function gardenPositionFromPinClick(
    clickX: number,
    clickY: number,
    bedSize: { width: number; height: number },
    plan: GardenPlacementPlan,
): { x: number; y: number } {
    return clampBedOnGarden(
        clickX - bedSize.width / 2,
        clickY - bedSize.height,
        bedSize,
        plan,
    );
}

export function bedPinTipPx(
    gardenX: number,
    gardenY: number,
    bedSize: { width: number; height: number },
): { x: number; y: number } {
    return {
        x: gardenX + bedSize.width / 2,
        y: gardenY + bedSize.height,
    };
}

export function shapeMaskClipRects(
    plan: GardenPlacementPlan,
    surfaceWidth: number,
    surfaceHeight: number,
): { x: number; y: number; w: number; h: number }[] {
    const mask = plan.shape_mask;
    if (!mask?.length) {
        return [];
    }

    const cols = mask[0]?.length ?? 0;
    const rows = mask.length;
    if (!cols) {
        return [];
    }

    const cellW = surfaceWidth / cols;
    const cellH = surfaceHeight / rows;
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
}
