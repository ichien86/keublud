<script setup>
// Halaman edit rincian (PRD 7.7, 8.4; TDD 6.3).
// Draf disimpan otomatis ke server; status pagu dihitung langsung di klien dan dikonfirmasi server.
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import IndikatorPagu from '../../Components/IndikatorPagu.vue';
import TataLetak from '../../Components/TataLetak.vue';
import DialogPerluPersetujuan from '../../Components/DialogPerluPersetujuan.vue';
import { formatRp, kaliVolume, keSen } from '../../Lib/uang.js';
import { kirim } from '../../Lib/http.js';

const props = defineProps({
  pagu: Object,
  paket: Object,
  rincian: Array,
  draf: Array,
  ringkasan: Object, // deferred
});

// ---------- status baris ----------
let kunciBaru = 0;
function dariRincian(r) {
  return {
    kunci: `r${r.id}`, rincian_id: r.id, aksi: null, item_id: r.item_id, item_label: r.uraian, rekening_blud_id: r.rekening_blud_id,
    uraian: r.uraian, satuan: r.satuan, volume: String(r.volume), harga: String(r.harga), harga_standar: String(r.harga_standar),
    asli: { volume: String(r.volume), harga: String(r.harga), item_id: r.item_id, rekening_blud_id: r.rekening_blud_id },
    pemakaian: keSen(r.realisasi) + keSen(r.komitmen), terkunci: r.terkunci_paket_id !== null || r.terkunci_transaksi,
    row_version: r.row_version, bukti: false,
  };
}

const baris = reactive(props.rincian.map(dariRincian));

// Terapkan draf yang tersimpan sebelumnya.
for (const d of props.draf) {
  if (d.aksi === 'TAMBAH') {
    baris.push({ kunci: `n${++kunciBaru}`, rincian_id: null, aksi: 'TAMBAH', pemakaian: 0n, terkunci: false, bukti: false,
      item_label: d.sesudah.uraian, ...d.sesudah, volume: String(d.sesudah.volume), harga: String(d.sesudah.harga) });
    continue;
  }
  const b = baris.find((x) => x.rincian_id === d.rincian_id);
  if (!b) continue;
  b.aksi = d.aksi;
  b.row_version = d.rincian_row_version ?? b.row_version;
  if (d.aksi === 'UBAH') Object.assign(b, { ...d.sesudah, volume: String(d.sesudah.volume), harga: String(d.sesudah.harga), item_label: d.sesudah.uraian });
}

const nilaiBaris = (b) => (b.aksi === 'HAPUS' ? 0n : kaliVolume(b.harga, b.volume));
const diubah = (b) => b.aksi === 'TAMBAH' || b.aksi === 'HAPUS'
  || (b.volume !== b.asli?.volume || b.harga !== b.asli?.harga || b.item_id !== b.asli?.item_id || b.rekening_blud_id !== b.asli?.rekening_blud_id);

const paguSen = computed(() => keSen(props.pagu.pagu_berlaku));
const totalSen = computed(() => baris.reduce((t, b) => t + nilaiBaris(b), 0n));
const statusPagu = computed(() => (totalSen.value === paguSen.value ? 'SEIMBANG' : 'BERUBAH'));
const adaPerubahan = computed(() => baris.some(diubah));
const diAtasStandar = computed(() => baris.some((b) => b.aksi !== 'HAPUS' && keSen(b.harga) > keSen(b.harga_standar)));

// ---------- autosave draf ----------
const galatServer = ref([]);
const statusSimpan = ref('');
let pewaktu = null;

function muatanDraf() {
  return baris.filter(diubah).map((b) => ({
    aksi: b.aksi === 'TAMBAH' || b.aksi === 'HAPUS' ? b.aksi : 'UBAH',
    rincian_id: b.aksi === 'TAMBAH' ? null : b.rincian_id,
    item_id: b.item_id, rekening_blud_id: b.rekening_blud_id, volume: b.volume, harga: b.harga, uraian: b.uraian,
  }));
}

async function simpanDraf() {
  statusSimpan.value = 'Menyimpan draf…';
  try {
    const h = await kirim('PUT', `/revisi/${props.paket.uuid}/baris`, { baris: muatanDraf() });
    galatServer.value = h.pelanggaran ?? [];
    statusSimpan.value = `Draf tersimpan ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
  } catch (e) {
    statusSimpan.value = e.message;
  }
}

watch(baris, () => { clearTimeout(pewaktu); pewaktu = setTimeout(simpanDraf, 700); }, { deep: true });

// ---------- edit baris ----------
function ubahBaris(b) { if (!b.terkunci && b.aksi === null) b.aksi = 'UBAH'; }
function hapusBaris(b) {
  if (b.terkunci) return;
  if (b.aksi === 'TAMBAH') { baris.splice(baris.indexOf(b), 1); return; }
  b.aksi = b.aksi === 'HAPUS' ? null : 'HAPUS';
}

const pencarian = reactive({ q: '', hasil: [], rekening: [] });
async function cariItem() {
  if (pencarian.q.length < 2) { pencarian.hasil = []; return; }
  pencarian.hasil = (await kirim('GET', `/standar-harga/cari?tahun=${props.pagu.tahun}&q=${encodeURIComponent(pencarian.q)}`)).data;
}
let pewaktuCari = null;
watch(() => pencarian.q, () => { clearTimeout(pewaktuCari); pewaktuCari = setTimeout(cariItem, 350); });

onMounted(async () => {
  pencarian.rekening = (await kirim('GET', `/kodefikasi/rekening?jenis_id=${props.pagu.jenis_id ?? ''}`).catch(() => ({ data: [] }))).data;
});

function tambahItem(item) {
  baris.push({
    kunci: `n${++kunciBaru}`, rincian_id: null, aksi: 'TAMBAH', item_id: item.id, item_label: item.uraian,
    rekening_blud_id: pencarian.rekening[0]?.id ?? null, uraian: item.uraian, satuan: item.satuan,
    volume: '1', harga: item.harga, harga_standar: item.harga, pemakaian: 0n, terkunci: false, bukti: false,
  });
  pencarian.q = '';
  pencarian.hasil = [];
}

async function unggahBukti(b, ev) {
  const file = ev.target.files?.[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('file', file);
  fd.append('item_id', b.item_id);
  if (b.rincian_id) fd.append('rincian_id', b.rincian_id);
  const xsrf = decodeURIComponent(document.cookie.split('; ').find((x) => x.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
  const res = await fetch(`/revisi/${props.paket.uuid}/bukti`, { method: 'POST', body: fd, headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' } });
  b.bukti = res.ok;
}

// ---------- simpan & kirim ----------
const dialog = reactive({ terbuka: false, mode: 'simpan', pesan: '', memproses: false, tujuan: null });
const notifikasi = ref(null);

async function simpan() {
  clearTimeout(pewaktu);
  await simpanDraf();
  try {
    const h = await kirim('POST', `/revisi/${props.paket.uuid}/simpan`);
    sudahDikirim = true;
    notifikasi.value = h.status === 'BERLAKU'
      ? { jenis: 'sukses', teks: `Revisi ${h.nomor} berlaku.` }
      : { jenis: 'info', teks: `Revisi ${h.nomor} menunggu persetujuan perencana. Rincian lama tetap berlaku.` };
    router.reload();
  } catch (e) {
    if (e.status === 409 && e.isi?.kode === 'PERLU_PERSETUJUAN') {
      Object.assign(dialog, { terbuka: true, mode: 'simpan', pesan: e.isi.pesan, tujuan: null });
    } else {
      notifikasi.value = { jenis: 'galat', teks: e.message };
    }
  }
}

async function kirimPergeseran() {
  dialog.memproses = true;
  try {
    const h = await kirim('POST', `/revisi/${props.paket.uuid}/kirim-pergeseran`);
    sudahDikirim = true;
    const batas = new Date(h.permintaan.batas_pasangan).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
    notifikasi.value = { jenis: 'info', teks: `Permintaan ${h.permintaan.nomor} terkirim. Batas pasangan: ${batas} WIB. Rincian dalam paket ini dikunci sampai diputuskan.`, editLain: true };
    dialog.terbuka = false;
    if (dialog.tujuan) { router.visit(dialog.tujuan); return; }
    router.reload();
  } catch (e) {
    notifikasi.value = { jenis: 'galat', teks: e.message };
    dialog.terbuka = false;
  } finally {
    dialog.memproses = false;
  }
}

async function buang() {
  await kirim('POST', `/revisi/${props.paket.uuid}/batalkan`).catch(() => {});
  sudahDikirim = true;
  dialog.terbuka = false;
  router.visit(dialog.tujuan ?? window.location.href);
}

// ---------- peringatan keluar halaman (FR-095) ----------
let sudahDikirim = false;
const perluPeringatan = () => !sudahDikirim && adaPerubahan.value && statusPagu.value !== 'SEIMBANG';

function sebelumUnload(e) {
  if (perluPeringatan()) { e.preventDefault(); e.returnValue = ''; }
}
const lepasRouter = router.on('before', (event) => {
  const tujuan = event.detail.visit.url.href;
  if (perluPeringatan() && !tujuan.includes(window.location.pathname)) {
    Object.assign(dialog, {
      terbuka: true, mode: 'keluar', tujuan,
      pesan: 'Ada perubahan yang mengubah pagu jenis belanja dan memerlukan persetujuan. Draf tetap tersimpan, tetapi belum diajukan.',
    });
    return false;
  }
  return true;
});
onMounted(() => window.addEventListener('beforeunload', sebelumUnload));
onBeforeUnmount(() => { window.removeEventListener('beforeunload', sebelumUnload); lepasRouter(); });
</script>

<template>
  <Head :title="`Edit rincian — ${pagu.unit}`" />
  <TataLetak>
    <header>
      <h1 class="text-lg font-semibold">{{ pagu.unit }}</h1>
      <p class="text-sm text-slate-600">{{ pagu.jenis?.kode }} {{ pagu.jenis?.uraian }} · Sumber: {{ pagu.sumber_dana }} · Tahun {{ pagu.tahun }}</p>
    </header>

    <IndikatorPagu :pagu-sen="paguSen" :total-sen="totalSen" :berlaku-langsung="pagu.berlaku_langsung"
      :nama-jenis="pagu.jenis?.uraian ?? ''" :di-atas-standar="diAtasStandar" />

    <div v-if="notifikasi" class="rounded-md border p-3 text-sm"
      :class="{ 'border-emerald-300 bg-emerald-50': notifikasi.jenis === 'sukses', 'border-sky-300 bg-sky-50': notifikasi.jenis === 'info', 'border-rose-300 bg-rose-50': notifikasi.jenis === 'galat' }">
      {{ notifikasi.teks }}
      <button v-if="notifikasi.editLain" class="ml-2 underline" @click="router.reload()">Edit rincian lain</button>
    </div>

    <ul v-if="galatServer.length" class="rounded-md border border-rose-300 bg-rose-50 p-3 text-sm text-rose-800">
      <li v-for="g in galatServer" :key="g.baris + g.kode">{{ g.pesan }}</li>
    </ul>

    <section class="flex flex-wrap items-center gap-2">
      <div class="relative w-full max-w-md">
        <label class="sr-only" for="cari-item">Tambah rincian dari standar harga</label>
        <input id="cari-item" v-model="pencarian.q" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="+ Tambah rincian: cari item standar harga…" />
        <ul v-if="pencarian.hasil.length" class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-md border bg-white shadow">
          <li v-for="i in pencarian.hasil" :key="i.id">
            <button class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50" @click="tambahItem(i)">
              {{ i.uraian }} <span class="text-slate-500">· {{ i.satuan }} · {{ formatRp(keSen(i.harga)) }}</span>
              <span v-if="i.tambahan_tengah_tahun" class="ml-1 rounded bg-slate-100 px-1 text-xs">tambahan tengah tahun</span>
            </button>
          </li>
        </ul>
        <p v-else-if="pencarian.q.length >= 2" class="mt-1 text-xs text-slate-600">
          Tidak ditemukan? <a class="underline" :href="`/standar-harga/usulan/baru?konteks=REVISI&kembali=${encodeURIComponent($page.url)}`">Usulkan standar harga baru</a>
        </p>
      </div>
      <span class="ml-auto text-xs text-slate-500">{{ statusSimpan }}</span>
    </section>

    <div class="overflow-x-auto rounded-lg border bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
          <tr><th class="px-3 py-2">Uraian / item</th><th class="px-3 py-2">Volume</th><th class="px-3 py-2">Harga satuan</th>
            <th class="px-3 py-2 text-right">Nilai</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr>
        </thead>
        <tbody>
          <tr v-for="b in baris" :key="b.kunci" class="border-t"
            :class="{ 'bg-slate-100 text-slate-500': b.terkunci, 'line-through opacity-60': b.aksi === 'HAPUS', 'bg-teal-50/40': b.aksi === 'TAMBAH' }">
            <td class="px-3 py-2">
              <div class="font-medium">{{ b.item_label }}</div>
              <div class="text-xs text-slate-500">{{ b.satuan }} · standar {{ formatRp(keSen(b.harga_standar)) }}</div>
            </td>
            <td class="px-3 py-2">
              <input v-model="b.volume" :disabled="b.terkunci || b.aksi === 'HAPUS'" inputmode="decimal"
                class="angka w-24 rounded border border-slate-300 px-2 py-1" @input="ubahBaris(b)" />
            </td>
            <td class="px-3 py-2">
              <input v-model="b.harga" :disabled="b.terkunci || b.aksi === 'HAPUS'" inputmode="decimal"
                class="angka w-36 rounded border border-slate-300 px-2 py-1" @input="ubahBaris(b)" />
              <div v-if="keSen(b.harga) > keSen(b.harga_standar) && b.aksi !== 'HAPUS'" class="mt-1 text-xs text-amber-800">
                ▲ di atas standar — lampirkan bukti harga pasar
                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block text-xs" @change="unggahBukti(b, $event)" />
                <span v-if="b.bukti" class="text-emerald-700">bukti terunggah</span>
              </div>
            </td>
            <td class="angka px-3 py-2 text-right">{{ formatRp(nilaiBaris(b)) }}</td>
            <td class="px-3 py-2 text-xs">
              <span v-if="b.terkunci">🔒 terkunci</span>
              <span v-else-if="b.aksi === 'TAMBAH'">baru</span>
              <span v-else-if="b.aksi === 'HAPUS'">dihapus</span>
              <span v-else-if="diubah(b)">diubah</span>
              <span v-if="b.pemakaian > 0n" class="block text-slate-500">terpakai {{ formatRp(b.pemakaian) }}</span>
            </td>
            <td class="px-3 py-2 text-right">
              <button v-if="!b.terkunci && b.pemakaian === 0n" class="text-xs text-rose-700 hover:underline" @click="hapusBaris(b)">
                {{ b.aksi === 'HAPUS' ? 'Urungkan' : 'Hapus' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <footer class="flex justify-end gap-2">
      <button class="rounded-md border border-slate-300 px-4 py-2 text-sm" :disabled="!adaPerubahan" @click="buang">Batalkan</button>
      <button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-60" :disabled="!adaPerubahan" @click="simpan">Simpan</button>
    </footer>

    <DialogPerluPersetujuan :terbuka="dialog.terbuka" :mode="dialog.mode" :pesan="dialog.pesan" :memproses="dialog.memproses"
      @kirim="kirimPergeseran" @lanjut="dialog.terbuka = false; dialog.tujuan = null" @buang="buang" />
  </TataLetak>
</template>
