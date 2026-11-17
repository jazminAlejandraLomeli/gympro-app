<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\UploadExerciseClipRequest;
use App\Jobs\CompressExerciseClip;
use App\Models\Exercise;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clip del ejercicio. El original se guarda en el disco privado y se
 * comprime en la cola: el entrenador no espera a FFmpeg.
 */
class ExerciseClipController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function store(UploadExerciseClipRequest $request, Exercise $exercise): RedirectResponse
    {
        $file = $request->file('clip');
        $extension = in_array($file->extension(), ['mp4', 'mov', 'qt', 'webm', 'm4v', 'gif'], true) ? $file->extension() : 'mp4';

        $rawPath = $file->storeAs(
            "gyms/{$this->tenant->id()}/clips/raw",
            Str::uuid().'.'.$extension,
            'local',
        );

        if (! $rawPath) {
            return back()->with('error', 'No se pudo guardar el video. Inténtalo de nuevo.');
        }

        // El clip anterior (si hay) se sigue viendo hasta que el nuevo esté listo
        $exercise->forceFill(['clip_status' => Exercise::CLIP_PROCESSING])->save();

        CompressExerciseClip::dispatch($exercise->id, $rawPath);

        return back()->with('success', 'Video recibido. Lo estamos procesando, tarda unos segundos.');
    }

    public function destroy(Exercise $exercise): RedirectResponse
    {
        if ($exercise->clip_path) {
            Storage::disk('public')->delete($exercise->clip_path);
        }

        $exercise->forceFill([
            'clip_path' => null,
            'clip_status' => Exercise::CLIP_NONE,
            'clip_size_bytes' => null,
        ])->save();

        return back()->with('success', 'Clip eliminado.');
    }
}
