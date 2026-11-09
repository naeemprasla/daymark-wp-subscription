<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Stripe {
	const MODE_OPTION = 'daymark_stripe_mode';
	const PRICE_MAP_OPTION = 'daymark_stripe_price_map';
	const PRICE_HISTORY_OPTION = 'daymark_stripe_price_history';
	const INTRO_COUPON_MAP_OPTION = 'daymark_stripe_intro_coupon_map';
	const SETUP_FEE_MAP_OPTION = 'daymark_stripe_setup_fee_map';
	const AUTOMATIC_TAX_OPTION = 'daymark_stripe_automatic_tax';

	public static function mode() {
		$mode = defined( 'DAYMARK_STRIPE_MODE' ) ? DAYMARK_STRIPE_MODE : get_option( self::MODE_OPTION, 'test' );
		return in_array( $mode, array( 'test', 'live' ), true ) ? $mode : 'test';
	}

	public static function mode_label() { return 'live' === self::mode() ? __( 'Live', 'daymark-subscriptions' ) : __( 'Sandbox', 'daymark-subscriptions' ); }

	public static function secret_key() {
		$mode_constant = 'live' === self::mode() ? 'DAYMARK_STRIPE_LIVE_SECRET_KEY' : 'DAYMARK_STRIPE_TEST_SECRET_KEY';
		if ( defined( $mode_constant ) ) return (string) constant( $mode_constant );
		if ( 'test' === self::mode() && defined( 'DAYMARK_STRIPE_SECRET_KEY' ) ) return (string) DAYMARK_STRIPE_SECRET_KEY;
		return (string) get_option( 'daymark_stripe_' . self::mode() . '_secret_key', 'test' === self::mode() ? get_option( 'daymark_stripe_secret_key', '' ) : '' );
	}

	public static function webhook_secret() {
		$mode_constant = 'live' === self::mode() ? 'DAYMARK_STRIPE_LIVE_WEBHOOK_SECRET' : 'DAYMARK_STRIPE_TEST_WEBHOOK_SECRET';
		if ( defined( $mode_constant ) ) return (string) constant( $mode_constant );
		if ( 'test' === self::mode() && defined( 'DAYMARK_STRIPE_WEBHOOK_SECRET' ) ) return (string) DAYMARK_STRIPE_WEBHOOK_SECRET;
		return (string) get_option( 'daymark_stripe_' . self::mode() . '_webhook_secret', 'test' === self::mode() ? get_option( 'daymark_stripe_webhook_secret', '' ) : '' );
	}
	public static function configured() { return '' !== self::secret_key() && '' !== self::webhook_secret(); }

	public static function test_connection() {
		if ( '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe secret key is not configured for this mode.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		$response = wp_remote_get( 'https://api.stripe.com/v1/account', array( 'timeout' => 15, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( self::secret_key() . ':' ) ) ) );
		if ( is_wp_error( $response ) ) return new WP_Error( 'daymark_stripe_connection_failed', __( 'Stripe could not be reached.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		$code = wp_remote_retrieve_response_code( $response ); $body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['id'] ) ) return new WP_Error( 'daymark_stripe_connection_rejected', __( 'Stripe rejected the configured credentials.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		if ( ( 'live' === self::mode() ) !== ! empty( $body['livemode'] ) ) return new WP_Error( 'daymark_stripe_mode_mismatch', __( 'The Stripe key does not match the selected Sandbox/Live mode.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		return array( 'provider' => 'stripe', 'mode' => self::mode(), 'configured' => self::configured(), 'account_id' => sanitize_text_field( $body['id'] ), 'livemode' => ! empty( $body['livemode'] ) );
	}

	public static function create_checkout_session( WP_User $user, array $plan, $idempotency_key, $review_id = 0, $quantity = 1 ) {
		$price_id = self::ensure_price( $plan );
		if ( is_wp_error( $price_id ) ) return $price_id;
		$params = array(
			'mode' => 'subscription',
			'line_items' => array( array( 'price' => $price_id, 'quantity' => max( 1, absint( $quantity ) ) ) ),
			'customer_email' => $user->user_email,
			'client_reference_id' => (string) $user->ID,
			'success_url' => Daymark_Payment_Provider::checkout_success_url(),
			'cancel_url' => add_query_arg( 'daymark_checkout', 'cancel', home_url( '/' ) ),
			'metadata' => array( 'daymark_user_id' => (string) $user->ID, 'daymark_plan_id' => (string) $plan['id'], 'daymark_review_id' => (string) absint( $review_id ), 'daymark_quantity' => (string) max( 1, absint( $quantity ) ) ),
		);
		if ( ! empty( $plan['trial_days'] ) ) $params['subscription_data[trial_period_days]'] = absint( $plan['trial_days'] );
		$intro_coupon = self::ensure_intro_coupon( $plan );
		if ( is_wp_error( $intro_coupon ) ) return $intro_coupon;
		if ( $intro_coupon ) $params['discounts[0][coupon]'] = $intro_coupon;
		if ( ! empty( $plan['allow_promotion_codes'] ) && ! $intro_coupon ) $params['allow_promotion_codes'] = 'true';
		if ( ! empty( $plan['setup_fee'] ) ) {
			$setup_price = self::ensure_setup_fee_price( $plan );
			if ( is_wp_error( $setup_price ) ) return $setup_price;
			$params['line_items'][] = array( 'price' => $setup_price, 'quantity' => 1 );
		}
		if ( get_option( self::AUTOMATIC_TAX_OPTION, false ) ) $params['automatic_tax[enabled]'] = 'true';
		$body = self::request( 'POST', 'checkout/sessions', $params, $idempotency_key );
		if ( is_wp_error( $body ) || empty( $body['url'] ) ) return is_wp_error( $body ) ? $body : new WP_Error( 'daymark_stripe_checkout_failed', __( 'Stripe could not create checkout.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		return array( 'id' => sanitize_text_field( $body['id'] ), 'url' => esc_url_raw( $body['url'] ) );
	}

	public static function create_billing_portal_session( $customer_id, $return_url ) {
		if ( ! $customer_id || '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_customer_missing', __( 'A Stripe billing profile is not available for this subscription.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$session = self::request( 'POST', 'billing_portal/sessions', array( 'customer' => sanitize_text_field( $customer_id ), 'return_url' => esc_url_raw( $return_url ) ), 'daymark-portal-' . md5( $customer_id . '|' . $return_url ) );
		if ( is_wp_error( $session ) || empty( $session['url'] ) ) return is_wp_error( $session ) ? $session : new WP_Error( 'daymark_stripe_portal_failed', __( 'Stripe could not open billing settings.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		return array( 'url' => esc_url_raw( $session['url'] ) );
	}

	public static function billing_action( $invoice_id, $action, $amount = 0, $idempotency_key = '' ) {
		$invoice_id = sanitize_text_field( $invoice_id );
		$action = sanitize_key( $action );
		if ( ! $invoice_id || '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_invoice_missing', __( 'The Stripe invoice is not available.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$invoice = self::request( 'GET', 'invoices/' . rawurlencode( $invoice_id ) );
		if ( is_wp_error( $invoice ) ) return $invoice;
		if ( 'refund' === $action || 'credit' === $action ) {
			$amount = (int) round( (float) $amount * 100 );
			$max = absint( $invoice['amount_paid'] ?? $invoice['total'] ?? 0 );
			if ( $amount < 1 || ( $max && $amount > $max ) ) return new WP_Error( 'daymark_invoice_amount_invalid', __( 'The adjustment amount is invalid for this invoice.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
			$endpoint = 'credit' === $action ? 'credit_notes' : 'refunds';
			$params = 'credit' === $action ? array( 'invoice' => $invoice_id, 'amount' => (string) $amount ) : array( 'payment_intent' => sanitize_text_field( $invoice['payment_intent'] ?? '' ), 'amount' => (string) $amount );
			if ( empty( $params['payment_intent'] ) && 'refund' === $action ) return new WP_Error( 'daymark_invoice_payment_missing', __( 'Stripe did not expose a refundable payment for this invoice.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
			return self::request( 'POST', $endpoint, $params, $idempotency_key );
		}
		$paths = array( 'void' => 'invoices/' . rawurlencode( $invoice_id ) . '/void', 'uncollectible' => 'invoices/' . rawurlencode( $invoice_id ) . '/mark_uncollectible', 'resend' => 'invoices/' . rawurlencode( $invoice_id ) . '/send_invoice', 'pay' => 'invoices/' . rawurlencode( $invoice_id ) . '/pay' );
		if ( empty( $paths[ $action ] ) ) return new WP_Error( 'daymark_invoice_action_invalid', __( 'This billing action is not supported.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		return self::request( 'POST', $paths[ $action ], array(), $idempotency_key );
	}

	public static function ensure_price( array $plan ) {
		$mode = self::mode(); $map = get_option( self::PRICE_MAP_OPTION, array() ); $plan_id = absint( $plan['id'] );
		if ( ! empty( $map[ $mode ][ $plan_id ] ) ) return sanitize_text_field( $map[ $mode ][ $plan_id ] );
		if ( 'test' === $mode && ! empty( $plan['stripe_price_id'] ) ) return sanitize_text_field( $plan['stripe_price_id'] );
		if ( '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe is not configured.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		$interval = in_array( $plan['interval_unit'], array( 'day', 'month', 'year' ), true ) ? $plan['interval_unit'] : 'month';
		$price_params = array( 'currency' => strtolower( $plan['currency'] ), 'unit_amount' => (string) round( (float) $plan['price'] * 100 ), 'recurring[interval]' => $interval, 'product_data[name]' => $plan['name'], 'product_data[description]' => $plan['description'] );
		if ( in_array( $plan['tax_behavior'] ?? 'automatic', array( 'inclusive', 'exclusive' ), true ) ) $price_params['tax_behavior'] = $plan['tax_behavior'];
		$price = self::request( 'POST', 'prices', $price_params, 'daymark-plan-price-' . absint( $plan['id'] ) );
		if ( is_wp_error( $price ) ) return $price;
		$price_id = sanitize_text_field( $price['id'] ?? '' );
		if ( ! $price_id ) return new WP_Error( 'daymark_stripe_price_storage_failed', __( 'The Stripe price could not be linked to this plan.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		$map[ $mode ][ $plan_id ] = $price_id;
		if ( ! update_option( self::PRICE_MAP_OPTION, $map, false ) ) return new WP_Error( 'daymark_stripe_price_storage_failed', __( 'The Stripe price could not be linked to this plan.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		if ( 'test' === $mode && ! Daymark_Subscriptions_DB::update_plan( $plan['id'], array( 'stripe_price_id' => $price_id ) ) ) return new WP_Error( 'daymark_stripe_price_storage_failed', __( 'The Stripe price could not be linked to this plan.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		return $price_id;
	}

	public static function ensure_setup_fee_price( array $plan ) {
		$amount = (float) ( $plan['setup_fee'] ?? 0 );
		if ( $amount <= 0 ) return '';
		$mode = self::mode(); $map = get_option( self::SETUP_FEE_MAP_OPTION, array() ); $plan_id = absint( $plan['id'] );
		if ( ! empty( $map[ $mode ][ $plan_id ] ) ) return sanitize_text_field( $map[ $mode ][ $plan_id ] );
		if ( '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe is not configured.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		$params = array( 'currency' => strtolower( $plan['currency'] ), 'unit_amount' => (string) round( $amount * 100 ), 'product_data[name]' => $plan['name'] . ' setup fee', 'product_data[description]' => __( 'One-time setup fee for this subscription plan.', 'daymark-subscriptions' ) );
		if ( in_array( $plan['tax_behavior'] ?? 'automatic', array( 'inclusive', 'exclusive' ), true ) ) $params['tax_behavior'] = $plan['tax_behavior'];
		$price = self::request( 'POST', 'prices', $params, 'daymark-setup-fee-' . $mode . '-' . $plan_id . '-' . md5( $amount . '|' . ( $plan['currency'] ?? '' ) ) );
		if ( is_wp_error( $price ) ) return $price;
		$price_id = sanitize_text_field( $price['id'] ?? '' ); if ( ! $price_id ) return new WP_Error( 'daymark_setup_fee_storage_failed', __( 'The setup fee could not be linked to this plan.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		$map[ $mode ][ $plan_id ] = $price_id;
		return update_option( self::SETUP_FEE_MAP_OPTION, $map, false ) ? $price_id : new WP_Error( 'daymark_setup_fee_storage_failed', __( 'The setup fee could not be linked to this plan.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
	}

	public static function ensure_intro_coupon( array $plan ) {
		$cycles = absint( $plan['intro_cycles'] ?? 0 ); $intro_price = (float) ( $plan['intro_price'] ?? 0 ); $price = (float) ( $plan['price'] ?? 0 );
		if ( ! $cycles ) return '';
		if ( $intro_price < 0 || $intro_price >= $price || ! in_array( $plan['interval_unit'] ?? '', array( 'month', 'year' ), true ) ) return new WP_Error( 'daymark_intro_pricing_invalid', __( 'This introductory offer requires a lower monthly or yearly price.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$mode = self::mode(); $map = get_option( self::INTRO_COUPON_MAP_OPTION, array() ); $plan_id = absint( $plan['id'] );
		if ( ! empty( $map[ $mode ][ $plan_id ] ) ) return sanitize_text_field( $map[ $mode ][ $plan_id ] );
		if ( '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe is not configured.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		$months = 'year' === $plan['interval_unit'] ? $cycles * 12 : $cycles; $amount_off = (int) round( ( $price - $intro_price ) * 100 );
		$coupon = self::request( 'POST', 'coupons', array( 'duration' => 'repeating', 'duration_in_months' => $months, 'amount_off' => $amount_off, 'currency' => strtolower( $plan['currency'] ), 'name' => $plan['name'] . ' introductory offer' ), 'daymark-intro-coupon-' . $mode . '-' . $plan_id . '-' . md5( $price . '|' . $intro_price . '|' . $cycles ) );
		if ( is_wp_error( $coupon ) ) return $coupon;
		$coupon_id = sanitize_text_field( $coupon['id'] ?? '' ); if ( ! $coupon_id ) return new WP_Error( 'daymark_stripe_coupon_storage_failed', __( 'The introductory offer could not be linked to this plan.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		$map[ $mode ][ $plan_id ] = $coupon_id;
		return update_option( self::INTRO_COUPON_MAP_OPTION, $map, false ) ? $coupon_id : new WP_Error( 'daymark_stripe_coupon_storage_failed', __( 'The introductory offer could not be linked to this plan.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
	}

	public static function invalidate_intro_mapping( $plan_id ) {
		$map = get_option( self::INTRO_COUPON_MAP_OPTION, array() ); $changed = false; $plan_id = absint( $plan_id );
		foreach ( array( 'test', 'live' ) as $mode ) if ( ! empty( $map[ $mode ][ $plan_id ] ) ) { unset( $map[ $mode ][ $plan_id ] ); $changed = true; }
		return ! $changed || update_option( self::INTRO_COUPON_MAP_OPTION, $map, false );
	}

	public static function invalidate_setup_fee_mapping( $plan_id ) {
		$map = get_option( self::SETUP_FEE_MAP_OPTION, array() ); $changed = false; $plan_id = absint( $plan_id );
		foreach ( array( 'test', 'live' ) as $mode ) if ( ! empty( $map[ $mode ][ $plan_id ] ) ) { unset( $map[ $mode ][ $plan_id ] ); $changed = true; }
		return ! $changed || update_option( self::SETUP_FEE_MAP_OPTION, $map, false );
	}

	public static function invalidate_price_mapping( $plan_id, $legacy_price_id = '' ) {
		$plan_id = absint( $plan_id );
		if ( ! $plan_id ) return false;
		$map = get_option( self::PRICE_MAP_OPTION, array() ); $history = get_option( self::PRICE_HISTORY_OPTION, array() ); $changed = false;
		foreach ( array( 'test', 'live' ) as $mode ) {
			$price_id = sanitize_text_field( $map[ $mode ][ $plan_id ] ?? '' );
			if ( ! $price_id && 'test' === $mode ) $price_id = sanitize_text_field( $legacy_price_id );
			if ( ! $price_id ) continue;
			$history[ $mode ][ $price_id ] = $plan_id; unset( $map[ $mode ][ $plan_id ] ); $changed = true;
		}
		if ( ! $changed ) return true;
		return update_option( self::PRICE_MAP_OPTION, $map, false ) && update_option( self::PRICE_HISTORY_OPTION, $history, false );
	}

	public static function handle_webhook( $payload, $signature ) {
		if ( ! self::verify_signature( $payload, $signature ) ) return new WP_Error( 'daymark_invalid_stripe_signature', __( 'Invalid webhook signature.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) return new WP_Error( 'daymark_invalid_stripe_event', __( 'Invalid webhook event.', 'daymark-subscriptions' ), array( 'status' => 400 ) );
		$claimed = Daymark_Subscriptions_DB::mark_stripe_event( $event['id'], $event['type'], $payload );
		if ( null === $claimed ) return new WP_Error( 'daymark_webhook_storage_failed', __( 'Webhook could not be recorded.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		if ( ! $claimed ) return true;
		return self::process_event( $event );
	}

	public static function replay_event( $event_id ) {
		$stored = Daymark_Subscriptions_DB::get_stripe_event( $event_id );
		if ( ! $stored || empty( $stored['payload'] ) ) return new WP_Error( 'daymark_webhook_replay_missing', __( 'The webhook payload is not available for replay.', 'daymark-subscriptions' ), array( 'status' => 404 ) );
		$event = json_decode( $stored['payload'], true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) return new WP_Error( 'daymark_webhook_replay_invalid', __( 'The stored webhook payload is invalid.', 'daymark-subscriptions' ), array( 'status' => 422 ) );
		$claimed = Daymark_Subscriptions_DB::mark_stripe_event( $event['id'], $event['type'], $stored['payload'] );
		if ( null === $claimed ) return new WP_Error( 'daymark_webhook_replay_storage_failed', __( 'The webhook could not be claimed for replay.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		if ( ! $claimed ) return true;
		return self::process_event( $event );
	}

	private static function process_event( array $event ) {
		$object = $event['data']['object'] ?? array(); $handled = true;
		switch ( $event['type'] ) {
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded': $handled = self::checkout_completed( $object ); break;
			case 'customer.subscription.updated': $handled = self::subscription_updated( $object ); break;
			case 'customer.subscription.deleted': $handled = self::subscription_deleted( $object ); break;
			case 'invoice.payment_succeeded': $handled = self::invoice_succeeded( $object ); break;
			case 'invoice.paid': $handled = self::invoice_succeeded( $object ); break;
			case 'invoice.finalized': $handled = self::record_invoice( $object, 'open' ); break;
			case 'invoice.payment_failed': $handled = self::invoice_failed( $object ); break;
			case 'invoice.payment_action_required': $handled = self::payment_issue( $object, 'action_required' ); break;
			case 'invoice.voided': $handled = self::payment_issue( $object, 'voided' ); break;
			case 'invoice.marked_uncollectible': $handled = self::payment_issue( $object, 'uncollectible' ); break;
			case 'charge.dispute.created': $handled = self::payment_issue( $object, 'disputed' ); break;
			case 'charge.refunded': $handled = self::payment_issue( $object, 'refunded' ); break;
		}
		if ( ! $handled ) { Daymark_Subscriptions_DB::release_stripe_event( $event['id'] ); return new WP_Error( 'daymark_webhook_processing_failed', __( 'Webhook processing failed.', 'daymark-subscriptions' ), array( 'status' => 503 ) ); }
		if ( ! Daymark_Subscriptions_DB::complete_stripe_event( $event['id'] ) ) return new WP_Error( 'daymark_webhook_completion_failed', __( 'Webhook state could not be finalized.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		return true;
	}

	private static function request( $method, $path, $params = array(), $idempotency_key = '' ) {
		$started = microtime( true ); $operation = preg_replace( '/\/[^\/]+/', '/{id}', ltrim( $path, '/' ) );
		$headers = array( 'Authorization' => 'Basic ' . base64_encode( self::secret_key() . ':' ), 'Content-Type' => 'application/x-www-form-urlencoded' );
		if ( $idempotency_key ) $headers['Idempotency-Key'] = $idempotency_key;
		$args = array( 'method' => $method, 'timeout' => 15, 'headers' => $headers );
		if ( $params ) $args['body'] = http_build_query( $params, '', '&' );
		$response = wp_remote_request( 'https://api.stripe.com/v1/' . ltrim( $path, '/' ), $args );
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		if ( is_wp_error( $response ) ) { Daymark_Subscriptions_DB::record_provider_metric( 'provider_request_failed', $operation, 0, $latency ); return new WP_Error( 'daymark_stripe_request_failed', __( 'Stripe could not be reached.', 'daymark-subscriptions' ), array( 'status' => 502 ) ); }
		$code = wp_remote_retrieve_response_code( $response ); $body = json_decode( wp_remote_retrieve_body( $response ), true );
		Daymark_Subscriptions_DB::record_provider_metric( $code >= 200 && $code < 300 ? 'provider_request' : 'provider_request_failed', $operation, $code, $latency );
		if ( $code < 200 || $code >= 300 ) return new WP_Error( 'daymark_stripe_request_failed', __( 'Stripe rejected the subscription change.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		return is_array( $body ) ? $body : array();
	}

	public static function sync_cancel( $provider_id, $immediate, $key ) {
		if ( ! $provider_id ) return true;
		if ( '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe is not configured.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		return $immediate ? self::request( 'DELETE', 'subscriptions/' . rawurlencode( $provider_id ), array(), $key ) : self::request( 'POST', 'subscriptions/' . rawurlencode( $provider_id ), array( 'cancel_at_period_end' => 'true' ), $key );
	}

	public static function sync_pause( $provider_id, $key ) {
		if ( ! $provider_id ) return true;
		return '' === self::secret_key() ? new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe is not configured.', 'daymark-subscriptions' ), array( 'status' => 503 ) ) : self::request( 'POST', 'subscriptions/' . rawurlencode( $provider_id ), array( 'pause_collection[behavior]' => 'void' ), $key );
	}

	public static function sync_resume( $provider_id, $key, $cancel_scheduled = false ) {
		if ( ! $provider_id ) return true;
		return '' === self::secret_key() ? new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe is not configured.', 'daymark-subscriptions' ), array( 'status' => 503 ) ) : self::request( 'POST', 'subscriptions/' . rawurlencode( $provider_id ), $cancel_scheduled ? array( 'cancel_at_period_end' => 'false' ) : array( 'pause_collection' => '' ), $key );
	}

	public static function sync_plan( $provider_id, $price_id, $key, $proration_behavior = 'always_invoice' ) {
		if ( ! $provider_id ) return true;
		if ( ! $price_id || '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_not_configured', __( 'Stripe plan mapping is not configured.', 'daymark-subscriptions' ), array( 'status' => 503 ) );
		$subscription = self::request( 'GET', 'subscriptions/' . rawurlencode( $provider_id ) );
		if ( is_wp_error( $subscription ) ) return $subscription;
		$item_id = sanitize_text_field( $subscription['items']['data'][0]['id'] ?? '' );
		$proration_behavior = in_array( $proration_behavior, array( 'always_invoice', 'none', 'create_prorations' ), true ) ? $proration_behavior : 'always_invoice';
		return $item_id ? self::request( 'POST', 'subscriptions/' . rawurlencode( $provider_id ), array( 'items[0][id]' => $item_id, 'items[0][price]' => $price_id, 'proration_behavior' => $proration_behavior, 'payment_behavior' => 'error_if_incomplete' ), $key ) : new WP_Error( 'daymark_stripe_item_missing', __( 'Stripe subscription item is missing.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
	}

	public static function preview_plan_change( $provider_id, array $plan ) {
		if ( ! $provider_id || '' === self::secret_key() ) return new WP_Error( 'daymark_stripe_preview_unavailable', __( 'Stripe preview is not configured for this subscription.', 'daymark-subscriptions' ), array( 'status' => 409 ) );
		$price_id = self::ensure_price( $plan ); if ( is_wp_error( $price_id ) ) return $price_id;
		$subscription = self::request( 'GET', 'subscriptions/' . rawurlencode( $provider_id ) ); if ( is_wp_error( $subscription ) ) return $subscription;
		$item_id = sanitize_text_field( $subscription['items']['data'][0]['id'] ?? '' );
		if ( ! $item_id ) return new WP_Error( 'daymark_stripe_item_missing', __( 'Stripe subscription item is missing.', 'daymark-subscriptions' ), array( 'status' => 502 ) );
		$preview = self::request( 'GET', 'invoices/upcoming?' . http_build_query( array( 'customer' => sanitize_text_field( $subscription['customer'] ?? '' ), 'subscription' => sanitize_text_field( $provider_id ), 'subscription_items[0][id]' => $item_id, 'subscription_items[0][price]' => $price_id, 'subscription_proration_behavior' => 'always_invoice' ), '', '&' ) );
		if ( is_wp_error( $preview ) ) return $preview;
		return array( 'available' => true, 'currency' => strtoupper( sanitize_text_field( $preview['currency'] ?? $plan['currency'] ) ), 'amount_due' => isset( $preview['amount_due'] ) ? (float) $preview['amount_due'] / 100 : null, 'total' => isset( $preview['total'] ) ? (float) $preview['total'] / 100 : null, 'invoice_id' => sanitize_text_field( $preview['id'] ?? '' ) );
	}

	private static function verify_signature( $payload, $header ) {
		if ( strlen( (string) $header ) > 4096 ) return false;
		$timestamp = 0; $signatures = array();
		foreach ( explode( ',', (string) $header ) as $part ) { $pair = explode( '=', trim( $part ), 2 ); if ( count( $pair ) !== 2 ) continue; if ( 't' === $pair[0] ) $timestamp = absint( $pair[1] ); if ( 'v1' === $pair[0] ) $signatures[] = $pair[1]; }
		if ( ! $timestamp || abs( time() - $timestamp ) > 300 || ! self::webhook_secret() ) return false;
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, self::webhook_secret() );
		foreach ( $signatures as $signature ) if ( hash_equals( $expected, $signature ) ) return true;
		return false;
	}

	private static function checkout_completed( $session ) {
		$payment_status = sanitize_key( $session['payment_status'] ?? '' );
		if ( 'subscription' !== sanitize_key( $session['mode'] ?? '' ) || ! in_array( $payment_status, array( 'paid', 'no_payment_required' ), true ) ) return true;
		$user_id = absint( $session['metadata']['daymark_user_id'] ?? 0 ); $plan_id = absint( $session['metadata']['daymark_plan_id'] ?? 0 ); $plan = Daymark_Subscriptions_DB::get_plan( $plan_id, true );
		$provider_id = sanitize_text_field( $session['subscription'] ?? '' ); $session_id = sanitize_text_field( $session['id'] ?? '' );
		if ( ! $user_id || ! $plan || ! $provider_id || ! $session_id ) return false;
		$now = current_time( 'mysql', true ); $end = gmdate( 'Y-m-d H:i:s', strtotime( '+1 ' . $plan['interval_unit'], strtotime( $now . ' UTC' ) ) );
		$review_id = absint( $session['metadata']['daymark_review_id'] ?? 0 );
		if ( $review_id ) {
			$activated = Daymark_Subscriptions_DB::activate_approved_subscription( $review_id, array( 'status' => 'pending_activation', 'current_period_start' => $now, 'current_period_end' => $end, 'provider_customer_id' => sanitize_text_field( $session['customer'] ?? '' ), 'provider_subscription_id' => $provider_id, 'checkout_session_id' => $session_id, 'idempotency_key' => 'stripe:' . $session_id ) );
			Daymark_Subscriptions_DB::update_checkout_by_provider_session( $session_id, array( 'status' => 'verified' ) );
			if ( $activated && ! Daymark_Payment_Provider::development_mode() ) update_user_meta( absint( $activated['user_id'] ), 'daymark_account_state', 'pending_activation' );
			return (bool) $activated;
		}
		$created = Daymark_Subscriptions_DB::create_subscription_once( array( 'user_id' => $user_id, 'plan_id' => $plan_id, 'status' => 'pending_activation', 'started_at' => $now, 'current_period_start' => $now, 'current_period_end' => $end, 'cancel_at_period_end' => 0, 'canceled_at' => null, 'paused_at' => null, 'paused_until' => null, 'pending_plan_id' => null, 'change_effective_at' => null, 'last_payment_status' => 'paid', 'failure_count' => 0, 'quantity' => max( 1, absint( $session['metadata']['daymark_quantity'] ?? 1 ) ), 'provider_customer_id' => sanitize_text_field( $session['customer'] ?? '' ), 'provider_subscription_id' => $provider_id, 'checkout_session_id' => $session_id, 'idempotency_key' => 'stripe:' . $session_id, 'created_at' => $now ) );
		Daymark_Subscriptions_DB::update_checkout_by_provider_session( $session_id, array( 'status' => 'verified', 'user_id' => $user_id ) );
		if ( ! $created ) return false;
		if ( ! empty( $created['conflict'] ) ) {
			$cancelled = self::request( 'DELETE', 'subscriptions/' . rawurlencode( $provider_id ) );
			return ! is_wp_error( $cancelled );
		}
		if ( ! $created['created'] ) return true;
		if ( ! Daymark_Payment_Provider::development_mode() ) update_user_meta( $user_id, 'daymark_account_state', 'pending_activation' );
		return true;
	}

	private static function subscription_updated( $subscription ) {
		$id = sanitize_text_field( $subscription['id'] ?? '' ); if ( ! $id ) return true;
		$status = in_array( $subscription['status'] ?? '', array( 'active', 'trialing' ), true ) ? 'active' : ( 'paused' === ( $subscription['status'] ?? '' ) ? 'paused' : ( 'past_due' === ( $subscription['status'] ?? '' ) ? 'past_due' : sanitize_key( $subscription['status'] ?? 'expired' ) ) );
		$local = Daymark_Subscriptions_DB::get_subscription_by_provider_id( $id );
		if ( ! $local || in_array( $local['status'], array( 'canceled', 'expired' ), true ) ) return true;
		if ( in_array( $local['status'], array( 'pending_review', 'pending_activation' ), true ) ) $status = $local['status'];
		$items = $subscription['items']['data'][0] ?? array(); $price_id = sanitize_text_field( $items['price']['id'] ?? '' ); $plan = Daymark_Subscriptions_DB::get_plan_by_stripe_price( $price_id ); $data = array( 'status' => $status, 'cancel_at_period_end' => ! empty( $subscription['cancel_at_period_end'] ) ? 1 : 0, 'provider_customer_id' => sanitize_text_field( $subscription['customer'] ?? '' ) ); $formats = array( '%s', '%d', '%s' );
		if ( isset( $subscription['current_period_start'] ) ) { $data['current_period_start'] = gmdate( 'Y-m-d H:i:s', absint( $subscription['current_period_start'] ) ); $formats[] = '%s'; }
		if ( isset( $subscription['current_period_end'] ) ) { $data['current_period_end'] = gmdate( 'Y-m-d H:i:s', absint( $subscription['current_period_end'] ) ); $formats[] = '%s'; }
		if ( $plan ) { $data['plan_id'] = $plan['id']; $formats[] = '%d'; }
		return false !== Daymark_Subscriptions_DB::update_by_provider_id( $id, $data, $formats );
	}

	public static function reconcile_subscription( $provider_id ) {
		if ( ! $provider_id || '' === self::secret_key() ) return false;
		$remote = self::request( 'GET', 'subscriptions/' . rawurlencode( $provider_id ) );
		if ( is_wp_error( $remote ) || ! self::subscription_updated( $remote ) ) return false;
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_provider_id( sanitize_text_field( $provider_id ) );
		if ( $subscription ) do_action( 'daymark_subscription_reconciled', $subscription );
		return true;
	}

	private static function subscription_deleted( $subscription ) {
		$local = Daymark_Subscriptions_DB::get_subscription_by_provider_id( sanitize_text_field( $subscription['id'] ?? '' ) );
		if ( ! $local ) return true;
		$updated = Daymark_Subscriptions_DB::update_by_provider_id( sanitize_text_field( $subscription['id'] ?? '' ), array( 'status' => 'canceled', 'canceled_at' => current_time( 'mysql', true ), 'cancel_at_period_end' => 0 ), array( '%s', '%s', '%d' ) );
		if ( $updated ) {
			$updated_subscription = Daymark_Subscriptions_DB::get_subscription_by_provider_id( sanitize_text_field( $subscription['id'] ?? '' ) ) ?: $local;
			do_action( 'daymark_subscription_provider_cancelled', $updated_subscription );
			do_action( 'daymark_subscription_deleted', $updated_subscription );
			do_action( 'daymark_subscription_access_changed', $updated_subscription, 'canceled' );
		}
		return $updated;
	}
	private static function invoice_succeeded( $invoice ) { return self::record_invoice( $invoice, 'paid' ); }
	private static function invoice_failed( $invoice ) { return self::record_invoice( $invoice, 'failed' ); }
	private static function record_invoice( $invoice, $status ) {
		$invoice_id = sanitize_text_field( $invoice['id'] ?? '' );
		if ( ! $invoice_id ) return false;
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_provider_id( sanitize_text_field( $invoice['subscription'] ?? '' ) );
		if ( ! $subscription ) return true;
		$plan = Daymark_Subscriptions_DB::get_plan( $subscription['plan_id'] );
		if ( 'subscription_cycle' !== sanitize_key( $invoice['billing_reason'] ?? '' ) ) {
			if ( ! $plan ) return false;
			$saved = Daymark_Subscriptions_DB::save_invoice( array( 'subscription_id' => $subscription['id'], 'user_id' => $subscription['user_id'], 'plan_id' => $subscription['plan_id'], 'provider_invoice_id' => $invoice_id, 'amount' => ( (float) ( $invoice['amount_paid'] ?? $invoice['amount_due'] ?? $invoice['total'] ?? 0 ) ) / 100, 'currency' => strtoupper( sanitize_text_field( $invoice['currency'] ?? $plan['currency'] ) ), 'status' => $status, 'invoice_url' => $invoice['hosted_invoice_url'] ?? ( $invoice['invoice_pdf'] ?? '' ), 'issued_at' => gmdate( 'Y-m-d H:i:s', absint( $invoice['created'] ?? time() ) ), 'created_at' => current_time( 'mysql', true ) ) );
			if ( $saved ) self::dispatch_invoice_event( $subscription, $status, $invoice_id );
			return $saved;
		}
		if ( $subscription && 'paid' === $status && (int) $subscription['pending_plan_id'] ) {
			$plan = Daymark_Subscriptions_DB::get_plan( $subscription['pending_plan_id'] );
			$price_id = $plan ? self::ensure_price( $plan ) : new WP_Error( 'daymark_plan_missing', __( 'The subscription plan could not be found.', 'daymark-subscriptions' ) );
			if ( ! $plan || is_wp_error( $price_id ) || is_wp_error( self::sync_plan( $subscription['provider_subscription_id'], $price_id, 'daymark-renewal-' . $subscription['id'] . '-' . $invoice_id, 'none' ) ) ) return false;
		}
		if ( ! Daymark_Subscriptions_DB::record_renewal( $subscription['id'], $status, $invoice_id ) ) return false;
		$saved_invoice = true;
		if ( $plan ) $saved_invoice = Daymark_Subscriptions_DB::save_invoice( array( 'subscription_id' => $subscription['id'], 'user_id' => $subscription['user_id'], 'plan_id' => $subscription['plan_id'], 'provider_invoice_id' => $invoice_id, 'amount' => ( (float) ( $invoice['amount_paid'] ?? $invoice['amount_due'] ?? 0 ) ) / 100, 'currency' => strtoupper( sanitize_text_field( $invoice['currency'] ?? $plan['currency'] ) ), 'status' => $status, 'invoice_url' => $invoice['hosted_invoice_url'] ?? ( $invoice['invoice_pdf'] ?? '' ), 'issued_at' => gmdate( 'Y-m-d H:i:s', absint( $invoice['created'] ?? time() ) ), 'created_at' => current_time( 'mysql', true ) ) );
		if ( ! $saved_invoice ) return false;
		self::dispatch_invoice_event( $subscription, $status, $invoice_id );
		do_action( 'daymark_subscription_payment_updated', Daymark_Subscriptions_DB::get_subscription_by_provider_id( $subscription['provider_subscription_id'] ) ?: $subscription, $status );
		return true;
	}

	private static function dispatch_invoice_event( array $subscription, $status, $invoice_id ) {
		$subscription = Daymark_Subscriptions_DB::get_subscription_by_id( $subscription['id'] ) ?: $subscription;
		do_action( 'daymark_subscription_invoice_created', $subscription, sanitize_text_field( $invoice_id ), sanitize_key( $status ) );
		if ( 'paid' === $status ) do_action( 'daymark_subscription_invoice_paid', $subscription, sanitize_text_field( $invoice_id ) );
		if ( 'failed' === $status ) do_action( 'daymark_subscription_invoice_failed', $subscription, sanitize_text_field( $invoice_id ) );
	}

	private static function payment_issue( $object, $status ) {
		$invoice_id = sanitize_text_field( $object['invoice'] ?? $object['id'] ?? '' );
		$subscription_id = sanitize_text_field( $object['subscription'] ?? '' );
		$subscription = $subscription_id ? Daymark_Subscriptions_DB::get_subscription_by_provider_id( $subscription_id ) : ( $invoice_id ? Daymark_Subscriptions_DB::get_subscription_by_invoice_id( $invoice_id ) : false );
		return ! $subscription || Daymark_Subscriptions_DB::mark_payment_issue( $subscription['id'], $status, $invoice_id );
	}
}
