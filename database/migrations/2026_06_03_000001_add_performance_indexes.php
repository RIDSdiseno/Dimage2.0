<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!$this->hasIndex('orders', 'idx_orders_estado')) $t->index('estadoradiologo', 'idx_orders_estado');
            if (!$this->hasIndex('orders', 'idx_orders_operator')) $t->index('operator_id', 'idx_orders_operator');
            if (!$this->hasIndex('orders', 'idx_orders_clinic')) $t->index('clinic_id', 'idx_orders_clinic');
            if (!$this->hasIndex('orders', 'idx_orders_created')) $t->index('created_at', 'idx_orders_created');
        });
        // examination_order uses raw SQL to avoid strict mode issues
        if (!$this->hasIndex('examination_order', 'idx_eo_order')) {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE `examination_order` ADD INDEX `idx_eo_order` (`order_id`)');
        }
        Schema::table('files', function (Blueprint $t) {
            if (!$this->hasIndex('files', 'idx_files_exam')) $t->index('examination_id', 'idx_files_exam');
        });
        Schema::table('answers', function (Blueprint $t) {
            if (!$this->hasIndex('answers', 'idx_answers_exam')) $t->index('examination_id', 'idx_answers_exam');
        });
        Schema::table('order_staff_exam', function (Blueprint $t) {
            if (!$this->hasIndex('order_staff_exam', 'idx_ose_order')) $t->index('order_id', 'idx_ose_order');
            if (!$this->hasIndex('order_staff_exam', 'idx_ose_staff')) $t->index('staff_id', 'idx_ose_staff');
        });
    }

    public function down(): void {}

    private function hasIndex(string $table, string $index): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'"))->isNotEmpty();
    }
};
