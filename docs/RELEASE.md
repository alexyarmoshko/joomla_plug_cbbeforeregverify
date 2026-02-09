# Yak Shaver CB Before Registration Email Verify v0.1.0

Pre-registration email verification gateway for Community Builder. Requires guests to verify their email address via a 6-digit code before accessing the CB registration form.

## Features

- **Two-step verification flow**: Email entry → Code verification → Registration
- **No user creation before verification**: Users are only created after successful code verification
- **Joomla Mail Templates integration**: Customizable verification emails via System → Mail Templates
- **Configurable rate limiting**: Independent controls for IP, email, resend cooldown, and failed attempts
- **CSRF protection**: All form submissions and state-changing actions are CSRF-protected
- **Session security**: Session ID regeneration after successful verification
- **Auto-confirmation**: Verified users skip CB's redundant email confirmation step
- **Verified email enforcement**: Registration form email field is locked to the verified address
- **Automatic cleanup**: Configurable purge of old verification records

## Requirements

- Joomla 5.x
- Community Builder 2.11+
- PHP 8.1+

## Installation

1. Upload `plug_cbbeforeregverify-v0-1-0.zip` via CB Plugin Manager
2. Configure the secret key in plugin settings (required)
3. Enable the gateway

## Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| Secret | — | Required HMAC key for code hashing |
| TTL | 900s | Code expiration time (15 minutes) |
| Code length | 6 | Verification code digits |
| Purge after days | 30 | Auto-cleanup threshold for old records |

### Rate Limits (all independently toggleable)

| Limiter | Default | Description |
|---------|---------|-------------|
| IP short | 5/15min | Requests per IP in short window |
| IP daily | 25/24h | Requests per IP in day window |
| Email short | 3/30min | Requests per email in short window |
| Email daily | 10/24h | Requests per email in day window |
| Resend cooldown | 60s | Minimum wait between resends |
| Failed attempts | 8 | Max incorrect codes per verification |

## Known Limitations

- IPv6 clients may bypass IP rate limits by rotating addresses within their /64 prefix
- Changing code length does not affect active verification codes
