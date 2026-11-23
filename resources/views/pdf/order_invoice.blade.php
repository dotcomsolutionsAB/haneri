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
        .sheet { padding: 28px 36px 0 36px; }
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
        .meta-table, .party-table, .items, .totals, .sign, .hsn-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items th, .hsn-table th {
            background: #1f4e4a;
            color: #ffffff;
            font-size: 9px;
            letter-spacing: 0.6px;
            font-weight: bold;
            text-align: left;
            padding: 8px 8px;
        }
        .items td, .hsn-table td {
            padding: 9px 8px;
            border-bottom: 1px solid #e6eeec;
            vertical-align: top;
            font-size: 11px;
        }
        .hsn-table th { padding: 7px 8px; }
        .hsn-table td { padding: 7px 8px; font-size: 10px; }
        .hsn-table tfoot td {
            border-bottom: 0;
            border-top: 1px solid #1f4e4a;
            font-weight: bold;
            padding-top: 8px;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals td { padding: 3px 0; font-size: 11px; }
        .words {
            margin-top: 10px;
            background: #f4f8f7;
            padding: 10px 14px;
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
        .hsn-wrap {
            width: 50%;
            margin-top: 18px;
        }
        .hsn-wrap .label {
            margin-bottom: 6px;
        }
        .sheet-end {
            margin-top: 28px;
        }
        .sign-block {
            width: 168px;
            margin-left: auto;
            text-align: right;
            font-size: 10.5px;
        }
        .sign-block img {
            display: block;
            margin: 1px 8px -8px auto;
            width: 76px;
            height: 32px;
        }
        .sign-line {
            border-top: 1px solid #1f4e4a;
            padding-top: 4px;
            font-size: 9.5px;
            color: #5c6b69;
        }
        .disclaimer {
            margin-top: 22px;
            text-align: center;
            font-size: 8.5px;
            color: #5c6b69;
            line-height: 1.3;
        }
        .footer {
            margin-top: 10px;
            margin-left: -36px;
            margin-right: -36px;
            background: #1f4e4a;
            color: #ffffff;
            font-size: 8.5px;
            text-align: center;
            line-height: 26px;
            height: 26px;
            padding: 0 36px;
            overflow: hidden;
        }
    </style>
</head>
<body>
@php
    $email = strtolower((string) ($user->email ?? ''));
    $payment = ucfirst(strtolower((string) ($order->payment_status ?? 'pending')));
    $place = $ship['state'] ?? null;
    $gstin = strtoupper(trim((string) ($sellerGstin ?? '')));
    $boxes = (int) ($boxCount ?? 0);
    $hsnFallback = (string) ($defaultHsn ?? '841451');
    $copyLabels = $copyLabels ?? [
        'Original for the recipient',
        'Duplicate for the transporter',
        'Triplicate for the supplier',
    ];
@endphp
@foreach($copyLabels as $copyLabel)
<div class="sheet"@if(!$loop->last) style="page-break-after: always;"@endif>
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
                <div class="label" style="margin-top: 3px; letter-spacing: 1px;">{{ $copyLabel }}</div>
                <div style="margin-top: 6px;">{{ $invoiceNumber }}</div>
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
                <span style="display: inline-block; margin-top: 6px;">
                    GST No / GSTIN: {{ $gstin !== '' ? $gstin : '—' }}
                </span><br>
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
                    GST No / GSTIN: {{ strtoupper($user->gstin) }}<br>
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
            <td style="padding: 8px 12px; width: 25%;">
                <span class="muted">Order</span><br>
                <strong>{{ $order->id }}</strong>
            </td>
            <td style="padding: 8px 12px; width: 25%;">
                <span class="muted">Payment</span><br>
                <strong>{{ $payment }}</strong>
            </td>
            <td style="padding: 8px 12px; width: 25%;">
                <span class="muted">Status</span><br>
                <strong>{{ $statusLabel }}</strong>
            </td>
            <td style="padding: 8px 12px; width: 25%;">
                <span class="muted">No of boxes</span><br>
                <strong>{{ max(1, $boxes) }}</strong>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 16px;">
        <thead>
            <tr>
                <th style="width: 24px;">#</th>
                <th>Description</th>
                <th style="width: 58px;">HSN</th>
                <th style="width: 70px;">Variant</th>
                <th class="right" style="width: 78px;">Rate (incl. GST)</th>
                <th class="right" style="width: 72px;">Taxable value</th>
                <th class="center" style="width: 48px;">Qty</th>
                <th class="right" style="width: 78px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php
                    $rate = (float) ($item->price ?? 0);
                    $qty = (int) ($item->quantity ?? 0);
                    $lineIncl = round($rate * $qty, 2);
                    $lineTaxable = $lineIncl > 0 ? round($lineIncl / 1.18, 2) : 0.0;
                @endphp
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td><strong>{{ optional($item->product)->name ?? 'Product #'.$item->product_id }}</strong></td>
                    <td>{{ \App\services\GstInvoice::formatHsn(optional($item->variant)->hsn, $hsnFallback) }}</td>
                    <td>{{ optional($item->variant)->optionLabel() ?: '—' }}</td>
                    <td class="right">{{ number_format($rate, 2) }}</td>
                    <td class="right">{{ number_format($lineTaxable, 2) }}</td>
                    <td class="center">{{ $qty }} NOS</td>
                    <td class="right">{{ number_format($lineIncl, 2) }}</td>
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
            <td class="muted right">Shipping</td>
            <td class="right">₹{{ number_format($shippingCharge, 2) }}</td>
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
    </table>

    <div class="total-bar">
        Grand total&nbsp;&nbsp;₹{{ number_format($totalAmount, 2) }}
    </div>
    <div class="words">
        <span class="muted">Amount chargeable (in words)</span><br>
        <strong>{{ $amountInWords }}</strong>
    </div>

    @if(!empty($hsnRows))
        @php
            $hsnTaxableTotal = 0.0;
            $hsnCgstTotal = 0.0;
            $hsnSgstTotal = 0.0;
            $hsnIgstTotal = 0.0;
            $hsnTaxTotal = 0.0;
            $hsnQtyTotal = 0;
            foreach ($hsnRows as $hsnRow) {
                $hsnTaxableTotal += (float) $hsnRow['taxable'];
                $hsnCgstTotal += (float) $hsnRow['cgst'];
                $hsnSgstTotal += (float) $hsnRow['sgst'];
                $hsnIgstTotal += (float) $hsnRow['igst'];
                $hsnTaxTotal += (float) $hsnRow['tax'];
                $hsnQtyTotal += (int) $hsnRow['qty'];
            }
            $showIntra = !empty($taxSplit['intra']);
        @endphp
        <div class="hsn-wrap">
            <div class="label">HSN wise summary</div>
            <table class="hsn-table">
                <thead>
                    <tr>
                        <th>HSN/SAC</th>
                        <th class="center" style="width: 52px;">Qty</th>
                        <th class="right" style="width: 78px;">Taxable</th>
                        @if($showIntra)
                            <th class="right" style="width: 62px;">CGST 9%</th>
                            <th class="right" style="width: 62px;">SGST 9%</th>
                        @else
                            <th class="right" style="width: 62px;">IGST 18%</th>
                        @endif
                        <th class="right" style="width: 72px;">Tax</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hsnRows as $hsnRow)
                        <tr>
                            <td>{{ $hsnRow['hsn_label'] }}</td>
                            <td class="center">{{ $hsnRow['qty'] }} NOS</td>
                            <td class="right">{{ number_format($hsnRow['taxable'], 2) }}</td>
                            @if($showIntra)
                                <td class="right">{{ number_format($hsnRow['cgst'], 2) }}</td>
                                <td class="right">{{ number_format($hsnRow['sgst'], 2) }}</td>
                            @else
                                <td class="right">{{ number_format($hsnRow['igst'], 2) }}</td>
                            @endif
                            <td class="right">{{ number_format($hsnRow['tax'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="center">{{ $hsnQtyTotal }} NOS</td>
                        <td class="right">{{ number_format($hsnTaxableTotal, 2) }}</td>
                        @if($showIntra)
                            <td class="right">{{ number_format($hsnCgstTotal, 2) }}</td>
                            <td class="right">{{ number_format($hsnSgstTotal, 2) }}</td>
                        @else
                            <td class="right">{{ number_format($hsnIgstTotal, 2) }}</td>
                        @endif
                        <td class="right">{{ number_format($hsnTaxTotal, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <div class="sheet-end">
        <div class="sign-block">
            For <strong>{{ $sellerName }}</strong>
            @if(!empty($signPath))
                <img src="{{ $signPath }}" alt="Authorised signature" width="76" height="32">
            @else
                <div style="height: 32px;"></div>
            @endif
            <div class="sign-line">Authorised signatory</div>
        </div>
        <div class="disclaimer">
            This is a computer generated invoice. &nbsp;·&nbsp; Subject to Gautam Budh Nagar jurisdiction.
        </div>
        <div class="footer">
            {{ $sellerName }}
            &nbsp;&nbsp;·&nbsp;&nbsp;{{ config('blade_static_data.company.phone') }}
            &nbsp;&nbsp;·&nbsp;&nbsp;{{ strtolower((string) config('blade_static_data.company.support_email')) }}
        </div>
    </div>
</div>
@endforeach
</body>
</html>
