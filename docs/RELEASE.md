# Release Notes

## 0.1.2 (2026-10-16)

Fixes to the verification gateway: limits that hold under parallel requests, session handling, the mail template and several smaller issues.

### Fixes

- The attempt limit, the IP and email send limits and the resend cooldown now hold when requests arrive in parallel. Before, several parallel requests could all pass a limit check before any of them was recorded. Database named locks (`GET_LOCK`) now serialize each check with its write. The verification mail is sent after the locks are released.
- A verification request can no longer be overwritten by an older request running at the same time. A request leaves `pending` only through a conditional update.
- The session ID is rotated properly after a correct code, and the old session row is removed. A failed rotation fails the request.
- While an email is locked after too many wrong codes, no new code is sent, and the message says how long to wait.
- The resend cooldown applies to every new code for an email, whether it was requested by resend or by entering the email again.
- The mail template's insert buttons now insert `{CODE}` and `{MINUTES}`. On install, existing templates get the corrected tag list. Their subject and body are left as they are.
- A mail that throws an exception counts as a failed send.
- The "Use a different email" button on the registration form works without JavaScript.
- An email address containing `$1` is no longer mangled when the form is prefilled.
- A logged-in visitor, or a visit while registration is closed, gets Community Builder's own message.
- Errors are logged to `administrator/logs/cbbeforeregverify.php`.
- Stale rows are purged only once they are outside every enabled limit window.
- Removed the code field's `maxlength`, a leftover development string, and the lowercase language override keys that never matched.

### Requirements

- MySQL or MariaDB, because the limits use named locks. A multi-primary cluster such as Galera isn't supported, because named locks aren't shared between nodes.

### Testing

- New end-to-end tests in `tests/e2e/` sign up on a DDEV test site, and a parallel-request harness in `tests/concurrency/` checks the limits. Neither ships in the package.

### Upgrading

Install the 0.1.2 ZIP over 0.1.1 in Community Builder → Plugin Management. Settings and data are kept, and there is no database schema change.

## 0.1.1 (2026-10-13)

No functional changes. The shipped code is the same as in the published 0.1.0 package.

### Changes

- Release packages are reproducible: rebuilding tag `0.1.1` gives the same ZIP and sha256 on Linux and Windows.
- `library/Trigger/UserTrigger.php` now ships with LF line endings. 0.1.0 had mixed CRLF and LF.
- Removed `plug_cbbeforeregverify.update.xml`. Community Builder plugins have no update channel, so nothing used it.

### Upgrading

Install the 0.1.1 ZIP over 0.1.0 in Community Builder → Plugin Management. Settings and data are kept.

## 0.1.0 (2026-02-08)

Pre-registration email verification gateway for Community Builder. Requires guests to verify their email address via a 6-digit code before accessing the CB registration form.

### Features

- **Two-step verification flow**: Email entry → Code verification → Registration
- **No user creation before verification**: Users are only created after successful code verification
- **Joomla Mail Templates integration**: Customizable verification emails via System → Mail Templates
- **Configurable rate limiting**: Independent controls for IP, email, resend cooldown, and failed attempts
- **CSRF protection**: All form submissions and state-changing actions are CSRF-protected
- **Session security**: Session ID regeneration after successful verification
- **Auto-confirmation**: Verified users skip CB's redundant email confirmation step
- **Verified email enforcement**: Registration form email field is locked to the verified address
- **Automatic cleanup**: Configurable purge of old verification records

### Requirements

- Joomla 5.x
- Community Builder 2.11+
- PHP 8.1+

### Installation

1. Upload `plug_cbbeforeregverify-v0-1-0.zip` via CB Plugin Manager
2. Configure the secret key in plugin settings (required)
3. Enable the gateway

### Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| Secret | — | Required HMAC key for code hashing |
| TTL | 900s | Code expiration time (15 minutes) |
| Code length | 6 | Verification code digits |
| Purge after days | 30 | Auto-cleanup threshold for old records |

#### Rate Limits (all independently toggleable)

| Limiter | Default | Description |
|---------|---------|-------------|
| IP short | 5/15min | Requests per IP in short window |
| IP daily | 25/24h | Requests per IP in day window |
| Email short | 3/30min | Requests per email in short window |
| Email daily | 10/24h | Requests per email in day window |
| Resend cooldown | 60s | Minimum wait between resends |
| Failed attempts | 8 | Max incorrect codes per verification |

### Known Limitations

- IPv6 clients may bypass IP rate limits by rotating addresses within their /64 prefix
- Changing code length does not affect active verification codes
