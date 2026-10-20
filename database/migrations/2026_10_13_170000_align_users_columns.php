<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name') && ! Schema::hasColumn('users', 'nama')) {
                $table->renameColumn('name', 'nama');
            }
            foreach (['alamat', 'no_ktp', 'no_hp', 'no_rm'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $table->string($column, $column === 'no_rm' ? 25 : 255)->nullable();
                }
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'dokter', 'pasien'])->default('pasien');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->dropColumn(['alamat', 'no_ktp', 'no_hp', 'no_rm', 'role']);
        });
    }
};
