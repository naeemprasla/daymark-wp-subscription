=== Daymark Subscriptions ===
Contributors: daymark
Tags: subscriptions, memberships, recurring billing, REST API
Requires at least: 6.4
Requires PHP: 7.4
Tested up to: 6.8
Stable tag: 1.30.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage subscription plans and user subscription lifecycle in WordPress with an admin UI, shortcode, REST API, and secure server-side PHP SDK. See `docs/developer-documentation.md` for the extension contract.

== Description ==

Daymark Subscriptions provides plan management, authenticated subscription actions, Stripe or WooCommerce checkout, signed Stripe webhook activation/renewal, a WordPress REST API, and a secure server-side PHP SDK. Configure Stripe secrets before accepting payments; Stripe Prices and hidden WooCommerce checkout products are created automatically when first used.

== Installation ==

1. Copy the `daymark-subscriptions` directory into `wp-content/plugins/`.
2. Activate Daymark Subscriptions in Plugins.
3. Open Subscriptions in the WordPress admin and create a plan.
4. Add `[daymark_subscribe]` to a page.
5. Add `[daymark_member_settings]` to a members-only page for profile, notification preference, and subscription status management.

On an administrator visit, missing `subscribe`, `profile`, `settings`, and `checkout` pages are created automatically with the matching plugin shortcodes. Existing pages with those slugs are never overwritten.

== REST API ==

The namespace is `/wp-json/daymark/v1`.

* `GET /plans` is public.
* `GET /me/subscription` requires a logged-in user.
* `POST /me/checkout` creates a provider checkout session and requires `plan_id` and `X-Daymark-Idempotency-Key`.
* Public visitors can use the native checkout form with name, email, and phone; a new account is created server-side before payment. Existing account emails must authenticate first.
* `POST /me/subscription` remains a backward-compatible Checkout alias.
* `POST /me/email-change` sends a single-use confirmation link before changing the authenticated member email; the current email remains active until confirmation.
* `DELETE /me/subscription` schedules cancellation at period end; pass `immediate=true` for immediate cancellation.
* `POST /me/subscription/pause` pauses an active subscription.
* `POST /me/subscription/resume` resumes a paused or period-end-cancelled subscription.
* Development mode is available under Subscriptions > Settings. It is production-safe by default: Production is the default, while Development creates a local test subscription without calling Stripe or WooCommerce. A `DAYMARK_SUBSCRIPTIONS_ENVIRONMENT` constant can lock the mode from `wp-config.php`.
* Async plugin actions show a disabled loading state with a spinner while requests or form submissions are in progress.
* Stripe, Development, and tagged WooCommerce subscription checkouts return to the branded Checkout complete template.
* Each plan can use Direct approval or Admin review; reviewed checkouts remain pending until an administrator approves or rejects them.
* Admin review requests are emailed to the site administrator, shown under Subscriptions → Pending approvals, and do not create a payment checkout until approved. Approved members receive a payment/login email and an invoice after activation.
* `POST /me/subscription/change` changes plan immediately for upgrades and at period end for downgrades by default.
* `POST /me/subscription/change/preview` returns the next recurring amount, effective date, and provider proration preview when supported; WooCommerce and Development return an explicit unavailable boundary.
* `POST /me/subscription/change/cancel` cancels the authenticated member's pending period-end plan change.
* `POST /me/account-access` sends a generic guest account-recovery response and a one-time password setup link for matching Daymark accounts.
* `POST /me/billing-portal` opens the configured provider billing portal for payment-method updates; Stripe is supported, while WooCommerce returns an explicit gateway-managed response.
* `POST /me/subscription/payment-recovery` opens the provider-specific payment action for a past-due subscription: Stripe billing portal or the pending WooCommerce renewal order payment URL.
* Admins can use `POST /subscriptions/{id}/billing-action` for provider-backed `refund`, `credit`, `void`, `uncollectible`, `resend`, and `pay` actions. The route resolves the subscription's own invoice/renewal order, requires an idempotency key, and records an audit event; unsupported provider states return an explicit error.
* Subscription Settings includes a validated 1–90 day payment-grace policy used by the expiry worker; the default remains seven days.
* Subscription Settings also supports default approval mode, default cancellation timing, and a bounded pause duration (0 for indefinite).
* Subscription Settings can send one configurable-window renewal reminder before the next billing date; set it to 0 to disable.
* Notification settings support per-event delivery toggles, editable plain-text subject/body templates with placeholders, and an optional Reply-To address; Design settings support an attachment-backed logo and status palette.
* Design includes a validated shared content-width token (960px, 1200px, or 1320px) applied to plugin frontend containers.
* Privacy & Security includes a sanitized member data export and an administrator-reviewed account deletion request; billing records are not deleted automatically.
* Pending approvals includes account-deletion request review. Approval anonymizes profile/contact data and preserves subscription and invoice history; rejection clears the request.
* Admin Subscriptions now uses server-side pagination (50 records per page) while preserving status/search filters.
* Plans support a validated Stripe trial period of 0–365 days. WooCommerce core checkout rejects trial plans explicitly because its recurring trial charge contract is not provided by this plugin.
* Stripe automatic tax can be enabled from API Integration; WooCommerce checkout uses the store's configured tax settings and rejects plans whose currency differs from the store currency.
* The server-side SDK contract is `1.3.0`; add-ons can consume read-only catalog/member data, lifecycle methods, provider readiness, secure authorization helpers, and documented subscription events without accessing Daymark tables or provider secrets.
* Add-ons should register after `daymark_subscriptions_loaded`, use their own REST namespace and permission callback, and keep their own data in their own tables/options.
* Admin integrations can revoke or move up to 50 subscriptions in one protected bulk REST request; partial failures are returned per subscription and logged.

Cookie-authenticated requests must include the WordPress REST nonce. External server-side clients should use a supported WordPress authentication method.

== Hooks ==

* `daymark_subscription_created` fires after a subscription is stored.
* `daymark_subscription_pending_review` fires when a review request is created.
* `daymark_subscription_cancelled` fires after cancellation is stored.
* `daymark_subscription_paused`, `daymark_subscription_resumed`, `daymark_subscription_plan_changed`, and `daymark_subscription_plan_change_cancelled` fire after those member lifecycle writes commit.
* `daymark_subscription_payment_updated` fires after a paid, failed, refunded, disputed, voided, or uncollectible provider payment update.
* `daymark_subscription_provider_cancelled` fires after provider cancellation is stored.
* A daily reconciliation job refreshes up to 50 active, paused, or past-due Stripe subscriptions from the provider. Failed payments enter a seven-day local grace window before expiring if no successful renewal arrives.
* Administrator plan changes, revocations, and review decisions are recorded in Subscriptions → Audit log.
* Stripe webhook payloads are retained with processing status; failed events can be replayed from Subscriptions → Audit log after the underlying issue is fixed.
* WooCommerce renewals create one idempotent pending renewal order and send the member its payment URL. A compatible gateway can process it through normal WooCommerce checkout; payment-complete/failed order events reconcile the Daymark subscription. The `daymark_woocommerce_renewal_due` hook remains available for gateway adapters, which must use the supplied renewal order and never settle without provider payment evidence.
* Gateway adapters that can charge a saved WooCommerce token off-session may use the `daymark_woocommerce_renewal_charge` filter. Return `true` only after the supplied order is paid; Daymark refuses to settle the renewal unless WooCommerce reports paid evidence. If the filter remains `false`, the normal payment-link fallback is used.

Scheduled renewal and reconciliation jobs record attempts and errors, retry with bounded exponential backoff, and enter dead-letter state after three failures. Administrators can retry a dead-letter job from Audit log or through the server-side SDK/REST route.

Stripe has separate Sandbox and Live modes on Subscriptions → API Integration. Sandbox is the default and uses test keys; Live uses live keys. Each mode keeps its own secret key, webhook signing secret, and automatically-created recurring Price mappings. Define the mode-specific constants in `wp-config.php` or use the API Integration form; constants take priority over saved options. Stripe sends signed events to `/wp-json/daymark/v1/stripe/webhook`; only verified events activate subscriptions and record renewals. Refund, dispute, void, and uncollectible events move the local subscription to `past_due`. Period-end plan changes update the next cycle without creating an extra immediate proration invoice. Taxes and production monitoring remain provider-specific.

WooCommerce can be selected as an alternate checkout provider from the admin screen or with `DAYMARK_PAYMENT_PROVIDER`. WooCommerce mode uses WooCommerce core checkout/orders and creates a hidden, virtual ghost product for each plan on first checkout; existing product mappings remain supported. Renewals create a pending WooCommerce renewal order and payment URL. Automatic card charging still requires a compatible gateway adapter; without one, the member completes the normal WooCommerce payment flow manually. The API Integration health check states this manual-renewal boundary explicitly. WooCommerce mode does not provide provider-side pause, resume, or plan-change synchronization; those actions return an explicit unsupported response.

Content access rules are outside the current subscription catalog scope. The plugin does not expose the retired Access & Entitlements or Feature Builder screens.

Subscription activation, cancellation, pause, resume, plan changes, successful renewals, failed payments, and provider cancellation send notifications to the subscriber and the WordPress site administrator through `wp_mail`. Configure reliable SMTP or a transactional mail provider for production delivery.

== Privacy ==

The plugin stores WordPress user IDs, plan details, subscription status, timestamps and idempotency keys. It does not store payment-card data. Site owners should document retention and deletion requirements for their deployment.

== Changelog ==

= 1.0.0 =
* Initial release.
