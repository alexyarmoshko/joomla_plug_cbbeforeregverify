# Yak Shaver CB Before Registration Email Verify

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

- Joomla target platform: `5.x` and `6.x`.
- Community Builder compatibility (`<version>`): `2.11.0`.
- Plugin release (`<release>`): `0.1.1`.

## Requirements

- Joomla 5.x or 6.x
- Community Builder 2.11.0+
- PHP version supported by your Joomla + CB stack
- Working mail delivery in Joomla

## Installation

1. Download the release ZIP from GitHub, or build one (see [Build](#build)).
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

## Updating

Community Builder plugins get no update notifications. Joomla's updater doesn't track CB plugins, and CB's own version check covers only Joomlapolis products. To update, install the newer ZIP over the existing one in Community Builder → Plugin Management.

## Build

Run from the repository root in a Linux shell or Git Bash on Windows. Requires `make`, `git`, `php`, `awk`, and `sha256sum` or `shasum`.

| Target | What it does |
|---|---|
| `make info` | Show the version and the paths the build uses. |
| `make lint` | Check that `PACKAGE_FILES` in the Makefile matches the files git tracks in the plugin directory, then syntax-check the PHP and the manifest XML. |
| `make test` | No automated tests; release testing is manual. |
| `make dist_dev` | Build a test package from the working tree into `installation/dev/`. |
| `make release` | Run `test` and `lint`, then tag the version. Needs a clean tree and a `## <version>` heading in `docs/RELEASE.md`. |
| `make dist_release` | Build the release package from the tag into `installation/release/` and print its sha256. Needs a clean tree with `HEAD` at the tag. |
| `make clean` | Remove `build/` and both package output directories. |

The version comes from `<release>` in the manifest. `<version>` is the Community Builder compatibility version (`2.11.0`).

When you add a file to the plugin, add it to `PACKAGE_FILES` too, or `make lint` fails.

### Reproducible packages

`dist_release` packages a `git archive` export of the tag with `tools/jzip.php`. Every ZIP entry gets the tag's commit date, so rebuilding a tag gives the same bytes and sha256 on any machine. One caveat: a PHP built against zlib-ng instead of zlib compresses differently.

`release` and `dist_release` accept only `INSTALL_DIR` and `BUILD_DIR` on the command line, and stop if any other build variable is overridden.

`tools/jzip.php` is the build's deterministic ZIP writer. It isn't shipped in the package.

### Releasing

1. Bump `<release>` in `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.xml`, add a `## <version>` section to `docs/RELEASE.md`, and commit.
2. Run `make release`.
3. Run `make dist_release`.
4. Push the tag, upload `installation/release/plug_cbbeforeregverify-v<version-with-dashes>.zip` as the GitHub release asset, and put its sha256 in the release description.

## License

GNU/GPL v2
