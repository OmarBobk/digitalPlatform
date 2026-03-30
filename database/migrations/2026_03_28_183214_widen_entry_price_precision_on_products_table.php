<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom-amount products can use a very small per-unit entry price; DECIMAL(10,2) rounded those to 0.00 in MySQL.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products MODIFY entry_price DECIMAL(18,8) NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE products ALTER COLUMN entry_price TYPE DECIMAL(18,8)');

            return;
        }

        // SQLite (tests): NUMERIC already stores arbitrary precision; no ALTER needed.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products MODIFY entry_price DECIMAL(10,2) NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE products ALTER COLUMN entry_price TYPE DECIMAL(10,2)');

            return;
        }
    }
};
