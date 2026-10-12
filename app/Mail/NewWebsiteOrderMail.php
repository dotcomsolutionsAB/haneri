<?php

namespace App\Mail;

use App\Models\OrderModel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewWebsiteOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public OrderModel $order;

    /** @var array<int, array{name:string,variant:?string,qty:int,price:float,total:float}> */
    public array $items;

    public string $siteName;

    public string $orderUrl;

    /**
     * @param  array<int, array{name:string,variant:?string,qty:int,price:float,total:float}>  $items
     */
    public function __construct(User $user, OrderModel $order, array $items)
    {
        $this->user = $user;
        $this->order = $order;
        $this->items = $items;
        $this->siteName = (string) config('app.name', 'Haneri');
        $frontendUrl = rtrim((string) env('APP_FRONTEND_URL', 'https://haneri.com'), '/');
        $this->orderUrl = $frontendUrl.'/admin/orders/'.$order->id;
    }

    public static function subjectFor(OrderModel $order, ?string $siteName = null): string
    {
        $site = $siteName ?? (string) config('app.name', 'Haneri');

        return 'New website order • #'.$order->id.' • '.$site;
    }

    public function envelope(): Envelope
    {
        $replyTo = [];
        if (! empty($this->user->email)) {
            $replyTo[] = new Address($this->user->email, $this->user->name ?? '');
        }

        return new Envelope(
            subject: self::subjectFor($this->order, $this->siteName),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new_website_order',
            with: [
                'user' => $this->user,
                'order' => $this->order,
                'items' => $this->items,
                'siteName' => $this->siteName,
                'orderUrl' => $this->orderUrl,
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
