<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notificationTitle }}</title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Segoe UI, Tahoma, Arial, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f7; padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#9f1239;">
                            eDonate Admin Notification
                        </p>
                        <h1 style="margin:0 0 14px; font-size:22px; color:#7f1d1d;">{{ $notificationTitle }}</h1>
                        <p style="margin:0 0 14px; font-size:15px; line-height:1.6; color:#374151;">
                            Hello {{ $adminName }},
                        </p>
                        <p style="margin:0 0 18px; font-size:15px; line-height:1.7; color:#374151; white-space:pre-line;">
                            {{ $notificationMessage }}
                        </p>
                        @if ($relatedType && $relatedId)
                            <p style="margin:0 0 18px; font-size:13px; color:#6b7280;">
                                Reference: {{ $relatedType }} #{{ $relatedId }}
                            </p>
                        @endif
                        <p style="margin:0; font-size:13px; color:#6b7280;">
                            This email was sent because Email Notifications are enabled in your eDonate admin settings.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
