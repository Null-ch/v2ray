<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xuis', function (Blueprint $table) {
            $table->id();
            $table->string('tag');
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('path');
            $table->string('username');
            $table->string('password');
            $table->boolean('ssl')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xuis');
    }
};

