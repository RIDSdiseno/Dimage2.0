<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('corrections')) {
            Schema::create('corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('staffs')->nullOnDelete();
                $table->text('description')->nullable();
                $table->string('status')->default('pendiente');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('staffs')->nullOnDelete();
                $table->string('status')->default('pendiente');
                $table->text('observations')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('archives')) {
            Schema::create('archives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->string('filename');
                $table->string('path');
                $table->string('disk')->default('s3');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('feriados')) {
            Schema::create('feriados', function (Blueprint $table) {
                $table->id();
                $table->string('descripcion');
                $table->date('fecha')->unique();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
        Schema::dropIfExists('archives');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('corrections');
    }
};
