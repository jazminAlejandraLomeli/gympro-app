<?php

namespace App\Http\Requests\Auth;

use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Ese correo no parece válido.',
            'password.required' => 'Escribe tu contraseña.',
        ];
    }

    /**
     * La búsqueda del usuario pasa por el Global Scope: en el subdominio
     * de un gimnasio solo existen sus usuarios; en el dominio central,
     * solo los super_admin.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        $gym = app(TenantContext::class)->id() ?? 'central';

        return Str::transliterate("login|{$gym}|".Str::lower($this->string('email')).'|'.$this->ip());
    }
}
