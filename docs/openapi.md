# OpenAPI juhend (Lihtne)

See projekt kasutab OpenAPI dokumentatsiooni jaoks `dedoc/scramble` paketti.

## 1) Käivita backend

```bash
php artisan serve
```

## 2) Ava API dokumentatsioon brauseris

- UI: `http://127.0.0.1:8000/docs/api`
- JSON spec: `http://127.0.0.1:8000/docs/api.json`

## 3) Genereeri fail `api.json` (repo juuresse)

```bash
composer openapi
```

See käivitab taustal käsu:

```bash
php artisan scramble:export
```

## 4) Kuidas lisada endpointi kirjeldus

Kasuta controlleris Scramble attribute'e:

- `#[Group('Nimi', 'Kirjeldus')]` endpointide grupiks
- `#[Endpoint(title: '...', description: '...')]` endpointi kirjelduseks

Näide olemasolevast endpointist: `app/Http/Controllers/Api/HealthCheckController.php`.
