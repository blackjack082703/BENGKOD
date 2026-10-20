<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_sequences', function (Blueprint $table) {
            $table->string('month', 6)->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('no_rm');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['no_rm']);
        });
        Schema::dropIfExists('medical_record_sequences');
    }
};
