<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('muscle_group', 30);
            $table->text('instructions')->nullable();
            // Ruta relativa en el disco public (clip ya comprimido)
            $table->string('clip_path')->nullable();
            $table->string('clip_status', 20)->default('none'); // none | processing | ready | failed
            $table->unsignedInteger('clip_size_bytes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Catálogo filtrado por grupo muscular y ordenado por nombre
            $table->index(['gym_id', 'muscle_group', 'name']);
            $table->index(['gym_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
