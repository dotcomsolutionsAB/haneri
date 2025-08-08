<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation Invoice - {{ $q_name }}</title>
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
            /* margin-bottom: 10px; */
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

        .item_table th, .item_table td {
            font-size: 12px;
            padding: 6px 5px;
            text-align: center;
        }
        .item_table th{
            border: 2px solid #eee;
        }

        .item_table th {
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
            /* background-color: #315858; */
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

    {{-- Background image --}}
    <!-- @php
        $bgPath = public_path('logos/flower-removebg-preview.png');
        $bgType = pathinfo($bgPath, PATHINFO_EXTENSION);
        $bgData = file_get_contents($bgPath);
        $bgBase64 = 'data:image/' . $bgType . ';base64,' . base64_encode($bgData);
    @endphp -->
    <!-- <img src="{{ $bgBase64 }}" class="bg-image" /> -->

    {{-- Header --}}
    <div class="header">
        <table width="100%">
            <tr>
                <td class="w-50">
                    @php
                        $logoPath = public_path('https://haneri.com/images/Haneri%20Logo.png');
                        $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
                        $logoData = file_get_contents($logoPath);
                        $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
                    @endphp
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
                </td>
                <td class="w-50 text-right">
                    <strong>QUOTATION NUMBER:</strong> #HAN-20250001<br>
                    <strong>QUOTATION DATE:</strong> {{ now()->format('M d, Y, h:i A') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Quotation Label --}}
    <div class="content">
        <table class="invoice-title">
            <tr>
                <td>
                    <div class="folded-corner">
                        <div class="top"></div>
                        <div class="label">Quotation</div>
                        <div class="bottom"></div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Bill Details --}}
        <table width="100%" style="table-layout: fixed; margin-top: 5px; font-family: 'DejaVu Sans', sans-serif; font-size: 12px;">
            <tr>
                <!-- Left Box -->
                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                    <div style="border: 0px solid #ccc; padding: 10px; line-height: 1.4;">
                        <strong>Billed To:</strong><br>
                        <strong>{{ $q_name }}</strong><br>
                        {{ $q_email }}<br>
                        Tel: {{ $q_mobile ?? '+91 xxxxx-xxxxx' }}<br>

                        <strong>Ship To:</strong><br>
                        {{ q_address }} , INDIA<br>
                    </div>
                </td>

                <!-- Right Box -->
                <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                    <!-- <div style="border: 0px solid #ccc; padding: 10px; line-height: 1.4; float: right; min-width: 220px;">
                        <strong>ORDER:</strong> #{{ $order->order_code }}<br>
                        <strong>ORDER DATE:</strong> {{ $order->created_at->format('M d, Y, h:i A') }}<br>
                        <strong>Shipping:</strong> {{ $order->shipping_type ?? 'Home' }}<br>
                        <strong>Payment:</strong> {{ $order->payment_status ?? 'N/A' }}
                    </div> -->
                </td>
            </tr>
        </table>

        {{-- Items --}}
        <table class="item_table">
            <thead>
                <tr>
                    <th>##</th>
                    <th>ITEM</th>
                    <th>VARIATION</th>
                    <th>RATE</th>
                    <th>QTY</th>
                    <th class="text-right">SUBTOTAL (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($q_items as $item)
                @php
                    $base64Image = null;
                    if (!empty($item->image_link)) {
                        $imagePath = public_path('https://haneri.com/images/Haneri%20Logo.png');
                        if (file_exists($imagePath)) {
                            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
                            $data = file_get_contents($imagePath);
                            $base64Image = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        }
                    }
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <table width="100%">
                            <tr>
                                <td style="width: 40px;">
                                    @if ($base64Image)
                                        <img src="{{ $base64Image }}" class="product-image">
                                    @endif
                                </td>
                                <td class="product-info" style="text-align: left;">
                                    <strong>{{ $item->product_name }}</strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        {{ $item->variant_value }}
                    </td>
                    <td>
                        {{ $item->rate }}
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td class="text-right">₹{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Summary --}}
        <!-- <div class="summary">
            @php
                $subtotal = $order->grand_total - $order->tax_price - $order->shipping_charge;
            @endphp
            <h4>SUBTOTAL: ₹{{ number_format($subtotal, 2) }}</h4>
            <h4>TAX: ₹{{ number_format($order->tax_price, 2) }}</h4>
            <h4>SHIPPING & HANDLING: ₹{{ number_format($order->shipping_charge, 2) }}</h4>
            <h4>DISCOUNT: ₹ 0.00</h4>
            <h4 class="grand-total">GRAND TOTAL: ₹{{ number_format($order->grand_total, 2) }}</h4>
        </div> -->
    </div>

    {{-- Signature Section --}}
    <!-- @php
        $invoiceUrl = $order->invoice_link;
    @endphp -->
    <table width="100%" style="margin-top: 40px; padding: 20px 40px;">
        @php
            $signLogoPath = public_path('https://haneri.com/images/Haneri%20Logo.png'); // same logo or a small variant
            $signLogoType = pathinfo($signLogoPath, PATHINFO_EXTENSION);
            $signLogoData = file_get_contents($signLogoPath);
            $signLogoBase64 = 'data:image/' . $signLogoType . ';base64,' . base64_encode($signLogoData);
        @endphp
        <tr>
            
            <td style="width: 50%; text-align: left;">
                <!--  -->
            </td>

            <td style="width: 50%; text-align: right; vertical-align: bottom;">
                <div style="border-top: 1px solid #333; width: 200px; margin-left: auto; margin-bottom: 5px;"></div>
                <div style="margin-bottom: 10px; font-size: 12px;">Authorized Signatory</div>
                <img src="{{ $signLogoBase64 }}" alt="Signature Logo" style="height: 40px;"><br>
                <div style="font-size: 11px; margin-top: 2px;"><strong>HANERI ELECTRICALS LLP</strong></div>
            </td>
        </tr>
    </table>

    {{-- Footer Area --}}
    <div class="footer" style="font-size: 11px; line-height: 1.5;">
        <strong>HANERI ELECTRICALS LLP</strong> &nbsp; | &nbsp;
        Corporate Office: A-48, SECTOR 57, NOIDA, UTTAR PRADESH, PINCODE - 201301<br>
        Email: <a href="mailto:support@liwaas.com" style="color: #fff; text-decoration: underline;">info@haneri.in</a> &nbsp; | &nbsp;
        Phone: +91 9876543210
    </div>



</body>
</html>