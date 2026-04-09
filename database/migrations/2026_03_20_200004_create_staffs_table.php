<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staffs')) {
            Schema::create('staffs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('rut')->nullable()->index();
                $table->string('name');
                $table->string('specialty')->nullable();
                $table->string('firma')->nullable();
                $table->boolean('externo')->default(false);
                $table->string('id_externo')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('clinic_staff')) {
            Schema::create('clinic_staff', function (Blueprint $table) {
                $table->foreignId('staff_id')->constrained('staffs')->cascadeOnDelete();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->primary(['staff_id', 'clinic_id']);
            });
        }

        if (!Schema::hasTable('kind_staff')) {
            Schema::create('kind_staff', function (Blueprint $table) {
                $table->foreignId('staff_id')->constrained('staffs')->cascadeOnDelete();
                $table->foreignId('kind_id')->constrained()->cascadeOnDelete();
                $table->primary(['staff_id', 'kind_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kind_staff');
        Schema::dropIfExists('clinic_staff');
        Schema::dropIfExists('staffs');
    }
};
