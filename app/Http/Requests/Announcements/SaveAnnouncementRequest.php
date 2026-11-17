<?php

namespace App\Http\Requests\Announcements;

use App\Enums\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Alta y edición de avisos. Fechas en hora local del gimnasio (datetime-local). */
class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-gym');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pinned' => $this->boolean('pinned'),
            'expires_at' => $this->input('expires_at') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AnnouncementType::class)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'pinned' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Elige el tipo de aviso.',
            'type.enum' => 'El tipo de aviso no es válido.',
            'title.required' => 'Escribe un título.',
            'title.max' => 'El título no puede pasar de 120 caracteres.',
            'body.required' => 'Escribe el contenido del aviso.',
            'body.max' => 'El contenido no puede pasar de 2000 caracteres.',
            'starts_at.required' => 'Indica desde cuándo se publica.',
            'starts_at.date' => 'La fecha de inicio no es válida.',
            'expires_at.date' => 'La fecha de expiración no es válida.',
            'expires_at.after' => 'La expiración debe ser posterior al inicio.',
            'pinned.boolean' => 'El valor de "fijar" no es válido.',
        ];
    }

    /** Datos listos para el modelo, con fechas en la zona del sistema. */
    public function announcementData(): array
    {
        $data = $this->safe()->only(['type', 'title', 'body', 'pinned']);
        $data['starts_at'] = Carbon::parse($this->validated('starts_at'));
        $data['expires_at'] = $this->validated('expires_at') ? Carbon::parse($this->validated('expires_at')) : null;

        return $data;
    }
}
