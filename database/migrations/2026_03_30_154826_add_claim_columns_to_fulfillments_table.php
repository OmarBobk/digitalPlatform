<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fulfillments', function (Blueprint $table) {
            if (! Schema::hasColumn('fulfillments', 'claimed_by')) {
                $table->unsignedBigInteger('claimed_by')->nullable()->after('order_item_id');
            }

            if (! Schema::hasColumn('fulfillments', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('completed_at');
            }
        });

        if (! $this->hasIndex('fulfillments', 'fulfillments_status_claimed_by_created_at_idx')) {
            Schema::table('fulfillments', function (Blueprint $table): void {
                $table->index(['status', 'claimed_by', 'created_at'], 'fulfillments_status_claimed_by_created_at_idx');
            });
        }

        if (! $this->hasForeignKey('fulfillments', 'fulfillments_claimed_by_foreign')) {
            try {
                Schema::table('fulfillments', function (Blueprint $table): void {
                    $table->foreign('claimed_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            } catch (QueryException) {
                // Leave claim column/index available even if legacy schema prevents FK creation.
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasForeignKey('fulfillments', 'fulfillments_claimed_by_foreign')) {
            Schema::table('fulfillments', function (Blueprint $table): void {
                $table->dropForeign('fulfillments_claimed_by_foreign');
            });
        }

        Schema::table('fulfillments', function (Blueprint $table) {
            if ($this->hasIndex('fulfillments', 'fulfillments_status_claimed_by_created_at_idx')) {
                $table->dropIndex('fulfillments_status_claimed_by_created_at_idx');
            }

            if (Schema::hasColumn('fulfillments', 'claimed_by')) {
                $table->dropColumn('claimed_by');
            }

            if (Schema::hasColumn('fulfillments', 'claimed_at')) {
                $table->dropColumn('claimed_at');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $indexName]
        );

        return $result !== null;
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_type = ? AND constraint_name = ? LIMIT 1',
            [$table, 'FOREIGN KEY', $constraintName]
        );

        return $result !== null;
    }
};
