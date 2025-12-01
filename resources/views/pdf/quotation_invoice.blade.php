<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation - {{ $quotation_no }}</title>
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

        .page-wrap {
            padding: 20px 40px 80px 40px; /* bottom space for footer */
            box-sizing: border-box;
        }

        /* ========= HEADER ========= */
        .header-bar {
            background-color: #ffffff;    /* no green background */
            color: #333333;
            /* more height via padding, logo unchanged */
            padding: 24px 40px 20px 40px;
            border-bottom: 4px solid #315858;  /* green bottom strip */
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-logo {
            height: 40px; /* same size as before */
        }

        .header-right {
            text-align: right;
            font-size: 11px;
            line-height: 1.4;
        }

        .header-right strong {
            font-size: 11px;
        }

        /* ========= TITLE ========= */
        .title-row {
            margin-top: 12px;
            margin-bottom: 10px;
        }

        .title-text {
            font-size: 18px;
            font-weight: bold;
            color: #315858;
        }

        /* ========= BILLING BLOCK ========= */
        .bill-table {
            width: 100%;
            table-layout: fixed;
            margin-top: 5px;
            font-size: 12px;
        }

        .bill-box {
            vertical-align: top;
            padding: 4px 0;
            line-height: 1.5;
        }

        .bill-label {
            font-weight: bold;
            margin-bottom: 2px;
        }

        /* ========= ITEMS TABLE ========= */
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .item-table th,
        .item-table td {
            font-size: 11px;
            padding: 6px 5px;
            text-align: center;
        }

        .item-table thead th {
            background-color: #315858;
            color: #ffffff;
            border: 1px solid #d1d5db;
        }

        .item-table tbody td {
            border-bottom: 1px solid #e5e7eb;
        }

        .item-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .item-name-cell {
            text-align: left;
        }

        .product-inner-table {
            width: 100%;
            /* border-collapse: collapse; */
        }

        .product-img-td {
            width: 30px;
        }

        .product-image {
            width: 26px;
            height: 26px;
            object-fit: contain;
        }

        .product-info {
            padding-left: 8px;
            vertical-align: middle;
            line-height: 1.4;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* ========= SUMMARY ========= */
        .summary-wrap {
            margin-top: 15px;
            width: 100%;
        }

        .summary-inner {
            width: 40%;
            margin-left: auto;
            font-size: 11px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 4px 4px;
        }

        .summary-label {
            text-align: right;
            color: #444;
        }

        .summary-value {
            text-align: right;
            width: 120px;
        }

        /* FULL-WIDTH GRAND TOTAL BAR */
        .grand-total-bar {
            margin-top: 10px;
            background-color: #315858;
            color: #ffffff;
            padding: 10px 12px;
            font-weight: bold;
            font-size: 16px;  /* bigger font */
            text-align: right;
        }

        /* ========= FOOTER + SIGNATURE ========= */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            
            background-color: #315858;
            color: #ffffff;
            font-size: 10px;
            line-height: 1.5;
            box-sizing: border-box;
        }

        .footer-signature-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 6px;
            background-color:#fff;
            color:#000;
            padding: 10px 40px 10px 40px;
        }

        .footer-signature-block {
            text-align: right;
            font-size: 11px;
        }

        .footer-signature-line {
            border-top: 2px solid #315858; /* white line on green */
            width: 200px;
            margin-left: auto;
            margin-bottom: 4px;
        }

        .footer-text {
            text-align: center;
            font-size: 10px;
            padding: 10px 40px 10px 40px;
        }

        .footer a {
            color: #ffffff;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    {{-- HEADER STRIP (white with green bottom line) --}}
    <div class="header-bar">
        <table class="header-table">
            <tr>
                <td style="width: 50%; vertical-align: middle;">
                    <img src="{{ asset('storage/upload/logo/Haneri_Logo.png') }}" alt="Logo" class="header-logo">
                </td>
                <td style="width: 50%;" class="header-right">
                    <div><strong>QUOTATION #:</strong> {{ $quotation->quotation_no ?? '—' }}</div>
                    <div><strong>DATE:</strong> {{ optional($quotation->created_at)->format('M d, Y') ?? now()->format('M d, Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="page-wrap">
        {{-- TITLE ROW --}}
        <div class="title-row">
            <span class="title-text">Quotation</span>
        </div>

        {{-- BILLED TO (LEFT) & SHIP TO (RIGHT) IN SAME ROW --}}
        <table class="bill-table">
            <tr>
                <td class="bill-box" style="width: 50%;">
                    <div class="bill-label">Billed To:</div>
                    <strong>{{ $q_name }}</strong><br>
                    {{ $q_email }}<br>
                    Tel: {{ $q_mobile ?? '+91 xxxxx-xxxxx' }}<br>
                </td>
                <td class="bill-box" style="width: 50%; text-align:right;">
                    <div class="bill-label">Ship To:</div>
                    {{ $q_address }} , INDIA
                </td>
            </tr>
        </table>

        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 35px;">#</th>
                    <th style="text-align:left;">ITEM</th>
                    <th style="width: 100px;">VARIATION</th>
                    <th style="width: 70px;">RATE</th>
                    <th style="width: 50px;">QTY</th>
                    <th style="width: 90px;" class="text-right">SUBTOTAL (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($q_items as $item)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="item-name-cell">
                            <table class="product-inner-table">
                                <tr>
                                    <td class="product-img-td">
                                        <img src="{{ asset('storage/upload/logo/Haneri_Favicon.jpg') }}" class="product-image" alt="H">
                                    </td>
                                    <td class="product-info">
                                        <strong>{{ $item->product_name }}</strong>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td>{{ $item->variant_value }}</td>
                        <td>{{ number_format($item->rate, 2) }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td class="text-right">₹{{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- SUMMARY BLOCK (RIGHT) --}}
        <div class="summary-wrap">
            <div class="summary-inner">
                <table class="summary-table">

                    <tr>
                        <td class="summary-label">SUBTOTAL:</td>
                        <td class="summary-value">₹{{ number_format($subTotal ?? 0, 2) }}</td>
                    </tr>

                    <tr>
                        <td class="summary-label">TAX:</td>
                        <td class="summary-value">₹{{ number_format($taxAmount ?? 0, 2) }}</td>
                    </tr>

                    <tr>
                        <td class="summary-label">SHIPPING:</td>
                        <td class="summary-value">₹{{ number_format($shipping ?? 0, 2) }}</td>
                    </tr>

                    <tr>
                        <td class="summary-label">DISCOUNT:</td>
                        <td class="summary-value">₹{{ number_format($discount ?? 0, 2) }}</td>
                    </tr>

                </table>
            </div>
        </div>

        {{-- FULL-WIDTH GRAND TOTAL BAR --}}
        @php
            $finalTotal = ($subTotal ?? 0) + ($taxAmount ?? 0) + ($shipping ?? 0) - ($discount ?? 0);
        @endphp

        <div class="grand-total-bar">
            GRAND TOTAL: ₹{{ number_format($finalTotal, 2) }}
        </div>

        {{-- NOTE: Signature is now handled inside the footer --}}
    </div>

    {{-- FOOTER STRIP (WITH SIGNATURE INSIDE, JUST ABOVE CONTACT DETAILS) --}}
    <div class="footer">
        <div class="footer-signature-row">
            <div class="footer-signature-block">
                <div class="footer-signature-line"></div>
                <div style="margin-bottom: 4px;">Authorized Signatory</div>
                <div><strong>HANERI ELECTRICALS LLP</strong></div>
            </div>
        </div>
        <div class="div" style="height:30px; background-color:#fff;"></div>
        <div class="footer-text">
            <strong>HANERI ELECTRICALS LLP</strong> &nbsp; | &nbsp;
            Corporate Office: A-48, SECTOR 57, NOIDA, UTTAR PRADESH, PINCODE - 201301<br>
            Email:
            <a href="mailto:customercare@haneri.com" style="color: #fff; text-decoration: underline;">customercare@haneri.com</a>
            &nbsp; | &nbsp;
            Phone: +91 8377826826
        </div>
    </div>

</body>
</html>
