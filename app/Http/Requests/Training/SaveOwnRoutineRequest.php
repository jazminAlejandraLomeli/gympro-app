<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

/** Constructor del socio: su propia rutina (de un día o de la semana). */
class SaveOwnRoutineRequest extends FormRequest
{
    use ValidatesRoutineItems;

    public function authorize(): bool
    {
        return $this->user()->can('use-member-portal');
    }

    protected function prepareForValidation(): void
    {
        $this->lockTypeToRoutine();
    }

    public function rules(): array
    {
        return $this->routineRules();
    }

    public function attributes(): array
    {
        return $this->routineAttributes();
    }

    public function messages(): array
    {
        return $this->routineMessages();
    }
}
