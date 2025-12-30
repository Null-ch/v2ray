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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_verified_at', 'password', 'remember_token']);
            $table->string('tg_tag')->nullable()->default(null)->after('name');
            $table->string('phone_number')->after('tg_tag');
            $table->bigInteger('tg_id')->after('phone_number');
            $table->uuid('uuid')->after('tg_id');
            $table->unsignedBigInteger('referrer_id')->nullable()->after('uuid');
            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('password')->after('email_verified_at');
            $table->rememberToken()->after('password');

            $table->dropForeign(['referrer_id']);
            $table->dropColumn(['tg_tag', 'phone_number', 'tg_id', 'uuid', 'referrer_id']);
        });
    }
};
