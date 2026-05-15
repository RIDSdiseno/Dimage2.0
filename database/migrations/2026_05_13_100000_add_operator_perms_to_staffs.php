<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            if (! Schema::hasColumn('staffs', 'puede_seleccionar_radiologo')) {
                $table->boolean('puede_seleccionar_radiologo')->default(false);
            }
            if (! Schema::hasColumn('staffs', 'puede_sin_diagnostico')) {
                $table->boolean('puede_sin_diagnostico')->default(false);
            }
            if (! Schema::hasColumn('staffs', 'puede_derivacion_clinica')) {
                $table->boolean('puede_derivacion_clinica')->default(false);
            }
            if (! Schema::hasColumn('staffs', 'puede_ver_menu_busqueda')) {
                $table->boolean('puede_ver_menu_busqueda')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            foreach (['puede_seleccionar_radiologo', 'puede_sin_diagnostico', 'puede_derivacion_clinica', 'puede_ver_menu_busqueda'] as $col) {
                if (Schema::hasColumn('staffs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
