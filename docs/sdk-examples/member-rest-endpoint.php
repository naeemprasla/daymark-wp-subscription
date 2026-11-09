<?php
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'my-addon/v1', '/member/status', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => function () {
			$allowed = Daymark_Subscriptions_SDK::authorize_member();
			return is_wp_error( $allowed ) ? $allowed : array( 'active' => Daymark_Subscriptions_SDK::is_active() );
		},
		'permission_callback' => function () {
			return is_user_logged_in();
		},
	) );
} );
