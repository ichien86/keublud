<script setup>
// Kotak masuk persetujuan, mobile-first (PRD 7.18). Satu kartu per pengajuan, keputusan lewat lembar bawah.
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import TataLetak from '../../Components/TataLetak.vue';
import { formatRp, keSen } from '../../Lib/uang.js';
import { kirim } from '../../Lib/http.js';

const props = defineProps({ item: Array, perlu_pin: Boolean });
const sederhana = computed(() => usePage().props.auth?.mode_sederhana);

const terbuka = ref(null);
const lembar = reactive({ tampil: false, item: null, keputusan: null, catatan: '', pin: '', memproses: false, galat: '' });
const pesan = ref(null);

const kunci = (i) => `${i.tipe}:${i.id}:${i.permintaan_id ?? ''}`;
const tanggal = (t) => (t ? new Date(t).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '');
const sisaHari = (t) => {
  if (!t) return null;
  const jam = (new Date(t) - new Date()) / 36e5;
  return jam < 0 ? 'lewat batas' : jam < 24 ? `${Math.ceil(jam)} jam lagi` : `${Math.ceil(jam / 24)} hari lagi`;
};

function buka(item, keputusan) {
  Object.assign(lembar, { tampil: true, item, keputusan, catatan: '', pin: '', galat: '' });
}

async function putuskan() {
  const { item, keputusan } = lembar;
  if (keputusan === 'TOLAK' && lembar.catatan.trim().length < 10) {
    lembar.galat = 'Tuliskan alasan minimal 10 karakter.';
    return;
  }
  lembar.memproses = true;
  lembar.galat = '';
  try {
    const h = await kirim('POST', `/persetujuan/${item.tipe}/${item.id}`, {
      keputusan, catatan: lembar.catatan || null, pin: lembar.pin || null, permintaan_id: item.permintaan_id,
      kanal: window.innerWidth < 640 ? 'MOBILE' : 'WEB',
    });
    lembar.tampil = false;
    pesan.value = { jenis: 'sukses', teks: `${item.judul}: ${keputusan === 'SETUJUI' ? item.aksi_setuju.toLowerCase() : item.aksi_tolak.toLowerCase()} — status ${h.status}.` };
    router.reload({ only: ['item', 'menunggu'] });
  } catch (e) {
    lembar.galat = e.message;
  } finally {
    lembar.memproses = false;
  }
}
</script>

<template>
  <Head title="Persetujuan" />
  <TataLetak lebar="max-w-2xl">
    <header class="flex items-baseline justify-between">
      <h1 class="font-semibold" :class="sederhana ? 'text-2xl' : 'text-lg'">Menunggu keputusan Anda</h1>
      <span class="text-sm text-slate-500">{{ item.length }} pengajuan</span>
    </header>

    <p v-if="pesan" class="rounded-lg p-3 text-sm" :class="pesan.jenis === 'sukses' ? 'bg-teal-50 text-teal-800' : 'bg-rose-50 text-rose-700'">{{ pesan.teks }}</p>

    <div v-if="!item.length" class="rounded-xl bg-white p-8 text-center ring-1 ring-slate-200">
      <div class="text-3xl">✓</div>
      <p class="mt-2 text-slate-600">Tidak ada pengajuan yang menunggu.</p>
    </div>

    <article v-for="i in item" :key="kunci(i)" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <button type="button" class="w-full p-4 text-left" @click="terbuka = terbuka === kunci(i) ? null : kunci(i)">
        <div class="flex items-center justify-between gap-2">
          <span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-800">{{ i.label }}</span>
          <span v-if="i.batas" class="text-xs" :class="sisaHari(i.batas) === 'lewat batas' ? 'text-rose-600' : 'text-amber-700'">⏱ {{ sisaHari(i.batas) }}</span>
        </div>
        <div class="mt-2 font-medium" :class="sederhana ? 'text-lg' : ''">{{ i.judul }}</div>
        <div class="angka mt-1 font-semibold text-slate-900" :class="sederhana ? 'text-2xl' : 'text-xl'">{{ formatRp(keSen(i.nilai)) }}</div>
        <div class="mt-1 text-xs text-slate-500">Diajukan {{ i.pengusul ?? 'sistem' }} · {{ tanggal(i.diajukan_pada) }}</div>
        <p v-if="i.peringatan" class="mt-2 rounded-md bg-amber-50 p-2 text-xs text-amber-800">⚠ {{ i.peringatan }}</p>
      </button>

      <div v-if="terbuka === kunci(i) && i.rincian.length" class="border-t border-slate-100 bg-slate-50 px-4 py-3">
        <div v-for="(r, n) in i.rincian" :key="n" class="flex justify-between gap-3 py-1 text-sm">
          <span class="text-slate-700">{{ r.label }}</span><span class="angka shrink-0">{{ formatRp(keSen(r.nilai)) }}</span>
        </div>
      </div>

      <div class="grid grid-cols-2 border-t border-slate-100">
        <button type="button" class="py-3 font-medium text-rose-700 hover:bg-rose-50" @click="buka(i, 'TOLAK')">{{ i.aksi_tolak }}</button>
        <button type="button" class="border-l border-slate-100 bg-teal-700 py-3 font-medium text-white hover:bg-teal-800" @click="buka(i, 'SETUJUI')">{{ i.aksi_setuju }}</button>
      </div>
    </article>

    <!-- Lembar konfirmasi -->
    <div v-if="lembar.tampil" class="fixed inset-0 z-30 flex items-end justify-center bg-slate-900/40 sm:items-center" @click.self="lembar.tampil = false">
      <form class="w-full max-w-md space-y-3 rounded-t-2xl bg-white p-5 sm:rounded-2xl" @submit.prevent="putuskan">
        <div class="mx-auto h-1 w-10 rounded-full bg-slate-300 sm:hidden" />
        <h2 class="text-base font-semibold">
          {{ lembar.keputusan === 'SETUJUI' ? lembar.item.aksi_setuju : lembar.item.aksi_tolak }}: {{ lembar.item.judul }}
        </h2>
        <p class="angka text-lg font-semibold">{{ formatRp(keSen(lembar.item.nilai)) }}</p>
        <label class="block text-sm">
          <span class="text-slate-700">{{ lembar.keputusan === 'TOLAK' ? 'Alasan (wajib, min. 10 karakter)' : 'Catatan (opsional)' }}</span>
          <textarea v-model="lembar.catatan" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" />
        </label>
        <label v-if="perlu_pin" class="block text-sm">
          <span class="text-slate-700">PIN konfirmasi</span>
          <input v-model="lembar.pin" type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest" />
        </label>
        <p v-if="lembar.galat" class="rounded-md bg-rose-50 p-2 text-sm text-rose-700">{{ lembar.galat }}</p>
        <div class="grid grid-cols-2 gap-2 pt-1">
          <button type="button" class="rounded-lg border border-slate-300 py-2.5" @click="lembar.tampil = false">Batal</button>
          <button type="submit" :disabled="lembar.memproses" class="rounded-lg py-2.5 font-medium text-white disabled:opacity-60"
            :class="lembar.keputusan === 'SETUJUI' ? 'bg-teal-700' : 'bg-rose-600'">
            {{ lembar.memproses ? 'Memproses…' : 'Konfirmasi' }}
          </button>
        </div>
      </form>
    </div>
  </TataLetak>
</template>
