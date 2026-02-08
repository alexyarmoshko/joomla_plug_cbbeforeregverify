# Build CBBeforeRegVerify Plugin to Gate CB Registration

This Execution Plan is a living document. The sections `Progress`, `Surprises & Discoveries`, `Decision Log`, and `Outcomes & Retrospective` must be kept up to date as work proceeds.

This document must be maintained in accordance with `PLANS.md`.

## Purpose / Big Picture

This change adds a pre-registration verification gateway in front of Community Builder (CB) registration. A guest first submits only an email, then enters a six-digit code, and only after successful verification is allowed to open the normal CB registration form with that email pre-populated and read-only.

Before verification succeeds, no new user record is created in `#__users` or `#__comprofiler`. The plugin does not check whether the submitted email already belongs to an existing account; duplicate email detection is deferred to Joomla/CB's own registration validation, which rejects duplicates with a standard error message.

Verification records are stored in a plugin-owned table with a two-field state model (`status` for row type and `outcome` for lifecycle result), TTL in seconds, resend with code rotation, failed-attempt row logging, and independently switchable rate-limiters.

## Progress

- [x] (2026-02-06 20:28 +00:00) Collected CB registration hook points and replacement hooks in local source.
- [x] (2026-02-06 20:28 +00:00) Confirmed product decisions for redirect behavior, code format, resend semantics, and limiter toggles.
- [x] (2026-02-06 20:50 +00:00) Revised plan naming and architecture to `plug_cbbeforeregverify` and `CBBeforeRegVerify`.
- [x] (2026-02-06 20:50 +00:00) Revised plan to explicitly guarantee no user creation before verification success.
- [x] (2026-02-06 20:50 +00:00) Simplified data model per stakeholder guidance.
- [x] (2026-02-06 21:37 +00:00) Added `sent_at` and switched expiration calculation to `sent_at + ttl`.
- [x] (2026-02-06) Security and consistency review: split status/outcome fields, added CSRF requirement, specified hash algorithm with configurable secret, added session regeneration, removed email existence check, added purge configuration, added request_ip index, documented known limitations.
- [x] (2026-02-07) Applied stakeholder clarifications: cancel always redirects home, attempt limiter uses verification table rows and `sent_at + ttl` window logic, new active rows always soft-cancel prior pending rows, hash strategy simplified to `sha256(code + secret)`, and purge-on-init runtime cost accepted for low traffic.
- [x] (2026-02-07) Extended model for attempt tracking: added `status=attempt`, `outcome=failed`, and explicit failed `submit_code` event rows used by attempts limiter.
- [x] (2026-02-07) Implemented plugin scaffold and configuration model in repository workspace.
- [x] (2026-02-07) Implemented Milestone 2: step-1 gateway form and `submit_email` handler with CSRF + email validation, pending-row soft-cancel + new `sent/pending` issuance + mail send, redirect to step-2 scaffold, and `saveregisters` guard requiring verified session.
- [x] (2026-02-07) Implemented Milestone 3: step-2 code submit/resend/cancel handlers with CSRF checks, verify/failed/expired/cancelled lifecycle transitions, failed-attempt row inserts (`attempt`/`failed`), attempts-limiter enforcement from verification history, and session-id regeneration + verified session marker on successful verification.
- [x] (2026-02-07) Implemented Milestone 4: verified email is prefilled and rendered read-only on CB registration form, save requests enforce CSRF + verified-email match, invalid/missing verified state redirects back to step-1, and verification session markers are cleared after successful registration.
- [x] (2026-02-07) Implemented Milestone 5 runtime controls: independently switchable IP/email send limits, resend cooldown, attempts limiter wiring from `attempt`/`failed` history, and purge-on-initialization with `purge_after_days` minimum enforced against TTL-derived floor.
- [x] (2026-02-07) Added release packaging artifacts: root `Makefile` for setup zip + update manifest generation into `./installation`, server update manifest scaffold `plug_cbbeforeregverify.update.xml`, and project `README.md`.
- [x] (2026-02-07 16:55 +00:00) Executed initial validation matrix run-through in repository scope (syntax checks, packaging build, and code-path verification for the original 10 scenarios) and recorded evidence with runtime-environment caveats.
- [x] (2026-02-07) Added UX recovery updates: explicit cancel button on step-1 email screen and explicit `Use a different email` restart control on locked registration email state.
- [x] (2026-02-07) Implemented Milestone 6: registered `onBeforeUserRegistration` and added confirmed-only in-memory override when verified-session email matches registration email.
- [x] (2026-02-07) Tightened Milestone 6 scope to mutate only `confirmed`; no other registration fields may be changed by this compatibility step.
- [x] (2026-02-08) Verified Joomla 5.4.2 test-site compatibility for Joomla Mail Templates and added Milestone 7 planning for customizable verification emails.
- [x] (2026-02-08) Implemented Milestone 7: added installer mail-template bootstrap (`createTemplate` only when missing) and runtime Joomla `MailTemplate` send flow with `cbNotification` fallback.
- [x] (2026-02-08) Added installer provisioning for admin `administrator/language/overrides/en-GB.override.ini` labels so Mail Templates list shows readable title/description instead of raw `comprofiler_MAIL_*` constants.
- [x] (2026-02-08) Adjusted installer to seed both lowercase and uppercase mail-template label keys (`comprofiler_MAIL_*` + `COMPROFILER_MAIL_*`) and write to both `en-GB` plus active admin language override files for Joomla language-key compatibility.
- [x] (2026-02-08) Hardened restart flow against CSRF: replaced registration-page `restart` GET link with CSRF-protected POST form and enforced token validation in `restart` handler.
- [x] (2026-02-08) Hardened gateway error handling: stopped exposing raw exception messages in user redirects and added server-side exception logging for submit/resend/attempt-store failures.
- [x] (2026-02-08) Extended maintenance purge to include stale expired `pending` verification rows older than the purge threshold.
- [x] (2026-02-08) Simplified maintenance purge criteria to status/outcome-agnostic stale-row cleanup with safeguard to retain unexpired `pending` rows.
- [x] Implement verification persistence, lifecycle transitions, and TTL checks.
- [x] Implement gateway screens and transitions.
- [x] Implement CB integration and server-side enforcement.
- [x] Execute validation matrix and complete retrospective.

## Surprises & Discoveries

- Observation: CB can fully replace registration output at request time via plugin return content.
  Evidence: `components/com_comprofiler/comprofiler.php` calls `onBeforeRegisterForm` and outputs plugin response if non-empty.

- Observation: CB plugin component routing is already used for multistep guest interactions.
  Evidence: `components/com_comprofiler/plugin/user/plug_cbinvites/component.cbinvites.php`.

- Observation: The guarantee "no user before verification" is achievable without core hacks by intercepting registration request path before `saveRegistration` execution.
  Evidence: Registration flow is routed by `registers` and `saveregisters` in `components/com_comprofiler/comprofiler.php` and can be gated by pre-save plugin events.

- Observation: Joomla Mail Templates list view only shows templates where `#__mail_templates.extension` maps to an enabled extension name in `#__extensions`.
  Evidence: `administrator/components/com_mails/src/Model/TemplatesModel.php` applies `a.extension IN (SELECT name FROM #__extensions WHERE enabled = 1)`.

- Observation: When mail-template label language keys are missing, `System -> Mail Templates` shows raw key names (e.g. `comprofiler_MAIL_cbbeforeregverify_verification_code_TITLE`) instead of readable labels.
  Evidence: Runtime observation from test-site UI after template creation.

- Observation: During CB plugin installation flow, manifest header fields such as `<name>` and `<description>` are displayed before language-key resolution, so key tokens appear literally in install success messages.
  Evidence: Runtime installation output showed `CBBEFOREREGVERIFY_MANIFEST_NAME` and `CBBEFOREREGVERIFY_MANIFEST_DESCRIPTION` instead of translated text when keys were used in manifest header.

## Decision Log

- Decision: Use plugin name `plug_cbbeforeregverify` and class/service naming `CBBeforeRegVerify`.
  Rationale: Stakeholder naming requirement.
  Date/Author: 2026-02-06 / Codex

- Decision: Treat unverified flow as an external pre-step and never call CB registration save path until verified session state exists.
  Rationale: Guarantees no user record is created before verification.
  Date/Author: 2026-02-06 / Codex

- Decision: Keep only one email column (`email`) stored normalized.
  Rationale: Stakeholder requested simplified schema and no duplicate normalized column.
  Date/Author: 2026-02-06 / Codex

- Decision: Split row state into two fields: `status` (row type: `sent`, `resent`, or `attempt`, immutable after insert) and `outcome` (lifecycle result: `pending`, `verified`, `cancelled`, or `failed`).
  Rationale: Separates immutable row type from mutable lifecycle for verification rows, while also allowing insert-only failed-attempt audit rows.
  Date/Author: 2026-02-06 / Stakeholder

- Decision: Use `ttl` (seconds) with `sent_at` for expiration checks and keep `modified_at` for lifecycle updates.
  Rationale: Stakeholder requested explicit send timestamp and expiration based on request sending time.
  Date/Author: 2026-02-06 / Codex

- Decision: Use `HMAC-SHA256(code, secret)` for code hashing instead of bcrypt.
  Rationale: HMAC is the correct keyed-hash primitive; stakeholder explicitly accepted a fast hash approach for this low-risk gateway control.
  Date/Author: 2026-02-06 / Stakeholder

- Decision: Require CSRF token validation on all form submissions and state-changing gateway actions (step-1 email, step-2 code, resend, cancel, restart).
  Rationale: Standard web security practice to prevent cross-site request forgery.
  Date/Author: 2026-02-06 / Review

- Decision: Regenerate session ID after verification completion.
  Rationale: Prevents session fixation attacks when transitioning from unauthenticated to verified state.
  Date/Author: 2026-02-06 / Review

- Decision: Do not check email existence before sending verification code; let Joomla/CB handle duplicate detection at registration time.
  Rationale: Simplifies the gateway flow, eliminates email enumeration risk, and defers duplicate handling to the platform's built-in validation. If a verified email already has an account, the user will see Joomla's standard duplicate email error at registration time.
  Date/Author: 2026-02-06 / Stakeholder

- Decision: Calculate attempts limiter history from rows in `#__comprofiler_plugin_beforeregverify` where `status = attempt` and `outcome = failed`, using `sent_at + ttl` time-window logic.
  Rationale: Stakeholder requested failed submit attempts to be recorded as dedicated rows and counted from table history.
  Date/Author: 2026-02-07 / Stakeholder

- Decision: On every failed code submission, insert a row with `status = attempt` and `outcome = failed`; on successful code submission, update only the active verification row.
  Rationale: Preserves one active verification flow while capturing each failed attempt for limiter enforcement and auditability.
  Date/Author: 2026-02-07 / Stakeholder

- Decision: Every time a new active verification row is created, first soft-cancel all existing pending rows for the same email with a note.
  Rationale: Ensures a single active row per email without adding strict DB locking/uniqueness complexity, acceptable for expected low volume.
  Date/Author: 2026-02-07 / Stakeholder

- Decision: Cancel action always redirects to site home page.
  Rationale: Stakeholder requested deterministic and simple cancel behavior.
  Date/Author: 2026-02-07 / Stakeholder

- Decision: Provide explicit restart action (`func=restart`) from the locked registration email state to clear verification session and restart verification.
  Rationale: Without a restart path, a previously verified email can stay sticky and block switching to a different address.
  Date/Author: 2026-02-07 / Stakeholder

- Decision: Restart action must be submitted via POST with Joomla CSRF token validation.
  Rationale: Prevents cross-site GET-triggered state reset of verification session.
  Date/Author: 2026-02-08 / Review

- Decision: Gateway catch blocks must not expose raw exception messages to end users; technical details should be logged server-side.
  Rationale: Avoids leaking internal storage/mail/runtime details while preserving diagnostics for operators.
  Date/Author: 2026-02-08 / Review

- Decision: Override only `confirmed` during the registration lifecycle (`onBeforeUserRegistration`) when gateway verification is present and email matches session; do not modify any other registration fields.
  Rationale: Limits behavioral change to the exact registration transaction and a single field, minimizing compatibility risk and avoiding post-registration state mutation.
  Date/Author: 2026-02-07 / Stakeholder

- Decision: Add configurable purge period for old verification rows.
  Rationale: Table grows indefinitely without cleanup. Purge threshold must be at least as long as the TTL to avoid deleting active rows.
  Date/Author: 2026-02-06 / Review

- Decision: Document IPv6 address rotation as a known limitation of IP-based rate limiting.
  Rationale: IPv6 clients can rotate addresses within their /64 prefix, potentially bypassing per-IP rate limits. Accepted as a known limitation for initial implementation.
  Date/Author: 2026-02-06 / Review

- Decision: Use a mail-template key prefix that maps to an enabled extension name (`comprofiler`), e.g. `comprofiler.cbbeforeregverify.verification_code`.
  Rationale: `MailTemplate::createTemplate()` derives the `extension` column from the key prefix, and `com_mails` hides templates for non-enabled extension names.
  Date/Author: 2026-02-08 / Codex

- Decision: Seed Administrator English (`en-GB`, `client_id = 1`) language overrides for mail-template title/description/short keys during installer `postflight`.
  Rationale: Ensures Mail Templates UI shows readable labels for this plugin template without requiring manual Language Overrides setup.
  Date/Author: 2026-02-08 / Codex

- Decision: Whitelist allowed view names in `renderView()` to prevent path-traversal risk from the `$view` parameter.
  Rationale: Although all current callers pass hardcoded strings, a defensive whitelist (`['step_email', 'step_code']`) prevents future misuse if the call chain changes.
  Date/Author: 2026-02-08 / Review

- Decision: Gate purge frequency using a file-based mtime check (`JPATH_ROOT/tmp/.cbbeforeregverify_purge`) with a hardcoded 3600-second interval.
  Rationale: Avoids a DELETE query on every request. File mtime is a zero-DB-cost gate; `@touch`/`@filemtime` degrade gracefully if tmp is unwritable (purge runs every request as before). DB-based gates would add a SELECT on every request, defeating the purpose.
  Date/Author: 2026-02-08 / Review

## Outcomes & Retrospective

All planned milestones (1-7) were implemented without CB core patching. The plugin now enforces a full pre-registration email verification gateway, keeps lifecycle state in the plugin-owned table (`status` + `outcome`), blocks registration saves unless verified-session state is consistent, and sends verification emails through Joomla Mail Templates with CB fallback for resiliency.

Validation run-through was completed on 2026-02-07 16:55 +00:00 with repository-local evidence. Runtime scenarios that require a live Joomla + CB + database environment were verified at code-path level and documented as environment-dependent follow-up checks.

### Validation Matrix Results (Repository Scope)

1. Email submission and no pre-verification user creation.
   Result: Code-path pass; live DB assertion pending runtime environment.
   Evidence: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:83`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:559`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:585`.
2. Duplicate email handling.
   Result: Code-path pass.
   Evidence: No lookup against `#__users`; issuance path validates format only and proceeds to send flow: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:569`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:573`.
3. Expired code path.
   Result: Code-path pass.
   Evidence: expiry detection and forced restart/cancel: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:366`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:146`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:276`.
4. Resend rotation.
   Result: Code-path pass.
   Evidence: resend issues new row and soft-cancels pending rows with `replaced_by_new_issue`: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:227`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:579`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:640`.
5. Save enforcement.
   Result: Code-path pass.
   Evidence: CSRF check + verified-email presence + mismatch rejection/redirect: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php:155`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php:168`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php:178`.
6. CSRF enforcement.
   Result: Code-path pass.
   Evidence: tokens rendered in gateway/restart forms and checked in handlers: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/templates/default/step_email.php:31`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/templates/default/step_code.php:34`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php:124`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:280`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:317`.
7. Limiter toggle tests.
   Result: Implementation pass; threshold behavior pending runtime environment.
   Evidence: independent toggles wired for IP/email/resend/attempts: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:264`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:294`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:324`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:501`.
8. Failed attempt logging and blocking.
   Result: Code-path pass; runtime threshold assertion pending.
   Evidence: failed attempts are inserted as `status=attempt`, `outcome=failed`; limiter check blocks afterward: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:427`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:457`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:197`.
9. Purge behavior.
   Result: Code-path pass; destructive DB assertion pending runtime environment.
   Evidence: maintenance-on-init and purge query remove stale rows by age regardless of status/outcome while retaining unexpired pending rows: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.php:22`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:60`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:538`.
10. Cancel redirect behavior.
   Result: Code-path pass.
   Evidence: cancel forms on both step-1 and step-2 post into common `cancel` handler; active row is cancelled where present and redirect goes to home: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/templates/default/step_email.php:33`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/templates/default/step_code.php:42`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:248`.
11. Restart from locked verified-email registration state.
    Result: Code-path pass and stakeholder runtime confirmation pass.
    Evidence: registration form injects `Use a different email` POST control with CSRF token; restart handler validates token, then clears session and restarts at step-1: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php:117`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:49`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php:279`.
12. Registration-time confirmed override.
    Result: Code-path pass; live runtime confirmation pending environment.
    Evidence: bootstrap registers `onBeforeUserRegistration`; handler sets only `confirmed=1` when verified-session email matches registration email: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.php:29`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php:217`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php:235`.
13. Joomla Mail Template customization.
    Result: Code-path pass; live runtime customization/fallback confirmation pending environment.
    Evidence: installer creates template key `comprofiler.cbbeforeregverify.verification_code` only when missing and keeps placeholders `{CODE}`/`{MINUTES}`: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/install.cbbeforeregverify.php:17`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/install.cbbeforeregverify.php:44`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/install.cbbeforeregverify.php:51`; runtime send uses Joomla `MailTemplate` then falls back to `cbNotification`: `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:621`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:649`, `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php:640`.

### Command-Level Validation Evidence

- PHP syntax checks passed for all plugin PHP files using `php -l`.
- Gateway exception handling now logs server-side details and returns generic localized redirect errors in component catches.
- Packaging workflow passed: `make info` and `make dist`.
- Generated artifacts validated:
  - `installation/plug_cbbeforeregverify-v0-1-0.zip`
  - `installation/plug_cbbeforeregverify.update.xml`
  - `plug_cbbeforeregverify.update.xml` (root copy)
- Working-tree diff confirms no CB core file modifications; only plugin files, plan file, and release artifacts are changed.

### Residual Risks and External Follow-Up

- Live Joomla+CB runtime verification is still required for DB-side assertions in scenarios 1, 7, 8, and 9.
- Browser-flow confirmation is still required for end-to-end UX messaging and redirects under real session/cookie conditions.
- Live runtime verification is still required for scenario 13 (Mail Templates UI visibility/customization and fallback delivery behavior under simulated template-send failure).
- No open product decisions remain; remaining work is deployment-environment validation and release hardening.

## Context and Orientation

CB registration is handled in `components/com_comprofiler/comprofiler.php`. Relevant hook points:

- `onBeforeRegisterFormRequest` before registration form path.
- `onBeforeRegisterForm` for output replacement.
- `onBeforeSaveUserRegistrationRequest` before save.
- `onBeforeRegisterFormDisplay`/`onAfterRegisterFormDisplay` around form rendering.
- `onAfterSaveUserRegistration` after successful registration.

Plugin component routing (as demonstrated by `plug_cbinvites`) works via URL parameter `?option=com_comprofiler&plugin=cbbeforeregverify&func=<action>`, which CB routes to the plugin's `getCBpluginComponent()` method. The `func` parameter selects the action within the component file.

In this plan, "soft-delete" means setting row outcome to `cancelled` and adding a reason in `note`, not physical deletion.

In this plan, "TTL" means a numeric seconds value stored per row in `ttl`. A row is expired when `sent_at + ttl <= now` while outcome is `pending`.

In this plan, `status` refers to row type (`sent`, `resent`, `attempt`) and is immutable after row creation. `outcome` refers to lifecycle result (`pending`, `verified`, `cancelled`, `failed`).

## Milestones

### Milestone 1: Plugin Skeleton and Configuration

Create plugin folder `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/` with:

- `cbbeforeregverify.php`
- `component.cbbeforeregverify.php`
- `cbbeforeregverify.xml`
- `install.cbbeforeregverify.php`
- `library/CBBeforeRegVerify.php`
- `library/Table/VerificationTable.php`
- `library/Trigger/UserTrigger.php`
- `templates/default/step_email.php`
- `templates/default/step_code.php`
- language resources

Acceptance: plugin installs and shows configuration fields in CB plugin manager.

### Milestone 2: Email-Only Gateway and No-User-Creation Guard

Implement step-1 screen and submit handler.

Flow rules:

- Guest hits CB registration URL. The `onBeforeRegisterForm` hook detects no verified session and returns the step-1 (email entry) form HTML, replacing the standard registration UI.
- Step-1 form submits to `component.cbbeforeregverify.php` via `?option=com_comprofiler&plugin=cbbeforeregverify&func=submit_email`. All form submissions must include and validate a Joomla CSRF token.
- The plugin does not check whether the email already exists. Duplicate email detection is deferred to Joomla/CB's own registration validation after verification completes.
- On valid email submission: soft-cancel any existing pending rows for the same email with note `replaced_by_new_issue`, then create a new verification row (status `sent`, outcome `pending`), send code email, and redirect to step-2.

Explicit guard requirement:

- `saveregisters` must be blocked unless verified session key exists.
- No call to user save methods before verification completion.

Acceptance: email receives code and no user row exists in `#__users` or `#__comprofiler` before verification.

### Milestone 3: Code Verification, Resend, Cancel, Expiry

Implement step-2 submit/resend/cancel/restart. All step forms and state-changing actions (`submit_email`, `submit_code`, `resend`, `cancel`, `restart`) must include and validate a Joomla CSRF token.

Rules:

- Submit correct code: set row outcome to `verified`, update `modified_at`, regenerate session ID to prevent session fixation, set verified session marker, redirect to CB registration form.
- Submit incorrect code for a non-expired active verification row: insert a new row with status `attempt`, outcome `failed`, note `failed_code`, `ttl` copied from the active verification row, and `sent_at`/`modified_at` set to current time; then continue showing step-2 unless blocked by attempts limiter.
- Resend: always generate a new code; soft-cancel any existing pending rows for the same email with note `replaced_by_new_issue`; create a new row with status `resent` and outcome `pending`.
- Cancel: available from both step-1 and step-2; if an active row exists, set its outcome to `cancelled` with note `user_cancelled`, clear verification session state, and always redirect to site home page.
- Expired active row: on any interaction with an expired row (`status` in `sent`/`resent`, `outcome` `pending`, and `sent_at + ttl <= now`), set outcome to `cancelled` with note `expired`, clear flow session, redirect to step-1 with expiration notice.

Acceptance: full multistep behavior with status/outcome transitions and no orphan active code ambiguity.

### Milestone 4: CB Registration Form Integration and Save Enforcement

After verification, redirect user to regular CB registration page.

Rules:

- Email field is prefilled from verified session and rendered read-only.
- Registration UI includes explicit `Use a different email` POST control with Joomla CSRF token that routes to restart action and clears verification session state.
- On save request, enforce that posted email matches verified session email. All form enforcement must validate a Joomla CSRF token.
- If verified state is absent or mismatched, reject save and redirect to step-1.
- After successful registration, clear verification session markers.

Acceptance: registration only succeeds when verified session is present and consistent.

### Milestone 5: Rate Limits, Toggling, Purge, Validation

Implement all requested limiters with independent on/off controls.

Default thresholds:

- IP short window: 5 per 15 minutes.
- IP day window: 25 per 24 hours.
- Email short window: 3 sends per 30 minutes.
- Email day window: 10 sends per 24 hours.
- Resend cooldown: 60 seconds.
- Code attempts per email: 8 (calculated from rows where `status = attempt` and `outcome = failed` for that email, using `sent_at + ttl` window logic; no per-row attempt counter).

Implement row purge: on plugin initialization, delete rows with `modified_at` older than `purge_after_days` regardless of status/outcome, except keep `pending` rows where `sent_at + ttl > now` (still active). The `purge_after_days` configuration value must be at least `ceil(verification_ttl_sec / 86400)` days. Running this on initialization is accepted for this plugin because registration volume is expected to be low.

Known limitation: IP-based rate limiting uses the full IP address. IPv6 clients can rotate addresses within their allocated prefix (commonly /64), which can allow bypassing per-IP rate limits. This is accepted for the initial implementation.

Acceptance: each limiter can be enabled/disabled independently and behaves accordingly. Purge removes old rows without affecting active verifications.

### Milestone 6: Registration-Time Confirmed Override Compatibility

Implement compatibility update so users verified by this gateway are not forced through CB confirmation a second time.

Rules:

- Register `onBeforeUserRegistration` in plugin bootstrap and handle it in `UserTrigger`.
- Apply override only when all conditions are true:
  - gateway is enabled
  - verified-session email exists
  - registration user email matches verified-session email (normalized comparison)
- During this hook only, set registration user fields to bypass redundant email confirmation:
  - `confirmed = 1`
- Do not modify any registration fields other than `confirmed` in this milestone. In particular, do not alter `cbactivation`, `activation`, `approved`, or `block`.
- Do not update `confirmed` in `onAfterSaveUserRegistration` or by direct post-registration DB update.
- Preserve existing guard behavior in `onBeforeSaveUserRegistrationRequest` (verified session + email-match enforcement).

Acceptance: gateway-verified registration results in `confirmed=1` at creation time, and Milestone 6 changes do not mutate any other registration fields.

### Milestone 7: Joomla Mail Template Integration for Verification Email

Integrate verification email delivery with Joomla Mail Templates so administrators can customize content in `System -> Mail Templates`.

Rules:

- Add installer bootstrap in `install.cbbeforeregverify.php` `postflight` to ensure a default template exists.
- Use a template key with enabled extension prefix, defaulting to `comprofiler.cbbeforeregverify.verification_code`.
- Create template only when missing (`MailTemplate::getTemplate($key, '') === null` then `MailTemplate::createTemplate(...)`); do not overwrite existing customized templates.
- Default template content must preserve current verification semantics and expose placeholders `{CODE}` and `{MINUTES}`.
- Update `CBBeforeRegVerify::sendVerificationEmail()` to use Joomla `MailTemplate` send flow:
  - instantiate `MailTemplate` with current language tag
  - pass template data for `code` and `minutes`
  - add recipient and call `send()`
- Keep CB fallback path (`cbNotification->sendFromSystem`) if template send returns false or throws.
- Keep existing gateway flow behavior and error handling (no user creation before verification).

Acceptance: verification emails are sent via Joomla Mail Templates under normal conditions, admins can customize the template from Mail Templates UI, and fallback mail send still works when template delivery fails.

## Plan of Work

Use bootstrap file `cbbeforeregverify.php` to register trigger methods.

Use `component.cbbeforeregverify.php` for step routing and form posts. The component receives requests via `?option=com_comprofiler&plugin=cbbeforeregverify&func=<action>` and routes based on the `func` parameter (e.g., `submit_email`, `submit_code`, `resend`, `cancel`, `restart`).

Use `library/CBBeforeRegVerify.php` for service logic:

- normalize email
- generate code and hash with `HMAC-SHA256(code, secret)` using configurable secret
- verify code using `hash_equals()` for timing-safe comparison
- TTL evaluation
- mail send via Joomla Mail Templates with CB fallback
- failed attempt row inserts on incorrect code submissions
- outcome transitions
- rate-limit checks (including counting rows with `status = attempt` and `outcome = failed` for the email using `sent_at + ttl` window logic)
- row purge

Use `library/Table/VerificationTable.php` for DB operations and active row queries. The active verification row query contract is `status IN ('sent','resent') AND outcome = 'pending'`.

Use `library/Trigger/UserTrigger.php` to intercept CB registration display/save hooks and enforce gating.

Session keys:

- `cbbeforeregverify_flow_email`
- `cbbeforeregverify_verified_email`

Hook behavior summary:

- `onBeforeRegisterFormRequest`: route guest to gateway start unless already verified for current flow.
- `onBeforeRegisterForm`: if gateway step is active, return gateway HTML instead of default registration UI.
- `onBeforeRegisterFormDisplay` and/or `onAfterRegisterFormDisplay`: enforce read-only verified email rendering and inject CSRF-protected restart control (`Use a different email`) for verified-session state.
- `onBeforeSaveUserRegistrationRequest`: block any registration save unless verified session state exists and matches submitted email.
- `onBeforeUserRegistration`: when verified-session email matches registration email, set only `confirmed=1` in-memory before persistence.
- `onAfterSaveUserRegistration`: clear verification session state after successful registration.

## Data Model

Create table `#__comprofiler_plugin_beforeregverify`.

Required columns:

- `id` int primary key auto increment.
- `email` varchar(255), always normalized (trimmed + lowercased).
- `code_hash` varchar(255) nullable, SHA-256 hash of `code + secret` for `sent`/`resent` rows; `NULL` for `attempt` rows.
- `status` varchar(16), row type: `sent` (initial code issue), `resent` (replacement issue), or `attempt` (failed code submission event). Set on INSERT and never changed.
- `outcome` varchar(16), lifecycle result: `pending` (awaiting verification), `verified` (code accepted), `cancelled` (expired, replaced, or user-cancelled), or `failed` (failed code submission event).
- `ttl` int unsigned, seconds. For `attempt`/`failed` rows, copy from the active verification row at the moment of failed submit.
- `sent_at` datetime, filled when the row is created. For `attempt`/`failed` rows, set to failed submit time.
- `modified_at` datetime, updated on outcome transitions for verification rows. For `attempt`/`failed` rows, set equal to insertion time.
- `request_ip` varchar(45) nullable.
- `note` text nullable.

Recommended indexes:

- `(email, outcome)`
- `(email, status, outcome, sent_at)`
- `(outcome)`
- `(sent_at)`
- `(modified_at)`
- `(request_ip, sent_at)`

Lifecycle semantics:

- Active request: `status` is `sent` or `resent`, and `outcome` is `pending`.
- Active verification row query contract: `status IN ('sent','resent') AND outcome = 'pending'`. `attempt` rows must never satisfy active-row queries.
- Completed request: `status` is `sent` or `resent`, and `outcome` is `verified`.
- Closed request: `status` is `sent` or `resent`, and `outcome` is `cancelled`.
- Failed attempt event: `status` is `attempt`, and `outcome` is `failed`.
- `status` is set on row creation (`sent` for first issue, `resent` for resend issue, `attempt` for failed submit) and is never modified.
- Valid outcome transitions for verification rows are `pending` → `verified` and `pending` → `cancelled`. Attempt rows are inserted directly as `failed` and are not transitioned.
- `sent_at` is written once at row creation and is never updated. It serves as both the creation timestamp and the baseline for TTL expiration.
- Expired detection: row with `status` in (`sent`, `resent`) and `outcome` `pending` where `sent_at + ttl` is in the past.
- Any creation of a new active row first sets all existing pending rows for the same email to `cancelled` with note `replaced_by_new_issue`.
- Every failed code submission inserts an `attempt`/`failed` row and does not modify the active verification row.
- Cancel, expiry, and failed attempt reasons are written to `note`. Note values are for audit purposes only and must not be parsed for rate-limiter logic.
- Changing `code_length` in configuration does not retroactively affect existing rows; active codes retain the length they were generated with.

## Configuration Model

Core settings:

- `gateway_enabled` yes/no default yes.
- `verification_ttl_sec` int default 900.
- `code_length` int default 6. Changing this value does not affect existing active verification codes.
- `secret` text, configurable secret used as the HMAC-SHA256 key for code hashing. Must be set to a non-empty value before the gateway is enabled.
- `purge_after_days` int default 30. Minimum value must be at least `ceil(verification_ttl_sec / 86400)`. Rows with `modified_at` older than this threshold are deleted on plugin initialization regardless of status/outcome, except active `pending` rows (`sent_at + ttl > now`) are retained.

Rate limiter settings (all independently switchable):

- `rl_ip_enabled` yes/no default yes.
- `rl_ip_short_window_min` int default 15.
- `rl_ip_short_max` int default 5.
- `rl_ip_day_window_hours` int default 24.
- `rl_ip_day_max` int default 25.

- `rl_email_enabled` yes/no default yes.
- `rl_email_short_window_min` int default 30.
- `rl_email_short_max` int default 3.
- `rl_email_day_window_hours` int default 24.
- `rl_email_day_max` int default 10.

- `rl_resend_enabled` yes/no default yes.
- `rl_resend_cooldown_sec` int default 60.

- `rl_attempts_enabled` yes/no default yes.
- `rl_attempts_max` int default 8.
- Attempts windowing counts rows where `status = attempt` and `outcome = failed` for the same email and where `sent_at + ttl > now`; no separate attempts-window configuration parameter is used.

Policy rules:

- If a limiter is disabled, its checks are skipped.
- Any enabled limiter can block the request independently.
- Block reason is shown via localized message and logged in `note` where appropriate.

Known limitation: IP-based rate limiting uses the full IP address. On IPv6 networks, clients may rotate addresses within their allocated prefix (commonly /64), which can allow bypassing per-IP rate limits. This is accepted for the initial implementation. Running purge on plugin initialization may add request-time database overhead, which is accepted for this low-volume use case.

## Concrete Steps

All commands run from repository root.

1. Create plugin directories.

    mkdir components\com_comprofiler\plugin\user\plug_cbbeforeregverify
    mkdir components\com_comprofiler\plugin\user\plug_cbbeforeregverify\library
    mkdir components\com_comprofiler\plugin\user\plug_cbbeforeregverify\library\Table
    mkdir components\com_comprofiler\plugin\user\plug_cbbeforeregverify\library\Trigger
    mkdir components\com_comprofiler\plugin\user\plug_cbbeforeregverify\templates
    mkdir components\com_comprofiler\plugin\user\plug_cbbeforeregverify\templates\default
    mkdir components\com_comprofiler\plugin\user\plug_cbbeforeregverify\language

2. Add manifest and installer.

    Create `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.xml` and `install.cbbeforeregverify.php`.

3. Add bootstrap and triggers.

    Create `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.php` and register required hook handlers.

4. Add component and templates.

    Create `component.cbbeforeregverify.php`, `templates/default/step_email.php`, and `templates/default/step_code.php`.

5. Add table/service classes.

    Create `library/Table/VerificationTable.php`, `library/CBBeforeRegVerify.php`, and `library/Trigger/UserTrigger.php`.

6. Add language keys and messages.

7. Add Joomla Mail Template bootstrap + send integration (`install.cbbeforeregverify.php` and `library/CBBeforeRegVerify.php`).

8. Run validation matrix below.

## Validation and Acceptance

1. Email submission and no pre-verification user creation.

- Submit an email address.
- Receive code and move to step-2.
- Confirm no new row in `#__users` and `#__comprofiler` before code verification.

2. Duplicate email handling.

- Submit an email that already has a Joomla/CB account.
- Verification code is sent normally (no email existence check at gateway).
- After successful verification, proceed to CB registration form.
- On registration save, Joomla/CB rejects the duplicate email with its standard error message.

3. Expired code path.

- Let active code expire (`sent_at + ttl`).
- Submit code.
- Row outcome transitions to `cancelled` with note `expired`.
- User returns to step-1 with expiration notice.

4. Resend rotation.

- Request resend.
- Any existing pending rows for that email become `cancelled` with note `replaced_by_new_issue`.
- New row is created with status `resent`, outcome `pending`, and new `code_hash`.

5. Save enforcement.

- Try posting registration without verified session or with mismatched email.
- Save is blocked and user redirected to step-1.

6. CSRF enforcement.

- Attempt form submission without a valid CSRF token.
- Request is rejected.

7. Limiter toggle tests.

- Disable each limiter independently and verify it does not block.
- Enable each limiter and verify threshold enforcement.
- Verify mixed enablement combinations behave as configured.

8. Failed attempt logging and blocking.

- Submit an incorrect verification code multiple times for the same email.
- Confirm each failed submission inserts one row with `status = attempt` and `outcome = failed`.
- Confirm once `rl_attempts_max` is exceeded within the active window, further submit attempts are blocked.

9. Purge behavior.

- Create expired/cancelled/verified/failed rows older than `purge_after_days`.
- Trigger plugin initialization.
- Confirm old rows are deleted and active rows are preserved.

10. Cancel redirect behavior.

- Trigger cancel from step-1 and step-2.
- If current active row exists, outcome becomes `cancelled` with note `user_cancelled`.
- User is redirected to site home page.

11. Restart from locked verified registration state.

- Reach CB registration with verified email prefilled and read-only.
- Trigger `Use a different email` via POST with valid CSRF token.
- Verification session keys are cleared and user is returned to step-1 with restart notice.

12. Registration-time confirmed override.

- With CB confirmation enabled, complete gateway verification and submit registration.
- Confirm newly created CB profile has `confirmed = 1` immediately (no second email-confirmation requirement).
- Confirm Milestone 6 does not mutate any registration fields other than `confirmed`.
- Confirm no post-registration correction is used (override happens only during registration hook execution).

13. Joomla Mail Template customization.

- Confirm `comprofiler.cbbeforeregverify.verification_code` exists in `#__mail_templates`.
- Confirm template is visible in `System -> Mail Templates` under extension `comprofiler`.
- Confirm template row title/description render as readable text (not raw `comprofiler_MAIL_*` constants).
- Customize subject/body and verify outgoing verification email uses customized template content with `{CODE}` and `{MINUTES}` replacements.
- Simulate template send failure and verify fallback to `cbNotification->sendFromSystem` still delivers email.

Acceptance is complete when all scenarios pass and no CB core files are modified.

## Idempotence and Recovery

Schema is additive and installation can be re-run safely.

Outcome transitions are idempotent by checking current outcome before update. Failed code submissions are insert-only `attempt`/`failed` events.

If mail delivery fails, keep flow at step-1/step-2 with user-safe retry path and no user record creation.

If session is lost, restart at step-1 without partial registration side effects.

Row purge is idempotent and safe to run on every plugin initialization.

## Artifacts and Notes

Expected files after implementation:

- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.php`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/component.cbbeforeregverify.php`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/cbbeforeregverify.xml`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/install.cbbeforeregverify.php`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/CBBeforeRegVerify.php`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Table/VerificationTable.php`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/library/Trigger/UserTrigger.php`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/templates/default/step_email.php`
- `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/templates/default/step_code.php`
- language files under `components/com_comprofiler/plugin/user/plug_cbbeforeregverify/language/`

Security notes:

- Never store plain verification codes; store only `HMAC-SHA256(code, secret)` hashes.
- All form submissions must validate Joomla CSRF tokens.
- Code verification must use `hash_equals()` for timing-safe comparison.
- Session ID must be regenerated after verification completion.

## Interfaces and Dependencies

Use existing Joomla/CB runtime APIs only.

Required class contracts at completion:

- `CBBeforeRegVerify` service for normalization, code generation/verification, TTL checks, failed-attempt logging, outcome transitions, rate-limit checks, and row purge.
- `VerificationTable` for active-row retrieval and persistence using `status IN ('sent','resent') AND outcome = 'pending'` for active-row lookups.
- `UserTrigger` for CB hook integration and enforcement.

Recommended service methods:

- `normalizeEmail(string $email): string`
- `issueCode(string $email, string $ip): VerificationTable`
- `hashCode(string $code): string` — HMAC-SHA256 with `secret` as key
- `verifyCode(VerificationTable $row, string $code): bool` — timing-safe comparison via `hash_equals()`
- `recordFailedAttempt(VerificationTable $activeRow, string $email, string $ip): VerificationTable` — inserts an `attempt`/`failed` row for incorrect code submission
- `getActiveVerificationRow(string $email): ?VerificationTable` — returns only rows where `status` is `sent`/`resent` and `outcome` is `pending`
- `isExpired(VerificationTable $row): bool`
- `cancelRequest(VerificationTable $row, string $reason): bool` — sets outcome to `cancelled` with note
- `markVerified(VerificationTable $row): bool` — sets outcome to `verified`
- `canProceedByRateLimits(string $email, string $ip): array`
- `purgeOldRows(int $days): int` - deletes stale rows older than threshold regardless of status/outcome, while retaining unexpired pending rows

## Revision Notes

Full revision history is maintained in [`execution_changelog.md`](execution_changelog.md).
