<?php

namespace App\Providers;

use App\Services\Training\ClipTranscoder;
use App\Services\Training\FfmpegClipTranscoder;
use Illuminate\Support\ServiceProvider;

/** Módulo B: rutinas y clips de ejercicios. */
class TrainingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(ClipTranscoder::class, FfmpegClipTranscoder::class);
    }
}
