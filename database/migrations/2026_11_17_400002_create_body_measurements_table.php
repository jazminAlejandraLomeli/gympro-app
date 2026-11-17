<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('chest_cm', 5, 1)->nullable();
            $table->decimal('arm_cm', 5, 1)->nullable();
            $table->decimal('leg_cm', 5, 1)->nullable();
            $table->decimal('body_fat_pct', 4, 1)->nullable();
            $table->string('notes', 500)->nullable();
            // Quién la capturó: el propio socio o un entrenador/admin
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Historial / última valoración de un socio (una por día)
            $table->unique(['gym_id', 'user_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
