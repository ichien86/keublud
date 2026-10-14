<script setup>
// Permintaan pergeseran terbuka dan pasangan antarunit (PRD 7.8a).
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TataLetak from '../../Components/TataLetak.vue';
import { formatRp, keSen } from '../../Lib/uang.js';
import { kirim } from '../../Lib/http.js';

const props = defineProps({ terbuka: Array, pasangan: Array, boleh_sahkan: Boolean, boleh_bentuk: Boolean });

const kelompok = computed(() => {
  const g = {};
  for (const m of props.terbuka) (g[`${m.kode_jenis} ${m.jenis} · ${m.sumber_dana}`] ??= []).push(m);
  return g;
});

const dipilih = ref([]);
const pilihan = computed(() => props.terbuka.filter((m) => dipilih.value.includes(m.id)));
const total = (arah) => pilihan.value.filter((m) => m.arah === arah).reduce((t, m) => t + keSen(m.nilai), 0n);
const seimbang = computed(() => total('TAMBAH') > 0n && total('TAMBAH') === total('KURANG'));
const pesan = ref(null);

async function bentuk() {
  pesan.value = null;
  try {
    const h = await kirim('POST', '/pergeseran/pasangan', { permintaan_ids: dipilih.value });
    pesan.value = { ok: true, teks: `Pasangan ${h.nomor} terbentuk dan menunggu persetujuan kepala unit.` };
    dipilih.value = [];
    router.reload({ only: ['terbuka', 'pasangan', 'menunggu'] });
  } catch (e) {
    pesan.value = { ok: false, teks: e.message };
  }
}

const warnaStatus = {
  MENUNGGU_UNIT: 'bg-amber-50 text-amber-800', MENUNGGU_PERENCANA: 'bg-sky-50 text-sky-800',
  DISAHKAN: 'bg-teal-50 text-teal-800', DISETUJUI_OTOMATIS: 'bg-teal-50 text-teal-800',
  DITOLAK: 'bg-rose-50 text-rose-700', DITOLAK_VALIDASI: 'bg-rose-50 text-rose-700',
};
const label = (s) => s.replaceAll('_', ' ').toLowerCase();
const tanggal = (t) => (t ? new Date(t).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-');
</script>

<template>
  <Head title="Pergeseran" />
  <TataLetak judul="Pergeseran antarunit">
    <p class="text-sm text-slate-600">
      Pilih permintaan <b>bertambah</b> dan <b>berkurang</b> dari unit berbeda pada jenis belanja dan sumber dana yang sama.
      Permintaan tanpa pasangan ditolak otomatis setelah 5 hari kerja.
    </p>
    <p v-if="pesan" class="rounded-lg p-3 text-sm" :class="pesan.ok ? 'bg-teal-50 text-teal-800' : 'bg-rose-50 text-rose-700'">{{ pesan.teks }}</p>

    <section class="space-y-3">
      <h2 class="font-semibold">Permintaan terbuka</h2>
      <p v-if="!terbuka.length" class="rounded-lg bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Tidak ada permintaan terbuka.</p>
      <div v-for="(daftar, nama) in kelompok" :key="nama" class="rounded-xl bg-white ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-4 py-2 text-sm font-medium">{{ nama }}</div>
        <label v-for="m in daftar" :key="m.id" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-slate-50">
          <input v-if="boleh_bentuk" v-model="dipilih" type="checkbox" :value="m.id" class="rounded border-slate-300" />
          <span class="w-20 font-medium" :class="m.arah === 'TAMBAH' ? 'text-teal-700' : 'text-rose-700'">{{ m.arah === 'TAMBAH' ? '▲ Tambah' : '▼ Kurang' }}</span>
          <span class="flex-1">{{ m.unit }} <span class="text-slate-400">· {{ m.nomor }}</span></span>
          <span class="hidden text-xs text-slate-500 sm:inline">batas {{ tanggal(m.batas_pasangan) }}</span>
          <span class="angka w-36 text-right">{{ formatRp(keSen(m.nilai)) }}</span>
        </label>
      </div>
      <div v-if="boleh_bentuk && dipilih.length" class="sticky bottom-20 flex flex-wrap items-center gap-3 rounded-xl bg-slate-900 p-3 text-sm text-white sm:bottom-4">
        <span>Tambah <b class="angka">{{ formatRp(total('TAMBAH')) }}</b></span>
        <span>Kurang <b class="angka">{{ formatRp(total('KURANG')) }}</b></span>
        <span v-if="!seimbang" class="text-amber-300">Belum seimbang</span>
        <button type="button" :disabled="!seimbang" class="ml-auto rounded-lg bg-teal-500 px-3 py-1.5 font-medium disabled:opacity-40" @click="bentuk">Bentuk pasangan</button>
      </div>
    </section>

    <section class="space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="font-semibold">Pasangan</h2>
        <Link href="/persetujuan" class="text-sm text-teal-700 underline">Putuskan di kotak masuk →</Link>
      </div>
      <p v-if="!pasangan.length" class="rounded-lg bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Belum ada pasangan.</p>
      <article v-for="p in pasangan" :key="p.id" class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-medium">{{ p.nomor }}</span>
          <span class="rounded-full px-2 py-0.5 text-xs" :class="warnaStatus[p.status]">{{ label(p.status) }}</span>
          <span v-if="p.memuat_di_atas_standar" class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800">di atas standar</span>
          <span class="angka ml-auto font-semibold">{{ formatRp(keSen(p.total_tambah)) }}</span>
        </div>
        <div class="text-xs text-slate-500">{{ p.kode_jenis }} {{ p.jenis }} · {{ p.sumber_dana }}
          <template v-if="p.batas_pengesahan"> · batas pengesahan {{ tanggal(p.batas_pengesahan) }}</template></div>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
          <li v-for="a in p.anggota" :key="a.permintaan_id" class="flex justify-between py-1">
            <span><span :class="a.arah === 'TAMBAH' ? 'text-teal-700' : 'text-rose-700'">{{ a.arah === 'TAMBAH' ? '▲' : '▼' }}</span> {{ a.unit }}
              <span class="text-xs text-slate-500">· unit {{ a.persetujuan_unit.toLowerCase() }}</span></span>
            <span class="angka">{{ formatRp(keSen(a.nilai)) }}</span>
          </li>
        </ul>
        <p v-if="p.alasan" class="mt-2 text-xs text-slate-500">{{ p.alasan }}</p>
      </article>
    </section>
  </TataLetak>
</template>
