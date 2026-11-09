<?php
/**
 * Fixture add-on used by the SDK contract tests. It owns no Daymark data.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'daymark_subscriptions_loaded', function ( $contract ) {
	if ( ! class_exists( 'Daymark_Subscriptions_SDK' ) || ! Daymark_Subscriptions_SDK::is_available( '1.3.0' ) ) {
		return;
	}
	$GLOBALS['daymark_sdk_fixture_contract'] = $contract;
} );

add_action( 'daymark_subscription_activated', function ( $subscription ) {
	$GLOBALS['daymark_sdk_fixture_activation'] = absint( $subscription['id'] ?? 0 );
}, 10, 1 );
