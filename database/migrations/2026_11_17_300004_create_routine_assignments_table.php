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
            // Una sola activa por socio (se garantiza en RoutineAssigner)
            $table->boolean('active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Rutina activa del socio
            $table->index(['gym_id', 'user_id', 'active']);
            // Socios con esta rutina
            $table->index(['gym_id', 'routine_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_assignments');
    }
};
