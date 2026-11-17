<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants (gimnasios) y su identidad visual.
 *
 * Corre antes que `users` porque users.gym_id apunta aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Subdominio: {slug}.dominio-base  (ej. irondemo.gympro.mx)
            $table->string('slug', 63)->unique();
            $table->string('status', 20)->default('active');

            // Licencia SaaS (cobro pasivo, se registra a mano)
            $table->decimal('saas_monthly_fee', 10, 2)->default(1800);
            $table->decimal('saas_discount', 10, 2)->default(0);
            $table->date('saas_paid_until')->nullable();
            $table->foreignId('referred_by_gym_id')->nullable()
                ->constrained('gyms')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();

            $table->timestamps();

            // Panel super_admin: activos / próximos a vencer
            $table->index(['status', 'saas_paid_until']);
        });

        Schema::create('gym_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand_name');
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->char('primary_color', 7)->default('#16a34a');
            $table->char('secondary_color', 7)->default('#0f172a');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_settings');
        Schema::dropIfExists('gyms');
    }
};
