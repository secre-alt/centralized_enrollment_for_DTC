<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public string $referenceNo;
    public string $statusUrl;

    public function __construct(Application $application, string $statusUrl)
    {
        $this->referenceNo = $application->reference_no;
        $this->statusUrl = $statusUrl;
    }

    public function build()
    {
        return $this
            ->subject('Your DTC EMS Application Reference')
            ->view('emails.application-submitted');
    }
}
