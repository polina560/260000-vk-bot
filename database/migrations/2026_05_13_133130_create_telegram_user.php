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
        Schema::create('telegram_user', function (Blueprint $table) {
			$table->id();
			$table->string('name')->nullable();
			$table->string('peer_id')->nullable();
			$table->string('form_id')->nullable();
			$table->integer('state')->default(0);
			$table->integer('prev_state')->default(0);
            $table->json('data')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_user');
    }
};
