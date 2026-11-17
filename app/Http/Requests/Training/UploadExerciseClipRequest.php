<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

/** Clip grabado en el celular: mp4, mov o webm de hasta 50 MB. */
class UploadExerciseClipRequest extends FormRequest
{
    public const MAX_KB = 50 * 1024;

    public function authorize(): bool
    {
        return $this->user()->can('manage-training');
    }

    public function rules(): array
    {
        return [
            'clip' => [
                'required',
                'file',
                // Se valida por contenido (no por la extensión que manda el navegador).
                // Los GIF animados también se aceptan: FFmpeg los convierte a MP4.
                'mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v,image/gif',
                'max:'.self::MAX_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'clip.required' => 'Elige o graba un video, o sube un GIF.',
            'clip.file' => 'No se pudo subir el video. Inténtalo de nuevo.',
            'clip.uploaded' => 'No se pudo subir el video (¿pesa más de 50 MB?).',
            'clip.mimetypes' => 'El archivo debe ser un video MP4, MOV o WebM, o un GIF animado.',
            'clip.max' => 'El archivo debe pesar menos de 50 MB.',
        ];
    }
}
