# CB Before Registration Verify

## Overview

CB Before Registration Verify adds a pre-registration email verification gateway in front of Community Builder registration.

Flow:

1. Guest enters email.
2. Plugin sends a verification code.
3. Guest submits code.
4. CB registration form is unlocked only after successful verification.
5. If needed, user can restart verification from the locked email field and switch to a different email.

No Joomla/CB user is created before verification completes.

## Features

- Email-first registration gateway.
- Verification code submission, resend, cancel, and restart actions.
- TTL-based verification expiry.
- Failed-attempt tracking with limiter.
- Independent IP and email send rate limiters.
- Resend cooldown limiter.
- Registration save enforcement (verified email only).
- Explicit cancel action on both step-1 and step-2 screens.
- Explicit `Use a different email` restart control on locked registration email state.
- Runtime purge of old terminal verification rows.

## Requirements

- Joomla 5.x or 6.x
- Community Builder
- PHP version supported by your Joomla + CB stack
- Mail delivery configured in Joomla

## Installation

1. Build package with `make dist` (or use an existing release zip in `installation/`).
2. In Community Builder plugin manager, install the generated plugin zip.
3. Publish plugin `Yak Shaver CB Before Registration Email Verify`.
4. Configure plugin parameters (especially `secret`) before going live.

## Configuration Notes

- `gateway_enabled`: toggles gateway behavior.
- `verification_ttl_sec`: validity period for active verification requests.
- `secret`: required for hashing verification codes (`sha256(code + secret)`).
- `purge_after_days`: retention for terminal rows (`verified`, `cancelled`, `failed`).
- Rate limit and attempt controls are independently switchable.

## Update Server Manifest

- Update feed file: `plug_cbbeforeregverify.update.xml`
- Typical URL on GitHub raw:
  `https://raw.githubusercontent.com/alexyarmoshko/joomla_plug_cbbeforeregverify/main/plug_cbbeforeregverify.update.xml`

## Packaging (Git Bash)

- `make info`   : show resolved version and output paths.
- `make dist`   : full release preparation.
- `make clean`  : remove generated files.

Generated artifacts:
- `installation/plug_cbbeforeregverify-v<version>.zip`

## Release Process

1. Update plugin release in `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.xml` (`<release>` tag).
   Keep `<version>` as the minimum compatible Community Builder version.
2. Run `make dist`.
3. Commit updated manifest and release assets.
4. Create GitHub release tag matching version.
5. Upload generated zip from `installation/`.

## License

GNU/GPL v2
