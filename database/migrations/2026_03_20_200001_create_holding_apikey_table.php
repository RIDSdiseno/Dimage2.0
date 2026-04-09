<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('holding_apikey')) {
            Schema::create('holding_apikey', function (Blueprint $table) {
                $table->id();
                $table->foreignId('holding_id')->constrained()->cascadeOnDelete();
                $table->string('apikey', 64)->unique();
                $table->string('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holding_apikey');
    }
};
