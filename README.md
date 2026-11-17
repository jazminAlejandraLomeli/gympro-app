# gympro-app
Plataforma para gestion general de un GYM.

SaaS multi-tenant marca blanca en Laravel 13 puro, sin starter kit (requiere PHP 8.3+).

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

## Multi-tenant (un gimnasio por subdominio)

- Cada gimnasio vive en `{slug}.APP_BASE_DOMAIN`. Local: `http://irondemo.localhost:8000`.
- Todo modelo con `gym_id` usa el trait `App\Tenancy\BelongsToGym`: filtra solo por el
  gimnasio activo, rellena `gym_id` al crear y bloquea mover datos entre gimnasios.
- Sin gimnasio activo (dominio central) no se ve ningún dato operativo; el `super_admin`
  autenticado en el dominio central ve todo.
- Llaves de Redis por gimnasio: `app(TenantContext::class)->key('member', $id, 'status')`
  → `gym:{id}:member:{id}:status`.
- Roles: `super_admin`, `gym_admin`, `trainer`, `member` (`App\Enums\Role`). En rutas:
  `->middleware('role:gym_admin,trainer')`; permisos generales en `AppServiceProvider`.

Datos de demo (solo desarrollo): `php artisan migrate:fresh --seed` crea Iron Demo
(`irondemo`) y Titan Fit (`titan`). Usuarios y contraseña en `database/seeders/DatabaseSeeder.php`.

