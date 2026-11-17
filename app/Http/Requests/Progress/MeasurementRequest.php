<?php

namespace App\Http\Requests\Progress;

use App\Models\BodyMeasurement;
use App\Models\User;
use App\Services\Progress\BodyMetric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Reglas comunes de una valoración física. El socio dueño de la
 * valoración lo define cada subclase (nunca viene del input).
 */
abstract class MeasurementRequest extends FormRequest
{
    /** Socio al que pertenece la valoración. */
    abstract public function member(): User;

    public function rules(): array
    {
        $rules = [
            'measured_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        $metrics = array_map(fn (BodyMetric $m) => $m->value, BodyMetric::cases());

        foreach (BodyMetric::cases() as $metric) {
            [$min, $max] = $metric->range();
            $others = implode(',', array_diff($metrics, [$metric->value]));
            $rules[$metric->value] = ['nullable', "required_without_all:{$others}", 'numeric', "between:{$min},{$max}", 'decimal:0,1'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('measured_on')) {
                    return;
                }
                // Una valoración por día. La consulta pasa por el Global
                // Scope (solo el gimnasio activo).
                $exists = BodyMeasurement::query()
                    ->where('user_id', $this->member()->id)
                    ->where('measured_on', $this->input('measured_on'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('measured_on', 'Ya hay una valoración registrada en esa fecha.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        $names = ['measured_on' => 'fecha', 'notes' => 'notas'];
        foreach (BodyMetric::cases() as $metric) {
            $names[$metric->value] = mb_strtolower($metric->label());
        }

        return $names;
    }

    public function messages(): array
    {
        $messages = [
            'measured_on.required' => 'Indica la fecha de la valoración.',
            'measured_on.date_format' => 'La fecha no es válida.',
            'measured_on.before_or_equal' => 'La fecha no puede ser futura.',
            'measured_on.after_or_equal' => 'La fecha no es válida.',
            'notes.max' => 'Las notas pueden tener hasta 500 caracteres.',
        ];

        foreach (BodyMetric::cases() as $metric) {
            [$min, $max] = $metric->range();
            $label = mb_strtolower($metric->label());
            $messages["{$metric->value}.required_without_all"] = 'Captura al menos una medida.';
            $messages["{$metric->value}.numeric"] = "El valor de {$label} debe ser un número.";
            $messages["{$metric->value}.between"] = "El valor de {$label} debe estar entre {$min} y {$max} {$metric->unit()}.";
            $messages["{$metric->value}.decimal"] = "Usa máximo un decimal en {$label}.";
        }

        return $messages;
    }

    /** Datos limpios listos para fill(). */
    public function measurementData(): array
    {
        return $this->safe()->only(['measured_on', 'notes', ...BodyMeasurement::METRICS]);
    }
}
