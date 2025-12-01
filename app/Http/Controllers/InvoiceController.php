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
            $html = view('pdf.quotation_invoice', compact(
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

        // Keep track of old status to detect  transition
        $oldStatus = $order->status;

        // Update only provided fields
        $order->update(array_filter([
            'status'          => $validated['status'] ?? $order->status,
            'payment_status'  => $validated['payment_status'] ?? $order->payment_status,
            'delivery_status' => $validated['delivery_status'] ?? $order->delivery_status,
        ]));

        // 🔥 Generate invoice when order moves from pending → completed (and no invoice yet)
        if ($oldStatus === 'pending'
            && $order->status === 'completed'
            && !$order->invoice_id) {

            $this->generateOrderInvoice($order);
            // refresh to get invoice_id if changed
            $order->refresh();
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
                'invoice_id'      => $order->invoice_id,   // ✅ return it
                'updated_at'      => $order->updated_at->toIso8601String(),
            ],
        ], 200);
    }

    private function generateOrderInvoice(OrderModel $order): void
    {
        if ($order->invoice_id) return;

        $order->loadMissing(['user','items.product','items.variant']);

        $invoiceNumber = 'HAN-INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
        $fileName      = $invoiceNumber . '.pdf';
        $relativePath  = 'upload/order_invoice/' . $fileName;
        $fullPath      = storage_path('app/public/' . $relativePath);

        // Ensure directory exists
        if (!File::isDirectory(dirname($fullPath))) {
            File::makeDirectory(dirname($fullPath), 0755, true, true);
        }

        try {
            $mpdf = new Mpdf([
                'format'        => 'A4',
                'default_font'  => 'dejavusans',
                'margin_top'    => 0,
                'margin_bottom' => 0,
            ]);

            $html = view('pdf.order_invoice', [
                'order' => $order,
                'user'  => $order->user,
                'items' => $order->items,
            ])->render();

            $mpdf->WriteHTML($html);
            $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);
        } catch (\Mpdf\MpdfException $e) {
            \Log::error('mPDF order invoice error: ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);
            return; // don’t set invoice_id if PDF failed
        }

        $size = File::exists($fullPath) ? File::size($fullPath) : 0;

        $upload = UploadModel::create([
            'file_path' => $relativePath,
            'type'      => 'pdf',
            'size'      => $size,
            'alt_text'  => "$invoiceNumber",
        ]);

        $order->invoice_id = $upload->id;
        $order->save();
    }



}
