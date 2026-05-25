export function parseCoordinateInput(value: string): number | null {
    const trimmed = value.trim();
    if (!trimmed) {
        return null;
    }

    const parsed = Number(trimmed);
    return Number.isFinite(parsed) ? parsed : null;
}

export function coordinateInputValue(value: number | null): string {
    return value == null ? '' : String(value);
}

export function optionalDimensionInputValue(
    value: number | null | undefined,
): string {
    if (value == null || !Number.isFinite(Number(value))) {
        return '';
    }

    return String(value);
}

export function parseOptionalDimensionInput(value: string): number | null {
    const trimmed = value.trim();
    if (!trimmed) {
        return null;
    }

    const parsed = Number(trimmed);
    return Number.isFinite(parsed) ? parsed : null;
}

export function roundGardenCoordinate(value: number): number {
    return Math.round(value * 10_000_000) / 10_000_000;
}
