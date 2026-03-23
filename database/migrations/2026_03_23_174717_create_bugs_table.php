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
        Schema::create('bugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 50);
            $table->string('scenario', 50);
            $table->string('subtype', 80);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open')->index();
            $table->string('current_url', 2048);
            $table->string('route_name', 150)->nullable();
            $table->string('description', 250)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scenario', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bugs');
    }
};
