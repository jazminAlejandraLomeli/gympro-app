<?php

namespace App\Providers;

use App\Models\Routine;
use App\Policies\RoutinePolicy;
use App\Services\Training\ClipTranscoder;
use App\Services\Training\FfmpegClipTranscoder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Módulo B: rutinas, solicitudes y biblioteca de ejercicios. */
class TrainingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(ClipTranscoder::class, FfmpegClipTranscoder::class);
    }

    public function boot(): void
    {
        Gate::policy(Routine::class, RoutinePolicy::class);
    }
}
