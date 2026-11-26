<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\RetentionCertificate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RetentionCertificateMail extends Mailable
{
    public function __construct(public RetentionCertificate $certificate)
    {
        $this->certificate->loadMissing('thirdParty');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->certificate->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.retention-certificate',
            with: ['company' => Company::query()->find($this->certificate->company_id)],
        );
    }
}
