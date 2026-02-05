// resources/js/composables/useGeolocation.ts
import { onMounted, ref } from 'vue';
export type Coords = { latitude: number; longitude: number };

export function useGeolocation(
    defaultCoords: Coords = { latitude: 59.437, longitude: 24.7536 },
) {
    const coords = ref<Coords>(defaultCoords);
    const loading = ref(true);
    const error = ref<string | null>(null);

    onMounted(() => {
        if (!('geolocation' in navigator)) {
            loading.value = false;
            error.value = 'unsupported';
            return;
        }
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
    });

    return { coords, loading, error };
}
