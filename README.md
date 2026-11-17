# gympro-app
Plataforma para gestion general de un GYM.

SaaS multi-tenant marca blanca en Laravel 13 puro (sin starter kit) + Inertia v3 + React 19 + Tailwind 4. Requiere PHP 8.3+.

## Infraestructura

| Servicio     | Dónde corre                         | Uso                                         |
|--------------|-------------------------------------|---------------------------------------------|
| Base de datos| **Servicio rentado externo**        | Datos (se conecta con `DB_*` en `.env`)     |
| Redis        | Docker (`docker-compose.yml`)       | Sesiones, caché, estatus QR, rachas, colas  |
| Meilisearch  | Docker (`docker-compose.yml`)       | Búsqueda instantánea (Laravel Scout)        |

## Arranque local

```bash
cp .env.example .env          # luego edita contraseñas y datos de la BD rentada
composer install && npm install
php artisan key:generate
php artisan storage:link
docker compose up -d          # levanta Redis y Meilisearch
php artisan migrate           # corre contra la BD rentada
php artisan scout:sync-index-settings          # filtros de Meilisearch (gym_id)
php artisan scout:import "App\Models\Membership"
```

Para trabajar, en terminales separadas:

```bash
php artisan serve             # http://localhost:8000 y http://{slug}.localhost:8000
npm run dev                   # Vite con recarga en caliente
php artisan queue:work        # asistencias del escáner, clips FFmpeg, indexado
```

Ver estado de los contenedores: `docker compose ps`

### Requisitos del servidor (producción)

- PHP 8.3+, `php artisan storage:link`, un worker de `queue:work` siempre activo (Supervisor).
- FFmpeg con libx264 (`FFMPEG_BINARY` si no está en el PATH).
- Subida de clips de hasta 50 MB: `upload_max_filesize=64M`, `post_max_size=64M` en php.ini y
  `client_max_body_size 64m;` en nginx.
- DNS comodín `*.tudominio.mx` + certificado SSL comodín. La cámara del escáner QR solo
  funciona con HTTPS.
- `APP_BASE_DOMAIN=tudominio.mx` y `MEILI_ENV=production`.

## Módulos

| Módulo | Quién | Dónde |
|---|---|---|
| 0. Panel del SaaS: gimnasios, pagos de licencia, MRR, salud, errores | super_admin | `localhost/admin` |
| A. Socios, membresías, pagos en mostrador, entrenadores, escáner QR | gym_admin | `/panel/socios`, `/panel/escaner` |
| A. Credencial QR firmada | socio | `/app/credencial` |
| B. Ejercicios con clip (FFmpeg en cola) y constructor de rutinas | gym_admin, trainer | `/panel/ejercicios`, `/panel/rutinas` |
| B. Rutina del día | socio | `/app/rutina` |
| C y D. Rachas semanales, medidas, logros y gráficas | socio / staff | `/app/progreso`, `/panel/valoraciones` |
| E. Avisos y promociones con vigencia automática | gym_admin | `/panel/avisos` |
| F. Inventario, movimientos y venta en mostrador | gym_admin | `/panel/inventario` |
| Marca blanca (logo y colores) | gym_admin | `/panel/marca` |

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

Datos de demo (solo desarrollo, nunca contra producción): `php artisan migrate:fresh --seed`
crea Iron Demo (`irondemo`), Titan Fit (`titan`) y Pulso Box (`pulso`, suspendido), con datos
de todos los módulos. Usuarios y contraseña en `database/seeders/DatabaseSeeder.php`.

