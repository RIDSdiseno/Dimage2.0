<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            if (! Schema::hasColumn('staffs', 'firma')) {
                $table->string('firma')->nullable();
            }
            if (! Schema::hasColumn('staffs', 'address')) {
                $table->string('address')->nullable();
            }
            if (! Schema::hasColumn('staffs', 'solo_adjuntar_informe')) {
                $table->boolean('solo_adjuntar_informe')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            foreach (['firma', 'address', 'solo_adjuntar_informe'] as $col) {
                if (Schema::hasColumn('staffs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
