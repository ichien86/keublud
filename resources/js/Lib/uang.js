// Perhitungan uang di klien dalam sen (BigInt) agar identik dengan server (App\Modules\Shared\Domain\Uang).

export function keSen(teks) {
  const s = String(teks ?? '0').trim();
  const m = s.match(/^(-)?(\d+)(?:\.(\d{1,2}))?$/);
  if (!m) return 0n;
  const sen = BigInt(m[2]) * 100n + BigInt((m[3] ?? '0').padEnd(2, '0'));
  return m[1] ? -sen : sen;
}

// harga (string desimal) x volume (maks 4 desimal), pembulatan half-up ke sen
export function kaliVolume(harga, volume) {
  const sen = keSen(harga);
  const m = String(volume ?? '0').trim().match(/^(\d+)(?:\.(\d{1,4}))?$/);
  if (!m || sen < 0n) return 0n;
  const v = BigInt(m[1] + (m[2] ?? '').padEnd(4, '0'));
  const hasil = sen * v;
  const bulat = hasil / 10000n;
  return hasil % 10000n >= 5000n ? bulat + 1n : bulat;
}

export function formatRp(sen) {
  const n = BigInt(sen);
  const neg = n < 0n;
  const abs = neg ? -n : n;
  const rupiah = (abs / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const s = abs % 100n;
  return `${neg ? '-' : ''}Rp ${rupiah}${s ? ',' + s.toString().padStart(2, '0') : ''}`;
}
