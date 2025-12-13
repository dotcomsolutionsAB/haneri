<?php

namespace App\Mail;

use App\Models\QuotationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
        // Get the PDF URL (ensure it's publicly accessible)
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
}
