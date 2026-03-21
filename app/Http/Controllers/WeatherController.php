<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600; // 1 tund – piisav, et limiiti ei täitu (vaba plaan ~1000 päringut/päevas)

    /** OpenWeather condition id + ikoon (01d/01n) või päikese tõus/loojumine → eesti keelne lühikirjeldus. */
    private static function openWeatherLabel(int $id, string $icon = '01d', ?bool $isNightFromSun = null): string
    {
        $isNight = $isNightFromSun ?? str_ends_with($icon, 'n');
        if ($id === 800) return $isNight ? 'Selge öö' : 'Päikeseline';
        if ($id >= 801 && $id <= 804) return match ($id) { 801 => 'Vähe pilvi', 802 => 'Hajusad pilved', default => 'Pilvine' };
        if ($id >= 200 && $id < 300) return 'Äike';
        if ($id >= 300 && $id < 400) return 'Kerge vihm';
        if ($id >= 500 && $id < 600) return 'Vihm';
        if ($id >= 600 && $id < 700) return 'Lumi';
        if ($id >= 700 && $id < 800) {
            return match ($id) {
                701, 741 => 'Udu',
                711 => 'Suits',
                721 => 'Hägusus',
                731, 751, 761 => 'Tolmune',
                762 => 'Tuhk õhus',
                771 => 'Tuuleiilid',
                781 => 'Tornaado',
                default => 'Hägune',
            };
        }
        return 'Muutlik';
    }

    public function __invoke(Request $request): JsonResponse
    {
        $key = config('services.openweather.api_key');
        if (empty($key) || ! is_string($key)) {
            return response()->json([
                'message' => 'OPENWEATHER_API_KEY pole .env failis. Lisa võti ja käivita: php artisan config:clear',
            ], 503);
        }

        $lat = $request->input('lat');
        $lon = $request->input('lon');
        if (! is_numeric($lat) || ! is_numeric($lon)) {
            return response()->json([
                'message' => 'Asukoht (lat/lon) puudub. Luba brauseris asukoha jagamine.',
            ], 422);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;
        $cacheKey = 'weather:v3:'.round($lat, 2).':'.round($lon, 2);

        $data = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($key, $lat, $lon) {
            // Current weather
            $weatherUrl = 'https://api.openweathermap.org/data/2.5/weather'
                .'?lat='.urlencode((string) $lat)
                .'&lon='.urlencode((string) $lon)
                .'&appid='.urlencode($key)
                .'&units=metric';

            /** @var Response $weatherRes */
            $weatherRes = Http::timeout(10)->get($weatherUrl);
            if (! $weatherRes->successful()) {
                $body = $weatherRes->json();
                $msg = $body['message'] ?? $weatherRes->body();
                Log::warning('OpenWeather current failed', ['status' => $weatherRes->status(), 'body' => $msg]);
                return ['_error' => $msg ?: 'OpenWeather API viga'];
            }

            $weatherJson = $weatherRes->json();
            $temp = isset($weatherJson['main']['temp']) ? (float) $weatherJson['main']['temp'] : null;
            $humidity = isset($weatherJson['main']['humidity']) ? (int) $weatherJson['main']['humidity'] : null;
            $windSpeed = isset($weatherJson['wind']['speed']) ? (float) $weatherJson['wind']['speed'] : null;
            $weather = $weatherJson['weather'][0] ?? [];
            $weatherId = isset($weather['id']) ? (int) $weather['id'] : 800;
            $openWeatherIcon = isset($weather['icon']) ? (string) $weather['icon'] : '01d';

            $timezone = isset($weatherJson['timezone']) ? (int) $weatherJson['timezone'] : 0;
            $sunrise = isset($weatherJson['sys']['sunrise']) ? (int) $weatherJson['sys']['sunrise'] : null;
            $sunset = isset($weatherJson['sys']['sunset']) ? (int) $weatherJson['sys']['sunset'] : null;
            $sunriseStr = $sunrise !== null ? gmdate('H:i', $sunrise + $timezone) : null;
            $sunsetStr = $sunset !== null ? gmdate('H:i', $sunset + $timezone) : null;

            // Reverse geocoding
            $geoUrl = 'https://api.openweathermap.org/geo/1.0/reverse'
                .'?lat='.urlencode((string) $lat)
                .'&lon='.urlencode((string) $lon)
                .'&limit=1'
                .'&appid='.urlencode($key);

            $locationName = null;
            /** @var Response $geoRes */
            $geoRes = Http::timeout(5)->get($geoUrl);
            if ($geoRes->successful()) {
                $geo = $geoRes->json();
                if (! empty($geo[0]['name'])) {
                    $locationName = $geo[0]['name'];
                }
            }

            // 5 päeva / 3h sammuga – võtame iga päeva max/min + põhikoodi
            $forecastUrl = 'https://api.openweathermap.org/data/2.5/forecast'
                .'?lat='.urlencode((string) $lat)
                .'&lon='.urlencode((string) $lon)
                .'&appid='.urlencode($key)
                .'&units=metric';

            $daily = [];
            $tMax = null;
            $tMin = null;
            /** @var Response $forecastRes */
            $forecastRes = Http::timeout(10)->get($forecastUrl);
            if ($forecastRes->successful()) {
                $forecastJson = $forecastRes->json();
                $byDay = [];
                foreach ($forecastJson['list'] ?? [] as $item) {
                    if (! isset($item['dt_txt'], $item['main']['temp_max'], $item['main']['temp_min'])) {
                        continue;
                    }
                    $date = substr((string) $item['dt_txt'], 0, 10); // YYYY-MM-DD
                    $max = (float) $item['main']['temp_max'];
                    $min = (float) $item['main']['temp_min'];
                    $w = $item['weather'][0] ?? null;
                    $code = isset($w['id']) ? (int) $w['id'] : null;
                    $icon = isset($w['icon']) ? (string) $w['icon'] : null;

                    if (! isset($byDay[$date])) {
                        $byDay[$date] = [
                            'date' => $date,
                            'tMax' => $max,
                            'tMin' => $min,
                            'weatherCode' => $code,
                            'icon' => $icon,
                        ];
                    } else {
                        $byDay[$date]['tMax'] = max($byDay[$date]['tMax'], $max);
                        $byDay[$date]['tMin'] = min($byDay[$date]['tMin'], $min);
                    }
                }

                // sort päevad ja võta kuni 4 (täna + 3 järgmist)
                ksort($byDay);
                $daily = array_values($byDay);

                if (! empty($daily)) {
                    $tMax = $daily[0]['tMax'] ?? null;
                    $tMin = $daily[0]['tMin'] ?? null;
                }
            }

            // WeatherAPI – astronoomia + ilmaikonid (üks päring: forecast days=1)
            $astronomy = null;
            $weatherapiIcon = null;
            $weatherapiKey = config('services.weatherapi.api_key');
            if (! empty($weatherapiKey) && is_string($weatherapiKey)) {
                $forecastUrl = 'https://api.weatherapi.com/v1/forecast.json'
                    .'?key='.urlencode($weatherapiKey)
                    .'&q='.urlencode((string) $lat).','.urlencode((string) $lon)
                    .'&days=1'
                    .'&lang=et';
                /** @var \Illuminate\Http\Client\Response $forecastRes */
                $forecastRes = Http::timeout(8)->get($forecastUrl);
                if ($forecastRes->successful()) {
                    $body = $forecastRes->json();
                    // Tänase päeva ikoon (day condition) – täpne ja ühtne disain
                    $dayCondition = $body['forecast']['forecastday'][0]['day']['condition'] ?? [];
                    $iconUrl = $dayCondition['icon'] ?? null;
                    if (! empty($iconUrl) && is_string($iconUrl)) {
                        $weatherapiIcon = str_starts_with($iconUrl, '//') ? 'https:'.$iconUrl : $iconUrl;
                    }
                    // Astronoomia esimesest forecastday
                    $astro = $body['forecast']['forecastday'][0]['astro'] ?? [];
                    if (! empty($astro)) {
                        $moonPhase = $astro['moon_phase'] ?? '';
                        $moonIllum = isset($astro['moon_illumination']) ? (int) $astro['moon_illumination'] : null;
                        $phaseMap = [
                            'New Moon' => ['phase' => 'Uuskuu', 'index' => 0],
                            'Waxing Crescent' => ['phase' => 'Kasvav sirp', 'index' => 1],
                            'First Quarter' => ['phase' => 'Esimene veerand', 'index' => 2],
                            'Waxing Gibbous' => ['phase' => 'Kasvav kumer kuu', 'index' => 3],
                            'Full Moon' => ['phase' => 'Täiskuu', 'index' => 4],
                            'Waning Gibbous' => ['phase' => 'Kahanev kumer kuu', 'index' => 5],
                            'Last Quarter' => ['phase' => 'Viimane veerand', 'index' => 6],
                            'Waning Crescent' => ['phase' => 'Kahanev sirp', 'index' => 7],
                        ];
                        $mapped = $phaseMap[$moonPhase] ?? null;
                        $astronomy = [
                            'sunrise' => ! empty($astro['sunrise']) ? (string) $astro['sunrise'] : $sunriseStr,
                            'sunset' => ! empty($astro['sunset']) ? (string) $astro['sunset'] : $sunsetStr,
                            'moon_phase' => $mapped ? $mapped['phase'] : $moonPhase,
                            'moon_phase_index' => $mapped ? $mapped['index'] : null,
                            'moon_illumination' => $moonIllum !== null ? $moonIllum / 100.0 : null,
                        ];
                    }
                }
            }

            return [
                'temp' => $temp,
                'humidity' => $humidity,
                'windSpeed' => $windSpeed,
                'locationName' => $locationName,
                'tMax' => $tMax,
                'tMin' => $tMin,
                'daily' => $daily,
                'updatedAt' => now()->locale('et')->format('d.m.Y H:i'),
                'openWeatherIcon' => $openWeatherIcon,
                'weatherId' => $weatherId,
                'sunrise' => $astronomy !== null ? ($astronomy['sunrise'] ?? $sunriseStr) : $sunriseStr,
                'sunset' => $astronomy !== null ? ($astronomy['sunset'] ?? $sunsetStr) : $sunsetStr,
                'sunriseUnix' => $sunrise,
                'sunsetUnix' => $sunset,
                'astronomy' => $astronomy,
                'weatherapiIcon' => $weatherapiIcon,
            ];
        });

        if ($data === null) {
            return response()->json(['message' => 'Ilmaandmete päring ebaõnnestus'], 502);
        }
        if (isset($data['_error'])) {
            return response()->json(['message' => $data['_error']], 502);
        }

        // Öö/päev ja silt arvutatakse iga päringu ajal (mitte vahemällu), et öösel näidataks "Selge öö"
        $sunriseUnix = $data['sunriseUnix'] ?? null;
        $sunsetUnix = $data['sunsetUnix'] ?? null;
        $isNight = ($sunriseUnix !== null && $sunsetUnix !== null)
            ? (time() < $sunriseUnix || time() > $sunsetUnix)
            : null;
        // Öösel kasuta WeatherAPI öö-ikooni (URL: .../day/123.png → .../night/123.png)
        if ($isNight && ! empty($data['weatherapiIcon']) && str_contains((string) $data['weatherapiIcon'], '/day/')) {
            $data['weatherapiIcon'] = str_replace('/day/', '/night/', (string) $data['weatherapiIcon']);
        }
        $data['openWeatherLabel'] = self::openWeatherLabel(
            (int) ($data['weatherId'] ?? 800),
            (string) ($data['openWeatherIcon'] ?? '01d'),
            $isNight
        );
        unset($data['sunriseUnix'], $data['sunsetUnix'], $data['weatherId']);

        return response()->json([
            'useOpenWeather' => true,
            ...$data,
        ]);
    }
}
