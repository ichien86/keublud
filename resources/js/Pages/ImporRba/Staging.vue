<script setup>
// Staging impor RBA: lengkapi kodefikasi, petakan label unit, rincikan baris ke item standar harga, rekonsiliasi, bentuk RBA.
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import TataLetak from '../../Components/TataLetak.vue';
import { formatRp, kaliVolume, keSen } from '../../Lib/uang.js';
import { kirim } from '../../Lib/http.js';

const props = defineProps({
  impor: Object, baris: Array, rekonsiliasi: Array, label: Array, unit: Array,
  rekening_hilang: Number, boleh_kodefikasi: Boolean, boleh_usul: Boolean,
});

const STATUS = {
  GAGAL: ['Rekening belum dikenal', 'bg-rose-50 text-rose-700'],
  PERLU_DIRINCI: ['Perlu dirinci', 'bg-amber-50 text-amber-800'],
  PERLU_UNIT: ['Perlu unit', 'bg-sky-50 text-sky-800'],
  SIAP: ['Siap', 'bg-teal-50 text-teal-800'],
  DIABAIKAN: ['Diabaikan', 'bg-slate-100 text-slate-600'],
};
const filter = ref('BELUM');
const hitung = computed(() => {
  const h = { SEMUA: props.baris.length, BELUM: 0 };
  for (const b of props.baris) {
    h[b.status] = (h[b.status] ?? 0) + 1;
    if (!['SIAP', 'DIABAIKAN'].includes(b.status)) h.BELUM++;
  }
  return h;
});
const tampil = computed(() => props.baris.filter((b) => filter.value === 'SEMUA' || (filter.value === 'BELUM' ? !['SIAP', 'DIABAIKAN'].includes(b.status) : b.status === filter.value)));
const selesai = computed(() => props.impor.status !== 'STAGING');
const rekonCocok = computed(() => props.rekonsiliasi.every((r) => r.cocok));
const bolehBentuk = computed(() => !selesai.value && hitung.value.BELUM === 0 && rekonCocok.value);

const pesan = ref(null);
const sibuk = ref(false);
async function aksi(metode, url, data, sukses) {
  sibuk.value = true;
  pesan.value = null;
  try {
    const h = await kirim(metode, url, data);
    pesan.value = { ok: true, teks: typeof sukses === 'function' ? sukses(h) : sukses };
    router.reload({ preserveScroll: true });
    return h;
  } catch (e) {
    pesan.value = { ok: false, teks: e.message };
  } finally {
    sibuk.value = false;
  }
}
const u = `/impor-rba/${props.impor.uuid}`;
const kodefikasi = () => aksi('POST', `${u}/kodefikasi`, {}, (h) => `Ditambahkan ${h.pemda} rekening Pemda, ${h.blud} rekening BLUD, ${h.pemetaan} pemetaan.`);
const usulan = () => aksi('POST', `${u}/ajukan-usulan`, {}, (h) => `${h.diajukan} baris diajukan sebagai usulan item standar harga. Perencana lain memutuskannya di halaman Standar Harga atau Persetujuan.`);
const unitMassal = ref('');
const setUnitMassal = () => aksi('POST', `${u}/unit-massal`, { unit_id: unitMassal.value }, (h) => `${h.jumlah} baris diberi unit.`);
const petaLabel = reactive({});
const simpanLabel = (l) => {
  const v = petaLabel[l.label];
  aksi('POST', `${u}/label`, v === 'BARU' ? { label: l.label, unit_baru: l.label } : { label: l.label, unit_id: v }, `Label "${l.label}" dipetakan.`);
};
const bentuk = () => aksi('POST', `${u}/bentuk`, {}, (h) => `RBA draf terbentuk (id ${h.rba_id}). Lanjutkan pengajuan dari halaman RBA.`);
const batalkan = () => confirmBatal.value && aksi('POST', `${u}/batalkan`, {}, 'Impor dibatalkan.');
const confirmBatal = ref(false);

// ---- editor rincian per baris ----
const buka = ref(null);
const ed = reactive({ item: [], unit_id: null, q: '', hasil: [], alasan: '' });
function bukaEditor(b) {
  if (buka.value === b.id) { buka.value = null; return; }
  buka.value = b.id;
  Object.assign(ed, {
    item: b.item.map((i) => ({ item_id: i.item_id, uraian: i.uraian, satuan: i.satuan, harga_standar: String(i.harga_standar), volume: String(Number(i.volume)), harga: String(i.harga), unit_id: i.unit_id })),
    unit_id: b.unit_id, q: '', hasil: [], alasan: b.alasan_abaikan ?? '',
  });
}
let tCari = null;
function cari() {
  clearTimeout(tCari);
  if (ed.q.trim().length < 2) { ed.hasil = []; return; }
  tCari = setTimeout(async () => {
    ed.hasil = (await kirim('GET', `/standar-harga/cari?tahun=${props.impor.tahun}&q=${encodeURIComponent(ed.q)}`)).data;
  }, 250);
}
function tambahItem(i, b) {
  const sisa = keSen(b.nilai_sumber) - totalEd.value;
  const vol = sisa > 0n && keSen(i.harga) > 0n && sisa % keSen(i.harga) === 0n ? String(sisa / keSen(i.harga)) : '1';
  ed.item.push({ item_id: i.id, uraian: i.uraian, satuan: i.satuan, harga_standar: i.harga, harga: i.harga, volume: vol, unit_id: null });
  ed.q = '';
  ed.hasil = [];
}
const totalEd = computed(() => ed.item.reduce((t, i) => t + kaliVolume(i.harga, i.volume), 0n));
const simpanItem = (b) => aksi('PUT', `/impor-rba/baris/${b.id}/item`, {
  unit_id: ed.unit_id || null,
  item: ed.item.map((i) => ({ item_id: i.item_id, volume: i.volume, harga: i.harga, unit_id: i.unit_id || null })),
}, 'Rincian baris disimpan.');
const abaikan = (b, ya) => aksi('POST', `/impor-rba/baris/${b.id}/abaikan`, { alasan: ya ? ed.alasan : null }, ya ? 'Baris diabaikan.' : 'Baris diaktifkan kembali.');
</script>

<template>
  <Head title="Staging impor RBA" />
  <TataLetak lebar="max-w-6xl">
    <header class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <Link href="/impor-rba" class="text-xs text-teal-700">← Impor RBA</Link>
        <h1 class="text-lg font-semibold">{{ impor.nama_file }}</h1>
        <p class="text-sm text-slate-600">Sheet <b>{{ impor.sheet }}</b> · {{ impor.kelompok }} · tahun tujuan {{ impor.tahun }} · {{ impor.sumber_dana }}</p>
      </div>
      <div v-if="selesai" class="rounded-lg bg-teal-50 px-3 py-2 text-sm text-teal-800">
        Impor {{ impor.status.toLowerCase() }}<template v-if="impor.rba_id"> — <Link href="/rba" class="underline">buka RBA</Link></template>
      </div>
    </header>

    <p v-if="pesan" class="rounded-lg p-3 text-sm" :class="pesan.ok ? 'bg-teal-50 text-teal-800' : 'bg-rose-50 text-rose-700'">{{ pesan.teks }}</p>

    <section v-if="!selesai" class="grid gap-3 lg:grid-cols-2">
      <div class="space-y-2 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <h2 class="font-semibold">Langkah</h2>
        <ol class="space-y-3 text-sm">
          <li>
            <b>1. Kodefikasi.</b>
            <span v-if="!rekening_hilang" class="text-teal-700">✓ semua rekening BLUD dikenal</span>
            <template v-else>
              <span class="text-rose-700">{{ rekening_hilang }} kode rekening BLUD belum ada di bagan / belum dipetakan.</span>
              <button v-if="boleh_kodefikasi" type="button" class="ml-2 rounded-md bg-teal-700 px-2 py-1 text-xs text-white" :disabled="sibuk" @click="kodefikasi">Lengkapi dari file</button>
              <span v-else class="block text-xs text-slate-500">Minta administrator membuka halaman ini dan menekan "Lengkapi dari file".</span>
            </template>
          </li>
          <li>
            <b>2. Unit kerja.</b> Petakan label penanggung jawab di file ke unit.
            <div class="mt-1 space-y-1">
              <div v-for="l in label" :key="l.label" class="flex flex-wrap items-center gap-2">
                <span class="w-40 truncate">{{ l.label }} <span class="text-xs text-slate-400">({{ l.jumlah }})</span></span>
                <span v-if="l.unit_id" class="text-teal-700">→ {{ l.unit }}</span>
                <template v-else>
                  <select v-model="petaLabel[l.label]" class="rounded-md border border-slate-300 px-1 py-0.5 text-xs">
                    <option :value="undefined" disabled>pilih unit…</option>
                    <option value="BARU">+ buat unit "{{ l.label }}"</option>
                    <option v-for="x in unit" :key="x.id" :value="x.id">{{ x.nama }}</option>
                  </select>
                  <button type="button" class="rounded-md border border-teal-700 px-2 text-xs text-teal-800" :disabled="!petaLabel[l.label] || sibuk" @click="simpanLabel(l)">Simpan</button>
                </template>
              </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
              Baris tanpa unit →
              <select v-model="unitMassal" class="rounded-md border border-slate-300 px-1 py-0.5"><option value="" disabled>pilih unit…</option><option v-for="x in unit" :key="x.id" :value="x.id">{{ x.nama }}</option></select>
              <button type="button" class="rounded-md border border-teal-700 px-2 text-teal-800" :disabled="!unitMassal || sibuk" @click="setUnitMassal">Terapkan</button>
            </div>
          </li>
          <li>
            <b>3. Rincian ke standar harga.</b> Rincikan per baris (tabel di bawah), atau ajukan sekaligus sebagai usulan item standar BLUD
            (baris "1 tahun/paket" menjadi item bersatuan paket).
            <button v-if="boleh_usul" type="button" class="mt-1 block rounded-md bg-amber-600 px-2 py-1 text-xs text-white" :disabled="sibuk" @click="usulan">Ajukan baris belum dirinci sebagai usulan item</button>
          </li>
          <li>
            <b>4. Bentuk RBA draf.</b>
            <button type="button" class="ml-2 rounded-md bg-teal-700 px-3 py-1 text-xs font-medium text-white disabled:opacity-40" :disabled="!bolehBentuk || sibuk" @click="bentuk">Bentuk RBA</button>
            <span v-if="!bolehBentuk" class="block text-xs text-slate-500">Aktif bila semua baris siap/diabaikan dan rekonsiliasi cocok.</span>
          </li>
        </ol>
      </div>

      <div class="space-y-2 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <h2 class="font-semibold">Rekonsiliasi per rekening Pemda</h2>
        <table class="w-full text-xs">
          <thead class="text-left text-slate-500"><tr><th>Rekening</th><th class="text-right">File</th><th class="text-right">Dirinci</th><th class="text-right">Diabaikan</th><th></th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="r in rekonsiliasi" :key="r.kode">
              <td class="py-1">{{ r.kode }}<div class="text-xs text-slate-500">{{ r.uraian }}</div></td>
              <td class="angka whitespace-nowrap pl-2 text-right">{{ formatRp(keSen(r.file)) }}</td>
              <td class="angka whitespace-nowrap pl-2 text-right">{{ formatRp(keSen(r.staging)) }}</td>
              <td class="angka whitespace-nowrap pl-2 text-right">{{ formatRp(keSen(r.diabaikan)) }}</td>
              <td class="pl-1" :class="r.cocok ? 'text-teal-700' : 'text-amber-700'">{{ r.cocok ? '✓' : '…' }}</td>
            </tr>
          </tbody>
        </table>
        <details v-if="impor.peringatan.length" class="text-sm">
          <summary class="cursor-pointer text-amber-700">{{ impor.peringatan.length }} peringatan dari file</summary>
          <ul class="mt-1 list-disc pl-5 text-xs text-slate-600"><li v-for="(p, i) in impor.peringatan" :key="i">{{ p }}</li></ul>
        </details>
        <div class="pt-2 text-xs">
          <label class="flex items-center gap-1"><input v-model="confirmBatal" type="checkbox" /> saya yakin membatalkan impor ini</label>
          <button type="button" class="mt-1 rounded-md border border-rose-300 px-2 py-0.5 text-rose-700 disabled:opacity-40" :disabled="!confirmBatal" @click="batalkan">Batalkan impor</button>
        </div>
      </div>
    </section>

    <nav class="flex flex-wrap gap-1 text-sm">
      <button v-for="k in ['BELUM', 'SEMUA', 'GAGAL', 'PERLU_DIRINCI', 'PERLU_UNIT', 'SIAP', 'DIABAIKAN']" :key="k" type="button"
        class="rounded-full px-3 py-1" :class="filter === k ? 'bg-slate-900 text-white' : 'bg-white ring-1 ring-slate-200'" @click="filter = k">
        {{ k === 'BELUM' ? 'Belum selesai' : k === 'SEMUA' ? 'Semua' : STATUS[k][0] }} <span class="opacity-70">{{ hitung[k] ?? 0 }}</span>
      </button>
    </nav>

    <section class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
      <p v-if="!tampil.length" class="p-4 text-sm text-slate-500">Tidak ada baris pada filter ini.</p>
      <article v-for="b in tampil" :key="b.id">
        <button type="button" class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-left text-sm hover:bg-slate-50" @click="bukaEditor(b)">
          <span class="w-10 text-xs text-slate-400">#{{ b.baris_sumber }}</span>
          <span class="min-w-0 flex-1">
            <span class="font-medium">{{ b.komponen }}</span><span v-if="b.rincian" class="text-slate-600"> — {{ b.rincian }}</span>
            <span class="block text-xs text-slate-500">{{ b.kode_blud }} · {{ b.volume ? Number(b.volume) : '' }} {{ b.satuan }} · {{ b.unit ?? b.label_unit ?? 'tanpa unit' }}
              <template v-if="b.status_usulan"> · usulan {{ b.status_usulan.toLowerCase().replaceAll('_', ' ') }}</template></span>
          </span>
          <span class="angka font-medium">{{ formatRp(keSen(b.nilai_sumber)) }}</span>
          <span class="rounded-full px-2 py-0.5 text-xs" :class="STATUS[b.status][1]">{{ STATUS[b.status][0] }}</span>
        </button>

        <div v-if="buka === b.id" class="space-y-3 border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm">
          <p v-for="(p, i) in b.peringatan" :key="'p' + i" class="rounded-md bg-amber-50 p-2 text-xs text-amber-800">⚠ {{ p }}</p>
          <p v-if="b.petunjuk.length" class="text-xs text-slate-600">Sub-komponen di file: {{ b.petunjuk.join(' · ') }}</p>

          <template v-if="!selesai && b.status !== 'DIABAIKAN'">
            <table class="w-full text-sm">
              <thead class="text-left text-xs text-slate-500"><tr><th>Item standar</th><th class="w-28">Volume</th><th class="w-36">Harga</th><th class="text-right">Nilai</th><th></th></tr></thead>
              <tbody>
                <tr v-for="(i, n) in ed.item" :key="n">
                  <td class="py-1">{{ i.uraian }} <span class="text-xs text-slate-500">/ {{ i.satuan }} · standar {{ formatRp(keSen(i.harga_standar)) }}</span></td>
                  <td><input v-model="i.volume" class="w-24 rounded border border-slate-300 px-1 py-0.5" inputmode="decimal" /></td>
                  <td><input v-model="i.harga" class="w-32 rounded border border-slate-300 px-1 py-0.5" inputmode="numeric"
                    :class="keSen(i.harga) > keSen(i.harga_standar) ? 'border-rose-500 bg-rose-50' : ''" /></td>
                  <td class="angka text-right">{{ formatRp(kaliVolume(i.harga, i.volume)) }}</td>
                  <td class="pl-2"><button type="button" class="text-xs text-rose-700" @click="ed.item.splice(n, 1)">hapus</button></td>
                </tr>
              </tbody>
              <tfoot class="text-xs">
                <tr><td colspan="3" class="pt-1 text-right">Jumlah rincian / nilai di file</td>
                  <td class="angka pt-1 text-right font-semibold" :class="totalEd === keSen(b.nilai_sumber) ? 'text-teal-700' : 'text-amber-700'">
                    {{ formatRp(totalEd) }} / {{ formatRp(keSen(b.nilai_sumber)) }}</td></tr>
              </tfoot>
            </table>
            <div class="relative">
              <input v-model="ed.q" placeholder="+ cari item standar harga…" class="w-full rounded-lg border border-slate-300 px-2 py-1.5" @input="cari" />
              <div v-if="ed.hasil.length" class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                <button v-for="i in ed.hasil" :key="i.id" type="button" class="flex w-full justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-teal-50" @click="tambahItem(i, b)">
                  <span>{{ i.uraian }} <span class="text-xs text-slate-500">{{ i.spesifikasi }}</span></span>
                  <span class="angka shrink-0 text-xs">{{ formatRp(keSen(i.harga)) }} / {{ i.satuan }}</span>
                </button>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs">Unit baris
                <select v-model="ed.unit_id" class="ml-1 rounded border border-slate-300 px-1 py-0.5"><option :value="null">—</option><option v-for="x in unit" :key="x.id" :value="x.id">{{ x.nama }}</option></select>
              </label>
              <button type="button" class="rounded-md bg-teal-700 px-3 py-1 text-xs text-white" :disabled="sibuk" @click="simpanItem(b)">Simpan rincian</button>
              <span class="ml-auto flex items-center gap-1 text-xs">
                <input v-model="ed.alasan" placeholder="alasan mengabaikan (min. 10 karakter)" class="w-64 rounded border border-slate-300 px-1 py-0.5" />
                <button type="button" class="rounded-md border border-slate-400 px-2 py-0.5" :disabled="ed.alasan.trim().length < 10 || sibuk" @click="abaikan(b, true)">Abaikan</button>
              </span>
            </div>
          </template>
          <template v-else-if="b.status === 'DIABAIKAN'">
            <p class="text-xs">Diabaikan: {{ b.alasan_abaikan }}</p>
            <button v-if="!selesai" type="button" class="rounded-md border border-teal-700 px-2 py-0.5 text-xs text-teal-800" @click="abaikan(b, false)">Aktifkan kembali</button>
          </template>
          <ul v-else class="text-xs">
            <li v-for="(i, n) in b.item" :key="n">{{ i.uraian }} — {{ Number(i.volume) }} {{ i.satuan }} × {{ formatRp(keSen(i.harga)) }}</li>
          </ul>
        </div>
      </article>
    </section>
  </TataLetak>
</template>
