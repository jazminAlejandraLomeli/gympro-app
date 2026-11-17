<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // entrada | salida | ajuste | venta
            $table->integer('quantity'); // con signo: + entra, - sale
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // personal
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete(); // socio (ventas)
            $table->timestamp('created_at')->nullable();

            // Historial de un producto
            $table->index(['gym_id', 'product_id', 'created_at']);
            // Ventas del día / filtros por tipo
            $table->index(['gym_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
