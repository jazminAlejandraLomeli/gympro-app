<?php

namespace App\Support;

use App\Models\Gym;
use App\Models\GymSettings;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Identidad visual (marca blanca) que se aplica a la interfaz.
 *
 * Con gimnasio activo sale de su `gym_settings` (cacheado en Redis en
 * gym:{id}:brand); en el dominio central, de config('gympro.default_brand').
 */
final class Brand
{
    public function __construct(
        public readonly string $name,
        public readonly string $primary,
        public readonly string $secondary,
        public readonly ?string $logoUrl = null,
        public readonly ?string $faviconUrl = null,
    ) {}

    /** Segundos que vive la marca en Redis (se invalida al guardar). */
    public const CACHE_TTL = 3600;

    public static function current(): self
    {
        $gym = app(TenantContext::class)->gym();

        return $gym ? self::forGym($gym) : self::default();
    }

    public static function forGym(Gym $gym): self
    {
        $data = Cache::remember(
            GymSettings::brandCacheKey($gym->getKey()),
            self::CACHE_TTL,
            // Filtro explícito por gym_id: no depende del contexto actual
            fn () => GymSettings::withoutGlobalScopes()
                ->where('gym_id', $gym->getKey())
                ->first()
                ?->only(['brand_name', 'logo_url', 'favicon_url', 'primary_color', 'secondary_color']) ?? [],
        );

        $fallback = config('gympro.default_brand');

        return new self(
            name: $data['brand_name'] ?? $gym->name,
            primary: self::sanitizeHex($data['primary_color'] ?? null, $fallback['primary_color']),
            secondary: self::sanitizeHex($data['secondary_color'] ?? null, $fallback['secondary_color']),
            logoUrl: self::sanitizeUrl($data['logo_url'] ?? null),
            faviconUrl: self::sanitizeUrl($data['favicon_url'] ?? null),
        );
    }

    public static function default(): self
    {
        $c = config('gympro.default_brand');

        return new self(
            name: $c['brand_name'],
            primary: self::sanitizeHex($c['primary_color'], '#16a34a'),
            secondary: self::sanitizeHex($c['secondary_color'], '#0f172a'),
            logoUrl: $c['logo_url'],
            faviconUrl: $c['favicon_url'],
        );
    }

    /**
     * Color de texto legible (negro o blanco) sobre un fondo dado,
     * para que un gimnasio con color amarillo no quede ilegible.
     */
    public static function readableTextOn(string $hex): string
    {
        [$r, $g, $b] = array_map(
            fn (string $c) => hexdec($c) / 255,
            str_split(ltrim($hex, '#'), 2),
        );

        $lin = fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        // Umbral donde el contraste con negro supera al de blanco
        return $luminance > 0.179 ? '#000000' : '#ffffff';
    }

    /**
     * Solo acepta #RRGGBB. Evita inyectar CSS arbitrario en <style>,
     * ya que el color lo captura cada gimnasio desde su panel.
     */
    public static function sanitizeHex(?string $hex, string $fallback): string
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : $fallback;
    }

    /** Solo https/http o rutas relativas al sitio (nada de javascript:, data:). */
    public static function sanitizeUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return preg_match('#^(https?://|/(?!/))#i', $url) ? $url : null;
    }

    /** Variables CSS que consume Tailwind (ver resources/css/app.css). */
    public function cssVariables(): array
    {
        return [
            '--brand-primary' => $this->primary,
            '--brand-on-primary' => self::readableTextOn($this->primary),
            '--brand-secondary' => $this->secondary,
            '--brand-on-secondary' => self::readableTextOn($this->secondary),
        ];
    }

    /** Lo que recibe React como prop compartida `brand`. */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'logoUrl' => $this->logoUrl,
            'faviconUrl' => $this->faviconUrl,
            'primary' => $this->primary,
            'secondary' => $this->secondary,
        ];
    }
}
