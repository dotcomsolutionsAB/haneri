<?php

namespace App\Mail;

use App\Models\User;
use App\Models\OrderModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public User $user;
    public OrderModel $order;
    /** @var array<int, array{name:string,variant:?string,qty:int,price:float,total:float}> */
    public array $items;
    public string $siteName;
    public string $frontendUrl;
    public string $loginUrl;
    public string $supportEmail;
    public string $techSupportEmail;
    public string $orderUrl;

    /**
     * @param array $items each item: ['name'=>string,'variant'=>?string,'qty'=>int,'price'=>float,'total'=>float]
     */
    public function __construct(User $user, OrderModel $order, array $items)
    {
        $this->user = $user;
        $this->order = $order;
        $this->items = $items;

        // All from .env (per your preference)
        $this->siteName        = env('APP_NAME', 'Haneri');
        $this->frontendUrl     = env('APP_FRONTEND_URL');
        $this->loginUrl        = env('APP_LOGIN_URL');
        $this->supportEmail    = env('MAIL_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS'));
        $this->techSupportEmail= env('MAIL_TECH_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS'));

        // Where the user can view the order on the website (adjust path if needed)
        $this->orderUrl        = $this->frontendUrl . '/profile#order';
        // $this->orderUrl        = $this->frontendUrl . '/profile/' . $this->order->id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmed • #' . $this->order->id . ' • ' . $this->siteName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order_placed',
            with: [
                'user'             => $this->user,
                'order'            => $this->order,
                'items'            => $this->items,
                'siteName'         => $this->siteName,
                'frontendUrl'      => $this->frontendUrl,
                'loginUrl'         => $this->loginUrl,
                'supportEmail'     => $this->supportEmail,
                'techSupportEmail' => $this->techSupportEmail,
                'orderUrl'         => $this->orderUrl,
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
