<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice {{ $invoiceNumber }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a2e2c;
            margin: 0;
            padding: 0;
        }
        .sheet { padding: 28px 36px 56px 36px; }
        .rule { border: 0; border-top: 1px solid #d7e3e1; margin: 14px 0; }
        .brand { color: #1f4e4a; }
        .muted { color: #5c6b69; }
        .label {
            font-size: 9px;
            letter-spacing: 1.2px;
            color: #1f4e4a;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .meta-table, .party-table, .items, .totals, .sign {
            width: 100%;
            border-collapse: collapse;
        }
        .items th {
            background: #1f4e4a;
            color: #ffffff;
            font-size: 9px;
            letter-spacing: 0.6px;
            font-weight: bold;
            text-align: left;
            padding: 8px 8px;
        }
        .items td {
            padding: 9px 8px;
            border-bottom: 1px solid #e6eeec;
            vertical-align: top;
            font-size: 11px;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals td { padding: 3px 0; font-size: 11px; }
        .words {
            margin-top: 10px;
            background: #f4f8f7;
            padding: 8px 12px;
            font-size: 11px;
        }
        .total-bar {
            margin-top: 8px;
            background: #1f4e4a;
            color: #ffffff;
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            padding: 10px 12px;
        }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: #1f4e4a;
            color: #ffffff;
            font-size: 8.5px;
            text-align: center;
            padding: 8px 36px;
        }
    </style>
</head>
<body>
@php
    $email = strtolower((string) ($user->email ?? ''));
    $payment = ucfirst(strtolower((string) ($order->payment_status ?? 'pending')));
    $place = $ship['state'] ?? null;
@endphp
<div class="sheet">
    <table class="meta-table">
        <tr>
            <td style="width: 55%; vertical-align: middle;">
                @if(!empty($logoPath))
                    <img src="{{ $logoPath }}" alt="Haneri" style="height: 34px;">
                @else
                    <div style="font-size: 22px; letter-spacing: 4px; font-weight: bold;" class="brand">HANERI</div>
                @endif
            </td>
            <td style="width: 45%; text-align: right; vertical-align: middle;">
                <div class="brand" style="font-size: 18px; letter-spacing: 2px; font-weight: bold;">TAX INVOICE</div>
                <div style="margin-top: 4px;">{{ $invoiceNumber }}</div>
                <div class="muted">{{ optional($order->created_at)->format('d M Y') ?? now()->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <hr class="rule">

    <table class="party-table">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 16px;">
                <div class="label">Sold by</div>
                <strong>{{ $sellerName }}</strong><br>
                <span class="muted">{{ $sellerAddress }}</span><br>
                <span style="display: inline-block; margin-top: 6px;">GSTIN: {{ $sellerGstin !== '' ? $sellerGstin : '—' }}</span><br>
                <span>State: {{ $sellerState['name'] }} ({{ $sellerState['code'] }})</span>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div class="label">Billed to</div>
                <strong>{{ $user->name ?? '—' }}</strong><br>
                @if($email !== '')
                    {{ $email }}<br>
                @endif
                @if(!empty($user->mobile))
                    Tel: {{ $user->mobile }}<br>
                @endif
                @if(!empty($user->gstin))
                    GSTIN: {{ strtoupper($user->gstin) }}<br>
                @endif
                <div class="label" style="margin-top: 10px;">Ship to</div>
                @if(!empty($ship['name']) && strcasecmp($ship['name'], (string) ($user->name ?? '')) !== 0)
                    <strong>{{ $ship['name'] }}</strong><br>
                @endif
                @foreach($ship['lines'] as $line)
                    {{ $line }}<br>
                @endforeach
                @if(!empty($ship['phone']))
                    Tel: {{ $ship['phone'] }}<br>
                @endif
                <span>Place of supply: {{ $place ? $place['name'].' ('.$place['code'].')' : '—' }}</span>
            </td>
        </tr>
    </table>

    <table class="meta-table" style="margin-top: 14px; background: #f4f8f7;">
        <tr>
            <td style="padding: 8px 12px; width: 33%;">
                <span class="muted">Order</span><br>
                <strong>{{ $order->id }}</strong>
            </td>
            <td style="padding: 8px 12px; width: 33%;">
                <span class="muted">Payment</span><br>
                <strong>{{ $payment }}</strong>
            </td>
            <td style="padding: 8px 12px; width: 34%;">
                <span class="muted">Status</span><br>
                <strong>{{ $statusLabel }}</strong>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 16px;">
        <thead>
            <tr>
                <th style="width: 28px;">#</th>
                <th>Description</th>
                <th style="width: 72px;">HSN</th>
                <th style="width: 80px;">Variant</th>
                <th class="right" style="width: 72px;">Rate</th>
                <th class="center" style="width: 36px;">Qty</th>
                <th class="right" style="width: 84px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php
                    $rate = (float) ($item->price ?? 0);
                    $qty = (int) ($item->quantity ?? 0);
                @endphp
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td><strong>{{ optional($item->product)->name ?? 'Product #'.$item->product_id }}</strong></td>
                    <td>{{ \App\services\GstInvoice::formatHsn(optional($item->variant)->hsn) }}</td>
                    <td>{{ optional($item->variant)->variant_value ?? optional($item->variant)->value ?? '—' }}</td>
                    <td class="right">{{ number_format($rate, 2) }}</td>
                    <td class="center">{{ $qty }}</td>
                    <td class="right">{{ number_format($rate * $qty, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 12px;">
        <tr>
            <td style="width: 62%;"></td>
            <td class="muted right">Items subtotal (incl. GST)</td>
            <td class="right" style="width: 100px;">₹{{ number_format($itemsSubtotal, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="muted right">Discount{{ !empty($couponCode) ? ' ('.$couponCode.')' : '' }}</td>
            <td class="right">₹{{ number_format($discount, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="muted right">Taxable value</td>
            <td class="right">₹{{ number_format($subTotal, 2) }}</td>
        </tr>
        @if($taxSplit['intra'])
            <tr>
                <td></td>
                <td class="muted right">CGST @ 9%</td>
                <td class="right">₹{{ number_format($taxSplit['cgst'], 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="muted right">SGST @ 9%</td>
                <td class="right">₹{{ number_format($taxSplit['sgst'], 2) }}</td>
            </tr>
        @else
            <tr>
                <td></td>
                <td class="muted right">IGST @ 18%</td>
                <td class="right">₹{{ number_format($taxSplit['igst'], 2) }}</td>
            </tr>
        @endif
        <tr>
            <td></td>
            <td class="muted right">Shipping</td>
            <td class="right">₹{{ number_format($shippingCharge, 2) }}</td>
        </tr>
    </table>

    <div class="total-bar">
        Grand total&nbsp;&nbsp;₹{{ number_format($totalAmount, 2) }}
    </div>
    <div class="words">
        <span class="muted">Amount in words</span><br>
        <strong>{{ $amountInWords }}</strong>
    </div>

    <table class="sign" style="margin-top: 28px;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; text-align: right;">
                For <strong>{{ $sellerName }}</strong>
                <table style="width: 190px; margin-left: auto; margin-top: 36px;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="border-top: 1px solid #1f4e4a; padding-top: 6px; text-align: right;">
                            Authorised signatory
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    {{ $sellerName }}
    @if($sellerAddress !== '')
        &nbsp;&nbsp;·&nbsp;&nbsp;{{ $sellerAddress }}
    @endif
    &nbsp;&nbsp;·&nbsp;&nbsp;{{ config('blade_static_data.company.phone') }}
    &nbsp;&nbsp;·&nbsp;&nbsp;{{ strtolower((string) config('blade_static_data.company.support_email')) }}
</div>
</body>
</html>
