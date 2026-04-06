<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('settlement_fulfillments', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropForeign(['fulfillment_id']);
                $table->dropForeign(['settlement_id']);
            } else {
                $table->dropForeign('settlement_fulfillments_fulfillment_id_foreign');
                $table->dropForeign('settlement_fulfillments_settlement_id_foreign');
            }

            $table->foreign('fulfillment_id', 'settlement_fulfillments_fulfillment_id_foreign')
                ->references('id')
                ->on('fulfillments')
                ->cascadeOnDelete();

            $table->foreign('settlement_id', 'settlement_fulfillments_settlement_id_foreign')
                ->references('id')
                ->on('settlements')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('settlement_fulfillments', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropForeign(['fulfillment_id']);
                $table->dropForeign(['settlement_id']);
            } else {
                $table->dropForeign('settlement_fulfillments_fulfillment_id_foreign');
                $table->dropForeign('settlement_fulfillments_settlement_id_foreign');
            }

            $table->foreign('fulfillment_id', 'settlement_fulfillments_fulfillment_id_foreign')
                ->references('id')
                ->on('fulfillments')
                ->cascadeOnDelete();

            $table->foreign('settlement_id', 'settlement_fulfillments_settlement_id_foreign')
                ->references('id')
                ->on('settlements')
                ->cascadeOnDelete();
        });
    }
};
