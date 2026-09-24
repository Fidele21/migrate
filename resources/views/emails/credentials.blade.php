<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ $isReset ? 'Password reset' : 'Your account' }}</title>
</head>
{{-- Inline styles throughout: mail clients strip stylesheets. --}}
<body style="margin:0;padding:0;background:#f4f5f7;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="max-width:560px;background:#ffffff;border:1px solid #e2e5ea;border-radius:6px;overflow:hidden;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;">

          <tr>
            <td style="background:#00539F;padding:20px 28px;">
              <div style="color:#ffffff;font-size:17px;font-weight:700;">City of Kigali</div>
              <div style="color:#cfe0f2;font-size:13px;">Inspection Data Management System</div>
            </td>
          </tr>

          <tr>
            <td style="padding:28px;">
              <p style="margin:0 0 16px;font-size:15px;color:#1d2330;">
                Dear {{ $user->name }},
              </p>

              @if($isReset)
                <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#41485a;">
                  Your password for the Inspection Data Management System has been reset
                  @if($issuedBy) by {{ $issuedBy }}@endif. Sign in with the temporary password
                  below. You will be asked to set a password of your own before you can
                  continue, and any other sessions on your account have been signed out.
                </p>
              @else
                <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#41485a;">
                  An account has been created for you on the Inspection Data Management System
                  @if($roleName) as <strong>{{ $roleName }}</strong>@endif. Sign in with the
                  temporary password below. You will be asked to set a password of your own
                  before you can continue.
                </p>
              @endif

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f7f9fc;border:1px solid #e2e5ea;border-radius:5px;margin:0 0 20px;">
                <tr>
                  <td style="padding:18px 20px;">
                    <div style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280;">Sign in with</div>
                    <div style="font-size:15px;font-weight:600;color:#1d2330;padding:3px 0 14px;">{{ $user->email }}</div>

                    <div style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280;">Temporary password</div>
                    <div style="font-family:Consolas,Menlo,monospace;font-size:22px;font-weight:700;letter-spacing:.09em;color:#00539F;padding-top:3px;">{{ $temporaryPassword }}</div>
                  </td>
                </tr>
              </table>

              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
                <tr>
                  <td style="background:#00539F;border-radius:4px;">
                    <a href="{{ $signInUrl }}"
                       style="display:inline-block;padding:11px 24px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;">
                      Sign in
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#6b7280;">
                If the button does not work, open
                <a href="{{ $signInUrl }}" style="color:#00539F;">{{ $signInUrl }}</a>
              </p>

              <p style="margin:18px 0 0;padding-top:16px;border-top:1px solid #eceef2;font-size:13px;line-height:1.6;color:#6b7280;">
                Keep this password to yourself and do not forward this message. If you were
                not expecting it, contact the Inspection Unit — someone may have entered your
                address by mistake.
              </p>
            </td>
          </tr>

          <tr>
            <td style="background:#f7f9fc;border-top:1px solid #e2e5ea;padding:16px 28px;">
              <div style="font-size:12px;color:#8b93a3;">
                City of Kigali — Inspection Unit. This message was sent automatically; replies are not monitored.
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
