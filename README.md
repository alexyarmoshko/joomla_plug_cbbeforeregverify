# CB Before Registration Verify

## Overview

CB Before Registration Verify adds an email verification gateway in front of Community Builder registration.

Current flow:

1. Guest enters an email on `step_email`.
2. Plugin issues and sends a numeric verification code.
3. Guest enters the code on `step_code` (with resend/cancel actions available).
4. On success, plugin marks the verification row as `verified`, regenerates the session ID, and redirects to CB registration.
5. Registration email is prefilled/locked to the verified address, with a CSRF-protected `Use a different email` restart action.
6. Registration submit is blocked unless the submitted email matches the verified session email.

No Joomla/CB user is created by this plugin before verification completes.

## Implemented Features

- Email-first registration gateway (`gateway_enabled` toggle).
- Verification actions: submit email, submit code, resend, cancel, restart.
- CSRF checks on all gateway actions (`submit_email`, `submit_code`, `resend`, `cancel`, `restart`) and registration save request.
- Exception details are logged server-side while user-facing gateway errors remain generic/localized.
- Code hashing with `HMAC-SHA256(code, secret)`.
- TTL-based expiry and automatic expiry handling.
- Failed-attempt row recording with configurable attempts limit.
- Independent IP-based and email-based send limits.
- Resend cooldown limiter.
- Runtime purge of stale rows older than purge threshold, while retaining unexpired `pending` verification rows.
- Joomla Mail Template support (`comprofiler.cbbeforeregverify.verification_code`) with fallback to `cbNotification`.
- At registration time, verified users are marked `confirmed = 1` when the verified email matches the registration email.

## Compatibility and Current Manifest Values

- Joomla target platform: `5.x` and `6.x` (from `plug_cbbeforeregverify.update.xml`).
- Community Builder compatibility (`<version>`): `2.11.0`.
- Plugin release (`<release>`): `0.1.0`.

## Requirements

- Joomla 5.x or 6.x
- Community Builder 2.11.0+
- PHP version supported by your Joomla + CB stack
- Working mail delivery in Joomla

## Installation

1. Build package with `make dist` (or use a release zip from `installation/`).
2. Install the generated zip in Community Builder plugin manager.
3. Publish plugin `Yak Shaver CB Before Registration Email Verify`.
4. Configure plugin parameters, especially `secret`, before enabling in production.

## Configuration Parameters

- `gateway_enabled` (default `1`): enable/disable the gateway.
- `verification_ttl_sec` (default `900`): active request TTL in seconds (runtime minimum `60`).
- `code_length` (default `6`): numeric code length (runtime minimum `4`).
- `secret` (default empty): required for issuing codes; used in `HMAC-SHA256(code, secret)`.
- `purge_after_days` (default `30`): stale-row retention window; unexpired pending rows are retained (runtime minimum is bounded by TTL window).
- `rl_ip_enabled` (default `1`): enable IP send limits.
- `rl_ip_short_window_min` (default `15`), `rl_ip_short_max` (default `5`).
- `rl_ip_day_window_hours` (default `24`), `rl_ip_day_max` (default `25`).
- `rl_email_enabled` (default `1`): enable email send limits.
- `rl_email_short_window_min` (default `30`), `rl_email_short_max` (default `3`).
- `rl_email_day_window_hours` (default `24`), `rl_email_day_max` (default `10`).
- `rl_resend_enabled` (default `1`), `rl_resend_cooldown_sec` (default `60`).
- `rl_attempts_enabled` (default `1`), `rl_attempts_max` (default `8`).

## Update Server Manifest

- Feed file: `plug_cbbeforeregverify.update.xml`
- Typical raw URL:
  `https://raw.githubusercontent.com/alexyarmoshko/joomla_plug_cbbeforeregverify/main/plug_cbbeforeregverify.update.xml`

## Packaging (Git Bash)

- `make info`: show resolved version and output paths.
- `make dist`: build zip and refresh update manifest version/download URL/sha256.
- `make clean`: remove generated package zip.

Generated artifact:
- `installation/plug_cbbeforeregverify-v<release-with-dashes>.zip`

## Release Process

1. Update `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.xml`:
   set `<release>` to new plugin release (keep `<version>` as CB compatibility baseline).
2. Run `make dist`.
3. Commit updated `plug_cbbeforeregverify.update.xml` and package asset(s).
4. Create GitHub release/tag matching `<release>`.
5. Upload `installation/plug_cbbeforeregverify-v<release-with-dashes>.zip` to that release.

## License

GNU/GPL v2
