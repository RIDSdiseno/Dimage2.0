<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('odontologo_id')->nullable()->constrained('staffs')->nullOnDelete();
                $table->foreignId('radiologo_id')->nullable()->constrained('staffs')->nullOnDelete();
                $table->string('status')->default('pendiente');
                $table->string('priority')->default('normal');
                $table->text('observations')->nullable();
                $table->timestamp('fecha_entrega')->nullable();
                $table->timestamp('fecha_enviada_radiologo')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
