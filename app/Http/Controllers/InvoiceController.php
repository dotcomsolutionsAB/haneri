<?php

namespace App\Http\Controllers;
use App\Models\QuotationModel;
use App\Models\QuotationItemModel;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Illuminate\Http\Request;
use App\Models\OrderModel;
use App\Models\UploadModel;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{

    // public function generateQuotationInvoice(QuotationModel $quotation)
    // {
    //     // 1. Customer info
    //     $q_name    = $quotation->q_user;
    //     $q_email   = $quotation->q_email;
    //     $q_mobile  = $quotation->q_mobile;
    //     $q_address = $quotation->q_address;

    //     // 2. Quotation items
    //     $q_items = QuotationItemModel::with([
    //             'product:id,name',
    //             'variant:id,product_id,variant_value'
    //         ])
    //         ->where('quotation_id', $quotation->id)
    //         ->get()
    //         ->map(function ($item) {
    //             $item->product_name  = $item->product->name ?? '';
    //             $item->variant_value = $item->variant->variant_value ?? '';
    //             $item->rate          = $item->price;
    //             $item->total         = $item->price * $item->quantity;
    //             return $item;
    //         });

    //     // 3. Build safe file name
    //     $sanitizedOrderId = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $quotation->id), '-');
    //     $publicPath       = 'upload/invoice_quotations/';
    //     $fileName         = 'invoice_' . $sanitizedOrderId. '.pdf';
    //     $filePath         = storage_path('app/public/' . $publicPath . $fileName);

    //     // 4. Ensure directory exists
    //     if (!File::isDirectory(dirname($filePath))) {
    //         File::makeDirectory(dirname($filePath), 0755, true, true);
    //     }

    //     // 5. Remove old file if present
    //     if (File::exists($filePath)) {
    //         File::delete($filePath);
    //     }

    //     try {
    //         $mpdf = new Mpdf(['format' => 'A4']);

    //         // 6. Render the single Blade view into mPDF
    //         $html = view('pdf.quotation_invoice', compact(
    //             'q_name',
    //             'q_email',
    //             'q_mobile',
    //             'q_address',
    //             'q_items',
    //             'quotation'
    //         ))->render();

    //         $mpdf->WriteHTML($html);

    //         // 7. Save PDF
    //         $mpdf->Output($filePath, 'F');

    //         // 8. Return public URL
    //         return asset('storage/' . $publicPath . $fileName);

    //     } catch (\Mpdf\MpdfException $e) {
    //         \Log::error('mPDF error: ' . $e->getMessage());
    //         return null;
    //     }
    // }

    public function generateQuotationInvoice(QuotationModel $quotation, float $subTotal, float $taxAmount)
    {
        // 1. Customer info
        $q_name    = $quotation->q_user;
        $q_email   = $quotation->q_email;
        $q_mobile  = $quotation->q_mobile;
        $q_address = $quotation->q_address;
        $q_total   = $quotation->total_amount;   // total
        $shipping = $quotation->shipping_amount ?? 0;
        $discount = $quotation->discount_amount ?? 0;

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

        // PDF file storage location
        $sanitizedId = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $quotation->id), '-');
        $publicPath  = 'upload/invoice_quotations/';
        $fileName    = 'invoice_' . $sanitizedId . '.pdf';
        $filePath    = storage_path('app/public/' . $publicPath . $fileName);

        // Ensure directory
        if (!File::isDirectory(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true, true);
        }

        // Remove old file
        if (File::exists($filePath)) File::delete($filePath);

        try {
            $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);

            // ⬇ Pass tax + subtotal + total into view
            $html = view('pdf.quotation_invoice', compact(
                'q_name','q_email','q_mobile','q_address',
                'q_items','quotation','subTotal','taxAmount','q_total','discount','shipping'
            ))->render();

            $mpdf->WriteHTML($html);
            $mpdf->Output($filePath, 'F');

            return asset('storage/' . $publicPath . $fileName);

        } catch (\Exception $e) {
            \Log::error('PDF Gen Error: '.$e->getMessage());
            return null;
        }
    }

    public function updateOrderStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status'           => 'nullable|in:pending,completed,cancelled,refunded',
            'payment_status'   => 'nullable|in:pending,paid,failed',
            'delivery_status'  => 'nullable|in:pending,accepted,arrived,completed,cancelled',
        ]);

        $order = OrderModel::find($id);

        if (!$order) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'Order not found.',
                'data'    => [],
            ], 404);
        }

        $oldStatus        = $order->status;
        $oldPaymentStatus = $order->payment_status;

        // Update fields
        $order->update(array_filter([
            'status'          => $validated['status'] ?? $order->status,
            'payment_status'  => $validated['payment_status'] ?? $order->payment_status,
            'delivery_status' => $validated['delivery_status'] ?? $order->delivery_status,
        ]));

        /**
         * 🔥 Invoice Generation Condition Updated
         * - Status changed from PENDING → COMPLETED
         * - Payment status is NOT 'pending'   (means paid/failed/whatever next)
         * - Invoice not already generated
         */
        if (
            $oldStatus === 'pending' &&
            $order->status === 'completed' &&
            $order->payment_status !== 'pending' &&
            !$order->invoice_id
        ) {
            $this->generateOrderInvoice($order);
            $order->refresh();
        }

        /** 🔹 Find invoice file URL if exists */
        $invoice = null;
        if ($order->invoice_id) {
            $upload = UploadModel::find($order->invoice_id);
            if ($upload) {
                $invoice = [
                    'id'  => $upload->id,
                    'url' => asset('storage/' . $upload->file_path),
                ];
            }
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Order status updated successfully!',
            'data'    => [
                'id'              => $order->id,
                'status'          => $order->status,
                'payment_status'  => $order->payment_status,
                'delivery_status' => $order->delivery_status,
                'invoice'         => $invoice,  // 🔥 Full URL returned
                'updated_at'      => $order->updated_at->toIso8601String(),
            ],
        ], 200);
    }

    private function generateOrderInvoice(OrderModel $order): void
    {
        if ($order->invoice_id) return; // avoid duplicate

        $order->loadMissing(['user','items.product','items.variant']);

        // ===== INVOICE NUMBER ===== //
        $dt    = $order->created_at ?? now();
        $year  = $dt->format('Y');
        $month = $dt->format('m');

        $orderIdStr = (string) $order->id;
        $series = strlen($orderIdStr) < 4 ? str_pad($orderIdStr, 4, '0', STR_PAD_LEFT) : $orderIdStr;

        $invoiceNumber = "HAN-INV-{$year}-{$month}-{$series}";
        $fileName      = $invoiceNumber . '.pdf';
        $relativePath  = 'upload/order_invoice/' . $fileName;
        $fullPath      = storage_path('app/public/' . $relativePath);

        // ===== PRICE BREAKUP ===== //
        $total    = (float) $order->total_amount;
        $taxRate  = 0.18;

        if ($total > 0) {
            $subTotal  = round($total / (1 + $taxRate), 2);
            $taxAmount = round($total - $subTotal, 2);
        } else {
            $subTotal  = 0.00;
            $taxAmount = 0.00;
        }

        // === ⭐ Shipping & Discount Rules ⭐ ===
        $shippingCharge = $subTotal < 5000 ? 120 : 0;
        $discount       = 0; // default

        // Net + shipping
        $grandTotal = $subTotal + $taxAmount + $shippingCharge - $discount;

        // Ensure directory
        if (!File::isDirectory(dirname($fullPath))) {
            File::makeDirectory(dirname($fullPath), 0755, true, true);
        }

        try {
            $mpdf = new \Mpdf\Mpdf([
                'format'        => 'A4',
                'default_font'  => 'dejavusans',
                'margin_top'    => 0,
                'margin_bottom' => 0,
            ]);

            $html = view('pdf.order_invoice', [
                'order'          => $order,
                'user'           => $order->user,
                'items'          => $order->items,
                'invoiceNumber'  => $invoiceNumber,

                // 🔥 send all values to blade
                'subTotal'       => $subTotal,
                'taxAmount'      => $taxAmount,
                'shippingCharge' => $shippingCharge,
                'discount'       => $discount,
                'grandTotal'     => $grandTotal,
                'totalAmount'    => $total, // original saved total
            ])->render();

            $mpdf->WriteHTML($html);
            $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);

        } catch (\Mpdf\MpdfException $e) {
            \Log::error('mPDF order invoice error: '.$e->getMessage(), ['order_id'=>$order->id]);
            return;
        }

        $size = File::exists($fullPath) ? File::size($fullPath) : 0;

        $upload = UploadModel::create([
            'file_path' => $relativePath,
            'type'      => 'order_invoice',
            'size'      => $size,
            'alt_text'  => $invoiceNumber
        ]);

        $order->invoice_id = $upload->id;
        $order->save();
    }


    
    // private function generateOrderInvoice(OrderModel $order): void
    // {
    //     // Don't create duplicate invoice
    //     if ($order->invoice_id) return;

    //     $order->loadMissing(['user', 'items.product', 'items.variant']);

    //     // Use order created_at for year & month (fallback to now if null)
    //     $dt    = $order->created_at ?? now();
    //     $year  = $dt->format('Y');
    //     $month = $dt->format('m');

    //     // Series: minimum 4 digits (left-pad with zeros if needed)
    //     $orderIdStr = (string) $order->id;
    //     if (strlen($orderIdStr) < 4) {
    //         $series = str_pad($orderIdStr, 4, '0', STR_PAD_LEFT);
    //     } else {
    //         $series = $orderIdStr; // already >= 4 digits
    //     }

    //     $invoiceNumber = "HAN-INV-{$year}-{$month}-{$series}";
    //     $fileName      = $invoiceNumber . '.pdf';
    //     $relativePath  = 'upload/order_invoice/' . $fileName;
    //     $fullPath      = storage_path('app/public/' . $relativePath);

    //     // Ensure directory exists
    //     if (!File::isDirectory(dirname($fullPath))) {
    //         File::makeDirectory(dirname($fullPath), 0755, true, true);
    //     }

    //     try {
    //         $mpdf = new Mpdf([
    //             'format'        => 'A4',
    //             'default_font'  => 'dejavusans',
    //             'margin_top'    => 0,
    //             'margin_bottom' => 0,
    //         ]);

    //         $html = view('pdf.order_invoice', [
    //             'order' => $order,
    //             'user'  => $order->user,
    //             'items' => $order->items,
    //             'invoiceNumber' => $invoiceNumber, // 👈 you can use this inside Blade
    //         ])->render();

    //         $mpdf->WriteHTML($html);
    //         $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);
    //     } catch (\Mpdf\MpdfException $e) {
    //         \Log::error('mPDF order invoice error: ' . $e->getMessage(), [
    //             'order_id' => $order->id,
    //         ]);
    //         return; // don’t set invoice_id if PDF failed
    //     }

    //     $size = File::exists($fullPath) ? File::size($fullPath) : 0;

    //     $upload = UploadModel::create([
    //         'file_path' => $relativePath,
    //         'type'      => 'order_invoice',  // or 'pdf' if you prefer
    //         'size'      => $size,
    //         'alt_text'  => $invoiceNumber,
    //     ]);

    //     $order->invoice_id = $upload->id;
    //     $order->save();
    // }

}
