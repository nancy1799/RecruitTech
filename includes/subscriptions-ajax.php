<?php
/**
 * RecruitTech Subscriptions Payment Handlers
 *
 * - recruittech_handle_subscription_purchase(): handles the "Subscribe Now"
 *   form submitted from templates/subscription-page.php, opens a
 *   subscription_transactions row, asks the active payment gateway to
 *   start a payment, then redirects the user to pay.
 * - recruittech_ajax_paymob_webhook(): the PayMob transaction callback
 *   (admin-ajax.php?action=recruittech_paymob_webhook). Verifies the HMAC,
 *   marks the transaction, and activates the subscription on success.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback billing phone number for PayMob card payments.
 *
 * This is only ever used as billing-data filler for card payments (PayMob
 * requires a phone_number field, but the card flow doesn't actually use it
 * to contact the customer). It must NOT be relied on if/when this plugin
 * adds an e-wallet payment method (e.g. Vodafone Cash), since those flows
 * genuinely need the customer's real phone number at the moment of
 * payment - at that point, collect it from the user directly instead of
 * falling back to this constant.
 */
define( 'RECRUITTECH_PAYMOB_FALLBACK_PHONE', '+201000000000' );

/**
 * Get the configured payment gateway instance for a given gateway key.
 *
 * @param string $gateway Gateway key, e.g. 'paymob'.
 * @return RecruitTech_Payment_Gateway|null
 */
function recruittech_get_payment_gateway( $gateway = 'paymob' ) {
	if ( 'paymob' === $gateway && class_exists( 'RecruitTech_Paymob_Gateway' ) ) {
		return new RecruitTech_Paymob_Gateway();
	}

	return null;
}

/**
 * Handle the "Subscribe Now" form submission from the My Subscription page.
 */
function recruittech_handle_subscription_purchase() {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['recruittech_subscribe_submit'] ) ) {
		return;
	}

	$subscription_page_url = function_exists( 'recruittech_get_shortcode_page_url' )
		? recruittech_get_shortcode_page_url( 'recruittech_my_subscription', 'my-subscription' )
		: home_url( '/my-subscription/' );

	if ( ! isset( $_POST['recruittech_subscribe_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['recruittech_subscribe_nonce'] ), 'recruittech_subscribe_action' ) ) {
		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'invalid_nonce', $subscription_page_url ) );
		exit;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'not_logged_in', $subscription_page_url ) );
		exit;
	}

	$current_user = wp_get_current_user();
	$plan_id      = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
	$plan         = recruittech_subscription_get_plan( $plan_id );

	if ( empty( $plan ) || 'active' !== $plan['status'] ) {
		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'invalid_plan', $subscription_page_url ) );
		exit;
	}

	$is_company_account = recruittech_is_company_user( $current_user->ID );
	$is_job_seeker_account = recruittech_is_job_seeker_user( $current_user->ID );

	if ( ( 'company' === $plan['account_type'] && ! $is_company_account )
		|| ( 'job_seeker' === $plan['account_type'] && ! $is_job_seeker_account ) ) {
		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'wrong_account_type', $subscription_page_url ) );
		exit;
	}

	global $wpdb;
	$transactions_table = $wpdb->prefix . 'recruitech_subscription_transactions';

	// Free plan: activate immediately, no PayMob involved at all. Still log
	// a transaction row (status success, gateway 'free', amount 0) so the
	// payment history stays complete and consistent.
	if ( 0.0 === (float) $plan['price'] ) {
		$new_subscription_id = recruittech_subscription_activate( $current_user->ID, $plan['id'] );

		if ( is_wp_error( $new_subscription_id ) ) {
			wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'transaction_failed', $subscription_page_url ) );
			exit;
		}

		$wpdb->insert(
			$transactions_table,
			array(
				'user_id'         => $current_user->ID,
				'subscription_id' => $new_subscription_id,
				'plan_id'         => absint( $plan['id'] ),
				'gateway'         => 'free',
				'amount'          => 0,
				'currency'        => 'EGP',
				'status'          => 'success',
			),
			array( '%d', '%d', '%d', '%s', '%f', '%s', '%s' )
		);

		if ( function_exists( 'recruittech_add_notification' ) ) {
			recruittech_add_notification(
				$current_user->ID,
				'Subscription Activated',
				'Your subscription payment was received and your plan is now active.',
				'subscription'
			);
		}

		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_success', 'free_plan_activated', $subscription_page_url ) );
		exit;
	}

	$inserted = $wpdb->insert(
		$transactions_table,
		array(
			'user_id' => $current_user->ID,
			'plan_id' => absint( $plan['id'] ),
			'gateway' => 'paymob',
			'amount'  => $plan['price'],
			'currency' => 'EGP',
			'status'  => 'pending',
		),
		array( '%d', '%d', '%s', '%f', '%s', '%s' )
	);

	if ( false === $inserted ) {
		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'transaction_failed', $subscription_page_url ) );
		exit;
	}

	$transaction_id    = $wpdb->insert_id;
	$special_reference = 'rtsub_' . $transaction_id;

	$gateway = recruittech_get_payment_gateway( 'paymob' );
	if ( null === $gateway ) {
		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'gateway_unavailable', $subscription_page_url ) );
		exit;
	}

	$phone_number = '';
	if ( $is_job_seeker_account && function_exists( 'recruittech_get_job_seeker_by_user_id' ) ) {
		$job_seeker_profile = recruittech_get_job_seeker_by_user_id( $current_user->ID );
		if ( ! empty( $job_seeker_profile['phone'] ) ) {
			$phone_number = $job_seeker_profile['phone'];
		}
	}
	// Companies have no phone column yet (recruitech_companies), and any
	// missing/empty job seeker phone also lands here.
	if ( '' === $phone_number ) {
		$phone_number = RECRUITTECH_PAYMOB_FALLBACK_PHONE;
	}

	$payment_result = $gateway->create_payment(
		array(
			'transaction_id'     => $transaction_id,
			'amount'             => $plan['price'],
			'currency'           => 'EGP',
			'item_name'          => $plan['plan_name'],
			'special_reference'  => $special_reference,
			'redirection_url'    => $subscription_page_url,
			'billing_data'       => array(
				'first_name'   => $current_user->first_name ? $current_user->first_name : $current_user->display_name,
				'last_name'    => $current_user->last_name ? $current_user->last_name : 'NA',
				'email'        => $current_user->user_email,
				'phone_number' => $phone_number,
			),
		)
	);

	if ( is_wp_error( $payment_result ) ) {
		$error_data = $payment_result->get_error_data();
		$wpdb->update(
			$transactions_table,
			array(
				'status'       => 'failed',
				'raw_response' => wp_json_encode(
					array(
						'message' => $payment_result->get_error_message(),
						'data'    => $error_data,
					)
				),
			),
			array( 'id' => $transaction_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		wp_safe_redirect( add_query_arg( 'recruittech_subscribe_result', 'gateway_error', $subscription_page_url ) );
		exit;
	}

	$wpdb->update(
		$transactions_table,
		array( 'gateway_order_id' => $payment_result['gateway_order_id'] ),
		array( 'id' => $transaction_id ),
		array( '%s' ),
		array( '%d' )
	);

	// wp_safe_redirect() would silently fall back to admin_url() here since
	// accept.paymob.com isn't in allowed_redirect_hosts. This URL comes
	// straight from a trusted, verified PayMob API response (not user
	// input), so a plain wp_redirect() to their checkout page is safe.
	wp_redirect( $payment_result['redirect_url'] );
	exit;
}
add_action( 'init', 'recruittech_handle_subscription_purchase' );

/**
 * Handle the PayMob transaction processed callback.
 * Registered for both logged-in and logged-out requests since PayMob calls
 * this endpoint directly, without a WordPress session.
 */
function recruittech_ajax_paymob_webhook() {
	$raw_body = file_get_contents( 'php://input' );
	$payload  = json_decode( $raw_body, true );

	if ( ! is_array( $payload ) ) {
		// Some PayMob callback modes send form-encoded data instead of JSON.
		$payload = wp_unslash( $_POST );
	}

	$received_hmac = isset( $_GET['hmac'] ) ? sanitize_text_field( wp_unslash( $_GET['hmac'] ) ) : ( isset( $payload['hmac'] ) ? sanitize_text_field( $payload['hmac'] ) : '' );

	$gateway = recruittech_get_payment_gateway( 'paymob' );
	if ( null === $gateway ) {
		wp_send_json_error( array( 'message' => 'Gateway unavailable.' ), 500 );
	}

	$result = $gateway->verify_webhook( $payload, $received_hmac );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 401 );
	}

	global $wpdb;
	$transactions_table = $wpdb->prefix . 'recruitech_subscription_transactions';

	// special_reference looks like "rtsub_<transaction_id>".
	$special_reference = isset( $result['special_reference'] ) ? $result['special_reference'] : '';
	$transaction_id    = 0;
	if ( preg_match( '/^rtsub_(\d+)$/', $special_reference, $matches ) ) {
		$transaction_id = absint( $matches[1] );
	}

	$transaction = $transaction_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$transactions_table} WHERE id = %d", $transaction_id ), ARRAY_A ) : null;

	if ( empty( $transaction ) ) {
		wp_send_json_error( array( 'message' => 'Unknown transaction.' ), 404 );
	}

	// Already processed: acknowledge without doing anything twice.
	if ( 'pending' !== $transaction['status'] ) {
		wp_send_json_success( array( 'message' => 'Already processed.' ) );
	}

	if ( empty( $result['success'] ) ) {
		$wpdb->update(
			$transactions_table,
			array(
				'status'                 => 'failed',
				'gateway_transaction_id' => isset( $result['gateway_transaction_id'] ) ? $result['gateway_transaction_id'] : null,
				'raw_response'           => wp_json_encode( $payload ),
			),
			array( 'id' => $transaction_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		wp_send_json_success( array( 'message' => 'Payment failed, transaction recorded.' ) );
	}

	$new_subscription_id = recruittech_subscription_activate( $transaction['user_id'], $transaction['plan_id'] );

	if ( is_wp_error( $new_subscription_id ) ) {
		$wpdb->update(
			$transactions_table,
			array( 'status' => 'failed', 'raw_response' => $new_subscription_id->get_error_message() ),
			array( 'id' => $transaction_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		wp_send_json_error( array( 'message' => $new_subscription_id->get_error_message() ), 500 );
	}

	$wpdb->update(
		$transactions_table,
		array(
			'status'                 => 'success',
			'subscription_id'        => $new_subscription_id,
			'gateway_transaction_id' => isset( $result['gateway_transaction_id'] ) ? $result['gateway_transaction_id'] : null,
			'raw_response'           => wp_json_encode( $payload ),
		),
		array( 'id' => $transaction_id ),
		array( '%s', '%d', '%s', '%s' ),
		array( '%d' )
	);

	if ( function_exists( 'recruittech_add_notification' ) ) {
		recruittech_add_notification(
			$transaction['user_id'],
			'Subscription Activated',
			'Your subscription payment was received and your plan is now active.',
			'subscription'
		);
	}

	wp_send_json_success( array( 'message' => 'Subscription activated.' ) );
}
add_action( 'wp_ajax_recruittech_paymob_webhook', 'recruittech_ajax_paymob_webhook' );
add_action( 'wp_ajax_nopriv_recruittech_paymob_webhook', 'recruittech_ajax_paymob_webhook' );
