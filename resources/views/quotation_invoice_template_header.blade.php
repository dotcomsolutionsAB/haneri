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



<table class="customer-info">
    <tr>
        <td>Client:</td><td>{{ $user->name }}</td>
        <td>Client:</td><td>{{ $user->email }}</td>
        <td>Order ID:</td><td>{{ $order->order_id }}</td>
    </tr>
    <tr>
        <td>Address:</td><td>{{ $user->address_line_1 }} {{ $user->address_line_2 }}</td>
        <td>Order Date:</td><td>{{ \Carbon\Carbon::parse($order->order_date)->format('d-m-Y') }}</td>
    </tr>
    <tr>
        <td>Mobile:</td><td>{{ $user->mobile }}</td>
        <td>Type:</td><td>{{ strtoupper($order->type) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="value" style="background: {{ $order->remarks ? 'yellow' : 'white' }};"><strong>Remarks:</strong> {{ $order->remarks }}</td>
    </tr>
</table>

<table class="order-summary">
    <thead>
        <tr>
            <th class="center-align">SN</th>
            <th>Photo</th>
            <th>Product Name</th>
            <th class="center-align">Qty</th>
            <th class="right-align">Unit Price (Rs.)</th>
            <th class="right-align">Total (Rs.)</th>
        </tr>
    </thead>
    <tbody>
