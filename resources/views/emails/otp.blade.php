<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $purpose }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 40px 20px;
            color: #374151;
            line-height: 1.6;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px 32px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            text-align: center;
        }
        .header {
            font-size: 28px;
            font-weight: 800;
            color: #002b5b;
            margin-bottom: 24px;
            letter-spacing: -0.5px;
        }
        p {
            font-size: 16px;
            margin: 0 0 24px 0;
            color: #4b5563;
        }
        .otp-highlight {
            display: inline-block;
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            background-color: #f3f4f6;
            padding: 12px 24px;
            border-radius: 8px;
            letter-spacing: 4px;
            margin: 8px 0;
        }
        .footer {
            font-size: 13px;
            color: #9ca3af;
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">Roamer</div>
        
        @if($purpose === 'Email Verification' || $purpose === 'Email Verification Resend')
            <p>Hi {{ $name }}, welcome to Roamer!</p>
        @else
            <p>Hi {{ $name }}, here is your {{ $purpose }} code!</p>
        @endif

        <p>Your verification code is:<br>
        <span class="otp-highlight">{{ $otp }}</span><br>
        <span style="font-size: 14px; color: #6b7280;">It expires in 3 minutes.</span></p>

        <p style="font-size: 14px; margin-bottom: 0;">If you didn't create a Roamer account, you can safely ignore this email.</p>
        
        <div class="footer">
            &copy; {{ date('Y') }} Roamer. All rights reserved.
        </div>
    </div>
</body>
</html>
