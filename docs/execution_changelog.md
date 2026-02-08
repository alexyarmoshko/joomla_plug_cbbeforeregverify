# Execution Plan Revision Notes

This file contains the chronological revision history extracted from [`execution_plan.md`](execution_plan.md).

---

Revised on 2026-02-06 to adopt stakeholder-requested naming (`plug_cbbeforeregverify` and `CBBeforeRegVerify`), to make the no-user-creation-before-verification rule explicit across milestones and validation, to simplify the data model, and to add `sent_at` as the expiration baseline (`sent_at + ttl`).

Revised on 2026-02-06 after security and consistency review. Changes: (1) split single `status` field into immutable `status` (sent/resent) and mutable `outcome` (pending/verified/cancelled); (2) specified HMAC-SHA256 with configurable secret for code hashing; (3) added CSRF token validation requirement to all form submissions; (4) added `hash_equals()` for timing-safe code comparison; (5) added session ID regeneration after verification; (6) removed email existence check — duplicate handling deferred to Joomla/CB registration validation; (7) documented IPv6 rate-limit limitation; (8) noted `code_length` configuration change is not retroactive; (9) attempt counting uses request history within time window, not per-row counter; (10) added `purge_after_days` configuration with minimum bound to TTL; (11) added `(request_ip, sent_at)` index; (12) clarified `sent_at` serves as creation timestamp; (13) removed `cbbeforeregverify_login_prefill` session key and `existing_email_redirect`/`prefill_login_email` configuration parameters.

Revised on 2026-02-07 to apply stakeholder clarifications. Changes: (1) cancel now always redirects to site home page and `cancel_redirect_mode`/`cbbeforeregverify_return` were removed from the plan; (2) attempt limiter history is sourced from verification table rows for the same email using `sent_at + ttl` window logic; (3) every new active row creation now soft-cancels existing pending rows for that email with note `replaced_by_new_issue`; (4) hash strategy simplified to `sha256(code + secret)`; (5) purge-on-initialization runtime overhead was explicitly accepted for expected low registration volume.

Revised on 2026-02-07 to implement failed-attempt row tracking. Changes: (1) `status` domain expanded to `sent`/`resent`/`attempt`; (2) `outcome` domain expanded to include `failed`; (3) each failed `submit_code` inserts one `attempt`/`failed` row while successful submit updates only the active verification row; (4) attempts limiter now counts only `attempt`/`failed` rows within the TTL-based window; (5) purge scope now includes `failed` rows.

Revised on 2026-02-07 to tighten implementation contracts. Changes: (1) failed-attempt row insertion now explicitly sets `ttl` from active verification row and sets `sent_at`/`modified_at` to failed-submit time; (2) active verification row lookup contract was made explicit as `status IN ('sent','resent') AND outcome = 'pending'` across table/service/interface guidance.

Revised on 2026-02-07 to align implemented UX recovery controls. Changes: (1) step-1 email screen now includes cancel action in addition to step-2 cancel; (2) verified-email registration state now includes `Use a different email` restart action routed through `func=restart`; (3) validation matrix extended with restart scenario evidence.

Revised on 2026-02-07 to plan confirmed-state compatibility improvement. Changes: (1) added Milestone 6 for registration-time-only `confirmed` override, (2) constrained implementation to `onBeforeUserRegistration` and to mutating only `confirmed`, (3) extended validation matrix with explicit confirmed override checks.

Revised on 2026-02-07 to implement Milestone 6 in code. Changes: (1) registered `onBeforeUserRegistration` in plugin bootstrap, (2) added guarded confirmed-only override in `UserTrigger` when verified-session email matches registration email, (3) recorded code-path evidence in the validation matrix.

Revised on 2026-02-08 to add Milestone 7 planning for Joomla Mail Template integration. Changes: (1) documented verified Joomla 5.4.2 constraints from the test site, (2) added requirement to use enabled extension prefix (`comprofiler`) in template key so it appears in Mail Templates UI, (3) added installer bootstrap + runtime `MailTemplate` send flow with CB fallback, and (4) extended validation scenarios for template customization and fallback behavior.

Revised on 2026-02-08 to implement Milestone 7 in code. Changes: (1) installer now creates default template `comprofiler.cbbeforeregverify.verification_code` when missing, (2) verification mail delivery now uses Joomla `MailTemplate` with `code`/`minutes` data and recipient handling, (3) `cbNotification->sendFromSystem` fallback remains active on template send failure/exception, and (4) code-path validation evidence for scenario 13 was recorded.

Revised on 2026-02-08 to document and address Mail Templates label-key rendering. Changes: (1) recorded finding that missing language keys display as raw `comprofiler_MAIL_*` constants, (2) added installer provisioning of admin language override entries in `administrator/language/overrides/en-GB.override.ini` for template title/description/short labels, and (3) extended scenario 13 validation criteria to assert readable label rendering.

Revised on 2026-02-08 to harden language-key compatibility. Changes: (1) after observing unresolved labels post-install, installer override provisioning was expanded to write both lowercase and uppercase mail-template label keys, (2) installer now writes missing keys for both `en-GB` and the active admin language tag override file, and (3) this preserves non-destructive behavior by adding only missing keys.

Revised on 2026-02-08 to harden restart action CSRF handling. Changes: (1) registration-page restart control was changed from GET link to POST form with token input, (2) `restart` handler now enforces `checkFormToken()` before mutating verification session state, and (3) CSRF-related milestones/validation notes were updated to include restart explicitly.

Revised on 2026-02-08 to harden user-facing error handling. Changes: (1) component catch blocks for email submit/resend/failed-attempt store no longer echo raw exception messages to users, (2) those paths now return generic localized error messages, and (3) detailed exceptions are logged server-side through Joomla Log with `error_log` fallback.

Revised on 2026-02-08 to improve maintenance cleanup scope. Changes: `purgeOldRows()` now deletes stale expired `pending` rows (status `sent`/`resent`, `sent_at + ttl <= now`) older than the purge threshold in addition to terminal outcomes.

Revised on 2026-02-08 to simplify purge criteria. Changes: `purgeOldRows()` now purges stale rows by age regardless of status/outcome using `modified_at < threshold`, while retaining unexpired `pending` rows (`sent_at + ttl > now`) as the only exception.

Revised on 2026-02-08 to harden `renderView()` against path traversal. Changes: (1) added early-return whitelist check (`['step_email', 'step_code']`) before file-path construction, (2) added corresponding decision-log entry.

Revised on 2026-02-08 to remove `extract()` from `renderView()`. Changes: replaced `extract( $vars, EXTR_SKIP )` with explicit `$flowEmail` and `$codeLength` variable assignments from the `$vars` array, eliminating implicit variable injection.

Revised on 2026-02-08 to gate purge frequency. Changes: (1) `runMaintenance()` now checks `filemtime()` of a marker file (`JPATH_ROOT/tmp/.cbbeforeregverify_purge`) and skips purge if the marker is younger than 3600 seconds, (2) marker is touched after successful purge, (3) degrades gracefully to every-request purge if the marker file cannot be written.
