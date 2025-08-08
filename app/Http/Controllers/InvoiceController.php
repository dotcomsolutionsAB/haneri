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
    // public function generateQuotationInvoice($quotation)
    // {
    //     // Fetch the quotation record
    //     $quotation = QuotationModel::where('id', $quotation->id)->first();

    //     // Extract the required fields from the quotation
    //     $q_name = $quotation->q_user;
    //     $q_email = $quotation->q_email;
    //     $q_mobile = $quotation->q_mobile;
    //     $q_address = $quotation->q_address;

    //     // Fetch quotation items
    //     $q_items = QuotationItemModel::with([
    //         'product:id,name',
    //         'variant:id,product_id,variant_value'
    //     ])
    //     ->where('quotation_id', $quotation->id)
    //     ->get()
    //     ->map(function ($item) {
    //         $item->product_name = $item->product->name ?? '';
    //         $item->variant_value = $item->variant->variant_value ?? '';
    //         $item->rate = $item->price;
    //         $item->total = $item->price * $item->quantity;
    //         return $item;
    //     });

    //     // Sanitize the order ID for the file name
    //     $sanitizedOrderId = preg_replace('/[^A-Za-z0-9]+/', '-', trim($quotation->order_id));
    //     $sanitizedOrderId = trim($sanitizedOrderId, '-');

    //     // Define file path for storing the PDF
    //     $publicPath = 'upload/invoice_quotations/';
    //     $fileName = 'invoice_' . $sanitizedOrderId . '.pdf';
    //     $filePath = storage_path('app/public/' . $publicPath . $fileName);

    //     // Create directory if it doesn't exist
    //     if (!File::isDirectory(dirname($filePath))) {
    //         File::makeDirectory(dirname($filePath), 0755, true);
    //     }

    //     // Delete existing file if present
    //     if (File::exists($filePath)) {
    //         File::delete($filePath);
    //     }

    //     try {
    //         $mpdf = new Mpdf();
    //         \Log::info('mPDF instance created');

    //         // Add header
    //         $mpdf->WriteHTML(view('quotation_invoice_template_header', [
    //             'q_user' => $q_name,
    //             'q_email' => $q_email,
    //             'q_mobile' => $q_mobile,
    //             'q_address' => $q_address,
    //             'quotation' => $quotation,
    //             'q_items' => $q_items
    //         ])->render());
    //         \Log::info('Header added to mPDF');

    //         // Process each chunk of items
    //         $q_items->chunk(10)->each(function ($chunk) use ($mpdf) {
    //             foreach ($chunk as $item) {
    //                 $mpdf->WriteHTML(view('quotation_invoice_template_items', compact('item'))->render());
    //             }
    //         });
    //         \Log::info('Items added to mPDF');

    //         // Add footer
    //         $mpdf->WriteHTML(view('quotation_invoice_template_footer', ['quotation' => $quotation])->render());
    //         \Log::info('Footer added to mPDF');

    //         // Save the PDF to the file
    //         $mpdf->Output($filePath, 'F');

    //         // Generate the file URL
    //         $fileUrl = asset('storage/' . $publicPath . $fileName);

    //         // Return the file URL
    //         return $fileUrl;
    //         \Log::info('PDF generated and saved to file');
    //     } catch (\Mpdf\MpdfException $e) {
    //         \Log::error('Error generating PDF: ' . $e->getMessage());
    //     }
    // }

    public function generateQuotationInvoice(QuotationModel $quotation)
    {
        // 1. Customer info
        $q_name    = $quotation->q_user;
        $q_email   = $quotation->q_email;
        $q_mobile  = $quotation->q_mobile;
        $q_address = $quotation->q_address;

        // 2. Quotation items
        $q_items = QuotationItemModel::with([
                'product:id,name',
                'variant:id,product_id,variant_value'
            ])
            ->where('quotation_id', $quotation->id)
            ->get()
            ->map(function ($item) {
                $item->product_name  = $item->product->name ?? '';
                $item->variant_value = $item->variant->variant_value ?? '';
                $item->rate          = $item->price;
                $item->total         = $item->price * $item->quantity;
                return $item;
            });

        // 3. Build safe file name
        $sanitizedOrderId = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $quotation->id), '-');
        $publicPath       = 'upload/invoice_quotations/';
        $fileName         = 'invoice_' . $sanitizedOrderId. '.pdf';
        $filePath         = storage_path('app/public/' . $publicPath . $fileName);

        // 4. Ensure directory exists
        if (!File::isDirectory(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true, true);
        }

        // 5. Remove old file if present
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        try {
            $mpdf = new Mpdf(['format' => 'A4']);

            // 6. Render the single Blade view into mPDF
            $html = view('quotation_invoice', compact(
                'q_name',
                'q_email',
                'q_mobile',
                'q_address',
                'q_items',
                'quotation'
            ))->render();

            $mpdf->WriteHTML($html);

            // 7. Save PDF
            $mpdf->Output($filePath, 'F');

            // 8. Return public URL
            return asset('storage/' . $publicPath . $fileName);

        } catch (\Mpdf\MpdfException $e) {
            \Log::error('mPDF error: ' . $e->getMessage());
            return null;
        }
    }

}
