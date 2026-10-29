<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('daftar_poli')->select('id_jadwal', 'no_antrian')
            ->groupBy('id_jadwal', 'no_antrian')->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('periksa')->select('id_daftar_poli')
                ->groupBy('id_daftar_poli')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Terdapat antrean atau pemeriksaan ganda. Periksa data tersebut sebelum menambahkan batasan keunikan.');
        }

        Schema::table('daftar_poli', function (Blueprint $table) {
            $table->unique(['id_jadwal', 'no_antrian'], 'daftar_poli_jadwal_antrian_unique');
        });
        Schema::table('periksa', function (Blueprint $table) {
            $table->unique('id_daftar_poli', 'periksa_pendaftaran_unique');
        });

        $this->changeDeleteRules('restrict');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('periksa', 'periksa_id_daftar_poli_foreign')) {
            Schema::table('periksa', function (Blueprint $table) {
                $table->index('id_daftar_poli', 'periksa_id_daftar_poli_foreign');
            });
        }

        if (! Schema::hasIndex('daftar_poli', 'daftar_poli_id_jadwal_foreign')) {
            Schema::table('daftar_poli', function (Blueprint $table) {
                $table->index('id_jadwal', 'daftar_poli_id_jadwal_foreign');
            });
        }

        $this->changeDeleteRules('cascade');
        Schema::table('periksa', function (Blueprint $table) {
            $table->dropUnique('periksa_pendaftaran_unique');
        });
        Schema::table('daftar_poli', function (Blueprint $table) {
            $table->dropUnique('daftar_poli_jadwal_antrian_unique');
        });
    }

    private function changeDeleteRules(string $action): void
    {
        $changeRules = function () use ($action): void {
            foreach ([
                ['users', 'id_poli', 'poli'],
                ['jadwal_periksa', 'id_dokter', 'users'],
                ['daftar_poli', 'id_pasien', 'users'],
                ['daftar_poli', 'id_jadwal', 'jadwal_periksa'],
            ] as [$tableName, $column, $parent]) {
                Schema::table($tableName, function (Blueprint $table) use ($column, $parent, $action) {
                    $table->dropForeign([$column]);
                    $table->foreign($column)->references('id')->on($parent)->onDelete($action);
                });
            }
        };

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints($changeRules);

            return;
        }

        $changeRules();
    }
};
