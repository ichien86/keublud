<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ login: '', password: '', remember: false });
const masuk = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
  <Head title="Masuk" />
  <main class="grid min-h-screen place-items-center bg-gradient-to-b from-teal-50 to-slate-50 p-4">
    <form class="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" @submit.prevent="masuk">
      <div class="text-center">
        <div class="mx-auto mb-2 grid h-12 w-12 place-items-center rounded-xl bg-teal-700 font-semibold text-white">KB</div>
        <h1 class="text-lg font-semibold">Keuangan BLUD RSUD</h1>
        <p class="text-sm text-slate-500">Masuk dengan email atau NIP</p>
      </div>
      <label class="block text-sm">
        <span class="text-slate-700">Email atau NIP</span>
        <input v-model="form.login" type="text" autocomplete="username" required autofocus
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100" />
      </label>
      <label class="block text-sm">
        <span class="text-slate-700">Kata sandi</span>
        <input v-model="form.password" type="password" autocomplete="current-password" required
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100" />
      </label>
      <p v-if="form.errors.login" class="rounded-md bg-rose-50 p-2 text-sm text-rose-700">{{ form.errors.login }}</p>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" /> Ingat saya
      </label>
      <button type="submit" :disabled="form.processing"
        class="w-full rounded-lg bg-teal-700 py-2.5 font-medium text-white hover:bg-teal-800 disabled:opacity-60">Masuk</button>
    </form>
  </main>
</template>
