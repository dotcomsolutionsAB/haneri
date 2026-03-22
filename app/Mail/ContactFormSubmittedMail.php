<?php

namespace App\Mail;

use App\Models\ContactFormModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ContactFormModel $contact;

    public string $siteName;

    public function __construct(ContactFormModel $contact)
    {
        $this->contact = $contact;
        $this->siteName = config('app.name', 'Haneri');
    }

    public function envelope(): Envelope
    {
        $replyTo = [];
        if (! empty($this->contact->email)) {
            $replyTo[] = new Address($this->contact->email, $this->contact->name ?? '');
        }

        return new Envelope(
            subject: 'New contact form submission • #' . $this->contact->id . ' • ' . $this->siteName,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact_form_submitted',
            with: [
                'contact'  => $this->contact,
                'siteName' => $this->siteName,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
