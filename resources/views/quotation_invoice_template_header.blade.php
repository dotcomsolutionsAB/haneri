<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Invoice</title>
    <style>
        /* Include all relevant styling */
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; }
        .header { width: 100%; padding-top: 15px; }
        .header img { width: 100%; height: auto; }
        .customer-info, .order-summary { width: 100%; margin-top: 20px; border-collapse: collapse; border: 1px solid #ddd;}
        .order-summary th, .order-summary td { padding: 8px; border: 1px solid #ddd; }
        .center-align { text-align: center; }
        .right-align { text-align: right; }
        .footer { text-align: center; background-color: lightgrey; color: black; padding: 10px; font-size: 16px; }
        .order-title { text-align: center; font-size: 24px; font-weight: bold; margin: 20px 0 10px; }
        .customer-info td { border: 1px solid #ddd; padding: 2px; }
    </style>
</head>
<body>

<div class="order-title">Order Invoice</div>

<div class="header">
    <img src="{{ asset('storage/uploads/s1.jpg') }}" alt="Logo">
</div>

<!-- Customer Info -->
<table class="customer-info">
    <tr>
        <td>Client:</td><td>{{ $q_user }}</td>  <!-- Use $q_user for the customer's name -->
        <td>Client Email:</td><td>{{ $q_email }}</td>  <!-- Use $q_email for the customer's email -->
        <td>Order ID:</td><td>{{ $quotation->order_id }}</td>  <!-- Use $quotation for the order -->
    </tr>
    <tr>
        <td>Address:</td><td>{{ $q_address }}</td>  <!-- Use $q_address for the customer's address -->
        <td>Order Date:</td><td>{{ \Carbon\Carbon::parse($quotation->order_date)->format('d-m-Y') }}</td>  <!-- Use $quotation for the order date -->
    </tr>
    <tr>
        <td>Mobile:</td><td>{{ $q_mobile }}</td>  <!-- Use $q_mobile for the customer's mobile number -->
        <td>Order Type:</td><td>{{ strtoupper($quotation->type) }}</td>  <!-- Use $quotation for order type -->
    </tr>
</table>

<!-- Order Summary Table -->
<table class="order-summary">
    <thead>
        <tr>
            <th class="center-align">SN</th>
            <th>Product Name</th>
            <th class="center-align">Qty</th>
            <th class="right-align">Unit Price (Rs.)</th>
            <th class="right-align">Total (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        <!-- Loop through order items -->
         dd($q_items);
        @foreach($q_items as $index => $item)
        <tr>
            <td class="center-align">{{ $index + 1 }}</td>
            <td>{{ $item->product_name }}</td>
            <td class="center-align">{{ $item->quantity }}</td>
            <td class="right-align">₹ {{ number_format($item->rate, 2) }}</td>
            <td class="right-align">₹ {{ number_format($item->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
