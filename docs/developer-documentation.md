# Daymark Subscriptions Developer Documentation

Version: 1.31.1  
API namespace: `/wp-json/daymark/v1`  
Source implementation: `includes/class-daymark-subscriptions-rest.php`  
Server SDK: `includes/class-daymark-subscriptions-sdk.php`
SDK contract: `1.3.1`

This document describes the current REST contract, server-side PHP SDK, lifecycle behavior, authentication and integration hooks. It is written from the implementation in this checkout. No browser JavaScript SDK is shipped; browser-facing screens use the plugin REST contract directly.

Guest checkout accounts expose a pending state until verified provider payment and the emailed WordPress password-claim flow complete. Add-ons should treat `daymark_account_state` as an internal access safeguard and use the SDK's canonical subscription/access methods rather than reading user metadata directly.

## Server-side PHP SDK

Use the server SDK when building another WordPress plugin or add-on. It is loaded automatically with Daymark and is available as `Daymark_Subscriptions_SDK`. The facade is the supported extension boundary: add-ons do not need to read Daymark tables, call Stripe directly, or store provider credentials.

Plan responses now include the provider-neutral pricing policy fields (`setup_fee`, commitment cycles, promotion-code flag, quantity bounds, and tax behavior). Checkout accepts a validated `quantity` for quantity-enabled plans. Subscription responses also expose the agreed `price_snapshot`, `currency_snapshot`, `interval_snapshot`, `quantity`, and `commitment_ends_at`; add-ons should use these snapshot values for member billing displays and entitlement decisions rather than reading mutable plan prices.

```php
if ( class_exists( 'Daymark_Subscriptions_SDK' ) && Daymark_Subscriptions_SDK::has_access( get_current_user_id(), array( 'professional' ) ) ) {
    // Add-on feature is available to this member.
}

$subscription = Daymark_Subscriptions_SDK::get_active_subscription( get_current_user_id() );
$plans        = Daymark_Subscriptions_SDK::list_plans();
$invoices     = Daymark_Subscriptions_SDK::get_invoices( get_current_user_id(), 20 );
```

### Supported server SDK methods

| Method | Purpose |
|---|---|
| `version()` | Return the server SDK contract version |
| `is_available($minimum_version)` / `supports($capability)` / `contract()` | Check additive SDK compatibility and capabilities |
| `list_plans($active_only = true)` | Return normalized plan records |
| `get_plan($id_or_slug, $active_only = false)` | Resolve one plan by ID or slug |
| `get_subscription($user_id = 0)` | Return the user’s current subscription; `0` means current user |
| `get_active_subscription($user_id = 0)` | Return only an active/paused/past-due subscription |
| `get_invoices($user_id = 0, $limit = 20)` | Return the user’s bounded invoice list |
| `get_invoice($invoice_id, $user_id = 0)` | Resolve one invoice with member-scope protection |
| `get_latest_invoice($user_id = 0)` | Return the newest invoice |
| `get_member_timeline($user_id = 0, $limit = 50)` | Return bounded invoice and lifecycle timeline data |
| `get_pending_review($user_id = 0)` / `get_pending_payment($user_id = 0)` | Resolve pending subscription states |
| `has_access($user_id, $plans, $statuses)` | Apply the canonical Daymark access rule |
| `is_active($user_id = 0)` / `is_paused($user_id = 0)` / `is_past_due($user_id = 0)` / `is_pending($user_id = 0)` | Check canonical subscription status |
| `days_until_renewal($user_id = 0)` | Return bounded days until the current period ends |
| `get_member($user_id = 0)` | Return a safe member profile projection |
| `get_member_settings($user_id = 0)` / `update_member_settings($data, $user_id = 0)` | Read or update member settings |
| `provider()` | Return `stripe` or `woocommerce` |
| `environment()` | Return `dev` or `prod` |
| `is_development_mode()` | Detect payment bypass mode |
| `provider_health()` | Return provider readiness/capability information |
| `checkout_status()` | Return production checkout readiness and hard blockers |
| `provider_capabilities()` | Return provider, environment, renewal, and WooCommerce capability flags |
| `Daymark_Subscriptions_SDK::checkout_status()` | Return whether the selected checkout can accept the current environment's transactions |
| `admin_provider_health()` | Return provider readiness only to administrators |
| `checkout($plan_id, $customer, $idempotency_key, $quantity = 1)` | Use the same validated native checkout flow as REST; checkout idempotency is owner-scoped |
| `pause_subscription($user_id = 0)` / `resume_subscription($user_id = 0)` | Run validated provider-aware lifecycle actions |
| `cancel_subscription($user_id, $reason, $immediate)` | Cancel immediately or at period end |
| `change_plan($plan_id, $effective, $user_id = 0)` | Change plan using the configured timing |
| `cancel_pending_plan_change($user_id = 0)` | Remove a scheduled plan change |
| `get_billing_portal_url($user_id = 0)` / `get_payment_recovery_url($user_id = 0)` | Return provider billing/recovery actions |
| `approve_subscription($subscription_id)` / `reject_subscription($subscription_id, $reason)` | Review a pending subscription as an administrator |
| `list_pending_reviews($limit, $page)` | Return a bounded administrator pending-review list |
| `list_subscribers($args)` / `get_subscriber_detail($subscription_id)` | Read protected admin subscriber data |
| `list_plan_subscribers($plan_id)` | Read protected subscribers assigned to one plan |
| `change_subscriber_plan($subscription_id, $plan_id)` / `revoke_subscription($subscription_id)` | Perform protected admin subscription actions |
| `bulk_subscription_action($ids, $action, $plan_id)` | Run the bounded admin bulk action |
| `admin_billing_action($subscription_id, $action, $amount, $idempotency_key)` | Run an audited provider billing action; returns a pending approval response when second-admin policy is enabled |
| `approve_operation($operation_id)` | Approve a pending second-admin operation as a different administrator |
| `authorize_member($user_id = 0)` / `authorize_admin($capability)` | Return `true` or a stable `WP_Error` for add-on REST authorization |
| `error($code, $message, $status, $data)` | Build a stable SDK `WP_Error` response |

Add-on bootstrap example:

```php
add_action( 'daymark_subscriptions_loaded', function ( $contract ) {
    if ( ! Daymark_Subscriptions_SDK::is_available( '1.3.1' ) || ! Daymark_Subscriptions_SDK::supports( 'subscription_events' ) ) {
        return;
    }
    // Register the add-on integration.
} );
```

Server checkout example:

```php
$result = Daymark_Subscriptions_SDK::checkout(
    12,
    array(
        'name'  => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+1 555 0100',
    ),
    'my-addon-checkout-' . wp_generate_uuid4()
);

if ( is_wp_error( $result ) ) {
    // Inspect get_error_code(), get_error_message() and get_error_data().
} else {
	$payload = $result; // provider, id, url, or review result
}
```

SDK methods return native arrays/scalars on success and `WP_Error` on failure; lookup methods may return `false` when no record exists. The SDK checkout method delegates to the same rate-limited, idempotent REST checkout implementation. Add-ons must preserve the same idempotency key when retrying one logical checkout. It does not expose Stripe secret keys or payment-card data. Add-ons remain responsible for their own UI authorization and must not use a member’s subscription data to expose another member’s private information.

Lifecycle mutations such as pause, resume, cancellation and plan changes should use the authenticated REST contract or the existing frontend flow. This prevents add-ons from bypassing provider synchronization, idempotency and reconciliation rules. Use the documented WordPress hooks to extend lifecycle behavior.

The SDK lifecycle methods above reuse those same REST callbacks and return normalized response data arrays or `WP_Error`. Member-scoped methods default to the current WordPress user. Passing another user ID requires an approved Daymark administrator capability. Admin methods enforce the matching Daymark subscription, review, or billing capability. Add-ons must not call lifecycle methods while changing global WordPress user state in their own code.

SDK `1.3.1` exposes `checkout_owner_scoping` and `bulk_action_policy` capabilities. Checkout idempotency is scoped to the authenticated user ID or normalized guest email; replaying the same key with a different owner, plan, quantity or email returns `daymark_checkout_key_mismatch` (HTTP 409) and never returns the stored checkout URL. When second-administrator approval is enabled, bulk revoke returns `daymark_bulk_second_approval_unsupported` (HTTP 409); use individual revoke plus `approve_operation()` instead.

## Quick start

For WordPress cookie-authenticated REST requests, send a REST nonce. The plugin’s rendered pages localize the correct API base and nonce automatically. External server-side integrations must use a supported WordPress authentication method; do not expose Stripe secrets or WordPress application credentials in browser code.

## Authentication and request rules

| Request type | Authentication | Additional requirement |
|---|---|---|
| Public plan catalog | None | Only active, visible plans are returned |
| Guest checkout | None | `X-Daymark-Idempotency-Key`, customer name/email/phone |
| Member routes | Logged-in WordPress user | REST nonce for cookie auth |
| Admin routes | Daymark admin capability | REST nonce for cookie auth |
| Stripe webhook | Provider signature | `Stripe-Signature` header; nonce is not used |

JSON requests should send `Content-Type: application/json`. Profile-picture upload uses `multipart/form-data` with field `profile_picture`; do not manually set the multipart boundary.

## REST endpoints

### Public catalog and checkout

#### `GET /plans`

Returns the public catalog. Query parameters:

- `page`: integer, default `1`.
- `per_page`: integer from `1` to `100`, default `50`.
- `order`: `asc` or `desc`.

The response includes `X-WP-Total` and `X-WP-TotalPages` headers. Public plan objects include the plan name, slug, price, currency, interval, trial/intro offer, approval mode, benefits, visibility, CTA label and active state. Provider IDs are not public fields.

#### `POST /me/checkout`

Public native checkout entry point. Required headers and body:

```http
X-Daymark-Idempotency-Key: checkout-unique-key
Content-Type: application/json
```

```json
{
  "plan_id": 12,
  "customer_name": "Ada Lovelace",
  "customer_email": "ada@example.com",
  "customer_phone": "+1 555 0100"
}
```

The response is normally:

```json
{
  "provider": "stripe",
  "id": "cs_test_...",
  "url": "https://checkout.stripe.com/..."
}
```

Development mode returns a local test subscription URL. Review-mode plans return a review result instead of charging immediately. Existing account emails must authenticate before subscribing. The account is created before the provider session so provider metadata can identify the WordPress user; abandoned account cleanup is handled by the renewal worker after the configured retention window.

The idempotency key is server-scoped to the checkout owner. Retrying the same key is safe only for the same owner/email, plan and quantity. A different request using that key returns `409 daymark_checkout_key_mismatch`; a different key while a pending checkout exists returns `409 daymark_checkout_pending`. Keep the original key for retries and do not treat it as a public checkout identifier.

`POST /me/subscription` is retained as a backward-compatible authenticated alias for checkout. New public integrations should use `/me/checkout`.

### Plan administration

Plan administration requires `manage_daymark_subscriptions`, `manage_options`, or super-admin access.

| Method | Route | Purpose |
|---|---|---|
| `POST` | `/plans` | Create a plan |
| `PATCH` | `/plans/{id}` | Update editable plan fields |
| `DELETE` | `/plans/{id}` | Delete only unused plans; archive plans with history |
| `GET` | `/plans/{id}/subscribers` | List current subscribers for a plan |

Plan creation accepts `name`, `price` and optional catalog/billing fields. `slug` is optional and is generated from the plan name with a collision suffix. Existing plan slugs are immutable through the update route because public checkout links depend on them.

Catalog writes are intentionally not exposed through the server SDK. Add-ons can read plans and build their own experiences, while pricing mutations remain inside the protected Daymark admin UI/REST contract.

### Admin subscription operations

Routes in this section require the relevant Daymark subscription, review, or billing capability.

| Method | Route | Purpose |
|---|---|---|
| `POST` | `/subscriptions/{id}/plan` | Change a subscriber’s plan using the configured admin timing policy |
| `POST` | `/subscriptions/{id}/revoke` | Immediately revoke a subscription |
| `GET` | `/subscriptions/{id}/detail` | Read member, subscription, invoice and lifecycle context |
| `POST` | `/subscriptions/{id}/review` | Approve or reject a pending subscription; approval creates payment only after review |
| `POST` | `/subscriptions/bulk` | Change up to 50 subscriptions; revoke is unavailable when second-admin approval is enabled |
| `POST` | `/subscriptions/{id}/billing-action` | Refund, credit, void, mark uncollectible, resend or pay |
| `POST` | `/admin/operations/{id}/approve` | Approve a pending second-admin operation as a different administrator |
| `GET` | `/admin/provider-health` | Provider connection, renewal capability and webhook health |

Billing actions require an `idempotency_key` in the JSON body. Amounts are only used for supported refund/credit actions and are validated against the provider order/invoice.
When the second-admin policy is enabled, individual review, revoke, and billing mutations return a `202` response with an `operation_id`; a different administrator must approve that operation before the original action is executed. Bulk revoke returns `409 daymark_bulk_second_approval_unsupported` because the bulk endpoint has no batch approval contract.

### Member subscription and billing

These routes require a logged-in user.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/me/subscription` | Read the current subscription or an empty object |
| `DELETE` | `/me/subscription` | Cancel; body may include `immediate` and a `reason` up to 500 characters |
| `POST` | `/me/subscription/pause` | Pause where the selected provider supports it |
| `POST` | `/me/subscription/resume` | Resume a paused or period-end-cancelled subscription |
| `POST` | `/me/subscription/change` | Change plan immediately or at period end |
| `POST` | `/me/subscription/change/cancel` | Cancel a scheduled plan change |
| `POST` | `/me/billing-portal` | Open Stripe billing portal; WooCommerce is gateway-managed |
| `POST` | `/me/subscription/payment-recovery` | Open Stripe recovery or a WooCommerce renewal payment URL |

Stripe supports provider-side pause/resume, plan change and billing-portal flows. WooCommerce core checkout does not provide provider-side pause/resume/plan-change synchronization; unsupported actions are rejected by REST and hidden by the plugin frontend.

### Member profile, privacy and email

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/me/settings` | Read profile, preferences, subscription and invoices |
| `PATCH` | `/me/settings` | Update names, description, phone, product updates and visibility |
| `POST` | `/me/profile-picture` | Upload a JPG, PNG, GIF or WebP up to 5 MB |
| `POST` | `/me/email-change` | Send a single-use confirmation link |
| `GET` | `/me/email-change/confirm` | Confirm `user_id` and `token` from the email link |
| `GET` | `/me/privacy/export` | Return the member data export |
| `POST` | `/me/privacy/delete-request` | Submit an administrator-reviewed deletion request |

Email confirmation is capability-token based, expires after 24 hours and is rate limited. The current email remains active until confirmation succeeds.

### Stripe webhook

#### `POST /stripe/webhook`

This route is public at the HTTP permission layer but accepts only a valid Stripe signature configured for the active Sandbox/Live mode. The handler validates payload size, timestamp tolerance and HMAC signature, claims event IDs idempotently, processes the event, and retains processing state for replay.

Handled event families include checkout completion, subscription updates/deletion, invoice success/failure, voided/uncollectible invoices, disputes and refunds. Failed events can be replayed by an administrator after the root cause is fixed.

## Lifecycle and provider rules

1. A public visitor selects a visible plan and submits name, email and phone.
2. Daymark creates or reuses the WordPress account subject to the existing-account login rule.
3. Direct plans create provider checkout; review plans create `pending_review` without charging.
4. Stripe activates from a verified webhook. WooCommerce activates from paid order evidence.
5. Renewal failures enter `past_due` and use the configured grace window before expiry.
6. Provider/local mutation failures return a reconciliation-required error and create an audit record.
7. Daily reconciliation refreshes eligible Stripe subscriptions; failed reconciliation is written to the audit log.

## WordPress hooks

Important extension hooks:

- `daymark_subscriptions_loaded`
- `daymark_subscription_checkout_created`
- `daymark_subscription_payment_verified`
- `daymark_subscription_activated`
- `daymark_subscription_access_changed`
- `daymark_subscription_invoice_created`
- `daymark_subscription_invoice_paid`
- `daymark_subscription_invoice_failed`
- `daymark_subscription_payment_recovery_required`
- `daymark_subscription_reconciliation_required`
- `daymark_subscription_reconciled`
- `daymark_subscription_expired`
- `daymark_subscription_deleted`

- `daymark_subscription_created`
- `daymark_subscription_pending_review`
- `daymark_subscription_reviewed`
- `daymark_subscription_approved_for_payment`
- `daymark_subscription_cancelled`
- `daymark_subscription_paused`
- `daymark_subscription_resumed`
- `daymark_subscription_plan_changed`
- `daymark_subscription_plan_change_cancelled`
- `daymark_subscription_payment_updated`
- `daymark_subscription_provider_cancelled`
- `daymark_subscription_renewal_due`
- `daymark_woocommerce_renewal_charge`
- `daymark_woocommerce_duplicate_subscription`

The WooCommerce off-session adapter receives `(false, $order, $subscription, $operation_key)` and should return an array with `status` set to `paid`, `failed`, or `pending`. It may retain the legacy boolean contract (`true` means paid), but the renewal order must also be paid before Daymark settles the subscription. The operation key must be reused for safe retries; never settle a renewal based only on an unverified filter result.

Lifecycle action payloads use the subscription array as the first argument. User-specific actions include the user ID as the final argument where applicable. `daymark_subscription_reviewed` receives `(subscription, decision, reason)` and `daymark_subscription_approved_for_payment` receives `(subscription, checkout_url)`. Add-ons should treat payload fields as read-only and use IDs to retrieve fresh state.

The new event payloads are emitted after local persistence. Checkout-created receives one context array with `user_id`, `plan_id`, `subscription`, and `checkout`. Invoice events receive `(subscription, invoice_id, status)` for `daymark_subscription_invoice_created`, `(subscription, invoice_id)` for paid/failed events, and payment recovery receives `(subscription, payment_status, invoice_id)`. Reconciliation receives `(subscription, action, details)`. Add-ons must be duplicate-safe and must store their own state outside Daymark tables.

The supported add-on SDK is server-side PHP only. Catalog writes, provider credentials, raw payment data, provider tokens and direct Daymark table access are not part of the contract. See `docs/sdk-reference.md` and `docs/sdk-examples/` for the complete extension reference.

## Development and verification

Development mode is selected from Subscriptions → Settings or locked with `DAYMARK_SUBSCRIPTIONS_ENVIRONMENT=dev`. It creates local test subscriptions and does not call Stripe or WooCommerce. Production mode is the default and calls the selected provider.

Run the dependency-free contract checks from the plugin directory:

```powershell
D:\WAMPSERVER\bin\php\php8.5.0\php.exe tests\test-contracts.php
```

The contract suite checks source contracts and fixture shapes. It does not replace a WordPress integration test, a browser checkout test, a Stripe Sandbox webhook test or a WooCommerce gateway renewal test.
