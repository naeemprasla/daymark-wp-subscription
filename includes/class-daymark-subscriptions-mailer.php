<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Subscriptions_Mailer {
	public const ADMIN_EMAIL_OPTION = 'daymark_subscriptions_notification_admin_email';
	public const FROM_NAME_OPTION = 'daymark_subscriptions_notification_from_name';
	public const FROM_EMAIL_OPTION = 'daymark_subscriptions_notification_from_email';
	public const ADMIN_ENABLED_OPTION = 'daymark_subscriptions_admin_notifications_enabled';
	public const REPLY_TO_OPTION = 'daymark_subscriptions_notification_reply_to';
	public const EVENTS_OPTION = 'daymark_subscriptions_notification_events';
	public const TEMPLATES_OPTION = 'daymark_subscriptions_notification_templates';
	public static function event_defaults() { return array( 'subscription_created' => true, 'pending_review' => true, 'approved_for_payment' => true, 'subscription_cancelled' => true, 'subscription_paused' => true, 'subscription_resumed' => true, 'plan_changed' => true, 'payment_updated' => true, 'payment_recovery_required' => true, 'invoice_created' => true, 'invoice_paid' => true, 'invoice_failed' => true, 'subscription_expired' => true, 'provider_cancelled' => true, 'renewal_due' => true, 'renewal_reminder' => true, 'trial_ending' => true, 'intro_expiring' => true ); }
	public static function event_enabled( $event ) { $events = get_option( self::EVENTS_OPTION, array() ); $events = is_array( $events ) ? array_merge( self::event_defaults(), $events ) : self::event_defaults(); return ! empty( $events[ sanitize_key( $event ) ] ); }
	public static function template_events() { return array( 'subscription_created', 'pending_review', 'approved_for_payment', 'subscription_cancelled', 'subscription_paused', 'subscription_resumed', 'plan_changed', 'payment_updated', 'payment_recovery_required', 'invoice_created', 'invoice_paid', 'invoice_failed', 'subscription_expired', 'provider_cancelled', 'renewal_due', 'renewal_reminder', 'trial_ending', 'intro_expiring' ); }
	public static function template_labels() { return array( 'subscription_created' => __( 'Subscription activated', 'daymark-subscriptions' ), 'pending_review' => __( 'Admin review request', 'daymark-subscriptions' ), 'approved_for_payment' => __( 'Review approval', 'daymark-subscriptions' ), 'subscription_cancelled' => __( 'Cancellation', 'daymark-subscriptions' ), 'subscription_paused' => __( 'Pause', 'daymark-subscriptions' ), 'subscription_resumed' => __( 'Resume', 'daymark-subscriptions' ), 'plan_changed' => __( 'Plan change', 'daymark-subscriptions' ), 'payment_updated' => __( 'Payment status', 'daymark-subscriptions' ), 'provider_cancelled' => __( 'Provider cancellation', 'daymark-subscriptions' ), 'renewal_due' => __( 'Renewal due', 'daymark-subscriptions' ), 'renewal_reminder' => __( 'Renewal reminder', 'daymark-subscriptions' ), 'trial_ending' => __( 'Trial ending', 'daymark-subscriptions' ), 'intro_expiring' => __( 'Introductory price ending', 'daymark-subscriptions' ), 'payment_recovery_required' => __( 'Payment recovery required', 'daymark-subscriptions' ), 'invoice_created' => __( 'Invoice created', 'daymark-subscriptions' ), 'invoice_paid' => __( 'Invoice paid', 'daymark-subscriptions' ), 'invoice_failed' => __( 'Invoice failed', 'daymark-subscriptions' ), 'subscription_expired' => __( 'Subscription expired', 'daymark-subscriptions' ) ); }
	public static function template_examples() { $examples = array( 'subscription_created' => array( 'subject' => 'Your subscription is active', 'body' => '<p>Your <strong>{plan_name}</strong> subscription is now active.</p><p>You can manage it from your account.</p><p>WordPress user ID: {user_id}</p>' ), 'pending_review' => array( 'subject' => 'Subscription approval required', 'body' => '<p>A new subscription request is waiting for review.</p><p>Plan: <strong>{plan_name}</strong><br>Email: {email}<br>WordPress user ID: {user_id}</p>' ), 'approved_for_payment' => array( 'subject' => 'Subscription request approved', 'body' => '<p>Your subscription request was approved.</p><p><a href="{checkout_url}">Complete payment</a></p><p>WordPress user ID: {user_id}</p>' ), 'subscription_cancelled' => array( 'subject' => 'Subscription cancellation updated', 'body' => '<p>Your subscription cancellation request was recorded.</p><p>WordPress user ID: {user_id}</p>' ), 'subscription_paused' => array( 'subject' => 'Subscription paused', 'body' => '<p>Your subscription has been paused.</p><p>WordPress user ID: {user_id}</p>' ), 'subscription_resumed' => array( 'subject' => 'Subscription resumed', 'body' => '<p>Your subscription has been resumed.</p><p>WordPress user ID: {user_id}</p>' ), 'plan_changed' => array( 'subject' => 'Subscription plan changed', 'body' => '<p>Your subscription plan was changed to <strong>{plan_name}</strong>.</p><p>The change is effective: {effective}.</p>' ), 'payment_updated' => array( 'subject' => 'Subscription payment update', 'body' => '<p>Your subscription payment status changed to: <strong>{status}</strong>.</p>' ), 'provider_cancelled' => array( 'subject' => 'Subscription canceled', 'body' => '<p>Your subscription has been canceled by the payment provider.</p><p>WordPress user ID: {user_id}</p>' ), 'renewal_due' => array( 'subject' => 'Subscription renewal is due', 'body' => '<p>Your subscription renewal is due.</p><p><a href="{checkout_url}">Complete payment</a></p>' ), 'renewal_reminder' => array( 'subject' => 'Upcoming subscription renewal', 'body' => '<p>Your <strong>{plan_name}</strong> subscription renews on {period_end}.</p><p>No action is needed unless you want to update your payment method.</p>' )); $examples['trial_ending'] = array( 'subject' => 'Your subscription trial is ending soon', 'body' => '<p>Your <strong>{plan_name}</strong> trial ends on {period_end}.</p><p>Regular subscription billing begins after the trial.</p>' ); $examples['intro_expiring'] = array( 'subject' => 'Your introductory price is ending soon', 'body' => '<p>Your introductory price for <strong>{plan_name}</strong> ends on {period_end}.</p><p>Regular plan pricing applies after that date.</p>' ); $examples['payment_recovery_required'] = array( 'subject' => 'Payment recovery required', 'body' => '<p>Your subscription payment needs attention.</p><p>Update your payment method to keep access active.</p><p>Status: {status}</p>' ); $examples['invoice_created'] = array( 'subject' => 'Subscription invoice created', 'body' => '<p>Your subscription invoice was created.</p><p>Status: {status}</p>' ); $examples['invoice_paid'] = array( 'subject' => 'Subscription invoice paid', 'body' => '<p>Your subscription invoice was paid successfully.</p>' ); $examples['invoice_failed'] = array( 'subject' => 'Subscription invoice failed', 'body' => '<p>Your subscription invoice could not be paid.</p><p>Status: {status}</p>' ); $examples['subscription_expired'] = array( 'subject' => 'Subscription expired', 'body' => '<p>Your subscription has expired.</p>' ); return $examples; }
	public static function template_placeholders() { return array( 'plan_name' => __( 'Plan name', 'daymark-subscriptions' ), 'period_end' => __( 'Current period end', 'daymark-subscriptions' ), 'checkout_url' => __( 'Checkout URL', 'daymark-subscriptions' ), 'setup_url' => __( 'Secure password setup URL', 'daymark-subscriptions' ), 'status' => __( 'Payment/status value', 'daymark-subscriptions' ), 'user_id' => __( 'WordPress user ID', 'daymark-subscriptions' ), 'email' => __( 'Member email', 'daymark-subscriptions' ), 'effective' => __( 'Plan change timing', 'daymark-subscriptions' ), 'invoice_url' => __( 'Invoice URL', 'daymark-subscriptions' ), 'activation_url' => __( 'Activation/password claim URL', 'daymark-subscriptions' ), 'payment_recovery_url' => __( 'Payment recovery URL', 'daymark-subscriptions' ) ); }
	public static function sanitize_template_body( $body ) { $allowed = array( 'a' => array( 'href' => true, 'title' => true, 'target' => true, 'rel' => true, 'style' => true, 'class' => true ), 'br' => array(), 'div' => array( 'style' => true, 'class' => true, 'id' => true ), 'span' => array( 'style' => true, 'class' => true, 'id' => true ), 'p' => array( 'style' => true, 'class' => true, 'id' => true ), 'strong' => array( 'style' => true, 'class' => true ), 'b' => array( 'style' => true, 'class' => true ), 'em' => array( 'style' => true, 'class' => true ), 'i' => array( 'style' => true, 'class' => true ), 'h1' => array( 'style' => true, 'class' => true ), 'h2' => array( 'style' => true, 'class' => true ), 'h3' => array( 'style' => true, 'class' => true ), 'ul' => array( 'style' => true, 'class' => true ), 'ol' => array( 'style' => true, 'class' => true ), 'li' => array( 'style' => true, 'class' => true ), 'table' => array( 'style' => true, 'class' => true, 'width' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true ), 'tbody' => array(), 'thead' => array(), 'tfoot' => array(), 'tr' => array( 'style' => true, 'class' => true ), 'td' => array( 'style' => true, 'class' => true, 'width' => true, 'colspan' => true, 'rowspan' => true, 'align' => true, 'valign' => true ), 'th' => array( 'style' => true, 'class' => true, 'width' => true, 'colspan' => true, 'rowspan' => true, 'align' => true, 'valign' => true ), 'img' => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true, 'class' => true ) ); return wp_kses( (string) $body, $allowed ); }
	private function apply_template( $event, $subject, $message, array $vars ) { $templates = get_option( self::TEMPLATES_OPTION, array() ); $template = is_array( $templates[ $event ] ?? null ) ? $templates[ $event ] : array(); $custom_subject = trim( (string) ( $template['subject'] ?? '' ) ); $custom_body = trim( (string) ( $template['body'] ?? '' ) ); $replacements = array(); foreach ( $vars as $key => $value ) $replacements[ '{' . sanitize_key( $key ) . '}' ] = $custom_body ? ( in_array( $key, array( 'checkout_url', 'setup_url', 'invoice_url', 'activation_url', 'payment_recovery_url' ), true ) ? esc_url( $value ) : esc_html( $value ) ) : (string) $value; return array( $custom_subject ? strtr( $custom_subject, $replacements ) : $subject, $custom_body ? strtr( $custom_body, $replacements ) : $message, (bool) $custom_body ); }
	public function __construct() {
		add_action( 'after_password_reset', array( $this, 'activate_claimed_account' ), 10, 2 );
		add_action( 'daymark_subscription_created', array( $this, 'subscription_created' ), 10, 1 );
		add_action( 'daymark_subscription_approved', array( $this, 'subscription_created' ), 10, 1 );
		add_action( 'daymark_subscription_pending_review', array( $this, 'pending_review' ), 10, 1 );
		add_action( 'daymark_subscription_approved_for_payment', array( $this, 'approved_for_payment' ), 10, 2 );
		add_action( 'daymark_subscription_cancelled', array( $this, 'subscription_cancelled' ), 10, 2 );
		add_action( 'daymark_subscription_paused', array( $this, 'subscription_paused' ), 10, 2 );
		add_action( 'daymark_subscription_resumed', array( $this, 'subscription_resumed' ), 10, 2 );
		add_action( 'daymark_subscription_plan_changed', array( $this, 'plan_changed' ), 10, 4 );
		add_action( 'daymark_subscription_payment_updated', array( $this, 'payment_updated' ), 10, 2 );
		add_action( 'daymark_subscription_payment_recovery_required', array( $this, 'payment_recovery_required' ), 10, 3 );
		add_action( 'daymark_subscription_invoice_created', array( $this, 'invoice_created' ), 10, 3 );
		add_action( 'daymark_subscription_invoice_paid', array( $this, 'invoice_paid' ), 10, 2 );
		add_action( 'daymark_subscription_invoice_failed', array( $this, 'invoice_failed' ), 10, 2 );
		add_action( 'daymark_subscription_expired', array( $this, 'subscription_expired' ), 10, 1 );
		add_action( 'daymark_subscription_provider_cancelled', array( $this, 'provider_cancelled' ), 10, 1 );
		add_action( 'daymark_woocommerce_renewal_due', array( $this, 'renewal_due' ), 10, 2 );
		add_action( 'daymark_subscription_renewal_reminder', array( $this, 'renewal_reminder' ), 10, 1 );
		add_action( 'daymark_subscription_offer_reminder', array( $this, 'offer_reminder' ), 10, 2 );
	}

	public function activate_claimed_account( $user, $new_password ) {
		if ( ! $user instanceof WP_User || 'pending_activation' !== sanitize_key( get_user_meta( $user->ID, 'daymark_account_state', true ) ) ) return;
		if ( ! Daymark_Subscriptions_DB::activate_pending_activation( $user->ID ) ) return;
		update_user_meta( $user->ID, 'daymark_account_state', 'active' );
		delete_user_meta( $user->ID, 'daymark_checkout_created_at' );
		Daymark_Subscriptions_DB::record_audit_log( 'account_claimed', 'user', $user->ID, array( 'activation' => 'password_reset' ) );
	}

	public function subscription_created( $subscription ) {
		if ( ! self::event_enabled( 'subscription_created' ) ) return;
		$user_id = absint( $subscription['user_id'] ?? 0 );
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ?? 0 ) );
		$setup_url = $this->password_setup_url( $user_id );
		$pending_activation = 'pending_activation' === sanitize_key( $subscription['status'] ?? '' );
		$subject = $pending_activation ? __( 'Payment verified — activate your subscription account', 'daymark-subscriptions' ) : __( 'Your subscription is active', 'daymark-subscriptions' );
		$message = $pending_activation ? sprintf( __( "Your payment for %s was verified.\n\nSet your account password here to activate access: %s\n\nYou can manage your subscription after signing in.", 'daymark-subscriptions' ), $plan ? $plan['name'] : __( 'new', 'daymark-subscriptions' ), $setup_url ) : sprintf( __( "Your %s subscription is now active.\n\nSet your account password here: %s\n\nYou can manage it from your account.", 'daymark-subscriptions' ), $plan ? $plan['name'] : __( 'new', 'daymark-subscriptions' ), $setup_url );
		$this->send_user( $user_id, $subject, $message, 'subscription_created', array( 'plan_name' => $plan['name'] ?? __( 'new', 'daymark-subscriptions' ), 'user_id' => $user_id, 'setup_url' => $setup_url, 'status' => $subscription['status'] ?? '' ) );
		$login = wp_login_url( home_url( '/' ) );
		$invoice = sprintf( __( "Plan: %s\nAmount: %s %s\nBilling period: %s to %s\n\nLogin: %s", 'daymark-subscriptions' ), $plan ? $plan['name'] : __( 'new', 'daymark-subscriptions' ), $plan['currency'] ?? '', $plan ? number_format_i18n( (float) $plan['price'], 2 ) : '', $subscription['current_period_start'] ?? '', $subscription['current_period_end'] ?? '', $login );
		$invoices = Daymark_Subscriptions_DB::get_user_invoices( $user_id, 1 );
		if ( ! empty( $invoices[0]['invoice_url'] ) ) $invoice .= "\n\n" . sprintf( __( 'View invoice: %s', 'daymark-subscriptions' ), esc_url_raw( $invoices[0]['invoice_url'] ) );
		$this->send_user( $user_id, __( 'Your subscription invoice', 'daymark-subscriptions' ), $invoice, 'subscription_created', array( 'plan_name' => $plan['name'] ?? __( 'new', 'daymark-subscriptions' ), 'user_id' => $user_id, 'period_end' => $subscription['current_period_end'] ?? '' ) );
		$this->send_admin( __( 'New subscription activated', 'daymark-subscriptions' ), sprintf( __( 'A subscription was activated for WordPress user ID %d.', 'daymark-subscriptions' ), $user_id ), 'subscription_created', array( 'plan_name' => $plan['name'] ?? __( 'new', 'daymark-subscriptions' ), 'user_id' => $user_id ) );
	}

	public function pending_review( $subscription ) {
		if ( ! self::event_enabled( 'pending_review' ) ) return;
		$user_id = absint( $subscription['user_id'] ?? 0 ); $user = get_userdata( $user_id ); $plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ?? 0 ) );
		$this->send_admin( __( 'Subscription approval required', 'daymark-subscriptions' ), sprintf( __( "A new subscription request is waiting for review.\n\nMember: %s\nEmail: %s\nPlan: %s", 'daymark-subscriptions' ), $user ? $user->display_name : $user_id, $user ? $user->user_email : '', $plan ? $plan['name'] : __( 'Unknown', 'daymark-subscriptions' ) ), 'pending_review', array( 'plan_name' => $plan['name'] ?? __( 'Unknown', 'daymark-subscriptions' ), 'user_id' => $user_id, 'email' => $user ? $user->user_email : '' ) );
	}

	public function approved_for_payment( $subscription, $checkout_url = '' ) {
		if ( ! self::event_enabled( 'approved_for_payment' ) ) return;
		$user_id = absint( $subscription['user_id'] ?? 0 ); $login = wp_login_url( home_url( '/' ) );
		$setup_url = $this->password_setup_url( $user_id );
		$message = $checkout_url ? sprintf( __( "Your subscription request was approved. Complete payment here:\n%s\n\nAfter payment, set your password here: %s\n\nThen log in here: %s", 'daymark-subscriptions' ), esc_url_raw( $checkout_url ), $setup_url, $login ) : sprintf( __( "Your subscription request was approved and your test subscription is active.\n\nSet your password here: %s\n\nThen log in here: %s", 'daymark-subscriptions' ), $setup_url, $login );
		$this->send_user( $user_id, __( 'Subscription request approved', 'daymark-subscriptions' ), $message, 'approved_for_payment', array( 'checkout_url' => $checkout_url, 'setup_url' => $setup_url, 'user_id' => $user_id ) );
	}

	public function send_account_access( $user_id ) {
		$user = get_userdata( absint( $user_id ) ); if ( ! $user || ! is_email( $user->user_email ) ) return false;
		$url = $this->password_setup_url( $user->ID );
		return $this->send( $user->user_email, __( 'Set up your subscription account', 'daymark-subscriptions' ), sprintf( __( "Use the secure link below to set your account password and sign in:\n\n%s\n\nIf you did not request this, you can ignore this email.", 'daymark-subscriptions' ), $url ) );
	}

	public function resend_email( $log_id ) {
		$log = Daymark_Subscriptions_DB::get_email_log( $log_id );
		$user_id = absint( $log['user_id'] ?? 0 );
		if ( ! $log || ! $user_id || ! get_userdata( $user_id ) ) return new WP_Error( 'daymark_email_resend_unavailable', __( 'This email cannot be resent because its member record is unavailable.', 'daymark-subscriptions' ) );
		$subscription = Daymark_Subscriptions_DB::get_user_subscription( $user_id );
		if ( ! $subscription ) return new WP_Error( 'daymark_email_resend_unavailable', __( 'This email cannot be resent because the subscription record is unavailable.', 'daymark-subscriptions' ) );
		switch ( sanitize_key( $log['event'] ) ) {
			case 'subscription_created': $this->subscription_created( $subscription ); break;
			case 'payment_updated': $this->payment_updated( $subscription, $subscription['last_payment_status'] ?? 'failed' ); break;
			case 'subscription_expired': $this->subscription_expired( $subscription ); break;
			default: return new WP_Error( 'daymark_email_resend_unsupported', __( 'This event does not have a safe resend action.', 'daymark-subscriptions' ) );
		}
		return true;
	}

	private function password_setup_url( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) return wp_lostpassword_url();
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) return wp_lostpassword_url();
		return network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
	}

	public function subscription_cancelled( $subscription, $user_id ) {
		if ( ! self::event_enabled( 'subscription_cancelled' ) ) return;
		$this->send_user( $user_id, __( 'Subscription cancellation updated', 'daymark-subscriptions' ), __( 'Your subscription cancellation request was recorded. Access remains available until the current period ends unless an immediate cancellation was requested.', 'daymark-subscriptions' ), 'subscription_cancelled', array( 'user_id' => absint( $user_id ) ) );
		$this->send_admin( __( 'Subscription cancellation updated', 'daymark-subscriptions' ), sprintf( __( 'Cancellation was updated for WordPress user ID %d.', 'daymark-subscriptions' ), absint( $user_id ) ), 'subscription_cancelled', array( 'user_id' => absint( $user_id ) ) );
	}

	public function subscription_paused( $subscription, $user_id ) { if ( ! self::event_enabled( 'subscription_paused' ) ) return; $this->lifecycle_notice( $user_id, __( 'Subscription paused', 'daymark-subscriptions' ), __( 'Your subscription has been paused.', 'daymark-subscriptions' ), 'subscription_paused' ); }
	public function subscription_resumed( $subscription, $user_id ) { if ( ! self::event_enabled( 'subscription_resumed' ) ) return; $this->lifecycle_notice( $user_id, __( 'Subscription resumed', 'daymark-subscriptions' ), __( 'Your subscription has been resumed.', 'daymark-subscriptions' ), 'subscription_resumed' ); }

	public function plan_changed( $current, $new_plan, $effective, $user_id ) {
		if ( ! self::event_enabled( 'plan_changed' ) ) return;
		$message = sprintf( __( 'Your subscription plan was changed to %s. The change is effective %s.', 'daymark-subscriptions' ), $new_plan['name'], 'immediate' === $effective ? __( 'now', 'daymark-subscriptions' ) : __( 'at the end of the current period', 'daymark-subscriptions' ) );
		$this->lifecycle_notice( $user_id, __( 'Subscription plan changed', 'daymark-subscriptions' ), $message, 'plan_changed', array( 'plan_name' => $new_plan['name'], 'effective' => $effective ) );
	}

	public function payment_updated( $subscription, $status ) {
		if ( ! self::event_enabled( 'payment_updated' ) ) return;
		$user_id = absint( $subscription['user_id'] ?? 0 );
		if ( 'paid' === $status ) $this->lifecycle_notice( $user_id, __( 'Subscription payment received', 'daymark-subscriptions' ), __( 'Your subscription payment was received and your access remains active.', 'daymark-subscriptions' ), 'payment_updated', array( 'status' => $status ) );
		if ( 'failed' === $status ) $this->lifecycle_notice( $user_id, __( 'Subscription payment failed', 'daymark-subscriptions' ), __( 'Your subscription payment failed. Please update your payment method to prevent access interruption.', 'daymark-subscriptions' ), 'payment_updated', array( 'status' => $status ) );
		if ( in_array( $status, array( 'refunded', 'disputed', 'voided', 'uncollectible' ), true ) ) $this->lifecycle_notice( $user_id, __( 'Subscription payment requires attention', 'daymark-subscriptions' ), sprintf( __( 'Your subscription payment status changed to: %s.', 'daymark-subscriptions' ), $status ), 'payment_updated', array( 'status' => $status ) );
	}
	public function payment_recovery_required( $subscription, $status, $invoice_id = '' ) { $this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Payment recovery required', 'daymark-subscriptions' ), __( 'Your subscription payment needs attention. Update your payment method to keep access active.', 'daymark-subscriptions' ), 'payment_recovery_required', array( 'status' => $status, 'invoice_id' => $invoice_id ) ); }
	public function invoice_created( $subscription, $invoice_id, $status ) { $this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Subscription invoice created', 'daymark-subscriptions' ), sprintf( __( 'A subscription invoice was created with status: %s.', 'daymark-subscriptions' ), $status ), 'invoice_created', array( 'status' => $status, 'invoice_id' => $invoice_id ) ); }
	public function invoice_paid( $subscription, $invoice_id ) { $this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Subscription invoice paid', 'daymark-subscriptions' ), __( 'Your subscription invoice was paid successfully.', 'daymark-subscriptions' ), 'invoice_paid', array( 'status' => 'paid', 'invoice_id' => $invoice_id ) ); }
	public function invoice_failed( $subscription, $invoice_id ) { $this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Subscription invoice failed', 'daymark-subscriptions' ), __( 'Your subscription invoice could not be paid. Update your payment method during the grace period.', 'daymark-subscriptions' ), 'invoice_failed', array( 'status' => 'failed', 'invoice_id' => $invoice_id ) ); }
	public function subscription_expired( $subscription ) { $this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Subscription expired', 'daymark-subscriptions' ), __( 'Your subscription has expired and protected access is no longer available.', 'daymark-subscriptions' ), 'subscription_expired', array( 'status' => 'expired' ) ); }

	public function provider_cancelled( $subscription ) { if ( ! self::event_enabled( 'provider_cancelled' ) ) return; $this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Subscription canceled', 'daymark-subscriptions' ), __( 'Your subscription has been canceled by the payment provider.', 'daymark-subscriptions' ), 'provider_cancelled' ); }
	public function renewal_due( $subscription, $order = false ) {
		if ( ! self::event_enabled( 'renewal_due' ) ) return;
		$message = $order && is_object( $order ) ? sprintf( __( "Your WooCommerce subscription renewal is ready for payment. Complete it here:\n%s", 'daymark-subscriptions' ), esc_url_raw( $order->get_checkout_payment_url() ) ) : __( 'Your WooCommerce subscription renewal is due. Complete payment through the configured renewal gateway.', 'daymark-subscriptions' );
		$this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Subscription renewal is due', 'daymark-subscriptions' ), $message, 'renewal_due', array( 'checkout_url' => $order && is_object( $order ) ? $order->get_checkout_payment_url() : '' ) );
	}

	public function renewal_reminder( $subscription ) {
		if ( ! self::event_enabled( 'renewal_reminder' ) ) return;
		$end = $subscription['current_period_end'] ?? ''; $plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ?? 0 ) ); $plan_name = $plan ? $plan['name'] : __( 'subscription', 'daymark-subscriptions' ); $message = sprintf( __( "Your %s subscription renews on %s. No action is needed unless you want to update your payment method.", 'daymark-subscriptions' ), $plan_name, $end );
		$this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), __( 'Upcoming subscription renewal', 'daymark-subscriptions' ), $message, 'renewal_reminder', array( 'plan_name' => $plan_name, 'period_end' => $end ) );
	}

	public function offer_reminder( $subscription, $type ) {
		$event = 'trial' === $type ? 'trial_ending' : 'intro_expiring';
		if ( ! self::event_enabled( $event ) ) return;
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $subscription['plan_id'] ?? 0 ) ); $name = $plan ? $plan['name'] : __( 'subscription', 'daymark-subscriptions' ); $end = $subscription['offer_ends_at'] ?? '';
		$subject = 'trial' === $type ? __( 'Your subscription trial is ending soon', 'daymark-subscriptions' ) : __( 'Your introductory subscription price is ending soon', 'daymark-subscriptions' );
		$message = 'trial' === $type ? sprintf( __( 'Your %s trial ends on %s. Your regular subscription billing begins after the trial.', 'daymark-subscriptions' ), $name, $end ) : sprintf( __( 'Your introductory price for %s ends on %s. Regular plan pricing applies after that date.', 'daymark-subscriptions' ), $name, $end );
		$this->lifecycle_notice( absint( $subscription['user_id'] ?? 0 ), $subject, $message, $event, array( 'plan_name' => $name, 'period_end' => $end, 'effective' => 'trial' === $type ? 'trial end' : 'intro price end' ) );
	}

	private function lifecycle_notice( $user_id, $subject, $message, $event = '', array $vars = array() ) {
		$this->send_user( $user_id, $subject, $message, $event, $vars );
		$this->send_admin( $subject, sprintf( __( '%s WordPress user ID: %d.', 'daymark-subscriptions' ), $message, absint( $user_id ) ), $event, $vars + array( 'user_id' => absint( $user_id ) ) );
	}

	private function send_user( $user_id, $subject, $message, $event = '', array $vars = array() ) {
		$user = get_userdata( absint( $user_id ) );
		if ( $user && is_email( $user->user_email ) ) { $is_html = false; if ( $event ) list( $subject, $message, $is_html ) = $this->apply_template( $event, $subject, $message, $vars ); return $this->send( $user->user_email, $subject, $message, $is_html, array( 'user_id' => $user_id, 'event' => $event ) ); }
		return false;
	}

	private function send_admin( $subject, $message, $event = '', array $vars = array() ) {
		if ( ! get_option( self::ADMIN_ENABLED_OPTION, true ) ) return;
		$admin_email = get_option( self::ADMIN_EMAIL_OPTION, get_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) { $is_html = false; if ( $event ) list( $subject, $message, $is_html ) = $this->apply_template( $event, $subject, $message, $vars ); $this->send( $admin_email, $subject, $message, $is_html, array( 'user_id' => absint( $vars['user_id'] ?? 0 ), 'event' => $event ) ); }
	}

	private function send( $to, $subject, $message, $is_html = false, array $meta = array() ) {
		$from_email = get_option( self::FROM_EMAIL_OPTION, get_option( 'admin_email' ) );
		$from_name = sanitize_text_field( get_option( self::FROM_NAME_OPTION, Daymark_Subscriptions_Admin::business_name() ) );
		$from_email = is_email( $from_email ) ? $from_email : get_option( 'admin_email' );
		$from_filter = static function () use ( $from_email ) { return $from_email; };
		$name_filter = static function () use ( $from_name ) { return $from_name; };
		add_filter( 'wp_mail_from', $from_filter );
		add_filter( 'wp_mail_from_name', $name_filter );
		$reply_to = sanitize_email( get_option( self::REPLY_TO_OPTION, Daymark_Subscriptions_Admin::support_email() ) ); $headers = $reply_to && is_email( $reply_to ) ? array( 'Reply-To: ' . $reply_to ) : array(); if ( $is_html ) $headers[] = 'Content-Type: text/html; charset=UTF-8';
		$result = wp_mail( $to, $subject, $message, $headers );
		Daymark_Subscriptions_DB::record_email_log( array( 'user_id' => absint( $meta['user_id'] ?? 0 ), 'event' => $meta['event'] ?? '', 'subject' => $subject, 'recipient_hash' => hash( 'sha256', strtolower( sanitize_email( $to ) ) ), 'status' => $result ? 'sent' : 'failed', 'error_message' => $result ? '' : __( 'WordPress mail transport returned false.', 'daymark-subscriptions' ) ) );
		if ( ! $result ) {
			Daymark_Subscriptions_DB::record_audit_log( 'email_delivery_failed', 'email', 0, array( 'recipient_hash' => hash( 'sha256', strtolower( sanitize_email( $to ) ) ), 'event_subject' => sanitize_text_field( $subject ) ) );
		}
		remove_filter( 'wp_mail_from', $from_filter );
		remove_filter( 'wp_mail_from_name', $name_filter );
		return $result;
	}
}
