<?php

declare(strict_types=1);

namespace App\Modules\ImporRba\Domain;

use App\Modules\Shared\Domain\Uang;

/**
 * Membaca "Lampiran Rincian Anggaran Belanja" format lama (TDD 8.4) dari larik sel satu sheet.
 *
 * Posisi kolom dicari dari baris judul (KODE REKENING, KOMPONEN, Jml/Satuan/Harga Satuan/Jumlah),
 * bukan huruf kolom tetap. Struktur yang dikenali (berdasarkan dokumen RBA BLUD acuan):
 *   - kode rekening Pemda bertitik utuh (5.1.02.99.99.9999) di satu kolom;
 *   - kode rinci BLUD dalam 3 segmen (01. 01. 0037) di bawah jenis Pemda → 5.1.02.01.01.0037;
 *   - baris segmen < 3 adalah judul objek/rincian objek (total, bukan komponen);
 *   - baris komponen = segmen lengkap; sub-komponen = baris uraian tanpa kode di bawahnya
 *     (penanda A./B membentuk kelompok);
 *   - label penanggung jawab di kolom tepat sebelum kolom Jml (teks; angka di kolom ini adalah catatan).
 * Kolom catatan lain (setelah kelompok nilai) diabaikan. Gangguan (#REF!, catatan manual) menjadi peringatan.
 *
 * Murni: tidak bergantung Laravel; masukan larik baris (indeks 0) berisi string|int|float|null.
 */
final class LampiranRbaParser
{
    private const POLA_PEMDA = '/^5(\.\d{1,4}){1,5}$/';

    private const POLA_SEGMEN = '/^\d{1,4}\.?$/';

    private const PENANDA_KELOMPOK = '/^([A-Z]|\d{1,2}|[a-z])[.)]?$/';

    /**
     * @param list<list<mixed>> $baris
     * @param int $kelompok indeks kelompok nilai (0 = kelompok pertama, mis. Penetapan; 1 = kelompok kedua, mis. Perubahan)
     */
    public function baca(array $baris, int $kelompok = 0): HasilBacaRba
    {
        $judul = $this->cariJudul($baris);
        if ($judul === null) {
            return HasilBacaRba::gagal('Baris judul tabel (KODE REKENING / KOMPONEN / Jml / Satuan / Harga Satuan / Jumlah) tidak ditemukan.');
        }
        ['baris' => $h, 'kode' => $kolKode, 'komponen' => $kolKomponen, 'kelompok' => $daftarKelompok] = $judul;
        if (! isset($daftarKelompok[$kelompok])) {
            return HasilBacaRba::gagal("Kelompok nilai ke-".($kelompok + 1).' tidak ada di sheet ini.');
        }
        $k = $daftarKelompok[$kelompok];
        $kolLabel = $daftarKelompok[0]['jml'] - 1;
        $kolTeksAwal = $kolKomponen + 2;           // uraian komponen
        $kolTeksAkhir = min($kolKomponen + 4, $kolLabel - 1);
        $kolPemda = $this->kolomPemda($baris, $h, $kolKode, $kolKomponen);

        $hasil = new HasilBacaRba;
        $hasil->kelompok = array_column($daftarKelompok, 'judul');
        $hasil->kelompokDipakai = $daftarKelompok[$kelompok]['judul'];
        $hasil->kepala = $this->kepala($baris, $h);

        $pemda6 = null;
        $jenis = null;
        $objek = null;
        $labelLevel = [1 => null, 2 => null];
        /** @var array<string, mixed>|null $komponen */
        $komponen = null;
        $this->level = null;
        $selesaikan = function () use (&$komponen, $hasil): void {
            if ($komponen !== null) {
                $this->selesaikanKomponen($komponen, $hasil);
            }
            $komponen = null;
        };

        for ($i = $h + 1, $n = count($baris); $i < $n; $i++) {
            $r = $baris[$i];
            if ($this->adalahTandaTangan($r)) {
                break;
            }

            $kodePemda = $kolPemda !== null ? $this->teks($r[$kolPemda] ?? null) : '';
            if ($kodePemda !== '' && preg_match(self::POLA_PEMDA, $kodePemda)) {
                $selesaikan();
                $this->tutupLevel($hasil);
                $bagian = explode('.', $kodePemda);
                $jenis = count($bagian) >= 3 ? implode('.', array_slice($bagian, 0, 3)) : null;
                $hasil->rekening['PEMDA'][$kodePemda] ??= ['uraian' => $this->teksPertama($r, $kolKomponen, $kolLabel), 'tingkat' => count($bagian)];
                $objek = null;
                if (count($bagian) === 6) {
                    $pemda6 = $kodePemda;
                    $jumlah = $this->uang($r[$k['jumlah']] ?? null, $hasil, $i, 'jumlah rekening');
                    if (! isset($hasil->totalRekening[$pemda6])) {
                        $hasil->totalRekening[$pemda6] = $jumlah ?? Uang::nol();
                        $hasil->uraianRekening[$pemda6] = $this->teksPertama($r, $kolKomponen, $kolLabel);
                    }
                } elseif (count($bagian) < 6) {
                    $pemda6 = null;
                }
                $labelLevel = [1 => null, 2 => null];
                continue;
            }

            $segmen = $this->segmen($r, $kolPemda ?? $kolKode, $kolKomponen);
            if ($segmen !== []) {
                $selesaikan();
                $label = $this->label($r[$kolLabel] ?? null);
                if (count($segmen) < 3 && $jenis !== null) {
                    // judul objek (1 segmen) / rincian objek (2 segmen) BLUD — bahan kodefikasi
                    if (count($segmen) === 1) {
                        $objek = $jenis.'.'.$this->pad($segmen[0], 2);
                        $kodeLevel = $objek;
                    } else {
                        $objek = $jenis.'.'.$this->pad($segmen[0], 2);
                        $kodeLevel = $objek.'.'.$this->pad($segmen[1], 2);
                    }
                    $hasil->rekening['BLUD'][$kodeLevel] ??= ['uraian' => $this->teksPertama($r, $kolKomponen, $kolLabel), 'tingkat' => 3 + count($segmen)];
                }
                if (count($segmen) < 3) {
                    $this->tutupLevel($hasil);
                    if (count($segmen) === 2) {
                        $this->level = ['baris' => $i + 1, 'nilai' => $this->nilai($r, $k, $hasil, $i), 'jumlah_baris' => 0, 'komponen_nol' => null, 'label' => $label];
                    }
                    $labelLevel[count($segmen)] = $label;
                    if (count($segmen) === 1) {
                        $labelLevel[2] = null;
                    }
                    continue;
                }
                if ($jenis === null) {
                    $hasil->peringatan[] = 'Baris '.($i + 1).': kode rinci tanpa rekening jenis di atasnya, dilewati.';
                    continue;
                }
                [$teksKol, $teks] = $this->teksDalam($r, $kolTeksAwal, $kolTeksAkhir);
                $kodeBlud = $jenis.'.'.$this->pad($segmen[0], 2).'.'.$this->pad($segmen[1], 2).'.'.$this->pad($segmen[2], 4);
                $hasil->rekening['BLUD'][$kodeBlud] ??= ['uraian' => $teks, 'tingkat' => 6, 'pemda' => $pemda6];
                $komponen = [
                    'baris' => $i + 1,
                    'kode_blud' => $kodeBlud,
                    'kode_pemda' => $pemda6,
                    'uraian' => $teks,
                    'nilai' => $this->nilai($r, $k, $hasil, $i),
                    'label' => $label ?? $labelLevel[2] ?? $labelLevel[1],
                    'anak' => [],
                    'kedalaman' => $teksKol ?? $kolTeksAwal,
                ];
                continue;
            }

            if ($komponen === null) {
                continue;
            }
            [$kol, $teks, $penanda] = $this->teksAnak($r, $kolTeksAwal, $kolTeksAkhir);
            if ($teks === '') {
                continue;
            }
            $simpul = [
                'baris' => $i + 1, 'uraian' => $teks, 'penanda' => $penanda,
                'nilai' => $this->nilai($r, $k, $hasil, $i),
                'label' => $this->label($r[$kolLabel] ?? null),
                'kedalaman' => $kol, 'anak' => [],
            ];
            $this->sisipkan($komponen, $simpul);
        }
        $selesaikan();
        $this->tutupLevel($hasil);

        return $hasil;
    }

    /** @var array{baris:int, nilai:array, jumlah_baris:int, komponen_nol:?array, label:?string}|null rincian objek (segmen 2) yang sedang dibaca */
    private ?array $level = null;

    /**
     * Pada sebagian file nilai ditulis di baris rincian objek, sedangkan baris komponen di bawahnya kosong
     * (mis. "Belanja Sewa Gedung dan Bangunan 1 tahun 50.000.000"). Bila tidak ada komponen bernilai di bawahnya,
     * nilai itu dibebankan ke komponen pertama agar total rekening tetap cocok dengan file.
     */
    private function tutupLevel(HasilBacaRba $hasil): void
    {
        $l = $this->level;
        $this->level = null;
        $jumlah = $l['nilai']['jumlah'] ?? null;
        if ($l === null || $l['jumlah_baris'] > 0 || $l['komponen_nol'] === null || $jumlah === null || $jumlah->adalahNol()) {
            return;
        }
        $k = $l['komponen_nol'];
        $b = new BarisRba;
        $b->baris = $k['baris'];
        $b->kodeBlud = $k['kode_blud'];
        $b->kodePemda = $k['kode_pemda'];
        $b->komponen = $k['uraian'];
        $b->volume = $l['nilai']['volume'];
        $b->satuan = $l['nilai']['satuan'];
        $b->harga = $l['nilai']['harga'];
        $b->jumlah = $jumlah;
        $b->label = $k['label'] ?? $l['label'];
        $b->gelondongan = true;
        $b->petunjuk = $this->petunjuk($k['anak']);
        $b->peringatan = ["Nilai {$jumlah->format()} tertulis di baris judul (baris {$l['baris']}), bukan di baris komponen; dibebankan ke komponen ini."];
        $hasil->baris[] = $b;
        $hasil->nol = array_values(array_filter($hasil->nol, fn ($n) => $n['baris'] !== $k['baris']));
    }

    // ---------------------------------------------------------------- struktur

    /** @return array{baris:int, kode:int, komponen:int, kelompok: list<array{judul:string, jml:int, satuan:int, harga:int, jumlah:int}>}|null */
    private function cariJudul(array $baris): ?array
    {
        foreach ($baris as $i => $r) {
            $kode = $komponen = null;
            foreach ($r as $j => $c) {
                $t = mb_strtoupper($this->teks($c));
                if ($t === 'KODE REKENING') {
                    $kode = $j;
                } elseif ($t === 'KOMPONEN') {
                    $komponen = $j;
                }
            }
            if ($kode === null || $komponen === null) {
                continue;
            }
            // Baris "Jml" bisa berada 1–2 baris di bawah judul.
            for ($d = 0; $d <= 2; $d++) {
                $rj = $baris[$i + $d] ?? [];
                $kelompok = [];
                foreach ($rj as $j => $c) {
                    if (mb_strtolower($this->teks($c)) === 'jml'
                        && str_contains(mb_strtolower($this->teks($rj[$j + 3] ?? '')), 'jumlah')) {
                        $kelompok[] = ['judul' => $this->judulKelompok($baris[$i], $j), 'jml' => $j, 'satuan' => $j + 1, 'harga' => $j + 2, 'jumlah' => $j + 3];
                    }
                }
                if ($kelompok !== []) {
                    return ['baris' => $i + $d, 'kode' => $kode, 'komponen' => $komponen, 'kelompok' => $kelompok];
                }
            }
        }

        return null;
    }

    private function judulKelompok(array $judul, int $kol): string
    {
        for ($j = $kol; $j < $kol + 4; $j++) {
            $t = $this->teks($judul[$j] ?? null);
            if ($t !== '') {
                return $t;
            }
        }

        return 'Kelompok kolom '.($kol + 1);
    }

    /** Kolom tempat kode rekening Pemda bertitik (modus kemunculan). */
    private function kolomPemda(array $baris, int $h, int $dari, int $sampai): ?int
    {
        $hitung = [];
        foreach (array_slice($baris, $h + 1) as $r) {
            for ($j = $dari; $j < $sampai; $j++) {
                if (preg_match(self::POLA_PEMDA, $this->teks($r[$j] ?? null))) {
                    $hitung[$j] = ($hitung[$j] ?? 0) + 1;
                }
            }
        }
        if ($hitung === []) {
            return null;
        }
        arsort($hitung);

        return (int) array_key_first($hitung);
    }

    /** @return list<string> segmen kode rinci BLUD di kanan kolom kode Pemda */
    private function segmen(array $r, int $kolPemda, int $kolKomponen): array
    {
        $hasil = [];
        for ($j = $kolPemda + 1; $j < $kolKomponen; $j++) {
            $c = $r[$j] ?? null;
            $t = is_int($c) || (is_float($c) && floor($c) === $c) ? (string) (int) $c : $this->teks($c);
            if ($t !== '' && preg_match(self::POLA_SEGMEN, $t)) {
                $hasil[] = rtrim($t, '.');
            }
        }

        return count($hasil) <= 3 ? $hasil : [];
    }

    /** @return array<string, array{kode: string, nama: string}> */
    private function kepala(array $baris, int $h): array
    {
        $kepala = [];
        for ($i = 0; $i < $h; $i++) {
            $isi = array_values(array_filter(array_map(fn ($c) => $this->teks($c), $baris[$i]), fn ($t) => $t !== ''));
            $titik = array_search(':', $isi, true);
            if ($titik === false || $titik === 0) {
                if (preg_match('/TAHUN ANGGARAN\s+(\d{4})/i', implode(' ', $isi), $m)) {
                    $kepala['Tahun Anggaran'] = ['kode' => $m[1], 'nama' => $m[1]];
                }

                continue;
            }
            $label = str_replace('Otganisasi', 'Organisasi', $isi[$titik - 1]);
            $kepala[$label] = ['kode' => $isi[$titik + 1] ?? '', 'nama' => $isi[$titik + 2] ?? ($isi[$titik + 1] ?? '')];
        }

        return $kepala;
    }

    private function adalahTandaTangan(array $r): bool
    {
        foreach ($r as $c) {
            $t = $this->teks($c);
            if ($t !== '' && (preg_match('/^(Pemimpin|Direktur|Kepala) BLUD$/i', $t) || preg_match('/^NIP[.:]?\s*\d/i', $t))) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- sub-komponen

    /** @return array{0: ?int, 1: string} */
    private function teksDalam(array $r, int $dari, int $sampai): array
    {
        for ($j = $dari; $j <= $sampai; $j++) {
            $t = $this->teks($r[$j] ?? null);
            if ($t !== '' && ! $this->angka($t)) {
                return [$j, $t];
            }
        }

        return [null, ''];
    }

    /** @return array{0: int, 1: string, 2: ?string} kolom, teks, penanda kelompok (A./B/1.) */
    private function teksAnak(array $r, int $dari, int $sampai): array
    {
        for ($j = $dari; $j <= $sampai; $j++) {
            $t = $this->teks($r[$j] ?? null);
            if ($t === '' || $this->angka($t)) {
                continue;
            }
            if (preg_match(self::PENANDA_KELOMPOK, $t)) {
                [, $teks] = $this->teksDalam($r, $j + 1, $sampai);

                return [$j, $teks !== '' ? $teks : $t, rtrim($t, '.)')];
            }

            return [$j, $t, null];
        }

        return [0, '', null];
    }

    /** Menempatkan simpul di bawah simpul terakhir yang lebih dangkal (struktur indentasi kolom). */
    private function sisipkan(array &$induk, array $simpul): void
    {
        $n = count($induk['anak']);
        if ($n > 0) {
            $terakhir = &$induk['anak'][$n - 1];
            if ($terakhir['kedalaman'] < $simpul['kedalaman']) {
                $this->sisipkan($terakhir, $simpul);

                return;
            }
        }
        $induk['anak'][] = $simpul;
    }

    // ---------------------------------------------------------------- pemecahan nilai

    private function selesaikanKomponen(array $k, HasilBacaRba $hasil): void
    {
        $petunjuk = $this->petunjuk($k['anak']);
        $target = $k['nilai']['jumlah'];
        $baris = $this->pecah($k, $target, [], $k['label'], $hasil);

        if ($baris === []) {
            foreach ($k['nilai']['peringatan'] as $p) {
                $hasil->peringatan[] = "Baris {$k['baris']} ({$k['uraian']}): {$p}";
            }
            $hasil->nol[] = ['baris' => $k['baris'], 'kode_blud' => $k['kode_blud'], 'uraian' => $k['uraian']];
            if ($this->level !== null && $this->level['komponen_nol'] === null) {
                $this->level['komponen_nol'] = $k;
            }

            return;
        }
        if ($this->level !== null) {
            $this->level['jumlah_baris'] += count($baris);
        }
        foreach ($baris as $b) {
            $b->kodeBlud = $k['kode_blud'];
            $b->kodePemda = $k['kode_pemda'];
            $b->komponen = $k['uraian'];
            $b->petunjuk = $petunjuk;
            $hasil->baris[] = $b;
        }
    }

    /**
     * Memakai rincian anak bila jumlahnya tepat sama dengan nilai simpul; bila tidak, simpul itu sendiri satu baris.
     *
     * @param list<string> $jalur
     * @return list<BarisRba>
     */
    private function pecah(array $simpul, ?Uang $target, array $jalur, ?string $label, HasilBacaRba $hasil): array
    {
        $label = $simpul['label'] ?? $label;
        $anakBernilai = array_values(array_filter($simpul['anak'], fn ($a) => $a['nilai']['jumlah'] !== null && ! $a['nilai']['jumlah']->adalahNol()));
        $total = array_reduce($anakBernilai, fn (Uang $t, $a) => $t->tambah($a['nilai']['jumlah']), Uang::nol());
        $jalurAnak = isset($simpul['kode_blud']) ? $jalur : [...$jalur, ($simpul['penanda'] ? $simpul['penanda'].'. ' : '').$simpul['uraian']];

        if ($anakBernilai !== [] && ($target === null || $target->adalahNol() || $total->sama($target))) {
            if ($target === null || $target->adalahNol()) {
                $hasil->peringatan[] = "Baris {$simpul['baris']}: jumlah komponen kosong; dipakai rincian di bawahnya ({$total->format()}).";
            }
            $hasilAnak = [];
            foreach ($anakBernilai as $a) {
                array_push($hasilAnak, ...$this->pecah($a, $a['nilai']['jumlah'], $jalurAnak, $label, $hasil));
            }

            return $hasilAnak;
        }

        if ($target === null || $target->adalahNol()) {
            return [];
        }
        $b = new BarisRba;
        $b->baris = $simpul['baris'];
        $b->rincian = isset($simpul['kode_blud']) ? null : implode(' › ', $jalurAnak);
        $b->volume = $simpul['nilai']['volume'];
        $b->satuan = $simpul['nilai']['satuan'];
        $b->harga = $simpul['nilai']['harga'];
        $b->jumlah = $target;
        $b->label = $label;
        $b->peringatan = $simpul['nilai']['peringatan'];
        if ($anakBernilai !== [] && ! $total->sama($target)) {
            $b->peringatan[] = "Jumlah rincian di bawahnya ({$total->format()}) tidak sama dengan jumlah komponen ({$target->format()}); dipakai jumlah komponen.";
        }
        $b->gelondongan = $b->volume === null || in_array(mb_strtolower((string) $b->satuan), ['tahun', 'paket', 'ls', 'kegiatan'], true);

        return [$b];
    }

    /** Uraian sub-komponen tanpa nilai: kandidat pemecahan item (TDD 8.4 butir 2). @return list<string> */
    private function petunjuk(array $anak): array
    {
        $hasil = [];
        foreach ($anak as $a) {
            $hasil[] = $a['uraian'];
            array_push($hasil, ...$this->petunjuk($a['anak']));
        }

        return array_values(array_unique($hasil));
    }

    // ---------------------------------------------------------------- nilai sel

    /** @return array{volume: ?string, satuan: ?string, harga: ?Uang, jumlah: ?Uang, peringatan: list<string>} */
    private function nilai(array $r, array $k, HasilBacaRba $hasil, int $i): array
    {
        $pesan = [];
        $volume = $this->volume($r[$k['jml']] ?? null);
        $satuan = $this->teks($r[$k['satuan']] ?? null) ?: null;
        $harga = $this->uang($r[$k['harga']] ?? null, $hasil, $i, 'harga', $pesan);
        $jumlah = $this->uang($r[$k['jumlah']] ?? null, $hasil, $i, 'jumlah', $pesan);
        $selJml = $r[$k['jml']] ?? null;
        $jmlNolEksplisit = $selJml === 0 || $selJml === 0.0 || $this->teks($selJml) === '-' || $this->teks($selJml) === '0';
        if ($jmlNolEksplisit && $jumlah !== null && ! $jumlah->adalahNol()) {
            // Mis. Jml "-" tetapi Jumlah 10.000.000.000.000.000 akibat rumus rusak di file sumber.
            $pesan[] = "Jml bernilai 0 tetapi Jumlah terisi ({$jumlah->format()}); dianggap 0.";
            $jumlah = Uang::nol();
        }
        if ($volume !== null && $harga !== null && $jumlah !== null && ! $jumlah->adalahNol()
            && ! $harga->kaliVolume($volume)->sama($jumlah)) {
            $pesan[] = "Volume × harga ({$harga->kaliVolume($volume)->format()}) tidak sama dengan jumlah ({$jumlah->format()}); dipakai jumlah.";
        }

        return ['volume' => $volume, 'satuan' => $satuan, 'harga' => $harga, 'jumlah' => $jumlah, 'peringatan' => $pesan];
    }

    private function volume(mixed $c): ?string
    {
        if (is_int($c) || is_float($c)) {
            return $c > 0 ? rtrim(rtrim(number_format((float) $c, 4, '.', ''), '0'), '.') : null;
        }
        $t = str_replace(',', '.', $this->teks($c));

        return preg_match('/^\d+(\.\d{1,4})?$/', $t) && (float) $t > 0 ? $t : null;
    }

    /** @param list<string> $pesan */
    private function uang(mixed $c, HasilBacaRba $hasil, int $i, string $kolom, array &$pesan = []): ?Uang
    {
        if ($c === null || $c === '') {
            return null;
        }
        if (is_int($c)) {
            return Uang::dari($c);
        }
        if (is_float($c)) {
            // Batas sistem: nilai sel Excel berupa float; dibulatkan ke sen sekali di sini.
            return Uang::dari(number_format(round($c, 2), 2, '.', ''));
        }
        $t = $this->teks($c);
        if ($t === '' || $t === '-') {
            return Uang::nol();
        }
        if (str_contains($t, '#')) {
            $pesan[] = "Sel {$kolom} berisi galat ({$t}); dianggap kosong.";

            return null;
        }
        $negatif = str_starts_with($t, '(') || str_starts_with($t, '-');
        $t = trim($t, '()- ');
        // 1,234,567.89 (gaya Inggris) atau 1.234.567,89 (gaya Indonesia)
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $t)) {
            $t = str_replace(',', '', $t);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $t)) {
            $t = str_replace(['.', ','], ['', '.'], $t);
        } elseif (preg_match('/^\d+,\d{1,2}$/', $t)) {
            $t = str_replace(',', '.', $t);
        }
        if (! preg_match('/^\d+(\.\d+)?$/', $t)) {
            $pesan[] = "Sel {$kolom} bukan angka ({$c}); dianggap kosong.";

            return null;
        }
        $u = Uang::dari(self::bulatSen($t));

        return $negatif ? $u->negatif() : $u;
    }

    /** Membulatkan teks desimal ke 2 digit (half-up) tanpa float. */
    private static function bulatSen(string $t): string
    {
        [$bulat, $pecahan] = array_pad(explode('.', $t), 2, '');
        if (strlen($pecahan) <= 2) {
            return $bulat.($pecahan !== '' ? '.'.$pecahan : '');
        }
        $sen = Uang::dari($bulat.'.'.substr($pecahan, 0, 2));

        return ((int) $pecahan[2] >= 5 ? $sen->tambah(Uang::dariSen(1)) : $sen)->keDesimal();
    }

    private function label(mixed $c): ?string
    {
        $t = $this->teks($c);

        return $t === '' || $this->angka($t) ? null : rtrim($t, ' ,');
    }

    private function teksPertama(array $r, int $dari, int $sampai): string
    {
        for ($j = $dari; $j < $sampai; $j++) {
            $t = $this->teks($r[$j] ?? null);
            if ($t !== '' && ! $this->angka($t)) {
                return $t;
            }
        }

        return '';
    }

    private function angka(string $t): bool
    {
        return (bool) preg_match('/^[(\-]?[\d.,]+%?\)?$/', $t) || $t === '-';
    }

    private function teks(mixed $c): string
    {
        if ($c === null || is_bool($c)) {
            return '';
        }
        if (is_float($c) || is_int($c)) {
            return (string) $c;
        }

        return trim(preg_replace('/\s+/u', ' ', str_replace('\\', '', (string) $c)) ?? '');
    }

    private function pad(string $s, int $n): string
    {
        return str_pad($s, $n, '0', STR_PAD_LEFT);
    }
}
