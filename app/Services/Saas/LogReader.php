<?php

namespace App\Services\Saas;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Lee las últimas entradas de storage/logs/laravel.log.
 *
 * Solo lee el FINAL del archivo (máximo 512 KB) para no cargar logs de
 * cientos de MB en memoria. La ruta es fija: nunca viene del input.
 */
class LogReader
{
    public const MAX_BYTES = 512 * 1024;

    public const MAX_ENTRIES = 100;

    private const TRACE_LINES = 25;

    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+([\w-]+)\.(\w+):\s?(.*)$/';

    public function __construct(private ?string $path = null)
    {
        $this->path ??= storage_path('logs/laravel.log');
    }

    /**
     * @return list<array{id: int, date: string|null, env: string, level: string, message: string, trace: string|null, trace_truncated: bool}>
     */
    public function latest(int $limit = self::MAX_ENTRIES): array
    {
        $content = $this->tail();
        if ($content === '') {
            return [];
        }

        $entries = [];
        $current = null;

        foreach (preg_split('/\R/', $content) as $line) {
            if (preg_match(self::HEADER, $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = ['date' => $m[1], 'env' => $m[2], 'level' => strtoupper($m[3]), 'lines' => [$m[4]]];
            } elseif ($current) {
                $current['lines'][] = $line;
            }
            // Las líneas antes del primer encabezado son el resto de una
            // entrada cortada por el límite de bytes: se descartan.
        }
        if ($current) {
            $entries[] = $current;
        }

        $entries = array_slice($entries, -$limit);

        return array_values(array_map(fn (array $e, int $i) => $this->format($e, $i), array_reverse($entries), array_keys($entries)));
    }

    public function size(): int
    {
        return is_file($this->path) ? (int) filesize($this->path) : 0;
    }

    private function tail(): string
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            return '';
        }

        try {
            $handle = fopen($this->path, 'rb');
            $size = filesize($this->path);
            $offset = max(0, $size - self::MAX_BYTES);
            fseek($handle, $offset);
            $content = (string) stream_get_contents($handle, self::MAX_BYTES);
            fclose($handle);
        } catch (Throwable) {
            return '';
        }

        // Si se empezó a mitad de una línea, se tira esa línea parcial
        if ($offset > 0 && ($pos = strpos($content, "\n")) !== false) {
            $content = substr($content, $pos + 1);
        }

        // Evita romper JSON con bytes UTF-8 cortados
        return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    }

    private function format(array $entry, int $id): array
    {
        $first = array_shift($entry['lines']);
        $rest = array_values(array_filter($entry['lines'], fn ($l) => trim($l) !== ''));

        // La excepción suele venir como mensaje + {"exception":"[object] ... at file:line)
        $message = $first;
        if (preg_match('/^(.*?)\s*(\{"exception":.*)$/s', $first, $m)) {
            $message = $m[1];
            array_unshift($rest, $m[2]);
        }

        $truncated = count($rest) > self::TRACE_LINES;
        $trace = array_slice($rest, 0, self::TRACE_LINES);

        try {
            $date = Carbon::parse($entry['date'])->toIso8601String();
        } catch (Throwable) {
            $date = null;
        }

        return [
            'id' => $id,
            'date' => $date,
            'env' => $entry['env'],
            'level' => $entry['level'],
            'message' => str($message)->limit(600)->toString(),
            'trace' => $trace ? str(implode("\n", $trace))->limit(6000)->toString() : null,
            'trace_truncated' => $truncated,
        ];
    }
}
