<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.verify_new_email_subject') }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: #1f2937;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9fafb;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #e07a5f 0%, #cc5500 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .content {
            background: white;
            padding: 40px 30px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #e07a5f 0%, #cc5500 100%);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 12px;
            margin: 24px 0;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px 0 rgba(224, 122, 95, 0.3);
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px 0 rgba(224, 122, 95, 0.4);
        }
        .footer {
            background: #f8fafc;
            padding: 30px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 20px;
            border-radius: 12px;
            margin: 24px 0;
            border-left: 4px solid #f59e0b;
        }
        .url-box {
            word-break: break-all;
            background: #fef7f0;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 14px;
            border: 1px solid #fed7aa;
        }
        .brand {
            color: #ffffff;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="brand">fragfolio</div>
            <h1>{{ __('auth.verify_new_email_title') }}</h1>
        </div>

        <div class="content">
            <p>{{ __('auth.hello') }} {{ $user->name ?? '' }},</p>

            <p>{{ __('auth.verify_new_email_message', ['current_email' => $currentEmail]) }}</p>

            <div class="warning">
                <strong>{{ __('auth.important') }}:</strong> {{ __('auth.verify_new_email_warning') }}
            </div>

            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="button">
                    {{ __('auth.verify_new_email_button') }}
                </a>
            </div>

            <p>{{ __('auth.verify_email_manual_instruction') }}:</p>
            <div class="url-box">
                {{ $verificationUrl }}
            </div>

            <p>{{ __('auth.verify_email_expire_notice') }}</p>
        </div>

        <div class="footer">
            <p>{{ __('auth.email_footer') }}</p>
            <p>{{ __('auth.no_reply_notice') }}</p>
        </div>
    </div>
</body>
</html>