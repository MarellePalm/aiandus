import { inferCmPositionsFromGrid } from '@/pages/map/bedEditorCmLayout';
import { CM_TO_PX, DEFAULT_BED_CELL_SIZE_CM } from '@/pages/map/constants';

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

export type ResolvedBedBrick = {
    left_cm: number;
    top_cm: number;
    width_cm: number;
    height_cm: number;
    kind: 'plantable' | 'walkway' | 'empty';
};

export type BedFootprintRectPx = {
    left: number;
    top: number;
    width: number;
    height: number;
    kind: 'plantable' | 'walkway' | 'empty';
};

function clampUnitCm(unitCm: number): number {
    return Math.max(
        10,
        Math.min(200, Math.round(unitCm) || DEFAULT_BED_CELL_SIZE_CM),
    );
}

/** Redaktori alamruudustik (w=3) salvestati varem ilma width_cm-ta → 90 cm asemel 30 cm. */
const EDITOR_SUBGRID_UNITS = 3;

export function repairLegacySubgridBrick(
    brick: BedCellBrick,
    unitCm: number,
): BedCellBrick {
    const unit = clampUnitCm(unitCm);
    const w = Math.max(1, brick.w ?? 1);
    const h = Math.max(1, brick.h ?? 1);
    const widthCm = brick.width_cm ?? w * unit;
    const heightCm = brick.height_cm ?? h * unit;
    const x = brick.x ?? 0;
    const y = brick.y ?? 0;

    const looksLikeSubgridMistake =
        w >= EDITOR_SUBGRID_UNITS &&
        h >= EDITOR_SUBGRID_UNITS &&
        widthCm === w * unit &&
        heightCm === h * unit &&
        brick.left_cm == null &&
        x % EDITOR_SUBGRID_UNITS === 0 &&
        y % EDITOR_SUBGRID_UNITS === 0;

    if (!looksLikeSubgridMistake) {
        return brick;
    }

    const blockW = Math.max(1, Math.round(w / EDITOR_SUBGRID_UNITS));
    const blockH = Math.max(1, Math.round(h / EDITOR_SUBGRID_UNITS));

    return {
        ...brick,
        x: Math.round(x / EDITOR_SUBGRID_UNITS),
        y: Math.round(y / EDITOR_SUBGRID_UNITS),
        w: blockW,
        h: blockH,
        width_cm: unit * blockW,
        height_cm: unit * blockH,
    };
}

export function resolveBedBricksCm(
    bricks: BedCellBrick[],
    unitCm = 30,
): ResolvedBedBrick[] {
    const unit = clampUnitCm(unitCm);

    const normalized = bricks.map((brick) => {
        const repaired = repairLegacySubgridBrick(brick, unit);
        const w = Math.max(1, repaired.w ?? 1);
        const h = Math.max(1, repaired.h ?? 1);

        return {
            x: repaired.x ?? 0,
            y: repaired.y ?? 0,
            width_cm: Math.max(
                10,
                Math.min(500, Math.round(repaired.width_cm ?? w * unit)),
            ),
            height_cm: Math.max(
                10,
                Math.min(500, Math.round(repaired.height_cm ?? h * unit)),
            ),
            left_cm:
                repaired.left_cm != null
                    ? Math.max(0, Math.round(repaired.left_cm))
                    : undefined,
            top_cm:
                repaired.top_cm != null
                    ? Math.max(0, Math.round(repaired.top_cm))
                    : undefined,
            kind: repaired.kind ?? 'plantable',
        };
    });

    const missingPosition = normalized.some(
        (brick) => brick.left_cm == null || brick.top_cm == null,
    );

    if (missingPosition) {
        inferCmPositionsFromGrid(normalized);
    }

    return normalized.map((brick) => ({
        left_cm: brick.left_cm ?? 0,
        top_cm: brick.top_cm ?? 0,
        width_cm: brick.width_cm,
        height_cm: brick.height_cm,
        kind: brick.kind ?? 'plantable',
    }));
}

export function bedBoundsCmFromBricks(bricks: ResolvedBedBrick[]): {
    widthCm: number;
    heightCm: number;
    minLeft: number;
    minTop: number;
} {
    if (!bricks.length) {
        return {
            widthCm: DEFAULT_BED_CELL_SIZE_CM,
            heightCm: DEFAULT_BED_CELL_SIZE_CM,
            minLeft: 0,
            minTop: 0,
        };
    }

    let minLeft = Number.POSITIVE_INFINITY;
    let minTop = Number.POSITIVE_INFINITY;
    let maxRight = 0;
    let maxBottom = 0;

    for (const brick of bricks) {
        minLeft = Math.min(minLeft, brick.left_cm);
        minTop = Math.min(minTop, brick.top_cm);
        maxRight = Math.max(maxRight, brick.left_cm + brick.width_cm);
        maxBottom = Math.max(maxBottom, brick.top_cm + brick.height_cm);
    }

    return {
        minLeft,
        minTop,
        widthCm: Math.max(10, maxRight - minLeft),
        heightCm: Math.max(10, maxBottom - minTop),
    };
}

export function bedFootprintRectsPx(
    bricks: BedCellBrick[],
    unitCm = DEFAULT_BED_CELL_SIZE_CM,
): BedFootprintRectPx[] {
    const resolved = resolveBedBricksCm(bricks, unitCm);
    const { minLeft, minTop } = bedBoundsCmFromBricks(resolved);

    return resolved.map((brick) => ({
        left: Math.round((brick.left_cm - minLeft) * CM_TO_PX),
        top: Math.round((brick.top_cm - minTop) * CM_TO_PX),
        width: Math.max(1, Math.round(brick.width_cm * CM_TO_PX)),
        height: Math.max(1, Math.round(brick.height_cm * CM_TO_PX)),
        kind: brick.kind,
    }));
}

export function bedFootprintSizePx(
    bricks: BedCellBrick[],
    unitCm = DEFAULT_BED_CELL_SIZE_CM,
): { width: number; height: number } {
    const { widthCm, heightCm } = bedBoundsCmFromBricks(
        resolveBedBricksCm(bricks, unitCm),
    );

    return {
        width: Math.max(1, Math.round(widthCm * CM_TO_PX)),
        height: Math.max(1, Math.round(heightCm * CM_TO_PX)),
    };
}
