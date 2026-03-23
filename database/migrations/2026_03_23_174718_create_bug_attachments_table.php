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
        Schema::create('bug_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_id')->constrained('bugs')->cascadeOnDelete();
            $table->string('path', 2048);
            $table->string('type', 20);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_attachments');
    }
};
