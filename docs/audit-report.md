# Current verification — 2026-11-09

## 2026-11-09 — Centralized asset loading

- PASS: Plugin CSS and JavaScript file enqueue calls are centralized in the main plugin bootstrap; shortcode/template/access classes no longer enqueue asset files directly.
- PASS: Existing page-specific loading, code-editor dependency, localization, inline branding, and cache-busting version behavior remain preserved.
- PENDING: Browser verification across every shortcode route and theme combination remains staging coverage.

## 2026-11-09 — Audit log pagination

- PASS: The administrator audit-log query now uses a bounded page/limit query with ten records per page and a separate total count.
- PASS: Page input is sanitized and clamped before rendering native WordPress pagination links; no audit records are deleted or exposed by pagination.
- PENDING: Live multi-page admin browser verification and high-volume database performance profiling remain staging checks.

## 2026-11-09 — Product architecture alignment

- PASS: `docs/product-architecture-recommendations.md` remained unchanged and was treated as the source of truth.
- PASS: Database version `1.16.0` adds durable checkout records without rewriting financial history; legacy `pending_payment` reads remain compatible while new writes use `payment_pending`.
- PASS: Verified Stripe/WooCommerce payments now create `pending_activation` subscriptions; access becomes active only after the password claim completes.
- PASS: Checkout idempotency records survive provider retries and preserve payment-pending, review, and verified outcomes.
- PASS: Pending activation retention expires unclaimed access without deleting subscription, invoice, or audit records.
- PASS: Plan refund-policy copy is stored separately from the description and is available through the server-side plan contract without changing historical subscription snapshots.
- PASS: Invoice, payment-recovery, and expiry transitions now have configurable notification events and supported template placeholders.
- PASS: PHP lint, contract, integration, lifecycle, webhook, and whitespace checks were rerun after this alignment.
- PENDING: Real Stripe/WooCommerce provider callbacks, SMTP delivery, server cron, and live browser viewport checks remain staging/deployment verification.

## Architecture coverage boundaries

- PASS: General settings now store a validated business/site name and customer support email, with WordPress-native defaults and uninstall cleanup.

- COMPLETE: Provider-neutral checkout, canonical subscription states, verified-payment activation, guest/review journeys, provider readiness gates, WooCommerce adapter boundary, plan snapshots, plan-change preview, recovery states, rate limiting, access control, invoice ownership, SDK boundaries, and notification event contracts.
- PARTIAL: The current Settings UI still uses WordPress-native site name, administrator email, timezone, date format, and page defaults instead of duplicating those values as Daymark-owned settings.
- PARTIAL: Operation/job retry backoff and dead-letter handling exist, but retry count and schedule are not yet administrator-configurable.
- PARTIAL: Email failures are recorded in the audit log; a dedicated delivery-log/resend queue UI is not yet available.
- PARTIAL: Account deletion is admin-reviewed and billing history is preserved, but a separate configurable deletion-retention queue is not yet implemented.
- FUTURE: MFA/passkey support remains intentionally deferred because the source architecture marks it as a future phase.

## 2026-11-09 — Secure-code ownership hardening

- PASS: Guest checkout rejects every existing WordPress account before changing billing metadata or account state; subscription presence is no longer the only guard.
- PASS: WooCommerce checkout tokens are accepted for Daymark order tagging only when the order billing email matches the target account, with the logged-in user check preserved.
- PASS: The admin WYSIWYG editor removes executable elements, event attributes, inline styles, and unsafe URL schemes before rendering or submitting HTML.
- PASS: Bulk admin requests propagate the selected subscription ID into each child operation.
- PASS: SDK review and billing operations enforce their dedicated capabilities; cross-member billing URLs require billing administration scope.
- PASS: Immediate Stripe cancellation forwards the caller's idempotency key to the provider request.
- PASS: Contract smoke coverage protects ownership, bulk-action, capability, and idempotency boundaries.
- PASS: PHP lint, contract, WordPress integration, lifecycle, webhook, and whitespace checks were rerun after the changes.
- PENDING: Live WooCommerce gateway callback, Stripe provider delivery, SMTP delivery, and browser multi-user abuse tests remain staging checks.

## 2026-11-07 — Server SDK 1.3.0 add-on contract

- PASS: Additive SDK availability, capability, contract metadata, member timeline, and authorization helpers are implemented.
- PASS: Lifecycle SDK calls can preserve a caller-supplied idempotency key without exposing provider credentials or raw payment data.
- PASS: Add-on bootstrap and documented lifecycle/payment/reconciliation hooks are connected after local persistence boundaries.
- PASS: Catalog writes and browser SDK remain outside the add-on contract.
- PENDING: External third-party add-on compatibility and real provider event delivery remain deployment/staging checks.

## 2026-11-07 — Plan field visual consistency

- PASS: Create Plan and Edit Plan controls share the same radius, border, height, padding, background, typography, and focus treatment.

## 2026-11-07 — Plan form control parity

- PASS: Checkbox labels and helper text use the same spacing/layout treatment in both plan forms.

## 2026-11-07 — Plan form width parity

- PASS: Create Plan and Edit Plan use the same full-width form contract.
- PASS: Both forms use two equal columns on desktop and one column below 700px.
- PASS: Long text fields, actions, and checkbox rows retain the shared full-width/rhythm rules.

## 2026-11-07 — Frontend and admin UI/UX refinement

- PASS: Added a final shared token pass for the Quiet Operations Console direction without changing payment, REST, nonce, capability, or SDK behavior.
- PASS: Admin controls now use consistent compact sizing, visible focus, bounded checkbox rows, white drawer/modal surfaces, responsive settings tabs, and an overflow-safe subscription table action grid.
- PASS: Frontend plans, checkout, profile, member settings, privacy, subscription management, invoices, buttons, and status surfaces now share flat panels, controlled radii, semantic focus, and responsive action behavior.
- PASS: Large-screen plan/member actions are compact; full-width actions are limited to checkout and mobile contexts.
- PASS: Reduced-motion fallbacks cover the shared frontend and admin surfaces.
- PENDING: Full live Chrome desktop/tablet/mobile route verification remains required after the asset version refresh; source changes were kept CSS-only apart from the version/documentation records.

## 2026-11-07 — Create/Edit Plan form parity

- PASS: Create Plan and Edit Plan now receive the same `daymark-plan-form` grid, label rhythm, checkbox treatment, long-text span, action alignment, and mobile breakpoint rules.
- PASS: Edit Plan no longer exposes the settings-owned currency field; the existing selected currency remains preserved in the update payload.
- PENDING: Live interaction with both drawers remains a browser verification item; no plan data was created, edited, archived, or deleted during this pass.
- PASS: Edit Plan currency is now removed from the visible/accessibility tree and retained as a hidden submitted value, so Create/Edit parity does not expose a settings-owned control.

## 2026-11-07 — Plan drawer width parity correction

- PASS: The Create Plan drawer now overrides the generic drawer single-column rule and uses the same full-width two-column desktop grid as Edit Plan.
- PASS: Both plan forms retain single-column responsive stacking below 700px.

## Production launch implementation update

The current launch-blocker implementation is documented in `docs/production-readiness-audit-2026-11-07.md`. This pass adds verified guest-account activation state, fail-closed WooCommerce production capability checks, the admin readiness REST contract, server SDK provider capability helpers, and regression coverage. Local lint, contract, WordPress integration, lifecycle, and signed-webhook checks pass. Live provider transactions, SMTP delivery, browser verification, and deployed system cron remain external checks and are not represented as passing here.

# Current verification — 2026-11-05

The dated source-of-truth review is tracked in `docs/audit-report-2026-11-05.md` and remains the authoritative current implementation audit. Since the historical entries below, the plugin now includes provider-operation reconciliation, scheduled-job retry/dead-letter/acknowledgement controls, guest lifecycle recovery, plan-change previews, billing timelines, provider telemetry, explicit retention rules, configurable trial/intro expiry reminders, a local lifecycle integration test, and a signed local webhook integration test. The source-of-truth findings file was not modified.

Current local evidence: every PHP file passes PHP 8.3.28 lint; contract, read-only WordPress integration, lifecycle, and signed-webhook tests pass; live WordPress DB migration is `1.15.1`; Chrome renders the admin Overview/Settings/Transactional/Plans pages and the public Plans/Account Access pages. Existing subscription pricing is snapshotted and backfilled during migration. Plan setup fees, promotion-code opt-in, quantity bounds, minimum commitment, and tax behavior are now represented through the validated REST/admin/provider paths. Real Stripe/WooCommerce provider transactions, SMTP delivery, and production cron remain external verification boundaries. Optional second-admin approval remains policy-gated.

# 2026-11-04 — Create-plan checkbox audit

- FIXED: The Featured plan checkbox now uses an inline flex layout, compact checkbox width, and aligned helper text within the Create a plan grid.
- VERIFIED: The selector is scoped to the create/form grid checkbox pattern; other checkbox controls and submit behavior are unchanged.
- PENDING: Live Chrome visual verification after the local asset cache refresh.

# 2026-11-04 — Create-plan slug audit

- FIXED: Create a plan no longer asks admins for a manual slug; the UI removes the field and the server derives the slug from the plan name.
- FIXED: Duplicate generated slugs receive a bounded numeric suffix instead of failing the creation flow.
- VERIFIED: Existing plan edit slug support remains unchanged; contract smoke tests and PHP lint pass.
- PENDING: Live Chrome confirmation of the updated create drawer after asset cache refresh.

# 2026-11-04 — Built-in notification examples audit

- FIXED: Every transactional notification now exposes a built-in subject and HTML example with the relevant dynamic placeholders.
- FIXED: Examples are read-only reference content; blank custom fields still preserve the existing built-in message fallback.
- VERIFIED: Template storage and HTML delivery contracts remain unchanged.
- PENDING: Live Chrome confirmation of the details/code layout.

# 2026-11-04 — HTML editor correction audit

- FIXED: Transactional HTML bodies now use WordPress CodeMirror HTML editing instead of the generic plain textarea/custom WYSIWYG path.
- FIXED: Code editor assets load only on the dedicated Transactional emails page and the HTML textarea is excluded from the generic editor initializer.
- VERIFIED: HTML source saving, placeholder replacement, sanitization, and HTML mail delivery contracts remain intact.
- PENDING: Live Chrome confirmation that the CodeMirror assets load under the current local WordPress admin cache.

# 2026-11-04 — Transactional email template audit

- FIXED: Transactional copy is now managed on its own admin page instead of inside the Settings notification tab.
- FIXED: Email bodies use an HTML code editor with a supported placeholder reference; the old WYSIWYG/text copy surface is removed.
- FIXED: Saved custom HTML is sanitized and sent with a scoped HTML content-type header; default messages remain plain text.
- VERIFIED: Existing notification nonce, event storage, subject storage, and placeholder replacement flow remain in place.
- PENDING: Live browser delivery test through a real SMTP/provider inbox.

# 2026-11-04 — Form spacing audit

- FIXED: Admin form labels, helper text, fields, settings forms, plan editor fields, and frontend checkout fields now use a consistent label-to-control rhythm.
- FIXED: Removed overlapping margin rules from form-grid controls and WordPress submit paragraphs.
- VERIFIED: CSS selectors are scoped to existing Daymark form classes; no payment, REST, or submission behavior changed.
- PENDING: Live Chrome visual verification after the local page reload.

# 2026-11-04 — Settings tab system

- PASS: Settings now groups provider/environment controls under General, lifecycle controls under Subscription, and mail controls under Notifications.
- PASS: Tabs use `role=tablist`, `role=tab`, `role=tabpanel`, `aria-selected`, keyboard arrows/Home/End, and URL hash deep-linking.
- PASS: Existing forms remain unchanged and retain their action/nonces.
- VERIFIED: PHP lint and contract smoke tests pass.
- PENDING: Browser visual verification after the local PHP opcode cache refresh.

# 2026-11-04 — Admin class PHP boundary audit

- FOUND: `subscriptions_page()` closed PHP after pagination and did not reopen it before `render_duplicate_notice()`. Token inspection confirmed the method was parsed as inline HTML, explaining the literal source output and undefined `$duplicates` warning.
- FIXED: Reopened PHP mode before the method declaration; plugin metadata updated to `1.28.61`.
- VERIFIED: PHP lint, token-level method discovery, and contract smoke tests pass.

# 2026-11-04 — Live admin audit: duplicate notice initialization

- FIXED: Duplicate notice rendering now initializes its local rows and accepts only array database results before counting or iterating.
- VERSION: Plugin metadata updated to `1.28.60`.
- PENDING: Live browser confirmation still depends on the local PHP opcode cache loading the latest source.

# 2026-11-04 — Live admin audit: duplicate notice caller guard

- FOUND: The live PHP worker continued returning `null` from the duplicate lookup after the database-boundary normalization was written, consistent with a stale class cache.
- FIXED: The admin caller now casts the lookup result to an array as an additional safe boundary; plugin metadata updated to `1.28.59`.
- PENDING: A final browser retest requires the local PHP opcode cache to load this latest source revision.

# 2026-11-04 — Live admin audit: duplicate lookup result normalization

- FOUND: The live Subscriptions page reached the duplicate-subscription notice but fatally called `count()` on a `null` database result.
- FIXED: `get_duplicate_subscription_users()` now always returns an array; plugin metadata updated to `1.28.58`.
- PENDING: The browser still needs a PHP opcode-cache refresh before this source change can be retested live.

# 2026-11-04 — Live admin audit: subscriptions page fatal guard

- FOUND: The live Subscriptions page returned a fatal error while invoking the duplicate-subscription review notice.
- FIXED: The call is now guarded with `method_exists()` so the page remains available during stale class-cache transitions; plugin metadata updated to `1.28.57`.
- VERIFIED: Plans & Pricing loaded, create/edit drawers opened, and the subscriber drawer returned the current subscriber controls before the Subscriptions page test.
- PENDING: Provider transactions and mutating admin actions were not executed during this browser audit.

# 2026-11-04 — Status palette tokens

- PASS: Success, warning, and danger colors use the existing validated design-token flow.
- PASS: The same palette is injected into admin and frontend surfaces and used by status badges/actions.
- PASS: Contract smoke coverage and version metadata updated to `1.28.38`.
- PENDING: Logo upload/placement remains a separate template-level audit item.

## 2026-11-04 — Lifecycle policy defaults

- PASS: Settings now persist validated default approval, cancellation timing, and pause-duration values.
- PASS: New REST-created plans use the configured approval default when no plan-specific mode is supplied.
- PASS: Member cancellation and pause actions apply the configured defaults while preserving explicit request overrides.
- PASS: A bounded renewal worker query now detects configured pause end dates and resumes local access only after provider-aware resume succeeds; unsupported WooCommerce provider behavior remains explicit.
- PENDING: Renewal-reminder scheduling remains unimplemented and requires a separate notification timing contract.

## 2026-11-04 — Notification event controls

- PASS: Admin can enable or disable each existing lifecycle mail event through validated option storage.
- PASS: A validated Reply-To address is applied only to plugin mail and remains optional.
- PASS: Existing sender/admin-recipient controls remain compatible and uninstall cleanup covers the new options.
- PENDING: Editable subject/body templates and delivery-status history remain separate work.

## 2026-11-04 — Member plan comparison

- PASS: Subscription Management now exposes a configured Plans page link for comparing available offers.
- PASS: The link is added once from localized server configuration and does not alter subscription state.

## 2026-11-04 — Attachment-backed brand logo

- PASS: Design settings now accept a WordPress media upload, store only the attachment ID, and support removal.
- PASS: Frontend surfaces consume the saved logo through the shared branding token; no arbitrary remote URL is accepted.
- PASS: Uninstall cleanup and contract coverage were updated.
- PENDING: Actual media upload and visual browser verification remain unexecuted.

## 2026-11-04 — Default approval form alignment

- PASS: The configured approval default now preselects in the admin Create Plan drawer and is also applied when REST input omits the field.

## 2026-11-04 — Renewal reminder policy

- PASS: Admin can configure a validated 0–30 day reminder window.
- PASS: The hourly renewal worker claims each eligible subscription once per period, sends the existing lifecycle email, and resets eligibility after successful renewal.
- PASS: Database migration `1.10.0` adds the idempotency timestamp with uninstall coverage.
- PENDING: Actual cron execution and email delivery remain runtime verification items.

## 2026-11-04 — Notification templates

- PASS: Admin can edit subject/body copy for every supported lifecycle event using constrained plain-text fields.
- PASS: Blank templates preserve built-in copy; supported placeholders are substituted server-side before sending.
- PASS: Template option cleanup and contract coverage are included.
- PENDING: Actual email rendering/delivery was not executed.

## 2026-11-04 — Notification input hardening

- PASS: Nested event/template settings now require arrays before sanitization, preventing malformed admin input warnings and unintended writes.

## 2026-11-04 — Runtime UI localization slice

- PASS: Subscription Management and Plans runtime labels now come from WordPress-localized script data with safe fallbacks.
- PASS: Locale/timezone configuration remains server-provided for date formatting.
- PENDING: Admin runtime strings and a complete translation catalog remain separate localization work.

## 2026-11-04 — Renewal reminder control alignment

- PASS: Renewal reminder now appears in the event-toggle UI and uses the same persisted event policy as the mailer and template editor.

## 2026-11-04 — Admin runtime localization slice

- PASS: Admin dynamic UI now receives localized labels through `DaymarkAdminConfig.strings`, with a fallback for the Create Plan trigger.
- PENDING: Remaining admin JavaScript literals still require a broader translation catalog pass.

# Admin UI audit — 2026-11-02

## 2026-11-04 — Provider connection health

- PASS: API Integration now exposes a protected “Test connection” action through the existing REST/nonce contract.
- PASS: Stripe health checks the selected mode’s /v1/account response; WooCommerce health checks runtime availability without printing credentials.
- PENDING: No live provider request was executed in this environment.

## 2026-11-04 — Member contact settings

- PASS: Authenticated member settings now expose a validated phone field and save it to the existing billing/contact metadata used by checkout.
- PASS: Profile-picture upload remains an automatic file/crop flow and is not coupled to the phone save action.
- RESOLVED: Verified email-change flow now keeps the current address until a hashed, expiring confirmation link for the new address is used.
- RESOLVED: Email changes now require a new-address confirmation link with a hashed single-use token, 24-hour expiry, conflict check, and current-email continuity until confirmation.

## 2026-11-04 — Plan catalog presentation metadata

- PASS: Plans now support ordered catalog display, a featured/recommended state, and up to eight sanitized customer-facing benefits.
- PASS: The metadata is separate from provider price/product identifiers and does not recreate entitlement enforcement.
- PENDING: Introductory pricing, coupons, tax behavior per plan, and quantity/seat billing remain separate pricing-policy decisions; trial days are resolved below.

## 2026-11-04 — Payment fixture assertion coverage

- PASS: The dependency-free contract smoke test now parses and validates the checked-in Stripe dispute and refund fixture shapes in addition to invoice success/failure and Woo renewal fixtures.
- PENDING: This remains contract coverage only; provider signature verification, database transactions, and live payment events still require integration execution.

## 2026-11-04 — Public plan projection hardening

- PASS: Public plan listings no longer expose provider-owned Stripe Price or WooCommerce product IDs.
- PASS: Internal single-plan provider lookup retains mapping fields for checkout and reconciliation operations.
- PASS: Live `GET /wp-json/daymark/v1/plans` was rechecked before the fix and exposed the issue; static and live verification must be rerun after asset/runtime refresh.

## 2026-11-04 — Retired Access & Entitlements removal completed

- PASS: Removed the remaining unused admin handlers/renderers, feature REST methods, and feature-specific admin JavaScript.
- PASS: Existing legacy option data and the isolated legacy class file remain untouched for non-destructive compatibility, but no active plugin code loads the feature builder or entitlement API.
- PASS: Page editor and native menu plan/status rules remain separate supported functionality.

## 2026-11-04 — Duplicate subscription repair flow

- PASS: Admin Subscriptions detects users with multiple current subscription records and links each to a filtered review view.
- PASS: Repair remains deliberate: administrators use existing row selection/bulk actions; no subscription or invoice history is deleted automatically.
- PENDING: A live database containing duplicate records was not available for browser execution of the review flow.

## 2026-11-04 — Ended subscription recovery

- PASS: Canceled and expired members now receive a visible `Choose a plan` recovery CTA in Subscription Management, reusing the existing checkout flow.
- PASS: Active, paused, pending, and past-due members do not receive the ended-subscription CTA.
- PENDING: A real canceled/expired account click-through was not executed in the live site.

## 2026-11-04 — Woo renewal adapter safety contract

- PASS: Woo renewal processing now has an explicit adapter filter for gateways that support off-session saved-token charges.
- PASS: The local subscription is settled only when the adapter returns `true` and WooCommerce reports the renewal order as paid; otherwise the payment-link fallback remains active.
- PENDING: No gateway-specific adapter was installed or executed; the supplied contract must be implemented by the selected WooCommerce gateway.

## 2026-11-04 — Provider fixture coverage

- PASS: Checked-in Stripe payment-success, payment-failure, dispute, refund, and WooCommerce renewal-order shape fixtures are now parsed by the dependency-free contract test.
- PASS: Test documentation states exactly what fixture validation proves and does not prove.
- PENDING: Provider signature verification, live gateway charges, DB transactions, and browser checkout remain integration boundaries.

## 2026-11-04 — Scheduled job monitoring

- PASS: Audit Log now exposes next-run status for renewal processing and Stripe reconciliation jobs.
- PASS: The UI explicitly identifies local WP-Cron and recommends persistent cron for low-traffic sites.
- PENDING: Actual cron execution and provider reconciliation remain runtime verification items.

## 2026-11-04 — Live Chrome verification

- PASS: Plans & Pricing rendered the then-current `1.28.1` assets during the recorded live check; the plan edit drawer opened and populated its fields. Later source updates are verified by the local contract/lint pass below.
- PASS: Subscriptions rendered the bulk toolbar, select-all checkbox, row checkbox, and action controls after the current script settled.
- PASS: No plugin-originated console errors were observed in the inspected admin tab; reported errors came from an unrelated browser extension.
- PENDING: No destructive bulk action or payment transaction was submitted during the audit.

## 2026-11-04 — Bulk response contract correction

- PASS: Bulk UI now reads the REST endpoint’s `updated` count and associative `failed` map, preserving partial-failure feedback instead of treating all responses as successful.
- PASS: The correction was found during live DOM/asset verification; no mutating bulk action was submitted.

## 2026-11-04 — Invoice email link

- PASS: Activation invoice mail now includes the stored provider/WooCommerce invoice URL where available and keeps a local/dev summary fallback.
- PENDING: A real provider-generated invoice document and email delivery were not executed in this environment.

## 2026-11-04 — Retired entitlement runtime cleanup

- PASS: Removed the remaining live `data-daymark-feature` content check; the retired feature builder is no longer part of runtime access evaluation.
- PASS: Explicit plan/status content rules remain available through the requested editor/menu integrations.

## 2026-11-04 — Provider mapping input contract

- PASS: Plan create/update REST input no longer accepts Stripe Price IDs or WooCommerce product IDs; Daymark owns provider mapping creation and invalidation.
- PASS: Existing internal mapping columns remain available for runtime provider reconciliation without exposing them as caller-controlled plan fields.

## 2026-11-04 — Bulk subscriber operations

- PASS: Admin subscription tables now expose accessible row selection, select-all, action, target-plan, confirmation, loading, and partial-failure feedback controls.
- PASS: UI posts through the existing nonce-protected bulk REST endpoint; SDK and endpoint contracts remain unchanged.
- PENDING: Browser execution and real WordPress REST verification remain outside this static workspace check.

## 2026-11-04 — Bulk subscriber operations

- PASS: Protected admin REST and SDK contracts support bulk revoke and plan changes for up to 50 subscriptions.
- PASS: Each item is processed through the existing single-subscription lifecycle/provider path; partial failures are returned without hiding successful updates.
- PASS: The batch is written to the administrator audit log.
- RESOLVED: The full Subscriptions admin table now exposes multi-select controls; browser execution remains a verification boundary.

## 2026-11-04 — Contract smoke coverage

- PASS: Added `tests/test-contracts.php` covering DB migration, webhook retention/replay, tax configuration, Woo renewal order flow, billing portal route, guest identity guard, and admin replay action.
- PASS: Contract smoke tests passed locally; PHP lint passed for all 15 PHP files.
- PENDING: These are static contract tests, not WordPress integration, provider sandbox, or browser tests.

## 2026-11-04 — Tax and currency guardrails

- PASS: Stripe automatic tax is an explicit admin setting and is sent only for new Stripe Checkout sessions.
- PASS: WooCommerce checkout rejects plans whose currency differs from the configured store currency instead of silently charging a mismatched amount.
- PASS: Tax setting is removed by opt-in uninstall.
- PENDING: Tax registration, VAT IDs, exemptions, and live provider tax calculation require account-level configuration and were not tested here.

## 2026-11-04 — Webhook operations and replay

- PASS: Stripe webhook payloads are persisted with event type, timestamps, and processing status.
- PASS: Failed webhook processing retains the event instead of deleting it.
- PASS: Admins can inspect webhook status and replay non-processed events through the same idempotent handler pipeline.
- PASS: DB migration version is bumped to add the payload column; uninstall already drops the events table.
- PENDING: Live Stripe webhook replay was not exercised against a provider account.

## 2026-11-04 — WooCommerce renewal order flow

- PASS: A due WooCommerce renewal creates at most one pending renewal order for the subscription and stores `_daymark_renewal_subscription_id` on that order.
- PASS: The member receives a normal WooCommerce payment URL; no local renewal is marked paid before a WooCommerce payment-complete event.
- PASS: Processing/completed renewal order events extend the Daymark period and create a paid invoice; failed events notify the member/admin and preserve the unpaid state.
- PASS: The existing renewal claim guard prevents the fallback settlement path when a renewal order was created.
- PENDING: Automatic card charging still depends on the installed WooCommerce gateway's saved-token capability; core WooCommerce cannot safely charge an arbitrary gateway without that contract. Live order/payment testing was unavailable.

## 2026-11-04 — Payment grace policy

- PASS: The subscription schema now stores `payment_issue_at` and migrates it with dbDelta.
- PASS: Failed Stripe/Woo renewal states enter `past_due`; the hourly renewal worker expires unresolved payment issues after seven days.
- PASS: Successful renewal and scheduled cancellation clear stale payment-issue timestamps.
- PENDING: Provider-specific retry cadence remains configured at the provider; live dunning behavior was not tested.

## 2026-11-04 — Provider reconciliation

- PASS: Activation/migration schedules a daily reconciliation hook and deactivation/uninstall clear it.
- PASS: The job is bounded to 50 active, paused, or past-due local Stripe subscriptions and refreshes provider state through the existing subscription mapper.
- PENDING: WooCommerce reconciliation and live cron execution were not tested; persistent cron is still recommended for low-traffic sites.

## 2026-11-04 — Administrator audit trail

- PASS: A migrated `daymark_audit_logs` table stores actor, action, target, details, and timestamp.
- PASS: Admins can review plan changes, revocations, and review decisions from Subscriptions → Audit log.
- PASS: The DB version is bumped and uninstall drops the audit table.
- PENDING: Historical actions before this migration cannot be reconstructed; provider reconciliation and webhook replay UI remain open.

## 2026-11-04 — Provider billing portal

- PASS: Authenticated members with an active, paused, or past-due provider customer can request a Stripe Billing Portal session from Subscription Management.
- PASS: The route is protected by the existing authenticated REST permission check and returns explicit errors for missing provider profiles, development mode, and WooCommerce gateway-managed billing.
- PASS: REST, SDK, member UI, loading state, and provider implementation are connected.
- PENDING: Stripe portal configuration and live payment-method update were not tested against a Stripe account in this environment.

## 2026-11-04 — Retired feature references

- PASS: Live dashboard and plan creation UI no longer advertise or link to the retired Access & Entitlements screen.
- PENDING: Legacy handlers/classes remain in the plugin for backward compatibility and require a separate migration/deletion decision before removal.

## 2026-11-04 — Profile-picture write contract

- PASS: REST settings no longer accepts a remote `profile_picture_url`; the existing authenticated file-upload route is the only write path.
- PASS: PHP lint re-run is required after this change.

## 2026-11-04 — Billing state hardening

- PASS: Guest checkout still supports new email addresses without login, while an existing account email returns a clear login-required conflict and no longer mutates that account's phone metadata.
- PASS: Period-end Stripe plan changes use `proration_behavior=none` after the renewal invoice succeeds; immediate plan changes retain the configured immediate-invoice behavior.
- PASS: Editing price, currency, or interval retires the active Stripe mapping and preserves the old price in history so existing provider webhooks can still resolve the original plan.
- PASS: Stripe refund, dispute, void, and uncollectible invoice events update the local subscription and invoice payment state in one database transaction and notify the member/admin.
- PASS: Uninstall cleanup now includes the invoices table, billing-related options, and profile metadata.
- PASS: PHP lint passed for all 14 plugin PHP files.
- PENDING: Live Stripe/WooCommerce webhook replay, database transaction test, and browser checkout verification were unavailable during this pass.

## 2026-11-04 — Plan upgrade null-render guard

- PASS: Plan action labels are checked before writing `textContent`.
- PASS: Missing current-subscription markup and plan cards now exit safely or show a recoverable message instead of throwing.
- PASS: Plugin version bumped to 1.27.85 for frontend asset cache-busting.
- PENDING: Live browser console/upgrade verification was unavailable during this pass.

## 2026-11-04 — Catalog view visual redesign

- PASS: Plan rows now read as focused catalog records instead of a dense undifferentiated grid.
- PASS: Existing plan edit, subscriber count, archive, and delete controls retain their selectors and behavior.
- PASS: The updated catalog styling keeps the existing Daymark tokens and responsive stacking rules.
- PASS: Plugin version bumped to 1.27.84 for admin stylesheet cache-busting.
- PENDING: Live Chrome visual comparison and keyboard interaction were unavailable during this pass.

## 2026-11-04 — Cancel scheduled plan change

- PASS: Authenticated members can cancel only their own pending plan change through `POST /daymark/v1/me/subscription/change/cancel`.
- PASS: The endpoint uses the existing rate limit, user lock, row lock, transaction, stale-state check, and rollback path before clearing `pending_plan_id` and `change_effective_at`.
- PASS: The SDK exposes `cancelPlanChange()` and the Plans UI refreshes from the server response after cancellation.
- PASS: The scheduled plan button becomes available again after the pending state is cleared.
- PASS: The REST contract and lifecycle hook are documented in `readme.txt`.
- PENDING: Live browser request, concurrent-request test, and provider/renewal sandbox verification were unavailable during this pass.

## 2026-11-04 — Per-plan subscriber management

- PASS: Plan cards expose current subscriber counts and open a focused subscriber drawer.
- PASS: Subscriber rows support admin plan changes and immediate revocation through manage-options REST endpoints.
- PASS: Counts exclude canceled/expired history, and provider synchronization plus transaction locking are reused for destructive lifecycle actions.
- PASS: Plugin version bumped to 1.27.82 for admin asset cache-busting.
- PENDING: Live Chrome interaction, provider sandbox cancellation, and database-backed lifecycle execution were unavailable during this pass.

## 2026-11-04 — Scheduled plan button state

- PASS: The plan returned by `pending_plan_id` is rendered with a `Scheduled` label and native disabled state.
- PASS: The state is restored from the server subscription response after refresh, rather than relying only on optimistic client state.
- PASS: The current plan remains separately labeled `Current plan`; other plan actions remain available.
- PENDING: Live browser refresh and period-end renewal verification were unavailable during this pass.

## 2026-11-04 — Plans & Pricing edit drawer visibility

- PASS: The edit drawer panel now overrides the create-drawer off-canvas transform, so clicking Edit displays the panel immediately.
- PASS: Plugin version bumped to 1.27.80 so the updated admin stylesheet is cache-busted.
- PENDING: Live Chrome click-through was unavailable during this pass.

## 2026-11-04 — Plan-change drawer null guard

- PASS: The plan-change drawer now verifies all required child controls before writing `textContent` or binding the confirm handler.
- PASS: Incomplete or stale markup shows a recoverable refresh message instead of throwing a JavaScript exception.
- PASS: Plugin version bumped to 1.27.79 so the updated frontend asset is cache-busted.
- PENDING: Live browser upgrade submission and provider sandbox verification were unavailable during this pass.

## 2026-11-04 — Optional plan-change parameter validation

- PASS: The optional `effective` REST argument no longer receives an invalid empty-string default, so requests that omit it pass WordPress enum validation.
- PASS: Plan-change timing remains server-controlled by `Daymark_Subscriptions_Admin::plan_change_timing()`; client input is still not authoritative.
- PASS: Plugin version bumped to 1.27.78 so enqueued assets and the updated plugin are cache-busted together.
- PENDING: Live authenticated upgrade, Stripe sandbox, and WooCommerce verification were unavailable during this pass.

## 2026-11-04 — Plans & Pricing management

- PASS: Manage-options administrators can edit plan catalog fields through an accessible right-side drawer.
- PASS: Active plans can be archived; inactive plans can be deleted only when no subscription or invoice references exist.
- PASS: REST update/delete routes are permission-protected and the SDK exposes matching `updatePlan`/`deletePlan` methods.
- PASS: Public plan retrieval continues to exclude inactive plans, while admin catalog data includes archived plans.
- PASS: Checkout success rendering remains registered independently of the removed access enforcement hooks.
- PENDING: Live admin browser interaction, database-backed delete/usage test, and provider sandbox verification were unavailable.

## 2026-11-04 — Access & Entitlements removal

- PASS: The Access & Entitlements submenu and callback are no longer registered.
- PASS: Editor access meta boxes, WordPress menu-item access controls, access/feature shortcodes, feature REST routes, SDK feature helpers, and frontend plan feature rendering are no longer active.
- PASS: Existing option data and legacy source files are retained but no longer loaded, avoiding destructive data deletion.
- PASS: PHP lint and whitespace validation completed for the changed PHP files.
- PENDING: Existing protected content should be reviewed because access enforcement is intentionally disabled by this request.

## 2026-11-04 — Admin plan-change timing setting

- PASS: Manage-options administrators can save `Immediate` or `At period end` from the Settings page.
- PASS: Member plan-change timing is read server-side from the validated option; client input is not authoritative.
- PASS: Default remains `period_end` for existing installations and invalid option values fall back safely.
- PENDING: Live admin browser save and provider sandbox verification were unavailable.

## 2026-11-04 — Period-end plan changes

- PASS: Upgrade and downgrade requests are now forced to `period_end` server-side, regardless of a client-supplied effective value.
- PASS: Confirmation copy clearly states that the current plan remains active and the new recurring charge starts after the current period ends.
- PENDING: Live renewal webhook, Stripe sandbox charge, and browser verification were unavailable during this pass.

## 2026-11-04 — Plan upgrade and downgrade flow

- PASS: Existing authenticated plan-change endpoint is now reached through an explicit confirmation drawer.
- PASS: Price increases default to immediate provider synchronization; lower-priced changes are forced to period-end server-side.
- PASS: Stripe/dev payment failure remains an error and does not advance the local plan state.
- PASS: Pending plan messaging is shown from the server subscription projection when available.
- PASS: PHP lint and diff whitespace checks passed.
- PENDING: Live browser, Stripe sandbox, and WooCommerce checkout verification were unavailable during this pass.

## 2026-11-04 — Privacy & Security two-column layout

- PASS: Privacy controls and password security are presented as separate panels in a balanced desktop grid.
- PASS: The layout stacks below 900px to preserve usable field width on tablet and mobile screens.
- PENDING: Live browser screenshot remains unavailable.

## 2026-11-04 — Privacy & Security settings

- PASS: Profile visibility and product-update consent are loaded from and saved through the authenticated `/me/settings` REST endpoint.
- PASS: Private profiles do not expose the member name, avatar, handle, or description to other visitors.
- PASS: Existing WordPress native password-reset flow remains available as a separate security card.
- PASS: Desktop two-column and mobile stacked layouts, loading state, status messaging, and keyboard-accessible checkbox labels are covered.
- PENDING: Live browser interaction and screenshot were unavailable during this pass.

## 2026-11-04 — Subscription Management enhancement

- PASS: Page now exposes only factual subscription fields already returned by the authenticated REST payload.
- PASS: Current plan, status, billing amount/cycle, next billing date, payment status, actions, invoices, and no-subscription state have distinct hierarchy.
- PASS: Desktop two-column layout and mobile stacked layout are covered in CSS.
- PENDING: Live browser screenshot was unavailable during this pass.

## 2026-11-04 — Author profile visual redesign

- PASS: Author profile uses real profile data only and avoids invented metrics, decorative gradients, generic card grids, and unnecessary controls.
- PASS: Desktop alignment separates identity from readable About content; mobile reflows to one column.
- PENDING: Live browser screenshot was unavailable during this pass.

## 2026-11-04 — Author profile template

- PASS: Plugin now loads `templates/author.php` for WordPress author archives.
- PASS: Legacy `/profile/` page is migrated away from the profile shortcode and redirects to the logged-in user's author URL.
- PENDING: Live author archive navigation was not rechecked after the browser session became unavailable.

## 2026-11-04 — Profile picture crop modal

- PASS: Image selection opens a modal crop surface before upload.
- PASS: Pointer drag is clamped to keep the image covering the square crop; Save picture uploads the generated JPEG crop.
- PENDING: Actual file selection and upload were not executed in Chrome during this pass.

## 2026-11-04 — Profile picture auto-save

- PASS: Selecting a valid profile image now immediately calls the authenticated upload endpoint and displays upload status.
- PASS: The separate Account access save button is no longer rendered; only the personal details save action remains.

## 2026-11-04 — Settings two-column cleanup

- PASS: Email input is no longer rendered in Settings.
- PASS: Personal details and Profile picture forms render as two desktop columns and collapse at the existing mobile breakpoint.

## 2026-11-03 — Subscription Management brand token follow-up

- PASS: Dedicated Subscription Management now receives the configured brand token and renders teal/danger action borders correctly.

## 2026-11-03 — Subscription action button theme reset

- PASS: Explicit border width/style keeps the dedicated management controls visibly actionable in the live theme.

## 2026-11-03 — Subscription action button polish

- PASS: Pause/resume and cancellation controls now retain visible borders and consistent full-width sizing against the active theme reset.

## 2026-11-03 — Subscription Management page

- PASS: Subscription controls and invoices are removed from Settings and rendered on the dedicated `/subscription-management/` page.
- PASS: Pause, resume, cancellation, pending states, invoice rows, and loading feedback use the existing authenticated REST SDK methods.

## 2026-11-03 — Settings sections and Privacy & Security

- PASS: First name, last name, display name, and profile description now appear in the Personal details form in the requested order.
- PASS: Email and profile picture are isolated in a separate Account access form with independent save behavior.
- PASS: Privacy & Security shortcode uses WordPress's native lost-password endpoint and a dedicated default page slug.

## 2026-11-03 — Settings template width follow-up

- PASS: Live theme content constraint was identified and scoped override applied; Settings template now targets a 1200px outer width.

## 2026-11-03 — Settings template width

- PASS: Member Settings content width is now capped at 1200px while mobile breakpoint behavior remains unchanged.

## 2026-11-03 — Member settings UI redesign

- PASS: Desktop layout separates editable account fields from subscription and invoice summary.
- PASS: Mobile viewport collapses the two-column layout into a single readable column.
- PASS: Live Chrome screenshot verified heading order, file upload affordance, WYSIWYG container, action buttons, invoice grouping, and card spacing.

## 2026-11-03 — Profile picture upload

- PASS: Member settings JavaScript now reads the `profile_picture` file input, matching the rendered form and authenticated upload endpoint.

- PASS: Settings accepts JPG, PNG, GIF, and WebP uploads up to 5 MB through a nonce-protected member endpoint.
- PASS: Uploaded media is associated with the current WordPress user and the Profile page reads the saved image URL.
- PENDING: A real image file upload was not executed during this audit.

## 2026-11-03 — Invoice history

- PASS: User-scoped invoice history is exposed through the authenticated Settings response and rendered under Subscription management.
- PASS: Activation invoices and Stripe renewal/payment events are persisted with provider links when supplied.
- PENDING: No real provider invoice webhook was replayed during this audit.

## 2026-11-03 — Profile settings editor

- PASS: Settings exposes profile picture URL preview and the WordPress rich description editor.
- PASS: Settings REST responses and updates persist the profile description and picture URL; Profile reads the same stored values.

## 2026-11-03 — Profile presentation

- PASS: Profile page no longer exposes an edit form and renders only avatar, display name, and sanitized user description.
- PASS: Existing default Profile page content is migrated only when it still contains the plugin-created member-settings shortcode.

## 2026-11-03 — Default member pages

- PASS: Missing Subscribe, Profile, Settings, and Checkout pages are created only for administrators and only when the slug does not already exist.
- PASS: Existing pages are preserved; the Profile and Settings pages reuse the existing member-settings shortcode and its REST/API behavior.

## 2026-11-03 — Guest checkout

- PASS: Visitors can select a plan and submit name, email, and phone without logging in.
- PASS: Guest checkout resolves a WordPress subscriber account server-side and preserves the existing provider/subscription ownership model.
- PENDING: No live provider charge or WooCommerce guest order was executed.

## 2026-11-03 — Plan approval workflow

- PASS: Existing plans default to Direct approval through the `approval_mode` database default; no existing checkout behavior changes.
- PASS: Review plans create a `pending_review` request without opening a provider checkout; provider checkout is created only after administrator approval.
- PASS: `pending_review` is excluded from access grants and normal active lifecycle operations; duplicate checkout attempts are rejected server-side.
- PASS: Stripe subscription updates and renewal events do not auto-promote a pending review to active.
- PASS: Admin Approve/Reject actions require `manage_options` and a WordPress nonce; rejection attempts provider cancellation before local cancellation.
- PASS: Review plans do not send the active-subscription email until approval; rejection does not grant access.
- PASS: Review requests notify the site admin, are listed in a dedicated Pending approvals screen, and only create a provider checkout after approval.
- PASS: Stripe and WooCommerce approval checkouts activate the existing pending row only after successful payment; activation sends the member login link and invoice email.
- PASS: Database schema version 1.8.3 is delivered through the existing `dbDelta` activation path.
- PENDING: No real payment, webhook, provider cancellation, or admin approval/rejection was executed during this change.

## 2026-11-03 — Checkout success CTA

- PASS: Checkout success now offers a login CTA with the current site URL as the post-login destination and retains the return-to-website link.

## 2026-11-03 — Checkout success page

- PASS: Stripe and Development return URLs now use the shared `daymark_checkout=success` route.
- PASS: Tagged Daymark WooCommerce orders use the same branded success route without changing unrelated WooCommerce order return URLs.
- PASS: Success rendering is handled before access protection and supports themes with or without classic header/footer templates.
- PASS: The success template keeps provider confirmation messaging generic; Development mode explicitly states that no provider was charged.
- PENDING: No external Stripe charge or WooCommerce order was executed during this change.

## 2026-11-03 — Button loading states

- PASS: Frontend plan, checkout, member settings, pause/resume, and cancellation actions use a shared disabled loading state and recover the button on errors.
- PASS: Admin plugin forms use delegated submit handling, including forms moved into the plan drawer/modal, with button-level spinner feedback and `aria-busy`.
- PASS: Immediate UI controls such as drawer/modal close, WYSIWYG formatting, and color presets are not presented as fake async work.
- PASS: Reduced-motion CSS remains respected for the spinner animation.
- PENDING: A real API error was not forced in the browser; recovery was verified statically and via the existing catch paths.

## Scope

Reviewed the Daymark Subscriptions admin dashboard styling after the visual redesign. The change is CSS-only and is scoped to `.daymark-admin` plus the existing modal class names.

## Findings

- The dark, high-contrast hero was replaced with a restrained operator banner so the metrics and recent subscription table carry the visual priority.
- Metric cards now read as one information strip with dividers instead of four floating cards.
- Panels, tables, forms, and status chips keep existing selectors and data behavior while using smaller radii, lighter borders, and fewer decorative effects.
- Responsive behavior remains explicit at 960px and 700px, including stacked grids and horizontal table overflow.
- Existing focus treatment and reduced-motion behavior remain present for interactive controls.

## Verification

- PASS — Re-read the updated stylesheet and confirmed every prior selector used by the PHP admin templates remains defined.
- PASS — Change is limited to `assets/admin-dashboard.css`; no form action, nonce, data query, or capability check was modified.
- UNAVAILABLE — Live WordPress browser screenshot and keyboard traversal were not run because no local admin session/browser route was available in this turn.

## Remaining risk

Visual spacing can still vary with the active WordPress admin theme/plugin stack. Validate the real Overview, Plans & Pricing, Subscriptions, Access & Entitlements, and Settings screens in a logged-in browser before release.

## Live Chrome UI audit — 2026-11-02

### Coverage

Reviewed the logged-in local WordPress admin in Chrome at desktop width:

- Overview
- Plans & Pricing
- Subscriptions and filter modal
- Access & Entitlements
- Settings
- Create plan modal
- Modal Escape close and focus return

### Findings

| Priority | Finding | Evidence | Recommendation |
| --- | --- | --- | --- |
| FIXED | Access & Entitlements had an inconsistent composition: `Feature builder` was a bare heading/description/button block while the two access-rule sections were bordered panels. | Updated `.daymark-admin > .wrap` panel treatment | Feature Builder now follows the same bordered panel language. |
| FIXED | Modal primary submit buttons used WordPress blue while the admin workspace primary action was teal. | Added `.daymark-modal .button-primary` rules | Body-mounted modal actions now use the Daymark teal signal. |
| P2 | Form actions are hidden behind modal triggers on Plans, Subscriptions, Access, and Settings. This keeps pages compact, but adds an extra click and hides available actions from first-time users. | Live page AX trees and screenshots | Keep the modal pattern for dense forms only; consider showing the primary form inline on Plans and Settings. |
| FIXED | The empty subscription table only said no records match. | Updated empty-state copy | It now suggests adjusting the filter or waiting for the first subscription. |
| FIXED | Filter search placeholder was visually truncated inside the modal at the observed desktop width. | Shortened server-rendered placeholder | The placeholder now reads “Search member, email, plan, or provider ID”. |

### Checks

- PASS — Typography is consistent across all pages: system WordPress admin font, clear heading scale, readable body copy, and repeated kicker treatment.
- PASS — Desktop spacing and alignment are consistent on Overview, Plans, Subscriptions, and Settings.
- PASS — Empty tables remain readable and do not break the layout.
- PASS — Modal Escape closes the dialog and returns focus to the trigger.
- PASS — Modal focus initially enters the first field.
- PASS — No submit or destructive action was triggered during audit.
- NOT_RUN — Mobile viewport screenshot and touch-target check; the live browser surface did not expose a viewport override in this audit session.
- NOT_RUN — Console and network log inspection; available browser control surface did not expose runtime logs.

### Score

Hierarchy 4/5 · Typography 4/5 · Color 3/5 · Spacing 4/5 · Consistency 3/5 · States 3/5 · Responsiveness 3/5 · Content clarity 4/5

Verdict: The brand system and main desktop consistency issues are fixed in source; live Chrome recheck is required before release sign-off.

Follow-up: The first live recheck exposed a body-mounted modal token-scope issue; modal-local tokens and explicit white button text were added before final verification.

Final live recheck:

- PASS — Overview renders the Daymark ring lockup, teal primary action, rule-based metric strip, and factual empty state.
- PASS — Access & Entitlements now presents Feature Builder, Page Rules, and Menu Rules with the same panel structure.
- PASS — Create Plan modal primary action renders with teal background and white text.
- PASS — Subscriptions empty state includes next-step guidance.
- PASS — No form submission, record creation, setting change, or destructive action was performed during verification.
- PASS — Final Daymark lockup now reads `DAYMARK / SUBSCRIPTIONS` and uses the open-ring mark in the page boundary and overview banner only.
- PASS — Live Overview and Create Plan modal reflect the documented type scale, 40px form controls, 4px control radius, 6px panel radius, teal focus/action system, and role-based surfaces.
- PASS — A specificity regression in the broad form-control selector was caught and fixed before completion; submit buttons are excluded from field styling.
## 2026-11-02 — Branding application and Design page

- PASS: Design page is available under Subscriptions → Design and uses the existing admin shell.
- PASS: Accent saving is restricted to `manage_options`, protected by `check_admin_referer()`, and sanitized with `sanitize_hex_color()`.
- PASS: Admin and frontend read the same stored value; invalid or missing values fall back to Daymark teal.
- PASS: Custom hex input, native color picker, preset buttons, and preview remain synchronized in the admin browser UI.
- PASS: Design form is excluded from the existing modal wrapper so color selection remains an inline settings interaction.
- PASS: Live Chrome verification confirmed the inline Design form, preset synchronization, `Changes saved.` response, and propagation to the Overview action/brand mark using a temporary Harbor Blue save; the site was restored to Daymark teal afterward.
- PENDING: A published page containing `[daymark_subscribe]` or `[daymark_member_settings]` was not available in the local site, so frontend rendering was verified by static enqueue/token inspection rather than a live shortcode page.
## 2026-11-02 — Provider mapping simplification

- PASS: Create Plan no longer asks for Stripe Price ID or WooCommerce Product ID.
- PASS: New plans persist without manual provider mappings; existing database columns and REST fields remain backward-compatible.
- PASS: Stripe checkout now lazily creates and stores a recurring Price from the Daymark plan when the first Stripe checkout is requested.
- PASS: WooCommerce checkout now lazily creates/reuses a published, hidden, virtual, sold-individually ghost product and links it to the plan.
- PASS: Live Chrome Plans & Pricing audit confirmed the Create Plan modal contains only plan/business fields; Stripe Price ID and WooCommerce Product ID are absent.
- PENDING: Provider creation requires live Stripe credentials or WooCommerce activation; no external provider mutation was run in this local audit.
- PASS: Plan and feature description fields initialize through the inline visual editor with formatting controls; the modal form remains usable after editor initialization.
- PASS: HTML descriptions are sanitized with `wp_kses_post()` and plan descriptions render as safe markup on the frontend.
- PASS: WYSIWYG initialization was rechecked after modal relocation and asset-cache version bump.
- PASS: API Integration page shows the selected provider and readiness status.
- PASS: Stripe credential form uses password inputs, nonce/capability checks, blank-preserve behavior, and constant precedence.
- PASS: WooCommerce provider shows readiness without inventing an API-key requirement.
## 2026-11-02 — Stripe payment modes

- PASS: API Integration exposes Sandbox and Live modes, with Sandbox selected by default.
- PASS: credentials and webhook signing secrets are stored/read per active mode; constants remain higher priority.
- PASS: Stripe Price creation and subscription plan changes resolve the active mode’s Price mapping.
- PASS: no Stripe credentials were entered and no external Stripe request was executed during this audit.

## 2026-11-02 — Plans & Pricing redesign

- PASS: live Chrome audit confirmed branded catalog context, provider/mode context, stats strip, detailed create-plan form, and empty catalog state.
- PASS: create-plan form remains keyboard-visible inline and still uses the existing nonce/action contract.
- PASS: responsive CSS was added for tablet and narrow layouts; no new dependency or provider mutation was introduced.
- PASS: Create Plan opens in a right-side drawer with close/backdrop/Escape/focus behavior; the underlying form action and nonce remain unchanged.
- PASS: currency is configured from Settings → Checkout defaults and applied to newly created admin plans; existing plan currency values are preserved.
- PASS: Feature Builder now requires assigned plans, displays assigned plans in the matrix, and filters assigned-only features during user resolution.
- PASS: plan-scoped feature values remain isolated from other plans during server-side resolution.
- PASS: page access rules now render as an editor sidebar meta box for page/post/UI-enabled custom post types; singular enforcement uses the same stored rule option.
- PASS: authenticated users failing a singular access rule now receive the branded Access revoked template; unauthenticated users retain the login redirect path.
- PASS: block-theme fallback now renders a standalone document without calling deprecated classic header/footer loaders when those templates are unavailable.
- PASS: Access revoked CSS is now isolated in `assets/access-revoked.css`; the template retains only semantic markup and the dynamic accent variable.
- PASS: Feature Builder Assigned plans renders as styled checkbox cards while preserving the existing `plan_ids[]` form payload.
- PASS: live Chrome audit found and fixed stretched checkbox inputs caused by the shared form width rule.
- PASS: menu access fields are registered on native nav menu items and saved into the existing menu-rule option; duplicate settings UI removed.

## 2026-11-02 — Database schema update

- PASS: `Daymark_Subscriptions_DB::maybe_upgrade()` already runs the activation schema routine when the stored DB version differs, so the 1.8.1 index migration is automatic on the next plugin load.
- PASS: Added a non-unique `stripe_price_id` index for plan price resolution and a non-unique `provider_subscription_id` index for subscription webhook/provider resolution.
- PASS: Existing plan, subscription, event, and rate-limit columns remain unchanged; no rows are deleted or transformed.
- PASS: Feature assignments and page/menu access rules remain in their existing WordPress options by design; no duplicate tables were introduced.
- PASS: Opt-in uninstall now removes the plugin options introduced by the branding, currency, Stripe mode, and database error flows.
- NOT_RUN: Live database migration was not executed because the local Wamp/WordPress runtime was not active in this session.

## 2026-11-02 — Frontend checkout smoke test

- PASS: Published the `Plans` page at `/index.php/plans/` with the `[daymark_subscribe]` shortcode.
- FOUND: The initial plan click requested `http://localhost/me/subscription` and returned an HTML 404, producing `Unexpected token '<'` in the frontend.
- FIXED: The SDK now reads the localized `apiBase` configuration used by the shortcode while preserving `baseUrl` compatibility.
- PENDING: Checkout redirect requires a configured payment provider credential; no payment or external provider mutation was executed.

## 2026-11-02 — Native checkout page

- Added `[daymark_checkout]` for a branded, server-rendered checkout review page.
- Plan buttons now route to `/checkout/?plan_id=...` before invoking the selected provider.
- Checkout keeps payment data out of the plugin UI; the final provider step remains responsible for payment collection.
- Stripe/WooCommerce provider credentials were not entered, so no payment redirect or charge was executed.

## 2026-11-02 — Plans and Checkout frontend branding

- PASS: Saved admin branding variables now scope to Plans, Member Settings, and native Checkout, keeping accent rules, buttons, and focus states consistent.
- PASS: Checkout now receives the shared frontend ink, muted, border, soft-surface, and focus tokens instead of relying on theme fallbacks.

## 2026-11-02 — Plans page member and visitor states

- PASS: Logged-out visitors see active plan details, pricing, feature availability, and a direct login CTA; subscribe actions remain disabled.
- PASS: Active members see current plan, renewal date, cancellation timing, and upgrade/downgrade labels; the current plan action is disabled.
- PASS: Canceled and expired members receive a restart message and are routed back through checkout when choosing a plan.
- PASS: Feature Builder assignments are rendered on plan cards without exposing provider IDs or internal metadata.
- PASS: PHP lint, diff check, and live Chrome Plans page verification completed after cache version bump.
- PENDING: Past-due recovery still requires the existing provider payment-method flow; no new payment-method UI was invented in this change.

## 2026-11-02 — Subscription management placement

- PASS: Pause, resume, and cancel controls are now rendered only in Member Settings subscription management.
- PASS: Plans page retains plan selection and plan-change status without duplicate lifecycle controls.
- PASS: Existing authenticated REST pause, resume, and cancel endpoints are reused.
- PASS: Member action buttons re-enable after successful state refresh and preserve the correct next action.

- Applied the Daymark visual system to plan cards and the native checkout layout.
- Improved desktop content width, card hierarchy, price emphasis, CTA sizing, panel alignment, and mobile stacking.
- Preserved existing shortcode data, provider routing, accessibility labels, and payment behavior.
- Live visual recheck completed on the published checkout page after the stylesheet cache version bump.

## 2026-11-03 — Payment execution mode

- PASS: Production is the default environment and existing provider checkout remains unchanged in Production.
- PASS: Development mode is enforced inside the authenticated checkout path, not only in the UI; it creates a local active subscription without external Stripe/WooCommerce requests.
- PASS: Mode changes require `manage_options` capability and a WordPress admin nonce; a `DAYMARK_SUBSCRIPTIONS_ENVIRONMENT` constant can lock the setting.
- PASS: Development subscription creation uses the existing transactional/idempotent `create_subscription_once()` path and server-validated plan data.
- PASS: Development subscription lifecycle actions bypass provider sync, including after switching the environment back to Production.
- PENDING: No payment provider request or real charge was executed during this change; Dev mode was not enabled on the local site.

## 2026-11-04 — Past-due payment recovery

- PASS: Authenticated members with a past-due subscription receive a provider-specific retry action. Stripe opens the existing billing portal; WooCommerce opens only the owned, unpaid renewal order payment URL.
- PASS: The REST endpoint rejects non-past-due subscriptions and never accepts a client-supplied subscription or order ID.
- PASS: The JavaScript SDK and Subscription Management UI expose loading/error states for the recovery action.
- PENDING: Provider payment completion and browser navigation were not executed in this environment; contract smoke tests and PHP lint cover the source boundary only.

## 2026-11-04 — Provider-aware billing adjustments

- PASS: Admin billing actions now resolve the current subscription's own provider invoice or WooCommerce renewal order; clients cannot supply arbitrary invoice/order IDs.
- PASS: Refund and credit amounts are validated against the provider invoice/order, mutating actions require an idempotency key, and successful actions are recorded in the audit log.
- PASS: Stripe supports refund, credit note, void, uncollectible, resend, and pay actions; WooCommerce supports refund/credit through the renewal order, cancellation for void/uncollectible, invoice email resend, and payment-link retry.
- PENDING: No live provider charge/refund or admin browser action was executed in this environment; static contract tests and PHP lint cover the source boundary only.

## 2026-11-04 — Provider and webhook health

- PASS: Provider Test connection now returns safe connection details and Stripe webhook telemetry for last received, processed, and failed events.
- PASS: WooCommerce health messaging explicitly states that recurring renewal charging requires a compatible gateway adapter; otherwise the pending-order/manual-payment flow is used.
- PENDING: No live provider request or webhook delivery was executed; the health payload and UI are covered by source contracts only.

## 2026-11-04 — Subscription payment policy

- PASS: Administrators can configure the payment grace period from Subscription Settings with a nonce-protected, capability-checked 1–90 day field; the renewal expiry worker uses the saved value.
- PASS: The seven-day default is preserved and the option is removed during explicit plugin data deletion.
- PENDING: Cancellation policy, pause duration, renewal reminder timing, and default approval policy remain separate product decisions and were not inferred from the audit.

## 2026-11-04 — Shared content-width design token

- PASS: Design now exposes validated compact, standard, and wide content widths; the selected value is injected as one frontend token and applied across the plugin page containers.
- PASS: The setting uses the existing capability/nonce-protected branding save path and is removed during explicit plugin data deletion.
- PENDING: Logo, font family, color-token set, radius scale, button treatment, and page-level preview remain separate design work.

## 2026-11-04 — Privacy self-service

- PASS: Authenticated members can download a JSON export containing sanitized account, subscription, and invoice data without provider identifiers.
- PASS: Account deletion is a non-destructive, rate-limited request workflow that notifies the site administrator, records an audit event, and preserves billing history for review.
- PENDING: Admin approval/execution UI for deletion requests and configurable retention periods remain separate work; no automatic destructive deletion was introduced.

## 2026-11-04 — Privacy deletion review

- PASS: Pending approvals now lists privacy deletion requests with nonce-protected approve/reject actions.
- PASS: Approval anonymizes profile/contact data and removes plugin profile metadata while preserving subscription and invoice rows for retention/audit requirements.
- PENDING: User account deletion itself is intentionally not automatic; WordPress/site retention policy may require a separate manual decision after anonymization.

## 2026-11-04 — Admin subscription pagination

- PASS: Admin subscription list now uses a server-side count and offset with 50 records per page; search and status filters remain in pagination links.
- RESOLVED: Read-only member detail timeline and invoice context are available from the subscription drawer; provider dashboard links remain separate.

## 2026-11-04 — Member detail drawer

- PASS: Subscription rows now open a protected read-only drawer with member identity, current/pending plan, invoice history, and subscription audit timeline.
- PASS: Detail data is scoped by subscription ID and user-controlled values are rendered as text in the admin UI.
- PENDING: Provider-specific customer dashboard links and live browser verification remain unavailable in this environment.

## 2026-11-04 — Member billing self-service detail

- PASS: Cancellation now accepts optional feedback, records it in the subscription audit log, and keeps the existing provider cancellation/idempotency flow.
- PASS: Invoice history offers a local text receipt fallback when no provider URL exists.
- PENDING: Full plan comparison UI and a broader localized runtime string catalog remain separate work.

## 2026-11-04 — WordPress date localization

- PASS: Subscription Management receives the WordPress locale/timezone and formats period/invoice dates accordingly.
- PENDING: Remaining literal JavaScript UI strings still need a translation catalog if broader multilingual runtime coverage is required.

## 2026-11-04 — Button treatment token

- PASS: Design now exposes a validated solid/outline button treatment applied to admin primary actions and frontend buttons.
- PENDING: Logo upload and per-status palette remain separate design work; page-level preview is resolved below.

## 2026-11-04 — Page-level design preview

- PASS: Design now previews representative plan, checkout, warning, and empty-invoice states using the same tokenized surfaces and actions.
- PENDING: Logo upload and a full mobile interaction preview remain separate; the current preview is intentionally static and dependency-free.

## 2026-11-04 — Plan catalog visibility and CTA

- PASS: Plans now support validated visible/hidden catalog state and an optional CTA label through DB, admin, REST, public API, and frontend card rendering.
- PASS: Hidden active plans remain reachable through direct checkout URLs because visibility filters public catalog retrieval only.
- PENDING: Logo upload, intro pricing, coupons, seats, and per-plan tax rules remain separate product-policy work.

## 2026-11-04 — Catalog runtime labels

- PASS: Catalog state updates preserve each plan's configured CTA label instead of replacing it with a hard-coded label.
- PASS: The plans script now receives WordPress locale/timezone metadata for consistent runtime formatting contracts.
- PENDING: Full runtime translation catalogs remain separate work.

## 2026-11-04 — Plan trial periods

- PASS: Plan trial days are stored, validated from 0–365, editable through REST/admin plan flows, exposed in the public plan projection, and shown on the branded plan cards.
- PASS: Stripe Checkout receives the trial period; Development mode advances the first period after the trial; WooCommerce core checkout returns an explicit unsupported response for trial plans.
- PENDING: Introductory pricing, setup fees, coupons, seats, and per-plan tax behavior remain separate pricing-model work.

## 2026-11-04 — Notification delivery controls

- PASS: Subscription settings now expose the admin recipient, sender name/email, and an admin-alert toggle with capability and nonce protection.
- PASS: Subscription lifecycle and invoice messages use the configured sender while admin alerts can be disabled without disabling member transactional mail.
- PASS: Notification options are removed during explicit plugin data deletion and contract coverage protects the scoped mail filters.
- PENDING: Email template editing, per-event routing, delivery logs, and live SMTP/provider delivery were not added or verified; WordPress mail transport still determines final delivery.

## 2026-11-04 — Public plan catalog contract

- PASS: The public plans endpoint now supports bounded pagination, deterministic sort order, explicit ascending/descending order, and WordPress-style total headers.
- PENDING: The current frontend requests the default first page; an intentionally large catalog still needs client-side pagination UI if that use case is required.

## 2026-11-04 — Shared design token controls

- PASS: Design now stores validated ink, muted, surface, font-stack, and radius tokens alongside the existing accent and width settings.
- PASS: The same token values are injected into admin and frontend CSS variables and applied to shared frontend surfaces; explicit uninstall removes the token option.
- PENDING: Logo upload, per-status palette, button-style presets, and full page-level preview remain separate design work.

## 2026-11-04 — Invoice-backed admin reporting

- PASS: Overview now provides a validated date-filtered report based on stored invoices and subscriptions, including net invoiced amount, paid/failed invoice counts, new subscriptions, cancellations, and plan breakdown.
- PENDING: MRR/ARR normalization across currencies, cohort churn, conversion funnels, and export files remain separate reporting work; mixed currencies intentionally suppress the global total and remain displayed by plan/currency rather than being summed falsely.

## 2026-11-04 — Admin catalog localization follow-up

- PASS: Admin plan and subscriber runtime states now use the localized `DaymarkAdminConfig.strings` catalog for common labels, errors, and loading states, with English fallbacks for compatibility.
- PENDING: Full WordPress translation extraction and live browser verification remain outside this static contract pass.

## 2026-11-04 — Webhook verification health follow-up

- PASS: Provider health now reports last received, last verified, last processed, and last failed webhook timestamps; verified time is persisted only after Stripe signature validation reaches event storage.
- PASS: Database version is `1.10.1` and uses the existing idempotent `dbDelta` migration path.

## 2026-11-04 — Introductory pricing follow-up

- PASS: Plans now store a lower introductory price and bounded cycle count, with validation that prevents invalid daily or non-discounted offers.
- PASS: Stripe Checkout applies the introductory amount for the configured recurring cycles through an idempotent, mode-specific coupon mapping.
- PASS: WooCommerce exposes an explicit unsupported boundary instead of silently charging the wrong amount.
- PASS: Existing plan edits invalidate the introductory mapping before a new offer is created.

## 2026-11-04 — Admin runtime translation coverage

- PASS: Common admin runtime labels and state messages now resolve through `DaymarkAdminConfig.strings` with compatibility fallbacks.
- PENDING: Live locale rendering and complete translation extraction still require a WordPress/browser run.

## 2026-11-04 — Frontend runtime translation coverage

- PASS: Subscription Management runtime states now use WordPress-localized strings for invoices, lifecycle actions, payment recovery, and empty/pending states.
- PENDING: Live locale rendering and complete translation extraction still require a WordPress/browser run.

## 2026-11-04 — Plans runtime translation coverage

- PASS: Plans UI runtime messages now use WordPress-localized strings for checkout, plan changes, pending states, and payment issues.
- PENDING: Live locale rendering and complete translation extraction still require a WordPress/browser run.

## 2026-11-09 — Remaining settings/runtime completion

- PASS: Retry maximum attempts and initial backoff are validated, persisted, applied to scheduled job failure handling, and removed by uninstall cleanup.
- PASS: Members can revoke all active WordPress sessions through an authenticated, rate-limited REST action; the UI confirms the action and exposes loading/error state.
- PASS: Transactional email delivery stores only status metadata and a recipient hash; admin resend is capability- and nonce-protected and rejects unsupported or admin-only records.
- PASS: Privacy approval now queues anonymization for configured retention; scheduled cleanup protects administrators and preserves subscription, invoice, and audit history.
- PASS: Plans expose pause enablement and cancellation timing (`inherit`, `period_end`, `immediate`) through the REST contract and admin plan forms. Member lifecycle endpoints enforce the saved plan policy server-side.
- PASS: Contract, WordPress integration, lifecycle, webhook, PHP lint, and `git diff --check` checks passed after `DB_VERSION 1.19.0` changes.
- PENDING: Live Chrome route checks, SMTP delivery, real Stripe/WooCommerce provider renewals, and real server cron execution remain deployment/staging evidence, not static-test evidence.

## 2026-11-09 — Frontend template design system pass

- PASS: Shared frontend tokens now provide a consistent editorial layout, surface, border, radius, shadow, spacing, focus, and responsive system across plans, checkout, settings, profile, privacy, subscription management, and author profile pages.
- PASS: Plan cards use a responsive three-column desktop grid, featured-state treatment, full-width primary actions, and one-column mobile reflow.
- PASS: Checkout uses an asymmetric plan-summary/form composition with stronger price hierarchy and consistent form controls.
- PASS: Success and access-revoked templates use the same branded status-card language with accessible focus states and mobile stacking.
- PASS: Local browser verification completed for Settings, Plans, and Checkout pages at desktop width; existing PHP/WordPress contract, integration, lifecycle, webhook, and lint checks remain green.
- PENDING: Mobile-width screenshot verification, keyboard-only walkthrough, screen-reader audit, and cross-theme checks require additional browser/staging coverage.

## 2026-11-09 — Member profile refinement

- PASS: The authenticated profile content and public author template now use an identity-led layout with avatar, handle, member-since metadata, profile visibility context, readable About content, and responsive reflow.
- PASS: The public author grid no longer leaves the About content trapped in a narrow avatar-width column.
- PASS: Browser verification completed on the local public member profile route after asset cache refresh.
- PENDING: Mobile-width screenshot, keyboard-only, screen-reader, and cross-theme verification remain deployment/staging checks.

## 2026-11-09 — Subscription management action cleanup

- PASS: Renewal/expired-state action now reads “Upgrade plan”.
- PASS: Compare-plans link injection was removed from subscription management.
- PASS: Retry payment remains hidden unless the subscription is in the server-supported `past_due` recovery state.
- PASS: Contract smoke checks cover the label, removal, and recovery visibility condition.
