<?php

namespace App\Http\Requests\Training;

use App\Enums\Role;
use App\Models\RoutineRequest;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Constructor del panel: crea o guarda una rutina completa en una sola
 * petición. Al crear decide "¿Para quién?": plantilla del gimnasio o un
 * socio (y la fecha si es de un día). Con `request_id` atiende una
 * solicitud pendiente de ese mismo socio.
 */
class SaveRoutineRequest extends FormRequest
{
    use ValidatesRoutineItems;

    public function authorize(): bool
    {
        return $this->user()->can('manage-training');
    }

    protected function prepareForValidation(): void
    {
        $this->lockTypeToRoutine();
    }

    public function rules(): array
    {
        $gymId = app(TenantContext::class)->id();

        return $this->routineRules() + [
            'target' => ['nullable', 'string', Rule::in(['template', 'member'])],
            // exists NO pasa por el TenantScope: gimnasio y rol a mano
            // En plantillas se ignoran el socio, la fecha y la solicitud
            'member_id' => [
                'exclude_unless:target,member',
                'nullable',
                'required_if:target,member',
                'integer',
                Rule::exists('users', 'id')->where('gym_id', $gymId)->where('role', Role::Member->value),
            ],
            'scheduled_for' => ['exclude_unless:target,member', 'nullable', 'date_format:Y-m-d'],
            'request_id' => [
                'exclude_unless:target,member',
                'nullable',
                'integer',
                Rule::exists('routine_requests', 'id')->where('gym_id', $gymId)->where('status', RoutineRequest::PENDING),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('routine') || $this->input('target') !== 'member'
                    || ! $this->filled('request_id') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $request = RoutineRequest::find($this->integer('request_id'));
                if ($request?->user_id !== $this->integer('member_id')) {
                    $validator->errors()->add('member_id', 'La rutina debe ser para el socio que la pidió.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return $this->routineAttributes() + [
            'member_id' => 'socio',
            'scheduled_for' => 'fecha',
        ];
    }

    public function messages(): array
    {
        return $this->routineMessages() + [
            'target.in' => 'Elige si es para un socio o una plantilla del gimnasio.',
            'member_id.required_if' => 'Elige al socio.',
            'member_id.exists' => 'El socio elegido no pertenece a este gimnasio.',
            'scheduled_for.date_format' => 'La fecha no es válida.',
            'request_id.exists' => 'Esa solicitud ya fue atendida o no existe.',
        ];
    }
}
