<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Nilai uang dalam sen (integer) agar bebas galat pembulatan float.
 * Sesuai kolom numeric(18,2) di database. Tidak bergantung pada bcmath/gmp.
 */
final class Uang implements JsonSerializable, Stringable
{
    private function __construct(public readonly int $sen) {}

    public static function nol(): self
    {
        return new self(0);
    }

    public static function dariSen(int $sen): self
    {
        return new self($sen);
    }

    /** Menerima "12345", "12345.6", "12345.67", "-5.00" (format numeric PostgreSQL). */
    public static function dari(string|int $nilai): self
    {
        if (is_int($nilai)) {
            return new self(self::kaliAman($nilai, 100));
        }

        $nilai = trim($nilai);
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $nilai, $m)) {
            throw new InvalidArgumentException("Format uang tidak valid: {$nilai}");
        }
        $sen = self::kaliAman((int) $m[2], 100) + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self(($m[1] ?? '') === '-' ? -$sen : $sen);
    }

    /**
     * Mengalikan harga dengan volume (maks. 4 desimal), dibulatkan half-up ke sen,
     * identik dengan round(volume * harga, 2) di PostgreSQL untuk nilai positif.
     */
    public function kaliVolume(string $volume): self
    {
        $volume = trim($volume);
        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', $volume, $m)) {
            throw new InvalidArgumentException("Format volume tidak valid: {$volume}");
        }
        if ($this->sen < 0) {
            throw new InvalidArgumentException('Harga negatif tidak dapat dikalikan volume');
        }
        $volumeSkala = ltrim($m[1].str_pad($m[2] ?? '', 4, '0'), '0') ?: '0';   // volume x 10^4
        $hasil = self::kaliString((string) $this->sen, $volumeSkala);           // sen x 10^4

        // bagi 10^4 dengan pembulatan half-up
        $panjang = strlen($hasil);
        if ($panjang <= 4) {
            $bulat = '0';
            $sisa = str_pad($hasil, 4, '0', STR_PAD_LEFT);
        } else {
            $bulat = substr($hasil, 0, $panjang - 4);
            $sisa = substr($hasil, -4);
        }
        if ((int) $sisa >= 5000) {
            $bulat = self::tambahSatuString($bulat);
        }
        if (strlen($bulat) > 18 || (strlen($bulat) === 18 && strcmp($bulat, '922337203685477580') > 0)) {
            throw new InvalidArgumentException('Nilai melebihi batas');
        }

        return new self((int) $bulat);
    }

    public function tambah(self $lain): self
    {
        return new self($this->sen + $lain->sen);
    }

    public function kurang(self $lain): self
    {
        return new self($this->sen - $lain->sen);
    }

    public function negatif(): self
    {
        return new self(-$this->sen);
    }

    public function abs(): self
    {
        return new self(abs($this->sen));
    }

    public function sama(self $lain): bool
    {
        return $this->sen === $lain->sen;
    }

    public function lebihBesar(self $lain): bool
    {
        return $this->sen > $lain->sen;
    }

    public function lebihKecil(self $lain): bool
    {
        return $this->sen < $lain->sen;
    }

    public function adalahNol(): bool
    {
        return $this->sen === 0;
    }

    public function positif(): bool
    {
        return $this->sen > 0;
    }

    /** @param iterable<self> $daftar */
    public static function jumlah(iterable $daftar): self
    {
        $total = 0;
        foreach ($daftar as $u) {
            $total += $u->sen;
        }

        return new self($total);
    }

    /** Untuk parameter SQL: "12345.67". */
    public function keDesimal(): string
    {
        $abs = abs($this->sen);

        return ($this->sen < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** "Rp 12.345.678" (sen ditampilkan bila tidak nol). */
    public function format(): string
    {
        $abs = abs($this->sen);
        $rupiah = number_format(intdiv($abs, 100), 0, ',', '.');
        $sen = $abs % 100;

        return ($this->sen < 0 ? '-' : '').'Rp '.$rupiah.($sen ? ','.str_pad((string) $sen, 2, '0', STR_PAD_LEFT) : '');
    }

    public function jsonSerialize(): string
    {
        return $this->keDesimal();
    }

    public function __toString(): string
    {
        return $this->keDesimal();
    }

    private static function kaliAman(int $a, int $b): int
    {
        $hasil = $a * $b;
        if (! is_int($hasil)) {
            throw new InvalidArgumentException('Nilai melebihi batas');
        }

        return $hasil;
    }

    /** Perkalian dua bilangan bulat non-negatif dalam bentuk string. */
    private static function kaliString(string $a, string $b): string
    {
        if ($a === '0' || $b === '0') {
            return '0';
        }
        $la = strlen($a);
        $lb = strlen($b);
        $hasil = array_fill(0, $la + $lb, 0);
        for ($i = $la - 1; $i >= 0; $i--) {
            for ($j = $lb - 1; $j >= 0; $j--) {
                $p = ((int) $a[$i]) * ((int) $b[$j]) + $hasil[$i + $j + 1];
                $hasil[$i + $j + 1] = $p % 10;
                $hasil[$i + $j] += intdiv($p, 10);
            }
        }

        return ltrim(implode('', $hasil), '0') ?: '0';
    }

    private static function tambahSatuString(string $angka): string
    {
        $digit = str_split($angka);
        for ($i = count($digit) - 1; $i >= 0; $i--) {
            if ($digit[$i] !== '9') {
                $digit[$i] = (string) ((int) $digit[$i] + 1);

                return implode('', $digit);
            }
            $digit[$i] = '0';
        }

        return '1'.implode('', $digit);
    }
}
