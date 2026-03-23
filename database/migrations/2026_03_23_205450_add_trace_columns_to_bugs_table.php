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
        Schema::table('bugs', function (Blueprint $table) {
            $table->uuid('trace_id')->nullable()->after('status')->index();
            $table->foreignId('potential_duplicate_of')
                ->nullable()
                ->after('metadata')
                ->constrained('bugs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bugs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('potential_duplicate_of');
            $table->dropColumn('trace_id');
        });
    }
};
