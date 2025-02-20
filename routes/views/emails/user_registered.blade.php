<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Our Store</title>
</head>
<body>
    <h2>Hello {{ $userName }},</h2>
    <p>Thank you for registering with us! Your account has been successfully created.</p>
    
    <h3>Your Login Credentials:</h3>
    <p><strong>Email:</strong> {{ $userEmail }}</p>
    <p><strong>Password:</strong> {{ $userPassword }}</p>

    <p><strong>⚠️ Please change your password after logging in.</strong></p>

    <p>Best Regards,<br>
    The Support Team</p>
</body>
</html>
