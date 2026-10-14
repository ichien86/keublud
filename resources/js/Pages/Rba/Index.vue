<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import TataLetak from '../../Components/TataLetak.vue';
import { formatRp, keSen } from '../../Lib/uang.js';
import { kirim } from '../../Lib/http.js';

defineProps({ rba: Array, boleh_ajukan: Boolean });
const pesan = ref(null);
const label = (s) => s.replaceAll('_', ' ').toLowerCase();

async function ajukan(r) {
  try {
    await kirim('POST', `/rba/${r.id}/ajukan`);
    pesan.value = { ok: true, teks: `RBA ${r.tahun} diajukan ke verifikator.` };
    router.reload();
  } catch (e) {
    pesan.value = { ok: false, teks: e.message };
  }
}
</script>

<template>
  <Head title="RBA" />
  <TataLetak judul="Rencana Bisnis dan Anggaran" lebar="max-w-4xl">
    <Link v-if="($page.props.auth?.izin ?? []).includes('rba.impor')" href="/impor-rba" class="inline-block rounded-lg border border-teal-700 px-3 py-1.5 text-sm text-teal-800">⇪ Impor RBA dari Excel (format lama)</Link>
    <p class="text-sm text-slate-600">Alur: diajukan → verifikasi → persetujuan internal → penetapan Pimpinan BLUD. Penetapan membentuk pagu jenis belanja.</p>
    <p v-if="pesan" class="rounded-lg p-3 text-sm" :class="pesan.ok ? 'bg-teal-50 text-teal-800' : 'bg-rose-50 text-rose-700'">{{ pesan.teks }}</p>
    <article v-for="r in rba" :key="r.id" class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <div class="flex-1">
        <div class="font-medium">RBA {{ r.tahun }} · versi {{ r.versi }} <span class="text-xs text-slate-500">({{ label(r.jenis_versi) }})</span></div>
        <div class="text-xs text-slate-500">{{ r.jumlah_rincian }} rincian
          <template v-if="r.nomor_pengesahan"> · {{ r.nomor_pengesahan }}</template>
          <template v-if="r.langkah"> · menunggu: {{ r.langkah }}</template></div>
      </div>
      <div class="angka font-semibold">{{ formatRp(keSen(r.total)) }}</div>
      <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ label(r.status) }}</span>
      <button v-if="boleh_ajukan && ['DRAF', 'DIKEMBALIKAN'].includes(r.status)" type="button"
        class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm text-white" @click="ajukan(r)">Ajukan</button>
    </article>
  </TataLetak>
</template>
