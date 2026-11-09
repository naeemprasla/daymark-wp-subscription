<?php
defined( 'ABSPATH' ) || exit;

final class Daymark_Subscriptions_DB {
	const DB_VERSION = '1.19.0';
	const DB_ENGINE = 'InnoDB';
	// shortcut: 15-minute renewal lease, increase when a gateway can legitimately exceed this duration.
	const RENEWAL_LEASE_SECONDS = 900;
	// shortcut: retry abandoned webhook claims after 10 minutes, increase when handlers can run longer.
	const EVENT_CLAIM_TIMEOUT_SECONDS = 600;
	const PAYMENT_GRACE_DAYS = 7;

	public static function payment_grace_days() {
		$value = absint( get_option( 'daymark_subscriptions_payment_grace_days', self::PAYMENT_GRACE_DAYS ) );
		return max( 1, min( 90, $value ?: self::PAYMENT_GRACE_DAYS ) );
	}

	public static function tables() {
		global $wpdb;
		return array(
			'plans'         => $wpdb->prefix . 'daymark_plans',
			'subscriptions' => $wpdb->prefix . 'daymark_subscriptions',
			'invoices'      => $wpdb->prefix . 'daymark_invoices',
			'events'        => $wpdb->prefix . 'daymark_stripe_events',
			'rate_limits'   => $wpdb->prefix . 'daymark_rate_limits',
			'audit_logs'    => $wpdb->prefix . 'daymark_audit_logs',
			'operations'    => $wpdb->prefix . 'daymark_operations',
			'job_runs'      => $wpdb->prefix . 'daymark_job_runs',
			'checkouts'     => $wpdb->prefix . 'daymark_checkouts',
			'email_logs'    => $wpdb->prefix . 'daymark_email_logs',
		);
	}

	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables = self::tables();
		$collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$tables['plans']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(80) NOT NULL,
			name varchar(120) NOT NULL,
			description text NOT NULL,
			refund_policy text NOT NULL,
			price decimal(10,2) NOT NULL DEFAULT 0.00,
			intro_price decimal(10,2) NOT NULL DEFAULT 0.00,
			intro_cycles tinyint(3) unsigned NOT NULL DEFAULT 0,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			interval_unit varchar(20) NOT NULL DEFAULT 'month',
			trial_days int(11) unsigned NOT NULL DEFAULT 0,
			approval_mode varchar(20) NOT NULL DEFAULT 'direct',
			pause_enabled tinyint(1) NOT NULL DEFAULT 1,
			cancellation_mode varchar(20) NOT NULL DEFAULT 'inherit',
			sort_order int(11) NOT NULL DEFAULT 0,
			featured tinyint(1) NOT NULL DEFAULT 0,
			benefits text NOT NULL,
			catalog_visibility varchar(20) NOT NULL DEFAULT 'visible',
			cta_label varchar(80) NOT NULL DEFAULT '',
			stripe_price_id varchar(190) NULL,
			woocommerce_product_id bigint(20) unsigned NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			setup_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			minimum_commitment_cycles tinyint(3) unsigned NOT NULL DEFAULT 0,
			allow_promotion_codes tinyint(1) NOT NULL DEFAULT 0,
			quantity_enabled tinyint(1) NOT NULL DEFAULT 0,
			quantity_min int(10) unsigned NOT NULL DEFAULT 1,
			quantity_max int(10) unsigned NOT NULL DEFAULT 1,
			tax_behavior varchar(20) NOT NULL DEFAULT 'automatic',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY stripe_price_id (stripe_price_id),
			KEY active (active)
		) ENGINE=InnoDB $collate;
		CREATE TABLE {$tables['invoices']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subscription_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			plan_id bigint(20) unsigned NOT NULL,
			provider_invoice_id varchar(190) NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			status varchar(20) NOT NULL DEFAULT 'paid',
			invoice_url varchar(500) NOT NULL DEFAULT '',
			issued_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY provider_invoice_id (provider_invoice_id),
			KEY user_issued (user_id, issued_at),
			KEY subscription_id (subscription_id)
		) ENGINE=InnoDB $collate;
		CREATE TABLE {$tables['subscriptions']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			plan_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			started_at datetime NOT NULL,
			current_period_start datetime NULL,
			current_period_end datetime NULL,
			cancel_at_period_end tinyint(1) NOT NULL DEFAULT 0,
			canceled_at datetime NULL,
			paused_at datetime NULL,
			paused_until datetime NULL,
			pending_plan_id bigint(20) unsigned NULL,
			change_effective_at datetime NULL,
			last_payment_status varchar(20) NOT NULL DEFAULT 'manual',
			payment_issue_at datetime NULL,
			failure_count int(11) unsigned NOT NULL DEFAULT 0,
			renewal_claimed_until datetime NULL,
			renewal_reminder_sent_at datetime NULL,
			trial_reminder_sent_at datetime NULL,
			intro_reminder_sent_at datetime NULL,
			last_invoice_id varchar(190) NULL,
			renewal_order_id bigint(20) unsigned NULL,
			provider_customer_id varchar(190) NOT NULL DEFAULT '',
			provider_subscription_id varchar(190) NOT NULL DEFAULT '',
			checkout_session_id varchar(190) NULL,
			idempotency_key varchar(100) NOT NULL,
			quantity int(10) unsigned NOT NULL DEFAULT 1,
			commitment_ends_at datetime NULL,
			price_snapshot decimal(10,2) NULL,
			currency_snapshot varchar(3) NULL,
			interval_snapshot varchar(20) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY user_status (user_id, status),
			KEY plan_id (plan_id),
			KEY provider_subscription_id (provider_subscription_id),
			KEY renewal_due (status, current_period_end, renewal_claimed_until)
		) ENGINE=InnoDB $collate;
		CREATE TABLE {$tables['events']} (
			event_id varchar(191) NOT NULL,
			event_type varchar(100) NOT NULL,
			payload longtext NULL,
			received_at datetime NOT NULL,
			verified_at datetime NULL,
			status varchar(20) NOT NULL DEFAULT 'processing',
			processed_at datetime NULL,
			PRIMARY KEY  (event_id),
			KEY event_type (event_type)
		) ENGINE=InnoDB $collate;
		CREATE TABLE {$tables['rate_limits']} (
			rate_key varchar(190) NOT NULL,
			window_started_at datetime NOT NULL,
			request_count int(11) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			PRIMARY KEY (rate_key),
			KEY expires_at (expires_at)
		) ENGINE=InnoDB $collate;";
		$sql .= " CREATE TABLE {$tables['audit_logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(80) NOT NULL,
			object_type varchar(40) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY action_created (action, created_at),
			KEY object_lookup (object_type, object_id)
		) ENGINE=InnoDB $collate;";
		$sql .= " CREATE TABLE {$tables['operations']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation_key varchar(190) NOT NULL,
			action varchar(80) NOT NULL,
			subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider varchar(30) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'pending',
			request_payload longtext NULL,
			provider_response longtext NULL,
			local_response longtext NULL,
			attempts int(11) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NULL,
			last_error text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY operation_key (operation_key),
			KEY status_retry (status, next_attempt_at),
			KEY subscription_action (subscription_id, action)
		) ENGINE=InnoDB $collate;";
		$sql .= " CREATE TABLE {$tables['job_runs']} (
			job_key varchar(80) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'idle',
			attempts int(11) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			started_at datetime NULL,
			finished_at datetime NULL,
			next_retry_at datetime NULL,
			acknowledged_at datetime NULL,
			acknowledged_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY (job_key),
			KEY status_retry (status, next_retry_at)
		) ENGINE=InnoDB $collate;";
		$sql .= " CREATE TABLE {$tables['checkouts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NULL,
			plan_id bigint(20) unsigned NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'pending_checkout',
			customer_name varchar(190) NOT NULL DEFAULT '',
			customer_email varchar(190) NOT NULL,
			customer_phone varchar(40) NOT NULL DEFAULT '',
			quantity int(10) unsigned NOT NULL DEFAULT 1,
			provider varchar(30) NOT NULL DEFAULT '',
			provider_session_id varchar(190) NOT NULL DEFAULT '',
			checkout_url varchar(500) NOT NULL DEFAULT '',
			idempotency_key varchar(100) NOT NULL,
			expires_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY user_status (user_id, status),
			KEY email_status (customer_email, status),
			KEY expires_at (expires_at)
		) ENGINE=InnoDB $collate;";
		$sql .= " CREATE TABLE {$tables['email_logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event varchar(80) NOT NULL DEFAULT '',
			subject varchar(190) NOT NULL DEFAULT '',
			recipient_hash char(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'failed',
			error_message text NULL,
			created_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY (id),
			KEY status_created (status, created_at),
			KEY user_event (user_id, event)
		) ENGINE=InnoDB $collate;";
		dbDelta( $sql );
		$columns_ready = self::ensure_column( $tables['subscriptions'], 'commitment_ends_at', 'datetime NULL AFTER quantity' );
		$columns_ready = self::ensure_column( $tables['plans'], 'pause_enabled', 'tinyint(1) NOT NULL DEFAULT 1 AFTER approval_mode' ) && $columns_ready;
		$columns_ready = self::ensure_column( $tables['plans'], 'cancellation_mode', "varchar(20) NOT NULL DEFAULT 'inherit' AFTER pause_enabled" ) && $columns_ready;
		$ready = true;
		foreach ( $tables as $table ) $ready = self::ensure_engine( $table ) && $ready;
		$ready = $columns_ready && $ready;
		$backfilled = false !== $wpdb->query( "UPDATE {$tables['subscriptions']} SET current_period_start = started_at WHERE current_period_start IS NULL" );
		$backfilled = false !== $wpdb->query( "UPDATE {$tables['subscriptions']} SET status = 'payment_pending' WHERE status = 'pending_payment'" ) && $backfilled;
		$backfilled = false !== $wpdb->query( "UPDATE {$tables['subscriptions']} s INNER JOIN {$tables['plans']} p ON p.id = s.plan_id SET s.price_snapshot = p.price, s.currency_snapshot = p.currency, s.interval_snapshot = p.interval_unit WHERE s.price_snapshot IS NULL" ) && $backfilled;
		$backfilled = false !== $wpdb->query( "UPDATE {$tables['subscriptions']} s INNER JOIN {$tables['plans']} p ON p.id = s.plan_id SET s.commitment_ends_at = DATE_ADD(s.started_at, INTERVAL p.minimum_commitment_cycles MONTH) WHERE s.commitment_ends_at IS NULL AND p.minimum_commitment_cycles > 0 AND p.interval_unit = 'month'" ) && $backfilled;
		$backfilled = false !== $wpdb->query( "UPDATE {$tables['subscriptions']} s INNER JOIN {$tables['plans']} p ON p.id = s.plan_id SET s.commitment_ends_at = DATE_ADD(s.started_at, INTERVAL p.minimum_commitment_cycles YEAR) WHERE s.commitment_ends_at IS NULL AND p.minimum_commitment_cycles > 0 AND p.interval_unit = 'year'" ) && $backfilled;
		$backfilled = false !== $wpdb->query( "UPDATE {$tables['subscriptions']} s INNER JOIN {$tables['plans']} p ON p.id = s.plan_id SET s.commitment_ends_at = DATE_ADD(s.started_at, INTERVAL p.minimum_commitment_cycles DAY) WHERE s.commitment_ends_at IS NULL AND p.minimum_commitment_cycles > 0 AND p.interval_unit = 'day'" ) && $backfilled;
		$scheduled = wp_next_scheduled( 'daymark_subscriptions_check_renewals' ) || false !== wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'daymark_subscriptions_check_renewals' );
		$reconciliation_scheduled = wp_next_scheduled( 'daymark_subscriptions_reconcile' ) || false !== wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'daymark_subscriptions_reconcile' );
		if ( $ready && $backfilled && $scheduled && $reconciliation_scheduled ) { update_option( 'daymark_subscriptions_db_version', self::DB_VERSION ); delete_option( 'daymark_subscriptions_db_error' ); }
		else update_option( 'daymark_subscriptions_db_error', 'Database setup is incomplete; the migration will retry on the next load.', false );
		self::sync_capabilities();
	}

	public static function deactivate() { wp_clear_scheduled_hook( 'daymark_subscriptions_check_renewals' ); wp_clear_scheduled_hook( 'daymark_subscriptions_reconcile' ); }

	public static function maybe_upgrade() {
		if ( get_option( 'daymark_subscriptions_db_version' ) !== self::DB_VERSION ) {
			self::activate();
		}
		self::sync_capabilities();
	}

	public static function sync_capabilities() {
		$role = function_exists( 'get_role' ) ? get_role( 'administrator' ) : false;
		if ( ! $role ) return;
		foreach ( array( 'manage_daymark_subscriptions', 'manage_daymark_billing', 'review_daymark_subscriptions', 'view_daymark_reports' ) as $capability ) $role->add_cap( $capability );
	}

	public static function get_plans( $active_only = true ) {
		global $wpdb;
		$table = self::tables()['plans'];
		$query = "SELECT id, slug, name, description, refund_policy, price, intro_price, intro_cycles, currency, interval_unit, trial_days, approval_mode, pause_enabled, cancellation_mode, sort_order, featured, benefits, catalog_visibility, cta_label, active, setup_fee, minimum_commitment_cycles, allow_promotion_codes, quantity_enabled, quantity_min, quantity_max, tax_behavior FROM $table";
		if ( $active_only ) {
			$query .= " WHERE active = 1 AND catalog_visibility = 'visible'";
		}
		$query .= ' ORDER BY sort_order ASC, id ASC';
		$rows = $wpdb->get_results( $query, ARRAY_A );
		foreach ( $rows as &$row ) $row['benefits'] = json_decode( (string) ( $row['benefits'] ?? '' ), true ) ?: array();
		return $rows;
	}

	public static function get_plan_subscriber_counts() {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$rows = $wpdb->get_results( "SELECT plan_id, COUNT(*) AS subscriber_count FROM $table WHERE status IN ('active', 'paused', 'past_due', 'pending_activation') GROUP BY plan_id", ARRAY_A );
		$counts = array();
		foreach ( $rows as $row ) $counts[ absint( $row['plan_id'] ) ] = (int) $row['subscriber_count'];
		return $counts;
	}

	public static function get_plan_subscribers( $plan_id ) {
		global $wpdb;
		$tables = self::tables();
		$sql = "SELECT s.id, s.user_id, s.plan_id, s.pending_plan_id, s.status, s.current_period_end, s.last_payment_status, u.display_name, u.user_email, p.name AS plan_name, pp.name AS pending_plan_name
			FROM {$tables['subscriptions']} s
			INNER JOIN {$wpdb->users} u ON u.ID = s.user_id
			INNER JOIN {$tables['plans']} p ON p.id = s.plan_id
			LEFT JOIN {$tables['plans']} pp ON pp.id = s.pending_plan_id
			WHERE s.plan_id = %d AND s.status IN ('active', 'paused', 'past_due', 'pending_activation')
			ORDER BY u.display_name ASC, s.id ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, absint( $plan_id ) ), ARRAY_A );
	}

	public static function get_admin_metrics() {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$plans = self::tables()['plans'];
		return array(
			'active' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'active'" ),
			'past_due' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'past_due'" ),
			'paused' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'paused'" ),
			'plans' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $plans WHERE active = 1" ),
		);
	}

	public static function get_admin_report( $from = '', $to = '' ) {
		global $wpdb;
		$tables = self::tables(); $from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from . ' 00:00:00' : gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) ); $to = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to . ' 23:59:59' : gmdate( 'Y-m-d 23:59:59' );
		$invoice_where = 'issued_at BETWEEN %s AND %s'; $subscription_where = 'created_at BETWEEN %s AND %s'; $currencies = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT currency FROM {$tables['invoices']} WHERE $invoice_where", $from, $to ) ); $revenue_currency = 1 === count( $currencies ) ? $currencies[0] : ''; $revenue = '' !== $revenue_currency ? (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN amount WHEN status IN ('refunded','credit') THEN -amount ELSE 0 END), 0) FROM {$tables['invoices']} WHERE $invoice_where", $from, $to ) ) : null;
		$paid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['invoices']} WHERE status = 'paid' AND $invoice_where", $from, $to ) );
		$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['invoices']} WHERE status IN ('failed','past_due','uncollectible') AND $invoice_where", $from, $to ) );
		$new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['subscriptions']} WHERE $subscription_where", $from, $to ) );
		$canceled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['subscriptions']} WHERE status IN ('canceled','expired') AND canceled_at BETWEEN %s AND %s", $from, $to ) );
		$by_plan = $wpdb->get_results( $wpdb->prepare( "SELECT p.name, i.currency, COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.amount WHEN i.status IN ('refunded','credit') THEN -i.amount ELSE 0 END), 0) AS revenue, COUNT(CASE WHEN i.status = 'paid' THEN 1 END) AS paid_invoices FROM {$tables['invoices']} i LEFT JOIN {$tables['plans']} p ON p.id = i.plan_id WHERE i.$invoice_where GROUP BY i.plan_id, p.name, i.currency ORDER BY revenue DESC", $from, $to ), ARRAY_A );
		$invoice_total = $paid + $failed;
		$webhook_failures = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['events']} WHERE status = 'failed' AND received_at BETWEEN %s AND %s", $from, $to ) );
		$reconciliation_errors = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['audit_logs']} WHERE action IN ('provider_reconcile_failed','provider_sync_recovery_required') AND created_at BETWEEN %s AND %s", $from, $to ) );
		$email_failures = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['audit_logs']} WHERE action = 'email_delivery_failed' AND created_at BETWEEN %s AND %s", $from, $to ) );
		$pending_reviews = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['subscriptions']} WHERE status = 'pending_review'" );
		$provider_health = self::get_provider_health_metrics( $from, $to );
		return array( 'from' => substr( $from, 0, 10 ), 'to' => substr( $to, 0, 10 ), 'revenue' => $revenue, 'revenue_currency' => $revenue_currency, 'paid_invoices' => $paid, 'failed_invoices' => $failed, 'new_subscriptions' => $new, 'cancellations' => $canceled, 'by_plan' => $by_plan, 'payment_success_rate' => $invoice_total ? round( ( $paid / $invoice_total ) * 100, 1 ) : null, 'webhook_failures' => $webhook_failures, 'reconciliation_errors' => $reconciliation_errors, 'email_failures' => $email_failures, 'pending_reviews' => $pending_reviews, 'provider_requests' => $provider_health['requests'], 'provider_errors' => $provider_health['errors'], 'provider_latency_ms' => $provider_health['average_latency_ms'] );
	}

	public static function record_provider_metric( $action, $operation, $status_code, $latency_ms ) {
		return self::record_audit_log( $action, 'provider', 0, array( 'provider' => 'stripe', 'operation' => sanitize_text_field( $operation ), 'status_code' => absint( $status_code ), 'latency_ms' => max( 0, absint( $latency_ms ) ) ) );
	}

	public static function get_provider_health_metrics( $from = '', $to = '' ) {
		global $wpdb;
		$table = self::tables()['audit_logs'];
		$from = preg_match( '/^\d{4}-\d{2}-\d{2}/', $from ) ? $from : gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
		$to = preg_match( '/^\d{4}-\d{2}-\d{2}/', $to ) ? $to : gmdate( 'Y-m-d 23:59:59' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT action, details FROM $table WHERE action IN ('provider_request', 'provider_request_failed') AND created_at BETWEEN %s AND %s ORDER BY id DESC LIMIT 500", $from, $to ), ARRAY_A );
		$latencies = array(); $errors = 0;
		foreach ( $rows as $row ) { $details = json_decode( $row['details'], true ); if ( ! is_array( $details ) ) continue; if ( 'provider_request_failed' === $row['action'] ) $errors++; if ( isset( $details['latency_ms'] ) ) $latencies[] = absint( $details['latency_ms'] ); }
		return array( 'requests' => count( $rows ), 'errors' => $errors, 'average_latency_ms' => $latencies ? (int) round( array_sum( $latencies ) / count( $latencies ) ) : null );
	}

	public static function get_admin_subscriptions( $status = '', $search = '', $limit = 50, $page = 1 ) {
		global $wpdb;
		$tables = self::tables();
		$allowed_statuses = array( 'pending_review', 'payment_pending', 'pending_payment', 'pending_activation', 'active', 'paused', 'past_due', 'recovery_required', 'canceled', 'expired' );
		$where = array( '1=1' ); $args = array();
		if ( in_array( $status, $allowed_statuses, true ) ) { $where[] = 's.status = %s'; $args[] = $status; }
		if ( '' !== trim( $search ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$where[] = '(u.user_email LIKE %s OR u.display_name LIKE %s OR p.name LIKE %s OR s.provider_subscription_id LIKE %s)';
			$args = array_merge( $args, array( $like, $like, $like, $like ) );
		}
		$limit = max( 1, min( 100, absint( $limit ) ) ); $offset = max( 0, ( absint( $page ) - 1 ) * $limit ); $args[] = $limit; $args[] = $offset;
		$sql = "SELECT s.id, s.user_id, s.status, s.current_period_end, s.last_payment_status, s.provider_subscription_id, COALESCE(u.user_email, '') AS user_email, COALESCE(u.display_name, '') AS display_name, COALESCE(p.name, '') AS plan_name, COALESCE(p.currency, 'USD') AS currency, COALESCE(p.price, 0) AS price FROM {$tables['subscriptions']} s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id LEFT JOIN {$tables['plans']} p ON p.id = s.plan_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY s.id DESC LIMIT %d OFFSET %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	public static function get_admin_subscription_count( $status = '', $search = '' ) {
		global $wpdb;
		$tables = self::tables(); $where = array( '1=1' ); $args = array(); $allowed = array( 'pending_review', 'payment_pending', 'pending_payment', 'pending_activation', 'active', 'paused', 'past_due', 'recovery_required', 'canceled', 'expired' );
		if ( in_array( $status, $allowed, true ) ) { $where[] = 's.status = %s'; $args[] = $status; }
		if ( '' !== trim( $search ) ) { $like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%'; $where[] = '(u.user_email LIKE %s OR u.display_name LIKE %s OR p.name LIKE %s OR s.provider_subscription_id LIKE %s)'; $args = array_merge( $args, array( $like, $like, $like, $like ) ); }
		$sql = "SELECT COUNT(*) FROM {$tables['subscriptions']} s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id LEFT JOIN {$tables['plans']} p ON p.id = s.plan_id WHERE " . implode( ' AND ', $where );
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	public static function get_admin_subscription_detail( $id ) {
		global $wpdb;
		$subscription = self::get_subscription_by_id( absint( $id ) );
		if ( ! $subscription ) return false;
		$user = get_userdata( absint( $subscription['user_id'] ) ); $plan = self::get_plan( absint( $subscription['plan_id'] ) ); $pending = ! empty( $subscription['pending_plan_id'] ) ? self::get_plan( absint( $subscription['pending_plan_id'] ) ) : false;
		$logs = $wpdb->get_results( $wpdb->prepare( 'SELECT action, details, created_at FROM ' . self::tables()['audit_logs'] . ' WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT 50', 'subscription', absint( $id ) ), ARRAY_A );
		return array( 'subscription' => $subscription, 'member' => array( 'id' => $user ? $user->ID : 0, 'name' => $user ? $user->display_name : '', 'email' => $user ? $user->user_email : '' ), 'plan' => $plan ? array( 'id' => $plan['id'], 'name' => $plan['name'], 'price' => $plan['price'], 'currency' => $plan['currency'] ) : array(), 'pending_plan' => $pending ? array( 'id' => $pending['id'], 'name' => $pending['name'] ) : null, 'invoices' => self::get_user_invoices( absint( $subscription['user_id'] ), 50 ), 'audit' => $logs );
	}

	public static function get_duplicate_subscription_users( $limit = 20 ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$limit = max( 1, min( 50, absint( $limit ) ) );
		$sql = "SELECT s.user_id, u.user_email, u.display_name, COUNT(*) AS subscription_count FROM $table s INNER JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE s.status IN ('pending_review', 'payment_pending', 'pending_payment', 'pending_activation', 'active', 'paused', 'past_due') GROUP BY s.user_id, u.user_email, u.display_name HAVING COUNT(*) > 1 ORDER BY subscription_count DESC, s.user_id ASC LIMIT %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $limit ), ARRAY_A ) ?: array();
	}

	public static function get_plan( $id, $active_only = false ) {
		global $wpdb;
		$table = self::tables()['plans'];
		$query = "SELECT id, slug, name, description, refund_policy, price, intro_price, intro_cycles, currency, interval_unit, trial_days, approval_mode, pause_enabled, cancellation_mode, sort_order, featured, benefits, catalog_visibility, cta_label, stripe_price_id, woocommerce_product_id, active, setup_fee, minimum_commitment_cycles, allow_promotion_codes, quantity_enabled, quantity_min, quantity_max, tax_behavior FROM $table WHERE id = %d";
		if ( $active_only ) {
			$query .= ' AND active = 1';
		}
		$row = $wpdb->get_row( $wpdb->prepare( $query, absint( $id ) ), ARRAY_A );
		if ( $row ) $row['benefits'] = json_decode( (string) ( $row['benefits'] ?? '' ), true ) ?: array();
		return $row;
	}

	public static function subscription_status_for_plan( array $plan ) { return 'review' === ( $plan['approval_mode'] ?? 'direct' ) ? 'pending_review' : 'active'; }

	public static function create_plan( $data ) {
		global $wpdb;
		$table = self::tables()['plans'];
		if ( isset( $data['benefits'] ) && is_array( $data['benefits'] ) ) $data['benefits'] = wp_json_encode( $data['benefits'] );
		$formats = array(); foreach ( array_keys( $data ) as $field ) $formats[] = in_array( $field, array( 'price', 'intro_price', 'setup_fee' ), true ) ? '%f' : ( in_array( $field, array( 'active', 'featured', 'sort_order', 'trial_days', 'intro_cycles', 'woocommerce_product_id', 'minimum_commitment_cycles', 'allow_promotion_codes', 'quantity_enabled', 'quantity_min', 'quantity_max', 'pause_enabled' ), true ) ? '%d' : '%s' );
		if ( false === $wpdb->insert( $table, $data, $formats ) ) return false;
		return self::get_plan( $wpdb->insert_id );
	}

	public static function update_plan( $id, $data ) {
		global $wpdb;
		$table = self::tables()['plans'];
		if ( isset( $data['benefits'] ) && is_array( $data['benefits'] ) ) $data['benefits'] = wp_json_encode( $data['benefits'] );
		$formats = array();
		foreach ( array_keys( $data ) as $field ) $formats[] = in_array( $field, array( 'price', 'intro_price', 'setup_fee' ), true ) ? '%f' : ( in_array( $field, array( 'active', 'featured', 'sort_order', 'trial_days', 'intro_cycles', 'woocommerce_product_id', 'minimum_commitment_cycles', 'allow_promotion_codes', 'quantity_enabled', 'quantity_min', 'quantity_max', 'pause_enabled' ), true ) ? '%d' : '%s' );
		return false !== $wpdb->update( $table, $data, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
	}

	public static function plan_usage( $id ) {
		global $wpdb;
		$tables = self::tables();
		return array( 'subscriptions' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['subscriptions']} WHERE plan_id = %d OR pending_plan_id = %d", absint( $id ), absint( $id ) ) ), 'invoices' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['invoices']} WHERE plan_id = %d", absint( $id ) ) ) );
	}

	public static function delete_plan( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::tables()['plans'], array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function get_user_subscription( $user_id ) {
		global $wpdb;
		$tables = self::tables();
		$sql = "SELECT s.id, s.user_id, s.plan_id, s.status, s.started_at, s.current_period_start, s.current_period_end, s.cancel_at_period_end, s.canceled_at, s.paused_at, s.paused_until, s.pending_plan_id, s.change_effective_at, s.last_payment_status, s.quantity, s.commitment_ends_at, s.price_snapshot, s.currency_snapshot, s.interval_snapshot, p.slug, p.name, p.price, p.currency, p.interval_unit, p.pause_enabled, p.cancellation_mode
			FROM {$tables['subscriptions']} s INNER JOIN {$tables['plans']} p ON p.id = s.plan_id
			WHERE s.user_id = %d AND s.status IN ('pending_review', 'payment_pending', 'pending_payment', 'pending_activation', 'active', 'paused', 'past_due', 'recovery_required', 'canceled', 'expired') ORDER BY s.id DESC LIMIT 1";
		return $wpdb->get_row( $wpdb->prepare( $sql, absint( $user_id ) ), ARRAY_A );
	}

	public static function get_active_subscription( $user_id, $for_update = false ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$sql = "SELECT * FROM $table WHERE user_id = %d AND status IN ('active', 'paused', 'past_due', 'pending_activation') ORDER BY id DESC LIMIT 1";
		if ( $for_update ) {
			$sql .= ' FOR UPDATE';
		}
		return $wpdb->get_row( $wpdb->prepare( $sql, absint( $user_id ) ), ARRAY_A );
	}

	public static function get_pending_review_subscription( $user_id ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d AND status = 'pending_review' ORDER BY id DESC LIMIT 1", absint( $user_id ) ), ARRAY_A );
	}

	public static function get_pending_payment_subscription( $user_id ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d AND status IN ('payment_pending', 'pending_payment') ORDER BY id DESC LIMIT 1", absint( $user_id ) ), ARRAY_A );
	}

	public static function create_checkout( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$values = array(
			'user_id'             => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
			'plan_id'             => absint( $data['plan_id'] ?? 0 ),
			'status'              => in_array( $data['status'] ?? 'pending_checkout', array( 'pending_checkout', 'pending_review', 'payment_pending', 'failed', 'verified', 'expired' ), true ) ? sanitize_key( $data['status'] ) : 'pending_checkout',
			'customer_name'       => sanitize_text_field( $data['customer_name'] ?? '' ),
			'customer_email'      => sanitize_email( $data['customer_email'] ?? '' ),
			'customer_phone'      => sanitize_text_field( $data['customer_phone'] ?? '' ),
			'quantity'            => max( 1, absint( $data['quantity'] ?? 1 ) ),
			'provider'            => sanitize_key( $data['provider'] ?? '' ),
			'provider_session_id' => sanitize_text_field( $data['provider_session_id'] ?? '' ),
			'checkout_url'        => esc_url_raw( $data['checkout_url'] ?? '' ),
			'idempotency_key'     => sanitize_text_field( $data['idempotency_key'] ?? '' ),
			'expires_at'          => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'created_at'          => $now,
			'updated_at'          => $now,
		);
		if ( ! $values['plan_id'] || ! is_email( $values['customer_email'] ) || ! $values['idempotency_key'] ) return false;
		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		$inserted = $wpdb->insert( self::tables()['checkouts'], $values, $formats );
		return $inserted ? self::get_checkout_by_idempotency( $values['idempotency_key'] ) : self::get_checkout_by_idempotency( $values['idempotency_key'] );
	}

	public static function get_checkout_by_idempotency( $key ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['checkouts'] . ' WHERE idempotency_key = %s', sanitize_text_field( $key ) ), ARRAY_A );
	}

	public static function get_checkout_by_provider_session( $session_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['checkouts'] . ' WHERE provider_session_id = %s', sanitize_text_field( $session_id ) ), ARRAY_A );
	}

	public static function get_latest_checkout( $user_id, $status = '' ) {
		global $wpdb;
		$where = 'user_id = %d'; $args = array( absint( $user_id ) );
		if ( in_array( $status, array( 'pending_checkout', 'pending_review', 'payment_pending', 'failed', 'verified', 'expired' ), true ) ) { $where .= ' AND status = %s'; $args[] = $status; }
		$args[] = 1;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['checkouts'] . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT %d', $args ), ARRAY_A );
	}

	public static function update_checkout( $id, array $data ) {
		global $wpdb;
		$allowed = array( 'pending_checkout', 'pending_review', 'payment_pending', 'failed', 'verified', 'expired' );
		if ( isset( $data['status'] ) ) $data['status'] = in_array( $data['status'], $allowed, true ) ? sanitize_key( $data['status'] ) : 'failed';
		if ( isset( $data['provider_session_id'] ) ) $data['provider_session_id'] = sanitize_text_field( $data['provider_session_id'] );
		if ( isset( $data['checkout_url'] ) ) $data['checkout_url'] = esc_url_raw( $data['checkout_url'] );
		if ( isset( $data['user_id'] ) ) $data['user_id'] = absint( $data['user_id'] );
		$data['updated_at'] = current_time( 'mysql', true );
		$formats = array();
		foreach ( $data as $field => $value ) $formats[] = in_array( $field, array( 'user_id' ), true ) ? '%d' : '%s';
		return false !== $wpdb->update( self::tables()['checkouts'], $data, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
	}

	public static function update_checkout_by_provider_session( $session_id, array $data ) {
		$checkout = self::get_checkout_by_provider_session( $session_id );
		return $checkout ? self::update_checkout( $checkout['id'], $data ) : false;
	}

	public static function begin_transaction() { global $wpdb; return false !== $wpdb->query( 'START TRANSACTION' ); }
	public static function commit() { global $wpdb; return false !== $wpdb->query( 'COMMIT' ); }
	public static function rollback() { global $wpdb; $wpdb->query( 'ROLLBACK' ); }

	private static function ensure_engine( $table ) {
		global $wpdb;
		$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
		if ( ! $engine ) return false;
		if ( 0 !== strcasecmp( self::DB_ENGINE, $engine ) && false === $wpdb->query( 'ALTER TABLE ' . $table . ' ENGINE=' . self::DB_ENGINE ) ) return false;
		return true;
	}

	private static function ensure_column( $table, $column, $definition ) {
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', $table, $column ) );
		return $exists || false !== $wpdb->query( 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition );
	}

	public static function consume_rate_limit( $key, $limit, $window_seconds ) {
		global $wpdb;
		$table = self::tables()['rate_limits'];
		$now = current_time( 'mysql', true );
		$expires = gmdate( 'Y-m-d H:i:s', time() + absint( $window_seconds ) );
		if ( ! self::begin_transaction() ) return null;
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE expires_at <= %s LIMIT 100", $now ) );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (rate_key, window_started_at, request_count, expires_at) VALUES (%s, %s, 1, %s)", $key, $now, $expires ) );
		if ( false === $inserted ) { self::rollback(); return null; }
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT request_count, expires_at FROM $table WHERE rate_key = %s FOR UPDATE", $key ), ARRAY_A );
		if ( ! $row ) { self::rollback(); return null; }
		if ( strtotime( $row['expires_at'] . ' UTC' ) <= time() ) {
			$updated = $wpdb->update( $table, array( 'window_started_at' => $now, 'request_count' => 1, 'expires_at' => $expires ), array( 'rate_key' => $key ), array( '%s', '%d', '%s' ), array( '%s' ) );
		} elseif ( (int) $row['request_count'] >= absint( $limit ) ) {
			return self::commit() ? false : null;
		} else {
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE $table SET request_count = request_count + 1 WHERE rate_key = %s", $key ) );
		}
		if ( false === $updated || ! self::commit() ) { self::rollback(); return null; }
		return true;
	}

	public static function lock_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID = %d FOR UPDATE", absint( $user_id ) ) );
	}

	public static function get_by_idempotency_key( $key ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE idempotency_key = %s", $key ), ARRAY_A );
	}

	public static function get_plan_by_slug( $slug ) {
		global $wpdb;
		$table = self::tables()['plans'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ), ARRAY_A );
	}

	public static function record_audit_log( $action, $object_type, $object_id, array $details = array() ) {
		global $wpdb;
		$table = self::tables()['audit_logs'];
		return false !== $wpdb->insert( $table, array( 'actor_id' => get_current_user_id(), 'action' => sanitize_key( $action ), 'object_type' => sanitize_key( $object_type ), 'object_id' => absint( $object_id ), 'details' => wp_json_encode( $details ), 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%s', '%s', '%d', '%s', '%s' ) );
	}

	public static function record_email_log( array $data ) {
		global $wpdb;
		$status = in_array( $data['status'] ?? '', array( 'sent', 'failed' ), true ) ? $data['status'] : 'failed';
		$sent_at = 'sent' === $status ? current_time( 'mysql', true ) : null;
		return false !== $wpdb->insert( self::tables()['email_logs'], array( 'user_id' => absint( $data['user_id'] ?? 0 ), 'event' => sanitize_key( $data['event'] ?? '' ), 'subject' => sanitize_text_field( $data['subject'] ?? '' ), 'recipient_hash' => sanitize_text_field( $data['recipient_hash'] ?? '' ), 'status' => $status, 'error_message' => sanitize_textarea_field( $data['error_message'] ?? '' ), 'created_at' => current_time( 'mysql', true ), 'sent_at' => $sent_at ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	public static function get_email_logs( $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, min( 100, absint( $limit ) ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT id, user_id, event, subject, recipient_hash, status, error_message, created_at, sent_at FROM ' . self::tables()['email_logs'] . ' ORDER BY created_at DESC, id DESC LIMIT %d', $limit ), ARRAY_A ) ?: array();
	}

	public static function get_email_log( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, user_id, event, subject, recipient_hash, status, error_message, created_at, sent_at FROM ' . self::tables()['email_logs'] . ' WHERE id = %d', absint( $id ) ), ARRAY_A );
	}

	public static function get_audit_logs( $limit = 100, $page = 1 ) {
		global $wpdb;
		$table = self::tables()['audit_logs']; $limit = max( 1, min( 500, absint( $limit ) ) ); $offset = max( 0, ( absint( $page ) - 1 ) * $limit );
		return $wpdb->get_results( $wpdb->prepare( "SELECT l.*, u.display_name AS actor_name FROM $table l LEFT JOIN {$wpdb->users} u ON u.ID = l.actor_id ORDER BY l.id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	public static function get_audit_log_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::tables()['audit_logs'] );
	}

	public static function create_operation( $key, $action, $subscription_id = 0, array $payload = array() ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$data = array(
			'operation_key' => sanitize_text_field( $key ),
			'action' => sanitize_key( $action ),
			'subscription_id' => absint( $subscription_id ),
			'provider' => sanitize_key( Daymark_Payment_Provider::provider() ),
			'status' => 'pending',
			'request_payload' => wp_json_encode( $payload ),
			'attempts' => 0,
			'created_at' => $now,
			'updated_at' => $now,
		);
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::tables()['operations'] . ' (operation_key, action, subscription_id, provider, status, request_payload, attempts, created_at, updated_at) VALUES (%s, %s, %d, %s, %s, %s, %d, %s, %s)', $data['operation_key'], $data['action'], $data['subscription_id'], $data['provider'], $data['status'], $data['request_payload'], 0, $now, $now ) );
		return $inserted ? self::get_operation_by_key( $key ) : false;
	}

	public static function get_operation_by_key( $key ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['operations'] . ' WHERE operation_key = %s', sanitize_text_field( $key ) ), ARRAY_A );
	}

	public static function get_operation_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['operations'] . ' WHERE id = %d', absint( $id ) ), ARRAY_A );
	}

	public static function update_operation( $id, $status, array $data = array() ) {
		global $wpdb;
		$allowed = array( 'pending', 'awaiting_second_approval', 'succeeded', 'failed', 'recovery_required', 'dead_letter' );
		$status = in_array( $status, $allowed, true ) ? $status : 'failed';
		$update = array_merge( array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ), $data );
		$formats = array();
		foreach ( $update as $field => $value ) $formats[] = in_array( $field, array( 'attempts' ), true ) ? '%d' : '%s';
		return false !== $wpdb->update( self::tables()['operations'], $update, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
	}

	public static function get_operations( $status = '', $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, min( 200, absint( $limit ) ) );
		$where = '';
		$args = array();
		if ( in_array( $status, array( 'pending', 'awaiting_second_approval', 'succeeded', 'failed', 'recovery_required', 'dead_letter' ), true ) ) { $where = ' WHERE status = %s'; $args[] = $status; }
		$args[] = $limit;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['operations'] . $where . ' ORDER BY updated_at DESC, id DESC LIMIT %d', $args ), ARRAY_A ) ?: array();
	}

	public static function start_job( $job_key ) {
		global $wpdb;
		$job_key = sanitize_key( $job_key ); $existing = self::get_job( $job_key );
		if ( $existing && 'dead_letter' === $existing['status'] ) return $existing;
		$now = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::tables()['job_runs'] . ' (job_key, status, attempts, started_at, updated_at) VALUES (%s, %s, 1, %s, %s) ON DUPLICATE KEY UPDATE status = %s, attempts = attempts + 1, started_at = %s, last_error = NULL, updated_at = %s', $job_key, 'running', $now, $now, 'running', $now, $now ) );
		return self::get_job( $job_key );
	}

	public static function finish_job( $job_key ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		return false !== $wpdb->update( self::tables()['job_runs'], array( 'status' => 'succeeded', 'finished_at' => $now, 'next_retry_at' => null, 'acknowledged_at' => null, 'acknowledged_by' => 0, 'updated_at' => $now ), array( 'job_key' => sanitize_key( $job_key ) ), array( '%s', '%s', '%s', '%s', '%d', '%s' ), array( '%s' ) );
	}

	public static function fail_job( $job_key, $error ) {
		global $wpdb;
		$job = self::get_job( $job_key ); $attempts = absint( $job['attempts'] ?? 0 ); $max_attempts = max( 1, min( 10, absint( get_option( 'daymark_subscriptions_retry_max_attempts', 3 ) ?: 3 ) ) ); $backoff_minutes = max( 5, min( 1440, absint( get_option( 'daymark_subscriptions_retry_backoff_minutes', 15 ) ?: 15 ) ) ); $status = $attempts >= $max_attempts ? 'dead_letter' : 'failed';
		$now = current_time( 'mysql', true ); $backoff = min( DAY_IN_SECONDS, $backoff_minutes * MINUTE_IN_SECONDS * ( 2 ** max( 0, $attempts - 1 ) ) ); $retry_at = 'failed' === $status ? gmdate( 'Y-m-d H:i:s', time() + $backoff ) : null;
		return false !== $wpdb->update( self::tables()['job_runs'], array( 'status' => $status, 'last_error' => sanitize_textarea_field( $error ), 'finished_at' => $now, 'next_retry_at' => $retry_at, 'acknowledged_at' => null, 'acknowledged_by' => 0, 'updated_at' => $now ), array( 'job_key' => sanitize_key( $job_key ) ), array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' ), array( '%s' ) );
	}

	public static function get_job( $job_key ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tables()['job_runs'] . ' WHERE job_key = %s', sanitize_key( $job_key ) ), ARRAY_A ); }
	public static function get_jobs() { global $wpdb; return $wpdb->get_results( 'SELECT * FROM ' . self::tables()['job_runs'] . ' ORDER BY updated_at DESC', ARRAY_A ) ?: array(); }
	public static function acknowledge_job( $job_key ) {
		global $wpdb;
		$job_key = sanitize_key( $job_key ); $job = self::get_job( $job_key );
		if ( ! $job || 'succeeded' !== $job['status'] ) return false;
		$now = current_time( 'mysql', true );
		return false !== $wpdb->update( self::tables()['job_runs'], array( 'acknowledged_at' => $now, 'acknowledged_by' => get_current_user_id(), 'updated_at' => $now ), array( 'job_key' => $job_key ), array( '%s', '%d', '%s' ), array( '%s' ) ) ? self::get_job( $job_key ) : false;
	}
	public static function retry_job( $job_key ) {
		global $wpdb;
		$job_key = sanitize_key( $job_key );
		if ( ! in_array( $job_key, array( 'renewals', 'reconciliation' ), true ) ) return false;
		$updated = $wpdb->update( self::tables()['job_runs'], array( 'status' => 'pending', 'attempts' => 0, 'last_error' => null, 'next_retry_at' => null, 'acknowledged_at' => null, 'acknowledged_by' => 0, 'updated_at' => current_time( 'mysql', true ) ), array( 'job_key' => $job_key ), array( '%s', '%d', '%s', '%s', '%s', '%d' ), array( '%s' ) );
		if ( false === $updated ) return false;
		if ( 'renewals' === $job_key ) self::run_renewal_job(); else self::run_reconciliation_job();
		return self::get_job( $job_key );
	}

	public static function run_renewal_job() {
		$job = self::start_job( 'renewals' ); if ( $job && 'dead_letter' === $job['status'] ) return;
		try { self::check_due_renewals(); self::finish_job( 'renewals' ); } catch ( Throwable $error ) { self::fail_job( 'renewals', $error->getMessage() ); self::record_audit_log( 'scheduled_job_failed', 'job', 0, array( 'job' => 'renewals', 'error' => $error->getMessage() ) ); }
	}

	public static function run_reconciliation_job() {
		$job = self::start_job( 'reconciliation' ); if ( $job && 'dead_letter' === $job['status'] ) return;
		try { Daymark_Payment_Provider::reconcile(); self::finish_job( 'reconciliation' ); } catch ( Throwable $error ) { self::fail_job( 'reconciliation', $error->getMessage() ); self::record_audit_log( 'scheduled_job_failed', 'job', 0, array( 'job' => 'reconciliation', 'error' => $error->getMessage() ) ); }
	}

	public static function get_plan_by_stripe_price( $price_id ) {
		global $wpdb;
		$table = self::tables()['plans'];
		$plan = $wpdb->get_row( $wpdb->prepare( "SELECT id, slug, name, description, price, currency, interval_unit, stripe_price_id, woocommerce_product_id, active FROM $table WHERE stripe_price_id = %s", $price_id ), ARRAY_A );
		if ( $plan ) return $plan;
		foreach ( (array) get_option( Daymark_Stripe::PRICE_MAP_OPTION, array() ) as $mode_map ) foreach ( (array) $mode_map as $plan_id => $mapped_price_id ) if ( hash_equals( (string) $mapped_price_id, (string) $price_id ) ) return self::get_plan( absint( $plan_id ) );
		foreach ( (array) get_option( Daymark_Stripe::PRICE_HISTORY_OPTION, array() ) as $mode_map ) foreach ( (array) $mode_map as $mapped_price_id => $plan_id ) if ( hash_equals( (string) $mapped_price_id, (string) $price_id ) ) return self::get_plan( absint( $plan_id ) );
		return null;
	}

	public static function update_subscription( $id, $user_id, $data, $formats ) {
		global $wpdb;
		return false !== $wpdb->update( self::tables()['subscriptions'], $data, array( 'id' => absint( $id ), 'user_id' => absint( $user_id ) ), $formats, array( '%d', '%d' ) );
	}

	public static function get_due_subscriptions() {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$now = current_time( 'mysql', true );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'active' AND current_period_end IS NOT NULL AND current_period_end <= %s AND (renewal_claimed_until IS NULL OR renewal_claimed_until <= %s) ORDER BY id ASC LIMIT 100", $now, $now ), ARRAY_A );
	}

	public static function get_reconcilable_subscriptions( $limit = 50 ) {
		global $wpdb;
		$table = self::tables()['subscriptions']; $limit = max( 1, min( 100, absint( $limit ) ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status IN ('active', 'paused', 'past_due', 'pending_activation', 'recovery_required') AND provider_subscription_id <> '' ORDER BY id ASC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function check_due_renewals() {
		self::cleanup_abandoned_checkout_accounts();
		$reminder_days = Daymark_Subscriptions_Admin::renewal_reminder_days();
		if ( $reminder_days > 0 ) foreach ( self::get_due_renewal_reminders( $reminder_days ) as $reminder ) if ( self::claim_renewal_reminder( $reminder['id'] ) ) do_action( 'daymark_subscription_renewal_reminder', $reminder );
		$offer_reminder_days = Daymark_Subscriptions_Admin::offer_reminder_days();
		if ( $offer_reminder_days > 0 ) {
			foreach ( self::get_due_offer_reminders( 'trial', $offer_reminder_days ) as $reminder ) if ( self::claim_offer_reminder( $reminder['id'], 'trial' ) ) do_action( 'daymark_subscription_offer_reminder', $reminder, 'trial' );
			foreach ( self::get_due_offer_reminders( 'intro', $offer_reminder_days ) as $reminder ) if ( self::claim_offer_reminder( $reminder['id'], 'intro' ) ) do_action( 'daymark_subscription_offer_reminder', $reminder, 'intro' );
		}
		foreach ( self::get_due_pauses() as $paused ) do_action( 'daymark_subscription_pause_expired', $paused );
		$passes = 0;
		do {
			$due = self::get_due_subscriptions();
			$claimed = 0;
		foreach ( $due as $subscription ) {
			if ( 'woocommerce' !== Daymark_Payment_Provider::provider() ) break;
				$claimed_until = self::claim_due_renewal( $subscription['id'] );
				if ( ! $claimed_until ) continue;
				$claimed++;
				$subscription['renewal_claimed_until'] = $claimed_until;
				$operation_key = 'renewal:' . absint( $subscription['id'] ) . ':' . md5( (string) $subscription['current_period_end'] );
				$operation = self::create_operation( $operation_key, 'renewal', $subscription['id'], array( 'period_end' => $subscription['current_period_end'], 'claim_token' => $claimed_until ) );
				$subscription['renewal_operation_key'] = $operation_key;
				do_action( 'daymark_subscription_renewal_due', $subscription );
				if ( 0 === strpos( (string) $subscription['provider_subscription_id'], 'wc_order:' ) && ! self::has_pending_woocommerce_renewal( $subscription['id'] ) ) self::settle_unhandled_woocommerce_renewal( $subscription['id'], $claimed_until );
			}
			$passes++;
		} while ( $claimed > 0 && count( $due ) === 100 && $passes < 100 );
		self::expire_past_due_subscriptions();
	}

	public static function cleanup_abandoned_checkout_accounts() {
		global $wpdb;
		$deletion_retention_days = max( 1, min( 365, absint( get_option( 'daymark_subscriptions_account_deletion_retention_days', 30 ) ?: 30 ) ) );
		$retention_days = max( 1, min( 90, absint( get_option( 'daymark_subscriptions_abandoned_retention_days', 7 ) ?: 7 ) ) );
		$activation_retention_days = max( 1, min( 90, absint( get_option( 'daymark_subscriptions_pending_activation_retention_days', 7 ) ?: 7 ) ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS );
		$activation_cutoff = gmdate( 'Y-m-d H:i:s', time() - $activation_retention_days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . self::tables()['checkouts'] . " WHERE expires_at IS NOT NULL AND expires_at <= %s AND status IN ('pending_checkout', 'payment_pending', 'failed', 'expired') LIMIT 100", $cutoff ) );
		$expired_activation_ids = $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id FROM " . self::tables()['subscriptions'] . " WHERE status = 'pending_activation' AND started_at IS NOT NULL AND started_at <= %s ORDER BY id ASC LIMIT 100", $activation_cutoff ), ARRAY_A );
		foreach ( $expired_activation_ids as $subscription ) {
			if ( 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE " . self::tables()['subscriptions'] . " SET status = 'expired' WHERE id = %d AND status = 'pending_activation'", absint( $subscription['id'] ) ) ) ) {
				update_user_meta( absint( $subscription['user_id'] ), 'daymark_account_state', 'expired' );
				self::record_audit_log( 'subscription_activation_expired', 'subscription', absint( $subscription['id'] ), array( 'retention_days' => $activation_retention_days ) );
			}
		}
		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'daymark_checkout_created_at' AND meta_value <= %s LIMIT 100", $cutoff ) );
		foreach ( $user_ids as $user_id ) {
			$user_id = absint( $user_id );
			if ( ! $user_id || self::get_user_subscription( $user_id ) || get_posts( array( 'author' => $user_id, 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true ) ) || ! ( $user = get_user_by( 'id', $user_id ) ) || ! in_array( 'subscriber', (array) $user->roles, true ) ) continue;
			if ( wp_delete_user( $user_id ) ) self::record_audit_log( 'abandoned_checkout_account_deleted', 'user', $user_id, array( 'retention_days' => $retention_days ) );
		}
		$deletion_cutoff = gmdate( 'Y-m-d H:i:s', time() - $deletion_retention_days * DAY_IN_SECONDS );
		$approved_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'daymark_privacy_deletion_approved_at' AND meta_value <= %s LIMIT 100", $deletion_cutoff ) );
		foreach ( $approved_ids as $user_id ) {
			$user_id = absint( $user_id );
			if ( ! $user_id || user_can( $user_id, 'manage_options' ) || ! get_userdata( $user_id ) ) continue;
			$updated = wp_update_user( array( 'ID' => $user_id, 'user_email' => 'deleted+' . $user_id . '@invalid.local', 'first_name' => '', 'last_name' => '', 'display_name' => 'Deleted member', 'description' => '' ) );
			if ( is_wp_error( $updated ) ) continue;
			foreach ( array( 'billing_phone', 'daymark_phone', 'daymark_profile_visibility', 'daymark_product_updates', 'daymark_profile_picture_url', 'daymark_email_change_hash', 'daymark_email_change_target', 'daymark_email_change_expires', 'daymark_privacy_deletion_requested_at', 'daymark_privacy_deletion_approved_at' ) as $key ) delete_user_meta( $user_id, $key );
			self::record_audit_log( 'privacy_deletion_completed', 'user', $user_id, array( 'retention_days' => $deletion_retention_days, 'billing_history_preserved' => true ) );
		}
	}

	public static function get_due_renewal_reminders( $days ) {
		global $wpdb; $table = self::tables()['subscriptions']; $now = current_time( 'mysql', true ); $until = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) + ( absint( $days ) * DAY_IN_SECONDS ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'active' AND current_period_end > %s AND current_period_end <= %s AND renewal_reminder_sent_at IS NULL ORDER BY current_period_end ASC LIMIT 50", $now, $until ), ARRAY_A );
	}

	public static function claim_renewal_reminder( $id ) {
		global $wpdb; $table = self::tables()['subscriptions'];
		return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE $table SET renewal_reminder_sent_at = %s WHERE id = %d AND status = 'active' AND renewal_reminder_sent_at IS NULL", current_time( 'mysql', true ), absint( $id ) ) );
	}

	public static function get_due_offer_reminders( $type, $days ) {
		global $wpdb;
		$column = 'trial' === $type ? 'trial_reminder_sent_at' : 'intro_reminder_sent_at';
		$condition = 'trial' === $type ? 'p.trial_days > 0' : 'p.intro_cycles > 0';
		$rows = $wpdb->get_results( "SELECT s.*, p.name AS plan_name, p.trial_days, p.intro_cycles, p.interval_unit FROM {$wpdb->prefix}daymark_subscriptions s INNER JOIN {$wpdb->prefix}daymark_plans p ON p.id = s.plan_id WHERE s.status = 'active' AND $condition AND s.$column IS NULL ORDER BY s.id ASC LIMIT 100", ARRAY_A );
		$now = current_time( 'timestamp', true ); $until = $now + ( absint( $days ) * DAY_IN_SECONDS ); $due = array();
		foreach ( $rows as $row ) {
			$start = strtotime( (string) $row['started_at'] . ' UTC' );
			if ( ! $start ) continue;
			$cycles = 'trial' === $type ? absint( $row['trial_days'] ) : absint( $row['intro_cycles'] );
			if ( 'trial' === $type ) $target = $start + ( $cycles * DAY_IN_SECONDS );
			else { $date = new DateTime( gmdate( 'Y-m-d H:i:s', $start ), new DateTimeZone( 'UTC' ) ); $date->modify( '+' . ( $cycles * ( 'year' === $row['interval_unit'] ? 12 : 1 ) ) . ' month' ); $target = $date->getTimestamp(); }
			if ( $target > $now && $target <= $until ) { $row['offer_reminder_type'] = $type; $row['offer_ends_at'] = gmdate( 'Y-m-d H:i:s', $target ); $due[] = $row; }
		}
		return $due;
	}

	public static function claim_offer_reminder( $id, $type ) {
		global $wpdb; $column = 'trial' === $type ? 'trial_reminder_sent_at' : ( 'intro' === $type ? 'intro_reminder_sent_at' : '' );
		if ( ! $column ) return false;
		return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE " . self::tables()['subscriptions'] . " SET $column = %s WHERE id = %d AND status = 'active' AND $column IS NULL", current_time( 'mysql', true ), absint( $id ) ) );
	}

	public static function get_due_pauses() {
		global $wpdb; $table = self::tables()['subscriptions']; $now = current_time( 'mysql', true );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'paused' AND paused_until IS NOT NULL AND paused_until <= %s ORDER BY id ASC LIMIT 50", $now ), ARRAY_A );
	}

	public static function resume_paused_subscription( $id ) {
		global $wpdb; $table = self::tables()['subscriptions'];
		return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'active', paused_at = NULL, paused_until = NULL WHERE id = %d AND status = 'paused' AND paused_until IS NOT NULL AND paused_until <= %s", absint( $id ), current_time( 'mysql', true ) ) );
	}

	public static function settle_unhandled_woocommerce_renewal( $id, $claimed_until ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		if ( ! self::begin_transaction() ) return false;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, cancel_at_period_end, renewal_claimed_until FROM $table WHERE id = %d FOR UPDATE", absint( $id ) ), ARRAY_A );
		if ( ! $row || 'active' !== $row['status'] || (string) $claimed_until !== (string) $row['renewal_claimed_until'] ) { self::rollback(); return true; }
		if ( (int) $row['cancel_at_period_end'] ) {
			$updated = $wpdb->update( $table, array( 'status' => 'canceled', 'canceled_at' => current_time( 'mysql', true ), 'last_payment_status' => 'manual', 'payment_issue_at' => null, 'renewal_claimed_until' => null ), array( 'id' => absint( $id ) ), array( '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$updated = $wpdb->update( $table, array( 'status' => 'past_due', 'last_payment_status' => 'manual', 'payment_issue_at' => current_time( 'mysql', true ), 'renewal_claimed_until' => null ), array( 'id' => absint( $id ) ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
		}
		if ( false === $updated || ! self::commit() ) { self::rollback(); return false; }
		return true;
	}

	public static function mark_woocommerce_renewal_order( $subscription_id, $order_id ) {
		global $wpdb;
		return false !== $wpdb->update( self::tables()['subscriptions'], array( 'status' => 'past_due', 'last_payment_status' => 'renewal_due', 'payment_issue_at' => current_time( 'mysql', true ), 'renewal_order_id' => absint( $order_id ), 'renewal_claimed_until' => null ), array( 'id' => absint( $subscription_id ) ), array( '%s', '%s', '%s', '%d', '%s' ), array( '%d' ) );
	}

	public static function has_pending_woocommerce_renewal( $subscription_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT renewal_order_id FROM " . self::tables()['subscriptions'] . " WHERE id = %d AND renewal_order_id > 0 AND status = 'past_due'", absint( $subscription_id ) ) );
	}

	public static function claim_due_renewal( $id ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$now = current_time( 'mysql', true );
		if ( ! self::begin_transaction() ) return false;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, current_period_end, renewal_claimed_until FROM $table WHERE id = %d FOR UPDATE", absint( $id ) ), ARRAY_A );
		if ( ! $row || 'active' !== $row['status'] || empty( $row['current_period_end'] ) || strtotime( $row['current_period_end'] . ' UTC' ) > time() || ( ! empty( $row['renewal_claimed_until'] ) && strtotime( $row['renewal_claimed_until'] . ' UTC' ) > time() ) ) { self::rollback(); return false; }
		$claimed_until = gmdate( 'Y-m-d H:i:s', time() + self::RENEWAL_LEASE_SECONDS );
		$updated = $wpdb->update( $table, array( 'renewal_claimed_until' => $claimed_until ), array( 'id' => absint( $id ) ), array( '%s' ), array( '%d' ) );
		if ( false === $updated || ! self::commit() ) { self::rollback(); return false; }
		return $claimed_until;
	}

	public static function record_renewal( $id, $payment_status, $invoice_id = '', $claim_token = '' ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$invoice_id = sanitize_text_field( $invoice_id );
		if ( ! in_array( $payment_status, array( 'paid', 'failed' ), true ) || ! self::begin_transaction() ) return false;
		$subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d FOR UPDATE", absint( $id ) ), ARRAY_A );
		if ( ! $subscription ) { self::rollback(); return false; }
		if ( $claim_token && (string) $claim_token !== (string) $subscription['renewal_claimed_until'] ) { self::rollback(); return false; }
		if ( $claim_token && ( empty( $subscription['renewal_claimed_until'] ) || strtotime( $subscription['renewal_claimed_until'] . ' UTC' ) <= time() ) ) { self::rollback(); return false; }
		if ( in_array( $subscription['status'], array( 'canceled', 'expired' ), true ) ) { $committed = self::commit(); if ( ! $committed ) self::rollback(); return $committed; }
		if ( 'pending_review' === $subscription['status'] ) { $committed = self::commit(); if ( ! $committed ) self::rollback(); return $committed; }
		if ( $invoice_id && $invoice_id === (string) ( $subscription['last_invoice_id'] ?? '' ) ) { $committed = self::commit(); if ( ! $committed ) self::rollback(); return $committed; }
		$last_invoice_id = $invoice_id ?: ( $subscription['last_invoice_id'] ?? null );
		if ( 'failed' === $payment_status ) {
			$updated = $wpdb->update( $table, array( 'status' => 'past_due', 'last_payment_status' => 'failed', 'payment_issue_at' => current_time( 'mysql', true ), 'failure_count' => (int) $subscription['failure_count'] + 1, 'renewal_claimed_until' => null, 'last_invoice_id' => $last_invoice_id ), array( 'id' => absint( $id ) ), array( '%s', '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated || ! self::commit() ) { self::rollback(); return false; }
			self::record_audit_log( 'renewal_payment_failed', 'subscription', $id, array( 'invoice_id' => $invoice_id, 'failure_count' => (int) $subscription['failure_count'] + 1 ) );
			return true;
		}
		if ( (int) $subscription['cancel_at_period_end'] ) {
			$updated = $wpdb->update( $table, array( 'status' => 'canceled', 'canceled_at' => current_time( 'mysql', true ), 'last_payment_status' => 'paid', 'payment_issue_at' => null, 'renewal_order_id' => null, 'renewal_claimed_until' => null, 'last_invoice_id' => $last_invoice_id ), array( 'id' => absint( $id ) ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated || ! self::commit() ) { self::rollback(); return false; }
			self::record_audit_log( 'renewal_cancelled_at_period_end', 'subscription', $id, array( 'invoice_id' => $invoice_id ) );
			return true;
		}
		$plan_id = $subscription['pending_plan_id'] ? $subscription['pending_plan_id'] : $subscription['plan_id'];
		$plan = self::get_plan( $plan_id );
		if ( ! $plan ) { self::rollback(); return false; }
		$period_start = $subscription['current_period_end']; $period_end = gmdate( 'Y-m-d H:i:s', strtotime( '+1 ' . $plan['interval_unit'], strtotime( $period_start . ' UTC' ) ) );
		$updated = $wpdb->update( $table, array( 'status' => 'active', 'plan_id' => $plan_id, 'current_period_start' => $period_start, 'current_period_end' => $period_end, 'pending_plan_id' => null, 'change_effective_at' => null, 'last_payment_status' => 'paid', 'payment_issue_at' => null, 'renewal_order_id' => null, 'failure_count' => 0, 'renewal_claimed_until' => null, 'last_invoice_id' => $last_invoice_id, 'price_snapshot' => (float) $plan['price'], 'currency_snapshot' => strtoupper( $plan['currency'] ), 'interval_snapshot' => $plan['interval_unit'] ), array( 'id' => absint( $id ) ), array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s' ), array( '%d' ) );
		if ( false !== $updated ) $wpdb->query( $wpdb->prepare( "UPDATE $table SET renewal_reminder_sent_at = NULL WHERE id = %d", absint( $id ) ) );
		if ( false === $updated || ! self::commit() ) { self::rollback(); return false; }
		self::record_audit_log( 'renewal_payment_succeeded', 'subscription', $id, array( 'invoice_id' => $invoice_id, 'plan_id' => absint( $plan_id ), 'period_end' => $period_end ) );
		return true;
	}

	public static function create_subscription( $data ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		$plan = self::get_plan( absint( $data['plan_id'] ?? 0 ) );
		if ( $plan ) {
			$data['price_snapshot'] = isset( $data['price_snapshot'] ) ? (float) $data['price_snapshot'] : (float) $plan['price'];
			$data['currency_snapshot'] = $data['currency_snapshot'] ?? strtoupper( $plan['currency'] );
			$data['interval_snapshot'] = $data['interval_snapshot'] ?? $plan['interval_unit'];
		}
		$data['quantity'] = max( 1, absint( $data['quantity'] ?? 1 ) );
		if ( $plan && empty( $data['commitment_ends_at'] ) && absint( $plan['minimum_commitment_cycles'] ?? 0 ) && ! empty( $data['started_at'] ) ) $data['commitment_ends_at'] = gmdate( 'Y-m-d H:i:s', strtotime( '+' . absint( $plan['minimum_commitment_cycles'] ) . ' ' . $plan['interval_unit'], strtotime( $data['started_at'] . ' UTC' ) ) );
		$formats = array(); foreach ( array_keys( $data ) as $field ) $formats[] = in_array( $field, array( 'user_id', 'plan_id', 'cancel_at_period_end', 'failure_count', 'quantity' ), true ) ? '%d' : ( 'price_snapshot' === $field ? '%f' : '%s' );
		$wpdb->insert( $table, $data, $formats );
		return $wpdb->insert_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $wpdb->insert_id ), ARRAY_A ) : false;
	}

	public static function create_subscription_once( $data ) {
		$user_id = absint( $data['user_id'] ?? 0 ); $provider_id = sanitize_text_field( $data['provider_subscription_id'] ?? '' );
		if ( ! $user_id || ! $provider_id || ! self::begin_transaction() || ! self::lock_user( $user_id ) ) { self::rollback(); return false; }
		$existing = self::get_subscription_by_provider_id( $provider_id );
		if ( $existing ) { self::commit(); return array( 'subscription' => $existing, 'created' => false ); }
		$active = self::get_active_subscription( $user_id, true );
		$pending = self::get_pending_review_subscription( $user_id ); $pending_payment = self::get_pending_payment_subscription( $user_id );
		if ( $active || $pending || $pending_payment ) { self::commit(); return array( 'subscription' => $active ?: ( $pending ?: $pending_payment ), 'created' => false, 'conflict' => true ); }
		$created = self::create_subscription( $data );
		if ( ! $created || ! self::commit() ) { self::rollback(); return false; }
		if ( in_array( $created['status'], array( 'active', 'pending_activation' ), true ) ) { $plan = self::get_plan( $created['plan_id'] ); if ( $plan ) self::save_invoice( array( 'subscription_id' => $created['id'], 'user_id' => $created['user_id'], 'plan_id' => $created['plan_id'], 'provider_invoice_id' => 'activation:' . $created['id'], 'amount' => ( (float) ( $created['price_snapshot'] ?? $plan['price'] ) * max( 1, absint( $created['quantity'] ?? 1 ) ) ) + (float) ( $plan['setup_fee'] ?? 0 ), 'currency' => $created['currency_snapshot'] ?? $plan['currency'], 'status' => 'paid', 'invoice_url' => '', 'issued_at' => $created['current_period_start'] ?: $created['created_at'], 'created_at' => current_time( 'mysql', true ) ) ); }
		if ( 'pending_review' !== $created['status'] ) {
			do_action( 'daymark_subscription_created', $created );
			if ( in_array( $created['status'], array( 'active', 'pending_activation' ), true ) ) {
				do_action( 'daymark_subscription_payment_verified', $created );
			}
			if ( 'active' === $created['status'] ) {
				do_action( 'daymark_subscription_activated', $created );
				do_action( 'daymark_subscription_access_changed', $created, 'active' );
			}
		}
		return array( 'subscription' => $created, 'created' => true );
	}

	public static function save_invoice( array $data ) {
		global $wpdb;
		$table = self::tables()['invoices'];
		return false !== $wpdb->replace( $table, array( 'subscription_id' => absint( $data['subscription_id'] ), 'user_id' => absint( $data['user_id'] ), 'plan_id' => absint( $data['plan_id'] ), 'provider_invoice_id' => sanitize_text_field( $data['provider_invoice_id'] ), 'amount' => (float) $data['amount'], 'currency' => strtoupper( sanitize_text_field( $data['currency'] ) ), 'status' => sanitize_key( $data['status'] ), 'invoice_url' => esc_url_raw( $data['invoice_url'] ?? '' ), 'issued_at' => sanitize_text_field( $data['issued_at'] ), 'created_at' => sanitize_text_field( $data['created_at'] ) ), array( '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ) );
	}

	public static function update_invoice_status( $provider_invoice_id, $status ) {
		global $wpdb;
		return false !== $wpdb->update( self::tables()['invoices'], array( 'status' => sanitize_key( $status ) ), array( 'provider_invoice_id' => sanitize_text_field( $provider_invoice_id ) ), array( '%s' ), array( '%s' ) );
	}

	public static function get_user_invoices( $user_id, $limit = 20 ) {
		global $wpdb;
		$tables = self::tables(); $limit = max( 1, min( 50, absint( $limit ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.id, i.subscription_id, i.provider_invoice_id, i.amount, i.currency, i.status, i.invoice_url, i.issued_at, p.name AS plan_name FROM {$tables['invoices']} i LEFT JOIN {$tables['plans']} p ON p.id = i.plan_id WHERE i.user_id = %d ORDER BY i.issued_at DESC, i.id DESC LIMIT %d", absint( $user_id ), $limit ), ARRAY_A );
		if ( ! $rows ) { $subscription = self::get_user_subscription( $user_id ); $plan = $subscription ? self::get_plan( $subscription['plan_id'] ) : false; if ( $subscription && $plan && in_array( $subscription['status'], array( 'active', 'pending_activation', 'paused', 'past_due' ), true ) ) { self::save_invoice( array( 'subscription_id' => $subscription['id'], 'user_id' => $subscription['user_id'], 'plan_id' => $subscription['plan_id'], 'provider_invoice_id' => 'activation:' . $subscription['id'], 'amount' => ( (float) ( $subscription['price_snapshot'] ?? $plan['price'] ) * max( 1, absint( $subscription['quantity'] ?? 1 ) ) ) + (float) ( $plan['setup_fee'] ?? 0 ), 'currency' => $subscription['currency_snapshot'] ?? $plan['currency'], 'status' => 'paid', 'invoice_url' => '', 'issued_at' => $subscription['current_period_start'] ?: $subscription['started_at'], 'created_at' => current_time( 'mysql', true ) ) ); $rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.id, i.subscription_id, i.provider_invoice_id, i.amount, i.currency, i.status, i.invoice_url, i.issued_at, p.name AS plan_name FROM {$tables['invoices']} i LEFT JOIN {$tables['plans']} p ON p.id = i.plan_id WHERE i.user_id = %d ORDER BY i.issued_at DESC, i.id DESC LIMIT %d", absint( $user_id ), $limit ), ARRAY_A ); } }
		return $rows;
	}

	public static function get_user_timeline( $user_id, $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, min( 100, absint( $limit ) ) );
		$tables = self::tables();
		$sql = "SELECT action, details, created_at FROM (SELECT a.id, a.action, a.details, a.created_at FROM {$tables['audit_logs']} a INNER JOIN {$tables['subscriptions']} s ON s.id = a.object_id WHERE a.object_type = %s AND s.user_id = %d UNION ALL SELECT i.id + 1000000000 AS id, CONCAT('invoice_', i.status) AS action, JSON_OBJECT('plan_name', p.name, 'amount', i.amount, 'currency', i.currency, 'status', i.status) AS details, i.issued_at AS created_at FROM {$tables['invoices']} i LEFT JOIN {$tables['plans']} p ON p.id = i.plan_id WHERE i.user_id = %d) timeline ORDER BY created_at DESC, id DESC LIMIT %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, 'subscription', absint( $user_id ), absint( $user_id ), $limit ), ARRAY_A ) ?: array();
	}

	public static function get_invoice( $id ) {
		global $wpdb;
		$tables = self::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT i.id, i.subscription_id, i.user_id, i.plan_id, i.provider_invoice_id, i.amount, i.currency, i.status, i.invoice_url, i.issued_at, i.created_at, p.name AS plan_name FROM {$tables['invoices']} i LEFT JOIN {$tables['plans']} p ON p.id = i.plan_id WHERE i.id = %d", absint( $id ) ), ARRAY_A );
		return $row ?: false;
	}

	public static function cancel_subscription( $id, $user_id ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		return false !== $wpdb->update( $table, array( 'status' => 'canceled', 'canceled_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $id ), 'user_id' => absint( $user_id ), 'status' => 'active' ), array( '%s', '%s' ), array( '%d', '%d', '%s' ) );
	}

	public static function get_subscription_by_provider_id( $provider_id ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE provider_subscription_id = %s", $provider_id ), ARRAY_A );
	}

	public static function get_subscription_by_invoice_id( $invoice_id ) {
		global $wpdb;
		$tables = self::tables();
		return $wpdb->get_row( $wpdb->prepare( "SELECT s.* FROM {$tables['subscriptions']} s INNER JOIN {$tables['invoices']} i ON i.subscription_id = s.id WHERE i.provider_invoice_id = %s LIMIT 1", sanitize_text_field( $invoice_id ) ), ARRAY_A );
	}

	public static function mark_payment_issue( $subscription_id, $payment_status, $invoice_id = '' ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		if ( ! self::begin_transaction() ) return false;
		$data = array( 'status' => 'past_due', 'last_payment_status' => sanitize_key( $payment_status ), 'payment_issue_at' => current_time( 'mysql', true ), 'renewal_claimed_until' => null );
		$formats = array( '%s', '%s', '%s', '%s' );
		if ( $invoice_id ) { $data['last_invoice_id'] = sanitize_text_field( $invoice_id ); $formats[] = '%s'; }
		$updated = $wpdb->update( $table, $data, array( 'id' => absint( $subscription_id ) ), $formats, array( '%d' ) );
		$invoice_updated = true;
		if ( $invoice_id ) $invoice_updated = false !== $wpdb->update( self::tables()['invoices'], array( 'status' => sanitize_key( $payment_status ) ), array( 'provider_invoice_id' => sanitize_text_field( $invoice_id ) ), array( '%s' ), array( '%s' ) );
		if ( false === $updated || ! $invoice_updated || ! self::commit() ) { self::rollback(); return false; }
		$updated_subscription = self::get_subscription_by_id( $subscription_id );
		self::record_audit_log( 'payment_issue', 'subscription', $subscription_id, array( 'status' => sanitize_key( $payment_status ), 'invoice_id' => sanitize_text_field( $invoice_id ) ) );
		if ( $updated_subscription ) {
			do_action( 'daymark_subscription_payment_recovery_required', $updated_subscription, sanitize_key( $payment_status ), sanitize_text_field( $invoice_id ) );
			do_action( 'daymark_subscription_access_changed', $updated_subscription, 'past_due' );
		}
		return true;
	}

	public static function expire_past_due_subscriptions() {
		global $wpdb;
		$table = self::tables()['subscriptions']; $cutoff = gmdate( 'Y-m-d H:i:s', time() - self::payment_grace_days() * DAY_IN_SECONDS );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE status = 'past_due' AND payment_issue_at IS NOT NULL AND payment_issue_at <= %s", $cutoff ) );
		$updated = false !== $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'expired', renewal_claimed_until = NULL WHERE status = 'past_due' AND payment_issue_at IS NOT NULL AND payment_issue_at <= %s", $cutoff ) );
		if ( $updated ) foreach ( $ids as $id ) { $subscription = self::get_subscription_by_id( $id ); if ( $subscription ) { do_action( 'daymark_subscription_expired', $subscription ); do_action( 'daymark_subscription_access_changed', $subscription, 'expired' ); } }
		return $updated;
	}

	public static function get_subscription_by_id( $id ) {
		global $wpdb;
		$table = self::tables()['subscriptions'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ), ARRAY_A );
	}

	public static function mark_pending_payment( $id, $user_id, $checkout_session_id ) {
		return self::update_subscription( $id, $user_id, array( 'status' => 'payment_pending', 'checkout_session_id' => sanitize_text_field( $checkout_session_id ), 'last_payment_status' => 'awaiting_payment' ), array( '%s', '%s', '%s' ) );
	}

	public static function activate_approved_subscription( $id, array $data ) {
		global $wpdb;
		$table = self::tables()['subscriptions']; $id = absint( $id );
		if ( ! $id || ! self::begin_transaction() ) return false;
		$subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND status IN ('pending_review', 'payment_pending', 'pending_payment') FOR UPDATE", $id ), ARRAY_A );
		if ( ! $subscription ) { self::rollback(); return false; }
		$activation_status = in_array( $data['status'] ?? 'pending_activation', array( 'active', 'pending_activation' ), true ) ? $data['status'] : 'pending_activation';
		$updated = $wpdb->update( $table, array( 'status' => $activation_status, 'current_period_start' => $data['current_period_start'], 'current_period_end' => $data['current_period_end'], 'last_payment_status' => 'paid', 'provider_customer_id' => sanitize_text_field( $data['provider_customer_id'] ?? '' ), 'provider_subscription_id' => sanitize_text_field( $data['provider_subscription_id'] ), 'checkout_session_id' => sanitize_text_field( $data['checkout_session_id'] ?? '' ), 'idempotency_key' => sanitize_text_field( $data['idempotency_key'] ) ), array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		if ( false === $updated || ! self::commit() ) { self::rollback(); return false; }
		$created = self::get_subscription_by_id( $id ); $plan = self::get_plan( $created['plan_id'] ); if ( $plan ) self::save_invoice( array( 'subscription_id' => $created['id'], 'user_id' => $created['user_id'], 'plan_id' => $created['plan_id'], 'provider_invoice_id' => 'activation:' . $created['id'], 'amount' => ( (float) ( $created['price_snapshot'] ?? $plan['price'] ) * max( 1, absint( $created['quantity'] ?? 1 ) ) ) + (float) ( $plan['setup_fee'] ?? 0 ), 'currency' => $created['currency_snapshot'] ?? $plan['currency'], 'status' => 'paid', 'invoice_url' => '', 'issued_at' => $created['current_period_start'], 'created_at' => current_time( 'mysql', true ) ) ); do_action( 'daymark_subscription_created', $created ); do_action( 'daymark_subscription_payment_verified', $created ); if ( 'active' === $created['status'] ) { do_action( 'daymark_subscription_activated', $created ); do_action( 'daymark_subscription_access_changed', $created, 'active' ); } return $created;
	}

	public static function activate_pending_activation( $user_id ) {
		global $wpdb;
		$id = absint( $user_id );
		if ( ! $id || ! self::begin_transaction() ) return false;
		$subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::tables()['subscriptions'] . " WHERE user_id = %d AND status = 'pending_activation' ORDER BY id DESC LIMIT 1 FOR UPDATE", $id ), ARRAY_A );
		if ( ! $subscription ) { self::rollback(); return false; }
		$updated = $wpdb->update( self::tables()['subscriptions'], array( 'status' => 'active' ), array( 'id' => absint( $subscription['id'] ), 'status' => 'pending_activation' ), array( '%s' ), array( '%d', '%s' ) );
		if ( false === $updated || ! self::commit() ) { self::rollback(); return false; }
		$active = self::get_subscription_by_id( $subscription['id'] );
		if ( $active ) { do_action( 'daymark_subscription_activated', $active ); do_action( 'daymark_subscription_access_changed', $active, 'active' ); self::record_audit_log( 'subscription_activated', 'subscription', $active['id'], array( 'activation' => 'account_claimed' ) ); }
		return $active;
	}

	public static function mark_stripe_event( $event_id, $event_type, $payload = '' ) {
		global $wpdb;
		$table = self::tables()['events'];
		$now = current_time( 'mysql', true );
		if ( ! self::begin_transaction() ) return null;
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (event_id, event_type, payload, received_at, status) VALUES (%s, %s, %s, %s, %s)", $event_id, $event_type, $payload, $now, 'processing' ) );
		if ( false === $result ) { self::rollback(); return null; }
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, received_at FROM $table WHERE event_id = %s FOR UPDATE", $event_id ), ARRAY_A );
		if ( ! $row ) { self::rollback(); return null; }
		$reclaim = 'processed' !== $row['status'] && ( 'processing' !== $row['status'] || strtotime( $row['received_at'] . ' UTC' ) <= time() - self::EVENT_CLAIM_TIMEOUT_SECONDS );
		if ( 1 === (int) $result || $reclaim ) {
			$update = array( 'received_at' => $now, 'verified_at' => $now, 'status' => 'processing' ); $formats = array( '%s', '%s', '%s' );
			if ( $payload ) { $update['payload'] = $payload; $formats[] = '%s'; }
			if ( 1 !== (int) $result && false === $wpdb->update( $table, $update, array( 'event_id' => $event_id ), $formats, array( '%s' ) ) ) { self::rollback(); return null; }
			if ( ! self::commit() ) { self::rollback(); return null; }
			return true;
		}
		return self::commit() ? false : null;
	}

	public static function complete_stripe_event( $event_id ) {
		global $wpdb;
		return false !== $wpdb->update( self::tables()['events'], array( 'status' => 'processed', 'processed_at' => current_time( 'mysql', true ) ), array( 'event_id' => $event_id ), array( '%s', '%s' ), array( '%s' ) );
	}

	public static function release_stripe_event( $event_id ) {
		global $wpdb;
		return false !== $wpdb->update( self::tables()['events'], array( 'status' => 'failed' ), array( 'event_id' => $event_id ), array( '%s' ), array( '%s' ) );
	}

	public static function get_stripe_event( $event_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::tables()['events'] . " WHERE event_id = %s", sanitize_text_field( $event_id ) ), ARRAY_A );
	}

	public static function get_stripe_events( $limit = 100 ) {
		global $wpdb;
		$limit = max( 1, min( 500, absint( $limit ) ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT event_id, event_type, received_at, status, processed_at FROM " . self::tables()['events'] . " ORDER BY received_at DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function get_webhook_health() {
		global $wpdb;
		$table = self::tables()['events'];
		return array(
			'last_received' => (string) $wpdb->get_var( "SELECT received_at FROM $table ORDER BY received_at DESC LIMIT 1" ),
			'last_verified' => (string) $wpdb->get_var( "SELECT verified_at FROM $table WHERE verified_at IS NOT NULL ORDER BY verified_at DESC LIMIT 1" ),
			'last_processed' => (string) $wpdb->get_var( "SELECT processed_at FROM $table WHERE status = 'processed' ORDER BY processed_at DESC LIMIT 1" ),
			'last_failed' => (string) $wpdb->get_var( "SELECT received_at FROM $table WHERE status = 'failed' ORDER BY received_at DESC LIMIT 1" ),
		);
	}

	public static function update_by_provider_id( $provider_id, $data, $formats ) {
		global $wpdb;
		return false !== $wpdb->update( self::tables()['subscriptions'], $data, array( 'provider_subscription_id' => $provider_id ), $formats, array( '%s' ) );
	}
}
