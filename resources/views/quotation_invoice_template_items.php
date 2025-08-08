<!-- resources/views/quotation_invoice_template_items.blade.php -->
<html>
<head>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <table class="items-table">
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
            @foreach ($q_items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->variant_value }}</td>
                    <td>{{ $item->rate }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->total }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
