<?php
/**
 * Isolated local signed-webhook integration test using checked-in Stripe fixtures.
 * Run with: php tests/test-wp-webhooks.php
 */

declare( strict_types=1 );

$root = dirname( __DIR__, 4 );
require_once $root . DIRECTORY_SEPARATOR . 'wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$fail = static function ( string $message ): void {
	throw new RuntimeException( $message );
};
$assert = static function ( $condition, string $message ) use ( $fail ): void {
	if ( ! $condition ) $fail( $message );
};

global $wpdb;
$tables = Daymark_Subscriptions_DB::tables();
$suffix = strtolower( substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 ) );
$email = 'daymark-webhook-' . $suffix . '@example.test';
$secret_option = 'daymark_stripe_' . Daymark_Stripe::mode() . '_webhook_secret';
$old_secret_exists = false !== get_option( $secret_option, false );
$old_secret = get_option( $secret_option, '' );
$created_user_id = 0;
$created_plan_id = 0;
$created_subscription_id = 0;
$event_ids = array( 'evt_test_invoice_paid_' . $suffix, 'evt_test_invoice_failed_' . $suffix );

$restore = static function () use (&$created_user_id, &$created_plan_id, &$created_subscription_id, &$event_ids, &$old_secret_exists, &$old_secret, $secret_option, $tables, $wpdb): void {
	foreach ( $event_ids as $event_id ) $wpdb->delete( $tables['events'], array( 'event_id' => $event_id ), array( '%s' ) );
	if ( $created_subscription_id ) {
		$wpdb->delete( $tables['audit_logs'], array( 'object_type' => 'subscription', 'object_id' => $created_subscription_id ), array( '%s', '%d' ) );
		$wpdb->delete( $tables['invoices'], array( 'subscription_id' => $created_subscription_id ), array( '%d' ) );
		$wpdb->delete( $tables['subscriptions'], array( 'id' => $created_subscription_id ), array( '%d' ) );
	}
	if ( $created_plan_id ) $wpdb->delete( $tables['plans'], array( 'id' => $created_plan_id ), array( '%d' ) );
	if ( $created_user_id ) wp_delete_user( $created_user_id );
	if ( $old_secret_exists ) update_option( $secret_option, $old_secret, false ); else delete_option( $secret_option );
};

add_filter( 'pre_wp_mail', static function () { return true; } );
$payload_paid = (string) file_get_contents( __DIR__ . '/fixtures/stripe-invoice-payment-succeeded.json' );
$payload_failed = (string) file_get_contents( __DIR__ . '/fixtures/stripe-invoice-payment-failed.json' );
$paid = json_decode( $payload_paid, true ); $failed = json_decode( $payload_failed, true );

try {
	if ( defined( 'DAYMARK_STRIPE_' . strtoupper( Daymark_Stripe::mode() ) . '_WEBHOOK_SECRET' ) ) $fail( 'The webhook test requires an option-backed local webhook secret.' );
	$secret = 'local-webhook-secret-' . $suffix;
	update_option( $secret_option, $secret, false );
	$user_id = wp_insert_user( array( 'user_login' => 'daymark-webhook-' . $suffix, 'user_pass' => wp_generate_password( 32, true, true ), 'user_email' => $email, 'display_name' => 'Daymark Webhook Test', 'role' => 'subscriber' ) );
	$assert( ! is_wp_error( $user_id ), 'Temporary webhook test user could not be created.' );
	$created_user_id = absint( $user_id );
	$plan = Daymark_Subscriptions_DB::create_plan( array( 'slug' => 'daymark-webhook-' . $suffix, 'name' => 'Webhook Test ' . $suffix, 'description' => 'Temporary webhook test plan.', 'price' => 29, 'intro_price' => 0, 'intro_cycles' => 0, 'currency' => 'USD', 'interval_unit' => 'month', 'trial_days' => 0, 'approval_mode' => 'direct', 'sort_order' => 999, 'featured' => 0, 'benefits' => array(), 'catalog_visibility' => 'hidden', 'cta_label' => '', 'active' => 1, 'created_at' => current_time( 'mysql', true ) ) );
	$assert( $plan, 'Temporary webhook test plan could not be created.' );
	$created_plan_id = absint( $plan['id'] );
	$now = current_time( 'mysql', true ); $period_end = gmdate( 'Y-m-d H:i:s', time() + MONTH_IN_SECONDS );
	$created = Daymark_Subscriptions_DB::create_subscription_once( array( 'user_id' => $created_user_id, 'plan_id' => $created_plan_id, 'status' => 'active', 'started_at' => $now, 'current_period_start' => $now, 'current_period_end' => $period_end, 'cancel_at_period_end' => 0, 'canceled_at' => null, 'paused_at' => null, 'paused_until' => null, 'pending_plan_id' => null, 'change_effective_at' => null, 'last_payment_status' => 'paid', 'failure_count' => 0, 'provider_customer_id' => 'cus_test_' . $suffix, 'provider_subscription_id' => 'sub_test_daymark', 'checkout_session_id' => '', 'idempotency_key' => 'webhook-test-' . $suffix, 'created_at' => $now ) );
	$assert( $created && ! empty( $created['created'] ), 'Temporary webhook test subscription could not be created.' );
	$created_subscription_id = absint( $created['subscription']['id'] );

	$sign = static function ( string $body ) use ( $secret ): string { $timestamp = time(); return 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ); };
	$paid['id'] = $event_ids[0]; $payload_paid = wp_json_encode( $paid );
	$result = Daymark_Stripe::handle_webhook( $payload_paid, $sign( $payload_paid ) );
	$assert( true === $result, 'Signed paid webhook was not accepted.' );
	$duplicate = Daymark_Stripe::handle_webhook( $payload_paid, $sign( $payload_paid ) );
	$assert( true === $duplicate, 'Duplicate webhook was not safely ignored.' );
	$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( $created_subscription_id );
	$assert( 'active' === $subscription['status'] && 'in_test_renewal' === $subscription['last_invoice_id'], 'Paid renewal webhook did not update local state.' );

	$failed['id'] = $event_ids[1]; $payload_failed = wp_json_encode( $failed );
	$result = Daymark_Stripe::handle_webhook( $payload_failed, $sign( $payload_failed ) );
	$assert( true === $result, 'Signed failed webhook was not accepted.' );
	$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( $created_subscription_id );
	$assert( 'past_due' === $subscription['status'] && 'in_test_failed' === $subscription['last_invoice_id'], 'Failed renewal webhook did not enter recovery state.' );

	$restore();
	echo "Daymark WordPress webhook integration test passed.\n";
} catch ( Throwable $error ) {
	$restore();
	throw $error;
}
