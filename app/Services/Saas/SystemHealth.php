<?php

namespace App\Services\Saas;

use App\Enums\GymStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\TenantScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Salud de la plataforma para el super_admin.
 *
 * Cada chequeo va en try/catch y mide su tiempo: si Redis, Meilisearch o
 * la cola no están disponibles, la página muestra "caído" en lugar de
 * romperse.
 */
class SystemHealth
{
    /** Segundos que se cachea el uso de almacenamiento por gimnasio. */
    public const STORAGE_TTL = 600;

    public const STORAGE_CACHE_KEY = 'saas:storage:by-gym';

    /** @return array{active_gyms: int, suspended_gyms: int, members: int, scans_today: int} */
    public function figures(): array
    {
        return [
            'active_gyms' => Gym::query()->where('status', GymStatus::Active)->count(),
            'suspended_gyms' => Gym::query()->where('status', GymStatus::Suspended)->count(),
            // Explícito sin filtro por gimnasio: cifras de toda la red
            'members' => User::withoutGlobalScope(TenantScope::class)->where('role', Role::Member)->count(),
            'scans_today' => Attendance::withoutGlobalScope(TenantScope::class)
                ->where('scanned_at', '>=', today())
                ->where('scanned_at', '<', today()->addDay())
                ->count(),
        ];
    }

    /** @return list<array{key: string, label: string, ok: bool, ms: float|null, detail: string}> */
    public function checks(): array
    {
        return [
            $this->check('database', 'Base de datos', function () {
                DB::select('select 1');

                return DB::connection()->getDriverName();
            }),
            $this->check('redis', 'Redis', function () {
                Redis::connection()->ping();

                return 'Responde';
            }),
            $this->check('meilisearch', 'Meilisearch', function () {
                $host = rtrim((string) config('scout.meilisearch.host'), '/');
                $response = Http::timeout(2)->connectTimeout(1)->acceptJson()->get($host.'/health');
                $response->throw();

                return 'Estado: '.($response->json('status') ?? 'desconocido');
            }),
            $this->check('queue', 'Cola (default)', function () {
                $connection = config('queue.default');
                $size = Queue::connection($connection)->size('default');

                return "{$size} trabajo(s) pendientes en «{$connection}»";
            }),
            $this->check('failed_jobs', 'Trabajos fallidos', function () {
                $count = $this->failedJobsCount();
                if ($count > 0) {
                    throw new \RuntimeException("{$count} trabajo(s) fallidos");
                }

                return 'Sin fallas';
            }),
            $this->check('ffmpeg', 'FFmpeg', function () {
                $path = $this->ffmpegPath() ?? throw new \RuntimeException('No se encontró el binario');

                return $path;
            }),
        ];
    }

    public function failedJobsCount(): int
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Uso de disco multimedia por gimnasio (disco `public`, carpeta gyms/{id}/).
     * Cacheado 10 min; si el caché (Redis) no responde, se calcula directo.
     *
     * @return array<int, int> gym_id => bytes
     */
    public function storageByGym(): array
    {
        try {
            return Cache::remember(self::STORAGE_CACHE_KEY, self::STORAGE_TTL, fn () => $this->computeStorage());
        } catch (Throwable) {
            return $this->computeStorage();
        }
    }

    /** @return array<int, int> */
    private function computeStorage(): array
    {
        $disk = Storage::disk('public');
        $sizes = [];

        foreach (Gym::query()->pluck('id') as $id) {
            $bytes = 0;
            try {
                foreach ($disk->allFiles("gyms/{$id}") as $file) {
                    $bytes += (int) $disk->size($file);
                }
            } catch (Throwable) {
                // carpeta inexistente o disco no disponible: cuenta como 0
            }
            $sizes[(int) $id] = $bytes;
        }

        return $sizes;
    }

    private function ffmpegPath(): ?string
    {
        $binary = (string) config('gympro.ffmpeg.binary', 'ffmpeg');

        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_file($binary) && is_executable($binary) ? $binary : null;
        }

        return (new ExecutableFinder)->find($binary);
    }

    /** @param  callable(): string  $probe */
    private function check(string $key, string $label, callable $probe): array
    {
        $start = hrtime(true);

        try {
            $detail = $probe();
            $ok = true;
        } catch (Throwable $e) {
            $detail = str($e->getMessage())->squish()->limit(160)->toString() ?: class_basename($e);
            $ok = false;
        }

        return [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'ms' => round((hrtime(true) - $start) / 1e6, 1),
            'detail' => $detail,
        ];
    }
}
