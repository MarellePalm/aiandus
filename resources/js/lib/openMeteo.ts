export type Coords = { latitude: number; longitude: number };

export async function fetchWeatherMoon({ latitude, longitude }: Coords) {
    const url =
        'https://api.open-meteo.com/v1/forecast' +
        `?latitude=${encodeURIComponent(latitude)}` +
        `&longitude=${encodeURIComponent(longitude)}` +
        '&current=temperature_2m' +
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


// see peaks näitama päikeseline, pilvine, vihmane, lumine või muutlik.
export function labelWeather(code: number) {
    if (code === 0) return 'Päikeseline';
    if (code <= 3) return 'Pilvine';
    if (code >= 61 && code <= 67) return 'Vihmane';
    if (code >= 71 && code <= 77) return 'Lumine';
    return 'Muutlik';
  }
  




// see osa ei tööta, pole moon API't, aga chat arvas et võiks alles hoida. 

export function labelMoon(f: number) {
    if (f < 0.03 || f > 0.97) return 'Noorkuu';
    if (f < 0.22) return 'Kasvav sirp';
    if (f < 0.28) return 'Esimene veerand';
    if (f < 0.47) return 'Kasvav kumer';
    if (f < 0.53) return 'Täiskuu';
    if (f < 0.72) return 'Kahanev kumer';
    if (f < 0.78) return 'Viimane veerand';
    return 'Kahanev sirp';
}
