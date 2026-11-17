<?php

namespace App\Jobs;

use App\Models\Exercise;
use App\Models\Gym;
use App\Services\Training\ClipTranscoder;
use App\Services\Training\FfmpegClipTranscoder;
use App\Tenancy\InteractsWithTenant;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Comprime el clip crudo de un ejercicio (disco local privado) a un MP4
 * ligero en el disco public. Corre en la cola (Redis) para no bloquear
 * al entrenador que lo subió desde el celular.
 *
 *  - Intenta CRF 30; si pesa más de gympro.ffmpeg.max_output_bytes,
 *    vuelve a codificar con 34 y luego 38.
 *  - Éxito: guarda el clip, borra el crudo y el clip anterior → ready.
 *  - Falla: borra crudo y temporales, deja el clip anterior → failed.
 */
class CompressExerciseClip implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    /** CRF a probar en orden (más alto = más ligero). */
    public const CRF_STEPS = [30, 34, 38];

    /** Un clip que falla no se reintenta: el entrenador puede volver a subirlo. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $exerciseId, public string $rawPath)
    {
        $this->captureTenant();
    }

    public function handle(): void
    {
        $raw = Storage::disk('local');
        $exercise = Exercise::find($this->exerciseId);

        if (! $exercise) {
            $raw->delete($this->rawPath);

            return;
        }

        $tmpOutput = $this->tempPath();

        try {
            if (! $raw->exists($this->rawPath)) {
                throw new \RuntimeException('No existe el video original.');
            }

            $transcoder = $this->transcoder();
            $limit = (int) config('gympro.ffmpeg.max_output_bytes', 800 * 1024);
            $size = 0;

            foreach (self::CRF_STEPS as $crf) {
                @unlink($tmpOutput);
                $transcoder->transcode($raw->path($this->rawPath), $tmpOutput, $crf);
                clearstatcache(true, $tmpOutput);
                $size = is_file($tmpOutput) ? (int) filesize($tmpOutput) : 0;

                if ($size === 0) {
                    throw new \RuntimeException('El transcodificador no generó el clip.');
                }
                if ($size <= $limit) {
                    break;
                }
            }

            if ($size > $limit) {
                Log::info('Clip por encima del peso objetivo tras el CRF máximo.', [
                    'gym_id' => $this->gymId, 'exercise_id' => $exercise->id, 'bytes' => $size,
                ]);
            }

            $newPath = Storage::disk('public')->putFileAs(
                "gyms/{$this->gymId}/clips",
                new File($tmpOutput),
                Str::uuid().'.mp4',
            );

            if (! $newPath) {
                throw new \RuntimeException('No se pudo guardar el clip comprimido.');
            }

            $previous = $exercise->clip_path;

            $exercise->forceFill([
                'clip_path' => $newPath,
                'clip_status' => Exercise::CLIP_READY,
                'clip_size_bytes' => $size,
            ])->save();

            if ($previous && $previous !== $newPath) {
                Storage::disk('public')->delete($previous);
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo comprimir el clip del ejercicio.', [
                'gym_id' => $this->gymId,
                'exercise_id' => $exercise->id,
                'error' => $e->getMessage(),
            ]);

            $exercise->forceFill(['clip_status' => Exercise::CLIP_FAILED])->save();
        } finally {
            $raw->delete($this->rawPath);
            @unlink($tmpOutput);
        }
    }

    /**
     * Si el worker mata el job (timeout), handle() no llega a su catch:
     * aquí se marca como fallido y se limpia el crudo.
     */
    public function failed(?Throwable $e): void
    {
        Storage::disk('local')->delete($this->rawPath);

        $gym = $this->gymId ? Gym::find($this->gymId) : null;
        if (! $gym) {
            return;
        }

        app(TenantContext::class)->runAs($gym, function () {
            Exercise::whereKey($this->exerciseId)
                ->where('clip_status', Exercise::CLIP_PROCESSING)
                ->update(['clip_status' => Exercise::CLIP_FAILED]);
        });
    }

    private function transcoder(): ClipTranscoder
    {
        // TrainingServiceProvider lo enlaza; si aún no está registrado, FFmpeg.
        return app()->bound(ClipTranscoder::class)
            ? app(ClipTranscoder::class)
            : app(FfmpegClipTranscoder::class);
    }

    private function tempPath(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'gympro-clip-'.Str::uuid().'.mp4';
    }
}
