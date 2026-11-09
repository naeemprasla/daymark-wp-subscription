<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Subscription_Access {
	const PAGE_RULES_OPTION = 'daymark_page_access_rules';
	const MENU_RULES_OPTION = 'daymark_menu_access_rules';
	const ALLOWED_STATUSES = array( 'active', 'paused', 'past_due' );
	private $elementor_registered = false;

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'protect_page' ) );
		add_action( 'template_redirect', array( $this, 'render_checkout_success' ), 1 );
		add_filter( 'wp_nav_menu_objects', array( $this, 'filter_menu_items' ), 10, 2 );
		add_action( 'elementor/element/after_section_end', array( $this, 'register_elementor_controls' ), 10, 3 );
		if ( did_action( 'elementor/loaded' ) ) $this->register_elementor_hooks(); else add_action( 'elementor/loaded', array( $this, 'register_elementor_hooks' ) );
	}

	public static function can_access( $user_id, $plans, $statuses = array( 'active' ) ) {
		$user_id = absint( $user_id ); $plans = self::clean_list( $plans, 'sanitize_title' ); $statuses = self::clean_list( $statuses, 'sanitize_key' );
		$statuses = array_values( array_intersect( $statuses, self::ALLOWED_STATUSES ) );
		if ( ! $user_id || ! $plans || ! $statuses ) return false;
		$account_state = sanitize_key( get_user_meta( $user_id, 'daymark_account_state', true ) );
		if ( $account_state && 'active' !== $account_state ) return false;
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( $user_id );
		if ( ! $subscription || ! in_array( $subscription['status'], $statuses, true ) ) return false;
		$plan = Daymark_Subscriptions_DB::get_plan( $subscription['plan_id'] );
		return $plan && in_array( $plan['slug'], $plans, true );
	}

	public static function current_subscription( $user_id = 0 ) { return Daymark_Subscriptions_DB::get_user_subscription( $user_id ? $user_id : get_current_user_id() ); }

	public function protect_page() {
		if ( ! is_singular() || is_front_page() ) return;
		$rules = get_option( self::PAGE_RULES_OPTION, array() ); $rule = $rules[ get_queried_object_id() ] ?? array();
		if ( ! $rule || self::can_access( get_current_user_id(), $rule['plans'] ?? array(), $rule['statuses'] ?? array( 'active' ) ) ) return;
		if ( ! is_user_logged_in() ) { wp_safe_redirect( wp_login_url( get_permalink() ) ); exit; }
		self::render_access_revoked( get_queried_object_id() );
	}

	public static function render_checkout_success() {
		$checkout_state = isset( $_GET['daymark_checkout'] ) && is_scalar( $_GET['daymark_checkout'] ) ? sanitize_key( wp_unslash( $_GET['daymark_checkout'] ) ) : '';
		if ( ! in_array( $checkout_state, array( 'success', 'review' ), true ) ) return;
		nocache_headers();
		$brand_color = class_exists( 'Daymark_Subscriptions_Admin' ) ? Daymark_Subscriptions_Admin::brand_color() : '#087f73';
		$design_style = class_exists( 'Daymark_Subscriptions_Admin' ) ? Daymark_Subscriptions_Admin::frontend_css_vars( $brand_color ) : '--daymark-front-accent:' . $brand_color . ';';
		$home_url = home_url( '/' ); $development = class_exists( 'Daymark_Payment_Provider' ) && Daymark_Payment_Provider::development_mode(); $review_request = 'review' === $checkout_state; $has_header = (bool) locate_template( 'header.php' ); $has_footer = (bool) locate_template( 'footer.php' );
		if ( $has_header ) get_header(); else { ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html__( 'Checkout complete', 'daymark-subscriptions' ); ?></title><?php wp_head(); ?></head><body <?php body_class( 'daymark-checkout-success-page' ); ?>><?php wp_body_open(); }
		include DAYMARK_SUBSCRIPTIONS_DIR . 'templates/checkout-success.php';
		if ( $has_footer ) get_footer(); else { wp_footer(); ?></body></html><?php } exit;
	}

	private static function render_access_revoked( $post_id ) {
		status_header( 403 ); nocache_headers();
		$page_title = get_the_title( $post_id ); $home_url = home_url( '/' ); $brand_color = class_exists( 'Daymark_Subscriptions_Admin' ) ? Daymark_Subscriptions_Admin::brand_color() : '#087f73'; $design_style = class_exists( 'Daymark_Subscriptions_Admin' ) ? Daymark_Subscriptions_Admin::frontend_css_vars( $brand_color ) : '--daymark-front-accent:' . $brand_color . ';'; $has_header = (bool) locate_template( 'header.php' ); $has_footer = (bool) locate_template( 'footer.php' );
		do_action( 'daymark_subscriptions_enqueue_access_revoked_assets' );
		if ( $has_header ) get_header(); else { ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html__( 'Access revoked', 'daymark-subscriptions' ); ?></title><?php wp_head(); ?></head><body <?php body_class( 'daymark-access-revoked-page' ); ?>><?php wp_body_open(); }
		include DAYMARK_SUBSCRIPTIONS_DIR . 'templates/access-revoked.php';
		if ( $has_footer ) get_footer(); else { wp_footer(); ?></body></html><?php } exit;
	}

	public function filter_menu_items( $items, $args ) {
		if ( is_admin() || current_user_can( 'manage_options' ) ) return $items;
		$rules = get_option( self::MENU_RULES_OPTION, array() ); if ( ! $rules ) return $items;
		$removed = array();
		foreach ( $items as $item ) {
			$rule = $rules[ $item->ID ] ?? array();
			if ( $rule && ! self::can_access( get_current_user_id(), $rule['plans'] ?? array(), $rule['statuses'] ?? array( 'active' ) ) ) $removed[ $item->ID ] = true;
		}
		$changed = true;
		while ( $changed ) { $changed = false; foreach ( $items as $item ) if ( ! isset( $removed[ $item->ID ] ) && $item->menu_item_parent && isset( $removed[ $item->menu_item_parent ] ) ) { $removed[ $item->ID ] = true; $changed = true; } }
		return array_values( array_filter( $items, function ( $item ) use ( $removed ) { return ! isset( $removed[ $item->ID ] ); } ) );
	}

	public static function render_restricted( $attributes, $content = '' ) {
		$attributes = shortcode_atts( array( 'plans' => '', 'statuses' => 'active', 'fallback' => '' ), $attributes, 'daymark_restricted' );
		if ( ! self::can_access( get_current_user_id(), explode( ',', $attributes['plans'] ), explode( ',', $attributes['statuses'] ) ) ) return '' !== $attributes['fallback'] ? esc_html( $attributes['fallback'] ) : '';
		return do_shortcode( $content );
	}

	public function register_elementor_hooks() {
		if ( $this->elementor_registered ) return;
		$this->elementor_registered = true;
		foreach ( array( 'section', 'column', 'container', 'widget' ) as $type ) add_filter( 'elementor/frontend/' . $type . '/should_render', array( $this, 'elementor_should_render' ), 10, 2 );
	}

	public function register_elementor_controls( $element, $section_id, $args ) {
		if ( 'section_advanced' !== $section_id || ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) ) return;
		static $plan_options = null;
		if ( null === $plan_options ) { $plan_options = array(); foreach ( Daymark_Subscriptions_DB::get_plans() as $plan ) $plan_options[ $plan['slug'] ] = $plan['name']; }
		$element->start_controls_section( 'daymark_subscription_access', array( 'label' => __( 'Subscription Access', 'daymark-subscriptions' ), 'tab' => \Elementor\Controls_Manager::TAB_ADVANCED ) );
		$element->add_control( 'daymark_plans', array( 'label' => __( 'Allowed plans', 'daymark-subscriptions' ), 'type' => \Elementor\Controls_Manager::SELECT2, 'multiple' => true, 'options' => $plan_options ) );
		$element->end_controls_section();
	}

	public function elementor_should_render( $should_render, $element ) {
		if ( ! $should_render || is_admin() || ! is_object( $element ) ) return $should_render;
		$settings = method_exists( $element, 'get_settings_for_display' ) ? $element->get_settings_for_display() : array();
		if ( ! empty( $settings['daymark_plans'] ) && ! self::can_access( get_current_user_id(), $settings['daymark_plans'], array( 'active' ) ) ) return false;
		$attributes = $element->get_settings( 'custom_attributes' );
		if ( ! is_string( $attributes ) || '' === trim( $attributes ) ) return $should_render;
		$rules = array();
		foreach ( preg_split( '/[\r\n]+/', $attributes ) as $attribute ) { $parts = array_map( 'trim', explode( '|', $attribute, 2 ) ); if ( count( $parts ) === 2 ) $rules[ sanitize_key( $parts[0] ) ] = $parts[1]; }
		if ( empty( $rules['data-daymark-plans'] ) ) return $should_render;
		return self::can_access( get_current_user_id(), explode( ',', $rules['data-daymark-plans'] ), explode( ',', $rules['data-daymark-statuses'] ?? 'active' ) );
	}

	public static function clean_rules( $rules ) {
		$clean = array();
		foreach ( (array) $rules as $page_id => $rule ) {
			$page_id = absint( $page_id ); $plans = self::clean_list( $rule['plans'] ?? array(), 'sanitize_title' ); $statuses = array_values( array_intersect( self::clean_list( $rule['statuses'] ?? array(), 'sanitize_key' ), self::ALLOWED_STATUSES ) );
			if ( $page_id && $plans && $statuses ) $clean[ $page_id ] = array( 'plans' => $plans, 'statuses' => $statuses );
		}
		return $clean;
	}

	private static function clean_list( $values, $sanitizer ) { return array_values( array_unique( array_filter( array_map( $sanitizer, (array) $values ) ) ) ); }
}
