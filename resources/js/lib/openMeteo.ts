// resources/js/lib/openMeteo.ts

export type Coords = { latitude: number; longitude: number };

export type DailyForecastItem = {
  date: string; // YYYY-MM-DD
  tMax: number | null;
  tMin: number | null;
  weatherCode: number | null;
};

export type WeatherSnapshot = {
  temp: number | null;
  weatherCode: number | null;

  // today's max/min (convenience)
  tMax: number | null;
  tMin: number | null;

  // includes today at index 0
  daily: DailyForecastItem[];

  updatedAt: string;
};

type OpenMeteoResponse = {
  current?: {
    temperature_2m?: number;
    weather_code?: number;
  };
  daily?: {
    time?: string[];
    temperature_2m_max?: number[];
    temperature_2m_min?: number[];
    weather_code?: number[];
  };
};

export async function fetchWeatherMoon(
  { latitude, longitude }: Coords,
  opts?: { days?: number }
): Promise<WeatherSnapshot> {
  const days = Math.min(Math.max(opts?.days ?? 3, 1), 7);

  const url =
    'https://api.open-meteo.com/v1/forecast' +
    `?latitude=${encodeURIComponent(latitude)}` +
    `&longitude=${encodeURIComponent(longitude)}` +
    '&current=temperature_2m,weather_code' +
    '&daily=weather_code,temperature_2m_max,temperature_2m_min' +
    `&forecast_days=${days}` +
    '&timezone=auto';

  const res = await fetch(url);
  if (!res.ok) throw new Error('Ilma päring ebaõnnestus');

  const d = (await res.json()) as OpenMeteoResponse;

  const times = d.daily?.time ?? [];
  const tMaxArr = d.daily?.temperature_2m_max ?? [];
  const tMinArr = d.daily?.temperature_2m_min ?? [];
  const codeArr = d.daily?.weather_code ?? [];

  const daily: DailyForecastItem[] = times.map((date, i) => ({
    date,
    tMax: tMaxArr[i] ?? null,
    tMin: tMinArr[i] ?? null,
    weatherCode: codeArr[i] ?? null,
  }));

  return {
    temp: d.current?.temperature_2m ?? null,
    weatherCode: d.current?.weather_code ?? null,

    tMax: daily[0]?.tMax ?? null,
    tMin: daily[0]?.tMin ?? null,

    daily,
    updatedAt: new Date().toLocaleString('et-EE'),
  };
}

/**
 * Open-Meteo uses WMO weather codes.
 */
export function labelWeather(code: number): 'Päikeseline' | 'Pilvine' | 'Vihmane' | 'Lumine' | 'Muutlik' {
  if (code === 0) return 'Päikeseline';
  if (code >= 1 && code <= 3) return 'Pilvine';
  if (code === 45 || code === 48) return 'Muutlik';
  if ((code >= 51 && code <= 57) || (code >= 61 && code <= 67) || (code >= 80 && code <= 82)) return 'Vihmane';
  if ((code >= 71 && code <= 77) || code === 85 || code === 86) return 'Lumine';
  if (code >= 95 && code <= 99) return 'Vihmane';
  return 'Muutlik';
}

export function iconWeatherMaterial(code: number): string {
  if (code === 0) return 'sunny';
  if (code >= 1 && code <= 3) return 'cloud';
  if (code === 45 || code === 48) return 'foggy';
  if ((code >= 51 && code <= 57) || (code >= 61 && code <= 67) || (code >= 80 && code <= 82)) return 'rainy';
  if ((code >= 71 && code <= 77) || code === 85 || code === 86) return 'weather_snowy';
  if (code >= 95 && code <= 99) return 'thunderstorm';
  return 'cloud';
}
