<?php

namespace App\Mail;

use App\Models\ApplicationEmailChange;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationEmailChangeVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ApplicationEmailChange $emailChange, public string $confirmationUrl)
    {
    }

    public function build()
    {
        return $this->subject('Verify your updated DTC EMS application email')
            ->view('emails.application-email-change-verification');
    }
}
