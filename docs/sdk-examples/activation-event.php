<?php
defined( 'ABSPATH' ) || exit;

add_action( 'daymark_subscription_activated', function ( $subscription ) {
	$user_id = absint( $subscription['user_id'] ?? 0 );
	if ( ! $user_id ) {
		return;
	}
	// Store only add-on-owned provisioning data.
}, 10, 1 );
