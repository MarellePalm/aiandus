// resources/js/lib/openMeteo.ts

export type Coords = { latitude: number; longitude: number };

export type WeatherSnapshot = {
  temp: number | null;
  weatherCode: number | null;
  tMax: number | null;
  tMin: number | null;
  updatedAt: string;
};

/**
 * Open-Meteo: "current" fields MUST be requested explicitly.
 * Docs: https://open-meteo.com/en/docs
 */
export async function fetchWeatherMoon({ latitude, longitude }: Coords): Promise<WeatherSnapshot> {
  const url =
    'https://api.open-meteo.com/v1/forecast' +
    `?latitude=${encodeURIComponent(latitude)}` +
    `&longitude=${encodeURIComponent(longitude)}` +
    '&current=temperature_2m,weather_code' + // ✅ FIX: request weather_code
    '&daily=temperature_2m_max,temperature_2m_min' +
    '&forecast_days=1' +
    '&timezone=auto';

  const res = await fetch(url);
  if (!res.ok) throw new Error('Ilma päring ebaõnnestus');
  const d = await res.json();

  return {
    temp: d.current?.temperature_2m ?? null,
    weatherCode: d.current?.weather_code ?? null,
    tMax: d.daily?.temperature_2m_max?.[0] ?? null,
    tMin: d.daily?.temperature_2m_min?.[0] ?? null,
    updatedAt: new Date().toLocaleString('et-EE'),
  };
}

/**
 * Open-Meteo uses WMO weather codes.
 * Categories are subjective; mapping below covers common ranges.
 */
export function labelWeather(code: number): 'Päikeseline' | 'Pilvine' | 'Vihmane' | 'Lumine' | 'Muutlik' {
  // Clear
  if (code === 0) return 'Päikeseline';

  // Mainly clear / partly cloudy / overcast
  if (code >= 1 && code <= 3) return 'Pilvine';

  // Fog
  if (code === 45 || code === 48) return 'Muutlik';

  // Drizzle / Rain
  if ((code >= 51 && code <= 57) || (code >= 61 && code <= 67) || (code >= 80 && code <= 82)) return 'Vihmane';

  // Snow
  if ((code >= 71 && code <= 77) || (code === 85 || code === 86)) return 'Lumine';

  // Thunderstorm
  if (code >= 95 && code <= 99) return 'Vihmane';

  return 'Muutlik';
}

/**
 * Optional: show an icon without hosting SVGs.
 * Uses Material Symbols names (works if you already load Material Symbols).
 */
export function iconWeatherMaterial(code: number): string {
  if (code === 0) return 'sunny';
  if (code >= 1 && code <= 3) return 'cloud';
  if (code === 45 || code === 48) return 'foggy';

  if ((code >= 51 && code <= 57) || (code >= 61 && code <= 67) || (code >= 80 && code <= 82)) return 'rainy';
  if ((code >= 71 && code <= 77) || code === 85 || code === 86) return 'weather_snowy';
  if (code >= 95 && code <= 99) return 'thunderstorm';

  return 'cloud';
}

// Kui sa päriselt "moon API" ei kasuta, jäta see praegu välja või tõsta eraldi faili.
// export function labelMoon(...) { ... }
