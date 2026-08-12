<?php
/**
 * RecruitTech Payment Gateway Interface
 *
 * A minimal contract every payment gateway (PayMob today, others later)
 * must implement, so the subscription purchase flow never has to know
 * which gateway is behind it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface RecruitTech_Payment_Gateway {

	/**
	 * Start a payment for a subscription plan and return where the
	 * customer should be sent to pay.
	 *
	 * @param array $args {
	 *     @type int    $transaction_id Row ID in recruitech_subscription_transactions.
	 *     @type float  $amount         Amount in the plan's currency (e.g. EGP), not cents.
	 *     @type string $currency       e.g. 'EGP'.
	 *     @type string $special_reference Merchant reference for this payment.
	 *     @type array  $billing_data   first_name, last_name, email, phone_number.
	 * }
	 * @return array|WP_Error {
	 *     @type string $redirect_url        URL to send the customer to.
	 *     @type string $gateway_order_id    Gateway's order/intention ID, if any.
	 * }
	 */
	public function create_payment( $args );

	/**
	 * Verify an incoming webhook/callback request and extract the result.
	 *
	 * @param array $payload Raw request payload (e.g. $_POST or decoded JSON body).
	 * @param string $received_hmac HMAC signature sent by the gateway.
	 * @return array|WP_Error {
	 *     @type bool   $success                Whether the transaction succeeded.
	 *     @type string $special_reference      The merchant reference that was paid.
	 *     @type string $gateway_transaction_id Gateway transaction ID.
	 * }
	 */
	public function verify_webhook( $payload, $received_hmac );
}
