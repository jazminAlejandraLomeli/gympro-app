<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_name', 80)->nullable();
            $table->string('phone', 20)->nullable();
            // Activo si hoy <= expires_on O credits > 0
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('credits')->default(0);
            // Contenido del QR (va firmado con HMAC, ver QrSigner)
            $table->uuid('qr_token')->unique();
            $table->timestamps();

            // Un socio tiene una sola membresía por gimnasio
            $table->unique(['gym_id', 'user_id']);
            // Filtros de "vencidos" / "por vencer"
            $table->index(['gym_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
