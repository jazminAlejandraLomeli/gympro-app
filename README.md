# gympro-app
Plataforma para gestion general de un GYM.

SaaS multi-tenant marca blanca en Laravel 13 (PHP 8.2+).

## Infraestructura

| Servicio     | Dónde corre                         | Uso                                         |
|--------------|-------------------------------------|---------------------------------------------|
| Base de datos| **Servicio rentado externo**        | Datos (se conecta con `DB_*` en `.env`)     |
| Redis        | Docker (`docker-compose.yml`)       | Sesiones, caché, estatus QR, rachas, colas  |
| Meilisearch  | Docker (`docker-compose.yml`)       | Búsqueda instantánea (Laravel Scout)        |

## Arranque local

```bash
cp .env.example .env          # luego edita contraseñas y datos de la BD rentada
composer install
php artisan key:generate
docker compose up -d          # levanta Redis y Meilisearch
php artisan migrate           # corre contra la BD rentada
php artisan serve
php artisan queue:work        # en otra terminal: procesa colas (videos, indexado)
```

Ver estado de los contenedores: `docker compose ps`
