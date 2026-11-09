<?php
/**
 * Plugin Name: Daymark Subscriptions
 * Plugin URI: https://example.com/daymark-subscriptions
 * Description: Subscription plans and user subscription lifecycle management for WordPress.
 * Version: 1.31.5
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Daymark
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: daymark-subscriptions
 */

defined( 'ABSPATH' ) || exit;

define( 'DAYMARK_SUBSCRIPTIONS_VERSION', '1.31.5' );
define( 'DAYMARK_SUBSCRIPTIONS_FILE', __FILE__ );
define( 'DAYMARK_SUBSCRIPTIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DAYMARK_SUBSCRIPTIONS_URL', plugin_dir_url( __FILE__ ) );

function daymark_subscriptions_has_shortcode( $tag ) {
	global $post;
	return $post instanceof WP_Post && has_shortcode( (string) $post->post_content, $tag );
}

function daymark_subscriptions_enqueue_frontend_assets() {
	$assets = array(
		'daymark_subscribe'                => 'daymark-subscriptions-ui',
		'daymark_profile'                  => '',
		'daymark_checkout'                 => 'daymark-checkout-ui',
		'daymark_subscription_management' => 'daymark-subscription-management',
		'daymark_member_settings'          => 'daymark-member-settings',
		'daymark_privacy_security'         => 'daymark-privacy-security',
		'daymark_email_change_confirmation' => '',
		'daymark_account_access'           => '',
	);
	$handles = array();
	foreach ( $assets as $shortcode => $handle ) {
		if ( daymark_subscriptions_has_shortcode( $shortcode ) ) $handles[ $shortcode ] = $handle;
	}
	$success = isset( $_GET['daymark_checkout'] ) && in_array( sanitize_key( wp_unslash( $_GET['daymark_checkout'] ) ), array( 'success', 'review' ), true );
	if ( $handles || is_author() || $success ) wp_enqueue_style( 'daymark-subscriptions-frontend', DAYMARK_SUBSCRIPTIONS_URL . 'assets/frontend.css', array(), DAYMARK_SUBSCRIPTIONS_VERSION );
	foreach ( $handles as $shortcode => $handle ) {
		if ( 'daymark_member_settings' === $shortcode ) wp_enqueue_script( 'daymark-member-settings', DAYMARK_SUBSCRIPTIONS_URL . 'assets/member-settings.js', array(), DAYMARK_SUBSCRIPTIONS_VERSION, true );
		elseif ( $handle ) wp_enqueue_script( $handle, DAYMARK_SUBSCRIPTIONS_URL . 'assets/' . $handle . '.js', array(), DAYMARK_SUBSCRIPTIONS_VERSION, true );
	}
	if ( daymark_subscriptions_has_shortcode( 'daymark_member_settings' ) && function_exists( 'wp_enqueue_editor' ) ) wp_enqueue_editor();
	if ( $success ) wp_enqueue_style( 'daymark-subscriptions-checkout-success', DAYMARK_SUBSCRIPTIONS_URL . 'assets/checkout-success.css', array(), DAYMARK_SUBSCRIPTIONS_VERSION );
}

function daymark_subscriptions_enqueue_access_revoked_assets() {
	wp_enqueue_style( 'daymark-subscriptions-access-revoked', DAYMARK_SUBSCRIPTIONS_URL . 'assets/access-revoked.css', array(), DAYMARK_SUBSCRIPTIONS_VERSION );
}

function daymark_subscriptions_enqueue_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'daymark-subscriptions' ) && ! in_array( $hook, array( 'post.php', 'post-new.php', 'nav-menus.php' ), true ) ) return;
	$dependencies = array();
	if ( false !== strpos( $hook, 'daymark-subscriptions-transactional' ) && function_exists( 'wp_enqueue_code_editor' ) ) { wp_enqueue_code_editor( array( 'type' => 'text/html' ) ); $dependencies[] = 'code-editor'; }
	wp_enqueue_style( 'daymark-subscriptions-admin', DAYMARK_SUBSCRIPTIONS_URL . 'assets/admin-dashboard.css', array(), DAYMARK_SUBSCRIPTIONS_VERSION );
	wp_enqueue_script( 'daymark-subscriptions-admin', DAYMARK_SUBSCRIPTIONS_URL . 'assets/admin-dashboard.js', $dependencies, DAYMARK_SUBSCRIPTIONS_VERSION, true );
}

add_action( 'wp_enqueue_scripts', 'daymark_subscriptions_enqueue_frontend_assets' );
add_action( 'daymark_subscriptions_enqueue_access_revoked_assets', 'daymark_subscriptions_enqueue_access_revoked_assets' );
add_action( 'admin_enqueue_scripts', 'daymark_subscriptions_enqueue_admin_assets', 9 );

require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-subscriptions-db.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-stripe.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-payment-provider.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-subscriptions-mailer.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-subscriptions-rest.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-subscriptions-admin.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-subscriptions-shortcode.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-subscription-access.php';
require_once DAYMARK_SUBSCRIPTIONS_DIR . 'includes/class-daymark-subscriptions-sdk.php';

register_activation_hook( __FILE__, array( 'Daymark_Subscriptions_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Daymark_Subscriptions_DB', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Daymark_Subscriptions_DB::maybe_upgrade();
		add_action( 'daymark_subscriptions_check_renewals', array( 'Daymark_Subscriptions_DB', 'run_renewal_job' ) );
		add_action( 'daymark_subscriptions_reconcile', array( 'Daymark_Subscriptions_DB', 'run_reconciliation_job' ) );
		new Daymark_Subscriptions_REST();
		new Daymark_Subscriptions_Admin();
		new Daymark_Payment_Provider();
		new Daymark_Subscriptions_Mailer();
		new Daymark_Subscriptions_Shortcode();
		new Daymark_Subscription_Access();
		do_action( 'daymark_subscriptions_loaded', Daymark_Subscriptions_SDK::contract() );
	}
);
