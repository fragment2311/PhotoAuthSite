<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('change_histories', function (Blueprint $table): void {
            $table->bigIncrements('Id');
            $table->string('Objective', 100);
            $table->string('ObjectiveId', 64);
            $table->string('Action', 32);
            $table->unsignedBigInteger('ActorWebuserId')->nullable();
            $table->string('ActorLogin', 200)->nullable();
            $table->timestamp('ChangedAt');
            $table->json('DetailsJson')->nullable();

            $table->index(['Objective', 'ObjectiveId', 'ChangedAt'], 'idx_change_histories_objective');
            $table->index('ActorWebuserId', 'idx_change_histories_actor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_histories');
    }
};

