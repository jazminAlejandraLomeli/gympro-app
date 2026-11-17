<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de la licencia SaaS (Módulo 0). Dato central del SaaS: lo
 * registra a mano el super_admin, por eso el modelo NO usa BelongsToGym.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->unsignedSmallInteger('months');
            $table->date('paid_at');
            // saas_paid_until del gimnasio después de este pago
            $table->date('covers_until');
            $table->string('method', 20);
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Historial de un gimnasio
            $table->index(['gym_id', 'paid_at']);
            // Ingresos por mes de toda la red
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_payments');
    }
};
