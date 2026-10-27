<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;        // <- typed
    public string $password;  // <- typed
    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $password)
    {
        //
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'User Registered Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user_registered',
            with: [
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'userPassword' => $this->password,
                'siteName' => config('app.name', 'Haneri'),
                'loginUrl' => env('APP_LOGIN_URL'),
                'frontendUrl' => env('APP_FRONTEND_URL'),
                'supportEmail' => env('MAIL_SUPPORT_EMAIL', config('mail.from.address')),
                'techSupportEmail' => env('MAIL_TECH_SUPPORT_EMAIL', config('mail.from.address')),
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
