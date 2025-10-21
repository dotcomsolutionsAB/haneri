<?php

namespace App\Mail;

use App\Models\User;              // ✅ make sure THIS is imported

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $siteName;
    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $siteName = 'Haneri')
    {
        $this->user = $user;
        $this->siteName = $siteName;
    }

    public function build()
    {
        return $this->subject('Welcome to ' . $this->siteName . ' 🎉')
            ->markdown('emails.welcome_user', [
                'user' => $this->user,
                'siteName' => $this->siteName,
                // If you have a frontend login page, pass it here:
                'loginUrl' => config('app.frontend_url', url('/login')),
                'supportEmail' => config('mail.from.address'),
            ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome User Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome_user',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
