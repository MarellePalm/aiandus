import { describe, expect, it } from 'vitest';

import {
    defaultGardenSelectionAroundAnchor,
    defaultPolygonAroundAnchor,
    latLngPolygonToApplyResult,
    pointInPolygon,
    polygonToApplyResult,
    selectionToGardenBounds,
} from './gardenAreaSelection';

describe('gardenAreaSelection', () => {
    const parcel = {
        centerLat: 58.4,
        centerLng: 26.7,
        widthMeters: 200,
        heightMeters: 400,
    };

    it('places default selection around anchor inside parcel', () => {
        const sel = defaultGardenSelectionAroundAnchor(
            parcel,
            58.401,
            26.701,
            40,
        );
        const garden = selectionToGardenBounds(parcel, sel);

        expect(garden.widthMeters).toBeGreaterThanOrEqual(5);
        expect(garden.heightMeters).toBeGreaterThanOrEqual(5);
        expect(garden.widthMeters).toBeLessThanOrEqual(parcel.widthMeters);
        expect(garden.heightMeters).toBeLessThanOrEqual(parcel.heightMeters);
    });

    it('maps full parcel selection to parcel dimensions', () => {
        const garden = selectionToGardenBounds(parcel, {
            u0: 0,
            v0: 0,
            u1: 1,
            v1: 1,
        });

        expect(garden.widthMeters).toBe(200);
        expect(garden.heightMeters).toBe(400);
        expect(garden.centerLat).toBeCloseTo(parcel.centerLat, 5);
        expect(garden.centerLng).toBeCloseTo(parcel.centerLng, 5);
    });

    it('builds apply result from WGS84 polygon ring', () => {
        const points = defaultPolygonAroundAnchor(58.401, 26.701, 40);
        const result = latLngPolygonToApplyResult(points);

        expect(result).not.toBeNull();
        expect(result?.bounds.centerLat).toBeCloseTo(58.401, 3);
        expect(result?.bounds.centerLng).toBeCloseTo(26.701, 3);
        expect(result?.bounds.widthMeters).toBeGreaterThanOrEqual(5);
        expect(result?.bounds.heightMeters).toBeGreaterThanOrEqual(5);
    });

    it('builds shape mask for triangular polygon', () => {
        const points = [
            { u: 0.4, v: 0.4 },
            { u: 0.6, v: 0.4 },
            { u: 0.5, v: 0.6 },
        ];
        const result = polygonToApplyResult(parcel, points);

        expect(result).not.toBeNull();
        expect(result?.shapeMask).not.toBeNull();
        expect(pointInPolygon(0.5, 0.5, points)).toBe(true);
    });
});
