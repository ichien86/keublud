<script setup>
// Impor RBA format lama (TDD 8.4): unggah → pilih sheet & kelompok nilai → pratinjau rekonsiliasi → staging.
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import TataLetak from '../../Components/TataLetak.vue';
import { formatRp, keSen } from '../../Lib/uang.js';
import { kirim } from '../../Lib/http.js';

const props = defineProps({ impor: Array, sumber_dana: Array, batas_unggah_mb: Number });

const st = reactive({ batch: null, sheet: [], pilih: '', kelompok: 0, pratinjau: null, tahun: null, sumber: props.sumber_dana[0]?.id, sibuk: '', galat: '' });
const berkas = ref(null);

function xsrf() {
  const c = document.cookie.split('; ').find((x) => x.startsWith('XSRF-TOKEN='));
  return c ? decodeURIComponent(c.split('=')[1]) : '';
}

async function unggah() {
  const f = berkas.value?.files?.[0];
  if (!f) return;
  if (f.size > props.batas_unggah_mb * 1048576) {
    st.galat = `Ukuran file ${(f.size / 1048576).toFixed(1)} MB melebihi batas unggah server (${props.batas_unggah_mb} MB). Naikkan upload_max_filesize dan post_max_size di php.ini.`;
    return;
  }
  Object.assign(st, { sibuk: 'Mengunggah dan membaca daftar sheet…', galat: '', pratinjau: null, sheet: [] });
  const data = new FormData();
  data.append('file', f);
  try {
    const res = await fetch('/impor-rba/unggah', { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() } });
    const isi = await res.json().catch(() => null);
    if (!res.ok) throw new Error(isi?.pesan ?? isi?.message ?? `Gagal mengunggah (${res.status})`);
    st.batch = isi.batch_uuid;
    st.sheet = isi.sheet;
    st.pilih = isi.sheet[0] ?? '';
    await pratinjau();
  } catch (e) {
    st.galat = e.message;
  } finally {
    st.sibuk = '';
  }
}

async function pratinjau() {
  if (!st.pilih) return;
  Object.assign(st, { sibuk: `Membaca sheet "${st.pilih}"…`, galat: '' });
  try {
    st.pratinjau = await kirim('POST', '/impor-rba/pratinjau', { batch_uuid: st.batch, sheet: st.pilih, kelompok: st.kelompok });
    if (st.pratinjau.kelompok.length && st.kelompok >= st.pratinjau.kelompok.length) { st.kelompok = 0; return pratinjau(); }
    st.tahun ??= st.pratinjau.tahun;
  } catch (e) {
    st.galat = e.message;
  } finally {
    st.sibuk = '';
  }
}

async function mulai() {
  Object.assign(st, { sibuk: 'Menyimpan ke staging…', galat: '' });
  try {
    const h = await kirim('POST', '/impor-rba/mulai', { batch_uuid: st.batch, sheet: st.pilih, kelompok: st.kelompok, tahun: st.tahun, sumber_dana_id: st.sumber });
    router.visit(`/impor-rba/${h.uuid}`);
  } catch (e) {
    st.galat = e.message;
    st.sibuk = '';
  }
}
const label = (s) => s.replaceAll('_', ' ').toLowerCase();
</script>

<template>
  <Head title="Impor RBA" />
  <TataLetak judul="Impor RBA format lama" lebar="max-w-5xl">
    <p class="text-sm text-slate-600">
      Unggah file Excel <b>Lampiran Rincian Anggaran Belanja</b>. Sistem membaca kode rekening Pemda & BLUD, komponen, volume, harga,
      dan label penanggung jawab, lalu mencocokkan total per rekening dengan file. Setiap baris kemudian dirinci ke item standar harga
      sebelum RBA terbentuk.
    </p>

    <section class="space-y-3 rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <h2 class="font-semibold">1. Unggah file</h2>
      <div class="flex flex-wrap items-center gap-2">
        <input ref="berkas" type="file" accept=".xlsx,.xls" class="text-sm" />
        <button type="button" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm text-white" :disabled="!!st.sibuk" @click="unggah">Unggah</button>
        <span class="text-xs text-slate-500">Batas unggah server: {{ batas_unggah_mb }} MB</span>
      </div>

      <template v-if="st.sheet.length">
        <h2 class="pt-2 font-semibold">2. Pilih sheet dan kelompok nilai</h2>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="st.pilih" class="max-w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="st.kelompok = 0; pratinjau()">
            <option v-for="s in st.sheet" :key="s" :value="s">{{ s }}</option>
          </select>
          <label v-for="(k, i) in st.pratinjau?.kelompok ?? []" :key="k" class="flex items-center gap-1 rounded-lg border px-2 py-1 text-sm"
            :class="st.kelompok === i ? 'border-teal-600 bg-teal-50' : 'border-slate-300'">
            <input v-model="st.kelompok" type="radio" :value="i" @change="pratinjau" /> {{ k }}
          </label>
        </div>
      </template>

      <p v-if="st.sibuk" class="text-sm text-slate-500">⏳ {{ st.sibuk }}</p>
      <p v-if="st.galat" class="rounded-md bg-rose-50 p-2 text-sm text-rose-700">{{ st.galat }}</p>

      <div v-if="st.pratinjau && !st.pratinjau.galat" class="space-y-3">
        <div class="grid gap-2 text-sm sm:grid-cols-2">
          <div v-for="(v, k) in st.pratinjau.kepala" :key="k"><span class="text-slate-500">{{ k }}:</span> {{ v.kode }} {{ v.nama !== v.kode ? v.nama : '' }}</div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
          <span><b>{{ st.pratinjau.jumlah_baris }}</b> baris bernilai</span>
          <span><b>{{ st.pratinjau.jumlah_gelondongan }}</b> gelondongan (1 tahun/paket)</span>
          <span><b>{{ st.pratinjau.jumlah_nol }}</b> komponen bernilai nol dilewati</span>
          <span>Total <b class="angka">{{ formatRp(keSen(st.pratinjau.total)) }}</b></span>
        </div>
        <table class="w-full text-sm">
          <thead class="text-left text-xs text-slate-500"><tr><th class="py-1">Rekening Pemda</th><th class="text-right">Total di file</th><th class="text-right">Terbaca</th><th></th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="r in st.pratinjau.rekonsiliasi" :key="r.kode">
              <td class="py-1">{{ r.kode }} <span class="text-slate-500">{{ r.uraian }}</span></td>
              <td class="angka text-right">{{ formatRp(keSen(r.file)) }}</td>
              <td class="angka text-right">{{ formatRp(keSen(r.terbaca)) }}</td>
              <td class="pl-2" :class="r.cocok ? 'text-teal-700' : 'text-rose-700'">{{ r.cocok ? '✓' : 'selisih' }}</td>
            </tr>
          </tbody>
        </table>
        <details v-if="st.pratinjau.peringatan.length" class="text-sm">
          <summary class="cursor-pointer text-amber-700">{{ st.pratinjau.peringatan.length }} peringatan dari file</summary>
          <ul class="mt-1 list-disc pl-5 text-xs text-slate-600"><li v-for="(p, i) in st.pratinjau.peringatan" :key="i">{{ p }}</li></ul>
        </details>

        <h2 class="pt-2 font-semibold">3. Tahun anggaran tujuan</h2>
        <div class="flex flex-wrap items-end gap-2">
          <label class="text-sm">Tahun<input v-model.number="st.tahun" type="number" class="ml-2 w-24 rounded-lg border border-slate-300 px-2 py-1" /></label>
          <label class="text-sm">Sumber dana
            <select v-model="st.sumber" class="ml-2 rounded-lg border border-slate-300 px-2 py-1"><option v-for="s in sumber_dana" :key="s.id" :value="s.id">{{ s.nama }}</option></select>
          </label>
          <button type="button" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-medium text-white" :disabled="!!st.sibuk" @click="mulai">Mulai impor ke staging</button>
        </div>
      </div>
      <p v-if="st.pratinjau?.galat" class="rounded-md bg-amber-50 p-2 text-sm text-amber-800">{{ st.pratinjau.galat }}</p>
    </section>

    <section class="space-y-2">
      <h2 class="font-semibold">Riwayat impor</h2>
      <p v-if="!impor.length" class="text-sm text-slate-500">Belum ada impor.</p>
      <Link v-for="i in impor" :key="i.uuid" :href="`/impor-rba/${i.uuid}`" class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-3 text-sm ring-1 ring-slate-200 hover:ring-teal-400">
        <span class="font-medium">{{ i.nama_file }}</span>
        <span class="text-slate-500">{{ i.sheet }} · {{ i.kelompok }} · tahun {{ i.tahun }}</span>
        <span class="ml-auto">{{ i.selesai }}/{{ i.jumlah }} baris siap</span>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ label(i.status) }}</span>
      </Link>
    </section>
  </TataLetak>
</template>
