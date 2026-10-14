<script setup>
// Katalog standar harga, usulan item baru dengan pengecekan kecocokan langsung, dan keputusan perencana (PRD 7.4).
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import TataLetak from '../../Components/TataLetak.vue';
import { formatRp, keSen } from '../../Lib/uang.js';
import { kirim } from '../../Lib/http.js';

const props = defineProps({ tahun: Number, daftar_tahun: Array, jumlah_item: Number, usulan_saya: Array, usulan_menunggu: Array, boleh_usul: Boolean, boleh_putuskan: Boolean });

const tab = ref(props.boleh_putuskan && props.usulan_menunggu.length ? 'putuskan' : 'cari');
const pesan = ref(null);
const tanggal = (t) => new Date(t).toLocaleDateString('id-ID', { dateStyle: 'medium' });

// ---- katalog ----
const q = ref('');
const hasilCari = ref([]);
let tCari = null;
watch(q, (v) => {
  clearTimeout(tCari);
  if (v.trim().length < 2) { hasilCari.value = []; return; }
  tCari = setTimeout(async () => {
    hasilCari.value = (await kirim('GET', `/standar-harga/cari?tahun=${props.tahun}&q=${encodeURIComponent(v)}`)).data;
  }, 250);
});

// ---- usulan ----
const form = reactive({ uraian: '', spesifikasi: '', satuan: '', harga: '', kategori: '' });
const kecocokan = ref([]);
let tCocok = null;
watch(() => [form.uraian, form.spesifikasi, form.satuan, form.harga], () => {
  clearTimeout(tCocok);
  if (form.uraian.trim().length < 3 || !form.satuan || !/^\d+$/.test(form.harga)) { kecocokan.value = []; return; }
  tCocok = setTimeout(async () => {
    const p = new URLSearchParams({ tahun: props.tahun, uraian: form.uraian, spesifikasi: form.spesifikasi, satuan: form.satuan, harga: form.harga });
    kecocokan.value = (await kirim('GET', `/standar-harga/cocok?${p}`)).data;
  }, 400);
});
async function ajukan() {
  pesan.value = null;
  try {
    const h = await kirim('POST', '/standar-harga/usulan', { tahun: props.tahun, ...form, spesifikasi: form.spesifikasi || null, kategori: form.kategori || null });
    pesan.value = { ok: true, teks: `Usulan terkirim ke perencana (kecocokan: ${h.kategori_cocok.toLowerCase()}).` };
    Object.assign(form, { uraian: '', spesifikasi: '', satuan: '', harga: '', kategori: '' });
    tab.value = 'saya';
    router.reload({ only: ['usulan_saya', 'usulan_menunggu'] });
  } catch (e) {
    pesan.value = { ok: false, teks: e.message };
  }
}

// ---- keputusan perencana ----
const alasan = reactive({});
async function putuskan(u, keputusan, itemId = null) {
  pesan.value = null;
  try {
    await kirim('POST', `/standar-harga/usulan/${u.uuid}/putuskan`, { keputusan, item_rujukan_id: itemId, alasan: alasan[u.id] || null });
    pesan.value = { ok: true, teks: `Usulan "${u.uraian}" diputuskan: ${keputusan.replace('_', ' ').toLowerCase()}.` };
    router.reload({ only: ['usulan_menunggu', 'usulan_saya', 'jumlah_item', 'menunggu'] });
  } catch (e) {
    pesan.value = { ok: false, teks: e.message };
  }
}
const batchMenunggu = computed(() => {
  const g = {};
  for (const u of props.usulan_menunggu) if (u.batch_uuid) (g[u.batch_uuid] ??= []).push(u);
  return Object.entries(g).filter(([, d]) => d.length > 1);
});
async function sahkanBatch(uuid) {
  pesan.value = null;
  try {
    const h = await kirim('POST', `/standar-harga/usulan/batch/${uuid}/sahkan-memenuhi-syarat`);
    pesan.value = { ok: true, teks: `${h.disahkan} usulan disahkan; ${h.dilewati} dilewati karena mirip item yang ada (putuskan satu per satu).` };
    router.reload();
  } catch (e) {
    pesan.value = { ok: false, teks: e.message };
  }
}
const warnaCocok = { TINGGI: 'bg-rose-50 text-rose-700', SEDANG: 'bg-amber-50 text-amber-800', RENDAH: 'bg-sky-50 text-sky-800', TANPA: 'bg-slate-100 text-slate-600' };
</script>

<template>
  <Head title="Standar Harga" />
  <TataLetak lebar="max-w-4xl">
    <header class="flex flex-wrap items-baseline justify-between gap-2">
      <h1 class="flex items-center gap-2 text-lg font-semibold">Standar harga
        <select :value="tahun" class="rounded-md border border-slate-300 py-0.5 text-base" @change="router.get('/standar-harga', { tahun: $event.target.value })">
          <option v-for="t in daftar_tahun" :key="t" :value="t">{{ t }}</option>
        </select></h1>
      <span class="text-sm text-slate-500">{{ jumlah_item }} item aktif · <a href="/standar-harga/usulan/template" class="text-teal-700 underline">template impor usulan</a></span>
    </header>

    <nav class="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 text-sm">
      <button v-for="[k, l] in [['cari', 'Cari katalog'], ...(boleh_usul ? [['usul', 'Usulkan item'], ['saya', 'Usulan saya']] : []), ...(boleh_putuskan ? [['putuskan', `Menunggu keputusan (${usulan_menunggu.length})`]] : [])]"
        :key="k" type="button" class="whitespace-nowrap rounded-md px-3 py-1.5" :class="tab === k ? 'bg-white font-medium shadow-sm' : 'text-slate-600'" @click="tab = k">{{ l }}</button>
    </nav>
    <p v-if="pesan" class="rounded-lg p-3 text-sm" :class="pesan.ok ? 'bg-teal-50 text-teal-800' : 'bg-rose-50 text-rose-700'">{{ pesan.teks }}</p>

    <section v-if="tab === 'cari'" class="space-y-2">
      <input v-model="q" type="search" placeholder="Ketik uraian atau kode barang…" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
      <div class="divide-y divide-slate-100 rounded-xl bg-white ring-1 ring-slate-200">
        <div v-for="i in hasilCari" :key="i.id" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
          <div><div class="font-medium">{{ i.uraian }} <span v-if="i.tambahan_tengah_tahun" class="text-xs text-amber-700">(tambahan)</span></div>
            <div class="text-xs text-slate-500">{{ i.kode_barang ?? '-' }} · {{ i.spesifikasi }} · {{ i.sumber }}</div></div>
          <div class="angka text-right">{{ formatRp(keSen(i.harga)) }}<div class="text-xs text-slate-500">/ {{ i.satuan }}</div></div>
        </div>
        <p v-if="q.length >= 2 && !hasilCari.length" class="p-4 text-sm text-slate-500">Tidak ditemukan. Ajukan sebagai usulan item baru.</p>
      </div>
    </section>

    <section v-if="tab === 'usul'" class="grid gap-4 md:grid-cols-2">
      <form class="space-y-3 rounded-xl bg-white p-4 ring-1 ring-slate-200" @submit.prevent="ajukan">
        <label class="block text-sm">Uraian<input v-model="form.uraian" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
        <label class="block text-sm">Spesifikasi<input v-model="form.spesifikasi" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="block text-sm">Satuan<input v-model="form.satuan" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
          <label class="block text-sm">Harga (Rp)<input v-model="form.harga" required inputmode="numeric" pattern="\d+" class="angka mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
        </div>
        <label class="block text-sm">Kategori<input v-model="form.kategori" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
        <button type="submit" class="w-full rounded-lg bg-teal-700 py-2 font-medium text-white">Ajukan ke perencana</button>
      </form>
      <div class="space-y-2">
        <h3 class="text-sm font-semibold">Item serupa di katalog</h3>
        <p v-if="!kecocokan.length" class="text-sm text-slate-500">Isi uraian, satuan, dan harga untuk melihat kecocokan.</p>
        <div v-for="c in kecocokan" :key="c.item_id" class="rounded-lg bg-white p-3 text-sm ring-1 ring-slate-200">
          <div class="flex justify-between"><span class="font-medium">{{ c.uraian }}</span>
            <span class="rounded-full px-2 text-xs" :class="warnaCocok[c.kategori]">{{ c.kategori.toLowerCase() }} · {{ Math.round(c.skor * 100) }}%</span></div>
          <div class="text-xs text-slate-500">{{ c.spesifikasi }} · {{ formatRp(keSen(c.harga)) }} / {{ c.satuan }}</div>
        </div>
        <p v-if="kecocokan.some((c) => c.kategori === 'TINGGI')" class="rounded-md bg-rose-50 p-2 text-xs text-rose-700">Ada item yang sangat mirip. Sebaiknya gunakan item tersebut.</p>
      </div>
    </section>

    <section v-if="tab === 'saya'" class="divide-y divide-slate-100 rounded-xl bg-white ring-1 ring-slate-200">
      <p v-if="!usulan_saya.length" class="p-4 text-sm text-slate-500">Belum ada usulan.</p>
      <div v-for="u in usulan_saya" :key="u.id" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
        <div><div class="font-medium">{{ u.uraian }} <span class="text-slate-500">{{ u.spesifikasi }}</span></div>
          <div class="text-xs text-slate-500">{{ tanggal(u.dibuat_pada) }} · {{ u.alasan_keputusan ?? '' }}</div></div>
        <div class="text-right"><div class="angka">{{ formatRp(keSen(u.harga)) }} / {{ u.satuan }}</div>
          <span class="text-xs font-medium">{{ u.status.replaceAll('_', ' ').toLowerCase() }}</span></div>
      </div>
    </section>

    <section v-if="tab === 'putuskan'" class="space-y-3">
      <p v-if="!usulan_menunggu.length" class="rounded-lg bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Tidak ada usulan menunggu.</p>
      <div v-for="[uuid, daftar] in batchMenunggu" :key="uuid" class="flex flex-wrap items-center gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
        <span>{{ daftar.length }} usulan dari satu impor/batch.</span>
        <button type="button" class="rounded-lg bg-amber-600 px-3 py-1 text-white" @click="sahkanBatch(uuid)">Sahkan semua yang tidak mirip item lain</button>
      </div>
      <article v-for="u in usulan_menunggu" :key="u.id" class="space-y-2 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <div class="flex flex-wrap justify-between gap-2">
          <div><div class="font-medium">{{ u.uraian }} <span class="text-slate-500">{{ u.spesifikasi }}</span></div>
            <div class="text-xs text-slate-500">oleh {{ u.pengusul }} · {{ tanggal(u.dibuat_pada) }}</div></div>
          <div class="text-right"><div class="angka font-semibold">{{ formatRp(keSen(u.harga)) }} / {{ u.satuan }}</div>
            <span class="rounded-full px-2 text-xs" :class="warnaCocok[u.kategori_cocok]">kecocokan {{ u.kategori_cocok?.toLowerCase() }}</span></div>
        </div>
        <div v-for="c in u.kecocokan" :key="c.id" class="flex items-center justify-between gap-2 rounded-md bg-slate-50 px-3 py-1.5 text-sm">
          <span>{{ c.uraian }} <span class="text-xs text-slate-500">{{ c.spesifikasi }} · {{ formatRp(keSen(c.harga)) }}/{{ c.satuan }} · {{ Math.round(c.skor * 100) }}%</span></span>
          <button type="button" class="text-xs font-medium text-teal-700 underline" @click="putuskan(u, 'PAKAI_ITEM', c.id)">Pakai item ini</button>
        </div>
        <textarea v-model="alasan[u.id]" rows="2" placeholder="Alasan / catatan keputusan" class="w-full rounded-lg border border-slate-300 p-2 text-sm" />
        <div class="flex gap-2">
          <button type="button" class="flex-1 rounded-lg border border-rose-300 py-2 text-sm text-rose-700" @click="putuskan(u, 'KEMBALIKAN')">Kembalikan</button>
          <button type="button" class="flex-1 rounded-lg bg-teal-700 py-2 text-sm font-medium text-white" @click="putuskan(u, 'SAHKAN')">Sahkan sebagai item BLUD</button>
        </div>
      </article>
    </section>
  </TataLetak>
</template>
