import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
  title: (judul) => (judul ? `${judul} — Keuangan BLUD` : 'Keuangan BLUD'),
  resolve: (nama) => {
    const halaman = import.meta.glob('./Pages/**/*.vue', { eager: true });
    return halaman[`./Pages/${nama}.vue`];
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
  },
  progress: { color: '#0f766e' },
});
