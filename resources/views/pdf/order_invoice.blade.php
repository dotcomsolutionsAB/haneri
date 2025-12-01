<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Order Invoice - HAN-INV-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .bg-image {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 400px;
            height: 550px;
            transform: translate(-50%, -50%);
            z-index: -1;
            opacity: 0.1;
        }

        .content {
            padding: 20px 40px;
            box-sizing: border-box;
        }

        .header {
            background-color: #231f20;
            color: white;
            padding: 10px 40px;
        }

        .invoice-title {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            padding: 0px 10px;
        }

        .invoice-box {
            width: 48%;
            border: 1px solid #eee;
            padding: 5px;
            box-sizing: border-box;
            vertical-align: top;
        }

        .invoice-box h4 {
            font-size: 12px;
            color: #3b3b3b;
        }

        .invoice-box p {
            margin: 0 0 6px;
            font-size: 12px;
        }

        .item_table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .item_table th,
        .item_table td {
            font-size: 12px;
            padding: 6px 5px;
            text-align: center;
        }

        .item_table th {
            border: 2px solid #eee;
            background-color: #315858;
            color: white;
        }

        .summary {
            text-align: right;
            margin-top: 20px;
        }

        .summary h4 {
            margin: 5px 0;
            font-size: 12px;
        }

        .grand-total {
            font-size: 14px;
            font-weight: bold;
            background-color: #315858;
            color: white;
            padding: 10px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 60px;
            background-color: #315858;
            color: #fff;
            font-size: 12px;
            text-align: center;
        }

        .folded-corner {
            position: relative;
            height: 40px;
            width: 100%;
            vertical-align: middle !important;
        }

        .folded-corner .top {
            width: 0;
            height: 0;
            border-top: 40px solid #9d9c9c;
            border-right: 40px solid transparent;
            position: absolute;
            top: 0;
            left: 0;
        }

        .folded-corner .label {
            height: 50px;
            line-height: 40px;
            padding-left: 50px;
            color: #3b3b3b;
            font-size: 20px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        .folded-corner .bottom {
            width: 0;
            height: 0;
            border-bottom: 40px solid #3b3b3b;
            border-right: 40px solid transparent;
            position: absolute;
            top: 0;
            left: 0;
        }

        .product-image {
            width: 40px;
            height: auto;
        }

        .product-info {
            vertical-align: top;
            padding-left: 10px;
            line-height: 1.5;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .logo {
            height: 100px;
        }

        .w-50 {
            width: 50%;
        }
    </style>
</head>

<body>

    {{-- Header --}}
    <div class="header">
        <table width="100%">
            <tr>
                <td class="w-50">
                    <img src="{{ asset('storage/upload/logo/Haneri_Logo.png') }}" alt="Logo" class="logo">
                </td>
                <td class="w-50 text-right">
                    <strong>INVOICE NUMBER:</strong> #HAN-INV-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}<br>
                    <strong>INVOICE DATE:</strong> {{ optional($order->created_at)->format('M d, Y, h:i A') ??
                    now()->format('M d, Y, h:i A') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Invoice Label --}}
    <div class="content">
        <table class="invoice-title">
            <tr>
                <td>
                    <div class="folded-corner">
                        <div class="top"></div>
                        <div class="label">Tax Invoice</div>
                        <div class="bottom"></div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Bill Details --}}
        <table width="100%"
            style="table-layout: fixed; margin-top: 5px; font-family: 'DejaVu Sans', sans-serif; font-size: 12px;">
            <tr>
                {{-- Left Box --}}
                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                    <div style="border: 0px solid #ccc; padding: 10px; line-height: 1.4;">
                        <strong>Billed To:</strong><br>
                        <strong>{{ $user->name ?? '-' }}</strong><br>
                        {{ $user->email ?? '-' }}<br>
                        Tel: {{ $user->mobile ?? '+91 xxxxx-xxxxx' }}<br>

                        <strong>Ship To:</strong><br>
                        {{ $order->shipping_address ?? 'INDIA' }}<br>
                    </div>
                </td>

                {{-- Right Box – summary --}}
                <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                    <div style="border: 0px solid #ccc; padding: 10px; line-height: 1.4;">
                        <strong>Order ID:</strong> {{ $order->id }}<br>
                        <strong>Payment Status:</strong> {{ strtoupper($order->payment_status ?? 'PENDING') }}<br>
                        <strong>Delivery Status:</strong> {{ strtoupper($order->delivery_status ?? 'PENDING') }}<br>
                        <strong>Order Status:</strong> {{ strtoupper($order->status ?? 'PENDING') }}<br>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Items --}}
        <table class="item_table">
            <thead>
                <tr>
                    <th>ITEM</th>
                    <th>VARIATION</th>
                    <th>RATE (₹)</th>
                    <th>QTY</th>
                    <th class="text-right">SUBTOTAL (₹)</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotal = 0; @endphp

                @foreach($items as $item)
                @php
                $rate = $item->price ?? 0;
                $qty = $item->quantity ?? 0;
                $lineTotal = $rate * $qty;
                $grandTotal += $lineTotal;
                @endphp
                <tr>
                    <td>
                        <table width="100%">
                            <tr>
                                <td style="width: 40px;">
                                    {{-- If you have product image, replace src below --}}
                                    <img src="{{ asset('storage/upload/logo/Haneri_Favicon.jpg') }}" class="product-image">
                                </td>
                                <td class="product-info" style="text-align: left;">
                                    <strong>{{ optional($item->product)->name ?? 'Product #'.$item->product_id
                                        }}</strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        {{ optional($item->variant)->value ?? '-' }}
                    </td>
                    <td>
                        ₹{{ number_format($rate, 2) }}
                    </td>
                    <td>{{ $qty }}</td>
                    <td class="text-right">₹{{ number_format($lineTotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Summary --}}
        <div class="summary">
            {{-- If you later add tax / shipping, extend here --}}
            <h4><strong>Grand Total:</strong> ₹{{ number_format($order->total_amount ?? $grandTotal, 2) }}</h4>
        </div>
    </div>

    {{-- Signature --}}
    <table width="100%" style="margin-top: 40px; padding: 20px 40px;">
        <tr>
            <td style="width: 50%; text-align: left;">
                {{-- Any terms & conditions or notes --}}
            </td>

            <td style="width: 50%; text-align: right; vertical-align: bottom;">
                <div style="border-top: 1px solid #333; width: 200px; margin-left: auto; margin-bottom: 5px;"></div>
                <div style="margin-bottom: 10px; font-size: 12px;">Authorized Signatory</div>
                <img src="{{ asset('storage/upload/logo/Haneri_Logo.png') }}" alt="Signature Logo"
                    style="height: 40px;"><br>
                <div style="font-size: 11px; margin-top: 2px;"><strong>HANERI ELECTRICALS LLP</strong></div>
            </td>
        </tr>
    </table>

    {{-- Footer Area --}}
    <div class="footer" style="font-size: 11px; line-height: 1.5;">
        <strong>HANERI ELECTRICALS LLP</strong> &nbsp; | &nbsp;
        Corporate Office: A-48, SECTOR 57, NOIDA, UTTAR PRADESH, PINCODE - 201301<br>
        Email: <a href="mailto:customercare@haneri.com" style="color: #fff; text-decoration: underline;">customercare@haneri.com</a>
        &nbsp; | &nbsp;
        Phone: +91 8377826826
    </div>

</body>

</html>