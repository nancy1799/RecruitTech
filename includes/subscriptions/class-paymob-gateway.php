<?php
/**
 * RecruitTech PayMob Gateway
 *
 * Talks to PayMob's Intention API (https://accept.paymob.com/v1/intention/)
 * to start a payment, and verifies the HMAC on the transaction callback
 * PayMob sends back to includes/subscriptions-ajax.php.
 *
 * Credentials are entered on Settings > RecruitTech Subscriptions and read
 * here via get_option(): recruittech_paymob_secret_key,
 * recruittech_paymob_public_key, recruittech_paymob_integration_id,
 * recruittech_paymob_hmac_secret.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-payment-gateway.php';

class RecruitTech_Paymob_Gateway implements RecruitTech_Payment_Gateway {

	const INTENTION_URL = 'https://accept.paymob.com/v1/intention/';
	const CHECKOUT_URL  = 'https://accept.paymob.com/unifiedcheckout/';

	/**
	 * @inheritDoc
	 */
	public function create_payment( $args ) {
		$secret_key    = get_option( 'recruittech_paymob_secret_key', '' );
		$public_key    = get_option( 'recruittech_paymob_public_key', '' );
		$integration_id = get_option( 'recruittech_paymob_integration_id', '' );

		if ( '' === $secret_key || '' === $public_key || '' === $integration_id ) {
			return new WP_Error( 'recruittech_paymob_not_configured', 'PayMob is not configured yet. Please contact the site administrator.' );
		}

		$amount   = isset( $args['amount'] ) ? (float) $args['amount'] : 0;
		$currency = isset( $args['currency'] ) ? $args['currency'] : 'EGP';
		$billing  = isset( $args['billing_data'] ) && is_array( $args['billing_data'] ) ? $args['billing_data'] : array();

		$body = array(
			'amount'             => (int) round( $amount * 100 ), // PayMob amounts are in cents.
			'currency'           => $currency,
			'payment_methods'    => array( absint( $integration_id ) ),
			'items'              => array(
				array(
					'name'        => isset( $args['item_name'] ) ? $args['item_name'] : 'RecruitTech Subscription',
					'amount'      => (int) round( $amount * 100 ),
					'description' => isset( $args['item_name'] ) ? $args['item_name'] : 'RecruitTech Subscription',
					'quantity'    => 1,
				),
			),
			'billing_data'       => array(
				'first_name'   => isset( $billing['first_name'] ) ? $billing['first_name'] : 'NA',
				'last_name'    => isset( $billing['last_name'] ) ? $billing['last_name'] : 'NA',
				'email'        => isset( $billing['email'] ) ? $billing['email'] : 'na@na.com',
				'phone_number' => isset( $billing['phone_number'] ) ? $billing['phone_number'] : 'NA',
				'apartment'    => 'NA',
				'floor'        => 'NA',
				'street'       => 'NA',
				'building'     => 'NA',
				'city'         => 'NA',
				'state'        => 'NA',
				'country'      => 'EG',
			),
			'special_reference'  => isset( $args['special_reference'] ) ? $args['special_reference'] : '',
			'notification_url'   => admin_url( 'admin-ajax.php?action=recruittech_paymob_webhook' ),
			'redirection_url'    => isset( $args['redirection_url'] ) ? $args['redirection_url'] : '',
		);

		$response = wp_remote_post(
			self::INTENTION_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Token ' . $secret_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'recruittech_paymob_request_failed', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $response_body['client_secret'] ) ) {
			$error_message = ! empty( $response_body['message'] ) ? $response_body['message'] : 'PayMob rejected the payment request.';
			return new WP_Error( 'recruittech_paymob_error', $error_message, $response_body );
		}

		$redirect_url = add_query_arg(
			array(
				'publicKey'    => rawurlencode( $public_key ),
				'clientSecret' => rawurlencode( $response_body['client_secret'] ),
			),
			self::CHECKOUT_URL
		);

		return array(
			'redirect_url'     => $redirect_url,
			'gateway_order_id' => isset( $response_body['intention_order_id'] ) ? $response_body['intention_order_id'] : '',
		);
	}

	/**
	 * @inheritDoc
	 *
	 * Follows PayMob's documented "transaction processed callback" HMAC
	 * recipe: concatenate the listed fields (in this exact order) from the
	 * transaction object, then compare SHA512-HMAC (hex) against the hmac
	 * PayMob sent with the callback.
	 */
	public function verify_webhook( $payload, $received_hmac ) {
		$hmac_secret = get_option( 'recruittech_paymob_hmac_secret', '' );
		if ( '' === $hmac_secret || '' === $received_hmac ) {
			return new WP_Error( 'recruittech_paymob_hmac_missing', 'Missing HMAC configuration or signature.' );
		}

		$transaction = isset( $payload['obj'] ) && is_array( $payload['obj'] ) ? $payload['obj'] : $payload;

		$ordered_fields = array(
			'amount_cents',
			'created_at',
			'currency',
			'error_occured',
			'has_parent_transaction',
			'id',
			'integration_id',
			'is_3d_secure',
			'is_auth',
			'is_capture',
			'is_refunded',
			'is_standalone_payment',
			'is_voided',
			'order.id',
			'owner',
			'pending',
			'source_data.pan',
			'source_data.sub_type',
			'source_data.type',
			'success',
		);

		$concatenated = '';
		foreach ( $ordered_fields as $field_path ) {
			$concatenated .= $this->get_nested_value( $transaction, $field_path );
		}

		$calculated_hmac = hash_hmac( 'sha512', $concatenated, $hmac_secret );

		if ( ! hash_equals( $calculated_hmac, (string) $received_hmac ) ) {
			return new WP_Error( 'recruittech_paymob_hmac_mismatch', 'HMAC signature does not match. Rejecting callback.' );
		}

		return array(
			'success'                => ! empty( $transaction['success'] ) && true === $transaction['success'],
			'special_reference'      => isset( $transaction['order']['merchant_order_id'] ) ? $transaction['order']['merchant_order_id'] : '',
			'gateway_transaction_id' => isset( $transaction['id'] ) ? $transaction['id'] : '',
		);
	}

	/**
	 * Read a dot-notated key (e.g. 'source_data.pan') from a nested array,
	 * returning an empty string when missing (booleans are cast to
	 * 'true'/'false' to match how PayMob renders them in the signed string).
	 *
	 * @param array  $data Data array.
	 * @param string $path Dot-notated path.
	 * @return string
	 */
	private function get_nested_value( $data, $path ) {
		$segments = explode( '.', $path );
		$value    = $data;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return '';
			}
			$value = $value[ $segment ];
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return '';
		}

		return (string) $value;
	}
}
