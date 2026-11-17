<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dominio base
    |--------------------------------------------------------------------------
    |
    | Cada gimnasio vive en {slug}.{base_domain}. El dominio base solo
    | (o un subdominio reservado) es el sitio central / super_admin.
    | Local: "localhost" → irondemo.localhost:8000 (los navegadores
    | resuelven *.localhost a 127.0.0.1 sin tocar /etc/hosts).
    |
    */

    'base_domain' => env('APP_BASE_DOMAIN', 'localhost'),

    'reserved_subdomains' => ['www', 'app', 'admin', 'api', 'mail', 'static', 'cdn'],

    /*
    |--------------------------------------------------------------------------
    | Marca por defecto
    |--------------------------------------------------------------------------
    |
    | Se usa en el dominio central y como respaldo si un gimnasio aún no
    | tiene registro en `gym_settings`.
    |
    */

    'default_brand' => [
        'brand_name' => env('APP_NAME', 'GymPro'),
        'logo_url' => null,
        'favicon_url' => null,
        'primary_color' => '#16a34a',
        'secondary_color' => '#0f172a',
    ],

    /*
    |--------------------------------------------------------------------------
    | Licencia SaaS
    |--------------------------------------------------------------------------
    */

    'saas_monthly_fee' => 1800,

    /*
    |--------------------------------------------------------------------------
    | FFmpeg (Módulo B: compresión de clips de ejercicios)
    |--------------------------------------------------------------------------
    */

    'ffmpeg' => [
        'binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
        'timeout' => 120,
        // Peso objetivo por clip (300 KB – 800 KB)
        'max_output_bytes' => 800 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rachas (Módulo C)
    |--------------------------------------------------------------------------
    */

    'streaks' => [
        // Días mínimos de asistencia por semana (lunes a domingo)
        'weekly_goal' => 3,
    ],

];
