<?php

namespace App\Mail;

use App\Models\User; // ✅ important
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */

    public User $user;
    public string $newPassword;

    public function __construct(User $user, string $newPassword)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Reset Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_reset', // using HTML view (not markdown)
            with: [
                'user'             => $this->user,
                'newPassword'      => $this->newPassword,
                'siteName'         => config('app.name', 'Haneri'),
                'loginUrl'         => env('APP_LOGIN_URL'),
                'frontendUrl'      => env('APP_FRONTEND_URL'),
                'supportEmail'     => env('MAIL_SUPPORT_EMAIL', config('mail.from.address')),
                'officeEmail'       => env('MAIL_FROM_ADDRESS'),
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
