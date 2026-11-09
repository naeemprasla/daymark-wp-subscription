<?php
defined( 'ABSPATH' ) || exit;

$capabilities = class_exists( 'Daymark_Subscriptions_SDK' ) ? Daymark_Subscriptions_SDK::provider_capabilities() : array();
if ( 'stripe' === ( $capabilities['provider'] ?? '' ) ) {
	// Render Stripe-supported billing controls.
}
