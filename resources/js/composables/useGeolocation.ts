// resources/js/composables/useGeolocation.ts
import { ref } from 'vue';
export type Coords = { latitude: number; longitude: number };

const STORAGE_KEY = 'aiandus_weather_coords_v1';
/** Pärast seda aega küsime uuesti (vältimaks väga vanu koordinaate). */
const STORED_MAX_AGE_MS = 30 * 24 * 60 * 60 * 1000;

function readStoredCoords(): Coords | null {
    if (typeof window === 'undefined') {
        return null;
    }
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return null;
        }
        const j = JSON.parse(raw) as { lat: number; lon: number; t?: number };
        if (typeof j.lat !== 'number' || typeof j.lon !== 'number') {
            return null;
        }
        if (j.t != null && Date.now() - j.t > STORED_MAX_AGE_MS) {
            return null;
        }

        return { latitude: j.lat, longitude: j.lon };
    } catch {
        return null;
    }
}

function saveStoredCoords(c: Coords): void {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                lat: c.latitude,
                lon: c.longitude,
                t: Date.now(),
            }),
        );
    } catch {
        /* private mode / quota */
    }
}

export function useGeolocation(
    defaultCoords: Coords = { latitude: 59.437, longitude: 24.7536 },
) {
    const fromStorage = readStoredCoords();
    const coords = ref<Coords>(
        fromStorage ?? { ...defaultCoords },
    );
    /** True, kui laadisime koordinaadid localStorage’ist (kasutaja varem nuppu vajutanud). */
    const restoredFromStorage = ref(fromStorage !== null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    function requestPosition(): void {
        if (!('geolocation' in navigator)) {
            error.value = 'unsupported';
            return;
        }
        loading.value = true;
        error.value = null;
        navigator.geolocation.getCurrentPosition(
            (p) => {
                coords.value = {
                    latitude: p.coords.latitude,
                    longitude: p.coords.longitude,
                };
                saveStoredCoords(coords.value);
                restoredFromStorage.value = true;
                loading.value = false;
            },
            (e) => {
                error.value = e.message || 'denied';
                loading.value = false;
            },
            { maximumAge: 60000, timeout: 10000, enableHighAccuracy: false },
        );
    }

    return {
        coords,
        loading,
        error,
        requestPosition,
        restoredFromStorage,
    };
}
