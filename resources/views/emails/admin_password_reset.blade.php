<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Password Reset</title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Segoe UI, Tahoma, Arial, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f7; padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                <tr>
                    <td style="padding:24px;">
                        <h1 style="margin:0 0 12px; font-size:22px; color:#b91c1c;">Reset Your Admin Password</h1>
                        <p style="margin:0 0 14px; font-size:15px; line-height:1.6; color:#374151;">
                            Hi {{ $fullName }}, we received a request to reset your admin account password.
                        </p>
                        <p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#374151;">
                            Click the button below to set a new password. This link expires in {{ $expiryMinutes }} minutes.
                        </p>
                        <p style="margin:0 0 18px; text-align:center;">
                            <a href="{{ $resetUrl }}" style="display:inline-block; background:#b91c1c; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 22px; border-radius:8px;">Reset Password</a>
                        </p>
                        <p style="margin:0 0 8px; font-size:13px; color:#6b7280; word-break:break-all;">
                            If the button does not work, open this link:<br>{{ $resetUrl }}
                        </p>
                        <p style="margin:0; font-size:13px; color:#6b7280;">
                            If you did not request this, you can ignore this email.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
