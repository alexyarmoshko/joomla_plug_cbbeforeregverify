# End-to-end sign-up tests

Browser tests that sign up on a running test site. They check that a visitor who confirms their email can register, and that a visitor who hasn't confirmed it can't. The plan behind them is `docs/e2e_tests_plan.md`.

They aren't part of `make test`. They need a disposable Joomla + Community Builder site under DDEV, with Mailpit and the plugin installed.

## Tests

| Test | Checks |
|---|---|
| email step until verified | The registration page shows the gateway's email step, not the CB form. |
| wrong code | A wrong code keeps the visitor on the code step, and the CB form stays closed. |
| POST without verification | A direct POST to `saveregisters` with a valid form token is sent back to the email step, and no user is created. |
| other email than verified | Changing the read-only email before submitting is refused, and no user is created for either address. |
| change a verified email | "Use a different email" on the CB form resets verification. The gateway comes back, the new address must be confirmed, and the user is created with the new address only. |
| verified email registers | The CB form shows the verified email read-only. Submitting it creates the user with `confirmed = 1`, and the verification row's outcome is `verified`. |

The tests read verification codes from Mailpit's API.

## What a run changes on the site

- **Captcha field.** Before the tests, the run records whether CB's `antispam_captcha` field is published and unpublishes it if it is, because no test can solve reCAPTCHA. After the tests it puts back the recorded state. The state is kept in `.state/captcha.json` for the length of the run. If a run dies before restoring it, the next run refuses to start until you run `npm run restore`. The CB AntiSpam timer and honeypot stay on. The tests wait `E2E_FORM_DELAY_MS` before submitting.
- **Users and verification rows.** Before and after the run, `support/cb_cleanup.php` deletes every user whose email ends in `@example.test`. It deletes them through CB's `UserTable::delete()`, so CB plugins remove their own rows. CB Paid Subscriptions keeps a deleted user's payment basket and Kunena keeps its user row, so the script deletes those too. Then it deletes every `@example.test` row in the plugin's verification table. `tests/concurrency` uses the same domain. Use it for nothing else on the test site.
- **Mail.** The test messages stay in Mailpit.
- **Payment.** On a site with CB Paid Subscriptions, set `E2E_AFTER_REGISTER_CLICK` to the "pay offline" button. The registration test then clicks it, so no payment starts.

The run never reads the plugin's `secret`. It checks only that one is set.

## Requirements

- Node.js 22 or later, and `ddev` on the host.
- The DDEV project running, with Mailpit.
- Joomla in the DDEV project root (`docroot: ""`). The cleanup script and the cache clean run from the default `ddev exec` directory and expect `includes/` and `cli/joomla.php` there. A site with Joomla in a subdirectory isn't supported yet.
- `E2E_BASE_URL` serving the DDEV project in `E2E_DDEV_DIR`. Before anything else, global setup writes a random token file into the project's web root, fetches it through `E2E_BASE_URL`, and deletes it again. It refuses to start if the token doesn't come back, so the browser can't drive one site while the database helpers change another. The host in `E2E_MAILPIT_URL` must be one of the project's hostnames (`ddev describe`).
- `E2E_BASE_URL` on the same origin as the site's `live_site` in `configuration.php`, if one is set. CB builds form actions and redirects from it, so setup checks the gateway form's action and refuses on a mismatch.
- The plugin installed and published, with `gateway_enabled` on and a secret set. Global setup checks these, and that the registration page and Mailpit answer 200.
- A run sends 5 codes from one IP. That is exactly the default `rl_ip_short_max` (5 per 15 minutes), so the limit can't be any lower, and a new test that sends a code needs a higher limit. Cleanup deletes the earlier test rows, so runs don't add up.
- If CB AntiSpam's duplicate-registration check is on, fewer live users registered from the DDEV router's IP than its limit allows. Every test request comes from that IP. The check counts only users that still exist, so deleted test users don't count.

## Running

```sh
cd tests/e2e
npm ci
npx playwright install chromium-headless-shell
cp .env.example .env    # then set the values for your site
npx playwright test
```

`.env` holds the site URL, the Mailpit URL, the DDEV project directory, the table prefix and the site's extra registration fields. Git ignores it. `.env.example` describes each setting.

On failure, Playwright writes a screenshot and a trace to `test-results/`. `npx playwright show-trace <path>` opens a trace.
