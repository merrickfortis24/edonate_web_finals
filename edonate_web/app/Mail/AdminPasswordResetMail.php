<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $fullName;
    public string $resetUrl;
    public int $expiryMinutes;

    public function __construct(string $fullName, string $resetUrl, int $expiryMinutes)
    {
        $this->fullName = $fullName;
        $this->resetUrl = $resetUrl;
        $this->expiryMinutes = $expiryMinutes;
    }

    public function build(): self
    {
        return $this
            ->subject('Admin Password Reset Request')
            ->view('emails.admin_password_reset');
    }
}
