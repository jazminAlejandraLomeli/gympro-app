<?php

namespace App\Http\Requests\Progress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('use-member-portal');
    }

    public function rules(): array
    {
        return [
            'weekly_attendance_goal' => ['required', 'integer', 'between:1,7'],
        ];
    }

    public function messages(): array
    {
        return [
            'weekly_attendance_goal.required' => 'Elige cuántos días por semana quieres venir.',
            'weekly_attendance_goal.integer' => 'Elige un número de días válido.',
            'weekly_attendance_goal.between' => 'Tu meta debe ser de 1 a 7 días por semana.',
        ];
    }
}
