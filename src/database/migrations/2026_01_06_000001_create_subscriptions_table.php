<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('xui_id');
            $table->string('uuid');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('xui_id')
                ->references('id')
                ->on('xuis')
                ->onDelete('cascade');

            $table->index(['user_id', 'tag']);
            $table->unique(['user_id', 'xui_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};


