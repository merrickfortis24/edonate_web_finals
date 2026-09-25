<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $adminName,
        public string $notificationTitle,
        public string $notificationMessage,
        public string $notificationType,
        public ?string $relatedType = null,
        public ?int $relatedId = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('eDonate Admin Alert: '.$this->notificationTitle)
            ->view('emails.admin_notification');
    }
}
