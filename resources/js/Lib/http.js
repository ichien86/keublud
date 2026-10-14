// Pemanggil JSON sederhana dengan token CSRF Laravel; galat domain dikembalikan sebagai objek { kode, pesan, ... }.
function xsrf() {
  const c = document.cookie.split('; ').find((x) => x.startsWith('XSRF-TOKEN='));
  return c ? decodeURIComponent(c.split('=')[1]) : '';
}

export async function kirim(metode, url, data) {
  const res = await fetch(url, {
    method: metode,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
    body: data === undefined ? undefined : JSON.stringify(data),
  });
  const isi = res.status === 204 ? null : await res.json().catch(() => null);
  if (!res.ok) {
    const galat = new Error(isi?.pesan ?? isi?.message ?? `Permintaan gagal (${res.status})`);
    galat.status = res.status;
    galat.isi = isi;
    throw galat;
  }
  return isi;
}
