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

        // All from .env (per your preference). Cast/default so a missing variable can never
        // throw a TypeError here — that used to swallow the confirmation email silently.
        $this->siteName        = (string) env('APP_NAME', 'Haneri');
        $this->frontendUrl     = (string) env('APP_FRONTEND_URL', 'https://haneri.com');
        $this->loginUrl        = (string) env('APP_LOGIN_URL', rtrim($this->frontendUrl, '/') . '/login');
        $this->supportEmail    = (string) env('MAIL_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS'));
        $this->techSupportEmail= (string) env('MAIL_TECH_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS'));

        // Plain link to the order page; the storefront asks the customer to sign in when needed.
        // (No API token in the URL: a never-expiring credential in an email link is a liability.)
        $this->orderUrl = rtrim($this->frontendUrl, '/') . '/orders/' . $order->id;
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
