<!-- resources/views/quotation_invoice_template_header.blade.php -->
<html>
<head>
    <style>
        /* You can include styles for the header here */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header .title {
            font-size: 24px;
            font-weight: bold;
        }
        .details {
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Quotation Invoice</div>
        <div class="details">
            <p><strong>User:</strong> {{ $q_user }}</p>
            <p><strong>Email:</strong> {{ $q_email }}</p>
            <p><strong>Phone:</strong> {{ $q_mobile }}</p>
            <p><strong>Address:</strong> {{ $q_address }}</p>
        </div>
    </div>
</body>
</html>
