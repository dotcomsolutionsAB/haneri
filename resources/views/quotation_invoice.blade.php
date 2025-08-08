{{-- resources/views/quotation_invoice.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation Invoice</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .header   { text-align:center; margin-bottom:20px; }
        .title    { font-size:24px; font-weight:bold; }
        .details  { font-size:14px; margin-top:10px; }
        table     { width:100%; border-collapse:collapse; margin-top:20px; }
        table,th,td { border:1px solid #000; }
        th,td     { padding:8px; text-align:left; }
        th        { background:#f2f2f2; }
        .footer   { text-align:center; margin-top:30px; font-size:12px; color:#555; }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="title">Quotation Invoice</div>
    <div class="details">
        <p><strong>User:</strong> {{ $q_name }}</p>
        <p><strong>Email:</strong> {{ $q_email }}</p>
        <p><strong>Phone:</strong> {{ $q_mobile }}</p>
        <p><strong>Address:</strong> {{ $q_address }}</p>
    </div>
</div>

{{-- Items --}}
<table>
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Variant</th>
            <th>Rate</th>
            <th>Quantity</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($q_items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->variant_value }}</td>
                <td>{{ $item->rate }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Footer --}}
<div class="footer">
    <p>Thank you for doing business with us!</p>
    <p>If you have any questions, please contact us.</p>
</div>

</body>
</html>