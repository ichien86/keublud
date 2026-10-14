<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import TataLetak from '../Components/TataLetak.vue';
import { formatRp, keSen } from '../Lib/uang.js';

const props = defineProps({ tahun: Number, pagu: Array, boleh_edit_unit: Array, boleh_revisi: Boolean, menunggu: Number, peran: Array });

const perUnit = computed(() => {
  const g = {};
  for (const p of props.pagu) (g[p.unit] ??= []).push(p);
  return g;
});
const bolehEdit = (p) => props.boleh_revisi && props.boleh_edit_unit.includes(p.unit_id);
const persen = (p) => {
  const pagu = keSen(p.pagu_berlaku);
  if (pagu === 0n) return 0;
  return Number(((keSen(p.realisasi) + keSen(p.komitmen) + keSen(p.tercadang)) * 1000n) / pagu) / 10;
};
</script>

<template>
  <Head title="Beranda" />
  <TataLetak>
    <section class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-lg font-semibold">Pagu jenis belanja {{ tahun }}</h1>
        <p class="text-sm text-slate-600">Pengendalian per unit × jenis belanja × sumber dana.</p>
      </div>
      <Link v-if="menunggu" href="/persetujuan" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white">
        {{ menunggu }} pengajuan menunggu keputusan Anda →
      </Link>
    </section>

    <p v-if="!pagu.length" class="rounded-lg bg-white p-6 text-center text-sm text-slate-500 ring-1 ring-slate-200">
      Belum ada pagu untuk unit Anda. Pagu terbentuk setelah RBA ditetapkan definitif.
    </p>

    <section v-for="(daftar, unit) in perUnit" :key="unit" class="space-y-2">
      <h2 class="text-sm font-semibold text-slate-700">{{ unit }}</h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <article v-for="p in daftar" :key="p.id" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
          <div class="text-xs text-slate-500">{{ p.kode_jenis }} · {{ p.sumber_dana }}</div>
          <div class="font-medium">{{ p.jenis }}</div>
          <dl class="mt-3 grid grid-cols-2 gap-y-1 text-sm">
            <dt class="text-slate-500">Pagu berlaku</dt><dd class="angka text-right">{{ formatRp(keSen(p.pagu_berlaku)) }}</dd>
            <dt class="text-slate-500">Tercadang</dt><dd class="angka text-right">{{ formatRp(keSen(p.tercadang)) }}</dd>
            <dt class="text-slate-500">Sisa</dt><dd class="angka text-right font-semibold text-teal-800">{{ formatRp(keSen(p.sisa)) }}</dd>
          </dl>
          <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-teal-600" :style="{ width: persen(p) + '%' }" /></div>
          <Link v-if="bolehEdit(p)" :href="`/revisi/edit/${p.id}`"
            class="mt-3 block rounded-lg border border-teal-700 py-1.5 text-center text-sm font-medium text-teal-800 hover:bg-teal-50">Edit rincian</Link>
        </article>
      </div>
    </section>
  </TataLetak>
</template>
