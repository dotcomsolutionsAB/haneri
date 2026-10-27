<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $siteName;
    public string $frontendUrl;
    public string $cartUrl;
    public string $supportEmail;

    /**
     * @param  array<int, array{name:string,variant:?string,qty:int,price:float,total:float}>  $items
     */
    public function __construct(
        public User $user,
        public array $items,
        public int $stage,
        public ?string $couponCode = null,
        public ?int $discountPercent = null,
        public ?Carbon $couponExpiresAt = null,
        ?string $cartUrl = null,
    ) {
        $this->siteName = (string) env('APP_NAME', 'Haneri');
        $this->frontendUrl = rtrim((string) env('APP_FRONTEND_URL', 'https://haneri.com'), '/');
        $this->cartUrl = $cartUrl ?: $this->frontendUrl . '/cart';
        $this->supportEmail = (string) env('MAIL_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS'));
    }

    public function envelope(): Envelope
    {
        $subject = $this->stage === 2
            ? 'Your cart is still waiting • ' . $this->siteName
            : 'You left items in your cart • ' . $this->siteName;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned_cart',
            with: [
                'user' => $this->user,
                'items' => $this->items,
                'stage' => $this->stage,
                'couponCode' => $this->couponCode,
                'discountPercent' => $this->discountPercent,
                'couponExpiresAt' => $this->couponExpiresAt,
                'siteName' => $this->siteName,
                'frontendUrl' => $this->frontendUrl,
                'cartUrl' => $this->cartUrl,
                'supportEmail' => $this->supportEmail,
            ],
        );
    }
}
