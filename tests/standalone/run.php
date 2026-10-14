<?php

/*
 * Menjalankan tests/Unit/*Test.php tanpa Composer/Pest, dengan shim minimal test()/expect().
 * Dipakai di lingkungan yang belum bisa `composer install` dan di CI sebagai pemeriksaan cepat.
 * Hanya mendukung matcher yang dipakai di tests/Unit. Di proyek terpasang, jalankan `php artisan test`.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $kelas): void {
    if (str_starts_with($kelas, 'App\\')) {
        $berkas = __DIR__.'/../../app/'.str_replace('\\', '/', substr($kelas, 4)).'.php';
        if (is_file($berkas)) {
            require $berkas;
        }
    }
});

final class Harapan
{
    public function __construct(private mixed $nilai, private bool $negasi = false) {}

    public function __get(string $nama): self
    {
        if ($nama === 'not') {
            return new self($this->nilai, ! $this->negasi);
        }
        throw new LogicException("Properti {$nama} tidak dikenal");
    }

    private function cek(bool $hasil, string $pesan): self
    {
        if ($hasil === $this->negasi) {
            throw new RuntimeException(($this->negasi ? 'TIDAK ' : '').$pesan);
        }

        return $this;
    }

    public function toBe(mixed $harap): self { return $this->cek($this->nilai === $harap, 'diharapkan '.var_export($harap, true).', didapat '.var_export($this->nilai, true)); }
    public function toEqual(mixed $harap): self { return $this->cek($this->nilai == $harap, 'diharapkan sama dengan '.var_export($harap, true)); }
    public function toBeTrue(): self { return $this->toBe(true); }
    public function toBeFalse(): self { return $this->toBe(false); }
    public function toBeNull(): self { return $this->toBe(null); }
    public function toBeGreaterThan(int|float $n): self { return $this->cek($this->nilai > $n, "diharapkan > {$n}, didapat ".var_export($this->nilai, true)); }
    public function toBeLessThan(int|float $n): self { return $this->cek($this->nilai < $n, "diharapkan < {$n}, didapat ".var_export($this->nilai, true)); }
    public function toHaveCount(int $n): self { return $this->cek(count($this->nilai) === $n, "diharapkan {$n} elemen, didapat ".count($this->nilai)); }
    public function toContain(mixed $v): self
    {
        $ada = is_string($this->nilai) ? str_contains($this->nilai, (string) $v) : in_array($v, $this->nilai, true);

        return $this->cek($ada, 'diharapkan memuat '.var_export($v, true));
    }
    public function toThrow(string $kelas): self
    {
        try {
            ($this->nilai)();
        } catch (Throwable $e) {
            return $this->cek($e instanceof $kelas, "diharapkan {$kelas}, didapat ".$e::class);
        }

        return $this->cek(false, "diharapkan melempar {$kelas}");
    }
}

$GLOBALS['__uji'] = [];
function test(string $nama, Closure $fn): void { $GLOBALS['__uji'][] = [$nama, $fn]; }
function it(string $nama, Closure $fn): void { test('it '.$nama, $fn); }
function expect(mixed $nilai): Harapan { return new Harapan($nilai); }

$lulus = 0;
$gagal = 0;
$dilewati = 0;
foreach (glob(__DIR__.'/../Unit/*Test.php') as $berkas) {
    // Uji yang membaca file Excel butuh PhpSpreadsheet (vendor); dilewati di runner mandiri.
    if (str_contains((string) file_get_contents($berkas), '@butuh-vendor')) {
        $dilewati++;
        echo 'LEWAT '.basename($berkas, '.php')." (butuh composer install; jalankan php artisan test)\n";
        continue;
    }
    $GLOBALS['__uji'] = [];
    require $berkas;
    foreach ($GLOBALS['__uji'] as [$nama, $fn]) {
        try {
            $fn();
            $lulus++;
            echo "PASS ".basename($berkas, '.php')." › {$nama}\n";
        } catch (Throwable $e) {
            $gagal++;
            echo "FAIL ".basename($berkas, '.php')." › {$nama}\n     ".$e->getMessage()."\n";
        }
    }
}
echo "----\nLulus: {$lulus}  Gagal: {$gagal}  Berkas dilewati: {$dilewati}\n";
exit($gagal === 0 ? 0 : 1);
