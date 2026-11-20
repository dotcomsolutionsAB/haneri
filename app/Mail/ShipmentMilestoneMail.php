<?php

namespace App\Mail;

use App\Models\OrderModel;
use App\Models\OrderShipment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentMilestoneMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $siteName;
    public string $frontendUrl;
    public string $supportEmail;
    public string $headline;
    public string $intro;

    /**
     * @param  array<int, array{awb:string,url:string}>  $awbs
     */
    public function __construct(
        public User $user,
        public OrderModel $order,
        public OrderShipment $shipment,
        public string $milestone,
        public string $carrierStatus,
        public ?string $location,
        public ?string $scannedAt,
        public array $awbs,
    ) {
        $this->siteName = (string) env('APP_NAME', 'Haneri');
        $this->frontendUrl = (string) env('APP_FRONTEND_URL', 'https://haneri.com');
        $this->supportEmail = (string) env('MAIL_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'info@haneri.in'));
        [$this->headline, $this->intro] = self::copy($milestone);
    }

    public function subjectLine(): string
    {
        return match ($this->milestone) {
            'dispatched' => 'Your '.$this->siteName.' order has been dispatched',
            'out_for_delivery' => 'Your '.$this->siteName.' order is out for delivery',
            'delivered' => 'Your '.$this->siteName.' order has been delivered',
            'returned' => 'Your '.$this->siteName.' order is being returned',
            default => 'Delivery update for your '.$this->siteName.' order',
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shipment_milestone',
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function copy(string $milestone): array
    {
        return match ($milestone) {
            'dispatched' => [
                'Your order has been dispatched',
                'Your order is on the way. Use the tracking number below to follow it.',
            ],
            'out_for_delivery' => [
                'Your order is out for delivery',
                'The courier is out with your order today.',
            ],
            'delivered' => [
                'Your order has been delivered',
                'The courier has marked this shipment as delivered.',
            ],
            'returned' => [
                'Your order is being returned',
                'The courier is sending this shipment back. Contact us if you still need it delivered.',
            ],
            default => [
                'A delivery attempt was unsuccessful',
                'The courier could not complete this delivery and will try again.',
            ],
        };
    }
}
