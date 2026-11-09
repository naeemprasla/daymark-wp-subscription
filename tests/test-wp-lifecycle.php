<?php
/**
 * Isolated local lifecycle integration test. It mutates only temporary local data.
 * Run with: php tests/test-wp-lifecycle.php
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

if ( defined( 'DAYMARK_SUBSCRIPTIONS_ENVIRONMENT' ) && 'dev' !== DAYMARK_SUBSCRIPTIONS_ENVIRONMENT ) $fail( 'The lifecycle test requires a non-locked development environment.' );

global $wpdb;
$tables = Daymark_Subscriptions_DB::tables();
$suffix = strtolower( substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 ) );
$email = 'daymark-lifecycle-' . $suffix . '@example.test';
$slug_one = 'daymark-lifecycle-one-' . $suffix;
$slug_two = 'daymark-lifecycle-two-' . $suffix;
$created_user_id = 0;
$created_plan_ids = array();
$created_subscription_id = 0;
$original_environment_exists = false;
$original_environment = null;
$original_timing_exists = false;
$original_timing = null;

$restore = static function () use (&$created_user_id, &$created_plan_ids, &$created_subscription_id, &$original_environment_exists, &$original_environment, &$original_timing_exists, &$original_timing, $tables, $wpdb): void {
	if ( $created_subscription_id ) {
		$wpdb->delete( $tables['audit_logs'], array( 'object_type' => 'subscription', 'object_id' => $created_subscription_id ), array( '%s', '%d' ) );
		$wpdb->delete( $tables['operations'], array( 'subscription_id' => $created_subscription_id ), array( '%d' ) );
		$wpdb->delete( $tables['invoices'], array( 'subscription_id' => $created_subscription_id ), array( '%d' ) );
		$wpdb->delete( $tables['subscriptions'], array( 'id' => $created_subscription_id ), array( '%d' ) );
	}
	foreach ( $created_plan_ids as $plan_id ) $wpdb->delete( $tables['plans'], array( 'id' => $plan_id ), array( '%d' ) );
	if ( $created_user_id ) wp_delete_user( $created_user_id );
	if ( $original_environment_exists ) update_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, $original_environment, false ); else delete_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION );
	if ( $original_timing_exists ) update_option( 'daymark_subscriptions_plan_change_timing', $original_timing, false ); else delete_option( 'daymark_subscriptions_plan_change_timing' );
};

add_filter( 'pre_wp_mail', static function () { return true; } );
$original_environment_exists = false !== get_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, false );
$original_environment = get_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, 'prod' );
$original_timing_exists = false !== get_option( 'daymark_subscriptions_plan_change_timing', false );
$original_timing = get_option( 'daymark_subscriptions_plan_change_timing', 'period_end' );

try {
	update_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, 'dev', false );
	update_option( 'daymark_subscriptions_plan_change_timing', 'immediate', false );
	wp_set_current_user( 0 );

	$plan_one = Daymark_Subscriptions_DB::create_plan( array( 'slug' => $slug_one, 'name' => 'Lifecycle Test One ' . $suffix, 'description' => 'Temporary lifecycle test plan.', 'price' => 9.99, 'intro_price' => 0, 'intro_cycles' => 0, 'currency' => 'USD', 'interval_unit' => 'month', 'trial_days' => 0, 'approval_mode' => 'direct', 'sort_order' => 999, 'featured' => 0, 'benefits' => array( 'Lifecycle test' ), 'catalog_visibility' => 'hidden', 'cta_label' => '', 'active' => 1, 'created_at' => current_time( 'mysql', true ) ) );
	$plan_two = Daymark_Subscriptions_DB::create_plan( array( 'slug' => $slug_two, 'name' => 'Lifecycle Test Two ' . $suffix, 'description' => 'Temporary lifecycle test plan.', 'price' => 19.99, 'intro_price' => 0, 'intro_cycles' => 0, 'currency' => 'USD', 'interval_unit' => 'month', 'trial_days' => 0, 'approval_mode' => 'direct', 'sort_order' => 1000, 'featured' => 0, 'benefits' => array( 'Lifecycle test' ), 'catalog_visibility' => 'hidden', 'cta_label' => '', 'active' => 1, 'created_at' => current_time( 'mysql', true ) ) );
	$assert( $plan_one && $plan_two, 'Temporary lifecycle plans could not be created.' );
	$created_plan_ids = array( absint( $plan_one['id'] ), absint( $plan_two['id'] ) );

	$request = new WP_REST_Request( 'POST', '/daymark/v1/me/checkout' );
	$request->set_header( 'X-Daymark-Idempotency-Key', 'lifecycle-' . $suffix );
	$request->set_body_params( array( 'plan_id' => absint( $plan_one['id'] ), 'customer_name' => 'Daymark Lifecycle Test', 'customer_email' => $email, 'customer_phone' => '+1 555 0100' ) );
	$response = rest_do_request( $request );
	$assert( 201 === $response->get_status(), 'Guest development checkout did not return HTTP 201: ' . $response->get_status() );
	$payload = $response->get_data();
	$assert( ! empty( $payload['development'] ), 'Development checkout did not identify its bypass mode.' );
	$user = get_user_by( 'email', $email );
	$assert( $user, 'Guest checkout did not create the temporary account.' );
	$checkout_key = 'checkout:' . hash( 'sha256', 'email:' . strtolower( trim( $email ) ) . '|lifecycle-' . $suffix );
	$checkout = Daymark_Subscriptions_DB::get_checkout_by_idempotency( $checkout_key );
	$assert( $checkout && 'verified' === $checkout['status'], 'Checkout reservation was not finalized in Development mode.' );
	$created_user_id = absint( $user->ID );
	$subscription = Daymark_Subscriptions_DB::get_active_subscription( $created_user_id );
	$assert( $subscription && 'active' === $subscription['status'], 'Development checkout did not create an active subscription.' );
	$created_subscription_id = absint( $subscription['id'] );
	$assert( Daymark_Subscriptions_DB::get_user_invoices( $created_user_id, 10 ), 'Activation invoice was not recorded.' );
	$assert( Daymark_Subscription_Access::can_access( $created_user_id, array( $plan_one['slug'] ), array( 'active' ) ), 'Active subscription did not grant plan access.' );
	$assert( 'active' === get_user_meta( $created_user_id, 'daymark_account_state', true ), 'Development checkout did not mark the test account active.' );
	update_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, 'prod', false );
	Daymark_Subscriptions_DB::update_subscription( $created_subscription_id, $created_user_id, array( 'status' => 'pending_activation' ), array( '%s' ) );
	update_user_meta( $created_user_id, 'daymark_account_state', 'pending_activation' );
	$assert( ! Daymark_Subscription_Access::can_access( $created_user_id, array( $plan_one['slug'] ), array( 'active' ) ), 'Pending activation account was granted protected access.' );
	do_action( 'after_password_reset', get_userdata( $created_user_id ), 'temporary-test-password' );
	$assert( 'active' === get_user_meta( $created_user_id, 'daymark_account_state', true ), 'Password claim did not activate the account.' );
	$assert( 'active' === ( Daymark_Subscriptions_DB::get_subscription_by_id( $created_subscription_id )['status'] ?? '' ), 'Password claim did not activate the pending subscription.' );
	update_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, 'dev', false );

	wp_set_current_user( $created_user_id );
	$pause = rest_do_request( new WP_REST_Request( 'POST', '/daymark/v1/me/subscription/pause' ) );
	$assert( 200 === $pause->get_status() && 'paused' === ( $pause->get_data()['status'] ?? '' ), 'Pause lifecycle action failed.' );
	$resume = rest_do_request( new WP_REST_Request( 'POST', '/daymark/v1/me/subscription/resume' ) );
	$assert( 200 === $resume->get_status() && 'active' === ( $resume->get_data()['status'] ?? '' ), 'Resume lifecycle action failed.' );
	$change = new WP_REST_Request( 'POST', '/daymark/v1/me/subscription/change' );
	$change->set_body_params( array( 'plan_id' => absint( $plan_two['id'] ) ) );
	$changed = rest_do_request( $change );
	$assert( 200 === $changed->get_status() && absint( $changed->get_data()['plan_id'] ?? 0 ) === absint( $plan_two['id'] ), 'Immediate plan change failed.' );
	$cancel = new WP_REST_Request( 'DELETE', '/daymark/v1/me/subscription' );
	$cancel->set_param( 'immediate', true );
	$cancel->set_param( 'reason', 'Lifecycle integration test cleanup.' );
	$cancelled = rest_do_request( $cancel );
	$assert( 200 === $cancelled->get_status() && 'canceled' === ( $cancelled->get_data()['status'] ?? '' ), 'Immediate cancellation failed.' );

	$restore();
	wp_set_current_user( 0 );
	echo "Daymark WordPress lifecycle integration test passed.\n";
} catch ( Throwable $error ) {
	$restore();
	wp_set_current_user( 0 );
	throw $error;
}
