import { inferCmPositionsFromGrid } from '@/pages/map/bedEditorCmLayout';
import { CM_TO_PX } from '@/pages/map/constants';

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
    return Math.max(10, Math.min(200, Math.round(unitCm) || 30));
}

export function resolveBedBricksCm(
    bricks: BedCellBrick[],
    unitCm = 30,
): ResolvedBedBrick[] {
    const unit = clampUnitCm(unitCm);

    const normalized = bricks.map((brick) => {
        const w = Math.max(1, brick.w ?? 1);
        const h = Math.max(1, brick.h ?? 1);

        return {
            x: brick.x,
            y: brick.y,
            width_cm: Math.max(
                10,
                Math.min(500, Math.round(brick.width_cm ?? w * unit)),
            ),
            height_cm: Math.max(
                10,
                Math.min(500, Math.round(brick.height_cm ?? h * unit)),
            ),
            left_cm:
                brick.left_cm != null
                    ? Math.max(0, Math.round(brick.left_cm))
                    : undefined,
            top_cm:
                brick.top_cm != null
                    ? Math.max(0, Math.round(brick.top_cm))
                    : undefined,
            kind: brick.kind ?? 'plantable',
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

export function bedBoundsCmFromBricks(
    bricks: ResolvedBedBrick[],
): { widthCm: number; heightCm: number; minLeft: number; minTop: number } {
    if (!bricks.length) {
        return { widthCm: 30, heightCm: 30, minLeft: 0, minTop: 0 };
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
    unitCm = 30,
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
    unitCm = 30,
): { width: number; height: number } {
    const { widthCm, heightCm } = bedBoundsCmFromBricks(
        resolveBedBricksCm(bricks, unitCm),
    );

    return {
        width: Math.max(1, Math.round(widthCm * CM_TO_PX)),
        height: Math.max(1, Math.round(heightCm * CM_TO_PX)),
    };
}
