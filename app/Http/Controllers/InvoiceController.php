<?php

namespace App\Http\Controllers;
use App\Models\QuotationModel;
use App\Models\QuotationItemModel;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    //
    public function generateQuotationInvoice($quotation)
    {
        // Fetch the quotation record
        $quotation = QuotationModel::where('id', $quotation->id)->first();

        // Extract the required fields from the quotation
        $q_name = $quotation->q_user;
        $q_email = $quotation->q_email;
        $q_mobile = $quotation->q_mobile;
        $q_address = $quotation->q_address;

        $q_items = QuotationItemModel::where('quotation_id', $quotation->id)->get();
        echo "<pre>";
        print_r($q_items);
        dd("lll");

        // Sanitize the order ID for the file name
        $sanitizedOrderId = preg_replace('/[^A-Za-z0-9]+/', '-', trim($quotation->order_id));
        $sanitizedOrderId = trim($sanitizedOrderId, '-');

        // Define file path for storing the PDF
        $publicPath = 'uploads/invoice_quotations/';
        $fileName = 'invoice_' . $sanitizedOrderId . '.pdf';
        $filePath = storage_path('app/public/' . $publicPath . $fileName);

        // Create directory if it doesn't exist
        if (!File::isDirectory(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true);
        }

        // Delete existing file if present
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        // Generate the PDF using mPDF
        $mpdf = new Mpdf();

        // Render the header
        $mpdf->WriteHTML(view('quotation_invoice_template_header', [
            'q_user' => $q_name,
            'q_email' => $q_email,
            'q_mobile' => $q_mobile,
            'q_address' => $q_address,
            'quotation' => $quotation,
            'q_items' => $q_items
        ])->render());

        // Render the order items in chunks of 10 per page
        // foreach ($q_items->chunk(10) as $chunk) {
        //     echo "<pre>";
        //     print_r($chunk);
        //     foreach ($chunk as $index => $item) {
        //         $mpdf->WriteHTML(view('quotation_invoice_template_items', compact('item', 'index'))->render());
        //     }
        //     flush();
        // }

        // Render the order items in chunks of 10 per page
        $q_items->chunk(10)->each(function ($chunk) use ($mpdf) {
            if ($chunk->isEmpty()) {
                // Skip processing if the chunk is empty
                return;
            }
print_r($chunk);
            foreach ($chunk as $index => $item) {
                echo "<pre>";
                print_r($index);
                print_r($item);
                $mpdf->WriteHTML(view('quotation_invoice_template_items', compact('item', 'index'))->render());
            }
            flush();
        });


        // Render the footer
        $mpdf->WriteHTML(view('quotation_invoice_template_footer', ['quotation' => $quotation])->render());

        // Output the PDF to a file
        $mpdf->Output($filePath, 'F');

        // Generate the file URL
        $fileUrl = asset('storage/' . $publicPath . $fileName);

        // Return the file URL
        return $fileUrl;
    }
}
