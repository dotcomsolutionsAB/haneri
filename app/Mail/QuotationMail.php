<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\QuotationModel;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quotation;
    public $user;

    // Constructor to pass the necessary data
    public function __construct(QuotationModel $quotation, $user)
    {
        $this->quotation = $quotation;
        $this->user = $user;
    }

    // Build the email
    public function build()
    {
        // Get the PDF URL
        $pdfUrl = $this->quotation->invoice_quotation; // Path to the saved PDF

        return $this->subject('Your Quotation - ' . $this->quotation->quotation_no)
                    ->view('emails.quotation') // Use the email view to show details
                    ->attachFromStorage($pdfUrl, 'quotation_' . $this->quotation->quotation_no . '.pdf', [
                        'mime' => 'application/pdf', // Specify mime type as PDF
                    ])
                    ->with([
                        'user' => $this->user,
                        'quotation' => $this->quotation,
                    ]);
    }

    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Quotation Mail',
    //     );
    // }

    // /**
    //  * Get the message content definition.
    //  */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    // /**
    //  * Get the attachments for the message.
    //  *
    //  * @return array<int, \Illuminate\Mail\Mailables\Attachment>
    //  */
    // public function attachments(): array
    // {
    //     return [];
    // }
}
