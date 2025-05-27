<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login to Subterra</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #2d3436;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #636e72;
            font-size: 16px;
        }
        .content {
            margin: 30px 0;
        }
        .login-button {
            display: inline-block;
            background: #4285f4;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            text-align: center;
        }
        .login-button:hover {
            background: #3367d6;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #636e72;
            font-size: 14px;
            text-align: center;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🗻 Subterra</div>
            <div class="subtitle">Your gateway to the underground world</div>
        </div>
        
        <div class="content">
            <h2>Login to Your Account</h2>
            <p>Hello!</p>
            <p>You requested a login link for Subterra. Click the button below to securely log in to your account:</p>
            
            <div class="button-container">
                <a href="{{ $magicLinkUrl }}" class="login-button">
                    🔐 Login to Subterra
                </a>
            </div>
            
            <div class="warning">
                <strong>Security Notice:</strong> This link will expire in 30 minutes for your security. If you didn't request this login link, you can safely ignore this email.
            </div>
            
            <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace;">
                {{ $magicLinkUrl }}
            </p>
        </div>
        
        <div class="footer">
            <p>This email was sent by Subterra. If you have any questions, please contact our support team.</p>
            <p>
                <a href="https://github.com/paperclipmonkey/subterra" style="color: #4285f4;">Subterra is open source</a>
            </p>
        </div>
    </div>
</body>
</html>
