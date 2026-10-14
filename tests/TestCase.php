<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\PeranSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Memuat data uji yang sama dengan tests/sql/00_fixture.sql (unit FAR & LAB, rekening, standar harga,
     * RBA 2026 definitif dengan pagu FAR BBJ 60 jt, LAB BBJ 30 jt, LAB Modal 63,7 jt) plus pengguna dan peran.
     *
     * @return array<string, User>
     */
    protected function muatFixture(): array
    {
        $this->seed([PeranSeeder::class, WorkflowSeeder::class]);

        $pengguna = [
            'far' => ['Pengusul Farmasi', 'pengusul_unit', 'FAR'],
            'lab' => ['Pengusul Lab', 'pengusul_unit', 'LAB'],
            'kalab' => ['Kepala Lab', 'kepala_unit', 'LAB'],
            'kafar' => ['Kepala Farmasi', 'kepala_unit', 'FAR'],
            'perencana' => ['Perencana', 'perencana', null],
        ];
        $hasil = [];
        foreach ($pengguna as $k => [$nama, $peran]) {
            $u = User::query()->forceCreate(['name' => $nama, 'email' => $k.'@uji.test', 'password' => 'rahasia-uji-123']);
            $u->assignRole($peran);
            $hasil[$k] = $u;
        }

        $sql = file_get_contents(base_path('tests/sql/00_fixture.sql'));
        DB::unprepared($sql);

        foreach ($pengguna as $k => [, , $unit]) {
            if ($unit !== null) {
                DB::table('user_unit')->insert(['user_id' => $hasil[$k]->id, 'unit_id' => DB::table('unit_kerja')->where('kode', $unit)->value('id')]);
            }
        }

        return $hasil;
    }

    protected function paguId(string $unit, string $jenis): int
    {
        return (int) DB::selectOne('SELECT pagu(?, ?) AS id', [$unit, $jenis])->id;
    }
}
