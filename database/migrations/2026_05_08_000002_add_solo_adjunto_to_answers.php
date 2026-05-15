<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            if (! Schema::hasColumn('answers', 'solo_adjunto')) {
                $table->boolean('solo_adjunto')->default(false)->after('campo_1');
            }
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('solo_adjunto');
        });
    }
};
