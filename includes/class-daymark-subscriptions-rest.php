<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Subscriptions_REST {
	const ROUTE_NAMESPACE = 'daymark/v1';
	private static $registered = false;

	public function __construct() {
		if ( ! self::$registered ) { add_action( 'rest_api_init', array( $this, 'register_routes' ) ); self::$registered = true; }
	}

	public function register_routes() {
		register_rest_route( self::ROUTE_NAMESPACE, '/plans', array( array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'plans' ), 'permission_callback' => '__return_true', 'args' => array( 'page' => array( 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'per_page' => array( 'required' => false, 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint' ), 'order' => array( 'required' => false, 'type' => 'string', 'default' => 'asc', 'enum' => array( 'asc', 'desc' ), 'sanitize_callback' => 'sanitize_key' ) ) ), array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_plan' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $this->plan_args() ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/plans/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_plan' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => $this->plan_args( false ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/plans/(?P<id>\d+)', array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_plan' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/plans/(?P<id>\d+)/subscribers', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'plan_subscribers' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/subscriptions/(?P<id>\d+)/plan', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'admin_change_plan' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'plan_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'reason' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 500; } ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/subscriptions/(?P<id>\d+)/revoke', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'admin_revoke_subscription' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'reason' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( trim( $value ) ) > 0 && strlen( $value ) <= 500; } ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/subscriptions/(?P<id>\d+)/detail', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'admin_subscription_detail' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/subscriptions/(?P<id>\d+)/review', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'admin_review_subscription' ), 'permission_callback' => array( $this, 'can_review' ), 'args' => array( 'decision' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'approve', 'reject' ), 'sanitize_callback' => 'sanitize_key' ), 'reason' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 500; } ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/subscriptions/bulk', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'admin_bulk_action' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'subscription_ids' => array( 'required' => true, 'type' => 'array' ), 'action' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'revoke', 'change_plan' ) ), 'plan_id' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'reason' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 500; } ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/admin/provider-health', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'provider_health' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/admin/readiness', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'readiness' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/admin/operations', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'admin_operations' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'status' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/admin/operations/(?P<id>\d+)/retry', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'retry_operation' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/admin/operations/(?P<id>\d+)/approve', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'approve_operation' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/admin/jobs/(?P<job>[a-z_]+)/retry', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'retry_job' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/admin/jobs/(?P<job>[a-z_]+)/acknowledge', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'acknowledge_job' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'my_subscription' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'subscribe' ), 'permission_callback' => array( $this, 'can_read' ), 'args' => array( 'plan_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'validate_callback' => 'is_numeric', 'sanitize_callback' => 'absint' ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/checkout', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'checkout' ), 'permission_callback' => '__return_true', 'args' => array( 'plan_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'quantity' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 1, 'sanitize_callback' => 'absint' ), 'customer_name' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'customer_email' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ), 'customer_phone' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/account-access', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'request_account_access' ), 'permission_callback' => '__return_true', 'args' => array( 'email' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription', array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'cancel' ), 'permission_callback' => array( $this, 'can_read' ), 'args' => array( 'immediate' => array( 'required' => false, 'type' => 'boolean', 'default' => false ), 'reason' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 500; } ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/settings', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'settings' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/settings', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_settings' ), 'permission_callback' => array( $this, 'can_read' ), 'args' => $this->settings_args() ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/email-change', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'request_email_change' ), 'permission_callback' => array( $this, 'can_read' ), 'args' => array( 'email' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/email-change/confirm', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'confirm_email_change' ), 'permission_callback' => '__return_true', 'args' => array( 'user_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'token' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/privacy/export', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'privacy_export' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/privacy/delete-request', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'privacy_delete_request' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/security/logout-sessions', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'logout_sessions' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/profile-picture', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'upload_profile_picture' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription/pause', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'pause' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription/resume', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'resume' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription/change', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'change_plan' ), 'permission_callback' => array( $this, 'can_read' ), 'args' => array( 'plan_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'effective' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'immediate', 'period_end' ) ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription/change/preview', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'preview_plan_change' ), 'permission_callback' => array( $this, 'can_read' ), 'args' => array( 'plan_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'effective' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'immediate', 'period_end' ) ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription/change/cancel', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'cancel_plan_change' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/billing-portal', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'billing_portal' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/me/subscription/payment-recovery', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'payment_recovery' ), 'permission_callback' => array( $this, 'can_read' ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/subscriptions/(?P<id>\d+)/billing-action', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'admin_billing_action' ), 'permission_callback' => array( $this, 'can_billing' ), 'args' => array( 'action' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'refund', 'credit', 'void', 'uncollectible', 'resend', 'pay' ) ), 'amount' => array( 'required' => false, 'type' => 'number', 'minimum' => 0 ), 'reason' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 500; } ), 'idempotency_key' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( self::ROUTE_NAMESPACE, '/stripe/webhook', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'stripe_webhook' ), 'permission_callback' => '__return_true' ) );
	}

	public function can_read() { return is_user_logged_in() ? true : new WP_Error( 'daymark_auth_required', __( 'Authentication is required.', 'daymark-subscriptions' ), array( 'status' => 401 ) ); }
	public function can_manage() { return ( function_exists( 'is_super_admin' ) && is_super_admin() ) || current_user_can( 'manage_options' ) || current_user_can( 'manage_daymark_subscriptions' ); }
	public function can_billing() { return ( function_exists( 'is_super_admin' ) && is_super_admin() ) || current_user_can( 'manage_options' ) || current_user_can( 'manage_daymark_billing' ) || current_user_can( 'manage_daymark_subscriptions' ); }
	public function can_review() { return ( function_exists( 'is_super_admin' ) && is_super_admin() ) || current_user_can( 'manage_options' ) || current_user_can( 'review_daymark_subscriptions' ) || current_user_can( 'manage_daymark_subscriptions' ); }
	public function plans( WP_REST_Request $request ) {
		$plans = Daymark_Subscriptions_DB::get_plans();
		usort( $plans, static function ( $left, $right ) { $sort = (int) ( $left['sort_order'] ?? 0 ) <=> (int) ( $right['sort_order'] ?? 0 ); return $sort ?: strcasecmp( (string) $left['name'], (string) $right['name'] ); } );
		if ( 'desc' === $request->get_param( 'order' ) ) $plans = array_reverse( $plans );
		$total = count( $plans ); $per_page = max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 50 ) ) ); $page = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$response = new WP_REST_Response( array_slice( $plans, ( $page - 1 ) * $per_page, $per_page ) ); $response->header( 'X-WP-Total', $total ); $response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );
		return $response;
	}
	public function provider_health() { $result = Daymark_Payment_Provider::test_connection(); if ( is_wp_error( $result ) ) return $result; $result['renewal_capability'] = Daymark_Payment_Provider::renewal_capability(); $result['webhook'] = 'stripe' === Daymark_Payment_Provider::provider() ? Daymark_Subscriptions_DB::get_webhook_health() : array( 'last_received' => '', 'last_verified' => '', 'last_processed' => '', 'last_failed' => '' ); return rest_ensure_response( $result ); }
	public function readiness() { return rest_ensure_response( array( 'checkout' => Daymark_Payment_Provider::checkout_status(), 'provider' => Daymark_Payment_Provider::provider(), 'environment' => Daymark_Payment_Provider::environment(), 'renewal_capability' => Daymark_Payment_Provider::renewal_capability(), 'webhook' => 'stripe' === Daymark_Payment_Provider::provider() ? Daymark_Subscriptions_DB::get_webhook_health() : array(), 'jobs' => Daymark_Subscriptions_DB::get_jobs() ) ); }
	public function request_account_access( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'account_access', 3 ); if ( is_wp_error( $limited ) ) return $limited;
		$email = sanitize_email( $request->get_param( 'email' ) ); if ( ! is_email( $email ) ) return new WP_Error( 'daymark_account_access_email_invalid', __( 'Enter a valid email address.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$user = get_user_by( 'email', $email );
		if ( $user && ( Daymark_Subscriptions_DB::get_user_subscription( $user->ID ) || get_user_meta( $user->ID, 'daymark_checkout_created_at', true ) ) ) ( new Daymark_Subscriptions_Mailer() )->send_account_access( $user->ID );
		return rest_ensure_response( array( 'sent' => true, 'message' => __( 'If a matching subscription account exists, an account access link has been sent.', 'daymark-subscriptions' ) ) );
	}
	public function admin_operations( WP_REST_Request $request ) { return rest_ensure_response( Daymark_Subscriptions_DB::get_operations( sanitize_key( $request->get_param( 'status' ) ), 100 ) ); }
	private function require_second_approval( $key, $action, $subscription_id, array $payload, WP_REST_Request $request ) {
		if ( ! Daymark_Subscriptions_Admin::require_second_admin() ) return false;
		$existing = Daymark_Subscriptions_DB::get_operation_by_key( $key );
		$second_approval = (bool) $request->get_param( 'second_approval' );
		if ( $second_approval ) {
			if ( ! $existing || 'awaiting_second_approval' !== $existing['status'] ) return new WP_Error( 'daymark_second_approval_invalid', __( 'This action is not waiting for a second administrator.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
			$stored_payload = json_decode( (string) $existing['request_payload'], true );
			$stored_payload = is_array( $stored_payload ) ? $stored_payload : array();
			$requested_by = absint( $stored_payload['requested_by'] ?? 0 );
			if ( ! $requested_by || $requested_by === get_current_user_id() ) return new WP_Error( 'daymark_second_approval_same_admin', __( 'A different administrator must approve this action.', 'daymark-subscriptions' ), array( 'status' => 403 ) );
			return false;
		}
		if ( $existing && 'awaiting_second_approval' === $existing['status'] ) return new WP_Error( 'daymark_second_approval_required', __( 'A second administrator must approve this action before it can continue.', 'daymark-subscriptions' ), array( 'status' => 202, 'operation_id' => absint( $existing['id'] ) ) );
		$payload['requested_by'] = get_current_user_id();
		$operation = Daymark_Subscriptions_DB::create_operation( $key, $action, $subscription_id, $payload );
		if ( ! $operation ) return new WP_Error( 'daymark_second_approval_create_failed', __( 'The approval request could not be created.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		Daymark_Subscriptions_DB::update_operation( $operation['id'], 'awaiting_second_approval', array( 'last_error' => 'Waiting for approval by a different administrator.' ) );
		return new WP_Error( 'daymark_second_approval_required', __( 'A second administrator must approve this action before it can continue.', 'daymark-subscriptions' ), array( 'status' => 202, 'operation_id' => absint( $operation['id'] ) ) );
	}
	public function approve_operation( WP_REST_Request $request ) {
		$operation = Daymark_Subscriptions_DB::get_operation_by_id( absint( $request['id'] ) );
		if ( ! $operation || 'awaiting_second_approval' !== $operation['status'] ) return new WP_Error( 'daymark_second_approval_invalid', __( 'This approval request is no longer waiting for a second administrator.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$payload = json_decode( (string) $operation['request_payload'], true ); $payload = is_array( $payload ) ? $payload : array();
		if ( absint( $payload['requested_by'] ?? 0 ) === get_current_user_id() ) return new WP_Error( 'daymark_second_approval_same_admin', __( 'A different administrator must approve this action.', 'daymark-subscriptions' ), array( 'status' => 403 ) );
		$child = new WP_REST_Request( 'POST', '/daymark/v1/subscriptions/' . absint( $operation['subscription_id'] ) ); $child->set_param( 'id', absint( $operation['subscription_id'] ) ); $child->set_param( 'second_approval', true );
		if ( 'admin_approve_review' === $operation['action'] || 'admin_reject_review' === $operation['action'] ) { $child->set_param( 'decision', 'admin_approve_review' === $operation['action'] ? 'approve' : 'reject' ); $child->set_param( 'reason', $payload['reason'] ?? '' ); $result = $this->admin_review_subscription( $child ); }
		elseif ( 'admin_revoke' === $operation['action'] ) { $child->set_param( 'reason', $payload['reason'] ?? '' ); $result = $this->admin_revoke_subscription( $child ); }
		elseif ( 0 === strpos( $operation['action'], 'admin_billing_' ) ) { $child->set_param( 'action', substr( $operation['action'], strlen( 'admin_billing_' ) ) ); $child->set_param( 'amount', $payload['amount'] ?? 0 ); $child->set_param( 'reason', $payload['reason'] ?? '' ); $child->set_param( 'idempotency_key', 'daymark-second-approval-' . absint( $operation['id'] ) ); $result = $this->admin_billing_action( $child ); }
		else return new WP_Error( 'daymark_second_approval_unsupported', __( 'This operation does not support second approval.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( is_wp_error( $result ) ) return $result;
		Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => (int) $operation['attempts'] + 1, 'local_response' => wp_json_encode( array( 'approved_by' => get_current_user_id() ) ) ) );
		Daymark_Subscriptions_DB::record_audit_log( 'second_admin_approved', 'operation', $operation['id'], array( 'approved_by' => get_current_user_id(), 'original_actor' => absint( $payload['requested_by'] ?? 0 ) ) );
		return rest_ensure_response( array( 'approved' => true, 'operation_id' => absint( $operation['id'] ), 'result' => $result ) );
	}
	public function retry_job( WP_REST_Request $request ) {
		$job = Daymark_Subscriptions_DB::retry_job( sanitize_key( $request['job'] ) );
		if ( ! $job ) return new WP_Error( 'daymark_job_retry_invalid', __( 'The scheduled job could not be retried.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		return rest_ensure_response( $job );
	}
	public function acknowledge_job( WP_REST_Request $request ) {
		$job = Daymark_Subscriptions_DB::acknowledge_job( sanitize_key( $request['job'] ) );
		if ( ! $job ) return new WP_Error( 'daymark_job_acknowledge_invalid', __( 'Only a successfully resolved job can be acknowledged.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		Daymark_Subscriptions_DB::record_audit_log( 'scheduled_job_acknowledged', 'job', 0, array( 'job' => sanitize_key( $request['job'] ) ) );
		return rest_ensure_response( $job );
	}
	public function retry_operation( WP_REST_Request $request ) {
		$operation = Daymark_Subscriptions_DB::get_operations( '', 200 );
		$match = false;
		foreach ( $operation as $row ) if ( (int) $row['id'] === absint( $request['id'] ) ) { $match = $row; break; }
		if ( ! $match ) return new WP_Error( 'daymark_operation_not_found', __( 'Operation not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( ! $match['subscription_id'] ) return new WP_Error( 'daymark_operation_retry_unsupported', __( 'This operation cannot be automatically retried. Verify it with the provider first.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( $match['subscription_id'] );
		if ( ! $subscription || empty( $subscription['provider_subscription_id'] ) ) return new WP_Error( 'daymark_operation_subscription_missing', __( 'The subscription provider identity is missing.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( 'woocommerce' === $match['provider'] && 'renewal' === $match['action'] ) { $result = Daymark_Payment_Provider::retry_woocommerce_renewal( $subscription, $match ); return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'retried' => true, 'operation_id' => (int) $match['id'], 'result' => $result ) ); }
		if ( 'stripe' !== $match['provider'] ) return new WP_Error( 'daymark_operation_retry_unsupported', __( 'This operation cannot be automatically retried. Verify it with the provider first.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$result = Daymark_Stripe::reconcile_subscription( $subscription['provider_subscription_id'] );
		if ( ! $result ) { Daymark_Subscriptions_DB::update_operation( $match['id'], 'dead_letter', array( 'attempts' => (int) $match['attempts'] + 1, 'last_error' => 'Provider reconciliation failed.', 'next_attempt_at' => null ) ); return new WP_Error( 'daymark_operation_retry_failed', __( 'Provider reconciliation failed. The operation remains unresolved.', 'daymark-subscriptions' ), array( 'status' => 502 ) ); }
		Daymark_Subscriptions_DB::update_operation( $match['id'], 'succeeded', array( 'attempts' => (int) $match['attempts'] + 1, 'last_error' => null, 'next_attempt_at' => null ) );
		return rest_ensure_response( array( 'reconciled' => true, 'operation_id' => (int) $match['id'] ) );
	}
	public function request_email_change( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'email_change', 3 ); if ( is_wp_error( $limited ) ) return $limited;
		$user = wp_get_current_user(); $email = sanitize_email( $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) return new WP_Error( 'daymark_email_invalid', __( 'Enter a valid email address.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( strtolower( $email ) === strtolower( $user->user_email ) ) return new WP_Error( 'daymark_email_unchanged', __( 'This is already your account email.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$existing = get_user_by( 'email', $email ); if ( $existing && (int) $existing->ID !== (int) $user->ID ) return new WP_Error( 'daymark_email_in_use', __( 'That email address is already in use.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$token = wp_generate_password( 48, false, false ); update_user_meta( $user->ID, 'daymark_email_change_hash', hash( 'sha256', $token ) ); update_user_meta( $user->ID, 'daymark_email_change_target', $email ); update_user_meta( $user->ID, 'daymark_email_change_expires', time() + DAY_IN_SECONDS );
		$url = add_query_arg( array( 'user_id' => $user->ID, 'token' => rawurlencode( $token ) ), home_url( '/email-confirmation/' ) );
		if ( ! wp_mail( $email, __( 'Confirm your new email address', 'daymark-subscriptions' ), sprintf( __( "Confirm this email address for your Daymark account by opening the link below.\n\n%s\n\nThis link expires in 24 hours.", 'daymark-subscriptions' ), esc_url_raw( $url ) ) ) ) return new WP_Error( 'daymark_email_change_mail_failed', __( 'The confirmation email could not be sent. Try again later.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		return rest_ensure_response( array( 'pending_email' => $email, 'message' => __( 'A confirmation link was sent to the new email address.', 'daymark-subscriptions' ) ) );
	}
	public function confirm_email_change( WP_REST_Request $request ) {
			$limited = $this->rate_limit( 'email_change_confirm', 5 ); if ( is_wp_error( $limited ) ) return $limited;
		$user = get_user_by( 'id', absint( $request['user_id'] ) ); $token = sanitize_text_field( $request['token'] );
		$hash = $user ? (string) get_user_meta( $user->ID, 'daymark_email_change_hash', true ) : ''; $expires = $user ? absint( get_user_meta( $user->ID, 'daymark_email_change_expires', true ) ) : 0; $target = $user ? sanitize_email( get_user_meta( $user->ID, 'daymark_email_change_target', true ) ) : '';
		if ( ! $user || ! $hash || ! $target || ! $expires || time() > $expires || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) return new WP_Error( 'daymark_email_change_invalid', __( 'This email confirmation link is invalid or expired.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$existing = get_user_by( 'email', $target ); if ( $existing && (int) $existing->ID !== (int) $user->ID ) return new WP_Error( 'daymark_email_in_use', __( 'That email address is already in use.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$updated = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $target ) ); if ( is_wp_error( $updated ) ) return new WP_Error( 'daymark_email_change_failed', __( 'The account email could not be updated.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		delete_user_meta( $user->ID, 'daymark_email_change_hash' ); delete_user_meta( $user->ID, 'daymark_email_change_target' ); delete_user_meta( $user->ID, 'daymark_email_change_expires' );
		return rest_ensure_response( array( 'changed' => true, 'email' => $target, 'message' => __( 'Your account email has been updated. You can now sign in with it.', 'daymark-subscriptions' ) ) );
	}
	public function plan_subscribers( WP_REST_Request $request ) {
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $request['id'] ) );
		if ( ! $plan ) return new WP_Error( 'daymark_plan_not_found', __( 'Plan not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_plan_subscribers( $plan['id'] ) );
	}

	public function admin_subscription_detail( WP_REST_Request $request ) {
		$detail = Daymark_Subscriptions_DB::get_admin_subscription_detail( absint( $request['id'] ) );
		return $detail ? rest_ensure_response( $detail ) : new WP_Error( 'daymark_subscription_not_found', __( 'Subscription not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
	}

	public function admin_review_subscription( WP_REST_Request $request ) {
		if ( ! $this->can_review() ) return new WP_Error( 'daymark_review_forbidden', __( 'You are not allowed to review subscriptions.', 'daymark-subscriptions' ), array( 'status' => 403 ) );
		$id = absint( $request['id'] ); $decision = sanitize_key( $request->get_param( 'decision' ) ); $subscription = Daymark_Subscriptions_DB::get_subscription_by_id( $id );
		if ( ! $subscription ) return new WP_Error( 'daymark_subscription_not_found', __( 'Subscription not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( 'pending_review' !== $subscription['status'] ) return new WP_Error( 'daymark_review_invalid_state', __( 'Only pending review subscriptions can be reviewed.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) ) return new WP_Error( 'daymark_review_decision_invalid', __( 'Choose approve or reject.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$reason = sanitize_textarea_field( $request->get_param( 'reason' ) );
		$approval = $this->require_second_approval( 'daymark-review-' . $id . '-' . $decision, 'admin_' . $decision . '_review', $id, array( 'decision' => $decision, 'reason' => $reason ), $request );
		if ( is_wp_error( $approval ) ) return $approval;
		$operation = Daymark_Subscriptions_DB::create_operation( 'daymark-review-' . $id . '-' . $decision, 'admin_' . $decision . '_review', $id, array( 'decision' => $decision, 'reason' => $reason ) );
		if ( $operation && 'succeeded' === $operation['status'] ) return rest_ensure_response( array( 'decision' => $decision, 'subscription' => Daymark_Subscriptions_DB::get_subscription_by_id( $id ), 'checkout_url' => '', 'payment_required' => false ) );
		$checkout_url = ''; $saved = false;
		if ( 'reject' === $decision ) {
			$provider_result = 0 === strpos( (string) $subscription['provider_subscription_id'], 'review_request:' ) ? true : Daymark_Payment_Provider::sync_cancel( $subscription['provider_subscription_id'], true, 'daymark-review-reject-' . $id );
			if ( is_wp_error( $provider_result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $provider_result->get_error_message() ) ); return $provider_result; }
			$saved = Daymark_Subscriptions_DB::update_subscription( $id, $subscription['user_id'], array( 'status' => 'canceled', 'canceled_at' => current_time( 'mysql', true ) ), array( '%s', '%s' ) );
		} else {
			$user = get_user_by( 'id', absint( $subscription['user_id'] ) ); $plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ), true );
			if ( ! $user || ! $plan ) return new WP_Error( 'daymark_review_dependency_missing', __( 'The subscriber or plan could not be found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
			if ( Daymark_Payment_Provider::development_mode() ) {
				$now = current_time( 'mysql', true ); $end = gmdate( 'Y-m-d H:i:s', strtotime( '+1 ' . $plan['interval_unit'], strtotime( $now . ' UTC' ) ) );
				$created = Daymark_Subscriptions_DB::activate_approved_subscription( $id, array( 'status' => 'active', 'current_period_start' => $now, 'current_period_end' => $end, 'provider_customer_id' => 'dev_customer:' . $user->ID, 'provider_subscription_id' => 'dev_subscription:' . $user->ID . ':' . $id, 'checkout_session_id' => 'dev_review:' . $id, 'idempotency_key' => 'dev-review:' . $id ) );
				$saved = (bool) $created;
			} else {
			$session = Daymark_Payment_Provider::create_checkout_session( $user, $plan, 'daymark-review-checkout-' . $id, $id, max( 1, absint( $subscription['quantity'] ?? 1 ) ) );
			if ( is_wp_error( $session ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $session->get_error_message() ) ); return $session; }
			$checkout_url = esc_url_raw( $session['url'] ?? '' ); $saved = ! empty( $session['id'] ) && Daymark_Subscriptions_DB::mark_pending_payment( $id, $subscription['user_id'], $session['id'] );
			$checkout = Daymark_Subscriptions_DB::get_latest_checkout( $subscription['user_id'], 'pending_review' );
			if ( $saved && $checkout ) Daymark_Subscriptions_DB::update_checkout( $checkout['id'], array( 'status' => 'payment_pending', 'provider' => $session['provider'] ?? Daymark_Payment_Provider::provider(), 'provider_session_id' => $session['id'], 'checkout_url' => $checkout_url ) );
			if ( $saved ) update_user_meta( absint( $subscription['user_id'] ), 'daymark_account_state', 'payment_pending' );
			}
		}
		if ( ! $saved ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider review state changed but local subscription state could not be saved.' ) ); return new WP_Error( 'daymark_review_save_failed', __( 'The subscription review decision could not be saved.', 'daymark-subscriptions' ), array( 'status' => 500, 'reconciliation_required' => true ) ); }
		$updated = Daymark_Subscriptions_DB::get_subscription_by_id( $id );
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1, 'provider_response' => wp_json_encode( array( 'completed' => true ) ), 'local_response' => wp_json_encode( array( 'status' => $updated['status'] ?? '' ) ) ) );
		Daymark_Subscriptions_DB::record_audit_log( 'admin_' . $decision . '_review', 'subscription', $id, array( 'user_id' => absint( $subscription['user_id'] ), 'reason' => $reason ) );
		do_action( 'daymark_subscription_reviewed', $updated, $decision, $reason );
		if ( 'approve' === $decision ) do_action( 'daymark_subscription_approved_for_payment', $updated, $checkout_url );
		return rest_ensure_response( array( 'decision' => $decision, 'subscription' => $updated, 'checkout_url' => $checkout_url, 'payment_required' => in_array( $updated['status'] ?? '', array( 'payment_pending', 'pending_payment' ), true ) ) );
	}

	private function plan_args( $required = true ) {
		return array(
			'slug' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_title', 'validate_callback' => function ( $value ) { return is_string( $value ) && preg_match( '/^[a-z0-9-]{2,80}$/', $value ); } ),
			'name' => array( 'required' => $required, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( trim( $value ) ) <= 120 && strlen( trim( $value ) ) > 0; } ),
			'description' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ),
			'refund_policy' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'wp_kses_post', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 5000; } ),
			'price' => array( 'required' => $required, 'type' => 'number', 'minimum' => 0, 'sanitize_callback' => static function ( $value ) { return (float) $value; } ),
			'intro_price' => array( 'required' => false, 'type' => 'number', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => static function ( $value ) { return (float) $value; } ),
			'intro_cycles' => array( 'required' => false, 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 12, 'sanitize_callback' => 'absint' ),
			'currency' => array( 'required' => false, 'type' => 'string', 'default' => 'USD', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && 3 === strlen( $value ) && ctype_alpha( $value ); } ),
			'interval_unit' => array( 'required' => false, 'type' => 'string', 'default' => 'month', 'enum' => array( 'day', 'month', 'year' ) ),
			'trial_days' => array( 'required' => false, 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 365, 'sanitize_callback' => 'absint' ),
			'approval_mode' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'direct', 'review' ), 'sanitize_callback' => 'sanitize_key' ),
			'pause_enabled' => array( 'required' => false, 'type' => 'boolean', 'default' => true ),
			'cancellation_mode' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'inherit', 'period_end', 'immediate' ), 'default' => 'inherit', 'sanitize_callback' => 'sanitize_key' ),
			'sort_order' => array( 'required' => false, 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			'featured' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			'benefits' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
			'catalog_visibility' => array( 'required' => false, 'type' => 'string', 'default' => 'visible', 'enum' => array( 'visible', 'hidden' ), 'sanitize_callback' => 'sanitize_key' ),
			'cta_label' => array( 'required' => false, 'type' => 'string', 'default' => '', 'maximum' => 80, 'sanitize_callback' => 'sanitize_text_field' ),
			'active' => array( 'required' => false, 'type' => 'boolean', 'default' => true ),
			'setup_fee' => array( 'required' => false, 'type' => 'number', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => static function ( $value ) { return (float) $value; } ),
			'minimum_commitment_cycles' => array( 'required' => false, 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 60, 'sanitize_callback' => 'absint' ),
			'allow_promotion_codes' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			'quantity_enabled' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			'quantity_min' => array( 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 1000, 'sanitize_callback' => 'absint' ),
			'quantity_max' => array( 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 1000, 'sanitize_callback' => 'absint' ),
			'tax_behavior' => array( 'required' => false, 'type' => 'string', 'default' => 'automatic', 'enum' => array( 'automatic', 'inclusive', 'exclusive' ), 'sanitize_callback' => 'sanitize_key' ),
		);
	}

	private function settings_args() {
		return array(
			'phone' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 40; } ),
			'first_name' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 80; } ),
			'last_name' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 80; } ),
			'display_name' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( trim( $value ) ) > 0 && strlen( $value ) <= 120; } ),
			'profile_description' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post', 'validate_callback' => function ( $value ) { return is_string( $value ) && strlen( $value ) <= 5000; } ),
			'product_updates' => array( 'required' => false, 'type' => 'boolean' ),
			'profile_visibility' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'public', 'private' ), 'sanitize_callback' => 'sanitize_key' ),
		);
	}

	public function create_plan( WP_REST_Request $request ) {
		$data = $request->get_params();
			$data['slug'] = $this->unique_plan_slug( $data['slug'] ?? $data['name'] ?? 'plan' );
		if ( ! isset( $data['approval_mode'] ) ) $data['approval_mode'] = Daymark_Subscriptions_Admin::default_approval_mode();
		if ( Daymark_Subscriptions_DB::get_plan_by_slug( $data['slug'] ) ) return new WP_Error( 'daymark_plan_exists', __( 'A plan with this slug already exists.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$intro_price = (float) ( $data['intro_price'] ?? 0 ); $intro_cycles = absint( $data['intro_cycles'] ?? 0 ); $interval = $data['interval_unit'] ?? 'month';
		if ( $intro_cycles && ( $intro_price >= (float) $data['price'] || ! in_array( $interval, array( 'month', 'year' ), true ) ) ) return new WP_Error( 'daymark_intro_pricing_invalid', __( 'Introductory pricing requires a lower price and monthly or yearly billing.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$quantity_enabled = ! empty( $data['quantity_enabled'] ); $quantity_min = max( 1, min( 1000, absint( $data['quantity_min'] ?? 1 ) ) ); $quantity_max = max( $quantity_min, min( 1000, absint( $data['quantity_max'] ?? $quantity_min ) ) );
		$plan = Daymark_Subscriptions_DB::create_plan( array( 'slug' => $data['slug'], 'name' => $data['name'], 'description' => $data['description'] ?? '', 'price' => $data['price'], 'intro_price' => $intro_cycles ? $intro_price : 0, 'intro_cycles' => $intro_cycles, 'currency' => strtoupper( $data['currency'] ?? 'USD' ), 'interval_unit' => $interval, 'trial_days' => absint( $data['trial_days'] ?? 0 ), 'approval_mode' => in_array( $data['approval_mode'] ?? 'direct', array( 'direct', 'review' ), true ) ? $data['approval_mode'] : 'direct', 'pause_enabled' => array_key_exists( 'pause_enabled', $data ) ? ( ! empty( $data['pause_enabled'] ) ? 1 : 0 ) : 1, 'cancellation_mode' => in_array( $data['cancellation_mode'] ?? 'inherit', array( 'inherit', 'period_end', 'immediate' ), true ) ? $data['cancellation_mode'] : 'inherit', 'sort_order' => absint( $data['sort_order'] ?? 0 ), 'featured' => ! empty( $data['featured'] ) ? 1 : 0, 'benefits' => $this->clean_benefits( $data['benefits'] ?? '' ), 'catalog_visibility' => in_array( $data['catalog_visibility'] ?? 'visible', array( 'visible', 'hidden' ), true ) ? $data['catalog_visibility'] : 'visible', 'cta_label' => substr( sanitize_text_field( $data['cta_label'] ?? '' ), 0, 80 ), 'stripe_price_id' => null, 'woocommerce_product_id' => null, 'active' => ! empty( $data['active'] ) ? 1 : 0, 'setup_fee' => max( 0, (float) ( $data['setup_fee'] ?? 0 ) ), 'minimum_commitment_cycles' => absint( $data['minimum_commitment_cycles'] ?? 0 ), 'allow_promotion_codes' => ! empty( $data['allow_promotion_codes'] ) ? 1 : 0, 'quantity_enabled' => $quantity_enabled ? 1 : 0, 'quantity_min' => $quantity_enabled ? $quantity_min : 1, 'quantity_max' => $quantity_enabled ? $quantity_max : 1, 'tax_behavior' => in_array( $data['tax_behavior'] ?? 'automatic', array( 'automatic', 'inclusive', 'exclusive' ), true ) ? $data['tax_behavior'] : 'automatic', 'created_at' => current_time( 'mysql', true ) ) );
		if ( ! $plan ) return new WP_Error( 'daymark_plan_create_failed', __( 'The plan could not be created.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		if ( array_key_exists( 'refund_policy', $data ) ) { Daymark_Subscriptions_DB::update_plan( $plan['id'], array( 'refund_policy' => wp_kses_post( $data['refund_policy'] ) ) ); $plan = Daymark_Subscriptions_DB::get_plan( $plan['id'] ); }
		return new WP_REST_Response( $plan, 201 );
	}

	public function update_plan( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		$existing = Daymark_Subscriptions_DB::get_plan( $id );
		if ( ! $existing ) return new WP_Error( 'daymark_plan_not_found', __( 'Plan not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$data = $request->get_params(); unset( $data['id'] );
		$allowed = array_intersect_key( $data, array_flip( array( 'name', 'description', 'refund_policy', 'price', 'intro_price', 'intro_cycles', 'currency', 'interval_unit', 'trial_days', 'approval_mode', 'pause_enabled', 'cancellation_mode', 'sort_order', 'featured', 'benefits', 'catalog_visibility', 'cta_label', 'active', 'setup_fee', 'minimum_commitment_cycles', 'allow_promotion_codes', 'quantity_enabled', 'quantity_min', 'quantity_max', 'tax_behavior' ) ) );
		if ( isset( $allowed['benefits'] ) ) $allowed['benefits'] = $this->clean_benefits( $allowed['benefits'] );
		if ( isset( $allowed['slug'] ) ) { $duplicate = Daymark_Subscriptions_DB::get_plan_by_slug( $allowed['slug'] ); if ( $duplicate && (int) $duplicate['id'] !== $id ) return new WP_Error( 'daymark_plan_exists', __( 'A plan with this slug already exists.', 'daymark-subscriptions' ), array( 'status' => 409 ) ); }
		if ( isset( $allowed['currency'] ) ) $allowed['currency'] = strtoupper( $allowed['currency'] );
		if ( isset( $allowed['catalog_visibility'] ) && ! in_array( $allowed['catalog_visibility'], array( 'visible', 'hidden' ), true ) ) unset( $allowed['catalog_visibility'] );
		if ( isset( $allowed['cta_label'] ) ) $allowed['cta_label'] = substr( sanitize_text_field( $allowed['cta_label'] ), 0, 80 );
		if ( isset( $allowed['tax_behavior'] ) && ! in_array( $allowed['tax_behavior'], array( 'automatic', 'inclusive', 'exclusive' ), true ) ) unset( $allowed['tax_behavior'] );
		if ( isset( $allowed['quantity_min'] ) ) $allowed['quantity_min'] = max( 1, min( 1000, absint( $allowed['quantity_min'] ) ) );
		if ( isset( $allowed['quantity_max'] ) ) $allowed['quantity_max'] = max( (int) ( $allowed['quantity_min'] ?? $existing['quantity_min'] ?? 1 ), min( 1000, absint( $allowed['quantity_max'] ) ) );
		if ( isset( $allowed['quantity_enabled'] ) && ! $allowed['quantity_enabled'] ) { $allowed['quantity_min'] = 1; $allowed['quantity_max'] = 1; }
		$next_interval = $allowed['interval_unit'] ?? $existing['interval_unit']; $next_price = isset( $allowed['price'] ) ? (float) $allowed['price'] : (float) $existing['price']; $next_intro_cycles = isset( $allowed['intro_cycles'] ) ? absint( $allowed['intro_cycles'] ) : absint( $existing['intro_cycles'] ?? 0 ); $next_intro_price = isset( $allowed['intro_price'] ) ? (float) $allowed['intro_price'] : (float) ( $existing['intro_price'] ?? 0 );
		if ( $next_intro_cycles && ( $next_intro_price >= $next_price || ! in_array( $next_interval, array( 'month', 'year' ), true ) ) ) return new WP_Error( 'daymark_intro_pricing_invalid', __( 'Introductory pricing requires a lower price and monthly or yearly billing.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( ! $next_intro_cycles ) { $allowed['intro_price'] = 0; $allowed['intro_cycles'] = 0; }
		$price_changed = isset( $allowed['price'] ) && (float) $allowed['price'] !== (float) $existing['price'];
		$currency_changed = isset( $allowed['currency'] ) && strtoupper( $allowed['currency'] ) !== strtoupper( $existing['currency'] );
		$interval_changed = isset( $allowed['interval_unit'] ) && $allowed['interval_unit'] !== $existing['interval_unit'];
		$intro_changed = ( isset( $allowed['intro_price'] ) && (float) $allowed['intro_price'] !== (float) ( $existing['intro_price'] ?? 0 ) ) || ( isset( $allowed['intro_cycles'] ) && absint( $allowed['intro_cycles'] ) !== absint( $existing['intro_cycles'] ?? 0 ) );
		$setup_changed = isset( $allowed['setup_fee'] ) && (float) $allowed['setup_fee'] !== (float) ( $existing['setup_fee'] ?? 0 );
		$tax_changed = isset( $allowed['tax_behavior'] ) && $allowed['tax_behavior'] !== ( $existing['tax_behavior'] ?? 'automatic' );
		if ( $price_changed || $currency_changed || $interval_changed || $tax_changed ) {
			if ( ! Daymark_Stripe::invalidate_price_mapping( $id, $existing['stripe_price_id'] ?? '' ) ) return new WP_Error( 'daymark_plan_mapping_failed', __( 'The old Stripe price mapping could not be retired.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
			$allowed['stripe_price_id'] = null;
		}
		if ( $intro_changed || $price_changed || $currency_changed || $interval_changed ) Daymark_Stripe::invalidate_intro_mapping( $id );
		if ( $setup_changed || $currency_changed || $tax_changed ) Daymark_Stripe::invalidate_setup_fee_mapping( $id );
		if ( isset( $allowed['approval_mode'] ) && ! in_array( $allowed['approval_mode'], array( 'direct', 'review' ), true ) ) unset( $allowed['approval_mode'] );
		if ( $allowed && ! Daymark_Subscriptions_DB::update_plan( $id, $allowed ) ) return new WP_Error( 'daymark_plan_update_failed', __( 'The plan could not be updated.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_plan( $id ) );
	}

	private function clean_benefits( $value ) {
		$values = is_array( $value ) ? $value : preg_split( '/\r?\n/', (string) $value ); $values = array_map( 'sanitize_text_field', $values ); $values = array_values( array_filter( array_map( 'trim', $values ) ) ); return array_slice( $values, 0, 8 );
	}

		private function unique_plan_slug( $value ) {
			$base = sanitize_title( $value ) ?: 'plan'; $base = substr( $base, 0, 80 ); $slug = $base; $suffix = 2;
			while ( Daymark_Subscriptions_DB::get_plan_by_slug( $slug ) ) { $suffix_text = '-' . $suffix++; $slug = substr( $base, 0, 80 - strlen( $suffix_text ) ) . $suffix_text; }
			return $slug;
		}

	public function delete_plan( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		$plan = Daymark_Subscriptions_DB::get_plan( $id );
		if ( ! $plan ) return new WP_Error( 'daymark_plan_not_found', __( 'Plan not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$usage = Daymark_Subscriptions_DB::plan_usage( $id );
		if ( $usage['subscriptions'] || $usage['invoices'] ) return new WP_Error( 'daymark_plan_in_use', __( 'This plan has subscription or invoice history. Archive it instead of deleting it.', 'daymark-subscriptions' ), array( 'status' => 409, 'plan_usage' => $usage, 'archive_required' => true ) );
		if ( ! Daymark_Subscriptions_DB::delete_plan( $id ) ) return new WP_Error( 'daymark_plan_delete_failed', __( 'The plan could not be deleted.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
	}

	public function admin_change_plan( WP_REST_Request $request ) {
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( absint( $request['id'] ) );
		$new_plan = Daymark_Subscriptions_DB::get_plan( absint( $request['plan_id'] ), true );
		if ( ! $subscription ) return new WP_Error( 'daymark_subscription_not_found', __( 'Subscription not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( ! $new_plan ) return new WP_Error( 'daymark_plan_not_found', __( 'Plan not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( ! in_array( $subscription['status'], array( 'active', 'paused', 'past_due' ), true ) ) return new WP_Error( 'daymark_subscription_change_invalid', __( 'Only current subscriptions can change plans.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( (int) $subscription['plan_id'] === (int) $new_plan['id'] && ! $subscription['pending_plan_id'] ) return new WP_Error( 'daymark_same_plan', __( 'The subscriber is already on this plan.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$effective = Daymark_Subscriptions_Admin::plan_change_timing();
		$reason = sanitize_textarea_field( $request->get_param( 'reason' ) );
		$operation_key = 'daymark-admin-plan-' . $subscription['id'] . '-' . $new_plan['id'] . '-' . $effective;
		$operation = Daymark_Subscriptions_DB::create_operation( $operation_key, 'admin_plan_change', $subscription['id'], array( 'new_plan_id' => absint( $new_plan['id'] ), 'effective' => $effective, 'reason' => $reason ) );
		if ( $operation && 'succeeded' === $operation['status'] ) return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $subscription['user_id'] ) ?: $subscription );
		if ( 'immediate' === $effective ) {
			$provider_result = Daymark_Payment_Provider::sync_plan( $subscription['provider_subscription_id'], $new_plan, 'daymark-admin-plan-' . $subscription['id'] . '-' . $new_plan['id'] );
			if ( is_wp_error( $provider_result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $provider_result->get_error_message() ) ); return $provider_result; }
			if ( ! $this->lock_current_subscription( $subscription['user_id'], $subscription ) ) return $this->stale_lifecycle_error( 'changed' );
			$commitment_end = absint( $new_plan['minimum_commitment_cycles'] ?? 0 ) ? gmdate( 'Y-m-d H:i:s', strtotime( '+' . absint( $new_plan['minimum_commitment_cycles'] ) . ' ' . $new_plan['interval_unit'], current_time( 'timestamp', true ) ) ) : null;
			$updated = Daymark_Subscriptions_DB::update_subscription( $subscription['id'], $subscription['user_id'], array( 'plan_id' => $new_plan['id'], 'pending_plan_id' => null, 'change_effective_at' => null, 'price_snapshot' => (float) $new_plan['price'], 'currency_snapshot' => strtoupper( $new_plan['currency'] ), 'interval_snapshot' => $new_plan['interval_unit'], 'commitment_ends_at' => $commitment_end ), array( '%d', '%d', '%s', '%f', '%s', '%s', '%s' ) );
		} else {
			if ( ! $this->lock_current_subscription( $subscription['user_id'], $subscription ) ) return $this->stale_lifecycle_error( 'changed' );
			$current = Daymark_Subscriptions_DB::get_active_subscription( $subscription['user_id'], true );
			$updated = Daymark_Subscriptions_DB::update_subscription( $current['id'], $subscription['user_id'], array( 'pending_plan_id' => $new_plan['id'], 'change_effective_at' => $current['current_period_end'] ), array( '%d', '%s' ) );
		}
			if ( ! $updated || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider plan change succeeded but local subscription state needs reconciliation.' ) ); $this->record_remote_sync_recovery( 'admin_plan_change', $subscription, array( 'plan_id' => absint( $new_plan['id'] ), 'effective' => $effective ) ); return new WP_Error( 'daymark_admin_plan_change_pending_sync', __( 'The provider changed, but the local subscription update needs reconciliation.', 'daymark-subscriptions' ), array( 'status' => 503, 'reconciliation_required' => true ) ); }
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1, 'provider_response' => wp_json_encode( array( 'completed' => true ) ), 'local_response' => wp_json_encode( array( 'updated' => true ) ) ) );
		Daymark_Subscriptions_DB::record_audit_log( 'admin_plan_change', 'subscription', $subscription['id'], array( 'new_plan_id' => absint( $new_plan['id'] ), 'effective' => $effective, 'reason' => $reason ) );
		do_action( 'daymark_subscription_plan_changed', Daymark_Subscriptions_DB::get_user_subscription( $subscription['user_id'] ) ?: $subscription, $new_plan, $effective, $subscription['user_id'] );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $subscription['user_id'] ) );
	}

	public function admin_revoke_subscription( WP_REST_Request $request ) {
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( absint( $request['id'] ) );
		if ( ! $subscription ) return new WP_Error( 'daymark_subscription_not_found', __( 'Subscription not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( ! in_array( $subscription['status'], array( 'active', 'paused', 'past_due' ), true ) ) return new WP_Error( 'daymark_subscription_revoke_invalid', __( 'Only current subscriptions can be revoked.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$reason = sanitize_textarea_field( $request->get_param( 'reason' ) );
		if ( '' === trim( $reason ) ) return new WP_Error( 'daymark_revoke_reason_required', __( 'A reason is required when revoking a subscription.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$approval = $this->require_second_approval( 'daymark-admin-revoke-' . $subscription['id'], 'admin_revoke', $subscription['id'], array( 'reason' => $reason ), $request );
		if ( is_wp_error( $approval ) ) return $approval;
		$operation = Daymark_Subscriptions_DB::create_operation( 'daymark-admin-revoke-' . $subscription['id'], 'admin_revoke', $subscription['id'], array( 'reason' => $reason ) );
		if ( $operation && 'succeeded' === $operation['status'] ) return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $subscription['user_id'] ) ?: $subscription );
		$provider_result = Daymark_Payment_Provider::sync_cancel( $subscription['provider_subscription_id'], true, 'daymark-admin-revoke-' . $subscription['id'] );
		if ( is_wp_error( $provider_result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $provider_result->get_error_message() ) ); return $provider_result; }
		if ( ! $this->lock_current_subscription( $subscription['user_id'], $subscription ) ) return $this->stale_lifecycle_error( 'revoked' );
		$updated = Daymark_Subscriptions_DB::update_subscription( $subscription['id'], $subscription['user_id'], array( 'status' => 'canceled', 'cancel_at_period_end' => 0, 'canceled_at' => current_time( 'mysql', true ) ), array( '%s', '%d', '%s' ) );
			if ( ! $updated || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider cancellation succeeded but local subscription state needs reconciliation.' ) ); $this->record_remote_sync_recovery( 'admin_revoke', $subscription, array( 'reason' => $reason ) ); return new WP_Error( 'daymark_admin_revoke_pending_sync', __( 'The provider changed, but the local subscription update needs reconciliation.', 'daymark-subscriptions' ), array( 'status' => 503, 'reconciliation_required' => true ) ); }
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1, 'provider_response' => wp_json_encode( array( 'completed' => true ) ), 'local_response' => wp_json_encode( array( 'updated' => true ) ) ) );
		Daymark_Subscriptions_DB::record_audit_log( 'admin_revoke', 'subscription', $subscription['id'], array( 'user_id' => absint( $subscription['user_id'] ), 'reason' => $reason ) );
		do_action( 'daymark_subscription_cancelled', Daymark_Subscriptions_DB::get_user_subscription( $subscription['user_id'] ) ?: $subscription, $subscription['user_id'] );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $subscription['user_id'] ) );
	}

	public function admin_bulk_action( WP_REST_Request $request ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'subscription_ids' ) ) ) ) );
		$action = sanitize_key( $request->get_param( 'action' ) ); $plan_id = absint( $request->get_param( 'plan_id' ) ); $reason = sanitize_textarea_field( $request->get_param( 'reason' ) );
		if ( ! $ids || count( $ids ) > 50 ) return new WP_Error( 'daymark_bulk_limit', __( 'Select between one and 50 subscriptions.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( 'revoke' === $action && Daymark_Subscriptions_Admin::require_second_admin() ) return new WP_Error( 'daymark_bulk_second_approval_unsupported', __( 'Bulk revokes are unavailable while second-administrator approval is enabled. Review each subscription individually.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( 'change_plan' === $action && ! $plan_id ) return new WP_Error( 'daymark_bulk_plan_required', __( 'Choose a destination plan for a bulk plan change.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( 'revoke' === $action && '' === trim( $reason ) ) return new WP_Error( 'daymark_revoke_reason_required', __( 'A reason is required when revoking subscriptions.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$results = array( 'updated' => 0, 'failed' => array() );
		foreach ( $ids as $id ) {
			$child = new WP_REST_Request( 'POST', '/subscriptions/' . $id . ( 'revoke' === $action ? '/revoke' : '/plan' ) );
			$child->set_param( 'id', $id );
			if ( 'change_plan' === $action ) $child->set_param( 'plan_id', $plan_id );
			$child->set_param( 'reason', $reason );
			$result = 'revoke' === $action ? $this->admin_revoke_subscription( $child ) : $this->admin_change_plan( $child );
			if ( is_wp_error( $result ) ) $results['failed'][ (string) $id ] = $result->get_error_message(); else $results['updated']++;
		}
		Daymark_Subscriptions_DB::record_audit_log( 'admin_bulk_' . $action, 'subscription_batch', 0, array( 'subscription_ids' => $ids, 'plan_id' => $plan_id, 'updated' => $results['updated'], 'failed' => count( $results['failed'] ) ) );
		return rest_ensure_response( $results );
	}

	public function my_subscription() { return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( get_current_user_id() ) ?: array() ); }

	public function billing_portal() {
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( get_current_user_id() );
		if ( ! $subscription || empty( $subscription['provider_customer_id'] ) ) return new WP_Error( 'daymark_billing_profile_missing', __( 'No provider billing profile is available yet.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$result = Daymark_Payment_Provider::create_billing_portal_session( $subscription['provider_customer_id'], home_url( '/subscription-management/' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function payment_recovery() {
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( get_current_user_id() );
		if ( ! $subscription || 'past_due' !== $subscription['status'] ) return new WP_Error( 'daymark_payment_recovery_not_due', __( 'Payment recovery is only available for a past-due subscription.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$result = Daymark_Payment_Provider::create_payment_recovery_url( $subscription );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function admin_billing_action( WP_REST_Request $request ) {
		if ( ! $this->can_billing() ) return new WP_Error( 'daymark_billing_forbidden', __( 'You are not allowed to perform billing actions.', 'daymark-subscriptions' ), array( 'status' => 403 ) );
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( absint( $request['id'] ) );
		if ( ! $subscription ) return new WP_Error( 'daymark_subscription_not_found', __( 'Subscription not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$action = sanitize_key( $request->get_param( 'action' ) );
		if ( ! in_array( $subscription['status'], array( 'active', 'paused', 'past_due' ), true ) ) return new WP_Error( 'daymark_billing_action_invalid_state', __( 'Billing actions are only available for current subscriptions.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$key = sanitize_text_field( $request->get_param( 'idempotency_key' ) ); $reason = sanitize_textarea_field( $request->get_param( 'reason' ) );
		if ( in_array( $action, array( 'refund', 'credit', 'void', 'uncollectible' ), true ) && '' === trim( $reason ) ) return new WP_Error( 'daymark_billing_reason_required', __( 'A reason is required for this billing action.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$approval = $this->require_second_approval( $key, 'admin_billing_' . $action, $subscription['id'], array( 'action' => $action, 'amount' => (float) $request->get_param( 'amount' ), 'reason' => $reason ), $request );
		if ( is_wp_error( $approval ) ) return $approval;
		$operation = Daymark_Subscriptions_DB::create_operation( $key, 'admin_billing_' . $action, $subscription['id'], array( 'action' => $action, 'amount' => (float) $request->get_param( 'amount' ), 'reason' => $reason ) );
		$result = Daymark_Payment_Provider::billing_action( $subscription, $action, (float) $request->get_param( 'amount' ), $key );
		if ( is_wp_error( $result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $result->get_error_message() ) ); return $result; }
		$local_saved = true; if ( in_array( $action, array( 'refund', 'credit', 'void', 'uncollectible' ), true ) && ! empty( $subscription['last_invoice_id'] ) ) $local_saved = Daymark_Subscriptions_DB::update_invoice_status( $subscription['last_invoice_id'], 'credit' === $action ? 'credited' : $action );
		if ( ! $local_saved ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider billing action succeeded but local invoice state could not be updated.' ) ); return new WP_Error( 'daymark_billing_local_sync_required', __( 'The provider action succeeded, but local invoice state needs reconciliation.', 'daymark-subscriptions' ), array( 'status' => 503, 'reconciliation_required' => true ) ); }
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1, 'provider_response' => wp_json_encode( array( 'completed' => true ) ), 'local_response' => wp_json_encode( array( 'invoice_updated' => $local_saved ) ) ) );
		Daymark_Subscriptions_DB::record_audit_log( 'admin_billing_' . $action, 'subscription', $subscription['id'], array( 'amount' => (float) $request->get_param( 'amount' ), 'reason' => $reason, 'provider' => Daymark_Payment_Provider::provider() ) );
		return rest_ensure_response( array( 'action' => $action, 'subscription_id' => absint( $subscription['id'] ), 'result' => $result ) );
	}

	public function settings() {
		$user = wp_get_current_user();
		$visibility = get_user_meta( $user->ID, 'daymark_profile_visibility', true );
		return rest_ensure_response( array( 'email' => $user->user_email, 'pending_email' => (string) get_user_meta( $user->ID, 'daymark_email_change_target', true ), 'phone' => (string) get_user_meta( $user->ID, 'billing_phone', true ), 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'display_name' => $user->display_name, 'profile_description' => $user->description, 'profile_picture_url' => (string) get_user_meta( $user->ID, 'daymark_profile_picture_url', true ), 'product_updates' => (bool) get_user_meta( $user->ID, 'daymark_product_updates', true ), 'profile_visibility' => in_array( $visibility, array( 'public', 'private' ), true ) ? $visibility : 'public', 'deletion_requested_at' => (string) get_user_meta( $user->ID, 'daymark_privacy_deletion_requested_at', true ), 'subscription' => Daymark_Subscriptions_DB::get_user_subscription( $user->ID ) ?: array(), 'invoices' => Daymark_Subscriptions_DB::get_user_invoices( $user->ID ), 'timeline' => Daymark_Subscriptions_DB::get_user_timeline( $user->ID ) ) );
	}

	public function privacy_export() {
		$user = wp_get_current_user();
		return rest_ensure_response( array( 'exported_at' => current_time( 'mysql', true ), 'account' => array( 'id' => (int) $user->ID, 'email' => $user->user_email, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'display_name' => $user->display_name, 'description' => $user->description, 'phone' => (string) get_user_meta( $user->ID, 'billing_phone', true ), 'profile_visibility' => (string) get_user_meta( $user->ID, 'daymark_profile_visibility', true ) ), 'subscription' => Daymark_Subscriptions_DB::get_user_subscription( $user->ID ) ?: array(), 'invoices' => Daymark_Subscriptions_DB::get_user_invoices( $user->ID ) ) );
	}

	public function privacy_delete_request() {
		$user_id = get_current_user_id();
		if ( user_can( $user_id, 'manage_options' ) ) return new WP_Error( 'daymark_privacy_admin_protected', __( 'Administrator profiles cannot be deleted through this workflow.', 'daymark-subscriptions' ), array( 'status' => 403 ) );
		$limited = $this->rate_limit( 'privacy_delete_request', 2 ); if ( is_wp_error( $limited ) ) return $limited;
		$requested_at = current_time( 'mysql', true ); update_user_meta( $user_id, 'daymark_privacy_deletion_requested_at', $requested_at );
		$admin_email = get_option( 'admin_email' ); if ( is_email( $admin_email ) ) wp_mail( $admin_email, __( 'Daymark account deletion request', 'daymark-subscriptions' ), sprintf( __( 'WordPress user ID %d requested account deletion review at %s. Preserve billing records as required before removing profile data.', 'daymark-subscriptions' ), $user_id, $requested_at ) );
		Daymark_Subscriptions_DB::record_audit_log( 'privacy_deletion_requested', 'user', $user_id, array( 'requested_at' => $requested_at ) );
		return rest_ensure_response( array( 'requested' => true, 'requested_at' => $requested_at, 'message' => __( 'Your deletion request was sent for administrator review. Billing records may be retained where required.', 'daymark-subscriptions' ) ) );
	}

	public function logout_sessions() {
		$user_id = get_current_user_id();
		$limited = $this->rate_limit( 'logout_sessions', 3 );
		if ( is_wp_error( $limited ) ) return $limited;
		if ( ! $user_id ) return new WP_Error( 'daymark_auth_required', __( 'Authentication is required.', 'daymark-subscriptions' ), array( 'status' => 401 ) );
		WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		Daymark_Subscriptions_DB::record_audit_log( 'member_sessions_revoked', 'user', $user_id, array( 'scope' => 'all_sessions' ) );
		return rest_ensure_response( array( 'logged_out' => true, 'message' => __( 'All active sessions have been signed out. Sign in again on this device.', 'daymark-subscriptions' ) ) );
	}

	public function update_settings( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'settings', 20 );
		if ( is_wp_error( $limited ) ) return $limited;
		$user_id = get_current_user_id(); $data = array();
		foreach ( array( 'first_name', 'last_name', 'display_name' ) as $field ) if ( null !== $request->get_param( $field ) ) $data[ $field ] = $request->get_param( $field );
		if ( null !== $request->get_param( 'profile_description' ) ) $data['description'] = wp_kses_post( $request->get_param( 'profile_description' ) );
		if ( $data ) { $data['ID'] = $user_id; $updated = wp_update_user( $data ); if ( is_wp_error( $updated ) ) return new WP_Error( 'daymark_settings_update_failed', __( 'Your profile could not be updated.', 'daymark-subscriptions' ), array( 'status' => 500 ) ); }
		if ( null !== $request->get_param( 'product_updates' ) ) { $product_updates = $request->get_param( 'product_updates' ) ? '1' : '0'; $updated_meta = update_user_meta( $user_id, 'daymark_product_updates', $product_updates ); if ( ! $updated_meta && (string) get_user_meta( $user_id, 'daymark_product_updates', true ) !== $product_updates ) return new WP_Error( 'daymark_settings_update_failed', __( 'Your notification preference could not be updated.', 'daymark-subscriptions' ), array( 'status' => 500 ) ); }
		if ( null !== $request->get_param( 'profile_visibility' ) ) { $profile_visibility = in_array( $request->get_param( 'profile_visibility' ), array( 'public', 'private' ), true ) ? $request->get_param( 'profile_visibility' ) : 'public'; $updated_meta = update_user_meta( $user_id, 'daymark_profile_visibility', $profile_visibility ); if ( ! $updated_meta && (string) get_user_meta( $user_id, 'daymark_profile_visibility', true ) !== $profile_visibility ) return new WP_Error( 'daymark_settings_update_failed', __( 'Your profile visibility could not be updated.', 'daymark-subscriptions' ), array( 'status' => 500 ) ); }
		if ( null !== $request->get_param( 'phone' ) ) { $phone = preg_replace( '/[^0-9+().\-\s]/', '', (string) $request->get_param( 'phone' ) ); if ( strlen( $phone ) > 40 ) return new WP_Error( 'daymark_phone_invalid', __( 'Enter a valid phone number.', 'daymark-subscriptions' ), array( 'status' => 400 ) ); update_user_meta( $user_id, 'billing_phone', $phone ); update_user_meta( $user_id, 'daymark_phone', $phone ); }
		return $this->settings();
	}

	public function upload_profile_picture() {
		if ( empty( $_FILES['profile_picture']['name'] ) ) return new WP_Error( 'daymark_profile_picture_required', __( 'Choose an image to upload.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( ! empty( $_FILES['profile_picture']['size'] ) && absint( $_FILES['profile_picture']['size'] ) > 5 * MB_IN_BYTES ) return new WP_Error( 'daymark_profile_picture_too_large', __( 'Profile pictures must be 5 MB or smaller.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		$filetype = wp_check_filetype_and_ext( $_FILES['profile_picture']['tmp_name'], sanitize_file_name( $_FILES['profile_picture']['name'] ) );
		if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) return new WP_Error( 'daymark_profile_picture_type', __( 'Only JPG, PNG, GIF, or WebP images are allowed.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$attachment_id = media_handle_upload( 'profile_picture', 0, array( 'post_author' => get_current_user_id(), 'post_title' => sanitize_text_field( pathinfo( wp_unslash( $_FILES['profile_picture']['name'] ), PATHINFO_FILENAME ) ) ) );
		if ( is_wp_error( $attachment_id ) ) return new WP_Error( 'daymark_profile_picture_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $url || ! update_user_meta( get_current_user_id(), 'daymark_profile_picture_url', esc_url_raw( $url ) ) ) return new WP_Error( 'daymark_profile_picture_save_failed', __( 'The profile picture could not be saved.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		return $this->settings();
	}

	public function subscribe( WP_REST_Request $request ) { return $this->checkout( $request ); }

	public function checkout( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'checkout', 5 );
		if ( is_wp_error( $limited ) ) return $limited;
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $request['plan_id'] ), true );
		if ( ! $plan ) return new WP_Error( 'daymark_plan_not_found', __( 'Plan not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$quantity = max( 1, absint( $request->get_param( 'quantity' ) ?: 1 ) );
		if ( empty( $plan['quantity_enabled'] ) ) $quantity = 1;
		if ( $quantity < absint( $plan['quantity_min'] ?? 1 ) || $quantity > absint( $plan['quantity_max'] ?? 1 ) ) return new WP_Error( 'daymark_quantity_invalid', __( 'The selected quantity is outside this plan’s allowed range.', 'daymark-subscriptions' ), array( 'status' => 400, 'minimum' => absint( $plan['quantity_min'] ?? 1 ), 'maximum' => absint( $plan['quantity_max'] ?? 1 ) ) );
		$key = sanitize_text_field( $request->get_header( 'X-Daymark-Idempotency-Key' ) );
		if ( ! $key || strlen( $key ) > 100 ) return new WP_Error( 'daymark_idempotency_required', __( 'An idempotency key is required.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$user = $this->checkout_user( $request );
		if ( is_wp_error( $user ) ) return $user;
		$user_id = $user->ID;
		$identity = is_user_logged_in() ? 'user:' . $user_id : 'email:' . strtolower( trim( $user->user_email ) );
		$scoped_key = 'checkout:' . hash( 'sha256', $identity . '|' . $key );
		$customer_name = sanitize_text_field( $request->get_param( 'customer_name' ) ?: $user->display_name );
		$customer_phone = sanitize_text_field( $request->get_param( 'customer_phone' ) ?: get_user_meta( $user_id, 'billing_phone', true ) );
		if ( ! Daymark_Subscriptions_DB::begin_transaction() || ! Daymark_Subscriptions_DB::lock_user( $user_id ) ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_checkout_reservation_unavailable', __( 'The checkout could not be reserved. Please try again.', 'daymark-subscriptions' ), array( 'status' => 503 ) ); }
		$existing_checkout = Daymark_Subscriptions_DB::get_checkout_by_idempotency( $scoped_key );
		if ( $existing_checkout ) {
			if ( (int) $existing_checkout['user_id'] !== (int) $user_id || (int) $existing_checkout['plan_id'] !== (int) $plan['id'] || (int) $existing_checkout['quantity'] !== $quantity || strtolower( (string) $existing_checkout['customer_email'] ) !== strtolower( (string) $user->user_email ) ) { Daymark_Subscriptions_DB::commit(); return new WP_Error( 'daymark_checkout_key_mismatch', __( 'This checkout key was already used for a different checkout request.', 'daymark-subscriptions' ), array( 'status' => 409 ) ); }
			if ( in_array( $existing_checkout['status'], array( 'payment_pending', 'verified' ), true ) && ! empty( $existing_checkout['checkout_url'] ) ) { Daymark_Subscriptions_DB::commit(); return new WP_REST_Response( array( 'provider' => $existing_checkout['provider'], 'id' => $existing_checkout['provider_session_id'], 'url' => $existing_checkout['checkout_url'], 'idempotent_replay' => true ), 200 ); }
			if ( 'pending_review' === $existing_checkout['status'] ) { Daymark_Subscriptions_DB::commit(); return new WP_REST_Response( array( 'provider' => 'review', 'id' => $existing_checkout['provider_session_id'], 'review' => true, 'url' => Daymark_Payment_Provider::review_success_url(), 'idempotent_replay' => true ), 200 ); }
			Daymark_Subscriptions_DB::commit();
			return new WP_Error( 'daymark_checkout_replay_unavailable', __( 'This checkout request is already being processed. Use a new checkout only after the current request has expired.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		}
		if ( Daymark_Subscriptions_DB::get_latest_checkout( $user_id, 'pending_checkout' ) ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_checkout_pending', __( 'A checkout is already being processed for this account.', 'daymark-subscriptions' ), array( 'status' => 409 ) ); }
		if ( Daymark_Subscriptions_DB::get_active_subscription( $user_id ) ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_already_subscribed', __( 'You already have an active subscription.', 'daymark-subscriptions' ), array( 'status' => 409 ) ); }
		if ( Daymark_Subscriptions_DB::get_pending_review_subscription( $user_id ) ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_review_pending', __( 'Your subscription is waiting for admin review.', 'daymark-subscriptions' ), array( 'status' => 409 ) ); }
		if ( Daymark_Subscriptions_DB::get_pending_payment_subscription( $user_id ) ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_payment_pending', __( 'Your approved subscription is already waiting for payment.', 'daymark-subscriptions' ), array( 'status' => 409 ) ); }
		$provider_key = 'daymark-' . $user_id . '-' . absint( $plan['id'] ) . '-' . substr( hash( 'sha256', $scoped_key ), 0, 32 );
		$checkout = Daymark_Subscriptions_DB::create_checkout( array( 'user_id' => $user_id, 'plan_id' => $plan['id'], 'status' => 'pending_checkout', 'customer_name' => $customer_name, 'customer_email' => $user->user_email, 'customer_phone' => $customer_phone, 'quantity' => $quantity, 'idempotency_key' => $scoped_key ) );
		if ( ! $checkout || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_checkout_state_failed', __( 'The checkout could not be reserved. Please try again.', 'daymark-subscriptions' ), array( 'status' => 503 ) ); }
		if ( 'review' === ( $plan['approval_mode'] ?? 'direct' ) ) {
			$request_result = Daymark_Payment_Provider::create_review_request( $user, $plan, $provider_key, $quantity );
			if ( is_wp_error( $request_result ) ) { Daymark_Subscriptions_DB::update_checkout( $checkout['id'], array( 'status' => 'failed' ) ); return $request_result; }
			Daymark_Subscriptions_DB::update_checkout( $checkout['id'], array( 'status' => 'pending_review', 'provider' => 'review', 'provider_session_id' => $provider_key, 'checkout_url' => $request_result['url'] ?? '' ) );
			do_action( 'daymark_subscription_checkout_created', array( 'user_id' => $user_id, 'plan_id' => absint( $plan['id'] ), 'subscription' => Daymark_Subscriptions_DB::get_user_subscription( $user_id ), 'checkout' => $request_result ) );
			return new WP_REST_Response( $request_result, 201 );
		}
		$session = Daymark_Payment_Provider::create_checkout_session( $user, $plan, $provider_key, 0, $quantity );
			if ( is_wp_error( $session ) ) { Daymark_Subscriptions_DB::update_checkout( $checkout['id'], array( 'status' => 'failed' ) ); return $session; }
		$checkout_status = Daymark_Payment_Provider::development_mode() ? 'verified' : 'payment_pending';
		Daymark_Subscriptions_DB::update_checkout( $checkout['id'], array( 'status' => $checkout_status, 'provider' => $session['provider'] ?? Daymark_Payment_Provider::provider(), 'provider_session_id' => $session['id'] ?? '', 'checkout_url' => $session['url'] ?? '' ) );
		update_user_meta( $user_id, 'daymark_account_state', Daymark_Payment_Provider::development_mode() ? 'active' : 'payment_pending' );
		do_action( 'daymark_subscription_checkout_created', array( 'user_id' => $user_id, 'plan_id' => absint( $plan['id'] ), 'subscription' => Daymark_Subscriptions_DB::get_user_subscription( $user_id ), 'checkout' => $session ) );
		return new WP_REST_Response( $session, 201 );
	}

	private function checkout_user( WP_REST_Request $request ) {
		if ( is_user_logged_in() ) return wp_get_current_user();
		$email = sanitize_email( $request->get_param( 'customer_email' ) ); $name = trim( sanitize_text_field( $request->get_param( 'customer_name' ) ) ); $phone = trim( sanitize_text_field( $request->get_param( 'customer_phone' ) ) );
		if ( ! is_email( $email ) ) return new WP_Error( 'daymark_checkout_email_required', __( 'Enter a valid email address to continue.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( '' === $name ) return new WP_Error( 'daymark_checkout_name_required', __( 'Enter your name to continue.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		if ( '' === $phone ) return new WP_Error( 'daymark_checkout_phone_required', __( 'Enter your phone number to continue.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$user = get_user_by( 'email', $email );
		if ( $user ) return new WP_Error( 'daymark_existing_account_login_required', __( 'An account already exists for this email. Log in before subscribing with it.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( ! $user ) {
			$parts = preg_split( '/\s+/', $name, 2 ); $username = sanitize_user( strstr( $email, '@', true ), true ) ?: 'daymark-member'; $base = $username; $suffix = 1;
			while ( username_exists( $username ) ) $username = $base . '-' . $suffix++;
			$user_id = wp_insert_user( array( 'user_login' => $username, 'user_pass' => wp_generate_password( 32, true, true ), 'user_email' => $email, 'display_name' => $name, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '', 'role' => 'subscriber' ) );
			if ( is_wp_error( $user_id ) ) return new WP_Error( 'daymark_checkout_account_failed', __( 'Your checkout account could not be created.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
				update_user_meta( $user_id, 'daymark_checkout_created_at', current_time( 'mysql', true ) );
			$user = get_user_by( 'id', $user_id );
		}
		if ( $phone ) { update_user_meta( $user->ID, 'billing_phone', $phone ); update_user_meta( $user->ID, 'daymark_phone', $phone ); }
		update_user_meta( $user->ID, 'daymark_account_state', 'pending_checkout' );
		update_user_meta( $user->ID, 'daymark_checkout_created_at', current_time( 'mysql', true ) );
		return $user;
	}

	public function stripe_webhook( WP_REST_Request $request ) {
		$payload = $request->get_body();
		if ( strlen( $payload ) > 262144 ) return new WP_Error( 'daymark_webhook_too_large', __( 'Webhook payload is too large.', 'daymark-subscriptions' ), array( 'status' => 413 ) );
		$limited = $this->rate_limit( 'webhook', 120 );
		if ( is_wp_error( $limited ) ) return $limited;
		$result = Daymark_Stripe::handle_webhook( $payload, $request->get_header( 'stripe-signature' ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'received' => true ), 200 );
	}

	public function cancel( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'cancel', 10 );
		if ( is_wp_error( $limited ) ) return $limited;
		$user_id = get_current_user_id();
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $subscription ) return new WP_Error( 'daymark_no_active_subscription', __( 'No active subscription found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ) );
		if ( ! current_user_can( 'manage_daymark_subscriptions' ) && ! current_user_can( 'manage_options' ) && ! empty( $subscription['commitment_ends_at'] ) && strtotime( $subscription['commitment_ends_at'] . ' UTC' ) > current_time( 'timestamp', true ) ) return new WP_Error( 'daymark_commitment_active', sprintf( __( 'This subscription has a minimum commitment through %s.', 'daymark-subscriptions' ), wp_date( get_option( 'date_format' ), strtotime( $subscription['commitment_ends_at'] . ' UTC' ) ) ), array( 'status' => 409, 'commitment_ends_at' => $subscription['commitment_ends_at'] ) );
		$plan_cancellation = sanitize_key( $plan['cancellation_mode'] ?? 'inherit' );
		$immediate = $request->has_param( 'immediate' ) ? (bool) $request->get_param( 'immediate' ) : ( 'inherit' === $plan_cancellation ? 'immediate' === Daymark_Subscriptions_Admin::cancellation_mode() : 'immediate' === $plan_cancellation );
		$reason = trim( (string) $request->get_param( 'reason' ) );
		$idempotency = $this->idempotency_key( $request, 'cancel-' . $subscription['id'] . '-' . ( $immediate ? 'now' : 'period-end' ) );
		if ( is_wp_error( $idempotency ) ) return $idempotency;
		$operation = Daymark_Subscriptions_DB::create_operation( $idempotency, 'member_cancel', $subscription['id'], array( 'immediate' => $immediate, 'reason' => $reason ) );
		if ( $operation && 'succeeded' === $operation['status'] ) { $already_applied = $immediate ? 'canceled' === $subscription['status'] : (int) $subscription['cancel_at_period_end']; if ( $already_applied ) return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $subscription ); Daymark_Subscriptions_DB::update_operation( $operation['id'], 'pending', array( 'attempts' => 0, 'last_error' => null ) ); }
		$provider_result = Daymark_Payment_Provider::sync_cancel( $subscription['provider_subscription_id'], $immediate, $idempotency );
		if ( is_wp_error( $provider_result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $provider_result->get_error_message() ) ); return $provider_result; }
		if ( ! $this->lock_current_subscription( $user_id, $subscription ) ) return $this->stale_lifecycle_error( 'cancelled' );
		if ( $immediate ) {
			$updated = Daymark_Subscriptions_DB::update_subscription( $subscription['id'], $user_id, array( 'status' => 'canceled', 'cancel_at_period_end' => 0, 'canceled_at' => current_time( 'mysql', true ) ), array( '%s', '%d', '%s' ) );
		} else {
			$updated = Daymark_Subscriptions_DB::update_subscription( $subscription['id'], $user_id, array( 'cancel_at_period_end' => 1 ), array( '%d' ) );
		}
		if ( ! $updated || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider changed before local state committed.', 'next_attempt_at' => current_time( 'mysql', true ) ) ); $this->record_remote_sync_recovery( 'member_cancel', $subscription, array( 'immediate' => $immediate ) ); return new WP_Error( 'daymark_subscription_cancel_pending_sync', __( 'The provider changed, but the local subscription update needs reconciliation.', 'daymark-subscriptions' ), array( 'status' => 503, 'reconciliation_required' => true ) ); }
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1, 'provider_response' => wp_json_encode( array( 'accepted' => true ) ), 'local_response' => wp_json_encode( array( 'updated' => true ) ) ) );
		$updated_subscription = Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $subscription;
		do_action( 'daymark_subscription_cancelled', $updated_subscription, $user_id );
		do_action( 'daymark_subscription_access_changed', $updated_subscription, 'canceled' );
		Daymark_Subscriptions_DB::record_audit_log( 'member_cancelled', 'subscription', $subscription['id'], array( 'immediate' => $immediate, 'reason' => $reason ) );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) );
	}

	public function pause( WP_REST_Request $request = null ) {
		$limited = $this->rate_limit( 'pause', 10 );
		if ( is_wp_error( $limited ) ) return $limited;
		$user_id = get_current_user_id();
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $subscription || 'active' !== $subscription['status'] ) return new WP_Error( 'daymark_pause_invalid', __( 'Only an active subscription can be paused.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ) );
		if ( $plan && empty( $plan['pause_enabled'] ) ) return new WP_Error( 'daymark_pause_disabled', __( 'Pause is not available for this plan.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$idempotency = $this->idempotency_key( $request, 'pause-' . $subscription['id'] );
		if ( is_wp_error( $idempotency ) ) return $idempotency;
		$operation = Daymark_Subscriptions_DB::create_operation( $idempotency, 'member_pause', $subscription['id'] );
		if ( $operation && 'succeeded' === $operation['status'] ) { if ( 'paused' === $subscription['status'] ) return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $subscription ); Daymark_Subscriptions_DB::update_operation( $operation['id'], 'pending', array( 'attempts' => 0, 'last_error' => null ) ); }
		$provider_result = Daymark_Payment_Provider::sync_pause( $subscription['provider_subscription_id'], $idempotency );
		if ( is_wp_error( $provider_result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $provider_result->get_error_message() ) ); return $provider_result; }
		if ( ! $this->lock_current_subscription( $user_id, $subscription ) ) return $this->stale_lifecycle_error( 'paused' );
		$pause_days = Daymark_Subscriptions_Admin::pause_days(); $paused_until = $pause_days ? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) + ( $pause_days * DAY_IN_SECONDS ) ) : null;
		$updated = Daymark_Subscriptions_DB::update_subscription( $subscription['id'], $user_id, array( 'status' => 'paused', 'paused_at' => current_time( 'mysql', true ), 'paused_until' => $paused_until, 'cancel_at_period_end' => 0 ), array( '%s', '%s', '%s', '%d' ) );
		if ( ! $updated || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider changed before local state committed.', 'next_attempt_at' => current_time( 'mysql', true ) ) ); $this->record_remote_sync_recovery( 'member_pause', $subscription ); return new WP_Error( 'daymark_pause_pending_sync', __( 'The provider changed, but the local subscription update needs reconciliation.', 'daymark-subscriptions' ), array( 'status' => 503, 'reconciliation_required' => true ) ); }
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1 ) );
		$updated_subscription = Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $subscription;
		do_action( 'daymark_subscription_paused', $updated_subscription, $user_id );
		do_action( 'daymark_subscription_access_changed', $updated_subscription, 'paused' );
		Daymark_Subscriptions_DB::record_audit_log( 'member_paused', 'subscription', $subscription['id'], array( 'paused_until' => $paused_until ) );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) );
	}

	public function resume( WP_REST_Request $request = null ) {
		$limited = $this->rate_limit( 'resume', 10 );
		if ( is_wp_error( $limited ) ) return $limited;
		$user_id = get_current_user_id();
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $subscription || ( 'paused' !== $subscription['status'] && ! (int) $subscription['cancel_at_period_end'] ) ) return new WP_Error( 'daymark_resume_invalid', __( 'No paused or scheduled-cancellation subscription found.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$idempotency = $this->idempotency_key( $request, 'resume-' . $subscription['id'] );
		if ( is_wp_error( $idempotency ) ) return $idempotency;
		$operation = Daymark_Subscriptions_DB::create_operation( $idempotency, 'member_resume', $subscription['id'] );
		if ( $operation && 'succeeded' === $operation['status'] ) { if ( 'active' === $subscription['status'] && ! (int) $subscription['cancel_at_period_end'] ) return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $subscription ); Daymark_Subscriptions_DB::update_operation( $operation['id'], 'pending', array( 'attempts' => 0, 'last_error' => null ) ); }
		$provider_result = Daymark_Payment_Provider::sync_resume( $subscription['provider_subscription_id'], $idempotency, (bool) $subscription['cancel_at_period_end'] );
		if ( is_wp_error( $provider_result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $provider_result->get_error_message() ) ); return $provider_result; }
		if ( ! $this->lock_current_subscription( $user_id, $subscription ) ) return $this->stale_lifecycle_error( 'resumed' );
		$updated = Daymark_Subscriptions_DB::update_subscription( $subscription['id'], $user_id, array( 'status' => 'active', 'paused_at' => null, 'cancel_at_period_end' => 0 ), array( '%s', '%s', '%d' ) );
		if ( ! $updated || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider changed before local state committed.', 'next_attempt_at' => current_time( 'mysql', true ) ) ); $this->record_remote_sync_recovery( 'member_resume', $subscription ); return new WP_Error( 'daymark_resume_pending_sync', __( 'The provider changed, but the local subscription update needs reconciliation.', 'daymark-subscriptions' ), array( 'status' => 503, 'reconciliation_required' => true ) ); }
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1 ) );
		$updated_subscription = Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $subscription;
		do_action( 'daymark_subscription_resumed', $updated_subscription, $user_id );
		do_action( 'daymark_subscription_access_changed', $updated_subscription, 'active' );
		Daymark_Subscriptions_DB::record_audit_log( 'member_resumed', 'subscription', $subscription['id'] );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) );
	}

	public function change_plan( WP_REST_Request $request ) {
		$limited = $this->rate_limit( 'change_plan', 10 );
		if ( is_wp_error( $limited ) ) return $limited;
		$user_id = get_current_user_id(); $new_plan = Daymark_Subscriptions_DB::get_plan( absint( $request['plan_id'] ), true );
		if ( ! $new_plan ) return new WP_Error( 'daymark_plan_not_found', __( 'Plan not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$current = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $current ) return new WP_Error( 'daymark_no_active_subscription', __( 'No active subscription found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( (int) $current['plan_id'] === (int) $new_plan['id'] ) return new WP_Error( 'daymark_same_plan', __( 'You are already on this plan.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$effective = Daymark_Subscriptions_Admin::plan_change_timing();
		$idempotency = $this->idempotency_key( $request, 'plan-' . $current['id'] . '-' . $new_plan['id'] );
		if ( is_wp_error( $idempotency ) ) return $idempotency;
		$operation = false;
		if ( 'immediate' === $effective ) {
			$operation = Daymark_Subscriptions_DB::create_operation( $idempotency, 'member_plan_change', $current['id'], array( 'plan_id' => absint( $new_plan['id'] ), 'effective' => $effective ) );
			if ( $operation && 'succeeded' === $operation['status'] ) { if ( (int) $current['plan_id'] === (int) $new_plan['id'] && ! $current['pending_plan_id'] ) return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $current ); Daymark_Subscriptions_DB::update_operation( $operation['id'], 'pending', array( 'attempts' => 0, 'last_error' => null ) ); }
			$provider_result = Daymark_Payment_Provider::sync_plan( $current['provider_subscription_id'], $new_plan, $idempotency );
			if ( is_wp_error( $provider_result ) ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => $provider_result->get_error_message() ) ); return $provider_result; }
			if ( ! $this->lock_current_subscription( $user_id, $current ) ) return $this->stale_lifecycle_error( 'changed' );
			$commitment_end = absint( $new_plan['minimum_commitment_cycles'] ?? 0 ) ? gmdate( 'Y-m-d H:i:s', strtotime( '+' . absint( $new_plan['minimum_commitment_cycles'] ) . ' ' . $new_plan['interval_unit'], current_time( 'timestamp', true ) ) ) : null;
			$updated = Daymark_Subscriptions_DB::update_subscription( $current['id'], $user_id, array( 'plan_id' => $new_plan['id'], 'pending_plan_id' => null, 'change_effective_at' => null, 'price_snapshot' => (float) $new_plan['price'], 'currency_snapshot' => strtoupper( $new_plan['currency'] ), 'interval_snapshot' => $new_plan['interval_unit'], 'commitment_ends_at' => $commitment_end ), array( '%d', '%d', '%s', '%f', '%s', '%s', '%s' ) );
			if ( $operation && $updated ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => 1 ) );
		} else {
			if ( ! $this->lock_current_subscription( $user_id, $current ) ) return $this->stale_lifecycle_error( 'changed' );
			$current = Daymark_Subscriptions_DB::get_active_subscription( $user_id, true );
			$updated = Daymark_Subscriptions_DB::update_subscription( $current['id'], $user_id, array( 'pending_plan_id' => $new_plan['id'], 'change_effective_at' => $current['current_period_end'] ), array( '%d', '%s' ) );
		}
		if ( ! $updated || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'attempts' => 1, 'last_error' => 'Provider changed before local state committed.', 'next_attempt_at' => current_time( 'mysql', true ) ) ); $this->record_remote_sync_recovery( 'member_plan_change', $current, array( 'plan_id' => absint( $new_plan['id'] ), 'effective' => $effective ) ); return new WP_Error( 'daymark_plan_change_pending_sync', __( 'The provider changed, but the local subscription update needs reconciliation.', 'daymark-subscriptions' ), array( 'status' => 503, 'reconciliation_required' => true ) ); }
		$updated_subscription = Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $current;
		do_action( 'daymark_subscription_plan_changed', $updated_subscription, $new_plan, $effective, $user_id );
		do_action( 'daymark_subscription_access_changed', $updated_subscription, $updated_subscription['status'] ?? 'active' );
		Daymark_Subscriptions_DB::record_audit_log( 'member_plan_changed', 'subscription', $current['id'], array( 'plan_id' => absint( $new_plan['id'] ), 'effective' => $effective ) );
		return rest_ensure_response( Daymark_Subscriptions_DB::get_user_subscription( $user_id ) );
	}

	public function preview_plan_change( WP_REST_Request $request ) {
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $request['plan_id'] ), true ); $current = Daymark_Subscriptions_DB::get_active_subscription( get_current_user_id() );
		if ( ! $plan ) return new WP_Error( 'daymark_plan_not_found', __( 'Plan not found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( ! $current ) return new WP_Error( 'daymark_no_active_subscription', __( 'No active subscription found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$effective = sanitize_key( $request->get_param( 'effective' ) ?: Daymark_Subscriptions_Admin::plan_change_timing() ); if ( ! in_array( $effective, array( 'immediate', 'period_end' ), true ) ) $effective = Daymark_Subscriptions_Admin::plan_change_timing();
		$provider_preview = 'immediate' === $effective ? Daymark_Payment_Provider::preview_plan_change( $current, $plan ) : array( 'available' => false, 'reason' => 'period_end', 'message' => __( 'No immediate proration is expected. The new recurring amount applies at the next renewal.', 'daymark-subscriptions' ) );
		return rest_ensure_response( array( 'current' => array( 'name' => $current['name'], 'price' => (float) ( $current['price_snapshot'] ?? $current['price'] ), 'currency' => $current['currency_snapshot'] ?? $current['currency'], 'quantity' => max( 1, absint( $current['quantity'] ?? 1 ) ), 'period_end' => $current['current_period_end'] ), 'new' => array( 'id' => (int) $plan['id'], 'name' => $plan['name'], 'price' => (float) $plan['price'], 'currency' => $plan['currency'], 'interval_unit' => $plan['interval_unit'], 'setup_fee' => (float) ( $plan['setup_fee'] ?? 0 ) ), 'effective' => $effective, 'next_billing_amount' => (float) $plan['price'] * max( 1, absint( $current['quantity'] ?? 1 ) ), 'next_billing_date' => 'period_end' === $effective ? $current['current_period_end'] : null, 'provider_preview' => $provider_preview ) );
	}

	public function cancel_plan_change( WP_REST_Request $request = null ) {
		$limited = $this->rate_limit( 'cancel_plan_change', 10 );
		if ( is_wp_error( $limited ) ) return $limited;
		$user_id = get_current_user_id();
		$current = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $current ) return new WP_Error( 'daymark_no_active_subscription', __( 'No active subscription found.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		if ( empty( $current['pending_plan_id'] ) ) return new WP_Error( 'daymark_no_pending_plan_change', __( 'No scheduled plan change found.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$idempotency = $this->idempotency_key( $request, 'cancel-plan-change-' . $current['id'] );
		if ( is_wp_error( $idempotency ) ) return $idempotency;
		if ( ! $this->lock_current_subscription( $user_id, $current ) ) return $this->stale_lifecycle_error( 'cancelled' );
		$current = Daymark_Subscriptions_DB::get_active_subscription( $user_id, true );
		if ( ! $current || empty( $current['pending_plan_id'] ) ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_no_pending_plan_change', __( 'No scheduled plan change found.', 'daymark-subscriptions' ), array( 'status' => 409 ) ); }
		$updated = Daymark_Subscriptions_DB::update_subscription( $current['id'], $user_id, array( 'pending_plan_id' => null, 'change_effective_at' => null ), array( '%d', '%s' ) );
		if ( ! $updated || ! Daymark_Subscriptions_DB::commit() ) { Daymark_Subscriptions_DB::rollback(); return new WP_Error( 'daymark_plan_change_cancel_failed', __( 'The scheduled plan change could not be cancelled.', 'daymark-subscriptions' ), array( 'status' => 500 ) ); }
		$subscription = Daymark_Subscriptions_DB::get_user_subscription( $user_id ) ?: $current;
		do_action( 'daymark_subscription_plan_change_cancelled', $subscription, $user_id );
		Daymark_Subscriptions_DB::record_audit_log( 'member_plan_change_cancelled', 'subscription', $current['id'], array( 'idempotent' => true ) );
		return rest_ensure_response( $subscription );
	}

	private function lock_current_subscription( $user_id, $expected ) {
		if ( ! Daymark_Subscriptions_DB::begin_transaction() || ! Daymark_Subscriptions_DB::lock_user( $user_id ) ) { Daymark_Subscriptions_DB::rollback(); return false; }
		$current = Daymark_Subscriptions_DB::get_active_subscription( $user_id, true );
		if ( ! $current || (int) $current['id'] !== (int) $expected['id'] || (string) $current['provider_subscription_id'] !== (string) $expected['provider_subscription_id'] || (string) $current['status'] !== (string) $expected['status'] ) { Daymark_Subscriptions_DB::rollback(); return false; }
		return true;
	}

	private function stale_lifecycle_error( $action ) {
		return new WP_Error( 'daymark_subscription_changed', sprintf( __( 'The subscription changed while it was being %s. Please retry.', 'daymark-subscriptions' ), $action ), array( 'status' => 409 ) );
	}

	private function idempotency_key( $request, $fallback ) {
		$key = $request instanceof WP_REST_Request ? sanitize_text_field( $request->get_header( 'X-Daymark-Idempotency-Key' ) ) : '';
		$key = $key ?: sanitize_text_field( $fallback );
		return $key && strlen( $key ) <= 100 ? $key : new WP_Error( 'daymark_idempotency_invalid', __( 'The idempotency key is invalid.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
	}

	private function record_remote_sync_recovery( $action, array $subscription, array $details = array() ) {
		$key = 'recovery-' . sanitize_key( $action ) . '-' . absint( $subscription['id'] ) . '-' . wp_generate_uuid4();
		$operation = Daymark_Subscriptions_DB::create_operation( $key, $action, $subscription['id'], array_merge( array( 'provider_subscription_id' => sanitize_text_field( $subscription['provider_subscription_id'] ?? '' ) ), $details ) );
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'recovery_required', array( 'last_error' => 'Provider changed before local state committed.', 'next_attempt_at' => current_time( 'mysql', true ) ) );
		Daymark_Subscriptions_DB::record_audit_log( 'provider_sync_recovery_required', 'subscription', absint( $subscription['id'] ?? 0 ), array_merge( array( 'operation' => sanitize_key( $action ), 'provider' => Daymark_Payment_Provider::provider(), 'provider_subscription_id' => sanitize_text_field( $subscription['provider_subscription_id'] ?? '' ) ), $details ) );
		do_action( 'daymark_subscription_reconciliation_required', $subscription, sanitize_key( $action ), $details );
		}

	private function rate_limit( $action, $limit ) {
		$identity = get_current_user_id() . '|' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown' );
		$key = 'daymark_rl_' . hash( 'sha256', $action . '|' . $identity );
		$allowed = Daymark_Subscriptions_DB::consume_rate_limit( $key, $limit, MINUTE_IN_SECONDS );
		if ( null === $allowed ) return new WP_Error( 'daymark_rate_limit_unavailable', __( 'Request limiting is temporarily unavailable.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		if ( ! $allowed ) return new WP_Error( 'daymark_rate_limited', __( 'Too many requests. Please try again later.', 'daymark-subscriptions' ), array( 'status' => 429 ) );
		return true;
	}
}
