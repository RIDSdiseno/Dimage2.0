<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La tabla holdings viene del proyecto antiguo con valores en created_at que
        // colisionan con NO_ZERO_DATE de MySQL strict mode. Lo deshabilitamos
        // solo para esta sentencia y lo restauramos después.
        \Illuminate\Support\Facades\DB::statement("SET SESSION sql_mode = ''");
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE holdings ADD COLUMN pais VARCHAR(2) NOT NULL DEFAULT 'CL'"
        );
        \Illuminate\Support\Facades\DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('pais');
        });
    }
};
