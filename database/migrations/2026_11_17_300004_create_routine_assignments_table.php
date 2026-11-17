<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            // Fecha de una rutina de un solo día. Las semanales la dejan NULL.
            $table->date('scheduled_for')->nullable();
            // Semanal: a lo más UNA activa por socio. De un día: a lo más
            // una activa por socio y fecha (lo garantiza RoutineAssigner).
            $table->boolean('active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Rutina semanal activa del socio
            $table->index(['gym_id', 'user_id', 'active']);
            // Rutina de un día del socio para una fecha
            $table->index(['gym_id', 'user_id', 'scheduled_for']);
            // Socios con esta rutina
            $table->index(['gym_id', 'routine_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_assignments');
    }
};
