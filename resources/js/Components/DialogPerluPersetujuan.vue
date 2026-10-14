<script setup>
// Dialog saat menyimpan atau meninggalkan halaman dengan pagu berubah (PRD 7.7 butir 7 & 10, FR-092, FR-095).
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
  terbuka: Boolean,
  pesan: { type: String, default: '' },
  mode: { type: String, default: 'simpan' }, // 'simpan' | 'keluar'
  memproses: Boolean,
});
const emit = defineEmits(['kirim', 'lanjut', 'buang']);
const tombolUtama = ref(null);

watch(() => props.terbuka, async (v) => {
  if (v) { await nextTick(); tombolUtama.value?.focus(); }
});
</script>

<template>
  <div v-if="terbuka" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center" @keydown.esc="emit('lanjut')">
    <div role="dialog" aria-modal="true" aria-labelledby="judul-dialog" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
      <h2 id="judul-dialog" class="text-base font-semibold">Perubahan memerlukan persetujuan</h2>
      <p class="mt-2 text-sm text-slate-700">{{ pesan }}</p>
      <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="rounded-md px-3 py-2 text-sm text-rose-700 hover:bg-rose-50" :disabled="memproses" @click="emit('buang')">
          {{ mode === 'keluar' ? 'Buang perubahan' : 'Batalkan perubahan' }}
        </button>
        <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" :disabled="memproses" @click="emit('lanjut')">
          {{ mode === 'keluar' ? 'Kembali ke halaman edit' : 'Lanjutkan edit' }}
        </button>
        <button ref="tombolUtama" type="button" class="rounded-md bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-60"
          :disabled="memproses" @click="emit('kirim')">
          {{ memproses ? 'Mengirim…' : 'Kirim sebagai permintaan pergeseran' }}
        </button>
      </div>
    </div>
  </div>
</template>
