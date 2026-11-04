<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Fishline</title>
</head>
<body style="margin:0;padding:0;background:#eef3f6;font-family:Arial,Helvetica,sans-serif;color:#1a2b33;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f6;padding:28px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#0b4f6c;padding:28px 24px;text-align:center;">
                            @if(!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="Fishline" style="max-height:48px;">
                            @else
                                <strong style="font-size:24px;color:#ffffff;letter-spacing:0.5px;">Fishline</strong>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 28px;font-size:16px;line-height:1.7;">
                            <p style="margin:0 0 8px;font-size:22px;font-weight:bold;color:#0b4f6c;">
                                Welcome aboard
                            </p>
                            <p style="margin:0 0 16px;">
                                Hi {{ $name }},
                            </p>
                            <p style="margin:0 0 16px;">
                                Welcome to <strong>Fishline</strong>. Your account is ready —
                                we’re glad you’re here.
                            </p>
                            <p style="margin:0;color:#5a6b73;font-size:15px;">
                                Cast a line, share your catches, and enjoy the water with us.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px 28px;font-size:12px;color:#8a9aa3;text-align:center;border-top:1px solid #e8eef2;">
                            &copy; {{ $year }} Fishline
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
