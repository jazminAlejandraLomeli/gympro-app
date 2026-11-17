<?php

namespace App\Services\Training;

/**
 * Convierte un video crudo (celular) en un clip ligero para la app.
 *
 * Contrato: sin audio, máximo 480p conservando proporción (dimensiones
 * pares), máximo MAX_SECONDS segundos, H.264 yuv420p con +faststart.
 * Debe lanzar una excepción si no pudo generar $outputPath.
 */
interface ClipTranscoder
{
    public const MAX_SECONDS = 6;

    public const MAX_SHORT_SIDE = 480;

    public function transcode(string $inputPath, string $outputPath, int $crf): void;
}
