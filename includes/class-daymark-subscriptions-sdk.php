<?php
defined( 'ABSPATH' ) || exit;

/**
 * Stable server-side facade for trusted WordPress add-ons.
 * Add-ons should use this class instead of reading Daymark tables or provider credentials directly.
 */
final class Daymark_Subscriptions_SDK {
	const VERSION = '1.3.1';
	const CAPABILITIES = array(
		'catalog_read',
		'subscription_read',
		'invoice_read',
		'member_read',
		'member_settings',
		'checkout',
		'lifecycle_mutations',
		'admin_subscription_operations',
		'subscription_events',
		'provider_capabilities',
		'rest_authorization',
		'checkout_owner_scoping',
		'bulk_action_policy',
	);

	public static function version() { return self::VERSION; }
	public static function is_available( $minimum_version = '1.0.0' ) { return version_compare( self::VERSION, (string) $minimum_version, '>=' ); }
	public static function supports( $capability ) { return in_array( sanitize_key( $capability ), self::CAPABILITIES, true ); }
	public static function contract() {
		return array(
			'sdk_version' => self::VERSION,
			'plugin_version' => defined( 'DAYMARK_SUBSCRIPTIONS_VERSION' ) ? DAYMARK_SUBSCRIPTIONS_VERSION : '',
			'capabilities' => self::CAPABILITIES,
			'provider' => self::provider(),
			'environment' => self::environment(),
			'checkout' => self::checkout_status(),
			'checkout_contract' => array( 'idempotency_scope' => 'owner', 'mismatch_status' => 409, 'reservation_status' => 'pending_checkout' ),
			'bulk_action_contract' => array( 'max_items' => 50, 'second_admin_revoke' => 'individual_only' ),
			'renewal_capability' => Daymark_Payment_Provider::renewal_capability(),
		);
	}

	public static function checkout_status() { return Daymark_Payment_Provider::checkout_status(); }
	public static function provider_capabilities() { return array( 'provider' => Daymark_Payment_Provider::provider(), 'environment' => Daymark_Payment_Provider::environment(), 'renewal_capability' => Daymark_Payment_Provider::renewal_capability(), 'woocommerce' => Daymark_Payment_Provider::woocommerce_capabilities() ); }

	public static function list_plans( $active_only = true ) {
		return Daymark_Subscriptions_DB::get_plans( (bool) $active_only );
	}

	public static function get_plan( $plan, $active_only = false ) {
		if ( is_numeric( $plan ) ) return Daymark_Subscriptions_DB::get_plan( absint( $plan ), (bool) $active_only );
		$plan = Daymark_Subscriptions_DB::get_plan_by_slug( sanitize_title( $plan ) );
		return $plan ? Daymark_Subscriptions_DB::get_plan( absint( $plan['id'] ), (bool) $active_only ) : false;
	}

	public static function get_subscription( $user_id = 0 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		return $user_id && self::can_read_user( $user_id, $requested_id ) ? Daymark_Subscriptions_DB::get_user_subscription( $user_id ) : false;
	}

	public static function get_active_subscription( $user_id = 0 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		return $user_id && self::can_read_user( $user_id, $requested_id ) ? Daymark_Subscriptions_DB::get_active_subscription( $user_id ) : false;
	}

	public static function get_invoices( $user_id = 0, $limit = 20 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		return $user_id && self::can_read_user( $user_id, $requested_id ) ? Daymark_Subscriptions_DB::get_user_invoices( $user_id, $limit ) : array();
	}

	public static function get_invoice( $invoice_id, $user_id = 0 ) {
		$invoice = Daymark_Subscriptions_DB::get_invoice( absint( $invoice_id ) );
		if ( ! $invoice || ! self::can_read_user( $invoice['user_id'], $user_id ) ) return false;
		return $invoice;
	}

	public static function get_latest_invoice( $user_id = 0 ) {
		$invoices = self::get_invoices( $user_id, 1 );
		return $invoices ? reset( $invoices ) : false;
	}

	public static function get_member_timeline( $user_id = 0, $limit = 50 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		return $user_id && self::can_read_user( $user_id, $requested_id ) ? Daymark_Subscriptions_DB::get_user_timeline( $user_id, $limit ) : array();
	}

	public static function get_pending_review( $user_id = 0 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		return $user_id && self::can_read_user( $user_id, $requested_id ) ? Daymark_Subscriptions_DB::get_pending_review_subscription( $user_id ) : false;
	}

	public static function get_pending_payment( $user_id = 0 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		return $user_id && self::can_read_user( $user_id, $requested_id ) ? Daymark_Subscriptions_DB::get_pending_payment_subscription( $user_id ) : false;
	}

	public static function is_active( $user_id = 0 ) { return self::has_status( $user_id, array( 'active' ) ); }
	public static function is_paused( $user_id = 0 ) { return self::has_status( $user_id, array( 'paused' ) ); }
	public static function is_past_due( $user_id = 0 ) { return self::has_status( $user_id, array( 'past_due' ) ); }
	public static function is_pending( $user_id = 0 ) { return self::has_status( $user_id, array( 'pending_review', 'payment_pending', 'pending_payment', 'pending_activation' ) ); }

	public static function days_until_renewal( $user_id = 0 ) {
		$subscription = self::get_active_subscription( $user_id );
		if ( ! $subscription || empty( $subscription['current_period_end'] ) ) return null;
		$seconds = strtotime( $subscription['current_period_end'] . ' UTC' ) - current_time( 'timestamp', true );
		return max( 0, (int) ceil( $seconds / DAY_IN_SECONDS ) );
	}

	public static function get_member( $user_id = 0 ) {
		$user_id = self::resolve_user_id( $user_id );
		if ( ! $user_id || ! self::can_read_user( $user_id, $user_id ) ) return false;
		$user = get_userdata( $user_id );
		return $user ? array( 'id' => (int) $user->ID, 'email' => $user->user_email, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'display_name' => $user->display_name, 'description' => $user->description, 'profile_picture_url' => (string) get_user_meta( $user_id, 'daymark_profile_picture_url', true ) ) : false;
	}

	public static function get_member_settings( $user_id = 0 ) { return self::member_request( 'GET', '/me/settings', array(), $user_id ); }
	public static function request_privacy_deletion( $user_id = 0 ) { return self::member_request( 'POST', '/me/privacy/delete-request', array(), $user_id ); }

	public static function update_member_settings( array $data, $user_id = 0 ) {
		$fields = array( 'phone', 'first_name', 'last_name', 'display_name', 'profile_description', 'product_updates', 'profile_visibility' );
		if ( array_diff( array_keys( $data ), $fields ) ) return new WP_Error( 'daymark_settings_field_invalid', __( 'The settings payload contains an unsupported field.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$allowed = array_intersect_key( $data, array_flip( $fields ) );
		foreach ( array( 'first_name', 'last_name', 'display_name' ) as $field ) if ( array_key_exists( $field, $allowed ) ) $allowed[ $field ] = substr( sanitize_text_field( (string) $allowed[ $field ] ), 0, 'display_name' === $field ? 120 : 80 );
		if ( array_key_exists( 'phone', $allowed ) ) { $allowed['phone'] = preg_replace( '/[^0-9+().\-\s]/', '', (string) $allowed['phone'] ); if ( strlen( $allowed['phone'] ) > 40 ) return new WP_Error( 'daymark_phone_invalid', __( 'Enter a valid phone number.', 'daymark-subscriptions' ), array( 'status' => 400 ) ); }
		if ( array_key_exists( 'profile_description', $allowed ) ) $allowed['profile_description'] = substr( wp_kses_post( (string) $allowed['profile_description'] ), 0, 5000 );
		if ( array_key_exists( 'product_updates', $allowed ) ) $allowed['product_updates'] = (bool) $allowed['product_updates'];
		if ( array_key_exists( 'profile_visibility', $allowed ) ) { $allowed['profile_visibility'] = sanitize_key( $allowed['profile_visibility'] ); if ( ! in_array( $allowed['profile_visibility'], array( 'public', 'private' ), true ) ) return new WP_Error( 'daymark_profile_visibility_invalid', __( 'Profile visibility is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) ); }
		return self::member_request( 'PATCH', '/me/settings', $allowed, $user_id );
	}

	public static function cancel_subscription( $user_id = 0, $reason = '', $immediate = null, $idempotency_key = '' ) {
		$params = array( 'reason' => substr( sanitize_textarea_field( $reason ), 0, 500 ) );
		if ( null !== $immediate ) $params['immediate'] = (bool) $immediate;
		return self::member_request( 'DELETE', '/me/subscription', $params, $user_id, $idempotency_key );
	}

	public static function pause_subscription( $user_id = 0, $idempotency_key = '' ) { return self::member_request( 'POST', '/me/subscription/pause', array(), $user_id, $idempotency_key ); }
	public static function resume_subscription( $user_id = 0, $idempotency_key = '' ) { return self::member_request( 'POST', '/me/subscription/resume', array(), $user_id, $idempotency_key ); }

	public static function change_plan( $plan_id, $effective = null, $user_id = 0, $idempotency_key = '' ) {
		$params = array( 'plan_id' => absint( $plan_id ) );
		if ( ! $params['plan_id'] ) return new WP_Error( 'daymark_plan_required', __( 'A valid plan is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( null !== $effective ) { $effective = sanitize_key( $effective ); if ( ! in_array( $effective, array( 'immediate', 'period_end' ), true ) ) return new WP_Error( 'daymark_plan_change_timing_invalid', __( 'Plan change timing is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) ); $params['effective'] = $effective; }
		return self::member_request( 'POST', '/me/subscription/change', $params, $user_id, $idempotency_key );
	}

	public static function preview_plan_change( $plan_id, $effective = null, $user_id = 0 ) {
		$params = array( 'plan_id' => absint( $plan_id ) ); if ( null !== $effective ) $params['effective'] = sanitize_key( $effective );
		if ( ! $params['plan_id'] ) return new WP_Error( 'daymark_plan_required', __( 'A valid plan is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		return self::member_request( 'POST', '/me/subscription/change/preview', $params, $user_id );
	}

	public static function cancel_pending_plan_change( $user_id = 0, $idempotency_key = '' ) { return self::member_request( 'POST', '/me/subscription/change/cancel', array(), $user_id, $idempotency_key ); }

	public static function get_billing_portal_url( $user_id = 0 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		if ( $requested_id && $requested_id !== get_current_user_id() && ! self::can_capability( 'manage_daymark_billing' ) ) return self::scope_error();
		if ( ! self::can_read_user( $user_id, $user_id ) ) return self::scope_error();
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $subscription || empty( $subscription['provider_customer_id'] ) ) return new WP_Error( 'daymark_billing_profile_missing', __( 'No provider billing profile is available yet.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		return Daymark_Payment_Provider::create_billing_portal_session( $subscription['provider_customer_id'], home_url( '/subscription-management/' ) );
	}

	public static function get_payment_recovery_url( $user_id = 0 ) {
		$requested_id = absint( $user_id ); $user_id = self::resolve_user_id( $user_id );
		if ( $requested_id && $requested_id !== get_current_user_id() && ! self::can_capability( 'manage_daymark_billing' ) ) return self::scope_error();
		if ( ! self::can_read_user( $user_id, $user_id ) ) return self::scope_error();
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $subscription || 'past_due' !== $subscription['status'] ) return new WP_Error( 'daymark_payment_recovery_not_due', __( 'Payment recovery is only available for a past-due subscription.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		return Daymark_Payment_Provider::create_payment_recovery_url( $subscription );
	}

	public static function approve_subscription( $subscription_id ) { return absint( $subscription_id ) ? self::admin_request( 'POST', '/subscriptions/' . absint( $subscription_id ) . '/review', array( 'decision' => 'approve' ), 'review_daymark_subscriptions' ) : new WP_Error( 'daymark_subscription_required', __( 'A valid subscription is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) ); }
	public static function reject_subscription( $subscription_id, $reason = '' ) { return absint( $subscription_id ) ? self::admin_request( 'POST', '/subscriptions/' . absint( $subscription_id ) . '/review', array( 'decision' => 'reject', 'reason' => substr( sanitize_textarea_field( $reason ), 0, 500 ) ), 'review_daymark_subscriptions' ) : new WP_Error( 'daymark_subscription_required', __( 'A valid subscription is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) ); }
	public static function list_pending_reviews( $limit = 50, $page = 1 ) { return self::list_subscribers( array( 'status' => 'pending_review', 'per_page' => $limit, 'page' => $page ) ); }

	public static function list_subscribers( array $args = array() ) {
		if ( ! self::can_manage() ) return self::scope_error();
		$status = sanitize_key( $args['status'] ?? '' ); $search = sanitize_text_field( $args['search'] ?? '' ); $limit = max( 1, min( 50, absint( $args['per_page'] ?? $args['limit'] ?? 50 ) ) ); $page = max( 1, absint( $args['page'] ?? 1 ) );
		return array( 'items' => Daymark_Subscriptions_DB::get_admin_subscriptions( $status, $search, $limit, $page ), 'total' => Daymark_Subscriptions_DB::get_admin_subscription_count( $status, $search ), 'page' => $page, 'per_page' => $limit );
	}

	public static function list_plan_subscribers( $plan_id ) {
		if ( ! absint( $plan_id ) ) return new WP_Error( 'daymark_plan_required', __( 'A valid plan is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( ! self::can_manage() ) return self::scope_error();
		return Daymark_Subscriptions_DB::get_plan_subscribers( absint( $plan_id ) );
	}

	public static function get_subscriber_detail( $subscription_id ) { return self::admin_request( 'GET', '/subscriptions/' . absint( $subscription_id ) . '/detail' ); }
	public static function change_subscriber_plan( $subscription_id, $plan_id, $reason = '' ) { return self::admin_request( 'POST', '/subscriptions/' . absint( $subscription_id ) . '/plan', array( 'plan_id' => absint( $plan_id ), 'reason' => substr( sanitize_textarea_field( $reason ), 0, 500 ) ) ); }
	public static function revoke_subscription( $subscription_id, $reason = '' ) { return self::admin_request( 'POST', '/subscriptions/' . absint( $subscription_id ) . '/revoke', array( 'reason' => substr( sanitize_textarea_field( $reason ), 0, 500 ) ) ); }
	public static function bulk_subscription_action( array $subscription_ids, $action, $plan_id = 0, $reason = '' ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $subscription_ids ) ) ) ); $action = sanitize_key( $action );
		if ( ! $ids || count( $ids ) > 50 ) return new WP_Error( 'daymark_bulk_limit', __( 'Select between one and 50 subscriptions.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( ! in_array( $action, array( 'revoke', 'change_plan' ), true ) ) return new WP_Error( 'daymark_bulk_action_invalid', __( 'The bulk action is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( 'change_plan' === $action && ! absint( $plan_id ) ) return new WP_Error( 'daymark_bulk_plan_required', __( 'A destination plan is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		return self::admin_request( 'POST', '/subscriptions/bulk', array( 'subscription_ids' => $ids, 'action' => $action, 'plan_id' => absint( $plan_id ), 'reason' => substr( sanitize_textarea_field( $reason ), 0, 500 ) ) );
	}

	public static function admin_billing_action( $subscription_id, $action, $amount = 0, $idempotency_key = '', $reason = '' ) {
		$action = sanitize_key( $action );
		if ( ! absint( $subscription_id ) || ! in_array( $action, array( 'refund', 'credit', 'void', 'uncollectible', 'resend', 'pay' ), true ) ) return new WP_Error( 'daymark_billing_action_invalid', __( 'The billing action is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( (float) $amount < 0 ) return new WP_Error( 'daymark_billing_amount_invalid', __( 'The billing amount is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		return self::admin_request( 'POST', '/subscriptions/' . absint( $subscription_id ) . '/billing-action', array( 'action' => $action, 'amount' => (float) $amount, 'reason' => substr( sanitize_textarea_field( $reason ), 0, 500 ), 'idempotency_key' => sanitize_text_field( $idempotency_key ?: wp_generate_uuid4() ) ), 'manage_daymark_billing' );
	}
	public static function approve_operation( $operation_id ) {
		return absint( $operation_id ) ? self::admin_request( 'POST', '/admin/operations/' . absint( $operation_id ) . '/approve' ) : new WP_Error( 'daymark_operation_required', __( 'A valid operation is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
	}

	public static function authorize_member( $user_id = 0 ) {
		$user_id = self::resolve_user_id( $user_id );
		return $user_id && self::can_read_user( $user_id, $user_id ) ? true : self::scope_error();
	}

	public static function authorize_admin( $capability = 'manage_daymark_subscriptions' ) {
		$capability = sanitize_key( $capability );
		if ( ! in_array( $capability, array( 'manage_daymark_subscriptions', 'manage_daymark_billing', 'review_daymark_subscriptions', 'manage_options' ), true ) ) return self::scope_error();
		return self::can_capability( $capability ) ? true : self::scope_error();
	}

	public static function error( $code, $message, $status = 400, array $data = array() ) {
		$code = sanitize_key( $code ); $data['status'] = absint( $status ) ?: 400;
		return new WP_Error( $code ?: 'daymark_sdk_error', (string) $message, $data );
	}

	private static function has_status( $user_id, array $statuses ) {
		$subscription = self::get_subscription( $user_id );
		return $subscription && in_array( $subscription['status'], $statuses, true );
	}

	private static function resolve_user_id( $user_id ) { return absint( $user_id ?: get_current_user_id() ); }

	private static function can_read_user( $target_id, $requested_id = 0 ) {
		$target_id = absint( $target_id ); $requested_id = absint( $requested_id );
		if ( ! $target_id ) return false;
		if ( $requested_id && $requested_id !== $target_id && ! self::can_manage() ) return false;
		return $target_id === get_current_user_id() || self::can_manage();
	}

	private static function can_capability( $capability ) { return ( function_exists( 'is_super_admin' ) && is_super_admin() ) || current_user_can( $capability ); }

	private static function can_manage() { return self::can_capability( 'manage_options' ) || self::can_capability( 'manage_daymark_subscriptions' ); }

	private static function scope_error() { return new WP_Error( 'daymark_sdk_forbidden', __( 'The add-on is not allowed to access this member.', 'daymark-subscriptions' ), array( 'status' => 403 ) ); }

	private static function member_request( $method, $path, array $params, $user_id = 0, $idempotency_key = '' ) {
		$user_id = self::resolve_user_id( $user_id );
		if ( ! self::can_read_user( $user_id, $user_id ) ) return self::scope_error();
		$previous = get_current_user_id();
		if ( $previous !== $user_id ) wp_set_current_user( $user_id );
		$request = new WP_REST_Request( $method, '/daymark/v1' . $path );
		foreach ( $params as $key => $value ) $request->set_param( $key, $value );
		if ( $idempotency_key ) $request->set_header( 'X-Daymark-Idempotency-Key', sanitize_text_field( $idempotency_key ) );
		$rest = new Daymark_Subscriptions_REST();
		$callbacks = array( 'GET /me/settings' => 'settings', 'PATCH /me/settings' => 'update_settings', 'POST /me/privacy/delete-request' => 'privacy_delete_request', 'DELETE /me/subscription' => 'cancel', 'POST /me/subscription/pause' => 'pause', 'POST /me/subscription/resume' => 'resume', 'POST /me/subscription/change' => 'change_plan', 'POST /me/subscription/change/preview' => 'preview_plan_change', 'POST /me/subscription/change/cancel' => 'cancel_plan_change' );
		$key = $method . ' ' . $path; $callback = $callbacks[ $key ] ?? '';
		try {
			$result = $callback && method_exists( $rest, $callback ) ? $rest->{$callback}( $request ) : new WP_Error( 'daymark_sdk_route_missing', __( 'The requested SDK operation is unavailable.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		} finally {
			if ( $previous !== $user_id ) wp_set_current_user( $previous );
		}
		return $result instanceof WP_REST_Response ? $result->get_data() : $result;
	}

	private static function admin_request( $method, $path, array $params = array(), $capability = 'manage_daymark_subscriptions' ) {
		if ( ! self::can_capability( $capability ) ) return self::scope_error();
		$request = new WP_REST_Request( $method, '/daymark/v1' . $path );
		foreach ( $params as $key => $value ) $request->set_param( $key, $value );
		$rest = new Daymark_Subscriptions_REST();
		if ( false !== strpos( $path, '/operations/' ) && false !== strpos( $path, '/approve' ) ) $callback = 'approve_operation';
		elseif ( false !== strpos( $path, '/review' ) ) $callback = 'admin_review_subscription';
		elseif ( false !== strpos( $path, '/detail' ) ) $callback = 'admin_subscription_detail';
		elseif ( false !== strpos( $path, '/plan' ) ) $callback = 'admin_change_plan';
		elseif ( false !== strpos( $path, '/revoke' ) ) $callback = 'admin_revoke_subscription';
		elseif ( '/subscriptions/bulk' === $path ) $callback = 'admin_bulk_action';
		elseif ( false !== strpos( $path, '/billing-action' ) ) $callback = 'admin_billing_action';
		elseif ( false !== strpos( $path, '/admin/jobs/' ) && false !== strpos( $path, '/acknowledge' ) ) $callback = 'acknowledge_job';
		elseif ( false !== strpos( $path, '/admin/jobs/' ) ) $callback = 'retry_job';
		else $callback = '';
		if ( preg_match( '#/subscriptions/(\d+)/#', $path, $matches ) ) $request->set_param( 'id', absint( $matches[1] ) );
		if ( preg_match( '#/admin/operations/(\d+)/#', $path, $matches ) ) $request->set_param( 'id', absint( $matches[1] ) );
		if ( preg_match( '#/admin/jobs/([a-z_]+)/retry#', $path, $matches ) ) $request->set_param( 'job', sanitize_key( $matches[1] ) );
		$result = $callback && method_exists( $rest, $callback ) ? $rest->{$callback}( $request ) : new WP_Error( 'daymark_sdk_route_missing', __( 'The requested SDK operation is unavailable.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		return $result instanceof WP_REST_Response ? $result->get_data() : $result;
	}

	public static function has_access( $user_id, $plans, $statuses = array( 'active' ) ) {
		$user_id = absint( $user_id );
		return $user_id && self::can_read_user( $user_id, $user_id ) ? Daymark_Subscription_Access::can_access( $user_id, $plans, $statuses ) : false;
	}

	public static function provider() { return Daymark_Payment_Provider::provider(); }

	public static function environment() { return Daymark_Payment_Provider::environment(); }

	public static function is_development_mode() { return Daymark_Payment_Provider::development_mode(); }

	public static function provider_health() { return Daymark_Payment_Provider::test_connection(); }
	public static function admin_provider_health() { return self::can_manage() ? Daymark_Payment_Provider::test_connection() : self::scope_error(); }
	public static function retry_job( $job_key ) {
		$job_key = sanitize_key( $job_key );
		if ( ! in_array( $job_key, array( 'renewals', 'reconciliation' ), true ) ) return new WP_Error( 'daymark_job_invalid', __( 'The scheduled job is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		return self::admin_request( 'POST', '/admin/jobs/' . $job_key . '/retry' );
	}
	public static function acknowledge_job( $job_key ) {
		$job_key = sanitize_key( $job_key );
		if ( ! in_array( $job_key, array( 'renewals', 'reconciliation' ), true ) ) return new WP_Error( 'daymark_job_invalid', __( 'The scheduled job is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		return self::admin_request( 'POST', '/admin/jobs/' . $job_key . '/acknowledge' );
	}

	/**
	 * Uses the same validated public checkout path as the native REST endpoint.
	 * The caller must retain the idempotency key when retrying the same checkout.
	 */
	public static function checkout( $plan_id, array $customer = array(), $idempotency_key = '', $quantity = 1 ) {
		$request = new WP_REST_Request( 'POST', '/daymark/v1/me/checkout' );
		$request->set_param( 'plan_id', absint( $plan_id ) );
		$request->set_param( 'quantity', max( 1, min( 1000, absint( $quantity ) ) ) );
		$request->set_param( 'customer_name', sanitize_text_field( $customer['customer_name'] ?? $customer['name'] ?? '' ) );
		$request->set_param( 'customer_email', sanitize_email( $customer['customer_email'] ?? $customer['email'] ?? '' ) );
		$request->set_param( 'customer_phone', sanitize_text_field( $customer['customer_phone'] ?? $customer['phone'] ?? '' ) );
		$request->set_header( 'X-Daymark-Idempotency-Key', sanitize_text_field( $idempotency_key ?: wp_generate_uuid4() ) );
		$result = ( new Daymark_Subscriptions_REST() )->checkout( $request );
		return $result instanceof WP_REST_Response ? $result->get_data() : $result;
	}

	public static function request_account_access( $email ) {
		$request = new WP_REST_Request( 'POST', '/daymark/v1/me/account-access' ); $request->set_param( 'email', sanitize_email( $email ) );
		$result = ( new Daymark_Subscriptions_REST() )->request_account_access( $request ); return $result instanceof WP_REST_Response ? $result->get_data() : $result;
	}
}
