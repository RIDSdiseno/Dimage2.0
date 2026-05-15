<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            if (! Schema::hasColumn('staffs', 'address')) {
                $table->string('address')->nullable()->after('firma');
            }
            if (! Schema::hasColumn('staffs', 'solo_adjuntar_informe')) {
                $table->boolean('solo_adjuntar_informe')->default(false)->after('address');
            }
        });

        if (! Schema::hasTable('holding_staff')) {
            Schema::create('holding_staff', function (Blueprint $table) {
                $table->foreignId('staff_id')->constrained('staffs')->cascadeOnDelete();
                $table->foreignId('holding_id')->constrained('holdings')->cascadeOnDelete();
                $table->primary(['staff_id', 'holding_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holding_staff');

        Schema::table('staffs', function (Blueprint $table) {
            $table->dropColumn(['address', 'solo_adjuntar_informe']);
        });
    }
};
