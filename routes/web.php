<?php

use App\Http\Controllers\BerandaController;
use App\Modules\Anggaran\Http\RbaController;
use App\Modules\ImporRba\Http\ImporRbaController;
use App\Modules\Kodefikasi\Http\KodefikasiController;
use App\Modules\Pergeseran\Http\PergeseranController;
use App\Modules\Revisi\Http\RevisiController;
use App\Modules\StandarHarga\Http\StandarHargaController;
use App\Modules\Workflow\Http\PersetujuanController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/beranda');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/beranda', BerandaController::class)->name('beranda');

    // Kotak masuk persetujuan (mobile-first)
    Route::get('/persetujuan', [PersetujuanController::class, 'index'])->name('persetujuan.index');
    Route::post('/persetujuan/{tipe}/{id}', [PersetujuanController::class, 'putuskan'])->whereNumber('id')->name('persetujuan.putuskan');

    // RBA
    Route::get('/rba', [RbaController::class, 'index'])->name('rba.index');
    Route::post('/rba/{rba}/ajukan', [RbaController::class, 'ajukan'])->name('rba.ajukan');

    // Impor RBA format lama
    Route::get('/impor-rba', [ImporRbaController::class, 'index'])->name('impor_rba.index');
    Route::post('/impor-rba/unggah', [ImporRbaController::class, 'unggah'])->name('impor_rba.unggah');
    Route::post('/impor-rba/pratinjau', [ImporRbaController::class, 'pratinjau'])->name('impor_rba.pratinjau');
    Route::post('/impor-rba/mulai', [ImporRbaController::class, 'mulai'])->name('impor_rba.mulai');
    Route::put('/impor-rba/baris/{baris}/item', [ImporRbaController::class, 'item'])->whereNumber('baris')->name('impor_rba.item');
    Route::post('/impor-rba/baris/{baris}/abaikan', [ImporRbaController::class, 'abaikan'])->whereNumber('baris')->name('impor_rba.abaikan');
    Route::get('/impor-rba/{uuid}', [ImporRbaController::class, 'tampil'])->whereUuid('uuid')->name('impor_rba.tampil');
    Route::post('/impor-rba/{uuid}/kodefikasi', [ImporRbaController::class, 'kodefikasi'])->whereUuid('uuid')->name('impor_rba.kodefikasi');
    Route::post('/impor-rba/{uuid}/label', [ImporRbaController::class, 'label'])->whereUuid('uuid')->name('impor_rba.label');
    Route::post('/impor-rba/{uuid}/ajukan-usulan', [ImporRbaController::class, 'ajukanUsulan'])->whereUuid('uuid')->name('impor_rba.usulan');
    Route::post('/impor-rba/{uuid}/unit-massal', [ImporRbaController::class, 'unitMassal'])->whereUuid('uuid')->name('impor_rba.unit_massal');
    Route::post('/impor-rba/{uuid}/bentuk', [ImporRbaController::class, 'bentuk'])->whereUuid('uuid')->name('impor_rba.bentuk');
    Route::post('/impor-rba/{uuid}/batalkan', [ImporRbaController::class, 'batalkan'])->whereUuid('uuid')->name('impor_rba.batalkan');

    // Revisi rincian (PRD 7.7, TDD 6.3)
    Route::get('/revisi/edit/{pagu}', [RevisiController::class, 'edit'])->name('revisi.edit');
    Route::put('/revisi/{paket}/baris', [RevisiController::class, 'simpanBaris'])->name('revisi.baris');
    Route::get('/revisi/{paket}/ringkasan', [RevisiController::class, 'ringkasan'])->name('revisi.ringkasan');
    Route::post('/revisi/{paket}/simpan', [RevisiController::class, 'simpan'])->name('revisi.simpan');
    Route::post('/revisi/{paket}/kirim-pergeseran', [RevisiController::class, 'kirimPergeseran'])->name('revisi.kirim');
    Route::post('/revisi/{paket}/bukti', [RevisiController::class, 'bukti'])->name('revisi.bukti');
    Route::post('/revisi/{paket}/batalkan', [RevisiController::class, 'batalkan'])->name('revisi.batalkan');
    Route::post('/revisi/{paket}/setujui', [RevisiController::class, 'setujui'])->name('revisi.setujui');
    Route::post('/revisi/{paket}/tolak', [RevisiController::class, 'tolak'])->name('revisi.tolak');

    // Pergeseran antarunit (PRD 7.8, 7.8a)
    Route::get('/pergeseran', [PergeseranController::class, 'index'])->name('pergeseran.index');
    Route::get('/pergeseran/terbuka', [PergeseranController::class, 'terbuka'])->name('pergeseran.terbuka');
    Route::post('/pergeseran/permintaan/{permintaan}/batalkan', [PergeseranController::class, 'batalkanPermintaan'])->name('pergeseran.permintaan.batalkan');
    Route::post('/pergeseran/pasangan', [PergeseranController::class, 'bentuk'])->name('pergeseran.pasangan.bentuk');
    Route::post('/pergeseran/pasangan/{pasangan}/persetujuan-unit', [PergeseranController::class, 'putuskanUnit'])->name('pergeseran.pasangan.unit');
    Route::post('/pergeseran/pasangan/{pasangan}/sahkan', [PergeseranController::class, 'sahkan'])->name('pergeseran.pasangan.sahkan');
    Route::post('/pergeseran/pasangan/{pasangan}/tolak', [PergeseranController::class, 'tolak'])->name('pergeseran.pasangan.tolak');

    // Kodefikasi
    Route::get('/kodefikasi/rekening', [KodefikasiController::class, 'rekeningSejenis'])->name('kodefikasi.rekening');
    Route::get('/kodefikasi/pemetaan/belum', [KodefikasiController::class, 'belumDipetakan'])->name('kodefikasi.pemetaan.belum');

    // Standar harga (PRD 7.4, 7.4a)
    Route::get('/standar-harga', [StandarHargaController::class, 'index'])->name('standar.index');
    Route::get('/standar-harga/cari', [StandarHargaController::class, 'cari'])->name('standar.cari');
    Route::get('/standar-harga/cocok', [StandarHargaController::class, 'cocok'])->middleware('throttle:60,1')->name('standar.cocok');
    Route::post('/standar-harga/usulan', [StandarHargaController::class, 'ajukan'])->name('standar.usulan.ajukan');
    Route::post('/standar-harga/usulan/{usulan}/putuskan', [StandarHargaController::class, 'putuskan'])->name('standar.usulan.putuskan');
    Route::get('/standar-harga/usulan/template', [StandarHargaController::class, 'template'])->name('standar.usulan.template');
    Route::post('/standar-harga/usulan/impor', [StandarHargaController::class, 'imporPratinjau'])->name('standar.usulan.impor');
    Route::post('/standar-harga/usulan/batch/{batch}/ajukan', [StandarHargaController::class, 'imporAjukan'])->name('standar.batch.ajukan');
    Route::post('/standar-harga/usulan/batch/{batch}/sahkan-memenuhi-syarat', [StandarHargaController::class, 'sahkanMemenuhiSyarat'])->name('standar.batch.sahkan');
});
