<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40)->nullable();
            $table->string('name', 120);
            $table->string('category', 20); // suplemento | bebida | accesorio | equipo
            $table->boolean('is_for_sale')->default(true);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('cost', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            // SKU único dentro del gimnasio (NULL se permite repetido)
            $table->unique(['gym_id', 'sku']);
            $table->index(['gym_id', 'category']);
            $table->index(['gym_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
