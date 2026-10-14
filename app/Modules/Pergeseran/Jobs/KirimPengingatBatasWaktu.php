<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Jobs;

use App\Models\User;
use App\Modules\Pergeseran\Models\PasanganPergeseran;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Shared\Konfigurasi;
use App\Modules\Shared\Notifikasi\NotifikasiBlud;
use App\Modules\Shared\Notifikasi\Penerima;
use App\Modules\Shared\Support\Kalender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Hari kerja pukul 08:00: pengingat hari ke-4 (pasangan) dan hari ke-3/ke-4 (pengesahan). */
final class KirimPengingatBatasWaktu implements ShouldQueue
{
    use Queueable;

    public function handle(Kalender $kalender, Konfigurasi $konfigurasi, Penerima $penerima): void
    {
        $k = $kalender->kerja();
        $kini = now()->toDateTimeImmutable();
        if (! $k->hariKerja($kini)) {
            return;
        }

        $hariPasangan = (array) $konfigurasi->get('pergeseran.pengingat_pasangan_hari_ke', [4]);
        PermintaanPergeseran::query()->where('status', 'TERBUKA')->where('pengingat_terkirim', false)->get()
            ->each(function (PermintaanPergeseran $m) use ($k, $kini, $hariPasangan) {
                if (in_array($k->hariKerjaKeBerapa($m->diajukan_pada, $kini), $hariPasangan, true)) {
                    User::query()->find($m->diajukan_oleh)?->notify(new NotifikasiBlud('PENGINGAT_PASANGAN',
                        "Permintaan {$m->nomor} belum berpasangan",
                        'Batas pasangan '.$m->batas_pasangan->format('d-m-Y H:i').' WIB. Setelah itu permintaan ditolak otomatis.',
                        '/pergeseran/terbuka', true));
                    $m->forceFill(['pengingat_terkirim' => true])->save();
                }
            });

        $hariPengesahan = (array) $konfigurasi->get('pergeseran.pengingat_pengesahan_hari_ke', [3, 4]);
        PasanganPergeseran::query()->where('status', 'MENUNGGU_PERENCANA')->where('batas_pengesahan', '>=', now())->get()
            ->each(function (PasanganPergeseran $p) use ($k, $kini, $hariPengesahan, $penerima) {
                $hari = $k->hariKerjaKeBerapa($p->lengkap_pada, $kini);
                if (in_array($hari, $hariPengesahan, true) && Cache::add("pengingat-pengesahan:{$p->id}:{$hari}", true, now()->addDays(2))) {
                    Notification::send($penerima->denganPermission('pergeseran.sahkan'), new NotifikasiBlud('PENGINGAT_PENGESAHAN',
                        "Pasangan {$p->nomor} menunggu pengesahan (hari kerja ke-{$hari})",
                        $p->memuat_di_atas_standar ? 'Wajib diputuskan perencana.' : 'Akan disetujui otomatis pada '.$p->batas_pengesahan->format('d-m-Y H:i').' WIB.',
                        "/pergeseran/pasangan/{$p->uuid}"));
                }
            });
    }
}
