# Code Review #1

Date: 2026-02-08

## Overall Assessment

This is a well-structured, security-conscious CB plugin. The execution plan is thorough, the architecture cleanly separates concerns (controller / service / model / triggers / views), and the security posture is strong for its scope. The issues below are refinements rather than critical flaws.

---

## Security

**Strengths:** CSRF on all mutations, timing-safe `hash_equals`, session regeneration, no email enumeration, parameterized queries via CB's `Quote`/`NameQuote`, consistent `htmlspecialchars` in templates, generic user-facing errors with server-side logging.

**Recommendations:**

1. **Use HMAC instead of bare concatenation** -- `CBBeforeRegVerify.php` used `hash('sha256', $code . self::getSecret())`. Plain concatenation is theoretically vulnerable to length-extension attacks. `hash_hmac('sha256', $code, self::getSecret())` is the correct primitive for keyed hashing and costs nothing extra.
   **Status: DONE** -- switched to `hash_hmac`.

2. **Whitelist the `$view` parameter in `renderView`** -- `CBBeforeRegVerify.php` `renderView()` builds a file path from `$view`. Currently all callers pass hardcoded strings, but a defensive whitelist (`['step_email', 'step_code']`) would prevent any future path-traversal risk if the call chain changes.
   **Status: DONE** -- added early-return whitelist check against `['step_email', 'step_code']`.

3. **`extract()` usage** -- `CBBeforeRegVerify.php` `renderView()`. `EXTR_SKIP` limits the damage, but `extract` is a perennial source of subtle bugs. Since only two variables are passed (`flowEmail`, `codeLength`), passing them explicitly or using a simple wrapper would be clearer.
   **Status: DONE** -- replaced `extract()` with explicit `$flowEmail` and `$codeLength` assignments.

4. **Admin `secret` parameter type** -- `cbbeforeregverify.xml` declares `type="text"`. If CB supports a password/masked field type, it would reduce shoulder-surfing risk in the admin panel.
   **Status: WON'T FIX** -- deliberately left as clear text, consistent with Joomla's own `$secret` configuration parameter.

---

## Correctness / Bugs

5. **`getFailedAttemptCount` fetched all rows then filtered in PHP** -- `CBBeforeRegVerify.php` loaded every `attempt/failed` row for an email and looped in PHP to check `sent_at + ttl > now`. For a high-abuse email address this loaded unbounded rows.
   **Status: DONE** -- moved to SQL-side `SELECT COUNT(*) ... WHERE DATE_ADD(sent_at, INTERVAL ttl SECOND) > ?`.

6. **`SESSION_NOTICE` constant is defined but never used** -- `CBBeforeRegVerify.php`. Dead code that should be removed.
   **Status: DONE** -- constant and execution plan session-key reference removed.

7. **`softCancelPendingRows` and `cancelRequest` use string literals instead of class constants** -- `CBBeforeRegVerify.php` hard-codes `'cancelled'`, `'pending'`, `'sent'`, `'resent'` as strings rather than using `VerificationTable::OUTCOME_CANCELLED`, `VerificationTable::OUTCOME_PENDING`, etc. This creates a drift risk if the constant values ever change.
   **Status: DONE** -- replaced all string literals with `VerificationTable` constants in both methods.

8. **`sendVerificationEmail` is declared `public`** -- `CBBeforeRegVerify.php`. It's only called internally from `issueCode`. Should be `private` to reduce the public API surface.
   **Status: DONE** -- changed to `private`.

---

## Maintainability

9. **Stale "Milestone 1 scaffold" docblock comments** -- `UserTrigger.php` had six methods with "Milestone 1 scaffold: ... implemented in the next milestone" despite being fully implemented.
   **Status: DONE** -- replaced with accurate one-line descriptions.

10. **`canProceedByRateLimits` is ~90 lines with repetitive structure** -- `CBBeforeRegVerify.php`. The four rate-limit checks (IP short, IP day, email short, email day) plus resend cooldown share the same pattern. Extracting a helper like `checkWindowLimit(bool $enabled, int $count, int $max, string $messageKey, array $replacements)` would cut the method in half and make adding future limiters trivial.
   **Status: TODO** -- deferred to a future version. The current implementation is correct and readable; a helper extraction can be done when new limiters are added or the method is next modified.

11. **Entirely static class design** -- `CBBeforeRegVerify` is 100% static methods with no constructor. This is the CB convention, but it makes unit testing effectively impossible (can't inject mocks for database, session, or mail). If testing is ever desired, an instance-based service with constructor-injected dependencies would be the path.
   **Status: WON'T FIX** -- deliberately kept static to follow CB plugin conventions. Refactoring to instance-based DI would be an architectural outlier with no injection point (CB has no DI container), significant scope across every call site, and no immediate payoff without an existing test suite. Pure static methods (`normalizeEmail`, `hashCode`, `verifyCode`, `isExpired`) are already testable as-is.

12. **Direct `$_POST` manipulation** -- `UserTrigger.php` writes directly to `$_POST['email']`. This is a CB workaround and should have a brief inline comment explaining *why* it's necessary, since it looks alarming without context.
   **Status: DONE** -- added inline comments at both `$_POST['email']` sites explaining the CB-specific reason for each (form rendering population in `onBeforeRegisterFormDisplay`, tamper prevention in `onBeforeSaveUserRegistrationRequest`).

13. **No automated tests** -- The codebase has no unit or integration tests. The detailed validation matrix in the execution plan is a strong substitute for now, but even a few PHPUnit tests for the pure logic (`normalizeEmail`, `hashCode`/`verifyCode`, `isExpired` TTL math, `check()` validation) would be high-value and easy to add.

---

## Simplicity / Comprehension

14. **Execution plan verbosity** -- `docs/execution_plan.md` was ~691 lines. The revision history section alone was ~100 lines of changelog.
    **Status: DONE** -- revision notes extracted to [`execution_changelog.md`](execution_changelog.md) and replaced with a one-line reference.

15. **Configuration parameter count** -- 16 tunable parameters for rate limiting alone. Most operators will leave defaults. Consider whether a single "rate limiting strictness" level (off / moderate / strict) with preset values would cover 90% of use cases, keeping the granular knobs available as an "advanced" tab.
   **Status: TODO** -- deferred to a future version. Granular parameters are functional and documented; a simplified preset approach can be layered on top without breaking changes.

16. **Purge runs on every plugin load** -- `cbbeforeregverify.php`. Acknowledged in the plan, but a simple timestamp gate (e.g., run at most once per hour via a transient/option) would eliminate unnecessary DELETE queries on the vast majority of requests.
   **Status: DONE** -- added file-based mtime gate (`JPATH_ROOT/tmp/.cbbeforeregverify_purge`) with 3600-second interval; degrades gracefully to every-request purge if tmp is unwritable.

17. **`loadPluginGroup('user')` in bootstrap** -- `cbbeforeregverify.php` forces loading all user plugins. If this plugin is already being loaded as part of the `user` group, this call may be redundant or could cause re-entrant loading. Worth verifying it's needed.
   **Status: NOT AN ISSUE** -- verified against CB source (`cbPluginHandler::loadPluginGroup`). The method has re-entrancy protection via `$_pluginGroups[$group]` and `require_once`. This is the standard CB bootstrap pattern used by nearly every user-group plugin (`plug_cbactivity`, `plug_cbantispam`, `plug_cbarticles`, etc.). Safe, intentional, and minimal overhead on redundant calls.

---

## Summary of Recommended Actions (by priority)

| Priority | Item | Effort | Status |
|----------|------|--------|--------|
| High | Use `hash_hmac` instead of concatenated hash (#1) | Trivial | DONE |
| High | Move failed-attempt count to SQL (#5) | Small | DONE |
| Medium | Use constants instead of string literals in queries (#7) | Small | DONE |
| Medium | Remove stale "Milestone 1 scaffold" comments (#9) | Trivial | DONE |
| Medium | Whitelist `$view` in `renderView` (#2) | Trivial | DONE |
| Medium | Remove unused `SESSION_NOTICE` constant (#6) | Trivial | DONE |
| Medium | Make `sendVerificationEmail` private (#8) | Trivial | DONE |
| Low | Add inline comment explaining `$_POST` manipulation (#12) | Trivial | DONE |
| Low | Extract rate-limit check helper (#10) | Small | TODO |
| Low | Add basic unit tests for pure logic (#13) | Medium | Open |
| Low | Gate purge frequency (#16) | Small | DONE |
| Low | Replace `extract()` with explicit variables (#3) | Trivial | DONE |
