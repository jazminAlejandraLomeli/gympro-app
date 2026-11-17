<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('operativo'); // operativo | promocion
            $table->string('title', 120);
            $table->text('body');
            $table->dateTime('starts_at');
            $table->dateTime('expires_at')->nullable();
            $table->boolean('pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Avisos vigentes: starts_at <= ahora < expires_at
            $table->index(['gym_id', 'starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
