<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp
    ) {}

    public function build()
    {
        return $this
            ->subject('Tinh Shop - Password Reset Code')
            ->view('mails.password-reset-otp');
    }
}
