<?php

namespace App\Http\Controllers;
use App\Models\QuotationModel;
use App\Models\QuotationItemModel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Illuminate\Http\Request;
use App\Models\OrderModel;
use App\Models\UploadModel;
use App\Models\PaymentModel;
use App\Mail\OrderStatusUpdate;
use App\Models\EmailLog;
use App\services\GstInvoice;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{

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

    public function regenerateOrderInvoice(int $id)
    {
        try {
            $order = OrderModel::find($id);

            if (!$order) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Order not found.',
                    'data' => [],
                ], 404);
            }

            $status = strtolower((string) $order->status);
            $paymentStatus = strtolower((string) $order->payment_status);

            if ($status !== 'completed' || $paymentStatus === 'pending') {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Invoice can only be regenerated for completed, paid orders.',
                    'data' => [],
                ], 422);
            }

            $this->generateOrderInvoice($order, true);
            $order->refresh();

            $invoice = null;
            if ($order->invoice_id) {
                $upload = UploadModel::find($order->invoice_id);
                if ($upload && $upload->file_path) {
                    $invoice = [
                        'id' => $upload->id,
                        'url' => asset('storage/' . ltrim($upload->file_path, '/')),
                    ];
                }
            }

            if (!$invoice) {
                return response()->json([
                    'code' => 500,
                    'success' => false,
                    'message' => 'Invoice generation finished but no PDF file was saved.',
                    'data' => [],
                ], 500);
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Invoice regenerated successfully.',
                'data' => [
                    'invoice' => $invoice,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('regenerateOrderInvoice failed: '.$e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to regenerate invoice: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function updateOrderStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status'           => 'nullable|in:pending,processing,dispatched,completed,cancelled,refunded',
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

        $nextStatus = $validated['status'] ?? $order->status;
        $nextPayment = $validated['payment_status'] ?? $order->payment_status;

        // Paid orders move from pending → processing unless admin chose another status.
        if (
            $nextPayment === 'paid'
            && !array_key_exists('status', $validated)
            && strtolower((string) $order->status) === 'pending'
        ) {
            $nextStatus = 'processing';
        }

        // Update fields
        $order->update(array_filter([
            'status'          => $nextStatus,
            'payment_status'  => $nextPayment,
            'delivery_status' => $validated['delivery_status'] ?? $order->delivery_status,
        ]));

        // Keep payment records in sync when admin changes payment status
        if (array_key_exists('payment_status', $validated) && $validated['payment_status']) {
            try {
                PaymentModel::where('order_id', $order->id)->update([
                    'status' => $validated['payment_status'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync payment record status: '.$e->getMessage(), [
                    'order_id' => $order->id,
                    'payment_status' => $validated['payment_status'],
                ]);
            }
        }

        /**
         * Invoice generation / refresh
         * - Completed + payment not pending
         * - Create if missing; regenerate when an invoice already exists so coupon/discount fixes apply
         */
        if (
            $order->status === 'completed' &&
            $order->payment_status !== 'pending' &&
            (
                !$order->invoice_id ||
                ($oldStatus === 'pending' || array_key_exists('payment_status', $validated))
            )
        ) {
            $this->generateOrderInvoice($order, (bool) $order->invoice_id);
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

        // Log status update
        Log::info("Order ID {$order->id} status updated to {$order->status}, payment status to {$order->payment_status}");

        // Send status update email if status changed
        if ($oldStatus !== $order->status || $oldPaymentStatus !== $order->payment_status) {
            Log::info('Order status or payment status has changed. Sending email.');
            $user = $order->user;
            try {
                Mail::to($user->email)->send(new OrderStatusUpdate($order, $user, $order->status, $order->payment_status));
                EmailLog::record($user->email, OrderStatusUpdate::class, 'sent', [
                    'recipient_user_id' => $user->id,
                    'subject'           => 'Order Status Updated - Order #' . $order->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('OrderStatusUpdate email failed: ' . $e->getMessage());
                EmailLog::record($user->email, OrderStatusUpdate::class, 'failed', [
                    'recipient_user_id' => $user->id,
                    'error_message'     => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('No status or payment status change. No email sent.');
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
    
    private function generateOrderInvoice(OrderModel $order, bool $force = false): void
    {
        if ($order->invoice_id && !$force) {
            return;
        }

        $order->loadMissing(['user', 'items.product', 'items.variant']);

        $fallbackLogo = storage_path('app/public/upload/logo/Haneri_Favicon.jpg');
        if (!is_file($fallbackLogo)) {
            $fallbackLogo = public_path('storage/upload/logo/Haneri_Favicon.jpg');
        }

        foreach ($order->items as $item) {
            $photoUrl = is_file($fallbackLogo) ? $fallbackLogo : null;
            if ($item->variant && $item->variant->photo_id) {
                $firstPhotoId = trim((string) explode(',', (string) $item->variant->photo_id)[0]);
                if ($firstPhotoId !== '') {
                    $photo = UploadModel::find($firstPhotoId);
                    if ($photo && $photo->file_path) {
                        $absolute = storage_path('app/public/' . ltrim($photo->file_path, '/'));
                        if (is_file($absolute)) {
                            $photoUrl = $absolute;
                        }
                    }
                }
            }
            if ($item->variant) {
                $item->variant->photo_url = $photoUrl;
            }
        }

        $existingUpload = $order->invoice_id
            ? UploadModel::find($order->invoice_id)
            : null;

        if ($existingUpload && $existingUpload->alt_text) {
            $invoiceNumber = $existingUpload->alt_text;
            $relativePath = $existingUpload->file_path
                ?: ('upload/order_invoice/' . $invoiceNumber . '.pdf');
        } else {
            $dt = $order->created_at ?? now();
            $year = $dt->format('Y');
            $month = $dt->format('m');
            $orderIdStr = (string) $order->id;
            $series = strlen($orderIdStr) < 4
                ? str_pad($orderIdStr, 4, '0', STR_PAD_LEFT)
                : $orderIdStr;
            $invoiceNumber = "HAN-INV-{$year}-{$month}-{$series}";
            $relativePath = 'upload/order_invoice/' . $invoiceNumber . '.pdf';
        }

        $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
        $fullPath = storage_path('app/public/' . $relativePath);

        $itemsSubtotal = (float) $order->items->sum(function ($item) {
            return ((float) $item->price) * ((int) $item->quantity);
        });
        $shippingCharge = round((float) ($order->shipping_charge ?? 0), 2);
        $storedDiscount = round((float) ($order->discount_amount ?? 0), 2);
        $inferredDiscount = max(
            0,
            round($itemsSubtotal + $shippingCharge - (float) $order->total_amount, 2)
        );
        $discount = $storedDiscount > 0 ? $storedDiscount : $inferredDiscount;

        // Merchandise after coupon is tax-inclusive; split for display
        $merchandiseIncl = max(0, round($itemsSubtotal - $discount, 2));
        $taxRate = 0.18;
        if ($merchandiseIncl > 0) {
            $subTotal = round($merchandiseIncl / (1 + $taxRate), 2);
            $taxAmount = round($merchandiseIncl - $subTotal, 2);
        } else {
            $subTotal = 0.00;
            $taxAmount = 0.00;
        }

        $total = (float) $order->total_amount;
        $grandTotal = round($subTotal + $taxAmount + $shippingCharge, 2);

        $seller = SettingController::sellerDetails();
        $sellerAddress = $seller['address'];
        if ($sellerAddress === '' || stripos($sellerAddress, 'your full warehouse') !== false) {
            $sellerAddress = trim((string) config('blade_static_data.company.address'));
        }
        $sellerState = GstInvoice::sellerState($seller['gstin'], $seller['state'], $sellerAddress);
        $ship = GstInvoice::shippingBlock((string) ($order->shipping_address ?? ''));
        $taxSplit = GstInvoice::taxSplit($taxAmount, $sellerState, $ship['state']);
        $logo = storage_path('app/public/upload/logo/Haneri_Logo.png');
        if (!is_file($logo)) {
            $logo = public_path('storage/upload/logo/Haneri_Logo.png');
        }

        if (!File::isDirectory(dirname($fullPath))) {
            File::makeDirectory(dirname($fullPath), 0755, true, true);
        }

        try {
            $tempDir = storage_path('app/mpdf');
            if (!File::isDirectory($tempDir)) {
                File::makeDirectory($tempDir, 0755, true, true);
            }

            $mpdf = new \Mpdf\Mpdf([
                'format' => 'A4',
                'default_font' => 'dejavusans',
                'margin_top' => 0,
                'margin_bottom' => 0,
                'tempDir' => $tempDir,
            ]);

            $html = view('pdf.order_invoice', [
                'order' => $order,
                'user' => $order->user,
                'items' => $order->items,
                'invoiceNumber' => $invoiceNumber,
                'itemsSubtotal' => $itemsSubtotal,
                'subTotal' => $subTotal,
                'taxAmount' => $taxAmount,
                'taxSplit' => $taxSplit,
                'shippingCharge' => $shippingCharge,
                'discount' => $discount,
                'couponCode' => $order->coupon_code,
                'grandTotal' => $grandTotal,
                'totalAmount' => $total,
                'sellerName' => $seller['name'] !== '' ? $seller['name'] : 'Haneri Electricals LLP',
                'sellerAddress' => $sellerAddress,
                'sellerGstin' => $seller['gstin'],
                'sellerState' => $sellerState,
                'ship' => $ship,
                'statusLabel' => GstInvoice::statusLabel($order->status, $order->delivery_status),
                'amountInWords' => GstInvoice::amountInWords($total),
                'logoPath' => is_file($logo) ? $logo : null,
            ])->render();

            $mpdf->WriteHTML($html);
            $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);
        } catch (\Throwable $e) {
            Log::error('mPDF order invoice error: '.$e->getMessage(), [
                'order_id' => $order->id,
            ]);
            throw new \RuntimeException(
                'Unable to write invoice PDF: '.$e->getMessage(),
                0,
                $e
            );
        }

        if (!File::exists($fullPath)) {
            throw new \RuntimeException('Invoice PDF was not created on disk.');
        }

        $size = File::size($fullPath);

        if ($existingUpload) {
            $existingUpload->update([
                'file_path' => $relativePath,
                'type' => 'order_invoice',
                'size' => $size,
                'alt_text' => $invoiceNumber,
            ]);
            return;
        }

        $upload = UploadModel::create([
            'file_path' => $relativePath,
            'type' => 'order_invoice',
            'size' => $size,
            'alt_text' => $invoiceNumber,
        ]);

        $order->invoice_id = $upload->id;
        $order->save();
    }

}
