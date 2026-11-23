@php
    $hsnTaxableTotal = 0.0;
    $hsnCgstTotal = 0.0;
    $hsnSgstTotal = 0.0;
    $hsnIgstTotal = 0.0;
    $hsnTaxTotal = 0.0;
    $hsnQtyTotal = 0;
    $showIntra = !empty($taxSplit['intra']);
    if (!empty($hsnRows)) {
        foreach ($hsnRows as $hsnRow) {
            $hsnTaxableTotal += (float) $hsnRow['taxable'];
            $hsnCgstTotal += (float) $hsnRow['cgst'];
            $hsnSgstTotal += (float) $hsnRow['sgst'];
            $hsnIgstTotal += (float) $hsnRow['igst'];
            $hsnTaxTotal += (float) $hsnRow['tax'];
            $hsnQtyTotal += (int) $hsnRow['qty'];
        }
    }
@endphp
<div style="padding: 0 36px 8px 36px; font-family: dejavusans; color: #1a2e2c;">
    <table width="100%" style="border-collapse: collapse; border-top: 1px solid #d7e3e1;">
        <tr>
            <td width="52%" valign="bottom" style="padding: 12px 14px 0 0;">
                @if(!empty($hsnRows))
                    <div style="font-size: 8px; letter-spacing: 1.1px; color: #1f4e4a; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">
                        HSN wise summary
                    </div>
                    <table width="100%" style="border-collapse: collapse; font-size: 9px;">
                        <thead>
                            <tr>
                                <th style="background: #1f4e4a; color: #fff; text-align: left; padding: 5px 6px; font-size: 8px; letter-spacing: 0.4px;">HSN/SAC</th>
                                <th style="background: #1f4e4a; color: #fff; text-align: center; padding: 5px 6px; font-size: 8px; width: 48px;">Qty</th>
                                <th style="background: #1f4e4a; color: #fff; text-align: right; padding: 5px 6px; font-size: 8px; width: 70px;">Taxable</th>
                                @if($showIntra)
                                    <th style="background: #1f4e4a; color: #fff; text-align: right; padding: 5px 6px; font-size: 8px; width: 58px;">CGST 9%</th>
                                    <th style="background: #1f4e4a; color: #fff; text-align: right; padding: 5px 6px; font-size: 8px; width: 58px;">SGST 9%</th>
                                @else
                                    <th style="background: #1f4e4a; color: #fff; text-align: right; padding: 5px 6px; font-size: 8px; width: 58px;">IGST 18%</th>
                                @endif
                                <th style="background: #1f4e4a; color: #fff; text-align: right; padding: 5px 6px; font-size: 8px; width: 64px;">Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hsnRows as $hsnRow)
                                <tr>
                                    <td style="padding: 5px 6px; border-bottom: 1px solid #e6eeec;">{{ $hsnRow['hsn_label'] }}</td>
                                    <td style="padding: 5px 6px; border-bottom: 1px solid #e6eeec; text-align: center;">{{ $hsnRow['qty'] }} NOS</td>
                                    <td style="padding: 5px 6px; border-bottom: 1px solid #e6eeec; text-align: right;">{{ number_format($hsnRow['taxable'], 2) }}</td>
                                    @if($showIntra)
                                        <td style="padding: 5px 6px; border-bottom: 1px solid #e6eeec; text-align: right;">{{ number_format($hsnRow['cgst'], 2) }}</td>
                                        <td style="padding: 5px 6px; border-bottom: 1px solid #e6eeec; text-align: right;">{{ number_format($hsnRow['sgst'], 2) }}</td>
                                    @else
                                        <td style="padding: 5px 6px; border-bottom: 1px solid #e6eeec; text-align: right;">{{ number_format($hsnRow['igst'], 2) }}</td>
                                    @endif
                                    <td style="padding: 5px 6px; border-bottom: 1px solid #e6eeec; text-align: right;">{{ number_format($hsnRow['tax'], 2) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td style="padding: 5px 6px; border-top: 1px solid #1f4e4a; font-weight: bold;">Total</td>
                                <td style="padding: 5px 6px; border-top: 1px solid #1f4e4a; font-weight: bold; text-align: center;">{{ $hsnQtyTotal }} NOS</td>
                                <td style="padding: 5px 6px; border-top: 1px solid #1f4e4a; font-weight: bold; text-align: right;">{{ number_format($hsnTaxableTotal, 2) }}</td>
                                @if($showIntra)
                                    <td style="padding: 5px 6px; border-top: 1px solid #1f4e4a; font-weight: bold; text-align: right;">{{ number_format($hsnCgstTotal, 2) }}</td>
                                    <td style="padding: 5px 6px; border-top: 1px solid #1f4e4a; font-weight: bold; text-align: right;">{{ number_format($hsnSgstTotal, 2) }}</td>
                                @else
                                    <td style="padding: 5px 6px; border-top: 1px solid #1f4e4a; font-weight: bold; text-align: right;">{{ number_format($hsnIgstTotal, 2) }}</td>
                                @endif
                                <td style="padding: 5px 6px; border-top: 1px solid #1f4e4a; font-weight: bold; text-align: right;">{{ number_format($hsnTaxTotal, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </td>
            <td width="48%" valign="bottom" align="right" style="padding: 12px 0 0 14px;">
                <table align="right" width="176" style="border-collapse: collapse;">
                    <tr>
                        <td align="right" style="font-size: 10px; padding: 0 0 2px 0;">
                            For <strong>{{ $sellerName }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td align="right" style="padding: 2px 4px 0 0; height: 34px;">
                            @if(!empty($signPath))
                                <img src="{{ $signPath }}" alt="" width="68" height="26">
                            @else
                                &nbsp;
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td align="right" style="border-top: 1px solid #1f4e4a; padding-top: 4px; font-size: 9px; color: #5c6b69;">
                            Authorised signatory
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
<div style="text-align: center; font-size: 8px; color: #5c6b69; font-family: dejavusans; padding: 8px 36px 6px 36px;">
    This is a computer generated invoice. &nbsp;·&nbsp; Subject to Gautam Budh Nagar jurisdiction.
</div>
<div style="background: #1f4e4a; color: #ffffff; font-size: 8.5px; text-align: center; line-height: 26px; font-family: dejavusans;">
    {{ $sellerName }}
    &nbsp;&nbsp;·&nbsp;&nbsp;{{ config('blade_static_data.company.phone') }}
    &nbsp;&nbsp;·&nbsp;&nbsp;{{ strtolower((string) config('blade_static_data.company.support_email')) }}
</div>
