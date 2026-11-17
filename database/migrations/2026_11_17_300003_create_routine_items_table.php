<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // ISO 8601: 1 = lunes … 7 = domingo
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedTinyInteger('sets')->default(3);
            $table->string('reps', 20); // texto: "10-12", "al fallo", "30 s"
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'routine_id', 'day_of_week', 'position']);
            $table->index(['gym_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_items');
    }
};
