# Daymark Subscriptions — Missing Options Audit

**Audit date:** 2026-11-04  
**Scope:** Missing user/member options, subscription-plan options, admin settings, design controls, operational controls, and supporting REST/SDK processes.  
**Method:** Read-only repository audit. No code was changed by this audit.  
**Load assumed:** One WordPress site with multiple administrators, hundreds to thousands of subscribers, and provider webhooks/cron running asynchronously.

## What this repo does

Daymark manages subscription plans, checkout, member subscription lifecycle, invoices, Stripe webhooks, WooCommerce renewal orders, admin review, and branded WordPress frontend pages. The current product has a solid billing foundation, but several customer-facing controls and operator settings are still fixed in code or delegated to the provider.

## Already present

The following are implemented and are not repeated as missing findings: payment-provider selection, plan currency default, Development/Production mode, Stripe Sandbox/Live mode, direct/admin-review approval, immediate/period-end plan changes, Stripe automatic tax toggle, accent-color branding, profile picture upload/crop, privacy visibility, product-update preference, password reset, pause/resume, cancellation, Stripe billing portal, invoice list, duplicate review, webhook replay, bulk subscription actions, and scheduled-job status cards.

## Must fix

### 1. Provider connection test and webhook health check are missing

- **Evidence:** `includes/class-daymark-subscriptions-admin.php:251-260` only shows a configured/ready label; no provider test route or last-successful-connection state exists.
- **What this is:** Admin can save credentials, but cannot verify that the selected keys, mode, webhook secret, and provider account work together.
- **Problem:** A malformed Stripe key or inactive WooCommerce setup can appear ready until a customer reaches checkout.
- **Fix:** Add a protected “Test connection” action with safe result details, plus webhook health fields: last received, last verified, last processed, last failure, and mode.
- **If we skip it:** Operators discover broken billing only after a customer checkout or renewal fails.

### 2. WooCommerce recurring charging is still not a complete product option

- **Evidence:** `includes/class-daymark-payment-provider.php` documents the adapter filter, while `readme.txt` states that automatic charging requires a compatible external gateway.
- **What this is:** WooCommerce renewal orders and payment links exist, but Daymark does not provide a native saved-token charge path.
- **Problem:** Selecting WooCommerce does not by itself give the administrator a predictable recurring-billing experience.
- **Fix:** Either ship one supported gateway adapter contract with setup diagnostics, or label WooCommerce mode clearly as “manual renewal order flow” and expose its limitations before activation.
- **If we skip it:** A site owner may assume recurring cards are automatic and silently accumulate past-due subscriptions.

### 3. Member email-change and contact-management options are incomplete

- **Evidence:** `includes/class-daymark-subscriptions-rest.php:170-180` updates names, description, and preferences only; `includes/class-daymark-subscriptions-shortcode.php:112-123` displays email/profile-picture sections but has no verified email-change flow or phone editor.
- **What this is:** The account settings page cannot update a member’s email or phone after checkout.
- **Problem:** A member who changes email cannot receive invoices or recovery messages at the new address, and phone data can become stale.
- **Fix:** Add email-change confirmation with old/new-address verification, plus a validated phone field saved through one authenticated settings route.
- **If we skip it:** Billing notifications can continue going to an inaccessible address and support must repair accounts manually.

### 4. Subscription recovery has no first-class retry-payment action

- **Evidence:** `assets/subscription-management.js:41-50` shows a payment warning for `past_due`; only the Stripe portal button is available, and WooCommerce returns a gateway-managed unsupported response.
- **What this is:** Past-due members are told to update payment details, but the UI does not provide a provider-specific “Retry payment” or renewal-order link.
- **Problem:** WooCommerce members cannot see or pay the pending renewal from Subscription Management, and Stripe retry behavior remains external.
- **Fix:** Return a recovery URL/action from the provider layer and render it as a clear payment-recovery CTA with the invoice/order context.
- **If we skip it:** Past-due recovery depends on email wording or a provider dashboard the member may never find.

### 5. Financial adjustment controls are missing

- **Evidence:** REST routes in `includes/class-daymark-subscriptions-rest.php:1-30` and admin actions in `includes/class-daymark-subscriptions-admin.php:11` contain no refund, partial refund, credit, void, invoice resend, or manual payment route.
- **What this is:** The plugin records provider payment state but does not provide operator actions for common billing corrections.
- **Problem:** A refund or service credit requires leaving the plugin, and local invoice/subscription state can be updated later than the provider.
- **Fix:** Add provider-capability-aware refund/void/resend actions with confirmation, idempotency, audit logging, and local reconciliation.
- **If we skip it:** Support and accounting cannot safely resolve ordinary billing exceptions from the admin dashboard.

## Should fix

### 6. Plan pricing model is too narrow for real subscription catalogs

- **Evidence:** The create-plan form at `includes/class-daymark-subscriptions-admin.php:190` exposes only name, slug, price, currency, day/month/year interval, approval, description, and active state.
- **What this is:** Plans have one recurring price and one interval.
- **Problem:** There is no trial period, introductory price, setup fee, coupon/discount, minimum commitment, grace period override, tax behavior per plan, or quantity/seat model.
- **Fix:** Add only the pricing fields the product actually needs, starting with trial days and introductory pricing; keep provider IDs internal.
- **If we skip it:** Many commercial offers will require custom code or separate plans that make reporting and upgrades harder.

### 7. Plan merchandising and ordering options are missing

- **Evidence:** `includes/class-daymark-subscriptions-admin.php:191` renders plans in database order and only shows name, description, price, mapping text, status, and subscriber count.
- **What this is:** Administrators cannot mark a plan as recommended, featured, hidden-but-purchasable, or arrange the public catalog.
- **Problem:** The frontend cannot intentionally guide customers toward a preferred offer or present a stable sales order.
- **Fix:** Add `sort_order`, visibility, featured/recommended state, short benefit list, and optional CTA label; expose the same fields in public plan data.
- **If we skip it:** The plan page remains an operational list rather than a controlled pricing experience.

### 8. Plan benefits and entitlement presentation are absent by design but still needed for sales UX

- **Evidence:** `includes/class-daymark-subscriptions-shortcode.php:148-151` renders only the plan description, price, interval, and button; the retired Feature Builder is intentionally not active.
- **What this is:** Plans have no structured customer-facing benefits list.
- **Problem:** Administrators must put all value communication into one description, making comparison, accessibility, and consistent card layout difficult.
- **Fix:** Add a simple ordered plan-benefits field on each plan. Keep it presentation-only unless a separate entitlement product is deliberately reintroduced.
- **If we skip it:** Customers cannot scan differences between plans without reading long prose.

### 9. Subscription-management member options are incomplete

- **Evidence:** `includes/class-daymark-subscriptions-shortcode.php:69-77` exposes status, billing summary, invoices, billing portal, pause, cancel, and recovery; no plan comparison, cancellation reason, scheduled-change detail history, or downloadable local receipt exists.
- **What this is:** The page covers basic lifecycle actions but not the full member self-service journey.
- **Problem:** Members cannot explain cancellation, compare available plans from the management page, or retrieve a local receipt when the provider URL is unavailable.
- **Fix:** Add cancellation reason/feedback, clear pending-change history, plan comparison link, and a local invoice fallback with status and amount.
- **If we skip it:** Support receives avoidable “what happens next?” and “where is my receipt?” requests.

### 10. User privacy controls do not include data export, deletion, or retention choices

- **Evidence:** `includes/class-daymark-subscriptions-shortcode.php:45-54` provides only public-profile visibility, product updates, and password reset; `uninstall.php:17-39` is site-wide deletion, not member self-service.
- **What this is:** Members cannot request a copy of their profile/subscription data or request account deletion from the plugin.
- **Problem:** Site owners have no plugin-level workflow for privacy requests, retention review, or deletion exceptions caused by billing history.
- **Fix:** Add export request, deletion request, retention notice, and admin review state; preserve legally required billing records while removing personal profile data where allowed.
- **If we skip it:** Privacy requests remain manual and retention decisions are undocumented.

### 11. Notification settings are too coarse and hard-coded

- **Evidence:** `includes/class-daymark-subscriptions-mailer.php:5-79` sends fixed messages through `wp_mail()` and uses the global `admin_email`; the only member preference is `daymark_product_updates`.
- **What this is:** There are no per-event toggles, sender identity, reply-to address, template editor, or delivery-status view.
- **Problem:** A site cannot disable a non-essential notification, route operational alerts to a billing address, or brand transactional email consistently.
- **Fix:** Add event-level preferences for member/admin mail, sender name/address, reply-to, and safe editable templates with placeholders.
- **If we skip it:** Email behavior will not match the site’s support and compliance policy.

### 12. Admin design settings cover only one accent color

- **Evidence:** `includes/class-daymark-subscriptions-admin.php:262-266` stores only `daymark_subscriptions_brand_color`; `docs/branding.md:63-69` confirms the themeable system is one validated hex accent.
- **What this is:** Design page controls the primary accent and presets, but not the rest of the visual system.
- **Problem:** A site cannot configure logo/mark, font family, type scale, container width, surface/background colors, border radius, button treatment, status colors, or dark-mode preference.
- **Fix:** Add a small validated design-token set with live preview: logo upload, accent/ink/muted/surface colors, font stack, radius scale, content width, and button style. Keep safe defaults and contrast checks.
- **If we skip it:** Frontend pages may feel branded only through one color while still looking unrelated to the site.

### 13. Frontend layout options are fixed in CSS

- **Evidence:** `assets/frontend.css:11-24` hard-codes a `1120px` subscription container and a `760px` profile container; `includes/class-daymark-subscriptions-shortcode.php:174-176` injects only accent variables.
- **What this is:** Page width, density, card style, and spacing cannot be configured from Design.
- **Problem:** The same layout may be too wide for a narrow theme or too constrained for a large site, and template pages cannot align with the site’s content grid.
- **Fix:** Add one validated content-width and density token first; derive all page containers from it rather than adding per-page overrides.
- **If we skip it:** Administrators will keep editing CSS manually and updates can overwrite those changes.

### 14. Subscription policy settings are missing

- **Evidence:** `includes/class-daymark-subscriptions-admin.php:242-248` exposes only plan-change timing and Stripe automatic tax; grace period is fixed in the renewal worker and approval is fixed per plan.
- **What this is:** Operators cannot configure cancellation policy, grace days, retry behavior, pause duration, renewal reminder timing, or default approval behavior.
- **Problem:** Business policy is encoded in code/provider defaults rather than visible settings.
- **Fix:** Add a policy card with only supported controls: grace days, cancellation mode, pause duration, renewal reminders, and default approval mode. Validate every value and show the resulting lifecycle.
- **If we skip it:** Two sites with different business rules require code changes instead of settings.

### 15. Admin subscriber operations lack pagination and richer member inspection

- **Evidence:** `includes/class-daymark-subscriptions-admin.php:198-200` requests at most 100 rows; `:274-275` shows a compact table, while the subscriber drawer in `assets/admin-dashboard.js:91-114` provides plan change and revoke only.
- **What this is:** The operational list has a fixed cap and limited member context.
- **Problem:** Larger sites cannot reliably review all records, and an admin cannot inspect invoice history, provider customer ID, payment issue age, or lifecycle timeline before acting.
- **Fix:** Add server-side pagination and a read-only member detail drawer with subscription, invoice, audit, and provider-status summaries.
- **If we skip it:** Records beyond the first 100 disappear from normal review, increasing the chance of acting on incomplete information.

### 16. Reporting options are limited to counts

- **Evidence:** `includes/class-daymark-subscriptions-admin.php:172-177` shows active, past-due, paused, and live-plan counts; no revenue, churn, renewal, conversion, or plan-performance query is present.
- **What this is:** The dashboard reports state counts but not business performance.
- **Problem:** An operator cannot answer which plan earns revenue, how many members churned, or whether renewals are succeeding.
- **Fix:** Add a small date-filtered report layer for MRR/ARR, new subscriptions, cancellations, renewal success, and revenue by plan; do not invent metrics without invoice-backed data.
- **If we skip it:** Commercial decisions remain dependent on exports or provider dashboards.

## Nice to have

### 17. Public plan API has no pagination, ordering, or catalog metadata contract

- **Evidence:** The public plan projection in `includes/class-daymark-subscriptions-db.php` returns a flat active-plan list with no documented sort, featured state, benefits, or pagination.
- **What this is:** The API is enough for the current small catalog but not for a richer pricing UI.
- **Problem:** External frontend clients cannot request a stable order or distinguish featured plans without duplicating business rules.
- **Fix:** Add documented `sort_order`, `featured`, and `benefits` fields first; add pagination only when catalog size requires it.
- **If we skip it:** SDK consumers will implement inconsistent ordering and presentation logic.

### 18. Localization and formatting controls are incomplete

- **Evidence:** `assets/subscription-management.js:20-22` and `assets/subscriptions-ui.js:18-19` format dates in the browser locale, while several labels remain literal JavaScript strings; no plugin locale/timezone preference exists.
- **What this is:** Dates and labels are not consistently controlled by WordPress/site locale.
- **Problem:** A member can see a date format different from the admin, and untranslated runtime strings remain in English.
- **Fix:** Return formatted display values or locale configuration from WordPress and move user-facing JavaScript strings into localized script data.
- **If we skip it:** International sites receive inconsistent dates and partially translated interfaces.

### 19. Design preview is not page-level

- **Evidence:** `includes/class-daymark-subscriptions-admin.php:266` previews one mark, paragraph, and button only.
- **What this is:** Design settings do not preview plans, checkout, settings, subscription management, error, empty, or mobile states.
- **Problem:** A color can look acceptable in one small preview but fail contrast or hierarchy in cards, tables, warnings, and forms.
- **Fix:** Add a lightweight tabbed preview using existing components and representative long/empty/error states; keep it static and dependency-free.
- **If we skip it:** Administrators cannot judge the actual branded experience before publishing it.

### 20. Integration test coverage does not cover WordPress runtime or browser flows

- **Evidence:** `tests/test-contracts.php` is dependency-free source/fixture contract coverage; `tests/README.md` states that provider, database, and browser execution remain outside its proof.
- **What this is:** High-risk money, auth, migration, REST, and UI flows lack automated WordPress integration and browser tests.
- **Problem:** Static checks can pass while nonce behavior, DB transactions, redirects, uploads, or provider callbacks fail in WordPress.
- **Fix:** Add a small PHPUnit/WordPress integration suite, REST tests, and browser smoke tests for guest checkout, admin review, plan change, renewal recovery, profile upload, and webhook replay.
- **If we skip it:** Regressions will continue to be discovered manually after deployment.

## Recommended implementation order

1. Provider connection/webhook health and WooCommerce renewal clarity.
2. Member email/phone management and past-due payment recovery.
3. Financial adjustment controls and notification settings.
4. Plan trials/benefits/order plus subscription policy settings.
5. Design-token expansion and page-level preview.
6. Pagination/member detail, reporting, localization, and runtime test coverage.

## Verdict

The plugin is operationally usable, but it is not yet a complete subscription product for varied businesses. Fix provider health, member contact/recovery, and financial operations first; design expansion should follow the same token system already documented instead of adding page-specific CSS.

## Lean

Do not add every option at once. Start with the smallest shared contracts: provider health, verified contact changes, recovery action, plan benefits/order, and a compact design-token set. Avoid rebuilding the retired entitlement system unless access control becomes an explicit product requirement again.

## Not checked

No live provider account, real WooCommerce gateway, production cron, real member database, browser checkout transaction, email delivery, or accessibility scanner was run during this audit. Existing `docs/full-plugin-audit-2026-11-04.md` and `docs/audit-report.md` were read but not modified.
