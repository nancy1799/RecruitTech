<?php
/**
 * RecruitTech My Subscription Page
 * Front-end shortcode showing the logged-in company or job seeker their
 * current subscription status and the plans available for their account
 * type, with a "Subscribe Now" button that starts a PayMob payment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the My Subscription shortcode.
 *
 * @return string
 */
function recruittech_my_subscription_shortcode() {
	if ( ! is_user_logged_in() ) {
		return recruittech_send_access_denied( home_url( '/login/' ), '<p>Please log in to view your subscription.</p>' );
	}

	$current_user = wp_get_current_user();

	if ( recruittech_is_company_user( $current_user->ID ) ) {
		$account_type = 'company';
	} elseif ( recruittech_is_job_seeker_user( $current_user->ID ) ) {
		$account_type = 'job_seeker';
	} else {
		return recruittech_send_access_denied( recruittech_get_user_dashboard_url() );
	}

	$subscriptions_enabled = recruittech_subscription_is_enabled();
	$account_type_has_plans = recruittech_subscription_account_type_has_plans( $account_type );
	$current_subscription  = $subscriptions_enabled ? recruittech_subscription_get_current( $current_user->ID, $account_type ) : null;
	$available_plans       = $subscriptions_enabled ? recruittech_subscription_get_plans( $account_type, true ) : array();
	$ai_features            = recruittech_subscription_get_ai_features( $account_type );
	$limit_label            = 'company' === $account_type ? 'Jobs you can post' : 'Applications you can submit';

	$notice_code       = isset( $_GET['recruittech_subscription_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['recruittech_subscription_notice'] ) ) : '';
	$subscribe_result  = isset( $_GET['recruittech_subscribe_result'] ) ? sanitize_text_field( wp_unslash( $_GET['recruittech_subscribe_result'] ) ) : '';
	$subscribe_success = isset( $_GET['recruittech_subscribe_success'] ) ? sanitize_text_field( wp_unslash( $_GET['recruittech_subscribe_success'] ) ) : '';

	ob_start();
	include RECRUITTECH_PLUGIN_PATH . 'templates/subscription-page.php';
	return ob_get_clean();
}

/**
 * Register the shortcode.
 */
function recruittech_register_my_subscription_shortcode() {
	add_shortcode( 'recruittech_my_subscription', 'recruittech_my_subscription_shortcode' );
}
add_action( 'init', 'recruittech_register_my_subscription_shortcode' );

/**
 * URL of the My Subscription page, with the same fallback pattern used by
 * the other recruittech_get_*_page_url() helpers.
 *
 * @return string
 */
function recruittech_get_my_subscription_page_url() {
	return function_exists( 'recruittech_get_shortcode_page_url' )
		? recruittech_get_shortcode_page_url( 'recruittech_my_subscription', 'my-subscription' )
		: home_url( '/my-subscription/' );
}
