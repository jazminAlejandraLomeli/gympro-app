<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Días mínimos de asistencia por semana (lunes a domingo), 1–7
            $table->unsignedTinyInteger('weekly_attendance_goal');
            $table->timestamps();

            $table->unique(['gym_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_goals');
    }
};
