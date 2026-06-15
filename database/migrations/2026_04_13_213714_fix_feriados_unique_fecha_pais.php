<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feriados', function (Blueprint $table) {
            // Eliminar el unique solo sobre fecha
            $table->dropUnique('fecha');
            // Agregar unique compuesto (fecha + pais) para permitir el mismo día en distintos países
            $table->unique(['fecha', 'pais'], 'feriados_fecha_pais_unique');
        });
    }

    public function down(): void
    {
        Schema::table('feriados', function (Blueprint $table) {
            $table->dropUnique('feriados_fecha_pais_unique');
            $table->unique('fecha');
        });
    }
};
