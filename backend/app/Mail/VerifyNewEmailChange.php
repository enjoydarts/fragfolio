<?php

namespace App\Mail;

use App\Models\EmailChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyNewEmailChange extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmailChangeRequest $emailChangeRequest
    ) {
        $this->onQueue('emails');
        $this->locale = $this->emailChangeRequest->user->preferred_language ?? config('app.locale');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.verify_new_email_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-new-email-change',
            with: [
                'user' => $this->emailChangeRequest->user,
                'currentEmail' => $this->emailChangeRequest->user->email,
                'verificationUrl' => config('app.url').'/api/auth/email/verify-change/'.$this->emailChangeRequest->token,
                'frontendUrl' => config('app.frontend_url'),
            ],
        );
    }
}
