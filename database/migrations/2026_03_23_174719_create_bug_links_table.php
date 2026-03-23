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
        Schema::create('bug_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_id')->constrained('bugs')->cascadeOnDelete();
            $table->string('type', 30);
            $table->unsignedBigInteger('reference_id');
            $table->timestamps();

            $table->index(['type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_links');
    }
};
