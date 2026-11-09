<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Payment_Provider {
	const OPTION = 'daymark_payment_provider';
	const ENVIRONMENT_OPTION = 'daymark_subscriptions_environment';

	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'tag_cart_item' ), 10, 2 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_setup_fee' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'tag_order' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( $this, 'payment_complete' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'payment_complete' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'payment_complete' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'order_failed' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'order_cancelled' ), 10, 1 );
		add_action( 'daymark_subscription_renewal_due', array( $this, 'renewal_due' ), 10, 1 );
		add_action( 'daymark_subscription_pause_expired', array( $this, 'pause_expired' ), 10, 1 );
		add_filter( 'woocommerce_get_return_url', array( $this, 'woocommerce_return_url' ), 10, 2 );
	}

	public static function reconcile() {
		if ( self::development_mode() ) return;
		foreach ( Daymark_Subscriptions_DB::get_reconcilable_subscriptions( 50 ) as $subscription ) {
			if ( 'stripe' === self::provider() && 0 !== strpos( (string) $subscription['provider_subscription_id'], 'dev_subscription:' ) && ! Daymark_Stripe::reconcile_subscription( $subscription['provider_subscription_id'] ) ) Daymark_Subscriptions_DB::record_audit_log( 'provider_reconcile_failed', 'subscription', absint( $subscription['id'] ), array( 'provider_subscription_id' => sanitize_text_field( $subscription['provider_subscription_id'] ) ) );
		}
	}

	public function pause_expired( $subscription ) {
		$result = self::sync_resume( $subscription['provider_subscription_id'] ?? '', 'daymark-auto-resume-' . absint( $subscription['id'] ), false );
		if ( is_wp_error( $result ) || ! Daymark_Subscriptions_DB::resume_paused_subscription( $subscription['id'] ) ) return;
		do_action( 'daymark_subscription_resumed', Daymark_Subscriptions_DB::get_user_subscription( absint( $subscription['user_id'] ) ) ?: $subscription, absint( $subscription['user_id'] ) );
		Daymark_Subscriptions_DB::record_audit_log( 'subscription_auto_resumed', 'subscription', absint( $subscription['id'] ), array( 'paused_until' => $subscription['paused_until'] ) );
	}

	public static function provider() {
		$value = defined( 'DAYMARK_PAYMENT_PROVIDER' ) ? DAYMARK_PAYMENT_PROVIDER : get_option( self::OPTION, 'stripe' );
		return in_array( $value, array( 'stripe', 'woocommerce' ), true ) ? $value : 'stripe';
	}

	public static function environment() {
		$value = defined( 'DAYMARK_SUBSCRIPTIONS_ENVIRONMENT' ) ? DAYMARK_SUBSCRIPTIONS_ENVIRONMENT : get_option( self::ENVIRONMENT_OPTION, 'prod' );
		return in_array( $value, array( 'dev', 'prod' ), true ) ? $value : 'prod';
	}

	public static function development_mode() { return 'dev' === self::environment(); }

	public static function checkout_status() {
		$provider = self::provider();
		if ( self::development_mode() ) return array( 'ready' => true, 'environment' => 'dev', 'provider' => $provider, 'mode' => 'development', 'testing_only' => true, 'message' => __( 'Development mode is active. This checkout bypasses the payment gateway and is for testing only.', 'daymark-subscriptions' ) );
		if ( 'stripe' === $provider ) {
			$https = 'https' === strtolower( (string) wp_parse_url( rest_url(), PHP_URL_SCHEME ) );
			$ready = Daymark_Stripe::configured() && $https;
			$message = ! Daymark_Stripe::configured() ? __( 'Stripe is not configured for the selected mode. Configure the secret and webhook keys before accepting subscriptions.', 'daymark-subscriptions' ) : ( $https ? ( 'test' === Daymark_Stripe::mode() ? __( 'Stripe Sandbox is active. Payments are test transactions only.', 'daymark-subscriptions' ) : __( 'Stripe Live is active. Payments will be processed normally.', 'daymark-subscriptions' ) ) : __( 'Production Stripe checkout requires an HTTPS REST endpoint for signed webhooks.', 'daymark-subscriptions' ) );
			return array( 'ready' => $ready, 'environment' => 'prod', 'provider' => 'stripe', 'mode' => Daymark_Stripe::mode(), 'testing_only' => 'test' === Daymark_Stripe::mode(), 'https_webhook_endpoint' => $https, 'message' => $message );
		}
		$capabilities = self::woocommerce_capabilities();
		return array( 'ready' => ! empty( $capabilities['ready'] ), 'environment' => 'prod', 'provider' => 'woocommerce', 'mode' => 'configured gateway', 'testing_only' => false, 'renewal_capability' => self::renewal_capability(), 'capabilities' => $capabilities, 'message' => ! empty( $capabilities['ready'] ) ? __( 'WooCommerce checkout is ready with a compatible tokenized renewal gateway.', 'daymark-subscriptions' ) : __( 'WooCommerce production checkout requires an enabled tokenized renewal gateway and a compatible Daymark renewal adapter.', 'daymark-subscriptions' ) );
	}

	public static function woocommerce_capabilities() {
		$enabled = false; $tokenized = false; $gateway_ids = array();
		if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC() && WC()->payment_gateways() ) {
			$gateways = is_callable( array( WC()->payment_gateways(), 'get_available_payment_gateways' ) ) ? WC()->payment_gateways()->get_available_payment_gateways() : array();
			foreach ( $gateways as $gateway_id => $gateway ) {
				if ( ! isset( $gateway->enabled ) || 'yes' !== $gateway->enabled ) continue;
				$enabled = true; $gateway_ids[] = sanitize_key( $gateway_id );
				if ( is_callable( array( $gateway, 'supports' ) ) && ( $gateway->supports( 'tokenization' ) || $gateway->supports( 'subscriptions' ) ) ) $tokenized = true;
			}
		}
		$adapter = has_filter( 'daymark_woocommerce_renewal_charge' );
		return array( 'ready' => $enabled && $tokenized && false !== $adapter, 'enabled_gateway' => $enabled, 'tokenized_gateway' => $tokenized, 'renewal_adapter' => false !== $adapter, 'gateway_ids' => $gateway_ids );
	}

	public static function renewal_capability() {
		if ( self::development_mode() ) return 'development_bypass';
		if ( 'stripe' === self::provider() ) return Daymark_Stripe::configured() ? 'provider_managed' : 'unconfigured';
		if ( ! class_exists( 'WooCommerce' ) ) return 'woocommerce_unavailable';
		return has_filter( 'daymark_woocommerce_renewal_charge' ) ? 'adapter_dependent' : 'manual_payment_order';
	}

	public static function test_connection() {
		if ( self::development_mode() ) return array( 'provider' => self::provider(), 'environment' => 'dev', 'configured' => true, 'message' => __( 'Development mode bypasses provider requests.', 'daymark-subscriptions' ) );
		if ( 'stripe' === self::provider() ) { $result = Daymark_Stripe::test_connection(); if ( ! is_wp_error( $result ) ) $result['renewal_capability'] = self::renewal_capability(); return $result; }
		$capabilities = self::woocommerce_capabilities();
		return ! empty( $capabilities['ready'] ) ? array( 'provider' => 'woocommerce', 'configured' => true, 'renewal_capability' => self::renewal_capability(), 'capabilities' => $capabilities, 'message' => __( 'WooCommerce checkout and tokenized renewal adapter are available.', 'daymark-subscriptions' ) ) : new WP_Error( 'daymark_woocommerce_not_ready', __( 'WooCommerce requires an enabled tokenized renewal gateway and a compatible Daymark renewal adapter.', 'daymark-subscriptions' ), array( 'status' => 503, 'capabilities' => $capabilities ) );
	}

	public static function checkout_success_url() { return esc_url_raw( add_query_arg( 'daymark_checkout', 'success', home_url( '/' ) ) ); }

	public static function review_success_url() { return esc_url_raw( add_query_arg( 'daymark_checkout', 'review', home_url( '/' ) ) ); }

	public static function create_billing_portal_session( $customer_id, $return_url ) {
		if ( self::development_mode() ) return new WP_Error( 'daymark_billing_portal_unavailable', __( 'Billing portal is unavailable in development mode.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		return 'stripe' === self::provider() ? Daymark_Stripe::create_billing_portal_session( $customer_id, $return_url ) : new WP_Error( 'daymark_woocommerce_portal_unsupported', __( 'WooCommerce billing settings are managed by the configured gateway.', 'daymark-subscriptions' ), array( 'status' => 501 ) );
	}

	public static function create_payment_recovery_url( array $subscription ) {
		if ( self::development_mode() ) return new WP_Error( 'daymark_payment_recovery_unavailable', __( 'Payment recovery is unavailable in development mode.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( 'stripe' === self::provider() ) {
			if ( empty( $subscription['provider_customer_id'] ) ) return new WP_Error( 'daymark_billing_profile_missing', __( 'No provider billing profile is available yet.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
			return Daymark_Stripe::create_billing_portal_session( $subscription['provider_customer_id'], home_url( '/subscription-management/' ) );
		}
		if ( ! function_exists( 'wc_get_order' ) || empty( $subscription['renewal_order_id'] ) ) return new WP_Error( 'daymark_renewal_payment_missing', __( 'A renewal payment link is not available yet.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$order = wc_get_order( absint( $subscription['renewal_order_id'] ) );
		if ( ! $order || (int) $order->get_customer_id() !== (int) $subscription['user_id'] || $order->is_paid() ) return new WP_Error( 'daymark_renewal_payment_unavailable', __( 'This renewal payment is no longer available.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		return array( 'url' => esc_url_raw( $order->get_checkout_payment_url() ), 'provider' => 'woocommerce' );
	}

	public static function retry_woocommerce_renewal( array $subscription, array $operation ) {
		if ( 'woocommerce' !== self::provider() || ! function_exists( 'wc_get_order' ) || empty( $subscription['renewal_order_id'] ) ) return new WP_Error( 'daymark_woocommerce_renewal_retry_unavailable', __( 'The WooCommerce renewal order is not available for retry.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$order = wc_get_order( absint( $subscription['renewal_order_id'] ) );
		if ( ! $order || (int) $order->get_customer_id() !== (int) $subscription['user_id'] ) return new WP_Error( 'daymark_woocommerce_renewal_order_invalid', __( 'The WooCommerce renewal order could not be verified.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$key = sanitize_text_field( $operation['operation_key'] );
		$result = apply_filters( 'daymark_woocommerce_renewal_charge', false, $order, $subscription, $key );
		$status = is_array( $result ) ? sanitize_key( $result['status'] ?? 'pending' ) : ( true === $result ? 'paid' : 'pending' );
		if ( 'failed' === $status ) { Daymark_Subscriptions_DB::update_operation( $operation['id'], 'dead_letter', array( 'attempts' => (int) $operation['attempts'] + 1, 'last_error' => sanitize_textarea_field( $result['message'] ?? 'WooCommerce renewal retry failed.' ), 'next_attempt_at' => null ) ); return new WP_Error( 'daymark_woocommerce_renewal_retry_failed', __( 'The WooCommerce renewal retry failed.', 'daymark-subscriptions' ), array( 'status' => 502 ) ); }
		if ( 'paid' === $status && $order->is_paid() ) ( new self() )->activate_renewal_order( $order );
		return array( 'status' => $status, 'order_id' => $order->get_id(), 'payment_url' => $order->is_paid() ? '' : esc_url_raw( $order->get_checkout_payment_url() ) );
	}

	public static function billing_action( array $subscription, $action, $amount = 0, $idempotency_key = '' ) {
		$action = sanitize_key( $action );
		if ( self::development_mode() ) return new WP_Error( 'daymark_billing_action_unavailable', __( 'Billing adjustments are unavailable in development mode.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( 'stripe' === self::provider() ) return Daymark_Stripe::billing_action( $subscription['last_invoice_id'] ?? '', $action, $amount, $idempotency_key );
		if ( ! function_exists( 'wc_get_order' ) || empty( $subscription['renewal_order_id'] ) ) return new WP_Error( 'daymark_renewal_order_missing', __( 'The WooCommerce renewal order is not available.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$order = wc_get_order( absint( $subscription['renewal_order_id'] ) );
		if ( ! $order || (int) $order->get_customer_id() !== (int) $subscription['user_id'] ) return new WP_Error( 'daymark_renewal_order_invalid', __( 'The renewal order could not be verified.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( in_array( $action, array( 'refund', 'credit' ), true ) ) {
			$amount = (float) $amount;
			if ( $amount <= 0 || $amount > (float) $order->get_total() || ! function_exists( 'wc_create_refund' ) ) return new WP_Error( 'daymark_order_amount_invalid', __( 'The adjustment amount is invalid for this order.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
			return wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => wc_format_decimal( $amount ), 'reason' => 'Daymark subscription ' . $action, 'refund_payment' => true ) );
		}
		if ( 'void' === $action || 'uncollectible' === $action ) return $order->update_status( 'cancelled', __( 'Daymark billing action cancelled the unpaid renewal order.', 'daymark-subscriptions' ) );
		if ( 'resend' === $action ) { $mailer = WC()->mailer(); $emails = $mailer ? $mailer->get_emails() : array(); if ( ! empty( $emails['WC_Email_Customer_Invoice'] ) ) return $emails['WC_Email_Customer_Invoice']->trigger( $order->get_id() ); }
		if ( 'pay' === $action ) return array( 'url' => esc_url_raw( $order->get_checkout_payment_url() ) );
		return new WP_Error( 'daymark_order_action_invalid', __( 'This WooCommerce billing action is not supported.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
	}

	public static function create_review_request( WP_User $user, array $plan, $idempotency_key, $quantity = 1 ) {
		$now = current_time( 'mysql', true ); $provider_id = 'review_request:' . $user->ID . ':' . substr( hash( 'sha256', $idempotency_key ), 0, 32 );
		$created = Daymark_Subscriptions_DB::create_subscription_once( array( 'user_id' => $user->ID, 'plan_id' => absint( $plan['id'] ), 'status' => 'pending_review', 'started_at' => $now, 'current_period_start' => null, 'current_period_end' => null, 'cancel_at_period_end' => 0, 'canceled_at' => null, 'paused_at' => null, 'paused_until' => null, 'pending_plan_id' => null, 'change_effective_at' => null, 'last_payment_status' => 'unpaid', 'failure_count' => 0, 'quantity' => max( 1, absint( $quantity ) ), 'provider_customer_id' => '', 'provider_subscription_id' => $provider_id, 'checkout_session_id' => null, 'idempotency_key' => 'review:' . $idempotency_key, 'created_at' => $now ) );
		if ( ! $created ) return new WP_Error( 'daymark_review_request_failed', __( 'The approval request could not be created.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		if ( ! empty( $created['conflict'] ) ) return new WP_Error( 'daymark_review_pending', __( 'Your subscription is already waiting for admin review.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		do_action( 'daymark_subscription_pending_review', Daymark_Subscriptions_DB::get_subscription_by_id( $created['subscription']['id'] ) );
		return array( 'provider' => 'review', 'id' => $provider_id, 'review' => true, 'url' => self::review_success_url() );
	}

	public static function create_development_checkout( WP_User $user, array $plan, $idempotency_key, $quantity = 1 ) {
		$now = current_time( 'mysql', true ); $provider_id = 'dev_subscription:' . $user->ID . ':' . substr( hash( 'sha256', $idempotency_key ), 0, 32 ); $period_start = strtotime( $now . ' UTC' ) + ( absint( $plan['trial_days'] ?? 0 ) * DAY_IN_SECONDS ); $end = gmdate( 'Y-m-d H:i:s', strtotime( '+1 ' . $plan['interval_unit'], $period_start ) );
		$created = Daymark_Subscriptions_DB::create_subscription_once( array( 'user_id' => $user->ID, 'plan_id' => absint( $plan['id'] ), 'status' => Daymark_Subscriptions_DB::subscription_status_for_plan( $plan ), 'started_at' => $now, 'current_period_start' => $now, 'current_period_end' => $end, 'cancel_at_period_end' => 0, 'canceled_at' => null, 'paused_at' => null, 'paused_until' => null, 'pending_plan_id' => null, 'change_effective_at' => null, 'last_payment_status' => 'paid', 'failure_count' => 0, 'quantity' => max( 1, absint( $quantity ) ), 'provider_customer_id' => 'dev_customer:' . $user->ID, 'provider_subscription_id' => $provider_id, 'checkout_session_id' => 'dev_checkout:' . substr( hash( 'sha256', $idempotency_key ), 0, 32 ), 'idempotency_key' => 'dev:' . $idempotency_key, 'created_at' => $now ) );
		if ( ! $created ) return new WP_Error( 'daymark_dev_subscription_failed', __( 'The development subscription could not be created.', 'daymark-subscriptions' ), array( 'status' => 500 ) );
		if ( ! empty( $created['conflict'] ) ) return new WP_Error( 'daymark_already_subscribed', __( 'You already have an active subscription.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		update_user_meta( $user->ID, 'daymark_account_state', 'active' );
		return array( 'provider' => 'development', 'id' => $provider_id, 'development' => true, 'url' => self::checkout_success_url() );
	}

	public static function create_checkout_session( WP_User $user, array $plan, $idempotency_key, $review_id = 0, $quantity = 1 ) {
		$status = self::checkout_status();
		if ( empty( $status['ready'] ) ) return new WP_Error( 'daymark_checkout_gateway_unavailable', $status['message'], array( 'status' => 503, 'environment' => $status['environment'], 'provider' => $status['provider'], 'mode' => $status['mode'] ) );
		if ( self::development_mode() ) return self::create_development_checkout( $user, $plan, $idempotency_key, $quantity );
		if ( 'stripe' === self::provider() ) return Daymark_Stripe::create_checkout_session( $user, $plan, $idempotency_key, $review_id, $quantity );
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_checkout_url' ) ) return new WP_Error( 'daymark_woocommerce_required', __( 'WooCommerce is required for this payment mode.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		if ( ! empty( $plan['trial_days'] ) ) return new WP_Error( 'daymark_woocommerce_trial_unsupported', __( 'Trial periods require Stripe Checkout; WooCommerce core checkout does not provide the recurring trial contract.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( ! empty( $plan['intro_cycles'] ) ) return new WP_Error( 'daymark_woocommerce_intro_unsupported', __( 'Introductory pricing requires Stripe Checkout; WooCommerce core checkout does not provide the recurring introductory-price contract.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		if ( function_exists( 'get_woocommerce_currency' ) && strtoupper( (string) get_woocommerce_currency() ) !== strtoupper( (string) $plan['currency'] ) ) return new WP_Error( 'daymark_woocommerce_currency_mismatch', __( 'The plan currency must match the WooCommerce store currency.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$product = self::ensure_woocommerce_product( $plan );
		$product_id = $product ? $product->get_id() : 0;
		if ( ! $product || ! $product->is_purchasable() ) return new WP_Error( 'daymark_woocommerce_product_missing', __( 'The WooCommerce product is not configured or purchasable.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		$token = wp_generate_uuid4();
		set_transient( 'daymark_wc_checkout_' . $token, array( 'user_id' => $user->ID, 'plan_id' => absint( $plan['id'] ), 'product_id' => $product_id, 'quantity' => max( 1, absint( $quantity ) ), 'idempotency_key' => $idempotency_key, 'review_id' => absint( $review_id ) ), 15 * MINUTE_IN_SECONDS );
		return array( 'provider' => 'woocommerce', 'id' => $token, 'url' => esc_url_raw( add_query_arg( array( 'add-to-cart' => $product_id, 'daymark_token' => $token ), wc_get_checkout_url() ) ) );
	}

	public static function sync_cancel( $provider_id, $immediate, $key ) {
		if ( ! $provider_id || 0 === strpos( (string) $provider_id, 'dev_subscription:' ) ) return true;
		if ( 'stripe' === self::provider() ) return Daymark_Stripe::sync_cancel( $provider_id, $immediate, $key );
		if ( ! self::get_wc_order( $provider_id ) ) return new WP_Error( 'daymark_woocommerce_order_missing', __( 'The WooCommerce order could not be found.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		return true;
	}

	public static function sync_pause( $provider_id, $key ) { return ! $provider_id || 0 === strpos( (string) $provider_id, 'dev_subscription:' ) ? true : new WP_Error( 'daymark_woocommerce_pause_unsupported', __( 'WooCommerce mode does not support provider-side subscription pauses.', 'daymark-subscriptions' ), array( 'status' => 501 ) ); }
	public static function sync_resume( $provider_id, $key, $cancel_scheduled = false ) { return ! $provider_id || 0 === strpos( (string) $provider_id, 'dev_subscription:' ) ? true : ( 'stripe' === self::provider() ? Daymark_Stripe::sync_resume( $provider_id, $key, $cancel_scheduled ) : new WP_Error( 'daymark_woocommerce_resume_unsupported', __( 'WooCommerce mode does not support provider-side subscription resumes.', 'daymark-subscriptions' ), array( 'status' => 501 ) ) ); }

	public static function sync_plan( $provider_id, array $plan, $key, $proration_behavior = 'always_invoice' ) {
		if ( ! $provider_id || 0 === strpos( (string) $provider_id, 'dev_subscription:' ) ) return true;
		if ( 'stripe' === self::provider() ) {
			$price_id = Daymark_Stripe::ensure_price( $plan );
			return is_wp_error( $price_id ) ? $price_id : Daymark_Stripe::sync_plan( $provider_id, $price_id, $key, $proration_behavior );
		}
		return new WP_Error( 'daymark_woocommerce_plan_change_unsupported', __( 'WooCommerce mode does not support provider-side subscription plan changes.', 'daymark-subscriptions' ), array( 'status' => 501 ) );
	}

	public static function preview_plan_change( array $subscription, array $plan ) {
		if ( self::development_mode() ) return array( 'available' => false, 'reason' => 'development_mode', 'message' => __( 'Development mode does not contact a payment provider for proration.', 'daymark-subscriptions' ) );
		if ( 'stripe' === self::provider() ) { $result = Daymark_Stripe::preview_plan_change( $subscription['provider_subscription_id'] ?? '', $plan ); return is_wp_error( $result ) ? array( 'available' => false, 'reason' => 'provider_unavailable', 'message' => $result->get_error_message() ) : $result; }
		return array( 'available' => false, 'reason' => 'provider_preview_unavailable', 'message' => __( 'WooCommerce will confirm any renewal amount through the configured checkout or gateway.', 'daymark-subscriptions' ) );
	}

	private static function ensure_woocommerce_product( array $plan ) {
		$product_id = absint( $plan['woocommerce_product_id'] ?? 0 );
		$product = $product_id ? wc_get_product( $product_id ) : false;
		$is_ghost = $product && '1' === (string) $product->get_meta( '_daymark_ghost_product', true );
		if ( $product && ! $is_ghost ) return $product;
		if ( ! $product ) $product = new WC_Product_Simple();
		$product->set_name( 'Daymark — ' . $plan['name'] );
		$product->set_description( __( 'Managed checkout product for a Daymark subscription plan.', 'daymark-subscriptions' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( empty( $plan['quantity_enabled'] ) );
		$product->set_regular_price( (string) $plan['price'] );
		$product->set_price( (string) $plan['price'] );
		$product->update_meta_data( '_daymark_ghost_product', '1' );
		$saved_id = $product->save();
		if ( ! $saved_id ) return false;
		if ( $saved_id !== $product_id ) Daymark_Subscriptions_DB::update_plan( $plan['id'], array( 'woocommerce_product_id' => $saved_id ) );
		return wc_get_product( $saved_id );
	}

	public function tag_cart_item( $cart_item_data, $product_id ) {
		$token = sanitize_text_field( wp_unslash( $_REQUEST['daymark_token'] ?? '' ) ); $payload = $token ? get_transient( 'daymark_wc_checkout_' . $token ) : false;
		if ( $payload && ( ! is_user_logged_in() || absint( $payload['user_id'] ) === get_current_user_id() ) && absint( $payload['product_id'] ) === absint( $product_id ) ) $cart_item_data['_daymark_checkout_token'] = $token;
		return $cart_item_data;
	}

	public function add_setup_fee( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) return;
		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) return;
		foreach ( $cart->get_cart() as $item ) {
			$token = sanitize_text_field( $item['_daymark_checkout_token'] ?? '' ); $payload = $token ? get_transient( 'daymark_wc_checkout_' . $token ) : false;
			if ( ! $payload || ! empty( $payload['review_id'] ) ) continue;
			$plan = Daymark_Subscriptions_DB::get_plan( absint( $payload['plan_id'] ), true );
			if ( $plan && (float) ( $plan['setup_fee'] ?? 0 ) > 0 ) { $cart->add_fee( __( 'Subscription setup fee', 'daymark-subscriptions' ), (float) $plan['setup_fee'], false ); break; }
		}
	}

	public function tag_order( $order, $data ) {
		if ( 'woocommerce' !== self::provider() ) return;
		foreach ( $order->get_items() as $item ) {
			$token = sanitize_text_field( $item->get_meta( '_daymark_checkout_token', true ) );
			$payload = $token ? get_transient( 'daymark_wc_checkout_' . $token ) : false;
			$user_id = $payload ? absint( $payload['user_id'] ?? 0 ) : 0;
			$user = $user_id ? get_userdata( $user_id ) : false;
			$order_email = method_exists( $order, 'get_billing_email' ) ? sanitize_email( $order->get_billing_email() ) : '';
			$email_matches = $user && $order_email && hash_equals( strtolower( $user->user_email ), strtolower( $order_email ) );
			if ( $payload && $email_matches && ( ! is_user_logged_in() || $user_id === get_current_user_id() ) && absint( $payload['product_id'] ) === absint( $item->get_product_id() ) ) {
				$order->update_meta_data( '_daymark_plan_id', absint( $payload['plan_id'] ) );
				$order->update_meta_data( '_daymark_user_id', $user_id );
				if ( method_exists( $order, 'set_customer_id' ) ) $order->set_customer_id( $user_id );
				$order->update_meta_data( '_daymark_idempotency_key', sanitize_text_field( $payload['idempotency_key'] ) );
				$order->update_meta_data( '_daymark_review_id', absint( $payload['review_id'] ?? 0 ) );
				$order->update_meta_data( '_daymark_quantity', max( 1, absint( $payload['quantity'] ?? $item->get_quantity() ) ) );
				delete_transient( 'daymark_wc_checkout_' . $token );
				break;
			}
		}
	}

	public function payment_complete( $order_id ) { $order = wc_get_order( $order_id ); if ( $order && $order->get_meta( '_daymark_renewal_subscription_id', true ) ) { $this->activate_renewal_order( $order ); return; } $this->activate_order( $order ); }
	public function order_failed( $order_id ) { $order = wc_get_order( $order_id ); if ( $order && $order->get_meta( '_daymark_renewal_subscription_id', true ) ) { $this->set_renewal_order_status( $order, 'failed' ); return; } $this->set_order_status( $order, 'past_due' ); }
	public function order_cancelled( $order_id ) { $this->set_order_status( wc_get_order( $order_id ), 'canceled' ); }
	public function renewal_due( $subscription ) {
		if ( 0 !== strpos( (string) $subscription['provider_subscription_id'], 'wc_order:' ) || ! function_exists( 'wc_create_order' ) ) return;
		$order = $this->create_renewal_order( $subscription );
		$operation = ! empty( $subscription['renewal_operation_key'] ) ? Daymark_Subscriptions_DB::get_operation_by_key( $subscription['renewal_operation_key'] ) : false;
		if ( ! $order ) { if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => 1, 'last_error' => 'The WooCommerce renewal order could not be created.' ) ); return; }
		$automatic = apply_filters( 'daymark_woocommerce_renewal_charge', false, $order, $subscription, $subscription['renewal_operation_key'] ?? '' );
		$automatic_status = is_array( $automatic ) ? sanitize_key( $automatic['status'] ?? 'pending' ) : ( true === $automatic ? 'paid' : 'pending' );
		if ( 'failed' === $automatic_status ) {
			if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => (int) $operation['attempts'] + 1, 'last_error' => sanitize_textarea_field( $automatic['message'] ?? 'WooCommerce renewal adapter reported a failed charge.' ) ) );
			$this->set_renewal_order_status( $order, 'failed' );
			return;
		}
		if ( 'paid' === $automatic_status && $order->is_paid() ) {
			$this->activate_renewal_order( $order );
			return;
		}
		do_action( 'daymark_woocommerce_renewal_due', $subscription, $order );
	}
	public function woocommerce_return_url( $return_url, $order ) {
		return $order && is_object( $order ) && $order->get_meta( '_daymark_plan_id', true ) ? self::checkout_success_url() : $return_url;
	}

	private function activate_order( $order ) {
		if ( ! $order || 'woocommerce' !== self::provider() ) return;
		$plan_id = absint( $order->get_meta( '_daymark_plan_id', true ) ); $user_id = absint( $order->get_meta( '_daymark_user_id', true ) );
		$checkout_token = '';
		foreach ( $order->get_items() as $item ) { $checkout_token = sanitize_text_field( $item->get_meta( '_daymark_checkout_token', true ) ); if ( $checkout_token ) break; }
		$plan = Daymark_Subscriptions_DB::get_plan( $plan_id, true );
		$provider_id = 'wc_order:' . $order->get_id();
		if ( ! $plan || ! $user_id ) return;
		$now = current_time( 'mysql', true ); $end = gmdate( 'Y-m-d H:i:s', strtotime( '+1 ' . $plan['interval_unit'], strtotime( $now . ' UTC' ) ) );
		$review_id = absint( $order->get_meta( '_daymark_review_id', true ) );
		if ( $review_id ) {
			$activated = Daymark_Subscriptions_DB::activate_approved_subscription( $review_id, array( 'status' => self::development_mode() ? 'active' : 'pending_activation', 'current_period_start' => $now, 'current_period_end' => $end, 'provider_customer_id' => '', 'provider_subscription_id' => $provider_id, 'checkout_session_id' => '', 'idempotency_key' => 'wc:' . $order->get_id() ) );
			if ( $activated && ! self::development_mode() ) update_user_meta( $user_id, 'daymark_account_state', 'pending_activation' );
			if ( $activated && $checkout_token ) Daymark_Subscriptions_DB::update_checkout_by_provider_session( $checkout_token, array( 'status' => 'verified' ) );
			if ( ! $activated ) return;
			return;
		}
		$created = Daymark_Subscriptions_DB::create_subscription_once( array( 'user_id' => $user_id, 'plan_id' => $plan_id, 'status' => self::development_mode() ? 'active' : 'pending_activation', 'started_at' => $now, 'current_period_start' => $now, 'current_period_end' => $end, 'cancel_at_period_end' => 0, 'canceled_at' => null, 'paused_at' => null, 'paused_until' => null, 'pending_plan_id' => null, 'change_effective_at' => null, 'last_payment_status' => 'paid', 'failure_count' => 0, 'quantity' => max( 1, absint( $order->get_meta( '_daymark_quantity', true ) ) ), 'provider_customer_id' => '', 'provider_subscription_id' => $provider_id, 'checkout_session_id' => '', 'idempotency_key' => 'wc:' . $order->get_id(), 'created_at' => $now ) );
		if ( $created && ! self::development_mode() ) update_user_meta( $user_id, 'daymark_account_state', 'pending_activation' );
		if ( $created && $checkout_token ) Daymark_Subscriptions_DB::update_checkout_by_provider_session( $checkout_token, array( 'status' => 'verified' ) );
		if ( $created && ! empty( $created['conflict'] ) ) do_action( 'daymark_woocommerce_duplicate_subscription', $order, $created['subscription'] );
	}

	private function create_renewal_order( $subscription ) {
		$existing = ! empty( $subscription['renewal_order_id'] ) && function_exists( 'wc_get_order' ) ? wc_get_order( absint( $subscription['renewal_order_id'] ) ) : false;
		if ( $existing ) { if ( ! empty( $subscription['renewal_operation_key'] ) ) { $existing->update_meta_data( '_daymark_renewal_operation_key', sanitize_text_field( $subscription['renewal_operation_key'] ) ); $existing->save(); } return $existing; }
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ), true );
		if ( ! $plan ) return false;
		$product = self::ensure_woocommerce_product( $plan );
		if ( ! $product ) return false;
		$order = wc_create_order( array( 'customer_id' => absint( $subscription['user_id'] ) ) );
		if ( is_wp_error( $order ) || ! $order ) return false;
		$order->add_product( $product, max( 1, absint( $subscription['quantity'] ?? 1 ) ) );
		$order->update_meta_data( '_daymark_renewal_subscription_id', absint( $subscription['id'] ) );
		$order->update_meta_data( '_daymark_renewal_operation_key', sanitize_text_field( $subscription['renewal_operation_key'] ?? '' ) );
		$order->update_meta_data( '_daymark_plan_id', absint( $plan['id'] ) );
		$order->update_meta_data( '_daymark_quantity', max( 1, absint( $subscription['quantity'] ?? 1 ) ) );
		$order->set_customer_id( absint( $subscription['user_id'] ) );
		$order->calculate_totals();
		$order->set_status( 'pending', __( 'Daymark renewal awaiting payment.', 'daymark-subscriptions' ) );
		$order->save();
		if ( ! Daymark_Subscriptions_DB::mark_woocommerce_renewal_order( $subscription['id'], $order->get_id() ) ) return false;
		return $order;
	}

	private function activate_renewal_order( $order ) {
		$subscription_id = absint( $order->get_meta( '_daymark_renewal_subscription_id', true ) );
		$subscription = $subscription_id ? Daymark_Subscriptions_DB::get_subscription_by_id( $subscription_id ) : false;
		if ( ! $subscription || ! Daymark_Subscriptions_DB::record_renewal( $subscription_id, 'paid', 'wc_renewal:' . $order->get_id() ) ) return;
		$operation_key = sanitize_text_field( $order->get_meta( '_daymark_renewal_operation_key', true ) ); $operation = $operation_key ? Daymark_Subscriptions_DB::get_operation_by_key( $operation_key ) : false;
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'succeeded', array( 'attempts' => (int) $operation['attempts'] + 1, 'last_error' => null, 'provider_response' => wp_json_encode( array( 'order_id' => $order->get_id(), 'status' => 'paid' ) ), 'local_response' => wp_json_encode( array( 'subscription_id' => $subscription_id ) ) ) );
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ) );
		if ( $plan ) Daymark_Subscriptions_DB::save_invoice( array( 'subscription_id' => $subscription_id, 'user_id' => $subscription['user_id'], 'plan_id' => $subscription['plan_id'], 'provider_invoice_id' => 'wc_renewal:' . $order->get_id(), 'amount' => (float) $order->get_total(), 'currency' => $plan['currency'], 'status' => 'paid', 'invoice_url' => $order->get_view_order_url(), 'issued_at' => current_time( 'mysql', true ), 'created_at' => current_time( 'mysql', true ) ) );
		do_action( 'daymark_subscription_payment_updated', Daymark_Subscriptions_DB::get_subscription_by_id( $subscription_id ), 'paid' );
	}

	private function set_renewal_order_status( $order, $status ) {
		$subscription_id = absint( $order->get_meta( '_daymark_renewal_subscription_id', true ) );
		if ( ! $subscription_id ) return;
		$operation_key = sanitize_text_field( $order->get_meta( '_daymark_renewal_operation_key', true ) ); $operation = $operation_key ? Daymark_Subscriptions_DB::get_operation_by_key( $operation_key ) : false;
		if ( $operation ) Daymark_Subscriptions_DB::update_operation( $operation['id'], 'failed', array( 'attempts' => (int) $operation['attempts'] + 1, 'last_error' => 'WooCommerce renewal payment failed.' ) );
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( $subscription_id );
		if ( $subscription ) do_action( 'daymark_subscription_payment_updated', $subscription, 'failed' );
	}

	private function set_order_status( $order, $status ) {
		if ( ! $order ) return;
		$local = Daymark_Subscriptions_DB::get_subscription_by_provider_id( 'wc_order:' . $order->get_id() );
		if ( ! $local ) return;
		if ( in_array( $local['status'], array( 'canceled', 'expired' ), true ) ) return;
		$data = array( 'status' => $status ); $formats = array( '%s' );
		if ( 'canceled' === $status ) { $data['canceled_at'] = current_time( 'mysql', true ); $formats[] = '%s'; }
		if ( false === Daymark_Subscriptions_DB::update_subscription( $local['id'], $local['user_id'], $data, $formats ) ) return;
		$updated_local = Daymark_Subscriptions_DB::get_subscription_by_provider_id( 'wc_order:' . $order->get_id() ) ?: $local;
		if ( 'past_due' === $status ) do_action( 'daymark_subscription_payment_updated', $updated_local, 'failed' );
		if ( 'canceled' === $status ) do_action( 'daymark_subscription_provider_cancelled', $updated_local );
	}
	private static function get_wc_order( $provider_id ) { if ( 0 !== strpos( $provider_id, 'wc_order:' ) || ! function_exists( 'wc_get_order' ) ) return false; return wc_get_order( absint( substr( $provider_id, strlen( 'wc_order:' ) ) ) ); }
}
