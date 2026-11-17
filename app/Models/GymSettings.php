<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Identidad visual (marca blanca) de un gimnasio. */
#[Fillable(['brand_name', 'logo_url', 'favicon_url', 'primary_color', 'secondary_color'])]
class GymSettings extends Model
{
    use BelongsToGym;

    protected $table = 'gym_settings';

    protected static function booted(): void
    {
        $forget = fn (GymSettings $settings) => Cache::forget(self::brandCacheKey($settings->gym_id));

        static::saved($forget);
        static::deleted($forget);
    }

    public static function brandCacheKey(int $gymId): string
    {
        return "gym:{$gymId}:brand";
    }
}
