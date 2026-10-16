# Concurrency tests

Tests that fire parallel requests at a running test site, to check that the gateway's limits hold under concurrency (finding F2 in `docs/code_review.v2.md`). The plan these tests belong to is `docs/concurrency_fix_plan.md`.

These aren't unit tests and they aren't part of `make test`. They need a disposable Joomla + Community Builder site running under DDEV, with the plugin installed.

## `limits_burst.py`

It runs four bursts, three times each, and reports whether any limit was exceeded:

| Test | Burst | Limit checked |
|---|---|---|
| A | 6 sessions holding the same flow email, each sending 10 wrong codes | `rl_attempts_max` (8) |
| B | 10 sessions requesting a code for one address | `rl_email_short_max` (3) |
| C | 10 sessions requesting codes for 10 addresses from one IP | `rl_ip_short_max` (5) |
| D | 4 sessions requesting a code for one address, cooldown on | 1 code per cooldown |

Each session uses its own cookie jar, because Joomla's filesystem session handler runs requests that share a cookie one at a time. A barrier releases each burst at once. A run counts as reproduced when the row count in the plugin table goes over the limit; Mailpit counts are recorded alongside.

### What it changes on the site

- **Plugin parameters.** It changes them with `JSON_SET` before each test. Before every test, and again at the end (in a `finally`), it restores them to the plugin defaults. It refuses to start unless the current values are the defaults and the site answers 200, and a refusal changes nothing on the site. It never reads or prints the `secret` parameter.
- **Test rows.** It deletes every row in `<prefix>comprofiler_plugin_beforeregverify` whose email ends in `@example.test`, before and after each test. Use that domain for nothing else on the test site.
- **Mail.** It leaves its test messages in Mailpit.
- **What it doesn't touch.** `configuration.php` (it records the file's SHA-256 before and after, as a check), plugin code, and anything outside the test site.

### Requirements

- Python 3.9 or later, `curl`, and `ddev` on the host.
- The DDEV project running, with Mailpit.
- The plugin installed and published, with a non-empty secret and default limits.
- More than one php-fpm worker in the web container. With `pm.max_children = 1` the requests can't overlap, and the bursts prove nothing.

### Running it

```sh
python3 -I tests/concurrency/limits_burst.py \
  --site /path/to/ddev-project \
  --gateway-url 'https://<host>:<port>/index.php/<menu-alias>/pluginclass/cbbeforeregverify?func=' \
  --db-prefix jos_ \
  --out /path/outside/this/repo/limits-run-1
```

`--out` must be outside the repository; the script refuses a path inside it. It writes there:

- `summary.json`: one entry per run, with the limit, the observed row count, the Mailpit count and the peak request overlap
- one folder per run, with request timings, headers, HTML, cookie jars, database rows and Mailpit data
- before and after captures of the plugin parameters, the web log, the plugin log and the `configuration.php` hash

A race is probabilistic, so "not reproduced" on a run doesn't prove a limit is safe. Compare the counts across all three runs.
