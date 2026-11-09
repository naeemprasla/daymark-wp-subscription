<?php
/**
 * Read-only local WordPress integration smoke test.
 * Run with: php tests/test-wp-integration.php
 */

declare( strict_types=1 );

$root = dirname( __DIR__, 4 );
require_once $root . DIRECTORY_SEPARATOR . 'wp-load.php';

$fail = static function ( string $message ): void {
	throw new RuntimeException( $message );
};

if ( ! class_exists( 'Daymark_Subscriptions_DB' ) || ! class_exists( 'Daymark_Subscriptions_REST' ) ) $fail( 'Daymark classes did not load through WordPress.' );

global $wpdb;
$tables = Daymark_Subscriptions_DB::tables();
foreach ( $tables as $name => $table ) {
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) $fail( "Missing Daymark table: $name" );
}

if ( get_option( 'daymark_subscriptions_db_version' ) !== Daymark_Subscriptions_DB::DB_VERSION ) $fail( 'WordPress migration version is not current.' );

$routes = rest_get_server()->get_routes();
foreach ( array( '/daymark/v1/me/account-access', '/daymark/v1/me/subscription/change/preview', '/daymark/v1/admin/readiness', '/daymark/v1/admin/jobs/(?P<job>[a-z_]+)/acknowledge' ) as $route ) if ( empty( $routes[ $route ] ) ) $fail( "Missing REST route: $route" );

foreach ( array( 'plans', 'checkout', 'account-access', 'subscription-management' ) as $slug ) if ( ! get_page_by_path( $slug ) ) $fail( "Missing default page: $slug" );

$public_plans = rest_do_request( new WP_REST_Request( 'GET', '/daymark/v1/plans' ) );
if ( ! $public_plans || 200 !== $public_plans->get_status() ) $fail( 'Public plans REST endpoint did not return HTTP 200.' );

$environment_exists = false !== get_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, false );
$environment_before = get_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, 'prod' );
update_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, 'prod', false );
$production_status = Daymark_Payment_Provider::checkout_status();
if ( 'prod' !== ( $production_status['environment'] ?? '' ) || ! empty( $production_status['testing_only'] ) ) $fail( 'Production checkout status incorrectly exposed development bypass.' );
if ( $environment_exists ) update_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION, $environment_before, false ); else delete_option( Daymark_Payment_Provider::ENVIRONMENT_OPTION );

wp_set_current_user( 0 );
$anonymous_create_request = new WP_REST_Request( 'POST', '/daymark/v1/plans' );
$anonymous_create_request->set_body_params( array( 'name' => 'Permission probe', 'price' => 1 ) );
$anonymous_create = rest_do_request( $anonymous_create_request );
if ( ! $anonymous_create || ! in_array( $anonymous_create->get_status(), array( 401, 403 ), true ) ) $fail( 'Anonymous plan creation was not rejected.' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( ! empty( $admins[0] ) ) {
	wp_set_current_user( (int) $admins[0] );
	$invalid_admin_create = rest_do_request( new WP_REST_Request( 'POST', '/daymark/v1/plans' ) );
	if ( ! $invalid_admin_create || $invalid_admin_create->get_status() < 400 || $invalid_admin_create->get_status() >= 500 ) $fail( 'Authenticated plan validation boundary did not reject incomplete input.' );
}
wp_set_current_user( 0 );

echo "Daymark WordPress integration smoke tests passed.\n";
