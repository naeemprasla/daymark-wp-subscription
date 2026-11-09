<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Subscriptions_Shortcode {
	public function __construct() { add_action( 'admin_init', array( $this, 'ensure_default_pages' ) ); add_action( 'admin_post_nopriv_daymark_confirm_email_change', array( $this, 'confirm_email_change_page' ) ); add_action( 'admin_post_daymark_confirm_email_change', array( $this, 'confirm_email_change_page' ) ); add_action( 'admin_post_nopriv_daymark_account_access', array( $this, 'account_access_page' ) ); add_action( 'admin_post_daymark_account_access', array( $this, 'account_access_page' ) ); add_action( 'template_redirect', array( $this, 'redirect_legacy_profile_page' ) ); add_filter( 'template_include', array( $this, 'load_author_template' ) ); add_shortcode( 'daymark_subscribe', array( $this, 'render' ) ); add_shortcode( 'daymark_profile', array( $this, 'render_profile' ) ); add_shortcode( 'daymark_checkout', array( $this, 'render_checkout' ) ); add_shortcode( 'daymark_member_settings', array( $this, 'render_settings' ) ); add_shortcode( 'daymark_privacy_security', array( $this, 'render_privacy_security' ) ); add_shortcode( 'daymark_subscription_management', array( $this, 'render_subscription_management' ) ); add_shortcode( 'daymark_email_change_confirmation', array( $this, 'render_email_change_confirmation' ) ); add_shortcode( 'daymark_account_access', array( $this, 'render_account_access' ) ); }

	public function ensure_default_pages() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$profile_page = get_page_by_path( 'profile' );
		if ( $profile_page && in_array( trim( $profile_page->post_content ), array( '[daymark_member_settings]', '[daymark_profile]' ), true ) ) wp_update_post( array( 'ID' => $profile_page->ID, 'post_content' => '' ) );
		$pages = array(
			'subscribe' => array( 'Subscribe', '[daymark_subscribe]' ),
			'profile' => array( 'Profile', '' ),
			'settings' => array( 'Settings', '[daymark_member_settings]' ),
			'checkout' => array( 'Checkout', '[daymark_checkout]' ),
			'privacy-security' => array( 'Privacy & Security', '[daymark_privacy_security]' ),
			'subscription-management' => array( 'Subscription Management', '[daymark_subscription_management]' ),
			'email-confirmation' => array( 'Confirm email change', '[daymark_email_change_confirmation]' ),
			'account-access' => array( 'Account access', '[daymark_account_access]' ),
		);
		foreach ( $pages as $slug => $page ) if ( ! get_page_by_path( $slug ) ) wp_insert_post( array( 'post_title' => $page[0], 'post_name' => $slug, 'post_content' => $page[1], 'post_status' => 'publish', 'post_type' => 'page' ) );
		update_option( 'daymark_default_pages_ready', '1', false );
	}

	public function confirm_email_change_page() {
		$user_id = absint( $_POST['user_id'] ?? 0 ); $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$request = new WP_REST_Request( 'POST', '/daymark/v1/me/email-change/confirm' ); $request->set_param( 'user_id', $user_id ); $request->set_param( 'token', $token );
		$result = ( new Daymark_Subscriptions_REST() )->confirm_email_change( $request );
		$data = is_wp_error( $result ) ? array() : ( $result instanceof WP_REST_Response ? $result->get_data() : (array) $result );
		$success = ! empty( $data['changed'] );
		wp_safe_redirect( add_query_arg( 'daymark_email_confirmation', $success ? 'success' : 'error', home_url( '/email-confirmation/' ) ) ); exit;
	}

	public function account_access_page() {
		check_admin_referer( 'daymark_account_access' );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); $request = new WP_REST_Request( 'POST', '/daymark/v1/me/account-access' ); $request->set_param( 'email', $email );
		( new Daymark_Subscriptions_REST() )->request_account_access( $request );
		wp_safe_redirect( add_query_arg( 'daymark_account_access', 'sent', home_url( '/account-access/' ) ) ); exit;
	}

	public function render_account_access() {
		$this->enqueue_branding();
		if ( 'sent' === sanitize_key( $_GET['daymark_account_access'] ?? '' ) ) return '<section class="daymark-privacy-security"><div class="daymark-form-heading"><span class="daymark-form-eyebrow">' . esc_html__( 'Account access', 'daymark-subscriptions' ) . '</span><h2>' . esc_html__( 'Check your inbox', 'daymark-subscriptions' ) . '</h2><p>' . esc_html__( 'If a matching subscription account exists, an account access link has been sent.', 'daymark-subscriptions' ) . '</p></div></section>';
		return '<section class="daymark-privacy-security"><div class="daymark-form-heading"><span class="daymark-form-eyebrow">' . esc_html__( 'Account access', 'daymark-subscriptions' ) . '</span><h2>' . esc_html__( 'Recover your subscription account', 'daymark-subscriptions' ) . '</h2><p>' . esc_html__( 'Enter the email used at checkout and we will send a secure password setup link.', 'daymark-subscriptions' ) . '</p></div><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="daymark-privacy-card"><input type="hidden" name="action" value="daymark_account_access">' . wp_nonce_field( 'daymark_account_access', '_wpnonce', true, false ) . '<label class="daymark-field"><span>' . esc_html__( 'Checkout email', 'daymark-subscriptions' ) . '</span><input type="email" name="email" autocomplete="email" required></label><button class="daymark-button" type="submit">' . esc_html__( 'Email me an access link', 'daymark-subscriptions' ) . '</button></form></section>';
	}

	public function render_email_change_confirmation() {
		$user_id = absint( $_GET['user_id'] ?? 0 ); $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) ); $state = sanitize_key( $_GET['daymark_email_confirmation'] ?? '' );
		if ( 'success' === $state ) return '<section class="daymark-privacy-security"><div class="daymark-form-heading"><h2>' . esc_html__( 'Email updated', 'daymark-subscriptions' ) . '</h2><p>' . esc_html__( 'Your account email has been updated. You can now sign in with the new address.', 'daymark-subscriptions' ) . '</p></div></section>';
		if ( 'error' === $state ) return '<section class="daymark-privacy-security"><div class="daymark-form-heading"><h2>' . esc_html__( 'Confirmation failed', 'daymark-subscriptions' ) . '</h2><p>' . esc_html__( 'This confirmation link is invalid, expired, or already used. Request a new email change from account settings.', 'daymark-subscriptions' ) . '</p></div></section>';
		if ( ! $user_id || ! $token ) return '<section class="daymark-privacy-security"><div class="daymark-form-heading"><h2>' . esc_html__( 'Confirmation link incomplete', 'daymark-subscriptions' ) . '</h2><p>' . esc_html__( 'Open the complete confirmation link from your email.', 'daymark-subscriptions' ) . '</p></div></section>';
		return '<section class="daymark-privacy-security"><div class="daymark-form-heading"><span class="daymark-form-eyebrow">' . esc_html__( 'Account security', 'daymark-subscriptions' ) . '</span><h2>' . esc_html__( 'Confirm your new email', 'daymark-subscriptions' ) . '</h2><p>' . esc_html__( 'Click confirm to update the email address on your account. This action is explicit and can only be used once.', 'daymark-subscriptions' ) . '</p></div><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="daymark-privacy-card"><input type="hidden" name="action" value="daymark_confirm_email_change"><input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '"><input type="hidden" name="token" value="' . esc_attr( $token ) . '"><button class="daymark-button" type="submit">' . esc_html__( 'Confirm email change', 'daymark-subscriptions' ) . '</button></form></section>';
	}

	public function redirect_legacy_profile_page() {
		if ( is_admin() || ! is_page( 'profile' ) ) return;
		$target = is_user_logged_in() ? get_author_posts_url( get_current_user_id() ) : wp_login_url( get_permalink() );
		wp_safe_redirect( $target, 301 );
		exit;
	}

	public function load_author_template( $template ) {
		return is_author() ? DAYMARK_SUBSCRIPTIONS_DIR . 'templates/author.php' : $template;
	}

	public function render_privacy_security() {
		if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please log in to manage your privacy and security settings.', 'daymark-subscriptions' ) . '</p>';
		$this->enqueue_branding();
		wp_localize_script( 'daymark-privacy-security', 'DaymarkPrivacySecurityConfig', array( 'apiBase' => esc_url_raw( rest_url( 'daymark/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
		$user_id = get_current_user_id();
		$visibility = get_user_meta( $user_id, 'daymark_profile_visibility', true );
		$product_updates = (bool) get_user_meta( $user_id, 'daymark_product_updates', true );
			ob_start(); ?>
		<section class="daymark-privacy-security" aria-labelledby="daymark-privacy-security-title">
			<div class="daymark-form-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Account', 'daymark-subscriptions' ); ?></span><h2 id="daymark-privacy-security-title"><?php echo esc_html__( 'Privacy & security', 'daymark-subscriptions' ); ?></h2><p><?php echo esc_html__( 'Control what appears publicly and keep your account access secure.', 'daymark-subscriptions' ); ?></p></div>
			<div class="daymark-privacy-security-layout">
				<form class="daymark-privacy-security-form daymark-privacy-card">
					<div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Privacy', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html__( 'Your preferences', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'These controls affect your member profile and optional communication.', 'daymark-subscriptions' ); ?></p></div>
					<label class="daymark-check-field"><input name="profile_visibility" type="checkbox" value="public" <?php checked( 'private' !== $visibility ); ?>><span><?php echo esc_html__( 'Show my public profile', 'daymark-subscriptions' ); ?><small><?php echo esc_html__( 'When enabled, visitors can view your name, picture, and description on your author profile.', 'daymark-subscriptions' ); ?></small></span></label>
					<label class="daymark-check-field"><input name="product_updates" type="checkbox" <?php checked( $product_updates ); ?>><span><?php echo esc_html__( 'Receive optional product updates', 'daymark-subscriptions' ); ?><small><?php echo esc_html__( 'Get occasional updates about new features and improvements.', 'daymark-subscriptions' ); ?></small></span></label>
					<p class="daymark-privacy-security-status" role="status" aria-live="polite"></p><button class="daymark-button" type="submit"><?php echo esc_html__( 'Save privacy settings', 'daymark-subscriptions' ); ?></button>
				</form>
				<form class="daymark-password-reset-form daymark-privacy-card" action="<?php echo esc_url( wp_lostpassword_url() ); ?>" method="post"><div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Security', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html__( 'Reset your password', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'We will send a password reset link to your account email.', 'daymark-subscriptions' ); ?></p></div><label class="daymark-field"><span><?php echo esc_html__( 'Username or email', 'daymark-subscriptions' ); ?></span><input type="text" name="user_login" autocomplete="username" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required></label><input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/privacy-security/' ) ); ?>"><button class="daymark-button" type="submit" name="wp-submit"><?php echo esc_html__( 'Send password reset link', 'daymark-subscriptions' ); ?></button></form>
				<section class="daymark-privacy-card daymark-privacy-data-actions"><div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Your data', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html__( 'Export or request deletion', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'Download the account data Daymark stores, or send an administrator a deletion request. Billing records may be retained where required.', 'daymark-subscriptions' ); ?></p></div><div class="daymark-privacy-data-buttons"><button type="button" class="daymark-button daymark-button-secondary" data-daymark-privacy-export><?php echo esc_html__( 'Download my data', 'daymark-subscriptions' ); ?></button><button type="button" class="daymark-button daymark-button-danger" data-daymark-privacy-delete><?php echo esc_html__( 'Request account deletion', 'daymark-subscriptions' ); ?></button><button type="button" class="daymark-button daymark-button-secondary" data-daymark-privacy-logout><?php echo esc_html__( 'Sign out all sessions', 'daymark-subscriptions' ); ?></button></div><p class="daymark-privacy-data-status" role="status" aria-live="polite"></p></section>
			</div>
		</section>
		<?php return ob_get_clean();
	}

	public function render_subscription_management() {
		if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please log in to manage your subscription.', 'daymark-subscriptions' ) . '</p>';
		$this->enqueue_branding();
		wp_localize_script( 'daymark-subscription-management', 'DaymarkSubscriptionManagementConfig', array( 'apiBase' => esc_url_raw( rest_url( 'daymark/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'locale' => get_locale(), 'timezone' => wp_timezone_string(), 'plansUrl' => home_url( '/plans/' ), 'strings' => array( 'noInvoices' => __( 'No invoices yet.', 'daymark-subscriptions' ), 'viewInvoice' => __( 'View invoice', 'daymark-subscriptions' ), 'downloadReceipt' => __( 'Download receipt', 'daymark-subscriptions' ), 'noActiveSubscription' => __( 'No active subscription', 'daymark-subscriptions' ), 'upgradePlan' => __( 'Upgrade plan', 'daymark-subscriptions' ), 'subscriptionInvoice' => __( 'Subscription invoice', 'daymark-subscriptions' ), 'noActiveSummary' => __( 'You do not have an active subscription yet.', 'daymark-subscriptions' ), 'notScheduled' => __( 'Not scheduled', 'daymark-subscriptions' ), 'paymentAwaiting' => __( 'Payment is awaiting completion.', 'daymark-subscriptions' ), 'reviewAwaiting' => __( 'Waiting for admin review. Access will start after approval.', 'daymark-subscriptions' ), 'renews' => __( 'renews', 'daymark-subscriptions' ), 'cancellationScheduled' => __( 'cancellation scheduled', 'daymark-subscriptions' ), 'until' => __( 'until', 'daymark-subscriptions' ), 'pause' => __( 'Pause subscription', 'daymark-subscriptions' ), 'resume' => __( 'Resume subscription', 'daymark-subscriptions' ), 'saving' => __( 'Saving…', 'daymark-subscriptions' ), 'resumed' => __( 'Subscription resumed.', 'daymark-subscriptions' ), 'paused' => __( 'Subscription paused.', 'daymark-subscriptions' ), 'cancelPrompt' => __( 'Optional: tell us why you are cancelling.', 'daymark-subscriptions' ), 'cancelled' => __( 'Cancellation scheduled for period end.', 'daymark-subscriptions' ), 'billingSettings' => __( 'Opening billing settings…', 'daymark-subscriptions' ), 'paymentOpening' => __( 'Opening payment…', 'daymark-subscriptions' ), 'daily' => __( 'Daily', 'daymark-subscriptions' ), 'monthly' => __( 'Monthly', 'daymark-subscriptions' ), 'yearly' => __( 'Yearly', 'daymark-subscriptions' ) ) ) );
		wp_add_inline_script( 'daymark-subscription-management', 'window.DaymarkSubscriptionManagementConfig = window.DaymarkSubscriptionManagementConfig || {}; window.DaymarkSubscriptionManagementConfig.provider = ' . wp_json_encode( Daymark_Payment_Provider::provider() ) . ';', 'after' );
		ob_start(); ?>
			<section class="daymark-subscription-management" aria-labelledby="daymark-subscription-management-title">
			<div class="daymark-form-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Account', 'daymark-subscriptions' ); ?></span><h2 id="daymark-subscription-management-title"><?php echo esc_html__( 'Subscription management', 'daymark-subscriptions' ); ?></h2><p><?php echo esc_html__( 'Review your current plan, manage renewal, and access your invoices.', 'daymark-subscriptions' ); ?></p></div>
			<div class="daymark-subscription-management-layout">
				<article class="daymark-subscription-management-card daymark-subscription-overview">
					<header class="daymark-subscription-overview-header"><div><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Current subscription', 'daymark-subscriptions' ); ?></span><h3 class="daymark-subscription-plan-name"><?php echo esc_html__( 'Loading subscription…', 'daymark-subscriptions' ); ?></h3></div><span class="daymark-subscription-status-badge" hidden></span></header>
					<p class="daymark-subscription-management-summary" aria-live="polite"></p>
					<div class="daymark-subscription-detail-grid"><div><span><?php echo esc_html__( 'Billing amount', 'daymark-subscriptions' ); ?></span><strong class="daymark-subscription-billing-amount">—</strong></div><div><span><?php echo esc_html__( 'Billing cycle', 'daymark-subscriptions' ); ?></span><strong class="daymark-subscription-billing-cycle">—</strong></div><div><span><?php echo esc_html__( 'Next billing date', 'daymark-subscriptions' ); ?></span><strong class="daymark-subscription-next-billing">—</strong></div><div><span><?php echo esc_html__( 'Payment status', 'daymark-subscriptions' ); ?></span><strong class="daymark-subscription-payment-status">—</strong></div></div>
					<div class="daymark-member-subscription-actions"><a class="daymark-button daymark-button-secondary daymark-member-renew-subscription" href="<?php echo esc_url( home_url( '/plans/' ) ); ?>" hidden><?php echo esc_html__( 'Upgrade plan', 'daymark-subscriptions' ); ?></a><button type="button" class="daymark-button daymark-button-secondary daymark-member-payment-recovery" hidden><?php echo esc_html__( 'Retry payment', 'daymark-subscriptions' ); ?></button><button type="button" class="daymark-button daymark-button-secondary daymark-member-billing-portal" hidden><?php echo esc_html__( 'Update payment method', 'daymark-subscriptions' ); ?></button><button type="button" class="daymark-button daymark-button-secondary daymark-member-pause-subscription" hidden><?php echo esc_html__( 'Pause subscription', 'daymark-subscriptions' ); ?></button><button type="button" class="daymark-button daymark-button-danger daymark-member-cancel-subscription" hidden><?php echo esc_html__( 'Cancel at period end', 'daymark-subscriptions' ); ?></button></div><p class="daymark-member-subscription-status" role="status" aria-live="polite"></p>
				</article>
				<article class="daymark-subscription-management-card daymark-subscription-invoices"><div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Billing history', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html__( 'Invoices', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'Your latest subscription charges and receipts appear here.', 'daymark-subscriptions' ); ?></p></div><div class="daymark-member-invoice-list" aria-live="polite"></div></article>
				<article class="daymark-subscription-management-card daymark-subscription-timeline"><div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Subscription history', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html__( 'Activity timeline', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'Payment and lifecycle changes are recorded here for your account.', 'daymark-subscriptions' ); ?></p></div><div class="daymark-member-timeline-list" aria-live="polite"></div></article>
			</div>
		</section>
		<?php return ob_get_clean();
	}

	public function render_profile() {
		if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please log in to view your profile.', 'daymark-subscriptions' ) . '</p>';
		$user = wp_get_current_user();
		$this->enqueue_branding();
		$picture_url = esc_url( get_user_meta( $user->ID, 'daymark_profile_picture_url', true ) );
		$visibility = 'private' === get_user_meta( $user->ID, 'daymark_profile_visibility', true ) ? __( 'Private profile', 'daymark-subscriptions' ) : __( 'Public profile', 'daymark-subscriptions' );
		$member_since = $user->user_registered ? wp_date( get_option( 'date_format' ), strtotime( $user->user_registered ) ) : '';
		ob_start(); ?>
		<section class="daymark-profile" aria-labelledby="daymark-profile-title">
			<div class="daymark-form-heading">
				<span class="daymark-form-eyebrow"><?php echo esc_html__( 'Profile', 'daymark-subscriptions' ); ?></span>
				<h2 id="daymark-profile-title"><?php echo esc_html__( 'Your profile', 'daymark-subscriptions' ); ?></h2>
				<p><?php echo esc_html__( 'A considered public-facing profile for your member identity.', 'daymark-subscriptions' ); ?></p>
			</div>
			<article class="daymark-profile-card">
				<div class="daymark-profile-hero">
					<div class="daymark-profile-avatar">
						<?php echo $picture_url ? '<img class="daymark-profile-avatar-image" src="' . $picture_url . '" alt="' . esc_attr( $user->display_name ) . '">' : get_avatar( $user->ID, 128, '', $user->display_name, array( 'class' => array( 'daymark-profile-avatar-image' ) ) ); ?>
					</div>
					<div class="daymark-profile-copy">
						<span class="daymark-profile-kicker"><?php echo esc_html( $visibility ); ?></span>
						<h3><?php echo esc_html( $user->display_name ); ?></h3>
						<p class="daymark-profile-handle">@<?php echo esc_html( $user->user_nicename ); ?></p>
					</div>
				</div>
				<div class="daymark-profile-details">
					<div class="daymark-profile-meta"><span><?php echo esc_html__( 'Member since', 'daymark-subscriptions' ); ?></span><strong><?php echo esc_html( $member_since ); ?></strong></div>
					<div class="daymark-profile-meta"><span><?php echo esc_html__( 'Profile status', 'daymark-subscriptions' ); ?></span><strong><?php echo esc_html( $visibility ); ?></strong></div>
				</div>
				<div class="daymark-profile-about">
					<span class="daymark-profile-section-label"><?php echo esc_html__( 'About', 'daymark-subscriptions' ); ?></span>
					<?php if ( trim( $user->description ) ) : ?><div class="daymark-profile-description"><?php echo wp_kses_post( wpautop( $user->description ) ); ?></div><?php else : ?><p class="daymark-muted"><?php echo esc_html__( 'No profile description added yet.', 'daymark-subscriptions' ); ?></p><?php endif; ?>
				</div>
				<div class="daymark-profile-actions"><a class="daymark-button" href="<?php echo esc_url( home_url( '/settings/' ) ); ?>"><?php echo esc_html__( 'Edit profile', 'daymark-subscriptions' ); ?><span aria-hidden="true">→</span></a><?php if ( get_author_posts_url( $user->ID ) ) : ?><a class="daymark-button daymark-button-secondary" href="<?php echo esc_url( get_author_posts_url( $user->ID ) ); ?>"><?php echo esc_html__( 'View public profile', 'daymark-subscriptions' ); ?></a><?php endif; ?></div>
			</article>
		</section>
		<?php return ob_get_clean();
	}

	public function render_settings() {
		if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please log in to manage your member settings.', 'daymark-subscriptions' ) . '</p>';
		$this->enqueue_branding();
		wp_localize_script( 'daymark-member-settings', 'DaymarkMemberSettingsConfig', array( 'apiBase' => esc_url_raw( rest_url( 'daymark/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'plansUrl' => home_url( '/plans/' ) ) );
		$user = wp_get_current_user();
		ob_start(); ?>
		<section class="daymark-member-settings" aria-labelledby="daymark-member-settings-title">
			<div class="daymark-form-heading">
				<span class="daymark-form-eyebrow"><?php echo esc_html__( 'Account', 'daymark-subscriptions' ); ?></span>
				<h2 id="daymark-member-settings-title"><?php echo esc_html__( 'Member settings', 'daymark-subscriptions' ); ?></h2>
				<p><?php echo esc_html__( 'Manage your personal details, public profile, and account preferences.', 'daymark-subscriptions' ); ?></p>
			</div>
			<div class="daymark-settings-layout">
				<div class="daymark-settings-main">
					<form class="daymark-member-settings-form daymark-settings-personal-form">
						<div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Personal details', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html__( 'Your name', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'Use the name you want members and your account to recognize.', 'daymark-subscriptions' ); ?></p></div>
						<div class="daymark-form-fields">
							<p class="daymark-field"><label for="daymark-settings-first-name"><?php echo esc_html__( 'First name', 'daymark-subscriptions' ); ?></label><input id="daymark-settings-first-name" name="first_name" maxlength="80" autocomplete="given-name"></p>
							<p class="daymark-field"><label for="daymark-settings-last-name"><?php echo esc_html__( 'Last name', 'daymark-subscriptions' ); ?></label><input id="daymark-settings-last-name" name="last_name" maxlength="80" autocomplete="family-name"></p>
							<p class="daymark-field daymark-field-full"><label for="daymark-settings-display-name"><?php echo esc_html__( 'Display name', 'daymark-subscriptions' ); ?></label><input id="daymark-settings-display-name" name="display_name" maxlength="120" autocomplete="name" required><small><?php echo esc_html__( 'This is the name shown across your member experience.', 'daymark-subscriptions' ); ?></small></p>
							<div class="daymark-field daymark-field-full daymark-settings-editor"><label for="daymark_profile_description"><?php echo esc_html__( 'Profile description', 'daymark-subscriptions' ); ?></label><?php wp_editor( $user->description, 'daymark_profile_description', array( 'textarea_name' => 'profile_description', 'media_buttons' => false, 'teeny' => true, 'quicktags' => false, 'textarea_rows' => 6 ) ); ?><small><?php echo esc_html__( 'This description appears on your public profile.', 'daymark-subscriptions' ); ?></small></div>
							<label class="daymark-check-field daymark-field-full"><input id="daymark-settings-product-updates" name="product_updates" type="checkbox"> <span><?php echo esc_html__( 'Receive optional product updates', 'daymark-subscriptions' ); ?><small><?php echo esc_html__( 'You can change this preference at any time.', 'daymark-subscriptions' ); ?></small></span></label>
						</div>
						<p class="daymark-member-settings-status" role="status" aria-live="polite"></p><button class="daymark-button" type="submit"><?php echo esc_html__( 'Save personal details', 'daymark-subscriptions' ); ?></button>
					</form>
					<form class="daymark-member-settings-form daymark-settings-contact-form">
						<div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Profile', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html__( 'Profile picture', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'Choose an image to personalize your public profile.', 'daymark-subscriptions' ); ?></p></div>
						<div class="daymark-form-fields">
							<p class="daymark-field daymark-field-full"><label for="daymark-settings-email"><?php echo esc_html__( 'Account email', 'daymark-subscriptions' ); ?></label><input id="daymark-settings-email" name="email" type="email" maxlength="190" autocomplete="email" required><small class="daymark-settings-email-status"></small></p>
							<p class="daymark-field daymark-field-full"><label for="daymark-settings-phone"><?php echo esc_html__( 'Phone number', 'daymark-subscriptions' ); ?></label><input id="daymark-settings-phone" name="phone" type="tel" maxlength="40" autocomplete="tel"><small><?php echo esc_html__( 'Used for checkout and billing contact details.', 'daymark-subscriptions' ); ?></small></p>
							<p class="daymark-field daymark-field-full"><label for="daymark-settings-picture"><?php echo esc_html__( 'Profile picture file', 'daymark-subscriptions' ); ?></label><input id="daymark-settings-picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/gif,image/webp"><small><?php echo esc_html__( 'Upload a JPG, PNG, GIF, or WebP image up to 5 MB.', 'daymark-subscriptions' ); ?></small><img class="daymark-settings-picture-preview" alt="" hidden></p>
						</div>
						<p class="daymark-member-settings-status" role="status" aria-live="polite"></p>
						<button class="daymark-button" type="submit"><?php echo esc_html__( 'Save contact details', 'daymark-subscriptions' ); ?></button>
						<div class="daymark-picture-crop-modal" hidden role="dialog" aria-modal="true" aria-labelledby="daymark-picture-crop-title"><div class="daymark-picture-crop-dialog"><div class="daymark-settings-section-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Profile image', 'daymark-subscriptions' ); ?></span><h3 id="daymark-picture-crop-title"><?php echo esc_html__( 'Adjust your picture', 'daymark-subscriptions' ); ?></h3><p><?php echo esc_html__( 'Drag the image until the crop looks right, then save it.', 'daymark-subscriptions' ); ?></p></div><canvas class="daymark-picture-crop-canvas" width="320" height="320" aria-label="<?php echo esc_attr__( 'Profile picture crop area', 'daymark-subscriptions' ); ?>"></canvas><div class="daymark-picture-crop-actions"><button type="button" class="daymark-button daymark-button-secondary daymark-picture-crop-cancel"><?php echo esc_html__( 'Cancel', 'daymark-subscriptions' ); ?></button><button type="button" class="daymark-button daymark-picture-crop-save"><?php echo esc_html__( 'Save picture', 'daymark-subscriptions' ); ?></button></div></div></div>
					</form>
				</div>
			</div>
		</section>
		<?php return ob_get_clean();
	}

	public function render() {
		$plans = Daymark_Subscriptions_DB::get_plans();
		$this->enqueue_branding();
		$checkout_page = get_page_by_path( 'checkout' );
		$checkout_status = Daymark_Payment_Provider::checkout_status();
		wp_localize_script( 'daymark-subscriptions-ui', 'DaymarkSubscriptionsConfig', array( 'apiBase' => esc_url_raw( rest_url( 'daymark/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'loggedIn' => is_user_logged_in(), 'checkoutUrl' => $checkout_page ? get_permalink( $checkout_page ) : home_url( '/checkout/' ), 'planChangeTiming' => Daymark_Subscriptions_Admin::plan_change_timing(), 'locale' => get_locale(), 'timezone' => wp_timezone_string(), 'plans' => $plans, 'strings' => array( 'choosePlan' => __( 'Choose a plan', 'daymark-subscriptions' ), 'comparePlans' => __( 'Compare plans', 'daymark-subscriptions' ), 'pendingReview' => __( 'Pending review', 'daymark-subscriptions' ), 'paymentPending' => __( 'Payment pending', 'daymark-subscriptions' ), 'planChangeSaved' => __( 'Plan change saved.', 'daymark-subscriptions' ), 'previousEnded' => __( 'Your previous subscription has ended. Choose a plan to restart.', 'daymark-subscriptions' ), 'noActive' => __( 'No active subscription. Choose a plan to get started.', 'daymark-subscriptions' ), 'scheduled' => __( 'Scheduled', 'daymark-subscriptions' ), 'currentPlan' => __( 'Current plan', 'daymark-subscriptions' ), 'upgrade' => __( 'Upgrade', 'daymark-subscriptions' ), 'downgrade' => __( 'Downgrade', 'daymark-subscriptions' ), 'changePlan' => __( 'Change plan', 'daymark-subscriptions' ), 'changeTo' => __( 'Change to', 'daymark-subscriptions' ), 'checkoutOpening' => __( 'Opening checkout…', 'daymark-subscriptions' ), 'checkoutMissing' => __( 'Checkout page is not configured.', 'daymark-subscriptions' ), 'confirmUnavailable' => __( 'Plan change confirmation is unavailable. Please refresh the page and try again.', 'daymark-subscriptions' ), 'detailsUnavailable' => __( 'Plan details are unavailable. Please refresh the page and try again.', 'daymark-subscriptions' ), 'changeNow' => __( 'Change now to', 'daymark-subscriptions' ), 'scheduleChange' => __( 'Schedule change to', 'daymark-subscriptions' ), 'immediate' => __( 'Immediately', 'daymark-subscriptions' ), 'nextRenewal' => __( 'At next renewal', 'daymark-subscriptions' ), 'confirmChange' => __( 'Confirm plan change', 'daymark-subscriptions' ), 'scheduleUpgrade' => __( 'Schedule upgrade', 'daymark-subscriptions' ), 'scheduleDowngrade' => __( 'Schedule downgrade', 'daymark-subscriptions' ), 'savingChange' => __( 'Saving plan change…', 'daymark-subscriptions' ), 'savedChange' => __( 'Plan change saved.', 'daymark-subscriptions' ), 'cancelingChange' => __( 'Cancelling scheduled change…', 'daymark-subscriptions' ), 'cancelledChange' => __( 'Scheduled plan change cancelled.', 'daymark-subscriptions' ), 'paymentIssue' => __( 'Payment issue: update your payment method with the provider.', 'daymark-subscriptions' ), 'renewal' => __( 'renews', 'daymark-subscriptions' ), 'until' => __( 'until', 'daymark-subscriptions' ), 'next' => __( 'the next renewal', 'daymark-subscriptions' ) ) ) );
		wp_add_inline_script( 'daymark-subscriptions-ui', 'window.DaymarkSubscriptionsConfig = window.DaymarkSubscriptionsConfig || {}; window.DaymarkSubscriptionsConfig.provider = ' . wp_json_encode( Daymark_Payment_Provider::provider() ) . ';', 'after' );
		wp_add_inline_script( 'daymark-subscriptions-ui', 'window.DaymarkSubscriptionsConfig.checkoutReady = ' . wp_json_encode( ! empty( $checkout_status['ready'] ) ) . '; window.DaymarkSubscriptionsConfig.checkoutEnvironment = ' . wp_json_encode( $checkout_status['environment'] ) . '; window.DaymarkSubscriptionsConfig.checkoutProvider = ' . wp_json_encode( $checkout_status['provider'] ) . '; window.DaymarkSubscriptionsConfig.checkoutMode = ' . wp_json_encode( $checkout_status['mode'] ) . '; window.DaymarkSubscriptionsConfig.checkoutTestingOnly = ' . wp_json_encode( ! empty( $checkout_status['testing_only'] ) ) . '; window.DaymarkSubscriptionsConfig.checkoutMessage = ' . wp_json_encode( $checkout_status['message'] ) . ';', 'after' );
		ob_start(); ?>
			<section class="daymark-subscriptions" aria-labelledby="daymark-subscriptions-title">
			<div class="daymark-form-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Membership', 'daymark-subscriptions' ); ?></span><h2 id="daymark-subscriptions-title"><?php echo esc_html__( 'Choose a plan', 'daymark-subscriptions' ); ?></h2><p><?php echo esc_html__( 'Select the access level that fits you. You can manage your subscription after checkout.', 'daymark-subscriptions' ); ?></p></div>
			<div class="daymark-current-subscription" aria-live="polite"></div>
			<div class="daymark-plan-list">
				<?php foreach ( $plans as $plan ) : ?>
					<article class="daymark-plan<?php echo ! empty( $plan['featured'] ) ? ' is-featured' : ''; ?>" data-daymark-plan-card-id="<?php echo esc_attr( $plan['id'] ); ?>" data-daymark-plan-name="<?php echo esc_attr( $plan['name'] ); ?>" data-daymark-plan-price="<?php echo esc_attr( (float) $plan['price'] ); ?>" data-daymark-plan-currency="<?php echo esc_attr( $plan['currency'] ); ?>" data-daymark-plan-interval="<?php echo esc_attr( $plan['interval_unit'] ); ?>">
						<div><?php if ( ! empty( $plan['featured'] ) ) : ?><span class="daymark-plan-featured"><?php echo esc_html__( 'Recommended', 'daymark-subscriptions' ); ?></span><?php endif; ?><span class="daymark-plan-label"><?php echo esc_html__( 'Plan', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html( $plan['name'] ); ?></h3><div class="daymark-plan-description"><?php echo wp_kses_post( $plan['description'] ); ?></div><?php if ( ! empty( $plan['benefits'] ) ) : ?><ul class="daymark-plan-benefits"><?php foreach ( $plan['benefits'] as $benefit ) : ?><li><?php echo esc_html( $benefit ); ?></li><?php endforeach; ?></ul><?php endif; ?><?php if ( ! empty( $plan['refund_policy'] ) ) : ?><div class="daymark-plan-refund-policy"><strong><?php echo esc_html__( 'Refund policy', 'daymark-subscriptions' ); ?></strong><div><?php echo wp_kses_post( $plan['refund_policy'] ); ?></div></div><?php endif; ?></div>
						<p class="daymark-plan-price"><strong><?php echo esc_html( $plan['currency'] . ' ' . number_format_i18n( (float) $plan['price'], 2 ) ); ?></strong><span>/ <?php echo esc_html( $plan['interval_unit'] ); ?></span><?php if ( ! empty( $plan['intro_cycles'] ) ) : ?><small><?php echo esc_html( sprintf( __( '%1$s for the first %2$d billing cycle(s)', 'daymark-subscriptions' ), $plan['currency'] . ' ' . number_format_i18n( (float) $plan['intro_price'], 2 ), absint( $plan['intro_cycles'] ) ) ); ?></small><?php endif; ?></p>
						<button class="daymark-button" type="button" data-daymark-plan-id="<?php echo esc_attr( $plan['id'] ); ?>"><span class="daymark-plan-action-label"><?php echo esc_html( $plan['cta_label'] ?: __( 'Choose plan', 'daymark-subscriptions' ) ); ?></span><span aria-hidden="true">→</span></button>
					</article>
				<?php endforeach; if ( ! $plans ) : ?><div class="daymark-form-notice"><?php echo esc_html__( 'No plans are available right now.', 'daymark-subscriptions' ); ?></div><?php endif; ?>
			</div>
			<p class="daymark-subscription-status" role="status" aria-live="polite"></p><button class="daymark-button daymark-button-secondary daymark-cancel-plan-change" type="button" hidden>Cancel scheduled change</button>
			<div class="daymark-plan-change-drawer" hidden role="dialog" aria-modal="true" aria-labelledby="daymark-plan-change-title"><div class="daymark-plan-change-backdrop" data-daymark-plan-change-close></div><div class="daymark-plan-change-panel"><button class="daymark-plan-change-close" type="button" data-daymark-plan-change-close aria-label="<?php echo esc_attr__( 'Close plan change confirmation', 'daymark-subscriptions' ); ?>">×</button><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Plan change', 'daymark-subscriptions' ); ?></span><h3 id="daymark-plan-change-title"></h3><p class="daymark-plan-change-summary"></p><div class="daymark-plan-change-details"><div><span><?php echo esc_html__( 'New recurring price', 'daymark-subscriptions' ); ?></span><strong class="daymark-plan-change-price"></strong></div><div><span><?php echo esc_html__( 'Effective', 'daymark-subscriptions' ); ?></span><strong class="daymark-plan-change-effective"></strong></div></div><div class="daymark-plan-change-preview" hidden aria-live="polite"></div><p class="daymark-plan-change-note"></p><div class="daymark-plan-change-actions"><button class="daymark-button daymark-button-secondary" type="button" data-daymark-plan-change-close><?php echo esc_html__( 'Go back', 'daymark-subscriptions' ); ?></button><button class="daymark-button daymark-plan-change-confirm" type="button"><?php echo esc_html__( 'Confirm change', 'daymark-subscriptions' ); ?></button></div></div></div>
		</section><?php return ob_get_clean();
	}

	public function render_checkout() {
		$plan = Daymark_Subscriptions_DB::get_plan( absint( $_GET['plan_id'] ?? 0 ), true );
		$this->enqueue_branding();
		$current_user = wp_get_current_user();
		$checkout_status = Daymark_Payment_Provider::checkout_status();
		wp_add_inline_script( 'daymark-checkout-ui', 'window.DaymarkCheckoutConfig = window.DaymarkCheckoutConfig || {}; window.DaymarkCheckoutConfig.checkoutReady = ' . wp_json_encode( ! empty( $checkout_status['ready'] ) ) . '; window.DaymarkCheckoutConfig.checkoutEnvironment = ' . wp_json_encode( $checkout_status['environment'] ) . '; window.DaymarkCheckoutConfig.checkoutProvider = ' . wp_json_encode( $checkout_status['provider'] ) . '; window.DaymarkCheckoutConfig.checkoutMode = ' . wp_json_encode( $checkout_status['mode'] ) . '; window.DaymarkCheckoutConfig.checkoutTestingOnly = ' . wp_json_encode( ! empty( $checkout_status['testing_only'] ) ) . '; window.DaymarkCheckoutConfig.checkoutMessage = ' . wp_json_encode( $checkout_status['message'] ) . ';', 'after' );
		wp_localize_script( 'daymark-checkout-ui', 'DaymarkCheckoutConfig', array( 'apiBase' => esc_url_raw( rest_url( 'daymark/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'loggedIn' => is_user_logged_in(), 'customer' => array( 'name' => is_user_logged_in() ? $current_user->display_name : '', 'email' => is_user_logged_in() ? $current_user->user_email : '', 'phone' => is_user_logged_in() ? (string) get_user_meta( $current_user->ID, 'billing_phone', true ) : '' ) ) );
		if ( ! $plan ) return '<section class="daymark-checkout"><div class="daymark-form-heading"><span class="daymark-form-eyebrow">' . esc_html__( 'Checkout', 'daymark-subscriptions' ) . '</span><h2>' . esc_html__( 'Choose a plan first', 'daymark-subscriptions' ) . '</h2><p>' . esc_html__( 'Select a subscription plan before continuing to checkout.', 'daymark-subscriptions' ) . '</p></div><a class="daymark-button" href="' . esc_url( home_url( '/plans/' ) ) . '">' . esc_html__( 'View plans', 'daymark-subscriptions' ) . '</a></section>';
		$provider = Daymark_Payment_Provider::provider();
		$development = Daymark_Payment_Provider::development_mode(); $provider_label = $development ? __( 'Development test gateway', 'daymark-subscriptions' ) : ( 'stripe' === $provider ? ( 'test' === $checkout_status['mode'] ? __( 'Stripe Sandbox', 'daymark-subscriptions' ) : __( 'Stripe Live', 'daymark-subscriptions' ) ) : __( 'WooCommerce checkout', 'daymark-subscriptions' ) ); $payment_note = $checkout_status['message']; $checkout_action = ! empty( $checkout_status['ready'] ) ? ( $development ? __( 'Activate test subscription', 'daymark-subscriptions' ) : __( 'Continue to secure payment', 'daymark-subscriptions' ) ) : __( 'Checkout unavailable', 'daymark-subscriptions' );
		ob_start(); ?><section class="daymark-checkout" data-daymark-checkout-plan="<?php echo esc_attr( $plan['id'] ); ?>" aria-labelledby="daymark-checkout-title"><div class="daymark-form-heading"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Checkout', 'daymark-subscriptions' ); ?></span><h2 id="daymark-checkout-title"><?php echo esc_html__( 'Review your plan', 'daymark-subscriptions' ); ?></h2><p><?php echo esc_html__( 'Enter your details, then continue to secure payment.', 'daymark-subscriptions' ); ?></p></div><div class="daymark-checkout-layout"><article class="daymark-checkout-summary"><span class="daymark-plan-label"><?php echo esc_html__( 'Selected plan', 'daymark-subscriptions' ); ?></span><h3><?php echo esc_html( $plan['name'] ); ?></h3><div class="daymark-checkout-description"><?php echo wp_kses_post( $plan['description'] ); ?></div><p class="daymark-checkout-price"><strong><?php echo esc_html( $plan['currency'] . ' ' . number_format_i18n( (float) $plan['price'], 2 ) ); ?></strong><span>/ <?php echo esc_html( $plan['interval_unit'] ); ?></span><?php if ( ! empty( $plan['intro_cycles'] ) ) : ?><small><?php echo esc_html( sprintf( __( '%1$s for the first %2$d billing cycle(s)', 'daymark-subscriptions' ), $plan['currency'] . ' ' . number_format_i18n( (float) $plan['intro_price'], 2 ), absint( $plan['intro_cycles'] ) ) ); ?></small><?php endif; ?></p></article><aside class="daymark-checkout-details"><div class="daymark-checkout-fields"><label><?php echo esc_html__( 'Full name', 'daymark-subscriptions' ); ?><input class="daymark-checkout-name" type="text" autocomplete="name" maxlength="120" value="<?php echo esc_attr( $current_user->display_name ?? '' ); ?>" required></label><label><?php echo esc_html__( 'Email', 'daymark-subscriptions' ); ?><input class="daymark-checkout-email" type="email" autocomplete="email" maxlength="190" value="<?php echo esc_attr( $current_user->user_email ?? '' ); ?>" required></label><label><?php echo esc_html__( 'Phone', 'daymark-subscriptions' ); ?><input class="daymark-checkout-phone" type="tel" autocomplete="tel" maxlength="40" value="<?php echo esc_attr( is_user_logged_in() ? get_user_meta( $current_user->ID, 'billing_phone', true ) : '' ); ?>" required></label></div><div><span><?php echo esc_html__( 'Payment provider', 'daymark-subscriptions' ); ?></span><strong><?php echo esc_html( $provider_label ); ?></strong></div><div><span><?php echo esc_html__( 'Billing', 'daymark-subscriptions' ); ?></span><strong><?php echo esc_html__( 'Recurring subscription', 'daymark-subscriptions' ); ?></strong></div><p><?php echo esc_html( $payment_note ); ?></p><button class="daymark-button daymark-checkout-submit" type="button"><?php echo esc_html( $checkout_action ); ?><span aria-hidden="true">→</span></button><p class="daymark-checkout-status" role="status" aria-live="polite"></p></aside></div></section><?php return ob_get_clean();
	}

	private function enqueue_branding() {
		$vars = Daymark_Subscriptions_Admin::frontend_css_vars();
		wp_add_inline_style( 'daymark-subscriptions-frontend', '.daymark-subscriptions,.daymark-member-settings,.daymark-checkout,.daymark-profile,.daymark-privacy-security,.daymark-subscription-management,.daymark-author-profile{' . $vars . '}' );
	}
}
