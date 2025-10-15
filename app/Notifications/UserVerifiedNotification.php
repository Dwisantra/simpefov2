<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserVerifiedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ?string $initialPassword = null,
        private readonly ?string $loginUrl = null,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Akun SIMPEFO Anda Telah Diverifikasi')
            ->greeting('Halo ' . ($notifiable->name ?? 'Pengguna') . '!')
            ->line('Akun Anda telah diverifikasi oleh admin SIMPEFO.')
            ->line('Silakan gunakan kredensial berikut untuk masuk ke SIMPEFO:')
            ->line('Email: ' . ($notifiable->email ?? '-'));

        if ($this->initialPassword) {
            $mail->line('Password: ' . $this->initialPassword);
        } else {
            $mail->line('Gunakan password yang Anda daftarkan ketika registrasi.');
        }

        if ($this->loginUrl) {
            $mail->action('Masuk ke SIMPEFO', $this->loginUrl);
        }

        return $mail
            ->line('Setelah berhasil login, segera buka menu Pengaturan Profil untuk menyimpan kode ACC pertama Anda agar proses persetujuan berjalan lancar.')
            ->line('Terima kasih telah menggunakan SIMPEFO.');
    }
}
