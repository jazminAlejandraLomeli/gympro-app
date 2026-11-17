<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            // Socio que la pide. Máximo UNA pendiente por socio (se valida al pedir).
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // single | weekly
            $table->string('goal', 500); // qué quiere lograr
            $table->unsignedTinyInteger('days_per_week')->nullable();
            $table->string('status', 12)->default('pending'); // pending | done | cancelled
            $table->foreignId('routine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            // Bandeja de solicitudes pendientes del panel
            $table->index(['gym_id', 'status', 'created_at']);
            // Solicitudes de un socio
            $table->index(['gym_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_requests');
    }
};
