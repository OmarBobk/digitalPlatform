<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE order_items MODIFY entry_price DECIMAL(18,8) NULL');

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items ALTER COLUMN entry_price TYPE DECIMAL(18,8)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE order_items MODIFY entry_price DECIMAL(12,2) NULL');

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items ALTER COLUMN entry_price TYPE DECIMAL(12,2)');
        }
    }
};
