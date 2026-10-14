<?php

declare(strict_types=1);

namespace App\Modules\Shared\Notifikasi;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi umum: tersimpan di tabel notifications, disiarkan lewat Reverb, dan dikirim email sebagai cadangan.
 * Tautan selalu membuka objek terkait (PRD 7.15 butir 3).
 */
final class NotifikasiBlud extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $jenis,
        public readonly string $judul,
        public readonly string $pesan,
        public readonly string $url,
        public readonly bool $mendesak = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['jenis' => $this->jenis, 'judul' => $this->judul, 'pesan' => $this->pesan, 'url' => $this->url, 'mendesak' => $this->mendesak];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->judul)->line($this->pesan)->action('Buka', url($this->url));
    }
}
