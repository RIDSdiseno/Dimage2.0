<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            if (! Schema::hasColumn('staffs', 'puede_crear_ordenes')) {
                $table->boolean('puede_crear_ordenes')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            if (Schema::hasColumn('staffs', 'puede_crear_ordenes')) {
                $table->dropColumn('puede_crear_ordenes');
            }
        });
    }
};
