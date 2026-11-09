# Daymark Subscriptions — Current Full Plugin Audit

Date: 2026-11-04  
Scope: current checkout in `wp-content/plugins/daymark-subscriptions`  
Audit type: static code, flow, data, security and test-coverage review. No source code was changed by this audit.

## Executive verdict

The plugin has broad functionality for plans, guest checkout, Stripe webhooks, WooCommerce checkout hand-off, member settings, invoices, approval workflows, admin operations, branding and REST endpoints. It is not ready for production access-control or billing assurance until the bootstrap defect and provider/local-state recovery process are addressed.

### Severity summary

| Severity | Count | Current conclusion |
|---|---:|---|
| High | 3 | Must resolve before production |
| Medium | 5 | Must resolve or explicitly accept with an operator process |
| Low | 3 | Hardening and product completeness |

## High findings

### F-01 — Subscription access enforcement is not bootstrapped

**Evidence:** `daymark-subscriptions.php:40-45` instantiates REST, admin, provider, mailer and shortcode services, but never runs `new Daymark_Subscription_Access()`. The access constructor at `includes/class-daymark-subscription-access.php:10-16` is the code that registers `template_redirect`, menu filtering and Elementor hooks.

**Impact:** Page rules, native menu-item rules and Elementor access controls are not attached during normal plugin loading. A saved access rule may exist in the database but will not reliably protect the frontend content. The checkout-success static hook still runs, which can make the rest of the plugin appear healthy while protected content remains exposed.

**Status:** Open; static evidence is conclusive. Live browser proof was not run in this audit.

**Required direction:** Bootstrap the access service exactly once and avoid registering the checkout-success callback twice. Add a bootstrap contract test plus a live authenticated/unauthenticated access test.

### F-02 — Provider-first lifecycle mutations can diverge from local state

**Evidence:** Member cancellation at `includes/class-daymark-subscriptions-rest.php:356-364`, pause at `:376-381`, resume at `:392-396`, and immediate plan change at `:411-420` call `Daymark_Payment_Provider::sync_*()` first, then update/commit the local subscription.

**Failure path:** Stripe/WooCommerce accepts the remote mutation, then the local lock, update or transaction commit fails. The API returns an error while the provider has already changed. A later webhook/reconciliation may repair some states, but there is no explicit durable outbox/compensation record for this exact mutation.

**Impact:** Member can see stale plan/status/access; admin can repeat an action and create confusing billing state; support cannot reliably tell whether the remote operation succeeded.

**Status:** Open design risk; the failure path is statically confirmed, but an injected DB/provider failure was not executed.

**Required direction:** Add a mutation journal/idempotency record with remote intent, remote result, local result and retry state; make reconciliation expose per-subscription repair outcomes. At minimum, return a recoverable “remote changed, local sync pending” state instead of a generic 500.

### F-03 — WooCommerce recurring billing is not a native recurring-charge implementation

**Evidence:** `includes/class-daymark-payment-provider.php:110-122` creates a normal hidden/simple WooCommerce product and checkout URL. Renewal processing at `:192-201` creates a pending renewal order and defaults the automatic-charge filter to `false`. `includes/class-daymark-subscriptions-db.php:467-479` marks an unhandled renewal as `past_due` with `manual` payment status.

**Impact:** WooCommerce core checkout can collect the initial payment, but recurring payment depends on a compatible external gateway/adapter or manual customer payment. Trials and introductory cycles are explicitly rejected in `:114-115`. This does not provide the promised self-contained subscription billing alternative unless the configured WooCommerce gateway and renewal adapter are part of the deployment contract.

**Status:** Open product/architecture gap; code behavior is confirmed.

**Required direction:** Document WooCommerce mode as “checkout plus renewal-order orchestration” unless a gateway adapter is installed; add an admin readiness gate that detects whether automatic renewal charging is actually available.

## Medium findings

### F-04 — Guest checkout creates a WordPress account before payment succeeds

**Evidence:** `includes/class-daymark-subscriptions-rest.php:319-336` creates a subscriber account and sends the user notification before `checkout()` calls `Daymark_Payment_Provider::create_checkout_session()` at `:314-315`.

**Impact:** Abandoned, rejected or failed checkouts can leave orphan subscriber accounts. A failed mail delivery can also leave a customer without a usable password setup path. Repeated attempts are then blocked by the existing-email rule at `:325-326`.

**Status:** Open lifecycle gap; no cleanup/claim process was found in the reviewed flow.

**Required direction:** Track a pending checkout/account claim with expiry, provide password-set/login recovery UX, and clean up only accounts that have no subscription/order/activity after a safe retention period.

### F-05 — Uninstall deletes a generic WordPress/WooCommerce user meta key

**Evidence:** `uninstall.php:17` deletes `billing_phone` for every user while also deleting plugin-owned `daymark_phone`.

**Impact:** Enabling the plugin’s explicit data-delete uninstall mode can remove phone data owned by WooCommerce or another plugin. This is cross-plugin data loss and is not scoped to Daymark-owned records.

**Status:** Open; static evidence is conclusive.

**Required direction:** Delete only Daymark-owned metadata. Preserve generic keys unless the plugin created and exclusively owns them, with a documented migration/ownership rule.

### F-06 — Tests are static contracts, not runtime lifecycle tests

**Evidence:** `tests/test-contracts.php:1-5` describes dependency-free source-contract checks. `tests/README.md:3-11` explicitly states that fixtures do not prove provider signatures, database transactions, gateway charges or browser checkout.

**Impact:** The current suite can pass while the missing access-service bootstrap remains undetected. It also cannot prove REST permission behavior, DB transaction recovery, webhook retries, cron execution, email delivery, file upload behavior or real checkout redirects.

**Status:** Open coverage gap.

**Required direction:** Add a small WordPress integration suite for bootstrap/REST/DB and a browser smoke flow for guest checkout, approval, success, member settings and access denial. Keep the static contract suite as a fast guardrail.

### F-07 — Scheduled recovery depends on WP-Cron and has limited operator feedback

**Evidence:** `includes/class-daymark-subscriptions-db.php:147-149` schedules daily reconciliation and `:426-445` processes renewals through scheduled hooks. The admin copy at `includes/class-daymark-subscriptions-admin.php:332` itself recommends a persistent cron runner for low-traffic sites.

**Impact:** On low-traffic or misconfigured sites, renewals, pause expiry, reminders and reconciliation can be delayed. The current monitor exposes next scheduled timestamps, but the reviewed flow does not provide a durable per-record retry/dead-letter view for failed reconciliation or renewal work.

**Status:** Medium operational risk; schedule behavior is confirmed, live cron execution was not tested.

**Required direction:** Document real server cron as a production requirement, store job outcome/error counters, and provide a retryable failed-job list with last attempt and next retry.

### F-08 — Email-change confirmation is a state-changing GET endpoint without explicit throttling

**Evidence:** `includes/class-daymark-subscriptions-rest.php:27` registers `GET /me/email-change/confirm` with public permission. The confirmation token is the capability, so this is not automatically exploitable, but confirmation occurs through a read request and no endpoint-specific rate limit is visible in the route declaration.

**Impact:** Link scanners, browser prefetchers or repeated requests can consume a single-use token or trigger the email change before the user intentionally opens it. Abuse monitoring and user-visible confirmation are limited.

**Status:** Low/medium hardening item; token quality and expiry should be validated in a runtime test.

**Required direction:** Prefer a confirmation page plus POST, or explicitly make the GET idempotent and require a follow-up POST; add rate limiting and audit records for accepted/rejected confirmations.

## Low findings and product completeness gaps

### F-09 — Admin plan editing still exposes a manual slug field

The create-plan flow removes the slug UI in JavaScript, but the edit drawer still renders `URL slug` at `assets/admin-dashboard.js:77`. This is inconsistent with the newer “slug generated from plan name” rule and lets an admin rename a public identifier after links may already exist. Decide whether slugs are immutable after creation; the safer default is to keep them immutable and offer a redirect/alias migration if needed.

### F-10 — Dynamic admin HTML uses `innerHTML` extensively

`assets/admin-dashboard.js:20-21,34-36,44,77,101,139,143,148,209,257` uses `innerHTML`. Most strings are static or localized, but this pattern makes future insertion of plan/member data vulnerable to accidental HTML injection. The reviewed snippets did not prove an active exploit, so this is a maintainability/security-hardening item. Prefer DOM construction/text assignment for API-derived values and add an escaping contract for drawer data.

### F-11 — WooCommerce and Stripe member billing UX is not equivalent

Stripe exposes a billing portal through the provider, while WooCommerce billing portal is explicitly unsupported at `includes/class-daymark-payment-provider.php:60`. WooCommerce pause/resume and provider-side plan changes are also explicitly unsupported at `:132-141`. The UI should make these provider-specific limitations visible before the member clicks an unavailable action.

## What is already present

The current code includes these substantial areas, so they were not classified as missing:

- Public plan catalog and native guest checkout endpoint.
- Stripe test/live mode, webhook signature verification, event claiming and replay.
- Development bypass mode.
- Direct approval and admin-review subscription paths.
- Pending subscription admin view and approval/rejection workflow.
- Member profile/settings, profile-picture upload, email change and privacy export/deletion request endpoints.
- Subscription pause, resume, cancel, period-end plan change and immediate-plan setting.
- Invoice persistence and member invoice access.
- Admin plan create/update/archive/delete guard, subscriber drawer, bulk actions and billing actions.
- Design/branding settings, frontend width setting and transactional HTML template editor.
- Page/menu access rule storage and editor UI; runtime enforcement is blocked by F-01.

## Missing or incomplete process map

### Admin processes to complete

1. Provider readiness: verify credentials, webhook secret, environment, currency, automatic tax and renewal capability before enabling checkout.
2. Pending review: approve/reject, email delivery result, payment link status, expiry and resend/recovery action.
3. Payment recovery: failed payment, retry, invoice state, provider/local reconciliation and escalation history.
4. Lifecycle repair: remote mutation result versus local commit result, retry and operator acknowledgement.
5. Renewal operations: cron health, failed jobs, manual retry, renewal-order ownership and gateway capability.
6. Account operations: resend password setup/login link, orphan checkout cleanup, duplicate-account review and account deletion impact.
7. Access operations: confirm page/menu/Elementor enforcement is active after bootstrap and provide a protected-content test utility.

### Member processes to complete

1. Guest checkout account claim/password setup when payment is abandoned or email delivery fails.
2. Clear provider-specific billing actions: update payment method, retry payment, invoice URL, cancel, pause and resume.
3. Plan change preview: current plan, next plan, effective date, proration/charge result and rollback/error state.
4. Pending-review state: expected review SLA, approval email status, payment link expiry and support path.
5. Failed renewal state: actionable payment recovery link and exact access/grace-period explanation.
6. Data/privacy: deletion consequences for invoices/subscription history and confirmation status.

## Settings audit

### Admin settings present

- Payment provider, environment/dev mode, Stripe keys/webhook secrets, currency, automatic tax.
- Plan-change timing, cancellation mode, pause duration, grace days and renewal reminder days.
- Default approval mode and per-plan approval mode.
- Admin notification recipient, sender/reply-to, event toggles and transactional HTML templates.
- Branding color, logo, content width and design tokens.
- Page access rule and native menu access rule storage.

### Admin settings still needed or should be made explicit

- Production readiness checklist with hard blockers and last successful webhook/reconciliation timestamps.
- Renewal capability status for WooCommerce gateway/adapter.
- Retry/dead-letter settings and operator retry controls.
- Checkout-account retention/cleanup period.
- Email delivery diagnostics and resend controls.
- Immutable plan slug policy and migration behavior.
- Data-retention policy for invoices, audit logs, deleted users and orphan media.

### Member settings present

- Name/display name/profile description.
- Profile picture upload.
- Product update preference and profile visibility.
- Email-change request/confirmation.
- Privacy export and deletion request.
- Subscription management, invoices, pause/resume/cancel and plan change.
- Stripe billing portal/payment recovery where supported.

### Member settings still needed or should be made explicit

- Password setup/reset path for guest-created accounts.
- Provider-specific payment-method management messaging.
- Pending-review and pending-payment status details.
- Renewal failure timeline, retry state and grace-period end date.
- Account deletion impact summary and confirmation receipt.

## Verification performed

- Read current bootstrap, access, REST, provider, Stripe, DB, uninstall, admin and test files.
- Checked source references and line-level evidence for the findings above.
- Static contract test and PHP syntax lint were previously run successfully in this checkout.
- The PHP runtime emitted a non-blocking missing Xdebug DLL warning in the local WAMP setup.

## Verification not performed / limits

- No WordPress browser session, live REST request, database transaction injection, Stripe sandbox, WooCommerce gateway renewal, email delivery or WP-Cron execution was run.
- The repository has no PHPUnit/WordPress integration suite or CI manifest; the checked-in tests are dependency-free static contracts and fixtures.
- Automated security scanner and Python-based audit tooling were not run because the project instructions prohibit running Python from chat.

## Recommended order of work

1. Fix and test F-01 access bootstrap.
2. Define the production billing contract for WooCommerce versus Stripe and add readiness checks for F-03.
3. Add lifecycle mutation journal/reconciliation for F-02.
4. Fix uninstall ownership for F-05.
5. Add integration/browser coverage for F-06, then validate checkout, approval, renewal and access rules in a local WordPress browser session.
6. Add operator/member recovery processes and UX from F-04, F-07, F-08 and F-11.
