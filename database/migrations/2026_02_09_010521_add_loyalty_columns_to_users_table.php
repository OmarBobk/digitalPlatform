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
        Schema::table('users', function (Blueprint $table) {
            $table->string('loyalty_tier')->nullable()->after('last_login_at');
            $table->timestamp('loyalty_evaluated_at')->nullable()->after('loyalty_tier');
            $table->timestamp('loyalty_locked_until')->nullable()->after('loyalty_evaluated_at');
            $table->foreignId('loyalty_override_by')->nullable()->after('loyalty_locked_until')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['loyalty_override_by']);
            $table->dropColumn([
                'loyalty_tier',
                'loyalty_evaluated_at',
                'loyalty_locked_until',
                'loyalty_override_by',
            ]);
        });
    }
};
