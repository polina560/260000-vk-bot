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
        Schema::create('user_logs', function (Blueprint $table) {
			$table->id();
			$table->string('name')->nullable();
			$table->string('type')->nullable();
			$table->text('description')->nullable();
			$table->timestamp('date')->nullable();
			$table->foreignIdFor(\App\Models\TelegramUser::class, 'telegram_user_id')
				->constrained()
				->cascadeOnDelete()
				->cascadeOnUpdate();
			$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
