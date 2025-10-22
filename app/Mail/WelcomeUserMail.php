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

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . $this->siteName . ' 🎉',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome_user',
            with: [
                'user'        => $this->user,
                'siteName'    => $this->siteName,
                'loginUrl'    => config('app.frontend_url', url('/login')),
                'supportEmail'=> config('mail.from.address'),
            ],
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
