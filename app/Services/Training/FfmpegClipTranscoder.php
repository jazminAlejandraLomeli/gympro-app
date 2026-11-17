<?php

namespace App\Services\Training;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Implementación con el binario de FFmpeg (config('gympro.ffmpeg')). */
class FfmpegClipTranscoder implements ClipTranscoder
{
    public function __construct(
        private ?string $binary = null,
        private ?int $timeout = null,
    ) {
        $this->binary ??= (string) config('gympro.ffmpeg.binary', 'ffmpeg');
        $this->timeout ??= (int) config('gympro.ffmpeg.timeout', 120);
    }

    public function transcode(string $inputPath, string $outputPath, int $crf): void
    {
        $process = new Process($this->arguments($inputPath, $outputPath, $crf));
        $process->setTimeout($this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('FFmpeg falló: '.mb_substr(trim($process->getErrorOutput()), -500));
        }

        clearstatcache(true, $outputPath);
        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('FFmpeg no generó el archivo de salida.');
        }
    }

    /** @return list<string> */
    public function arguments(string $inputPath, string $outputPath, int $crf): array
    {
        $max = self::MAX_SHORT_SIDE;

        // El lado corto queda en máx. 480 px; el otro se calcula con -2
        // (conserva proporción y lo deja par). FFmpeg ya aplica la
        // rotación del celular antes del filtro, así que iw/ih son reales.
        $scale = "scale=w='if(gt(iw,ih),-2,trunc(min({$max},iw)/2)*2)'"
            .":h='if(gt(iw,ih),trunc(min({$max},ih)/2)*2,-2)'";

        return [
            $this->binary,
            '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $inputPath,
            '-t', (string) self::MAX_SECONDS,
            '-map', '0:v:0',
            '-an', '-sn', '-dn',
            '-map_metadata', '-1',
            '-vf', $scale.',setsar=1,format=yuv420p',
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-profile:v', 'main',
            '-crf', (string) $crf,
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            '-f', 'mp4',
            $outputPath,
        ];
    }
}
