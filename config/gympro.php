<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marca por defecto
    |--------------------------------------------------------------------------
    |
    | Identidad visual que se usa cuando no hay un gimnasio resuelto
    | (landing, panel super_admin). Cuando exista el módulo multi-tenant,
    | cada gimnasio la sobrescribe con su registro en `gym_settings`.
    |
    */

    'default_brand' => [
        'brand_name' => env('APP_NAME', 'GymPro'),
        'logo_url' => null,
        'favicon_url' => null,
        'primary_color' => '#16a34a',
        'secondary_color' => '#0f172a',
    ],

];
