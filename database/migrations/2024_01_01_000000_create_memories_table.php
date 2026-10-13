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
        Schema::ensureVectorExtensionExists();

        Schema::create(config('memory.table', 'memories'), function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable()->index();
            $table->text('content');
            $table->vector('embedding', dimensions: (int) config('memory.dimensions'))->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('memory.table', 'memories'));
    }
};
