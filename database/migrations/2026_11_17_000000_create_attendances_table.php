<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scanned_at');
            $table->string('method', 20)->default('qr'); // qr | manual
            $table->unsignedTinyInteger('credits_used')->default(0);
            $table->timestamp('created_at')->nullable();

            // "Escaneos de hoy" del gimnasio
            $table->index(['gym_id', 'scanned_at']);
            // Calendario semanal / rachas de un socio
            $table->index(['gym_id', 'user_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
