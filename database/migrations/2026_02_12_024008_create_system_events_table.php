<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Insert-only; no updates or deletes.
     */
    public function up(): void
    {
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->string('entity_type')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('severity', 20)->default('info')->index();
            $table->boolean('is_financial')->default(false)->index();
            $table->string('idempotency_key', 255)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['is_financial', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_events');
    }
};
