import {
    bedFootprintRectsPx,
    bedFootprintSizePx,
    repairLegacySubgridBrick,
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

export type GardenPlacementPlant = {
    image_url?: string | null;
    position_in_bed?: string | null;
};

export type GardenPlacementBed = {
    garden_x: number;
    garden_y: number;
    cell_size_cm: number;
    layout?: number[][] | null;
    cell_bricks?: BedCellBrick[] | null;
    rows?: number;
    columns?: number;
    plants?: GardenPlacementPlant[] | null;
};

export type GardenPlacementPreviewCell = {
    left: number;
    top: number;
    width: number;
    height: number;
    kind: 'plantable' | 'walkway' | 'empty';
    image_url?: string | null;
};

function plantImageForPlacementBrick(
    bed: GardenPlacementBed,
    brick: BedCellBrick,
): string | null {
    const unitCm = Math.max(10, bed.cell_size_cm || DEFAULT_BED_CELL_SIZE_CM);
    const repaired = repairLegacySubgridBrick(brick, unitCm);
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

export function gardenPlacementPreviewCells(
    bed: GardenPlacementBed,
    plantImageAt?: (index: number) => string | null,
): GardenPlacementPreviewCell[] {
    const bricks = bed.cell_bricks;
    if (!Array.isArray(bricks) || bricks.length === 0) {
        return [];
    }

    const unitCm = Math.max(10, bed.cell_size_cm || DEFAULT_BED_CELL_SIZE_CM);

    return bedFootprintRectsPx(bricks, unitCm).map((rect, index) => ({
        ...rect,
        image_url:
            plantImageAt?.(index) ??
            plantImageForPlacementBrick(bed, bricks[index] ?? {}),
    }));
}

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

export function placementSnapStepPx(
    bed: GardenPlacementBed,
    plan: GardenPlacementPlan,
): number {
    const bedStep = Math.max(
        4,
        Math.round(
            Math.max(10, bed.cell_size_cm || DEFAULT_BED_CELL_SIZE_CM) *
                CM_TO_PX,
        ),
    );
    const planStep = gridStepPx(plan);
    return Math.min(bedStep, planStep);
}

export function snapPlacementPx(value: number, stepPx: number): number {
    const step = Math.max(4, stepPx);
    return Math.round(value / step) * step;
}

const PLACEMENT_EDGE_SNAP_PX = 14;

export function snapToPlacementEdges(
    x: number,
    y: number,
    bedSize: { width: number; height: number },
    plan: GardenPlacementPlan,
    padding = 0,
): { x: number; y: number } {
    const surface = gardenSurfacePx(plan);
    const maxX = Math.max(padding, surface.width - bedSize.width - padding);
    const maxY = Math.max(padding, surface.height - bedSize.height - padding);

    let nextX = x;
    let nextY = y;

    if (nextX <= padding + PLACEMENT_EDGE_SNAP_PX) {
        nextX = padding;
    } else if (nextX >= maxX - PLACEMENT_EDGE_SNAP_PX) {
        nextX = maxX;
    }

    if (nextY <= padding + PLACEMENT_EDGE_SNAP_PX) {
        nextY = padding;
    } else if (nextY >= maxY - PLACEMENT_EDGE_SNAP_PX) {
        nextY = maxY;
    }

    return clampBedOnGarden(nextX, nextY, bedSize, plan, padding);
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

export function bedFootprintPx(bed: GardenPlacementBed): {
    width: number;
    height: number;
} {
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

type GardenPxRect = {
    left: number;
    top: number;
    width: number;
    height: number;
};

function pxRectsOverlap(a: GardenPxRect, b: GardenPxRect): boolean {
    return (
        a.left < b.left + b.width &&
        a.left + a.width > b.left &&
        a.top < b.top + b.height &&
        a.top + a.height > b.top
    );
}

/** Peenra plokid aiaplaani koordinaatides (px). */
export function bedFootprintPxRectsAt(
    bed: GardenPlacementBed,
    gardenX: number,
    gardenY: number,
): GardenPxRect[] {
    const bricks = bed.cell_bricks;
    if (Array.isArray(bricks) && bricks.length > 0) {
        const unitCm = Math.max(
            10,
            bed.cell_size_cm || DEFAULT_BED_CELL_SIZE_CM,
        );

        return bedFootprintRectsPx(bricks, unitCm).map((rect) => ({
            left: gardenX + rect.left,
            top: gardenY + rect.top,
            width: rect.width,
            height: rect.height,
        }));
    }

    const size = bedFootprintPx(bed);

    return [
        {
            left: gardenX,
            top: gardenY,
            width: size.width,
            height: size.height,
        },
    ];
}

export function draftPlacementOverlapsExistingBeds(
    gardenX: number,
    gardenY: number,
    draftBed: GardenPlacementBed,
    existingBeds: GardenPlacementBed[],
): boolean {
    if (!existingBeds.length) {
        return false;
    }

    const draftRects = bedFootprintPxRectsAt(draftBed, gardenX, gardenY);

    for (const existing of existingBeds) {
        const existingRects = bedFootprintPxRectsAt(
            existing,
            existing.garden_x ?? 0,
            existing.garden_y ?? 0,
        );

        for (const draftRect of draftRects) {
            for (const existingRect of existingRects) {
                if (pxRectsOverlap(draftRect, existingRect)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/** Leiab vaba koha, mis ei kattu olemasolevate peenardega. */
export function findNonOverlappingDraftPlacement(
    bedSize: { width: number; height: number },
    draftBed: GardenPlacementBed,
    plan: GardenPlacementPlan,
    existingBeds: GardenPlacementBed[],
    padding = 0,
): { x: number; y: number } {
    const preferred = centerBedOnGardenSurface(bedSize, plan, padding);

    if (
        !draftPlacementOverlapsExistingBeds(
            preferred.x,
            preferred.y,
            draftBed,
            existingBeds,
        )
    ) {
        return preferred;
    }

    const step = placementSnapStepPx(draftBed, plan);

    for (let ring = 1; ring <= 48; ring++) {
        for (let dy = -ring; dy <= ring; dy++) {
            for (let dx = -ring; dx <= ring; dx++) {
                if (Math.max(Math.abs(dx), Math.abs(dy)) !== ring) {
                    continue;
                }

                const candidate = clampBedOnGarden(
                    preferred.x + dx * step,
                    preferred.y + dy * step,
                    bedSize,
                    plan,
                    padding,
                );

                if (
                    !draftPlacementOverlapsExistingBeds(
                        candidate.x,
                        candidate.y,
                        draftBed,
                        existingBeds,
                    )
                ) {
                    return candidate;
                }
            }
        }
    }

    return preferred;
}

export function clampBedOnGarden(
    x: number,
    y: number,
    bedSize: { width: number; height: number },
    plan: GardenPlacementPlan,
    padding = GARDEN_PADDING,
): { x: number; y: number } {
    const surface = gardenSurfacePx(plan);
    const edge = Math.max(0, padding);

    return {
        x: Math.max(edge, Math.min(x, surface.width - bedSize.width - edge)),
        y: Math.max(edge, Math.min(y, surface.height - bedSize.height - edge)),
    };
}

export function gardenPositionFromClick(
    clickX: number,
    clickY: number,
    bedSize: { width: number; height: number },
    plan: GardenPlacementPlan,
    bed?: GardenPlacementBed,
): { x: number; y: number } {
    if (planUsesPinPlacement(plan)) {
        return gardenPositionFromPinClick(clickX, clickY, bedSize, plan);
    }

    const step = bed ? placementSnapStepPx(bed, plan) : gridStepPx(plan);
    const rawX = clickX - bedSize.width / 2;
    const rawY = clickY - bedSize.height / 2;
    const snapped = {
        x: snapPlacementPx(rawX, step),
        y: snapPlacementPx(rawY, step),
    };

    return snapToPlacementEdges(snapped.x, snapped.y, bedSize, plan, 0);
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

/** Kehtiva shape_mask piirid pikslites (tühi mask = kogu pind). */
export function shapeMaskPlacementBoundsPx(
    plan: GardenPlacementPlan,
    surfaceWidth: number,
    surfaceHeight: number,
): { x: number; y: number; width: number; height: number } {
    const rects = shapeMaskClipRects(plan, surfaceWidth, surfaceHeight);
    if (!rects.length) {
        return { x: 0, y: 0, width: surfaceWidth, height: surfaceHeight };
    }

    const minX = Math.min(...rects.map((rect) => rect.x));
    const minY = Math.min(...rects.map((rect) => rect.y));
    const maxX = Math.max(...rects.map((rect) => rect.x + rect.w));
    const maxY = Math.max(...rects.map((rect) => rect.y + rect.h));

    return {
        x: minX,
        y: minY,
        width: Math.max(1, maxX - minX),
        height: Math.max(1, maxY - minY),
    };
}

function bedOverlapsMaskRects(
    x: number,
    y: number,
    bedSize: { width: number; height: number },
    rects: { x: number; y: number; w: number; h: number }[],
): boolean {
    const bx2 = x + bedSize.width;
    const by2 = y + bedSize.height;

    return rects.some((rect) => {
        const rx2 = rect.x + rect.w;
        const ry2 = rect.y + rect.h;
        return x < rx2 && bx2 > rect.x && y < ry2 && by2 > rect.y;
    });
}

/** Vaikimisi asukoht kehtiva maski sees (mitte bboxi „augus" L-kujulisel aiaplaanil). */
export function centerBedOnGardenSurface(
    bedSize: { width: number; height: number },
    plan: GardenPlacementPlan,
    padding = 0,
): { x: number; y: number } {
    const surface = gardenSurfacePx(plan);
    const rects = shapeMaskClipRects(plan, surface.width, surface.height);
    const region = shapeMaskPlacementBoundsPx(
        plan,
        surface.width,
        surface.height,
    );

    let rawX =
        region.x + Math.max(0, Math.round((region.width - bedSize.width) / 2));
    let rawY =
        region.y +
        Math.max(0, Math.round((region.height - bedSize.height) / 2));

    if (rects.length > 0 && !bedOverlapsMaskRects(rawX, rawY, bedSize, rects)) {
        const anchor = rects.reduce((largest, rect) =>
            rect.w * rect.h > largest.w * largest.h ? rect : largest,
        );
        rawX =
            anchor.x + Math.max(0, Math.round((anchor.w - bedSize.width) / 2));
        rawY =
            anchor.y + Math.max(0, Math.round((anchor.h - bedSize.height) / 2));
    }

    return snapToPlacementEdges(rawX, rawY, bedSize, plan, padding);
}

export type BedLayoutRotationCell = {
    x: number;
    y: number;
    left_cm: number;
    top_cm: number;
    w: number;
    h: number;
    width_cm: number;
    height_cm: number;
};

/** Pöörab kõik plokid 90° päripäeva; koordinaadid normaliseeritakse (0,0) nurka. */
export function rotateBedLayoutCells90Cw<T extends BedLayoutRotationCell>(
    cells: T[],
    unitCm: number,
): T[] {
    if (cells.length === 0) {
        return cells;
    }

    const minLeft = Math.min(...cells.map((cell) => cell.left_cm));
    const minTop = Math.min(...cells.map((cell) => cell.top_cm));
    const maxRight = Math.max(
        ...cells.map((cell) => cell.left_cm + cell.width_cm),
    );
    const bedWidthCm = maxRight - minLeft;
    const unit = Math.max(10, unitCm);

    return cells.map((cell) => {
        const relL = cell.left_cm - minLeft;
        const relT = cell.top_cm - minTop;
        const newRelL = relT;
        const newRelT = bedWidthCm - relL - cell.width_cm;
        const newWidthCm = cell.height_cm;
        const newHeightCm = cell.width_cm;
        const newW = Math.max(1, Math.round(newWidthCm / unit));
        const newH = Math.max(1, Math.round(newHeightCm / unit));
        const newX = Math.max(0, Math.round(newRelL / unit));
        const newY = Math.max(0, Math.round(newRelT / unit));

        return {
            ...cell,
            x: newX,
            y: newY,
            w: newW,
            h: newH,
            left_cm: newRelL,
            top_cm: newRelT,
            width_cm: newWidthCm,
            height_cm: newHeightCm,
        };
    });
}
