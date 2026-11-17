<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\UpdateBrandingRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Marca blanca: el gym_admin cambia nombre, colores y logotipo. */
class BrandingController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function edit(): Response
    {
        $settings = $this->tenant->gym()->settings;

        return Inertia::render('Gym/Branding', [
            'settings' => $settings->only(['brand_name', 'logo_url', 'primary_color', 'secondary_color']),
        ]);
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $gym = $this->tenant->gym();
        $settings = $gym->settings;

        $settings->fill($request->safe()->only(['brand_name', 'primary_color', 'secondary_color']));

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            $this->deleteStoredLogo($settings->logo_url, $gym->id);
            $settings->logo_url = null;
        }

        if ($request->hasFile('logo')) {
            // Carpeta por gimnasio; nombre aleatorio para no cachear el viejo
            $path = $request->file('logo')->store("gyms/{$gym->id}/branding", 'public');
            $settings->logo_url = Storage::disk('public')->url($path);
        }

        $settings->save(); // invalida la marca en Redis (GymSettings::booted)

        return back()->with('success', 'Marca actualizada.');
    }

    /**
     * Borra el archivo del logo anterior. Acepta la URL relativa actual
     * (/storage/...) y las absolutas guardadas antes (http://host/storage/...).
     */
    private function deleteStoredLogo(?string $url, int $gymId): void
    {
        if (! $url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $prefix = rtrim(parse_url(Storage::disk('public')->url(''), PHP_URL_PATH) ?? '/storage', '/').'/';

        if (str_starts_with($path, $prefix)) {
            $relative = substr($path, strlen($prefix));
            // Solo dentro de la carpeta de ESTE gimnasio (nunca ../)
            if (! str_contains($relative, '..') && str_starts_with($relative, "gyms/{$gymId}/")) {
                Storage::disk('public')->delete($relative);
            }
        }
    }
}
