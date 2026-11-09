<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Subscription_Features {
	const DEFINITIONS_OPTION = 'daymark_feature_definitions';
	const VALUES_OPTION = 'daymark_plan_feature_values';
	const TYPES = array( 'boolean', 'limit', 'text' );

	public static function definitions() {
		return (array) get_option( self::DEFINITIONS_OPTION, array() );
	}

	public static function get( $slug ) {
		$slug = sanitize_title( $slug );
		$definitions = self::definitions();
		return isset( $definitions[ $slug ] ) ? $definitions[ $slug ] : false;
	}

	public static function save_definition( $data, $existing_slug = '' ) {
		$slug = sanitize_title( $data['slug'] ?? '' );
		$name = sanitize_text_field( $data['name'] ?? '' );
		$type = sanitize_key( $data['type'] ?? 'boolean' );
		if ( ! preg_match( '/^[a-z0-9-]{2,80}$/', $slug ) || ! $name || strlen( $name ) > 120 || ! in_array( $type, self::TYPES, true ) ) return false;
		$definitions = self::definitions(); $old = $existing_slug && isset( $definitions[ $existing_slug ] ) ? $definitions[ $existing_slug ] : array();
		if ( $existing_slug && $existing_slug !== $slug && isset( $definitions[ $slug ] ) ) return false;
		if ( $existing_slug && isset( $definitions[ $existing_slug ] ) ) unset( $definitions[ $existing_slug ] );
		$plan_ids = array_values( array_filter( array_map( 'absint', (array) ( $data['plan_ids'] ?? ( $old['plan_ids'] ?? array() ) ) ) ) );
		$definitions[ $slug ] = array( 'slug' => $slug, 'name' => $name, 'description' => wp_kses_post( $data['description'] ?? ( $old['description'] ?? '' ) ), 'type' => $type, 'default' => self::normalize_value( $data['default'] ?? ( $old['default'] ?? '' ), $type ), 'plan_ids' => $plan_ids );
		$updated = update_option( self::DEFINITIONS_OPTION, $definitions, false );
		if ( ! $updated && get_option( self::DEFINITIONS_OPTION, array() ) !== $definitions ) return false;
		return $definitions[ $slug ];
	}

	public static function clean_values( $values ) {
		$clean = array();
		foreach ( (array) $values as $slug => $value ) {
			$definition = self::get( $slug );
			if ( ! $definition ) continue;
			$clean[ $definition['slug'] ] = self::normalize_value( $value, $definition['type'] );
		}
		return $clean;
	}

	public static function values_for_plan( $plan_id ) {
		$all = (array) get_option( self::VALUES_OPTION, array() );
		return self::clean_values( $all[ absint( $plan_id ) ] ?? array() );
	}

	public static function save_values_for_plan( $plan_id, $values ) {
		$plan_id = absint( $plan_id );
		if ( ! $plan_id || ! Daymark_Subscriptions_DB::get_plan( $plan_id ) ) return false;
		$all = (array) get_option( self::VALUES_OPTION, array() );
		$all[ $plan_id ] = array();
		foreach ( self::definitions() as $slug => $definition ) $all[ $plan_id ][ $slug ] = 'boolean' === $definition['type'] ? false : self::default_value( $definition );
		$all[ $plan_id ] = array_merge( $all[ $plan_id ], self::clean_values( $values ) );
		$updated = update_option( self::VALUES_OPTION, $all, false );
		if ( ! $updated && get_option( self::VALUES_OPTION, array() ) !== $all ) return false;
		return true;
	}

	public static function for_user( $user_id ) {
		$subscription = Daymark_Subscriptions_DB::get_active_subscription( absint( $user_id ) );
		$values = array();
		$plan_id = $subscription ? absint( $subscription['plan_id'] ) : 0;
		foreach ( self::definitions() as $slug => $definition ) if ( empty( $definition['plan_ids'] ) || ( $plan_id && in_array( $plan_id, array_map( 'absint', (array) $definition['plan_ids'] ), true ) ) ) $values[ $slug ] = self::default_value( $definition );
		if ( ! $subscription ) return $values;
		foreach ( self::values_for_plan( $subscription['plan_id'] ) as $slug => $value ) {
			$definition = self::get( $slug );
			if ( $definition && ( empty( $definition['plan_ids'] ) || in_array( $plan_id, array_map( 'absint', (array) $definition['plan_ids'] ), true ) ) ) $values[ $slug ] = $value;
		}
		return $values;
	}

	public static function value( $user_id, $slug ) {
		$slug = sanitize_title( $slug );
		$definition = self::get( $slug );
		if ( ! $definition ) return null;
		$values = self::for_user( $user_id );
		$value = $values[ $slug ] ?? null;
		return apply_filters( 'daymark_feature_value', $value, $definition, absint( $user_id ) );
	}

	public static function has( $user_id, $slug ) {
		$definition = self::get( $slug );
		$value = self::value( $user_id, $slug );
		if ( ! $definition ) return false;
		if ( 'boolean' === $definition['type'] ) return true === $value;
		if ( 'limit' === $definition['type'] ) return (int) $value > 0 || -1 === (int) $value;
		return '' !== trim( (string) $value );
	}

	public static function normalize_value( $value, $type ) {
		if ( 'boolean' === $type ) return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		if ( 'limit' === $type ) return max( -1, min( 1000000000, (int) $value ) );
		return sanitize_text_field( (string) $value );
	}

	private static function default_value( $definition ) {
		return self::normalize_value( $definition['default'] ?? '', $definition['type'] );
	}
}
