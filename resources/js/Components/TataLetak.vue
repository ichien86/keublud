<script setup>
// Tata letak utama: navigasi atas di layar lebar, navigasi bawah di ponsel (mobile-first).
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ judul: String, lebar: { type: String, default: 'max-w-6xl' } });

const page = usePage();
const auth = computed(() => page.props.auth);
const menunggu = computed(() => page.props.menunggu ?? 0);
const lokasi = computed(() => page.url);

const bolehImpor = computed(() => (auth.value?.izin ?? []).includes('rba.impor'));
const menu = computed(() => [
  { href: '/beranda', label: 'Beranda', ikon: '⌂' },
  { href: '/persetujuan', label: 'Persetujuan', ikon: '✓', lencana: true },
  { href: '/pergeseran', label: 'Pergeseran', ikon: '⇄' },
  { href: '/standar-harga', label: 'Standar Harga', ikon: '≡' },
  { href: '/rba', label: 'RBA', ikon: '▤' },
  ...(bolehImpor.value ? [{ href: '/impor-rba', label: 'Impor RBA', ikon: '⇪', desktop: true }] : []),
]);
const aktif = (href) => lokasi.value.startsWith(href);
const keluar = () => router.post('/logout');
</script>

<template>
  <div class="min-h-screen pb-20 sm:pb-0">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex items-center gap-4 px-4 py-2" :class="lebar">
        <Link href="/beranda" class="flex items-center gap-2 font-semibold text-teal-800">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-700 text-sm text-white">KB</span>
          <span class="hidden whitespace-nowrap lg:inline">Keuangan BLUD</span>
        </Link>
        <nav class="hidden flex-1 gap-1 sm:flex">
          <Link v-for="m in menu" :key="m.href" :href="m.href"
            class="relative whitespace-nowrap rounded-md px-3 py-1.5 text-sm"
            :class="aktif(m.href) ? 'bg-teal-50 font-medium text-teal-800' : 'text-slate-600 hover:bg-slate-100'">
            {{ m.label }}
            <span v-if="m.lencana && menunggu" class="ml-1 rounded-full bg-rose-600 px-1.5 text-xs text-white">{{ menunggu }}</span>
          </Link>
        </nav>
        <div class="ml-auto flex items-center gap-3 text-right sm:ml-0">
          <div class="leading-tight">
            <div class="max-w-48 truncate text-sm font-medium">{{ auth?.nama }}</div>
            <div class="max-w-48 truncate text-xs text-slate-500">{{ auth?.jabatan }}</div>
          </div>
          <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100" @click="keluar">Keluar</button>
        </div>
      </div>
    </header>

    <main class="mx-auto space-y-4 p-4" :class="lebar">
      <h1 v-if="judul" class="text-lg font-semibold">{{ judul }}</h1>
      <slot />
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-slate-200 bg-white sm:hidden">
      <Link v-for="m in menu.filter((x) => !x.desktop)" :key="m.href" :href="m.href" class="relative flex flex-col items-center py-2 text-[11px]"
        :class="aktif(m.href) ? 'text-teal-700' : 'text-slate-500'">
        <span class="text-lg leading-none">{{ m.ikon }}</span>
        <span>{{ m.label }}</span>
        <span v-if="m.lencana && menunggu" class="absolute right-3 top-1 rounded-full bg-rose-600 px-1.5 text-[10px] text-white">{{ menunggu }}</span>
      </Link>
    </nav>
  </div>
</template>
