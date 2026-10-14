<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Services;

use App\Models\User;
use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Anggaran\Models\RbaRincian;
use App\Modules\Kodefikasi\Services\PemetaanService;
use App\Modules\Master\Services\AksesUnit;
use App\Modules\Pergeseran\Services\PermintaanService;
use App\Modules\Revisi\Domain\BarisRevisi;
use App\Modules\Revisi\Domain\HasilRevisi;
use App\Modules\Revisi\Domain\KalkulatorRevisi;
use App\Modules\Revisi\Domain\RincianBerlaku;
use App\Modules\Revisi\Models\RevisiDetail;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\Shared\Errors\KonflikVersi;
use App\Modules\Shared\Konfigurasi;
use App\Modules\Shared\Penomoran\PenomoranService;
use App\Modules\Shared\Support\Kunci;
use App\Modules\StandarHarga\Models\StandarHargaItem;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * Revisi rincian dalam unit, jenis belanja, dan sumber dana yang sama (PRD 7.7, TDD 4.4).
 *
 * Alur: bukaDraf -> simpanBaris (autosave) -> ringkasan (indikator) -> simpan
 *   - seimbang + Belanja Barang dan Jasa + harga <= standar  => BERLAKU
 *   - seimbang + jenis lain / harga > standar                 => MENUNGGU_PERENCANA (workflow)
 *   - pagu berubah                                            => 409 PERLU_PERSETUJUAN -> kirimPergeseran
 */
final class RevisiService
{
    public function __construct(
        private readonly KalkulatorRevisi $kalkulator,
        private readonly PemetaanService $pemetaan,
        private readonly WorkflowService $workflow,
        private readonly PermintaanService $permintaan,
        private readonly PenomoranService $penomoran,
        private readonly AuditService $audit,
        private readonly AksesUnit $akses,
        private readonly Konfigurasi $konfigurasi,
    ) {}

    public function bukaDraf(PaguJenis $pagu, User $user): RevisiPaket
    {
        $this->pastikanAkses($pagu, $user);

        return DB::transaction(function () use ($pagu, $user) {
            $draf = RevisiPaket::query()->where('pagu_jenis_id', $pagu->id)->where('dibuat_oleh', $user->id)
                ->where('status', 'DRAF')->first();

            return $draf ?? RevisiPaket::query()->forceCreate([
                'pagu_jenis_id' => $pagu->id, 'status' => 'DRAF', 'dibuat_oleh' => $user->id,
                'total_sebelum' => $pagu->pagu_berlaku, 'total_sesudah' => $pagu->pagu_berlaku,
            ]);
        });
    }

    /**
     * Menyimpan seluruh baris draf (autosave). Setiap baris:
     *   aksi, rincian_id?, row_version?, item_id, rekening_blud_id, volume, harga, uraian?, sub_kegiatan_id?
     *
     * @param list<array<string, mixed>> $baris
     */
    public function simpanBaris(RevisiPaket $paket, User $user, array $baris): HasilRevisi
    {
        $this->pastikanDrafMilik($paket, $user);
        $pagu = PaguJenis::query()->findOrFail($paket->pagu_jenis_id);
        $tanggal = now();

        DB::transaction(function () use ($paket, $pagu, $baris, $tanggal) {
            RevisiDetail::query()->where('paket_id', $paket->id)->delete();

            foreach ($baris as $b) {
                $aksi = (string) $b['aksi'];
                $sebelum = null;
                if ($aksi !== BarisRevisi::TAMBAH) {
                    $r = RbaRincian::query()->findOrFail($b['rincian_id']);
                    if ($r->unit_id !== $pagu->unit_id || $r->jenis_id !== $pagu->jenis_id || $r->sumber_dana_id !== $pagu->sumber_dana_id) {
                        throw new KesalahanDomain('RINCIAN_DI_LUAR_PAGU', 'Rincian tidak berada pada unit, jenis belanja, dan sumber dana yang sedang diedit.');
                    }
                    $sebelum = $r->only(['item_id', 'rekening_blud_id', 'volume', 'harga', 'uraian', 'nilai', 'row_version']);
                }

                $sesudah = null;
                $diAtas = false;
                if ($aksi !== BarisRevisi::HAPUS) {
                    $sesudah = $this->normalisasiSesudah($b, $pagu, $tanggal);
                    $diAtas = Uang::dari($sesudah['harga'])->lebihBesar(Uang::dari($sesudah['harga_standar']));
                }

                // Baris UBAH yang nilainya sama persis dengan rincian (mis. "180" vs "180.0000") bukan perubahan.
                if ($aksi === BarisRevisi::UBAH && self::tidakBerubah($sebelum, $sesudah)) {
                    continue;
                }

                RevisiDetail::query()->forceCreate([
                    'paket_id' => $paket->id, 'aksi' => $aksi,
                    'rincian_id' => $aksi === BarisRevisi::TAMBAH ? null : (int) $b['rincian_id'],
                    'rincian_row_version' => $sebelum['row_version'] ?? null,
                    'sebelum' => $sebelum, 'sesudah' => $sesudah, 'di_atas_standar' => $diAtas,
                ]);
            }
        });

        return $this->ringkasan($paket->refresh());
    }

    /** Indikator status pagu (FR-091). Tidak mengunci; hasil final dihitung ulang saat simpan. */
    public function ringkasan(RevisiPaket $paket): HasilRevisi
    {
        $pagu = PaguJenis::query()->findOrFail($paket->pagu_jenis_id);

        return $this->hitung($paket, $pagu, $this->rincianAktif($pagu));
    }

    /**
     * Menyimpan revisi (FR-092). Melempar KesalahanDomain PERLU_PERSETUJUAN (409) bila pagu berubah,
     * agar klien menampilkan dialog: kirim sebagai pergeseran / lanjutkan edit / batalkan.
     */
    public function simpan(RevisiPaket $paket, User $user, ?string $alasan = null): RevisiPaket
    {
        $this->pastikanDrafMilik($paket, $user);
        if (! RevisiDetail::query()->where('paket_id', $paket->id)->exists()) {
            throw new KesalahanDomain('TIDAK_ADA_PERUBAHAN', 'Belum ada rincian yang diubah.', statusHttp: 422);
        }

        return DB::transaction(function () use ($paket, $user, $alasan) {
            [$paket, $pagu, $hasil] = $this->kunciDanHitung($paket);
            $this->tolakBilaMelanggar($hasil);

            if ($hasil->keputusan === KalkulatorRevisi::KEPUTUSAN_PERLU_PERGESERAN) {
                throw new KesalahanDomain('PERLU_PERSETUJUAN', sprintf(
                    'Perubahan ini mengubah pagu %s unit Anda sebesar %s%s dan memerlukan persetujuan melalui pergeseran antarunit.',
                    $this->pemetaan->kodeJenis($pagu->jenis_id), $hasil->selisih->positif() ? '+' : '-', $hasil->selisih->abs()->format()
                ), ['ringkasan' => $hasil->jsonSerialize()], statusHttp: 409);
            }

            if ($hasil->memuatDiAtasStandar) {
                $this->pastikanBuktiHarga($paket);
            }

            $paket->forceFill([
                'nomor' => $this->penomoran->berikutnya('REVISI', $pagu->tahun, $this->kodeUnit($pagu)),
                'total_sebelum' => $hasil->totalSebelum->keDesimal(), 'total_sesudah' => $hasil->totalSesudah->keDesimal(),
                'memuat_di_atas_standar' => $hasil->memuatDiAtasStandar, 'alasan' => $alasan,
            ]);

            if ($hasil->keputusan === KalkulatorRevisi::KEPUTUSAN_BERLAKU) {
                $paket->save();
                $this->terapkan($paket);
                $this->audit->tindakan('REVISI_BERLAKU', 'revisi_paket', $paket->id, $alasan);

                return $paket->refresh();
            }

            // MENUNGGU_PERENCANA: rincian lama tetap berlaku, rincian yang disentuh dikunci.
            $paket->forceFill(['status' => 'MENUNGGU_PERENCANA', 'alasan_menunggu' => $hasil->alasanMenunggu])->save();
            $this->kunciRincian($paket);
            $this->workflow->mulai(
                $hasil->alasanMenunggu === 'DI_ATAS_STANDAR' ? 'REVISI_DI_ATAS_STANDAR' : 'REVISI_PERLU_PERSETUJUAN',
                $paket->id, $user->id, $hasil->totalSesudah->keDesimal(), $pagu->unit_id
            );

            return $paket->refresh();
        }, attempts: 3);
    }

    /** Mengirim selisih pagu sebagai permintaan pergeseran antarunit (FR-092, FR-093). */
    public function kirimPergeseran(RevisiPaket $paket, User $user, ?string $alasan = null): RevisiPaket
    {
        $this->pastikanDrafMilik($paket, $user);

        return DB::transaction(function () use ($paket, $user, $alasan) {
            [$paket, $pagu, $hasil] = $this->kunciDanHitung($paket);
            $this->tolakBilaMelanggar($hasil);

            if ($hasil->keputusan !== KalkulatorRevisi::KEPUTUSAN_PERLU_PERGESERAN) {
                throw new KesalahanDomain('TIDAK_PERLU_PERGESERAN', 'Pagu jenis belanja tidak berubah; simpan revisi seperti biasa.');
            }
            if ($hasil->memuatDiAtasStandar) {
                $this->pastikanBuktiHarga($paket);
            }

            $paket->forceFill([
                'nomor' => $this->penomoran->berikutnya('REVISI', $pagu->tahun, $this->kodeUnit($pagu)),
                'total_sebelum' => $hasil->totalSebelum->keDesimal(), 'total_sesudah' => $hasil->totalSesudah->keDesimal(),
                'memuat_di_atas_standar' => $hasil->memuatDiAtasStandar, 'alasan' => $alasan,
            ])->save();

            $permintaan = $this->permintaan->buat(
                $pagu, (string) $hasil->arahPergeseran(), $hasil->selisih->abs(), $user, $paket, $hasil->memuatDiAtasStandar
            );

            $paket->forceFill(['status' => 'MENUNGGU_PERGESERAN', 'permintaan_id' => $permintaan->id])->save();
            $this->kunciRincian($paket);

            return $paket->refresh();
        }, attempts: 3);
    }

    /** Persetujuan perencana atas revisi seimbang yang menunggu (harga > standar atau jenis selain BBJ). */
    public function setujui(RevisiPaket $paket, User $user, ?string $catatan = null, string $kanal = 'WEB', ?string $metode = null): RevisiPaket
    {
        return DB::transaction(function () use ($paket, $user, $catatan, $kanal, $metode) {
            /** @var RevisiPaket $paket */
            $paket = RevisiPaket::query()->whereKey($paket->id)->lockForUpdate()->firstOrFail();
            if ($paket->status !== 'MENUNGGU_PERENCANA') {
                throw new KesalahanDomain('STATUS_TIDAK_SESUAI', "Revisi berstatus {$paket->status}.", statusHttp: 409);
            }
            $tipe = $paket->alasan_menunggu === 'DI_ATAS_STANDAR' ? 'REVISI_DI_ATAS_STANDAR' : 'REVISI_PERLU_PERSETUJUAN';
            $p = $this->workflow->berjalan($tipe, $paket->id) ?? throw new KesalahanDomain('WORKFLOW_TIDAK_DITEMUKAN', 'Alur persetujuan tidak ditemukan.');

            if (! $this->workflow->setujui($p, $user, $catatan, $kanal, $metode)) {
                return $paket;   // masih ada langkah berikutnya
            }

            [$paket, , $hasil] = $this->kunciDanHitung($paket, abaikanKunciSendiri: true);
            $this->tolakBilaMelanggar($hasil);
            if ($hasil->status !== KalkulatorRevisi::SEIMBANG) {
                throw new KesalahanDomain('PAGU_SUDAH_BERUBAH', 'Pagu telah berubah sejak revisi diajukan; revisi perlu disusun ulang.', statusHttp: 409);
            }
            $this->terapkan($paket);

            return $paket->refresh();
        }, attempts: 3);
    }

    public function tolak(RevisiPaket $paket, User $user, string $catatan, string $kanal = 'WEB', ?string $metode = null): RevisiPaket
    {
        return DB::transaction(function () use ($paket, $user, $catatan, $kanal, $metode) {
            /** @var RevisiPaket $paket */
            $paket = RevisiPaket::query()->whereKey($paket->id)->lockForUpdate()->firstOrFail();
            if ($paket->status !== 'MENUNGGU_PERENCANA') {
                throw new KesalahanDomain('STATUS_TIDAK_SESUAI', "Revisi berstatus {$paket->status}.", statusHttp: 409);
            }
            $tipe = $paket->alasan_menunggu === 'DI_ATAS_STANDAR' ? 'REVISI_DI_ATAS_STANDAR' : 'REVISI_PERLU_PERSETUJUAN';
            $p = $this->workflow->berjalan($tipe, $paket->id) ?? throw new KesalahanDomain('WORKFLOW_TIDAK_DITEMUKAN', 'Alur persetujuan tidak ditemukan.');
            $this->workflow->tolak($p, $user, $catatan, false, $kanal, $metode);

            $this->bukaKunciRincian($paket);
            $paket->forceFill(['status' => 'DITOLAK'])->save();

            return $paket->refresh();
        });
    }

    public function batalkanDraf(RevisiPaket $paket, User $user): void
    {
        $this->pastikanDrafMilik($paket, $user);
        DB::transaction(function () use ($paket) {
            $paket->forceFill(['status' => 'DIBATALKAN'])->save();
        });
    }

    /** Dipanggil PermintaanService saat permintaan pergeseran ditolak/kedaluwarsa/dibatalkan. */
    public function gugurkan(RevisiPaket $paket, string $alasan): void
    {
        $this->bukaKunciRincian($paket);
        $paket->forceFill(['status' => 'GUGUR', 'alasan' => trim(($paket->alasan ? $paket->alasan.' | ' : '').$alasan)])->save();
    }

    /**
     * Menerapkan detail paket ke rba_rincian. Wajib di dalam transaksi yang sudah mengunci pagu & rincian.
     * Dipakai juga oleh PasanganService setelah pagu diubah.
     */
    public function terapkan(RevisiPaket $paket): void
    {
        $pagu = PaguJenis::query()->findOrFail($paket->pagu_jenis_id);
        $rba = Rba::definitif($pagu->tahun) ?? throw new KesalahanDomain('RBA_BELUM_DEFINITIF', 'RBA tahun ini belum definitif.');
        Kunci::izinkanUbahRincian();

        foreach (RevisiDetail::query()->where('paket_id', $paket->id)->orderBy('id')->get() as $d) {
            $s = $d->sesudah;
            $dasar = $d->di_atas_standar ? 'DISETUJUI_DI_ATAS_STANDAR' : 'STANDAR';

            match ($d->aksi) {
                BarisRevisi::TAMBAH => RbaRincian::query()->forceCreate([
                    'rba_id' => $rba->id, 'unit_id' => $pagu->unit_id, 'sumber_dana_id' => $pagu->sumber_dana_id,
                    'jenis_id' => $pagu->jenis_id, 'rekening_blud_id' => $s['rekening_blud_id'], 'rekening_pemda_id' => $s['rekening_pemda_id'],
                    'sub_kegiatan_id' => $s['sub_kegiatan_id'] ?? null, 'item_id' => $s['item_id'], 'uraian' => $s['uraian'],
                    'volume' => $s['volume'], 'satuan' => $s['satuan'], 'harga_standar' => $s['harga_standar'], 'harga' => $s['harga'],
                    'dasar_harga' => $dasar,
                ]),
                BarisRevisi::UBAH => RbaRincian::query()->whereKey($d->rincian_id)->update([
                    'rekening_blud_id' => $s['rekening_blud_id'], 'rekening_pemda_id' => $s['rekening_pemda_id'],
                    'item_id' => $s['item_id'], 'uraian' => $s['uraian'], 'volume' => $s['volume'], 'satuan' => $s['satuan'],
                    'harga_standar' => $s['harga_standar'], 'harga' => $s['harga'], 'dasar_harga' => $dasar,
                    'terkunci_paket_id' => null,
                ]),
                BarisRevisi::HAPUS => RbaRincian::query()->whereKey($d->rincian_id)->update(['aktif' => false, 'terkunci_paket_id' => null]),
            };
        }

        $paket->forceFill(['status' => 'BERLAKU', 'diterapkan_pada' => now()])->save();
    }

    /**
     * Menghitung ulang paket terhadap pagu tertentu (dipakai PasanganService setelah pagu berubah).
     * Rincian yang dikunci paket ini sendiri tidak dianggap terkunci.
     */
    public function hitungUntukPagu(RevisiPaket $paket, PaguJenis $pagu): HasilRevisi
    {
        return $this->hitung($paket, $pagu, $this->rincianAktif($pagu), true);
    }

    // ------------------------------------------------------------------ internal

    /** @return array{0: RevisiPaket, 1: PaguJenis, 2: HasilRevisi} */
    private function kunciDanHitung(RevisiPaket $paket, bool $abaikanKunciSendiri = false): array
    {
        /** @var RevisiPaket $paket */
        $paket = RevisiPaket::query()->whereKey($paket->id)->lockForUpdate()->firstOrFail();
        /** @var PaguJenis $pagu */
        $pagu = Kunci::baris(PaguJenis::class, [$paket->pagu_jenis_id])->first();
        $ids = RevisiDetail::query()->where('paket_id', $paket->id)->whereNotNull('rincian_id')->pluck('rincian_id');
        Kunci::baris(RbaRincian::class, $ids);

        return [$paket, $pagu, $this->hitung($paket, $pagu, $this->rincianAktif($pagu), $abaikanKunciSendiri)];
    }

    /** @param list<RbaRincian> $rincian */
    private function hitung(RevisiPaket $paket, PaguJenis $pagu, array $rincian, bool $abaikanKunciSendiri = true): HasilRevisi
    {
        $berlaku = array_map(static fn (RbaRincian $r) => new RincianBerlaku(
            id: $r->id, nilai: Uang::dari((string) $r->nilai), realisasi: Uang::dari((string) $r->realisasi),
            komitmen: Uang::dari((string) $r->komitmen), rowVersion: $r->row_version,
            terkunciPaketLain: $r->terkunci_paket_id !== null && ! ($abaikanKunciSendiri && $r->terkunci_paket_id === $paket->id),
            terkunciTransaksi: $r->terkunci_transaksi,
        ), $rincian);

        $draf = RevisiDetail::query()->where('paket_id', $paket->id)->orderBy('id')->get()->map(static fn (RevisiDetail $d) => new BarisRevisi(
            aksi: $d->aksi, rincianId: $d->rincian_id,
            volume: (string) ($d->sesudah['volume'] ?? '0'),
            harga: Uang::dari((string) ($d->sesudah['harga'] ?? '0')),
            hargaStandar: Uang::dari((string) ($d->sesudah['harga_standar'] ?? '0')),
            rowVersion: $d->rincian_row_version,
        ))->all();

        $kodeJenis = $this->pemetaan->kodeJenis($pagu->jenis_id);
        $berlakuLangsung = in_array($kodeJenis, (array) $this->konfigurasi->get('revisi.jenis_berlaku_langsung', ['5.1.02']), true);

        return $this->kalkulator->hitung($pagu->paguBerlaku(), $berlaku, $draf, $berlakuLangsung);
    }

    /** @return list<RbaRincian> */
    private function rincianAktif(PaguJenis $pagu): array
    {
        $rba = Rba::definitif($pagu->tahun) ?? throw new KesalahanDomain('RBA_BELUM_DEFINITIF', 'RBA tahun ini belum definitif.');

        return RbaRincian::query()->where('rba_id', $rba->id)->where('aktif', true)
            ->where('unit_id', $pagu->unit_id)->where('jenis_id', $pagu->jenis_id)->where('sumber_dana_id', $pagu->sumber_dana_id)
            ->orderBy('id')->get()->all();
    }

    /**
     * Validasi baris yang akan ditulis: item aktif & setahun, rekening sejenis dengan pagu (via pemetaan),
     * harga standar diambil dari item (bukan dari klien).
     *
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed>|null $sebelum
     * @param array<string, mixed>|null $sesudah
     */
    private static function tidakBerubah(?array $sebelum, ?array $sesudah): bool
    {
        if ($sebelum === null || $sesudah === null) {
            return false;
        }
        $angka = static fn ($v): string => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v;

        return (int) $sebelum['item_id'] === (int) $sesudah['item_id']
            && (int) $sebelum['rekening_blud_id'] === (int) $sesudah['rekening_blud_id']
            && $angka($sebelum['volume']) === $angka($sesudah['volume'])
            && Uang::dari((string) $sebelum['harga'])->sama(Uang::dari((string) $sesudah['harga']));
    }

    private function normalisasiSesudah(array $b, PaguJenis $pagu, \DateTimeInterface $tanggal): array
    {
        /** @var StandarHargaItem|null $item */
        $item = StandarHargaItem::query()->whereKey($b['item_id'] ?? 0)->first();
        if ($item === null || $item->status !== 'AKTIF' || $item->tahun !== $pagu->tahun) {
            throw new KesalahanDomain('ITEM_TIDAK_BERLAKU', 'Item harus dipilih dari daftar standar harga yang aktif pada tahun anggaran ini.',
                tindakan: ['label' => 'Usulkan standar harga baru', 'url' => '/standar-harga/usulan/baru?konteks=REVISI']);
        }
        if ($item->berlaku_mulai->greaterThan($tanggal)) {
            throw new KesalahanDomain('ITEM_BELUM_BERLAKU', "Item {$item->uraian} baru berlaku mulai {$item->berlaku_mulai->format('d-m-Y')}.");
        }

        $map = $this->pemetaan->resolusi((int) $b['rekening_blud_id'], $tanggal);
        if ($map['jenis_id'] !== $pagu->jenis_id) {
            throw new KesalahanDomain('BEDA_JENIS_BELANJA', 'Rekening yang dipilih berada di jenis belanja lain. Perubahan lintas jenis dilakukan melalui perubahan RBA.');
        }

        $volume = (string) $b['volume'];
        $harga = Uang::dari((string) $b['harga']);
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $volume) || (float) $volume <= 0) {
            throw new KesalahanDomain('VOLUME_TIDAK_VALID', 'Volume harus lebih dari 0 dengan maksimal 4 desimal.');
        }
        if (! $harga->positif()) {
            throw new KesalahanDomain('HARGA_TIDAK_VALID', 'Harga satuan harus lebih dari 0.');
        }

        return [
            'item_id' => $item->id, 'rekening_blud_id' => (int) $b['rekening_blud_id'], 'rekening_pemda_id' => $map['rekening_pemda_id'],
            'sub_kegiatan_id' => $b['sub_kegiatan_id'] ?? null, 'uraian' => (string) ($b['uraian'] ?? $item->uraian),
            'volume' => $volume, 'satuan' => $item->satuan, 'harga' => $harga->keDesimal(),
            'harga_standar' => Uang::dari((string) $item->harga)->keDesimal(),
        ];
    }

    private function tolakBilaMelanggar(HasilRevisi $hasil): void
    {
        if ($hasil->pelanggaran !== []) {
            throw new KesalahanDomain('REVISI_TIDAK_VALID', $hasil->pelanggaran[0]->pesan, ['pelanggaran' => $hasil->pelanggaran]);
        }
    }

    /**
     * Bukti harga pasar dilampirkan pada paket dengan kunci baris: "rincian:{id}" untuk UBAH, "item:{id}" untuk TAMBAH.
     * Kunci ini stabil walaupun draf disimpan ulang (autosave).
     */
    public static function kunciBukti(?int $rincianId, int $itemId): string
    {
        return $rincianId !== null ? 'BUKTI_HARGA:rincian:'.$rincianId : 'BUKTI_HARGA:item:'.$itemId;
    }

    private function pastikanBuktiHarga(RevisiPaket $paket): void
    {
        $ada = DB::table('lampiran')->where('lampiranable_type', 'revisi_paket')->where('lampiranable_id', $paket->id)
            ->pluck('jenis_dokumen')->flip();
        $tanpaBukti = RevisiDetail::query()->where('paket_id', $paket->id)->where('di_atas_standar', true)->get()
            ->filter(fn (RevisiDetail $d) => ! isset($ada[self::kunciBukti($d->rincian_id, (int) $d->sesudah['item_id'])]))
            ->count();
        if ($tanpaBukti > 0) {
            throw new KesalahanDomain('BUKTI_HARGA_WAJIB', 'Baris dengan harga di atas standar wajib dilampiri bukti harga pasar.', ['jumlah_baris' => $tanpaBukti]);
        }
    }

    /** Menyimpan berkas bukti harga untuk satu baris draf. */
    public function lampirkanBukti(RevisiPaket $paket, User $user, ?int $rincianId, int $itemId, \Illuminate\Http\UploadedFile $file): void
    {
        $this->pastikanDrafMilik($paket, $user);
        $path = $file->store('lampiran/revisi/'.$paket->uuid);
        DB::table('lampiran')->insert([
            'lampiranable_type' => 'revisi_paket', 'lampiranable_id' => $paket->id,
            'jenis_dokumen' => self::kunciBukti($rincianId, $itemId), 'nama_asli' => $file->getClientOriginalName(),
            'path' => $path, 'mime' => (string) $file->getMimeType(), 'ukuran' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()), 'dibuat_oleh' => $user->id,
        ]);
    }

    /**
     * Mengunci rincian untuk paket. Mengunci menaikkan row_version rincian, jadi versi yang dicatat
     * di detail ikut disinkronkan: sejak terkunci, kunci paketlah penjaga konkurensinya.
     * Hanya rincian yang versinya masih sama dengan saat draf dibuat yang boleh dikunci.
     */
    private function kunciRincian(RevisiPaket $paket): void
    {
        $jumlah = RevisiDetail::query()->where('paket_id', $paket->id)->whereNotNull('rincian_id')->count();
        $terkunci = DB::update(
            'UPDATE rba_rincian r SET terkunci_paket_id = d.paket_id
               FROM revisi_detail d
              WHERE d.paket_id = ? AND d.rincian_id = r.id
                AND (r.terkunci_paket_id IS NULL OR r.terkunci_paket_id = d.paket_id)
                AND (d.rincian_row_version IS NULL OR r.row_version = d.rincian_row_version)',
            [$paket->id],
        );
        if ($terkunci !== $jumlah) {
            throw new KonflikVersi('Rincian pada revisi '.$paket->nomor);
        }
        DB::update(
            'UPDATE revisi_detail d SET rincian_row_version = r.row_version
               FROM rba_rincian r WHERE d.paket_id = ? AND d.rincian_id = r.id',
            [$paket->id],
        );
    }

    private function bukaKunciRincian(RevisiPaket $paket): void
    {
        RbaRincian::query()->where('terkunci_paket_id', $paket->id)->update(['terkunci_paket_id' => null]);
    }

    private function pastikanDrafMilik(RevisiPaket $paket, User $user): void
    {
        if ($paket->status !== 'DRAF') {
            throw new KesalahanDomain('STATUS_TIDAK_SESUAI', "Revisi berstatus {$paket->status} dan tidak dapat diedit.", statusHttp: 409);
        }
        if ((int) $paket->dibuat_oleh !== (int) $user->id) {
            throw new KesalahanDomain('BUKAN_PEMILIK_DRAF', 'Draf revisi ini milik pengguna lain.', statusHttp: 403);
        }
    }

    private function pastikanAkses(PaguJenis $pagu, User $user): void
    {
        if (! $user->can('revisi.ajukan') || ! $this->akses->milik($user->id, $pagu->unit_id)) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Anda tidak berwenang merevisi rincian unit ini.', statusHttp: 403);
        }
    }

    private function kodeUnit(PaguJenis $pagu): string
    {
        return (string) DB::table('unit_kerja')->where('id', $pagu->unit_id)->value('kode');
    }
}
