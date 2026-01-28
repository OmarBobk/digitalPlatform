<?php

declare(strict_types=1);

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
        /** Proofs store top-up evidence metadata for review. */
        Schema::create('topup_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topup_request_id')->constrained('topup_requests')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topup_proofs');
    }
};
