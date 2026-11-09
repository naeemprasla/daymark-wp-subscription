# Daymark Subscriptions — Implementation Audit

**Date:** 2026-11-05  
**Source of truth:** `docs/missing-features-findings-2026-11-05.md` (not modified)

## Implemented in this pass

- Durable provider-operation journal with provider/local result fields, status, attempts, errors, and retry metadata.
- Admin reconciliation REST endpoints and an Audit Log reconciliation surface.
- Provider renewal capability reporting for Stripe, WooCommerce adapter-backed renewal, manual WooCommerce renewal, and development bypass.
- Guarded scheduled renewal/reconciliation runners with durable job status and failure metadata.
- Member subscription activity timeline backed by subscription audit events.
- WordPress locale normalization for browser date rendering (`en_US` → `en-US`).
- Explicit email-change confirmation page and POST action; state-changing confirmation is no longer a GET mutation.
- Single-use WordPress password setup links in activation and review-approval emails.
- Admin overview payment/integration health signals: payment success, webhook failures, reconciliation errors, and pending reviews.
- Plans & Pricing now surfaces the WooCommerce manual-renewal limitation when no automatic renewal adapter is registered.
- Abandoned guest-account cleanup now uses a bounded admin-configurable retention period instead of a hard-coded value.
- Scheduled jobs now expose explicit retry controls through admin, REST, and the server SDK; three failed attempts move a job to dead-letter until manually retried.
- Member billing timeline now combines subscription lifecycle audit entries with local invoice status events; provider identifiers remain excluded.
- Plan-change confirmation now requests a read-only preview before enabling confirmation. Stripe can return the upcoming invoice amount; WooCommerce and development mode return an explicit provider-preview-unavailable state instead of guessing.
- Guest account recovery now has a generic account-access page, REST endpoint, server SDK method, IP rate limit, and one-time password setup email; unknown addresses receive the same response.
- Scheduled-job retry metadata now uses bounded exponential backoff before the dead-letter threshold.
- Admin billing adjustments now record idempotent operation state and reason metadata; provider-success/local-failure is returned as a reconciliation-required response.
- Uninstall cleanup now covers the operation journal and scheduled-job tables; mail delivery failures are recorded without storing the recipient address.
- Admin plan changes and revokes now have durable operation state and reasoned audit records; the REST/server SDK and admin UI recognize `manage_daymark_subscriptions`.
- The admin UI now uses `manage_daymark_subscriptions`; billing and review REST actions also enforce their dedicated capabilities, with administrator compatibility preserved.
- Operational reporting now includes failed email delivery counts.
- Member cancellation, pause, resume, and immediate plan changes now reuse stable operation keys and safely short-circuit only when the target state is already applied.
- Renewal payment outcomes, payment issues, and period-end renewal cancellation are now emitted as timeline audit events.
- Scheduled-job acknowledgement is now persisted after successful resolution and exposed in the Audit Log, REST API, and server SDK.
- Stripe provider requests now record secret-free status and latency telemetry; Overview reports provider errors and average latency for the selected window.
- Added a read-only WordPress bootstrap smoke test covering all Daymark tables, current migration version, critical REST routes, and default public pages.
- Extended the read-only WordPress smoke test to exercise public plans access, anonymous protected-route denial, and authenticated invalid-input validation.
- Hardened the plans REST route method boundary and replaced incompatible native numeric sanitizers with argument-safe callbacks.
- Expanded the admin retention policy card to state which subscription, invoice, audit, profile, and abandoned-account records are preserved or removed.
- Added an isolated local lifecycle integration test for guest checkout/account creation, invoice/access, pause/resume, immediate plan change, cancellation, and temporary-data cleanup in Development mode.
- Added an isolated signed-webhook integration test for signature verification, duplicate suppression, paid renewal, and failed-renewal recovery using checked-in fixtures without contacting Stripe.
- Added configurable trial-ending and introductory-price expiry reminders using durable per-subscription claims, scheduled maintenance, admin event controls, and transactional templates.
- Added durable price, currency, and interval snapshots for subscriptions; existing records are backfilled during the `1.15.1` migration so later plan edits do not silently change their agreed renewal price.
- Added provider-neutral plan pricing controls for one-time setup fees, Stripe promotion-code opt-in, quantity/seat bounds, minimum commitment cycles, and per-plan tax behavior; checkout, subscription quantity, commitment enforcement, activation invoices, Stripe price creation, and WooCommerce cart/order metadata now carry the selected policy.
- Added an explicit schema-column guard in migration `1.15.1` because the live installation’s `dbDelta()` path did not add the commitment column reliably.
- Added optional second-admin approval enforcement for review decisions, subscription revokes, and billing actions. Requests are journaled as `awaiting_second_approval`, require a different administrator at the approval endpoint, and are exposed through the REST/server SDK contracts.
- Added the server SDK checkout quantity argument and prevented Stripe from combining promotion-code entry with an introductory coupon.

## Verification evidence

| Check | Result | Evidence |
|---|---|---|
| PHP syntax | PASS | WAMP PHP 8.0.30 linted every PHP file under the plugin. |
| Contract smoke tests | PASS | `tests/test-contracts.php` returned `Daymark contract smoke tests passed.` |
| Local WordPress integration smoke test | PASS | `tests/test-wp-integration.php` bootstrapped the site and verified all Daymark tables, current migration version, critical REST routes, default pages, public plans access, anonymous protected-route denial, and authenticated invalid-input validation. |
| Local lifecycle integration test | PASS | `tests/test-wp-lifecycle.php` verified a temporary guest checkout/account, activation invoice, access, pause/resume, immediate plan change, immediate cancellation, and zero-residue cleanup while provider calls were bypassed in Development mode. |
| Local signed-webhook integration test | PASS | `tests/test-wp-webhooks.php` verified signed fixture processing, duplicate suppression, paid renewal, failed-renewal recovery state, and cleanup without contacting Stripe. |
| Plans REST permission boundary | PASS | Anonymous POST with valid plan fields is rejected before plan creation; public GET remains available. |
| Grandfathered pricing persistence | PASS | Subscription creation, approval activation, plan-change renewal, and activation-invoice paths use durable subscription snapshots. |
| Catalog pricing policy propagation | PASS | REST/admin plan fields are validated and propagated to Stripe Checkout, development subscriptions, WooCommerce checkout metadata, local quantity, setup-fee invoices, and commitment enforcement. |
| Second-admin approval contract | PASS | Contract smoke test covers the approval route, durable operation status, operation ID lookup, and server SDK approval method; live execution remains dependent on two administrator accounts. |
| Public plans REST endpoint | PASS | `GET http://localhost/spa-wp-test/wp-json/daymark/v1/plans` returned HTTP 200. |
| REST route registration | PASS | Live WordPress REST index contains plan preview, guest account access, billing/revoke, scheduled-job retry/acknowledgement, and email confirmation routes. |
| Admin audit page | PASS | Chrome rendered scheduled-job health and provider reconciliation sections. |
| Public plans page | PASS | Chrome rendered `/plans/` with active plan catalog and provider messaging. |
| Member subscription page | PASS | Chrome rendered active subscription, invoice receipt, and activity timeline. |
| Email confirmation page | PASS | Chrome rendered `/email-confirmation/` with incomplete-link state. |
| Admin operational metrics | PASS | Chrome rendered payment success, webhook failure, reconciliation error, email failure, provider error/latency, and pending-review values on Overview. |
| Admin second-admin operations surface | PASS | Chrome rendered the Audit Log section for sensitive actions waiting for approval, including the empty state and approval table structure. |
| Second-admin lifecycle setting | PASS | Chrome rendered the setting checkbox inside the Subscription lifecycle tab after the selector fix. |
| Native checkout page | PASS | Chrome rendered `/checkout/?plan_id=2` with selected plan, guest details fields, provider state, and Development mode activation CTA. |
| Design page layout | PASS | Chrome screenshot showed the repaired three-column token/status controls with consistent field sizing and no vertical control collapse. |
| Design page hierarchy | PASS | Brand system is rendered before design tokens, status palette, logo, and page preview so the primary branding action is first. |
| Design page action sizing | PASS | Save controls are constrained to compact action widths rather than full-card width. |

## Not verified in this environment

- Real Stripe Sandbox Checkout, webhook signature, renewal, payment retry, refund, and proration behavior.
- Real Stripe upcoming-invoice plan-change preview and matching mutation/proration-date behavior.
- WooCommerce gateway tokenization and automatic recurring charge adapter behavior.
- Real email delivery, inbox rendering, and password-reset link consumption.
- WP-Cron execution under production traffic and persistent cron configuration.
- Multi-admin capability separation and real browser interaction of admin retry actions.

## Remaining product work

The remaining verification boundary is provider-sandbox end-to-end behavior and real two-administrator browser execution. Durable grandfathered pricing, defined catalog pricing policies, and optional second-admin approval controls are implemented.

## Provider study record

Selected gateway: Stripe Checkout/subscriptions in Sandbox or Live mode, plus WooCommerce Core Checkout with an explicit manual-renewal boundary when no adapter is available.

Official Stripe references reviewed for this implementation: [subscriptions](https://docs.stripe.com/billing/subscriptions), [subscription API](https://docs.stripe.com/api/subscriptions), [webhook signatures](https://docs.stripe.com/webhooks/signature), [webhook retries](https://docs.stripe.com/webhooks), [idempotency](https://docs.stripe.com/api/idempotent_requests), [3DS](https://docs.stripe.com/payments/3d-secure), [refunds](https://docs.stripe.com/refunds), [disputes](https://docs.stripe.com/disputes), and [invoices](https://docs.stripe.com/invoicing). Checked 2026-11-05.

Provider behavior not verified locally: real Stripe checkout/webhooks/proration/3DS/refunds, WooCommerce tokenized recurring gateways, email delivery, and production cron reliability.
# Design-token propagation audit — 2026-11-05

## Scope

Checked the configured brand color, design tokens, shared content width, font family, radius, button treatment, form fields, labels, status colors, modal/drawer surfaces, frontend pages, checkout success, and access-revoked template.

## Findings and fixes

- Admin token variables were scoped only to the main admin root and modal; body-mounted drawers could fall back to defaults. Drawer and plan-edit drawer roots now receive the same token set.
- Admin modal/drawer fields and primary buttons now use the configured surface, border, radius, font, accent, and button treatment.
- Frontend token construction was duplicated in the shortcode and omitted a consistent border/soft-surface mapping. It is now centralized and reused by all shortcode pages.
- Checkout success and access-revoked templates now receive the same frontend design tokens instead of only the accent color.
- Author profiles and native WordPress post/menu access controls now use the canonical token scope rather than an accent-only or theme-default fallback.

## Verification

- PHP lint was run for the changed PHP files.
- Contract, WordPress integration, lifecycle, and webhook smoke tests passed after the changes.
- Live browser verification should cover Design, Plans & Pricing, Checkout, Settings, Subscription Management, checkout success, and access-revoked states after cache refresh.
- Added and verified a WordPress-core override guard for `.button-primary` so configured brand color/button treatment remains authoritative.

## Pending approvals follow-up

- Review actions now enforce the review capability, preserve the Pending approvals page context, pass the optional reason to the REST flow, and display the actual failure message.
- Native review submits retain the existing loading state behavior and remain nonce-protected.

## Account deletion administrator protection — 2026-11-06

- Confirmed administrator deletion requests remain visible in Pending approvals and are labeled as protected.
- Confirmed the admin UI does not expose the anonymize action for administrators.
- Added server-side protection to both the public REST request path and the admin approval POST path.
- Rejection remains available so an administrator request can be cleared without changing the account.
- Static verification covers the REST error code, admin guard, protected UI copy, and server SDK helper.

## Admin subscription list visibility — 2026-11-06

- Verified the local database contains subscription data and a valid user/plan join.
- Added explicit super-admin authorization to the admin, REST, and server SDK capability paths.
- Changed the admin subscription list/count joins to left joins so historical rows remain visible if a related WordPress user or plan is missing.

## Checkout environment and gateway readiness — 2026-11-06

- Development mode uses the existing local bypass and is labeled testing-only.
- Production mode requires the selected provider to be configured before checkout can be opened.
- Stripe readiness uses the selected Sandbox/Live key pair plus webhook secret; the selected Stripe mode is exposed to the Choose a plan and checkout UI.
- WooCommerce readiness requires WooCommerce checkout plus at least one enabled payment gateway.
- Browser/provider-dashboard verification remains unavailable in this local run.
