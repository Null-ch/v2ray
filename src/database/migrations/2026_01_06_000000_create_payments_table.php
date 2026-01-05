<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('yookassa_payment_id')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->enum('status', ['pending', 'succeeded', 'canceled'])->default('pending');
            $table->string('yookassa_status')->nullable();
            $table->text('confirmation_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('user_id');
            $table->index('status');
            $table->index('yookassa_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

