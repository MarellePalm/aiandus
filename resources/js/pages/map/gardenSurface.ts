import {
    CM_TO_PX,
    MAX_GARDEN_SURFACE_HEIGHT,
    MAX_GARDEN_SURFACE_WIDTH,
} from '@/pages/map/constants';

export function computeGardenSurfacePx(
    widthCm: number,
    heightCm: number,
): {
    width: number;
    height: number;
} {
    let width = Math.max(320, Math.round(widthCm * CM_TO_PX));
    let height = Math.max(240, Math.round(heightCm * CM_TO_PX));
    const scale = Math.min(
        1,
        MAX_GARDEN_SURFACE_WIDTH / width,
        MAX_GARDEN_SURFACE_HEIGHT / height,
    );

    if (scale < 1) {
        width = Math.max(320, Math.round(width * scale));
        height = Math.max(240, Math.round(height * scale));
    }

    return { width, height };
}
