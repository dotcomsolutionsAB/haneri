<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Status Update - Order #{{ $order->id }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <h2>Hello {{ $user->name }},</h2>
    <p>We wanted to let you know that the status of your order #{{ $order->id }} has been updated.</p>
    <p><strong>Order Status:</strong> {{ ucfirst($status) }}</p>
    <p><strong>Payment Status:</strong> {{ ucfirst($payment_status) }}</p>
    <p>If you have any questions or concerns, feel free to reach out to our support team.</p>
    <p>Thank you for shopping with us!</p>
</body>
</html>
