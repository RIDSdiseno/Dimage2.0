<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('holdings')) {
            Schema::create('holdings', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('rut')->nullable();
                $table->string('phone')->nullable();
                $table->string('address')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holdings');
    }
};
