<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_payment_charge_id')->nullable()->after('telegram_message_id');
            $table->string('telegram_payment_charge_id')->nullable()->after('provider_payment_charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['provider_payment_charge_id', 'telegram_payment_charge_id']);
        });
    }
};


