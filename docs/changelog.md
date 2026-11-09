# Changelog

# 2026-11-09 — Subscription management asset path fix

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — frontend loading fix
- Summary: Corrected centralized JavaScript filename mappings so subscription management, checkout, plans, privacy, and member settings scripts load from their actual asset files.
- Files touched: daymark-subscriptions.php, tests/test-contracts.php, Readme.md, docs/changelog.md, docs/audit-report.md

# 2026-11-09 — Centralized asset loading

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — asset architecture session
- Summary: Centralized CSS and JavaScript file enqueueing in the main plugin bootstrap while preserving existing shortcode localization and inline branding configuration.
- Files touched: daymark-subscriptions.php, includes/class-daymark-subscriptions-admin.php, includes/class-daymark-subscriptions-shortcode.php, includes/class-daymark-subscription-access.php, templates/author.php, docs/changelog.md, docs/audit-report.md

# 2026-11-09 — Audit log pagination

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — admin audit log session
- Summary: Added server-side pagination to the super-admin audit log table with ten records per page and bounded page state; existing audit-log callers remain compatible.
- Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`

# Unreleased — 2026-11-09

- Added `docs/settings-architecture.md` as the working specification for super-admin, delegated-admin, and member settings.
- Started the settings implementation with validated business/site name and customer support email options, using WordPress-native defaults.
- Files touched: `docs/settings-architecture.md`, `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-mailer.php`, `uninstall.php`, `tests/test-contracts.php`, `docs/changelog.md`.
- Completed the remaining settings/runtime slice: bounded retry controls, all-session logout, email delivery metadata with safe resend actions, delayed privacy anonymization retention, and per-plan pause/cancellation policy overrides.
- Added additive database migration `1.19.0`; no billing or profile history is rewritten.
- Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-mailer.php`, `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.js`, `assets/privacy-security.js`, `uninstall.php`, `tests/test-contracts.php`, `docs/audit-report.md`, `docs/changelog.md`.

# 1.31.0 — 2026-11-09

- Aligned checkout persistence with the immutable product architecture: durable pending checkout records, idempotent replay, canonical payment and activation states, and verified-payment activation boundaries.
- Added plan-level refund-policy storage and secure server-side plan editing support (`DB_VERSION 1.17.0`).
- Added invoice, payment-recovery, and expiry notification events plus URL placeholders for transactional HTML templates.
- Added pending-activation retention policy; expired unclaimed access is marked expired while subscription, invoice, and audit history remain intact.
- Made password-claim activation transactional at the subscription boundary so account access is not promoted when the subscription activation fails.
- Added Stripe and WooCommerce verified-payment handling for pending activation and updated invoice fallback behavior.
- Updated plugin version and release documentation.
- Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-mailer.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-stripe.php`, `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-sdk.php`, `assets/subscriptions-ui.js`, `assets/subscription-management.js`, `includes/class-daymark-subscriptions-shortcode.php`, `tests/test-contracts.php`, `tests/test-wp-lifecycle.php`, `uninstall.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/developer-documentation.md`, `docs/audit-report.md`, `docs/changelog.md`.

# 1.30.1 — 2026-11-09 — Documentation

- Added `docs/product-architecture-recommendations.md` with the recommended subscription state model, admin options, visitor/member journeys, lifecycle policies, and security boundaries.
- No plugin code, database schema, REST contract, or runtime behavior changed.
- Files touched: `docs/product-architecture-recommendations.md`, `docs/changelog.md`.

# 1.30.1 — 2026-11-09

- Hardened guest checkout so an existing WordPress account cannot be mutated through an unauthenticated checkout request.
- Bound WooCommerce checkout tokens to the order billing email before assigning a Daymark account or activating a subscription.
- Sanitized WYSIWYG HTML before it enters the browser editor and on editor synchronization, while preserving supported HTML editing.
- Fixed bulk admin actions so each child request receives its selected subscription ID.
- Aligned SDK review, billing, and cross-member billing URL operations with their intended capability boundaries.
- Preserved the Stripe idempotency key for immediate cancellation requests.
- Added contract assertions for the ownership, bulk-action, capability, and idempotency boundaries.
- Files touched: `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-sdk.php`, `includes/class-daymark-stripe.php`, `assets/admin-dashboard.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

# 1.30.0 — 2026-11-07

- Upgraded the server-side SDK contract to `1.3.0` with additive availability/capability metadata, secure member/admin authorization helpers, member timeline access, and idempotency-aware lifecycle methods.
- Added the `daymark_subscriptions_loaded` add-on bootstrap action and documented provider/readiness boundaries.
- Added verified checkout, activation, access, invoice, payment-recovery, reconciliation, expiry, and provider-deletion event hooks.
- Added SDK reference documentation and independent add-on examples without reintroducing a browser SDK or catalog write access.
- Files touched: `daymark-subscriptions.php`, `includes/class-daymark-subscriptions-sdk.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-stripe.php`, `docs/developer-documentation.md`, `docs/sdk-reference.md`, `docs/sdk-examples/`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`, `tests/test-contracts.php`.

# 1.29.32 — 2026-11-07

- Normalized input, select, textarea, border radius, height, padding, focus and color styling across Create Plan and Edit Plan controls.
- Files touched: `assets/admin-dashboard.css`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

# 1.29.31 — 2026-11-07

- Matched checkbox helper-text spacing across Create Plan and Edit Plan forms.
- Files touched: `assets/admin-dashboard.css`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

# 1.29.30 — 2026-11-07

- Locked both Create Plan and Edit Plan forms to a shared full-width two-column desktop grid, while preserving one-column mobile stacking.
- Files touched: `assets/admin-dashboard.css`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

# 1.29.29 — 2026-11-07

- Fixed Create Plan drawer specificity so it uses the same full-width two-column desktop layout as Edit Plan instead of inheriting the generic single-column drawer form rule.
- Preserved one-column stacking for both plan forms below the mobile breakpoint.
- Files touched: `assets/admin-dashboard.css`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

# 1.29.28 — 2026-11-07

- Corrected Edit Plan currency handling: the settings-owned currency value is now retained as a hidden input rather than an accessible visible field, matching Create Plan and preventing accidental currency edits.
- Files touched: `assets/admin-dashboard.js`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

# 1.29.27 — 2026-11-07

- Unified Create Plan and Edit Plan drawer forms under the same shared plan-form layout, including field rhythm, two-column desktop grid, full-width long-text fields, checkbox rows, action alignment, and mobile stacking.
- Kept currency settings-owned by hiding the Edit Plan currency control while preserving the existing stored value during the update request.
- Added a shared `daymark-plan-form` class and bumped asset versioning.
- Files touched: `assets/admin-dashboard.css`, `assets/admin-dashboard.js`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

# 1.29.26 — 2026-11-07

- Refined the shared admin and frontend visual system around the Quiet Operations Console direction: semantic token aliases, restrained flat surfaces, consistent controls, accessible focus states, and responsive layout rules.
- Improved admin subscription tables, billing action density, settings tabs, checkbox rows, drawer/modal surfaces, empty states, and mobile stacking without changing REST or billing behavior.
- Normalized frontend plan, checkout, settings, profile, privacy, subscription-management, invoice, and action layouts; removed unnecessary hover lift/heavy shadows and limited full-width buttons to mobile/checkout contexts.
- Added reduced-motion and intermediate viewport rules for the shared plugin surfaces.
- Files touched: `assets/admin-dashboard.css`, `assets/frontend.css`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.29.25 — 2026-11-07

- Completed the Stripe invoice event matrix for finalized, paid, and payment-action-required states.
- Prevented the local renewal cron from claiming Stripe provider-managed renewals.
- Added durable WooCommerce renewal operation records, operation keys on renewal orders, adapter status normalization, and success/failure updates.
- Preserved WooCommerce adapter compatibility while allowing explicit `paid`, `failed`, or `pending` renewal results.

## 1.29.24 — 2026-11-07

- Added verified account activation state: guest checkout accounts remain non-entitled until verified payment and password claim; development bypass remains active for local testing.
- Removed the default WordPress new-user email from pre-payment guest checkout and made abandoned pending accounts recoverable through the existing cleanup job.
- Added WooCommerce production capability gating for enabled tokenized gateways and the compatible Daymark renewal adapter; unsupported production checkout now fails closed.
- Added the admin readiness REST contract and server SDK provider capability helpers.
- Added lifecycle regression coverage for pending activation access isolation and password-claim activation.

## 1.29.22 — 2026-11-06

- Restored subscription records for super administrators and prevented orphaned user/plan joins from hiding admin subscription rows.

## 1.29.23 — 2026-11-06

- Added one checkout readiness contract for development bypass, Stripe Sandbox/Live, and WooCommerce enabled-gateway conditions.
- Blocked production checkout when the selected gateway is not configured; development bypass remains explicitly testing-only.
- Added provider mode and readiness messaging to the public Choose a plan and checkout flows.

## 1.29.20 — 2026-11-06

- Completed the Pending approvals admin workflow audit and fixed review context, reason capture, capability enforcement, and visible error feedback.

## 1.29.19 — 2026-11-06

- Added a high-specificity token guard so WordPress core cannot reintroduce blue primary buttons over the configured Daymark brand treatment.
- Pending approvals now preserves the current admin page after review, accepts an optional review reason, and surfaces REST/provider errors instead of silently redirecting away.

## 1.29.18 — 2026-11-06

- Completed the design-token propagation audit for author profiles and native WordPress editor/menu access controls.

## 1.29.17 — 2026-11-05

- Audited design-token propagation across admin forms, labels, fields, buttons, drawers, modals, frontend templates, checkout success, and access-revoked states.
- Centralized frontend token generation and applied configured surface, border, typography, radius, status, focus, and button treatments consistently.
- Extended the same token scope to author profiles and WordPress native post/menu access controls.

## 1.29.16 — 2026-11-05

- Applied the compact action width directly to all Design layout-token primary buttons, including inherited WordPress button variants.

## 1.29.15 — 2026-11-05

- Added a page-local admin UI guard so Design page submit inputs remain compact even when WordPress or theme CSS overrides button widths.

## 1.29.14 — 2026-11-05

- Added a direct submit-input width guard for the Design page to override inherited WordPress admin button sizing.

## 1.29.13 — 2026-11-05

- Locked Design page save buttons to a compact 148px action width so inherited WordPress width rules cannot expand them.

## 1.29.12 — 2026-11-05

- Tightened Design page action selectors so WordPress global submit styles cannot stretch compact buttons to full card width.

## 1.29.11 — 2026-11-05

- Made Design page save actions compact instead of stretching them across the full card width.

## 1.29.10 — 2026-11-05

- Moved the Brand system section to the top of the Design page so accent color and primary brand controls are the first admin action.

## 1.29.9 — 2026-11-05

- Fixed the Design page token controls and status palette layout with responsive grids, consistent field sizing, and tighter card hierarchy.

## 1.29.8 — 2026-11-05

- Added optional second-admin approval enforcement for subscription review decisions, revokes, and billing actions, with durable waiting state, different-admin validation, REST approval endpoint, audit record, and server SDK support.
- Added the Audit Log operations surface for approving waiting second-admin actions from WordPress admin.
- Fixed the lifecycle settings selector so the second-admin checkbox is injected into the existing select-backed policy form.
- Added quantity input to the server SDK checkout contract and prevented Stripe promotion-code flags from being sent alongside an introductory coupon.

## 1.29.7 — 2026-11-05

- Added an explicit idempotent column guard for the commitment migration so existing WordPress installs receive the new subscription field even when `dbDelta()` does not alter the table.
- Added validated plan pricing controls for setup fees, promotion-code opt-in, quantity/seat bounds, minimum commitments, and per-plan tax behavior across REST, admin UI, Stripe Checkout, WooCommerce checkout metadata, development mode, invoices, and member billing displays.

## 1.29.6 — 2026-11-05

- Added durable subscription price, currency, and interval snapshots so existing subscribers retain agreed renewal pricing after plan edits.
- Added provider-neutral quantity and pricing-policy columns to the plan/subscription schema for future SDK/REST expansion.
- Bumped the database migration to `1.15.0`.

## 1.29.5 — 2026-11-05

- Added configurable trial-ending and introductory-price expiry reminders to the scheduled maintenance job, notification event controls, templates, and durable subscription reminder state.
- Added the local lifecycle integration test and retention policy verification described below.

## 1.29.4 — 2026-11-05

- Added secret-free Stripe provider request latency, status, and error telemetry to operational reporting; checkout now uses the same instrumented request path.
- Expanded the local WordPress smoke test to exercise public REST access, anonymous admin-route denial, and authenticated validation boundaries without mutating subscription data.
- Hardened the public/admin plans REST route registration so POST permission checks remain separate from public GET access, and fixed REST numeric sanitizers for current WordPress callback signatures.
- Expanded the admin retention policy card with explicit preservation/removal rules for billing, audit, profile, and abandoned-account data.
- Added an isolated local lifecycle integration test covering guest checkout/account creation, invoice/access, pause/resume, immediate plan change, cancellation, and cleanup.
- Added an isolated signed-webhook integration test covering signature verification, duplicate suppression, paid renewal, and failed-renewal recovery with checked-in fixtures.

## 1.29.3 — 2026-11-05
- Added durable scheduled-job acknowledgement after a successful resolution, with Audit Log, REST, server SDK, and migration support.
- Added renewal/payment outcome timeline events and stable idempotent member lifecycle operation keys.
- Added Daymark management, billing, review, and reporting capability boundaries across admin, REST, and SDK surfaces.
- Added a read-only local WordPress integration smoke test for migrated tables, REST routes, and default subscription pages.

## 1.29.2 — 2026-11-05
- Added the durable provider-operation journal and admin reconciliation/retry REST contract.
- Added operation status, attempt, retry, provider response, local response, and error fields to the subscription database migration.
- Exposed provider renewal capability so WooCommerce manual-renewal versus adapter-backed renewal is explicit.
- Added durable scheduled-job run status, attempts, failure details, retry time, and guarded renewal/reconciliation runners.
- Added member-facing subscription activity timeline data and lifecycle entries for pause, resume, cancellation, and plan changes.
- Normalized WordPress locale values such as `en_US` before browser date formatting to prevent frontend runtime errors.
- Replaced state-changing email confirmation GET links with an explicit confirmation page and POST action.
- Added single-use WordPress password setup links to activation and review-approval emails for guest-created accounts.
- Added the dated implementation audit with requirement evidence and explicit external-test boundaries.
- Expanded the admin overview with payment, webhook, reconciliation, and pending-review health signals.
- Added an explicit Plans & Pricing warning when WooCommerce is operating in manual-renewal mode without an automatic renewal adapter.
- Added bounded admin-configurable retention for abandoned guest checkout accounts while preserving billing history.
- Added explicit admin/REST/server-SDK retry controls for failed scheduled jobs. After three failed attempts, a job is marked dead-letter and remains paused until an administrator retries it.
- Added invoice status events to the member activity timeline without exposing provider identifiers.
- Added a read-only subscription plan-change preview route, server SDK method, Stripe upcoming-invoice proration preview, and explicit WooCommerce/development preview boundaries in the member drawer.
- Added a generic, rate-limited guest account-access page/REST/SDK flow that emails a one-time WordPress password setup link without revealing whether an email exists.
- Scheduled-job failures now use bounded exponential retry backoff before entering dead-letter state.
- Admin provider billing adjustments now persist operation state and reason metadata, and surface local invoice-sync failures as reconciliation-required instead of silently succeeding.
- Uninstall cleanup now removes durable operation and scheduled-job tables; failed `wp_mail()` calls are audit-recorded with a hashed recipient and sanitized subject.
- Admin plan changes and revokes now use durable operation records, require a revoke reason, and expose the reason in the audit trail; REST/server SDK admin access recognizes the Daymark management capability.
- Added Daymark management, billing, review, and reporting capabilities; the subscription admin menu and handlers now use the management capability, while sensitive REST operations enforce billing/review capabilities.
- Added email delivery failures to the admin operational report.
- Stabilized member lifecycle operation keys and guarded already-applied transitions so retries remain idempotent without blocking a later pause/resume cycle.
- Added renewal success/failure, payment-issue, and period-end renewal-cancellation audit events to the member billing timeline.
- Scheduled jobs now persist administrator acknowledgement after a successful resolution, with Audit Log, REST, and server SDK controls.

## Documentation — 2026-11-05
- Added `docs/missing-features-findings-2026-11-05.md` with current subscription-system gaps, missing flows, operational risks, and recommended implementation order.

## 1.28.68 — 2026-11-04
- Fixed the Create a plan Featured plan checkbox so it stays compact and inline instead of stretching as a full-width form control.

## 1.28.67 — 2026-11-04
- Removed the manual slug field from Create a plan.
- Generate a sanitized slug from the plan name and add a numeric suffix when the slug already exists.

## 1.28.66 — 2026-11-04
- Added built-in subject and HTML body examples for every transactional notification event.
- Kept custom fields blank by default so the existing built-in runtime messages remain the fallback until an admin saves a custom template.

## 1.28.65 — 2026-11-04
- Replaced the plain transactional body textarea with WordPress’s native syntax-highlighted HTML CodeMirror editor.
- Kept the stored value as HTML source and excluded it from the plugin’s generic WYSIWYG initializer.

## 1.28.64 — 2026-11-04
- Moved “Edit transactional copy” to a dedicated Transactional emails admin page.
- Replaced body textareas with HTML code editors and documented supported dynamic placeholders.
- Sanitized custom email HTML and sent saved custom bodies as scoped HTML emails while preserving built-in plain-text messages.

## 1.28.63 — 2026-11-04
- Normalized label-to-field spacing across admin form grids, settings forms, plan editing, and frontend checkout fields.
- Tightened helper-text rhythm and removed duplicate field margins so each form control follows one consistent vertical spacing scale.

## 1.28.62 — 2026-11-04
- Reorganized the admin Settings page into accessible General, Subscription, and Notifications tabs with hash deep-linking and keyboard navigation.
- Preserved all existing settings forms and save actions inside their original tab panels.

## 1.28.61 — 2026-11-04
- Reopened PHP mode after the Subscriptions pagination markup so `render_duplicate_notice()` is compiled as a class method instead of being emitted as page text.

## 1.28.60 — 2026-11-04
- Initialized duplicate-subscription notice rows before the database lookup and only accepted array results, eliminating undefined-variable and `count(null)` failures.

## 1.28.59 — 2026-11-04
- Added a caller-side array cast for the duplicate-subscription review notice so the admin page remains safe even if an older database class cache returns `null`.

## 1.28.58 — 2026-11-04
- Normalized duplicate-subscription lookup results to an empty array when the database returns no result, preventing `count(null)` fatal errors on the Subscriptions page.

## 1.28.57 — 2026-11-04
- Guarded the duplicate-subscription review notice so the Subscriptions admin page cannot fatal if an older cached class definition is served during a live update.

## 1.28.50 — 2026-11-04
- Added WordPress-localized runtime strings to the admin dashboard configuration and create-plan trigger.

## 1.28.49 — 2026-11-04
- Added the missing Renewal reminder checkbox to Notification policy controls.

## 1.28.48 — 2026-11-04
- Added WordPress-localized runtime strings for Subscription Management and Plans UI labels.
- Preserved JavaScript fallbacks for clients without localized data.

## 1.28.47 — 2026-11-04
- Hardened notification settings parsing against malformed nested POST values.

## 1.28.46 — 2026-11-04
- Added safe plain-text subject/body template controls for all supported notification events.
- Added placeholder substitution with built-in fallback copy and uninstall cleanup.
- Updated contract coverage and plugin metadata.

## 1.28.45 — 2026-11-04
- Added a bounded, once-per-period renewal reminder setting and subscription timestamp migration (`DB_VERSION 1.10.0`).
- Reset reminder eligibility after successful renewal and added mailer/contract coverage.

## 1.28.44 — 2026-11-04
- Applied the configured default approval mode to the admin Create Plan drawer as well as REST-created plans.
- Updated contract coverage and plugin metadata.

## 1.28.43 — 2026-11-04
- Added bounded scheduled pause-expiry detection and provider-aware auto-resume handling.
- Local access is restored only after the provider resume succeeds; unsupported WooCommerce behavior remains explicit.
- Updated contract coverage and plugin metadata.

## 1.28.42 — 2026-11-04
- Added attachment-backed logo upload/removal controls to Design settings.
- Applied the saved logo as a shared frontend heading token and added uninstall cleanup.
- Updated contract coverage and plugin metadata.

## 1.28.41 — 2026-11-04
- Added a member-facing Compare plans link to Subscription Management using localized Plans-page configuration.
- Updated contract coverage and plugin metadata.

## 1.28.40 — 2026-11-04
- Added per-event notification controls and a validated Reply-To address.
- Applied event preferences to member/admin lifecycle mail and cleaned them on uninstall.
- Updated contract coverage and plugin metadata.

## 1.28.39 — 2026-11-04
- Added lifecycle policy controls for default approval, default cancellation timing, and pause duration.
- Applied cancellation and pause policy defaults to authenticated member lifecycle actions.
- Updated contract coverage and plugin metadata.

## 1.28.38 — 2026-11-04
- Added validated success, warning, and danger branding tokens to Design settings.
- Applied the status palette to admin badges and frontend status/error actions.
- Updated contract coverage and plugin metadata.

## 1.28.37 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 23:52 PKT. Preserved per-plan CTA labels when the public plans UI recalculates button states, and added WordPress locale/timezone configuration to the plans script contract. Files touched: `assets/subscriptions-ui.js`, `includes/class-daymark-subscriptions-shortcode.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.36 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 23:38 PKT. Added plan catalog visibility and per-plan CTA label options end-to-end. Visible plans are returned by the public catalog; hidden active plans remain purchasable through a direct checkout URL. Added DB migration `1.9.0`, admin create/edit fields, REST validation, public card rendering, and contract coverage. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/admin-dashboard.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.35 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 23:04 PKT. Added a dependency-free page-level Design preview with representative plan, checkout, payment-warning, and empty-invoice states using the saved plugin tokens. Added responsive preview layout and contract coverage. Files touched: `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.34 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 22:41 PKT. Added a validated solid/outline button-treatment token and applied it to admin primary buttons and frontend actions, alongside the existing color, typography, surface, radius, and width tokens. Files touched: `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/frontend.css`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.33 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 22:22 PKT. Subscription Management now receives the WordPress locale and timezone, and invoice/period dates render through those settings instead of the browser’s local timezone. Added contract coverage. Files touched: `includes/class-daymark-subscriptions-shortcode.php`, `assets/subscription-management.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.32 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 22:07 PKT. Normalized injected design font stacks to CSS-safe unquoted families so saved token values render correctly in inline styles. Files touched: `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-shortcode.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

## 1.28.31 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 21:58 PKT. Added optional member cancellation feedback to the authenticated cancellation flow and audit log, plus a local text receipt download when a provider invoice URL is unavailable. Files touched: `includes/class-daymark-subscriptions-rest.php`, `assets/daymark-subscriptions-sdk.js`, `assets/subscription-management.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.30 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 21:42 PKT. Added a protected read-only member detail endpoint and admin drawer showing member identity, current/pending plan context, invoice history, and subscription audit timeline. User-controlled values are rendered with `textContent`; pagination and existing plan/revoke/billing actions remain unchanged. Added contract coverage. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-rest.php`, `assets/admin-dashboard.js`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.29 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 21:26 PKT. Corrected reporting for multi-currency catalogs: the global net total is shown only when the selected period contains one currency; otherwise the report requires the plan/currency breakdown. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.28 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 21:18 PKT. Added a date-filtered, invoice-backed admin billing report with net invoiced amount, paid/failed invoice counts, new subscriptions, cancellations, and revenue by plan. Added a compact dashboard filter and contract coverage. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.27 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 20:55 PKT. Expanded Design with validated ink, muted text, surface, font-stack, and corner-radius tokens. Tokens are injected into admin and frontend CSS variables, applied to shared surfaces, and removed during explicit uninstall. Added contract coverage. Files touched: `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/frontend.css`, `uninstall.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.26 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 20:39 PKT. Added public plan endpoint pagination (`page`, `per_page`), deterministic sort-order/name ordering, ascending/descending selection, and `X-WP-Total`/`X-WP-TotalPages` response metadata. Added contract coverage. Files touched: `includes/class-daymark-subscriptions-rest.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.25 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 20:32 PKT. Added Settings > Subscription email delivery with validated admin recipient, sender name/email, and an admin-alert toggle. Mailer sender filters are scoped to each message and member transactional emails remain enabled when admin alerts are disabled. Explicit uninstall cleanup and contract checks added. Files touched: `includes/class-daymark-subscriptions-mailer.php`, `includes/class-daymark-subscriptions-admin.php`, `uninstall.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.24 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 20:18 PKT. Added plan-level Stripe trials with a DB migration to `1.8.9`, validated REST/admin editing, public catalog metadata and frontend trial label, Stripe Checkout `trial_period_days`, and Development-mode period calculation. WooCommerce core checkout rejects trial plans explicitly rather than implying unsupported recurring-trial behavior. Added contract coverage and documentation. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-stripe.php`, `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/admin-dashboard.js`, `assets/subscriptions-ui.js`, `assets/frontend.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.23 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 19:41 PKT. Fixed the admin subscription pagination PHP output boundary after verification identified a duplicate PHP reopen token; pagination now passes the full contract and syntax checks. Files touched: `includes/class-daymark-subscriptions-admin.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

## 1.28.22 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 19:26 PKT. Added server-side pagination to the admin Subscriptions list with a count query, 50-record page size, and preserved status/search filters. Added DB/admin contract coverage and documentation. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.21 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 19:03 PKT. Fixed Design settings persistence so saving the accent color no longer resets a previously selected shared content width when the width field is absent from the submitted form. Files touched: `includes/class-daymark-subscriptions-admin.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`.

## 1.28.20 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 18:42 PKT. Added administrator review for privacy deletion requests under Pending approvals. Approved requests anonymize WordPress profile/contact fields, clear plugin profile metadata, preserve subscription/invoice history, and record an audit event; rejected requests clear the pending request. Added capability/nonce checks, contract coverage, and documentation. Files touched: `includes/class-daymark-subscriptions-admin.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.19 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 18:16 PKT. Added authenticated privacy data export and non-destructive account deletion-request workflows. Export omits provider IDs, deletion requests notify the site administrator and create an audit entry, and the Privacy & Security page adds both actions with loading/error states. Added uninstall cleanup, SDK/REST contracts, and documentation. Files touched: `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/privacy-security.js`, `assets/daymark-subscriptions-sdk.js`, `uninstall.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.18 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 17:52 PKT. Added a validated shared content-width design token with admin Design control and frontend CSS variable override for Plans, Checkout, Profile, Settings, Privacy, Subscription Management, and Author Profile containers. Added uninstall cleanup and contract coverage. Files touched: `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/frontend.css`, `uninstall.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.17 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 17:28 PKT. Added a validated Subscription Policy setting for payment grace days (1–90, default seven) and connected the renewal expiry worker to the saved value. Added nonce-protected admin form, uninstall cleanup, contract assertion, and documentation. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `uninstall.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.16 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 17:05 PKT. Completed provider-health coverage with webhook telemetry for last received, processed, and failed Stripe events. Clarified the WooCommerce manual-renewal boundary in the health response and readme, and surfaced webhook status in API Integration. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.js`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.15 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 16:42 PKT. Added provider-aware admin billing actions for refund/partial refund, credit, void, uncollectible, invoice resend, and payment retry. Actions resolve the subscription-owned Stripe invoice or WooCommerce renewal order, require an idempotency key, validate adjustment amounts and ownership, update local invoice status where applicable, and write an audit event. Added REST/SDK contracts and documentation. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-stripe.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-rest.php`, `assets/daymark-subscriptions-sdk.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.14 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 16:05 PKT. Added an authenticated past-due payment-recovery action. Stripe opens the existing billing portal; WooCommerce opens the unpaid renewal order payment URL after verifying ownership and payment state. Added the member Subscription Management retry button, SDK method, REST contract, readme entry, and smoke-test assertions. Files touched: `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/subscription-management.js`, `assets/daymark-subscriptions-sdk.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.13 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 14:45 PKT. Added a protected provider connection-health REST endpoint and API Integration “Test connection” action. Stripe verifies the configured mode against /v1/account; WooCommerce verifies checkout runtime availability without exposing credentials. Added the matching SDK method and contract assertion. Files touched: includes/class-daymark-stripe.php, includes/class-daymark-payment-provider.php, includes/class-daymark-subscriptions-rest.php, includes/class-daymark-subscriptions-admin.php, assets/admin-dashboard.js, assets/admin-dashboard.css, assets/daymark-subscriptions-sdk.js, tests/test-contracts.php, daymark-subscriptions.php, readme.txt, docs/changelog.md, docs/audit-report.md.
- Day: Wednesday; Date: 2026-11-04; Time: 14:58 PKT. Added authenticated member phone read/write settings with validation and a separate contact-details save action while preserving automatic profile-picture upload/crop. Files touched: includes/class-daymark-subscriptions-rest.php, includes/class-daymark-subscriptions-shortcode.php, assets/member-settings.js, tests/test-contracts.php, docs/changelog.md.
- Day: Wednesday; Date: 2026-11-04; Time: 15:18 PKT. Added verified member email-change flow with authenticated request, hashed single-use token, 24-hour expiry, conflict checks, confirmation endpoint, frontend status, SDK method, and uninstall cleanup. Files touched: includes/class-daymark-subscriptions-rest.php, includes/class-daymark-subscriptions-shortcode.php, assets/member-settings.js, assets/daymark-subscriptions-sdk.js, uninstall.php, readme.txt, tests/test-contracts.php, daymark-subscriptions.php, docs/changelog.md, docs/audit-report.md.
- Day: Wednesday; Date: 2026-11-04; Time: 15:42 PKT. Added structured plan benefits, featured-plan state, and catalog sort order with DB migration, REST/admin editing, and branded frontend plan-card presentation. Files touched: includes/class-daymark-subscriptions-db.php, includes/class-daymark-subscriptions-rest.php, includes/class-daymark-subscriptions-admin.php, includes/class-daymark-subscriptions-shortcode.php, assets/admin-dashboard.js, assets/frontend.css, tests/test-contracts.php, daymark-subscriptions.php, readme.txt, docs/changelog.md, docs/audit-report.md.

## Documentation audit — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 14:32 PKT. Added a read-only audit of missing member, plan, admin, design, operational, reporting, localization, and runtime-test options. Files touched: `docs/missing-options-audit-2026-11-04.md`, `docs/changelog.md`.

## 1.28.9 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 20:05 PKT. Extended the dependency-free contract smoke test to parse and validate Stripe dispute and refund fixtures, closing a test coverage gap in the payment-issue webhook contract. Files touched: `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.8 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 19:42 PKT. Removed internal Stripe Price and WooCommerce product mapping columns from the shared public plan projection; backend `get_plan()` provider lookups remain available where required. Files touched: `includes/class-daymark-subscriptions-db.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.7 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 19:18 PKT. Removed the retired Access & Entitlements/Feature Builder admin handlers, legacy settings page renderer, feature REST methods, and feature-specific admin JavaScript; existing stored legacy data was preserved but is no longer loaded as runtime functionality. Files touched: `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-rest.php`, `assets/admin-dashboard.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.6 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 18:42 PKT. Added duplicate-subscription detection and a review panel to the admin Subscriptions page. The flow links to filtered records and reuses existing bulk controls; it never deletes or merges billing history automatically. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.5 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 18:19 PKT. Added an ended-subscription recovery CTA in Subscription Management that routes canceled or expired members back to the existing branded Plans checkout flow; active members do not see it. Files touched: `includes/class-daymark-subscriptions-shortcode.php`, `assets/subscription-management.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.4 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 18:02 PKT. Added an explicit WooCommerce renewal-charge adapter filter. Automatic adapters must leave the renewal order paid before Daymark settles the local subscription; unsupported gateways continue through the existing payment-link fallback. Files touched: `includes/class-daymark-payment-provider.php`, `tests/test-contracts.php`, `readme.txt`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.3 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 17:43 PKT. Added checked-in Stripe webhook and WooCommerce renewal-order shape fixtures and extended the dependency-free contract test to parse and validate them. Files touched: `tests/fixtures/stripe-invoice-payment-succeeded.json`, `tests/fixtures/stripe-invoice-payment-failed.json`, `tests/fixtures/stripe-charge-dispute-created.json`, `tests/fixtures/stripe-charge-refunded.json`, `tests/fixtures/woocommerce-renewal-order.json`, `tests/test-contracts.php`, `tests/README.md`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.2 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 17:24 PKT. Added scheduled-job health cards to the admin Audit Log for renewal processing and Stripe reconciliation, including next local WP-Cron run times. Files touched: `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.1 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 17:06 PKT. Fixed bulk subscription UI response parsing so per-record REST failures are counted from the endpoint’s `failed` map instead of an unsupported array shape. Files touched: `assets/admin-dashboard.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.28.0 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 16:48 PKT. Subscription invoice email now includes the provider or WooCommerce invoice URL when one is available, while retaining a plain-text fallback for local/dev activations. Files touched: `includes/class-daymark-subscriptions-mailer.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.99 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 16:34 PKT. Removed the last live feature-entitlement custom-attribute evaluation; explicit plan/status content rules remain supported. Files touched: `includes/class-daymark-subscription-access.php`, `readme.txt`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.98 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 16:21 PKT. Removed provider-owned Stripe Price and WooCommerce product IDs from the public admin plan REST input contract; provider mappings remain internally managed. Added a negative contract assertion. Files touched: `includes/class-daymark-subscriptions-rest.php`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.97 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 16:08 PKT. Added admin subscription-table multi-select controls and loading-aware bulk revoke/plan-change UI backed by the existing protected REST endpoint. Files touched: `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.js`, `assets/admin-dashboard.css`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.96 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 15:44 PKT. Added protected bulk admin subscription actions for revoke and plan changes, capped at 50 records with per-record failure reporting, audit logging, and SDK support. Files touched: `includes/class-daymark-subscriptions-rest.php`, `assets/daymark-subscriptions-sdk.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.95 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 15:18 PKT. Added Stripe automatic-tax configuration, WooCommerce store-currency validation, and uninstall cleanup for the tax option. Files touched: `includes/class-daymark-stripe.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-admin.php`, `daymark-subscriptions.php`, `uninstall.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

- Added dependency-free high-risk contract smoke coverage at `tests/test-contracts.php`; it passed locally with PHP and does not require WordPress, WooCommerce, Stripe credentials, or a running server.

## 1.27.94 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 14:42 PKT. Added Stripe webhook payload retention, failed-event status preservation, idempotent replay through the normal event pipeline, and an admin operations table with replay controls. Bumped the DB migration and plugin asset version. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-stripe.php`, `includes/class-daymark-subscriptions-admin.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.93 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 14:04 PKT. Replaced the WooCommerce renewal fallback with an idempotent pending renewal-order flow: Daymark creates a renewal order, stores its subscription link, emails the pay URL, and reconciles processing/completed/failed order events without marking an unpaid renewal as settled. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-mailer.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

- Clarified WooCommerce automatic-charge boundary in the admin and readme: renewal orders are native core orders, while automatic gateway charging requires the gateway's saved-token contract.

## 1.27.92 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 13:32 PKT. Added a seven-day payment-failure grace window with `payment_issue_at` migration state; failed provider renewals now remain `past_due` during grace and expire afterward if no successful renewal is recorded. Files touched: `includes/class-daymark-subscriptions-db.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.91 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 13:06 PKT. Added a daily provider reconciliation job for local Stripe subscription state, with a bounded 50-subscription pass, scheduled-hook cleanup, and provider refresh through the existing subscription webhook state mapper. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-stripe.php`, `includes/class-daymark-payment-provider.php`, `daymark-subscriptions.php`, `uninstall.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.90 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 12:42 PKT. Added a database-backed administrator audit log with migration, admin viewer, uninstall cleanup, and records for plan changes, revocations, and approval decisions. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-admin.php`, `includes/class-daymark-subscriptions-rest.php`, `daymark-subscriptions.php`, `readme.txt`, `uninstall.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.89 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 12:18 PKT. Added an authenticated member billing-portal flow for Stripe payment-method updates, with explicit unsupported responses for development and WooCommerce gateway-managed billing. Updated the REST API, JavaScript SDK, member Subscription Management UI, and provider contract. Files touched: `includes/class-daymark-stripe.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/daymark-subscriptions-sdk.js`, `assets/subscription-management.js`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.88 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 11:56 PKT. Removed stale Access & Entitlements messaging and links from the live admin dashboard and plan creation UI after that feature was retired. Files touched: `includes/class-daymark-subscriptions-admin.php`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.87 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 11:42 PKT. Removed the deprecated REST profile-picture URL write path; profile pictures are now changed only through the authenticated multipart upload endpoint. Files touched: `includes/class-daymark-subscriptions-rest.php`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.86 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 11:30 PKT. Hardened billing state handling: existing guest emails now require login, Stripe period-end plan changes avoid duplicate proration invoices, historic Stripe prices remain resolvable after plan edits, refund/dispute/void/uncollectible events mark local payment state, and uninstall cleanup removes invoice data plus related options and user metadata. Files touched: `includes/class-daymark-stripe.php`, `includes/class-daymark-payment-provider.php`, `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-mailer.php`, `uninstall.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.85 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:21 PKT. Hardened plan upgrade rendering against missing plan labels, current-subscription markup, and plan cards so stale or partial markup cannot throw a null `textContent` error. Files touched: `assets/subscriptions-ui.js`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.84 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:45 PKT. Redesigned the Plans & Pricing catalog rows into a restrained operational ledger with clearer plan identity, price hierarchy, mapping metadata, status treatment, and responsive spacing. Files touched: `assets/admin-dashboard.css`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.83 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:14 PKT. Added user cancellation for pending period-end plan changes across the authenticated REST API, JavaScript SDK, and member Plans UI. Files touched: `includes/class-daymark-subscriptions-rest.php`, `assets/daymark-subscriptions-sdk.js`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/subscriptions-ui.js`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.82 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:31 PKT. Added per-plan current subscriber counts, a subscriber management drawer, admin plan reassignment using the configured timing policy, and immediate subscription revocation with protected REST and SDK methods. Files touched: `includes/class-daymark-subscriptions-db.php`, `includes/class-daymark-subscriptions-rest.php`, `includes/class-daymark-subscriptions-admin.php`, `assets/admin-dashboard.js`, `assets/admin-dashboard.css`, `assets/daymark-subscriptions-sdk.js`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.81 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:23 PKT. Scheduled plan changes now show `Scheduled` and disable the matching plan button until the pending change is applied. Files touched: `assets/subscriptions-ui.js`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.80 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:22 PKT. Fixed the Plans & Pricing edit drawer panel remaining off-screen after opening. Files touched: `assets/admin-dashboard.css`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.79 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:07 PKT. Fixed a null `textContent` error when the plan-change drawer markup is incomplete or stale. Files touched: `assets/subscriptions-ui.js`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.78 — 2026-11-04

- Day: Wednesday; Date: 2026-11-04; Time: 08:04 PKT. Fixed plan upgrades failing with `Invalid parameter(s): effective` when the optional REST parameter was omitted. Files touched: `includes/class-daymark-subscriptions-rest.php`, `daymark-subscriptions.php`, `docs/changelog.md`, `docs/audit-report.md`.

## 1.27.77 — 2026-11-04

- Added complete Plans & Pricing management: edit drawer, active/inactive archive control, safe unused-plan deletion, admin REST/SDK methods, and usage protection for plans referenced by subscriptions or invoices.
- Preserved the existing checkout success/thank-you redirect while keeping access enforcement hooks disabled.

## 1.27.76 — 2026-11-04

- Removed the Access & Entitlements admin page and runtime access/feature integrations: no submenu, editor meta box, menu-item controls, access shortcodes, feature REST routes, SDK feature helpers, or plan feature display remain active.
- Preserved existing access/feature option data and legacy class files without loading them, so removal does not destructively delete stored data.

## 1.27.75 — 2026-11-04

- Added a Super Admin Settings card for choosing whether member plan changes apply immediately or at the current billing period end; the REST lifecycle and member confirmation drawer now use this server-controlled setting.

## 1.27.74 — 2026-11-04

- Changed all member plan changes to activate at the current billing period end; upgrades no longer trigger an immediate prorated charge.

## 1.27.73 — 2026-11-04

- Added plan-change confirmation drawer, immediate Stripe/dev upgrades, period-end downgrade scheduling, pending-change messaging, and server-side protection against immediate downgrade requests.

## 1.27.72 — 2026-11-04

- Refined the Privacy & Security page into a balanced two-column layout with a wider privacy settings panel and readable security panel, while retaining responsive stacking on smaller screens.

## 1.27.71 — 2026-11-04

- Added functional Privacy & Security controls for public profile visibility and optional product-update consent, with authenticated REST persistence and a private author-profile state.

## 1.27.70 — 2026-11-04

- Enhanced Subscription Management with current plan/status hierarchy, billing amount, billing cycle, next billing date, payment status, improved action layout, and detailed invoice history states.

## 1.27.69 — 2026-11-04

- Redesigned the author profile template as a restrained editorial profile masthead with aligned avatar, identity, handle, and readable About section.

## 1.27.68 — 2026-11-04

- Replaced the static Profile shortcode page with the plugin-owned `author.php` archive template and legacy `/profile/` redirect.

## 1.27.67 — 2026-11-04

- Preserved visible brand borders on the crop modal actions against the active theme button reset.

## 1.27.66 — 2026-11-04

- Added a drag-to-adjust profile picture crop modal; only the confirmed square crop is uploaded and used across the profile.

## 1.27.65 — 2026-11-04

- Changed profile picture saving to upload automatically on file selection and removed the separate account access save button.

## 1.27.64 — 2026-11-04

- Removed the email field from Settings and changed the profile forms to a two-column desktop layout with mobile stacking.

## 1.27.63 — 2026-11-03

- Applied brand color tokens to Subscription Management and corrected action button border colors.

## 1.27.62 — 2026-11-03

- Made Subscription Management action button borders explicit against the active theme's `border-width: 0` reset.

## 1.27.61 — 2026-11-03

- Restored visible borders and full-width sizing for Subscription Management action buttons after the theme button reset.

## 1.27.60 — 2026-11-03

- Moved subscription status, pause/resume, cancellation, and invoice history into the dedicated Subscription Management page at `/subscription-management/`.

## 1.27.59 — 2026-11-03

- Split Member settings into personal details and account access forms, reordered name fields, and added the Privacy & Security password reset page.

## 1.27.58 — 2026-11-03

- Removed the WordPress theme content-width constraint from the member Settings template and set its outer width to 1200px.

## 1.27.57 — 2026-11-03

- Increased the member Settings page template width to 1200px.

## 1.27.56 — 2026-11-03

- Redesigned the member Settings page with a two-column account workspace, clearer form grouping, improved upload/editor states, subscription summary card, invoice hierarchy, and responsive mobile stacking.

## 1.27.55 — 2026-11-03

- Fixed the member settings file input lookup so selected profile pictures reach the upload endpoint.

## 1.27.54 — 2026-11-03

- Replaced the Profile picture URL field with authenticated image file upload, server-side type/size validation, media attachment storage, and live preview.

## 1.27.53 — 2026-11-03

- Added invoice history to Settings → Subscription management, including amount, status, issue date, and provider invoice links when available.

## 1.27.52 — 2026-11-03

- Added Profile picture URL with preview and rich Profile description editing to Settings; saved values now render on the read-only Profile page.

## 1.27.51 — 2026-11-03

- Changed the Profile page to a view-only branded card showing the member avatar, display name, and description; profile editing remains in Settings.

## 1.27.50 — 2026-11-03

- Added automatic branded Subscribe, Profile, Settings, and Checkout page provisioning when those slugs are missing.
- Reused the existing member settings component so profile, notification, and subscription management behavior stays consistent.

## 1.27.49 — 2026-11-03

- Removed the login requirement from public plan selection and native checkout.
- Added guest checkout name, email, and phone fields; guest checkout creates or reuses a WordPress subscriber account before provider checkout.

## 1.27.48 — 2026-11-03

- Added plan-level approval mode: Direct approval (default) or Admin review.
- Added the `pending_review` subscription state, admin Approve/Reject actions, duplicate-checkout protection, and provider/webhook guards that preserve review state until an administrator decides.
- Delayed the active-subscription notification until an administrator approves a reviewed subscription.
- Review requests now notify the admin, appear under Pending approvals, collect no payment before approval, and send the member an approval/payment link followed by an invoice after activation.
- Added the `approval_mode` plan-column migration with existing plans defaulting to Direct approval.
- Bumped the database schema to 1.8.3 for the pending-payment approval state and approval checkout metadata.

## 1.27.47 — 2026-11-03

- Replaced the checkout success page’s “View plans” action with a branded “Log in to your account” button.

## 1.27.46 — 2026-11-03

- Added a standalone, branded checkout-success page template with a theme-safe fallback when `header.php` or `footer.php` is unavailable.
- Routed Stripe and Development checkout returns, plus tagged Daymark WooCommerce orders, to the success template.

## 1.27.45 — 2026-11-03

- Added a shared loading state for frontend API actions and admin form submissions so buttons cannot be double-submitted while work is in progress.
- Added accessible `aria-busy` state, disabled controls, spinner feedback, error recovery, and reduced-motion compatibility.

## 1.27.34 — 2026-11-02

- Hardened native menu-item access saving so missing or invalid menu nonces cannot persist rules.

## 1.27.33 — 2026-11-02

- Moved menu item access rules into WordPress Appearance → Menus native menu-item settings.
- Preserved existing menu-rule storage and frontend filtering while removing the duplicate Access & Entitlements table.
- Added strict capability and native menu-save nonce validation for menu access updates.

## 1.27.32 — 2026-11-02

- Fixed checkbox card layout so generic form width rules no longer stretch the checkbox input across the plan card.

## 1.27.31 — 2026-11-02

- Replaced the Assigned plans multi-select with branded, keyboard-friendly checkbox cards.

## 1.27.30 — 2026-11-02

- Moved Access revoked presentation styles into `assets/access-revoked.css` and enqueue them from the template renderer.

## 1.27.29 — 2026-11-02

- Fixed Access revoked rendering for block themes without classic `header.php` or `footer.php` templates.

## 1.27.28 — 2026-11-02

- Replaced the generic access-denied error with a branded Access revoked frontend template for authenticated users.
- Preserved login redirect behavior for visitors who are not signed in.

## 1.27.27 — 2026-11-02

- Moved page access configuration from the Access & Entitlements settings table to a Subscription access sidebar meta box on pages, posts, and UI-enabled custom post types.
- Extended enforcement from pages to singular posts and custom post types while preserving the existing stored rule option.
- Kept menu item access rules in the Access & Entitlements screen.

## 1.27.26 — 2026-11-02

- Fixed feature resolution so plan-scoped entitlements are not returned to members on other plans.

## 1.27.25 — 2026-11-02

- Added required plan selection when creating a feature.
- Stored assigned plan IDs with each feature and enforced assignment during member feature resolution.
- Added an Assigned plans column to the feature value matrix.
- Prevented assigned-only features from leaking into members on other plans.

## 1.27.24 — 2026-11-02

- Allowed currency settings to save independently when the payment provider is configuration-locked.

## 1.27.23 — 2026-11-02

- Kept Checkout defaults visible inline in Settings instead of sending the currency/provider form through the generic modal initializer.
- Currency can still be saved when the payment provider is locked by a configuration constant.

## 1.27.22 — 2026-11-02

- Moved plan currency selection to Settings → Checkout defaults.
- New plan prices now use the configured currency; existing plan currencies remain unchanged.

## 1.27.21 — 2026-11-02

- Fixed Create Plan drawer form overflow by switching its fields to a single-column full-width layout.
- Made the drawer submit action full width and kept the form body vertically scrollable without horizontal clipping.

## 1.27.20 — 2026-11-02

- Set the Create Plan side drawer background to solid white for clearer separation and improved readability.

## 1.27.19 — 2026-11-02

- Moved Create Plan into a right-side drawer opened by the branded Create a plan button.
- Added drawer close, backdrop, Escape, focus return, focus trap, and narrow-screen full-width behavior.

## 1.27.18 — 2026-11-02

- Reworked Plans & Pricing into a branded catalog workspace with provider context, catalog stats, detailed plan rows, and a focused empty state.
- Kept the detailed create-plan form inline so its fields are immediately visible instead of hidden behind the generic modal initializer.

## 1.27.17 — 2026-11-02

- Added Stripe Sandbox and Live modes in API Integration, with mode-specific credentials and webhook secrets.
- Added mode-specific Stripe Price mapping so test and live checkouts never reuse each other’s prices.
- Kept legacy Stripe constants and the existing `stripe_price_id` column compatible for existing Sandbox installs.

## 2026-11-02 — 11:44 PKT

- Summary: Refined the admin dashboard into a professional, minimal operator workspace with flatter surfaces, clearer hierarchy, quieter color use, and responsive metric/table layouts.
- Files touched: `assets/admin-dashboard.css`
- Behavior: No PHP, data, form submission, modal, or admin navigation behavior changed.
- Version: Bumped plugin asset version to `1.27.7` so browsers load the updated stylesheet.
- Branding: Added the Daymark admin lockup treatment and documented the design system in `docs/branding.md`.
- Consistency: Unified the nested Feature Builder panel and modal primary button color through scoped CSS.
- Branding: Promoted the lockup from generic admin labeling to `DAYMARK / SUBSCRIPTIONS` and refined the open-ring mark.
- Design system: Added explicit typography, form, button, panel, table, spacing, responsive, and state tokens/rules to the stylesheet and brand guide.
## 2026-11-02

- Added a Design admin page with validated custom accent color, curated presets, live preview, and capability/nonce-protected saving.
- Applied the saved accent consistently to admin actions, focus states, brand mark, frontend subscription actions, and member settings.
- Aligned frontend form/card/button treatment with the Daymark branding system: system UI font, 40px controls, 4px controls/buttons, 6px panels, and restrained flat surfaces.
- Bumped plugin asset version to `1.27.8` so the new Design page behavior is not masked by cached admin JavaScript.
- Removed manual Stripe Price ID and WooCommerce product ID fields from plan creation. Stripe prices now auto-create on first Stripe checkout; WooCommerce creates and links a hidden virtual ghost product on first Woo checkout. Existing mappings remain supported.
- Bumped plugin version to `1.27.9`.
- Replaced plugin admin description textareas with an inline WYSIWYG editor and preserved safe formatting through `wp_kses_post()`.
- Bumped plugin version to `1.27.10`.
- Fixed editor initialization timing so WYSIWYG controls remain active after admin forms move into modals.
- Bumped plugin version to `1.27.11`.
- Explicitly enqueued WordPress editor and Quicktags runtime handles for admin WYSIWYG fields.
- Bumped plugin version to `1.27.12`.
- Replaced unreliable runtime editor initialization with a compact native contenteditable WYSIWYG control that syncs back to the sanitized textarea.
- Bumped plugin version to `1.27.13`.
- Fixed WYSIWYG enhancement for description textareas that did not have an HTML ID.
- Bumped plugin version to `1.27.14`.
- Added an API Integration admin page with selected-provider status and masked Stripe credential fields; WooCommerce shows core integration readiness without requiring API keys.
- Stripe credentials may be stored as options when constants are not defined; `wp-config.php` constants remain authoritative.
- Bumped plugin version to `1.27.15`.
- Kept the API credential form inline instead of wrapping it in the generic modal behavior.
- Bumped plugin version to `1.27.16`.
## 1.27.35 — 2026-11-02

- Bumped the database schema to 1.8.1 so existing installs add indexes for Stripe price and provider subscription lookups through the existing `dbDelta` upgrade path.
- Added cleanup for branding, currency, Stripe mode/credential, price-map, and database-error options during opt-in uninstall.

## 1.27.36 — 2026-11-02

- Fixed frontend checkout API requests using `apiBase` from the shortcode configuration; the SDK now accepts both `apiBase` and legacy `baseUrl` keys.

## 1.27.37 — 2026-11-02

- Added the native branded checkout page shortcode `[daymark_checkout]` with selected-plan summary, billing details, provider context, and secure payment action.
- Plan cards now open the native checkout page before starting provider checkout.

## 1.27.38 — 2026-11-02

- Refined Plans and Checkout frontend surfaces with the Daymark accent rule, wider content frame, stronger price hierarchy, consistent card treatments, improved action sizing, and responsive spacing.

## 1.27.39 — 2026-11-02

- Applied the saved admin brand color variables to the native Checkout wrapper so its accent rule, buttons, focus state, and soft surfaces match Plans.

## 1.27.40 — 2026-11-02

- Included native Checkout in the shared frontend design-token scope so ink, muted text, border, soft surface, and focus treatments remain consistent with Plans.

## 1.27.41 — 2026-11-02

- Improved the Plans page for visitors and members with a direct login link, assigned feature summaries, current-plan state, renewal date, upgrade/downgrade labels, and ended-subscription recovery messaging.

## 1.27.42 — 2026-11-02

- Moved pause, resume, and cancellation controls from the Plans page into the Member Settings subscription management card, keeping plan selection focused on catalog and plan changes.

## 1.27.43 — 2026-11-02

- Reset Member Settings subscription action button state after refresh so successful pause, resume, and cancellation operations remain usable immediately.

## 1.27.44 — 2026-11-03

- Added a server-enforced Development/Production execution mode under Subscriptions → Settings.
- Development mode bypasses external payment requests and creates an idempotent local test subscription; Production remains the default and preserves the existing Stripe/WooCommerce flow.
- Development subscriptions keep local pause, resume, cancellation, and plan-change actions provider-free.
- Added an optional `DAYMARK_SUBSCRIPTIONS_ENVIRONMENT` constant to lock the mode and removed the setting during opt-in uninstall.

## 2026-11-04 — Admin dynamic catalog localization

- Added localized admin runtime labels for plan editing, subscriber drawers, and request/loading states.
- Kept the source missing-options audit unchanged.

## 2026-11-04 — Webhook verification health

- Added a persisted `verified_at` event timestamp and exposed the last verified webhook time in provider health.
- Bumped the database schema to `1.10.1`; existing installations migrate through the normal `dbDelta` activation path.

## 2026-11-04 — Introductory plan pricing

- Added validated introductory price and cycle fields to plans.
- Stripe applies the offer through a repeating coupon for monthly/yearly plans; WooCommerce returns an explicit unsupported response.
- Bumped the database schema to `1.11.0` and removed the mapping on plan pricing changes.

## 2026-11-04 — Admin runtime translation coverage

- Extended the localized admin script catalog across bulk subscription actions, billing errors, provider health, modal controls, and member detail states.

## 2026-11-04 — Frontend runtime translation coverage

- Expanded localized strings for Subscription Management and plans/checkout runtime configuration, including lifecycle states and payment actions.

## 2026-11-04 — Plans runtime translation coverage

- Connected plan-change, checkout, pending, and subscription-state messages to the localized frontend string catalog.

## 1.29.21 — 2026-11-06

- Protected WordPress administrators from account anonymization/deletion while keeping their deletion requests visible for review.
- Added a server-side SDK helper and REST error contract for the protected administrator flow.

# 2026-11-09 — Full security and code audit

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — current audit session
- Summary: Added a source-based security/code audit. Recorded two open payment/account-boundary risks, one dependency/CI governance gap, one bulk-approval policy defect, entry-point coverage, verification limits and a prioritized remediation roadmap. No production code was modified.
- Files touched: `docs/audit-report-2026-11-09.md`, `docs/changelog.md`

# 2026-11-09 — Security findings remediation

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — remediation session
- Summary: Scoped checkout idempotency to the owner, serialized checkout reservations per user, added request-mismatch protection, blocked unsupported bulk revokes under second-admin policy, and updated lifecycle coverage/docs.
- Files touched: `includes/class-daymark-subscriptions-rest.php`, `tests/test-contracts.php`, `tests/test-wp-lifecycle.php`, `docs/audit-report-2026-11-09.md`, `docs/changelog.md`

# 2026-11-09 — SDK and REST contract alignment

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — contract update session
- Summary: Bumped the plugin patch version to 1.31.1, published SDK contract 1.3.1 metadata for owner-scoped checkout idempotency and bulk second-admin policy, and synchronized REST/SDK documentation.
- Files touched: `daymark-subscriptions.php`, `includes/class-daymark-subscriptions-sdk.php`, `tests/test-contracts.php`, `docs/developer-documentation.md`, `docs/sdk-reference.md`, `readme.txt`, `docs/changelog.md`

# 2026-11-09 — Frontend template design system

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — UI redesign session
- Summary: Applied a consistent professional/minimal visual system across frontend template pages with editorial hierarchy, refined plan and checkout compositions, branded status states, responsive layouts, and accessibility-focused focus/reduced-motion behavior.
- Files touched: `assets/frontend.css`, `assets/checkout-success.css`, `assets/access-revoked.css`, `docs/audit-report.md`, `docs/changelog.md`

# 2026-11-09 — Member profile refinement

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — profile UI session
- Summary: Refined the member profile and public author template into an identity-led layout with stronger avatar treatment, handle/status metadata, member-since context, readable About content, profile actions, and mobile reflow. Bumped the patch version to refresh frontend assets.
- Files touched: `templates/author.php`, `includes/class-daymark-subscriptions-shortcode.php`, `assets/frontend.css`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`

# 2026-11-09 — Subscription management action labels

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — subscription management UI session
- Summary: Renamed the management renewal action to “Upgrade plan”, removed the comparison-link insertion, kept Retry payment hidden until `past_due`, and bumped the patch version for asset refresh.
- Files touched: `includes/class-daymark-subscriptions-shortcode.php`, `assets/subscription-management.js`, `tests/test-contracts.php`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`

# 2026-11-09 — Subscription action visibility fix

- Day: Monday
- Date: 2026-11-09
- Time: Asia/Karachi — UI verification follow-up
- Summary: Added an explicit hidden-state display rule so Upgrade plan and Retry payment cannot remain visually exposed while their state is inactive; bumped the patch version for CSS cache refresh.
- Files touched: `assets/frontend.css`, `daymark-subscriptions.php`, `readme.txt`, `docs/changelog.md`

