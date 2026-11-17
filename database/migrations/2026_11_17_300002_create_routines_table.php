<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // single = sesión de un solo día | weekly = semana completa (L–D)
            $table->string('type', 10)->default('weekly');
            // Plantilla reutilizable del gimnasio (member_id NULL) o
            // rutina personal de un socio (member_id = socio). Nunca ambas.
            $table->boolean('is_template')->default(false);
            $table->foreignId('member_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('goal', 120)->nullable();
            $table->text('notes')->nullable();
            // Coach / dueño, o el mismo socio si él la armó
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Pestaña "Plantillas"
            $table->index(['gym_id', 'is_template', 'name']);
            // Pestaña "Historial" (rutinas personales más recientes)
            $table->index(['gym_id', 'is_template', 'created_at']);
            // Rutinas de un socio
            $table->index(['gym_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
