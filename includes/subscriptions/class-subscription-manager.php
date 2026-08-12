<?php
/**
 * RecruitTech Subscription Manager
 *
 * Core subscription logic shared by the admin settings page, the payment
 * gateway callback, and the enforcement checks in dashboards.php /
 * ai-ajax.php. As long as Settings > RecruitTech Subscriptions is left
 * disabled (the default), every function below behaves as if the site has
 * no limits at all, so the plugin keeps working exactly like before this
 * module was added.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the site owner turned the subscription system on.
 *
 * @return bool
 */
function recruittech_subscription_is_enabled() {
	return '1' === (string) get_option( 'recruittech_subscriptions_enabled', '0' );
}

/**
 * The AI features a plan can grant access to, per account type. Keys must
 * match the "feature" strings passed to recruittech_subscription_can_use_ai_feature().
 *
 * @param string $account_type 'company' or 'job_seeker'.
 * @return array<string,string> feature_key => human label
 */
function recruittech_subscription_get_ai_features( $account_type ) {
	if ( 'company' === $account_type ) {
		return array(
			'analyze'    => 'Analyze with AI',
			'rank_top10' => 'Rank Top 10 Candidates',
		);
	}

	if ( 'job_seeker' === $account_type ) {
		return array(
			'check_fit' => 'Check My Fit',
		);
	}

	return array();
}

/**
 * Fetch subscription plans for an account type.
 *
 * @param string $account_type 'company' or 'job_seeker'.
 * @param bool   $only_active  Only return plans with status = active.
 * @return array[]
 */
function recruittech_subscription_get_plans( $account_type, $only_active = false ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_subscription_plans';

	if ( $only_active ) {
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE account_type = %s AND status = 'active' ORDER BY price ASC",
			$account_type
		);
	} else {
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE account_type = %s ORDER BY price ASC",
			$account_type
		);
	}

	$rows = $wpdb->get_results( $sql, ARRAY_A );
	return is_array( $rows ) ? $rows : array();
}

/**
 * Whether at least one active plan exists for an account type. If a site
 * owner turns the subscription system on but never creates a plan for, say,
 * companies, company accounts should stay free/unlimited rather than being
 * locked out with nothing to subscribe to.
 *
 * @param string $account_type 'company' or 'job_seeker'.
 * @return bool
 */
function recruittech_subscription_account_type_has_plans( $account_type ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_subscription_plans';

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE account_type = %s AND status = 'active'",
			$account_type
		)
	);

	return $count > 0;
}

/**
 * Fetch a single plan by ID.
 *
 * @param int $plan_id Plan ID.
 * @return array|null
 */
function recruittech_subscription_get_plan( $plan_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_subscription_plans';

	$plan_id = absint( $plan_id );
	if ( ! $plan_id ) {
		return null;
	}

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $plan_id ), ARRAY_A );
	return ! empty( $row ) ? $row : null;
}

/**
 * Whether a plan has any subscriptions (used to block hard-deleting it).
 *
 * @param int $plan_id Plan ID.
 * @return bool
 */
function recruittech_subscription_plan_has_subscribers( $plan_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_user_subscriptions';

	$plan_id = absint( $plan_id );
	if ( ! $plan_id ) {
		return false;
	}

	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE plan_id = %d", $plan_id ) );
	return $count > 0;
}

/**
 * Get the subscription that is currently in effect for a user (status is
 * 'active' and NOW() falls inside its starts_at/expires_at window).
 *
 * @param int    $user_id      WP user ID.
 * @param string $account_type 'company' or 'job_seeker'.
 * @return array|null
 */
function recruittech_subscription_get_current( $user_id, $account_type ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_user_subscriptions';

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return null;
	}

	// Lazy check: if this user has a row still marked 'active' whose
	// expires_at has already passed, correct it now instead of waiting for
	// the next daily cron run. The date-based WHERE clause below already
	// treats it as not-current either way, so this only fixes the stored
	// status column and changes no access-control behavior.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET status = 'expired'
			WHERE user_id = %d AND account_type = %s AND status = 'active' AND expires_at < NOW()",
			$user_id,
			$account_type
		)
	);

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE user_id = %d AND account_type = %s AND status = 'active'
			AND starts_at <= NOW() AND expires_at >= NOW()
			ORDER BY starts_at DESC LIMIT 1",
			$user_id,
			$account_type
		),
		ARRAY_A
	);

	return ! empty( $row ) ? $row : null;
}

/**
 * Get the user's furthest-reaching active subscription (current or
 * already-scheduled future renewal), used as the anchor point when
 * activating a new renewal so unused remaining days are never lost.
 *
 * @param int    $user_id      WP user ID.
 * @param string $account_type 'company' or 'job_seeker'.
 * @return array|null
 */
function recruittech_subscription_get_latest_active( $user_id, $account_type ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_user_subscriptions';

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return null;
	}

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE user_id = %d AND account_type = %s AND status = 'active'
			ORDER BY expires_at DESC LIMIT 1",
			$user_id,
			$account_type
		),
		ARRAY_A
	);

	return ! empty( $row ) ? $row : null;
}

/**
 * Get every subscription row a user has ever had (history), most recent first.
 *
 * @param int    $user_id      WP user ID.
 * @param string $account_type 'company' or 'job_seeker'.
 * @return array[]
 */
function recruittech_subscription_get_history( $user_id, $account_type ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_user_subscriptions';

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return array();
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d AND account_type = %s ORDER BY created_at DESC",
			$user_id,
			$account_type
		),
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * Whether a company can post a new job right now.
 *
 * @param int $user_id WP user ID of the company account.
 * @return bool
 */
function recruittech_subscription_can_post_job( $user_id ) {
	if ( ! recruittech_subscription_is_enabled() ) {
		return true;
	}

	if ( ! recruittech_subscription_account_type_has_plans( 'company' ) ) {
		return true;
	}

	$subscription = recruittech_subscription_get_current( $user_id, 'company' );
	if ( empty( $subscription ) ) {
		return false;
	}

	return (int) $subscription['usage_count'] < (int) $subscription['usage_limit_snapshot'];
}

/**
 * Whether a job seeker can submit a new application right now.
 *
 * @param int $user_id WP user ID of the job seeker account.
 * @return bool
 */
function recruittech_subscription_can_apply( $user_id ) {
	if ( ! recruittech_subscription_is_enabled() ) {
		return true;
	}

	if ( ! recruittech_subscription_account_type_has_plans( 'job_seeker' ) ) {
		return true;
	}

	$subscription = recruittech_subscription_get_current( $user_id, 'job_seeker' );
	if ( empty( $subscription ) ) {
		return false;
	}

	return (int) $subscription['usage_count'] < (int) $subscription['usage_limit_snapshot'];
}

/**
 * Whether a user's current plan grants access to a given AI feature.
 *
 * @param int    $user_id      WP user ID.
 * @param string $account_type 'company' or 'job_seeker'.
 * @param string $feature_key  e.g. 'analyze', 'rank_top10', 'check_fit'.
 * @return bool
 */
function recruittech_subscription_can_use_ai_feature( $user_id, $account_type, $feature_key ) {
	if ( ! recruittech_subscription_is_enabled() ) {
		return true;
	}

	if ( ! recruittech_subscription_account_type_has_plans( $account_type ) ) {
		return true;
	}

	$subscription = recruittech_subscription_get_current( $user_id, $account_type );
	if ( empty( $subscription ) ) {
		return false;
	}

	$allowed_features = array_filter( array_map( 'trim', explode( ',', (string) $subscription['ai_features_snapshot'] ) ) );
	return in_array( $feature_key, $allowed_features, true );
}

/**
 * Increment the usage counter (jobs posted / applications submitted) on a
 * subscription after a successful action.
 *
 * @param int $subscription_id user_subscriptions row ID.
 * @return void
 */
function recruittech_subscription_increment_usage( $subscription_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_user_subscriptions';

	$subscription_id = absint( $subscription_id );
	if ( ! $subscription_id ) {
		return;
	}

	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET usage_count = usage_count + 1 WHERE id = %d", $subscription_id ) );
}

/**
 * Activate a subscription for a user after a successful payment.
 *
 * Renewal rule: if the user still has time left on a currently-active (or
 * already-scheduled future) subscription, the new period is stacked to
 * start right when that one ends, so no paid days are lost. Otherwise the
 * new period starts immediately. Every call inserts a new row, so the
 * user_subscriptions table also serves as a full subscription history.
 *
 * @param int $user_id WP user ID.
 * @param int $plan_id Plan being purchased.
 * @return int|WP_Error New user_subscriptions row ID, or WP_Error on failure.
 */
function recruittech_subscription_activate( $user_id, $plan_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	$plan    = recruittech_subscription_get_plan( $plan_id );

	if ( ! $user_id || empty( $plan ) ) {
		return new WP_Error( 'recruittech_subscription_invalid', 'Invalid user or plan.' );
	}

	$account_type = $plan['account_type'];
	$now          = current_time( 'mysql' );

	$latest = recruittech_subscription_get_latest_active( $user_id, $account_type );
	if ( ! empty( $latest ) && strtotime( $latest['expires_at'] ) > strtotime( $now ) ) {
		// User still has time left: stack the new period right after it.
		$starts_at = $latest['expires_at'];
	} else {
		$starts_at = $now;
	}

	$duration_days = (int) $plan['duration_days'];
	$expires_at    = gmdate( 'Y-m-d H:i:s', strtotime( $starts_at ) + ( $duration_days * DAY_IN_SECONDS ) );

	$table   = $wpdb->prefix . 'recruitech_user_subscriptions';
	$inserted = $wpdb->insert(
		$table,
		array(
			'user_id'              => $user_id,
			'account_type'         => $account_type,
			'plan_id'              => absint( $plan['id'] ),
			'plan_name_snapshot'   => $plan['plan_name'],
			'usage_limit_snapshot' => absint( $plan['usage_limit'] ),
			'ai_features_snapshot' => $plan['ai_features'],
			'usage_count'          => 0,
			'status'               => 'active',
			'starts_at'            => $starts_at,
			'expires_at'           => $expires_at,
		),
		array( '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		return new WP_Error( 'recruittech_subscription_insert_failed', 'Could not create the subscription.' );
	}

	return (int) $wpdb->insert_id;
}
