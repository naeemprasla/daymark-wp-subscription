# Daymark Subscriptions Server SDK Reference

SDK contract: `1.3.1`  
Namespace: `Daymark_Subscriptions_SDK`  
Runtime: server-side PHP only

## Availability

```php
add_action( 'daymark_subscriptions_loaded', function ( $contract ) {
	if ( ! class_exists( 'Daymark_Subscriptions_SDK' ) || ! Daymark_Subscriptions_SDK::is_available( '1.3.1' ) ) {
		return;
	}
	if ( ! Daymark_Subscriptions_SDK::supports( 'subscription_events' ) ) {
		return;
	}
	// Register the add-on integration.
} );
```

`contract()` returns the SDK/plugin versions, supported capabilities, provider, environment, checkout readiness, checkout idempotency policy, bulk-action policy and renewal capability. The action runs after Daymark services have loaded.

Checkout idempotency is scoped to the authenticated user ID or normalized guest email. A replay with a different owner, plan, quantity or email returns `daymark_checkout_key_mismatch` with HTTP 409. When second-administrator approval is enabled, bulk revoke returns `daymark_bulk_second_approval_unsupported` with HTTP 409; use individual revoke plus `approve_operation()` instead.

## Capabilities

Supported capabilities are `catalog_read`, `subscription_read`, `invoice_read`, `member_read`, `member_settings`, `checkout`, `lifecycle_mutations`, `admin_subscription_operations`, `subscription_events`, `provider_capabilities`, `rest_authorization`, `checkout_owner_scoping`, and `bulk_action_policy`.

## Read operations

Use `list_plans`, `get_plan`, `get_subscription`, `get_active_subscription`, `get_pending_payment`, `get_pending_review`, `get_invoices`, `get_invoice`, `get_latest_invoice`, `get_member`, `get_member_settings`, and `get_member_timeline` for read-only data. Catalog writes are intentionally not part of the add-on SDK.

Subscription records use provider-neutral fields and preserve historical values in `price_snapshot`, `currency_snapshot`, and `interval_snapshot`. Add-ons must not read Daymark tables directly.

## Mutations

`checkout`, `pause_subscription`, `resume_subscription`, `cancel_subscription`, `change_plan`, and `cancel_pending_plan_change` use the native validated lifecycle path. Pass a stable idempotency key when retrying the same logical mutation.

Administrator methods require the matching Daymark capability: `manage_daymark_subscriptions`, `review_daymark_subscriptions`, or `manage_daymark_billing`.

## Authorization helpers

```php
$allowed = Daymark_Subscriptions_SDK::authorize_member( $user_id );
if ( is_wp_error( $allowed ) ) {
	return $allowed;
}

$allowed = Daymark_Subscriptions_SDK::authorize_admin( 'manage_daymark_subscriptions' );
```

These helpers return `true` or `WP_Error`. They do not replace the add-on's own route-level `permission_callback`.

## Provider boundaries

`provider`, `environment`, `is_development_mode`, `checkout_status`, and `provider_capabilities` are safe capability/readiness projections. Provider secrets, card data, payment tokens, and raw provider credentials are never returned.

## Events

Actions are emitted after the relevant local state has been persisted. Event consumers should treat payloads as read-only and use IDs to fetch fresh state. Event processing is protected by the native provider/event idempotency boundary.

Available lifecycle events include:

```text
daymark_subscription_checkout_created
daymark_subscription_payment_verified
daymark_subscription_activated
daymark_subscription_access_changed
daymark_subscription_invoice_created
daymark_subscription_invoice_paid
daymark_subscription_invoice_failed
daymark_subscription_payment_recovery_required
daymark_subscription_reconciliation_required
daymark_subscription_reconciled
daymark_subscription_expired
daymark_subscription_deleted
```

`daymark_subscription_checkout_created` receives one context array with `user_id`, `plan_id`, `subscription` (when one already exists), and `checkout` result fields. Other subscription lifecycle events receive the persisted subscription array first.

Example:

```php
add_action( 'daymark_subscription_activated', function ( $subscription ) {
	$user_id = absint( $subscription['user_id'] ?? 0 );
	if ( ! $user_id || ! class_exists( 'Daymark_Subscriptions_SDK' ) ) {
		return;
	}
	// Provision add-on-owned data for this member.
}, 10, 1 );
```

## REST integration

Add-ons must register their own namespace on `rest_api_init`, define a `permission_callback`, validate arguments, and call `authorize_member()` or `authorize_admin()` before returning Daymark data. Never register add-on routes inside `daymark/v1`.

## Error contract

SDK methods return arrays/scalars on success, `false` for an absent read record where documented, and `WP_Error` for invalid, unauthorized, unavailable, or reconciliation-required operations. Use `get_error_code()`, `get_error_message()`, and `get_error_data()`; do not parse translated messages.

## Compatibility

`1.2.1` and `1.3.0` methods remain available in `1.3.1`. The new contract metadata is additive. Future breaking changes require a new major SDK contract.
