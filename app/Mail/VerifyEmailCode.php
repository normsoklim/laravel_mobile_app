<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmailCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code
    ) {
    }

    public function build()
    {
        return $this
            ->subject('Tinh Shop - Email Verification Code')
            ->view('mails.verify-email');
    }
}