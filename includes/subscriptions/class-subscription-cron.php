<?php
/**
 * RecruitTech Subscription Cron
 *
 * All enforcement (recruittech_subscription_get_current(), can_post_job(),
 * etc.) already decides "is this subscription usable right now" purely by
 * comparing expires_at to NOW() in SQL, so the stored status column being
 * momentarily stale never affects access control. This file exists only so
 * the status column itself stays truthful for anyone reading the table
 * directly (phpMyAdmin, a future admin list, etc.):
 *
 * - A daily WP-Cron event sweeps the whole table.
 * - recruittech_subscription_get_current() also does a small lazy update
 *   for the user/account_type it just looked up, so the column doesn't
 *   have to wait for cron on a low-traffic site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RECRUITTECH_SUBSCRIPTION_CRON_HOOK = 'recruittech_subscription_daily_cron';

/**
 * Cron callback: mark every subscription that is still stored as 'active'
 * but whose expires_at has already passed as 'expired'.
 */
function recruittech_subscription_cron_expire_subscriptions() {
	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_user_subscriptions';

	$wpdb->query( "UPDATE {$table} SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()" );
}
add_action( RECRUITTECH_SUBSCRIPTION_CRON_HOOK, 'recruittech_subscription_cron_expire_subscriptions' );

/**
 * Schedule the daily cron event if it isn't already scheduled. Hooked to
 * plugin activation.
 */
function recruittech_subscription_schedule_cron() {
	if ( ! wp_next_scheduled( RECRUITTECH_SUBSCRIPTION_CRON_HOOK ) ) {
		wp_schedule_event( time(), 'daily', RECRUITTECH_SUBSCRIPTION_CRON_HOOK );
	}
}

/**
 * Clear the scheduled event. Hooked to plugin uninstall.
 */
function recruittech_subscription_unschedule_cron() {
	wp_clear_scheduled_hook( RECRUITTECH_SUBSCRIPTION_CRON_HOOK );
}
