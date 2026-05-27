<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            if (!Schema::hasColumn('examinations', 'url_texto')) {
                $table->text('url_texto')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            if (Schema::hasColumn('examinations', 'url_texto')) {
                $table->dropColumn('url_texto');
            }
        });
    }
};
