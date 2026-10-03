<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $purpose }} OTP</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 24px;
            color: #333333;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 32px;
            border: 1px solid #e2e8f0;
        }
        .header {
            font-size: 20px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
        }
        .otp-box {
            background-color: #f7fafc;
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 24px 0;
        }
        .otp-code {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #2b6cb0;
        }
        .expiry-text {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
        }
        .footer {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 24px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">{{ config('app.name') }} - {{ $purpose }}</div>
        <p>Hello,</p>
        <p>Please use the following One-Time Password (OTP) to complete your request for <strong>{{ $purpose }}</strong>:</p>

        <div class="otp-box">
            <div class="otp-code">{{ $otp }}</div>
            <div class="expiry-text">This code will expire in 10 minutes.</div>
        </div>

        <p>If you did not make this request, please disregard this email or contact support if you suspect unauthorized activity.</p>
        <div class="footer">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
    </div>
</body>
</html>
