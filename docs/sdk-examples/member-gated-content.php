<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Daymark_Subscriptions_SDK' ) && Daymark_Subscriptions_SDK::has_access( get_current_user_id(), array( 'professional' ) ) ) {
	// Render the add-on feature.
}
