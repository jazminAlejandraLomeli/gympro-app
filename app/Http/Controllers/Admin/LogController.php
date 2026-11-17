<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Saas\LogReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Centro de errores: final de laravel.log y trabajos fallidos de la cola.
 * Ninguna ruta de archivo viene del input; los trabajos se identifican
 * por su uuid y se validan contra la tabla failed_jobs.
 */
class LogController extends Controller
{
    public function index(LogReader $logs): Response
    {
        return Inertia::render('Admin/Logs', [
            'entries' => $logs->latest(),
            'logSize' => $logs->size(),
            'maxBytes' => LogReader::MAX_BYTES,
            'failedJobs' => $this->failedJobs(),
        ]);
    }

    public function retry(string $uuid): RedirectResponse
    {
        $this->findFailedJob($uuid);

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (Throwable $e) {
            return back()->with('error', 'No se pudo reintentar: '.str($e->getMessage())->limit(120));
        }

        return back()->with('success', 'Trabajo enviado de nuevo a la cola.');
    }

    public function forget(string $uuid): RedirectResponse
    {
        $this->findFailedJob($uuid);

        Artisan::call('queue:forget', ['id' => $uuid]);

        return back()->with('success', 'Trabajo fallido descartado.');
    }

    private function table(): string
    {
        return config('queue.failed.table', 'failed_jobs');
    }

    private function findFailedJob(string $uuid): object
    {
        return DB::table($this->table())->where('uuid', $uuid)->first() ?? abort(404);
    }

    private function failedJobs(): array
    {
        try {
            $rows = DB::table($this->table())->orderByDesc('failed_at')->limit(50)
                ->get(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']);
        } catch (Throwable) {
            return [];
        }

        return $rows->map(function ($row) {
            $payload = json_decode($row->payload, true) ?: [];
            $lines = preg_split('/\R/', (string) $row->exception);

            return [
                'uuid' => $row->uuid,
                'job' => class_basename($payload['displayName'] ?? 'Desconocido'),
                'job_full' => $payload['displayName'] ?? null,
                'connection' => $row->connection,
                'queue' => $row->queue,
                'failed_at' => $row->failed_at ? Carbon::parse($row->failed_at)->toIso8601String() : null,
                'message' => str($lines[0] ?? '')->limit(300)->toString(),
                'trace' => str(implode("\n", array_slice($lines, 1, 25)))->limit(5000)->toString(),
            ];
        })->all();
    }
}
