<?php

namespace App\Mail;

use App\Models\OrderModel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use App\Models\UploadModel;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;
    public $status;
    public $payment_status;
    public $invoice; // Add invoice to make sure it's passed to the view
    public $siteName;
    public $frontendUrl;

    /**
     * Create a new message instance.
     *
     * @param OrderModel $order
     * @param User $user
     * @param string $status
     * @param string $payment_status
     */
    public function __construct(OrderModel $order, User $user, string $status, string $payment_status)
    {
        $this->order = $order;
        $this->user = $user;
        $this->status = $status;
        $this->payment_status = $payment_status;

        // Get site details directly from .env
        $this->siteName = env('APP_NAME', 'Haneri');  // Default to 'Haneri' if not set
        $this->frontendUrl = env('APP_FRONTEND_URL', 'https://haneri.com');  // Default to your frontend URL if not set

        // Find invoice if it exists
        $this->invoice = null;
        if ($this->order->invoice_id) {
            $upload = UploadModel::find($this->order->invoice_id);
            if ($upload) {
                $this->invoice = [
                    'id'  => $upload->id,
                    'url' => asset('storage/' . $upload->file_path),
                ];
            }
        }
    }

    public function build()
    {
        \Log::info('Building Order Status Update Email');
        return $this->subject('Order Status Updated - Order #' . $this->order->id)
                    ->view('emails.order_status_update')
                    ->with([
                        'user' => $this->user,
                        'order' => $this->order,
                        'status' => $this->status,
                        'payment_status' => $this->payment_status,
                        'siteName' => $this->siteName,
                        'frontendUrl' => $this->frontendUrl,
                        'invoice' => $this->invoice, // Pass invoice to view
                    ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Status Update',
        );
    }

    /**
     * Get the message content definition.
     */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    // public function attachments(): array
    // {
    //     return [];
    // }
}
