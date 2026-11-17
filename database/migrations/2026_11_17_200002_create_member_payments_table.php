<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cobro pasivo: recepción registra el pago (sin pasarela)
        Schema::create('member_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('concept', 120);
            $table->string('method', 20); // efectivo | tarjeta | transferencia
            $table->unsignedSmallInteger('months_added')->default(0);
            $table->unsignedInteger('credits_added')->default(0);
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Historial de un socio
            $table->index(['gym_id', 'user_id', 'paid_at']);
            // Corte de caja / ingresos del gimnasio
            $table->index(['gym_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_payments');
    }
};
