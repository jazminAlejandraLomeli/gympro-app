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
            $this->deleteStoredLogo($settings->logo_url);
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

    private function deleteStoredLogo(?string $url): void
    {
        $prefix = Storage::disk('public')->url('');

        if ($url && str_starts_with($url, $prefix)) {
            Storage::disk('public')->delete(substr($url, strlen($prefix)));
        }
    }
}
