<script setup>
// Indikator status pagu jenis belanja (PRD 7.7 butir 6, FR-091). Menempel di atas halaman.
import { computed } from 'vue';
import { formatRp } from '../Lib/uang.js';

const props = defineProps({
  paguSen: { type: BigInt, required: true },
  totalSen: { type: BigInt, required: true },
  berlakuLangsung: { type: Boolean, default: true },
  namaJenis: { type: String, default: '' },
  diAtasStandar: { type: Boolean, default: false },
});

const selisih = computed(() => props.totalSen - props.paguSen);
const status = computed(() => (selisih.value === 0n ? 'SEIMBANG' : selisih.value > 0n ? 'BERTAMBAH' : 'BERKURANG'));

const keterangan = computed(() => {
  if (status.value !== 'SEIMBANG') return 'Perlu persetujuan melalui pergeseran antarunit';
  if (props.diAtasStandar) return 'Ada harga di atas standar — menunggu persetujuan perencana setelah disimpan';
  return props.berlakuLangsung
    ? `${props.namaJenis} — revisi seimbang langsung berlaku`
    : `${props.namaJenis} — revisi memerlukan persetujuan perencana`;
});
</script>

<template>
  <div
    class="sticky top-0 z-10 rounded-lg border px-4 py-3 shadow-sm"
    :class="status === 'SEIMBANG' ? 'border-slate-200 bg-white' : 'border-amber-300 bg-amber-50'"
    role="status"
    aria-live="polite"
  >
    <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm">
      <span>Pagu jenis <strong class="angka">{{ formatRp(paguSen) }}</strong></span>
      <span>Total rincian <strong class="angka">{{ formatRp(totalSen) }}</strong></span>
    </div>
    <div class="mt-1 flex flex-wrap items-center gap-2">
      <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
        :class="status === 'SEIMBANG' ? 'bg-slate-100 text-slate-700' : 'bg-amber-200 text-amber-900'"
      >
        <span aria-hidden="true">●</span>
        <template v-if="status === 'SEIMBANG'">SEIMBANG</template>
        <template v-else>PAGU BERUBAH: {{ status === 'BERTAMBAH' ? 'bertambah' : 'berkurang' }}
          <span class="angka">{{ formatRp(selisih < 0n ? -selisih : selisih) }}</span></template>
      </span>
      <span class="text-xs text-slate-600">{{ keterangan }}</span>
    </div>
  </div>
</template>
