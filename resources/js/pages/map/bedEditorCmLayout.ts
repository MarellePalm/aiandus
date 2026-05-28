export type BedEditorCell = {
    id: string;
    x: number;
    y: number;
    left_cm: number;
    top_cm: number;
    width_cm: number;
    height_cm: number;
};

export const EDITOR_PX_PER_CM = 2.5;

export type CmRect = {
    left: number;
    top: number;
    right: number;
    bottom: number;
};

export type BedEditorCmLayout = {
    totalWidthCm: number;
    totalHeightCm: number;
    cellRect: (cell: BedEditorCell) => CmRect;
};

export function rectsOverlap(a: CmRect, b: CmRect): boolean {
    return !(
        a.right <= b.left ||
        a.left >= b.right ||
        a.bottom <= b.top ||
        a.top >= b.bottom
    );
}

export function brickCmRectsOverlap(
    leftCm: number,
    topCm: number,
    widthCm: number,
    heightCm: number,
    otherLeftCm: number,
    otherTopCm: number,
    otherWidthCm: number,
    otherHeightCm: number,
): boolean {
    return rectsOverlap(
        {
            left: leftCm,
            top: topCm,
            right: leftCm + widthCm,
            bottom: topCm + heightCm,
        },
        {
            left: otherLeftCm,
            top: otherTopCm,
            right: otherLeftCm + otherWidthCm,
            bottom: otherTopCm + otherHeightCm,
        },
    );
}

export function cellCmRect(cell: BedEditorCell): CmRect {
    return {
        left: cell.left_cm,
        top: cell.top_cm,
        right: cell.left_cm + cell.width_cm,
        bottom: cell.top_cm + cell.height_cm,
    };
}

export function buildBedEditorCmLayout(cells: BedEditorCell[]): BedEditorCmLayout {
    let totalWidthCm = 0;
    let totalHeightCm = 0;

    for (const cell of cells) {
        totalWidthCm = Math.max(totalWidthCm, cell.left_cm + cell.width_cm);
        totalHeightCm = Math.max(totalHeightCm, cell.top_cm + cell.height_cm);
    }

    return {
        totalWidthCm,
        totalHeightCm,
        cellRect: cellCmRect,
    };
}

export function cellsListHasOverlaps(
    cells: BedEditorCell[],
    excludeId?: string,
): boolean {
    const list = excludeId
        ? cells.filter((cell) => cell.id !== excludeId)
        : cells;

    for (let i = 0; i < list.length; i += 1) {
        for (let j = i + 1; j < list.length; j += 1) {
            const a = list[i];
            const b = list[j];

            if (
                brickCmRectsOverlap(
                    a.left_cm,
                    a.top_cm,
                    a.width_cm,
                    a.height_cm,
                    b.left_cm,
                    b.top_cm,
                    b.width_cm,
                    b.height_cm,
                )
            ) {
                return true;
            }
        }
    }

    return false;
}

export function brickFitsCm(
    cells: BedEditorCell[],
    leftCm: number,
    topCm: number,
    widthCm: number,
    heightCm: number,
    excludeId?: string,
): boolean {
    const test: CmRect = {
        left: leftCm,
        top: topCm,
        right: leftCm + widthCm,
        bottom: topCm + heightCm,
    };

    for (const cell of cells) {
        if (excludeId && cell.id === excludeId) {
            continue;
        }
        if (rectsOverlap(test, cellCmRect(cell))) {
            return false;
        }
    }

    return true;
}

/** Täida left_cm/top_cm vanast ruudustiku indeksist (laadimine). */
export function inferCmPositionsFromGrid(
    cells: Array<
        Pick<BedEditorCell, 'x' | 'y' | 'width_cm' | 'height_cm'> & {
            left_cm?: number;
            top_cm?: number;
        }
    >,
): void {
    const colWidth = new Map<number, number>();
    const rowHeight = new Map<number, number>();

    for (const cell of cells) {
        colWidth.set(cell.x, Math.max(colWidth.get(cell.x) ?? 0, cell.width_cm));
        rowHeight.set(cell.y, Math.max(rowHeight.get(cell.y) ?? 0, cell.height_cm));
    }

    const cols = [...colWidth.keys()].sort((a, b) => a - b);
    const rows = [...rowHeight.keys()].sort((a, b) => a - b);

    const colLeft = new Map<number, number>();
    let xAcc = 0;
    for (const col of cols) {
        colLeft.set(col, xAcc);
        xAcc += colWidth.get(col) ?? 0;
    }

    const rowTop = new Map<number, number>();
    let yAcc = 0;
    for (const row of rows) {
        rowTop.set(row, yAcc);
        yAcc += rowHeight.get(row) ?? 0;
    }

    for (const cell of cells) {
        if (cell.left_cm == null) {
            cell.left_cm = colLeft.get(cell.x) ?? 0;
        }
        if (cell.top_cm == null) {
            cell.top_cm = rowTop.get(cell.y) ?? 0;
        }
    }
}

/** Uuenda x/y salvestamiseks (järjestikused indeksid ilma vahedega). */
export function packGridIndicesFromCm(
    cells: Array<Pick<BedEditorCell, 'x' | 'y' | 'left_cm' | 'top_cm'>>,
): void {
    if (!cells.length) {
        return;
    }

    const sorted = [...cells].sort(
        (a, b) => a.top_cm - b.top_cm || a.left_cm - b.left_cm,
    );

    const rowTolerance = 0.5;
    const rows: (typeof cells)[] = [];

    for (const cell of sorted) {
        const row = rows.find(
            (group) => Math.abs(group[0].top_cm - cell.top_cm) <= rowTolerance,
        );
        if (row) {
            row.push(cell);
        } else {
            rows.push([cell]);
        }
    }

    let y = 0;
    for (const row of rows) {
        row.sort((a, b) => a.left_cm - b.left_cm);
        let x = 0;
        for (const cell of row) {
            cell.x = x;
            cell.y = y;
            x += 1;
        }
        y += 1;
    }
}

export function cmToPx(cm: number): number {
    return Math.round(cm * EDITOR_PX_PER_CM);
}
