<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patients')) {
            Schema::create('patients', function (Blueprint $table) {
                $table->id();
                $table->string('rut')->unique()->index();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('housephone')->nullable();
                $table->string('celphone')->nullable();
                $table->string('workphone')->nullable();
                $table->string('address')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('long', 10, 7)->nullable();
                $table->date('dateofbirth')->nullable();
                $table->string('tutorname')->nullable();
                $table->string('tutorrelation')->nullable();
                $table->string('id_externo')->nullable();
                $table->string('derivado_de')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('clinic_patient')) {
            Schema::create('clinic_patient', function (Blueprint $table) {
                $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->primary(['patient_id', 'clinic_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_patient');
        Schema::dropIfExists('patients');
    }
};
