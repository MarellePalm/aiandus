// resources/js/composables/useGeolocation.ts
import { ref } from 'vue';
export type Coords = { latitude: number; longitude: number };

export function useGeolocation(
    defaultCoords: Coords = { latitude: 59.437, longitude: 24.7536 },
) {
    const coords = ref<Coords>({ ...defaultCoords });
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
                loading.value = false;
            },
            (e) => {
                error.value = e.message || 'denied';
                loading.value = false;
            },
            { maximumAge: 60000, timeout: 10000, enableHighAccuracy: false },
        );
    }

    return { coords, loading, error, requestPosition };
}
