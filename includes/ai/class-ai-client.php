<?php
/**
 * RecruitTech AI Gateway Client
 *
 * Talks to the ITI student AI gateway (apiaccess.iti.net.eg), NOT OpenAI or
 * raw AWS Bedrock directly. The gateway validates the student's API key,
 * checks their model/budget policy, then forwards the request to Bedrock
 * on their behalf using its own request/response schema:
 *
 *   POST http://apiaccess.iti.net.eg/api/v1/student/chat
 *   Body: { model_id, system_prompt, messages: [ { role: "user", content } ], max_tokens }
 *   Reply: the generated text comes back as "output_text" (a JSON string in
 *          this project's case), with a few other field names checked as a
 *          fallback since the gateway's response shape isn't documented.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecruitTech_AI_Client {

	/**
	 * ITI student AI gateway endpoint.
	 */
	const ENDPOINT = 'http://apiaccess.iti.net.eg/api/v1/student/chat';

	/**
	 * Get the stored gateway API key.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return trim( (string) get_option( 'recruittech_ai_api_key', '' ) );
	}

	/**
	 * Get the model ID to send to the gateway.
	 *
	 * Must match one of the entries in the student's "Approved models"
	 * list on the ITI dashboard, so this is configurable in Settings
	 * rather than hard-coded.
	 *
	 * @return string
	 */
	public static function get_model_id() {
		$model_id = trim( (string) get_option( 'recruittech_ai_model_id', '' ) );
		return '' !== $model_id ? $model_id : 'anthropic.claude-3-5-sonnet-20240620-v1:0';
	}

	/**
	 * Get the configured max_tokens value.
	 *
	 * @return int
	 */
	public static function get_max_tokens() {
		$max_tokens = absint( get_option( 'recruittech_ai_max_tokens', 1500 ) );
		return $max_tokens > 0 ? $max_tokens : 1500;
	}

	/**
	 * Whether an API key has been configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get_api_key();
	}

	/**
	 * Send a chat request to the AI gateway.
	 *
	 * @param string $system_prompt Instructions for the model (role, output format, etc.).
	 * @param string $user_message  The actual request (candidate, job, policy context).
	 * @return string|WP_Error Generated text on success, WP_Error on failure.
	 */
	public static function chat( $system_prompt, $user_message ) {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'recruittech_ai_not_configured',
				'The AI Recruitment Assistant is not configured yet. Add your gateway API key under Settings > RecruitTech AI.'
			);
		}

		$request_body = array(
			'model_id'      => self::get_model_id(),
			'system_prompt' => (string) $system_prompt,
			'messages'      => array(
				array(
					'role'    => 'user',
					'content' => (string) $user_message,
				),
			),
			'max_tokens'    => self::get_max_tokens(),
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . self::get_api_key(),
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = ( is_array( $decoded ) && ! empty( $decoded['message'] ) )
				? sanitize_text_field( $decoded['message'] )
				: sprintf( 'The AI gateway returned an error (HTTP %d).', $status_code );

			return new WP_Error( 'recruittech_ai_http_error', $error_message, array( 'raw_body' => $raw_body ) );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'recruittech_ai_bad_response',
				'The AI gateway returned a response that could not be read.',
				array( 'raw_body' => $raw_body )
			);
		}

		return self::extract_text_from_response( $decoded, $raw_body );
	}

	/**
	 * Pull the generated text out of the gateway's reply.
	 *
	 * The gateway's reply shape isn't officially documented, so several
	 * likely field names are checked, in the order they're most likely to
	 * appear. "output_text" is the field this project's gateway actually
	 * uses in testing, so it's checked first.
	 *
	 * @param array  $decoded  Decoded JSON response.
	 * @param string $raw_body Raw response body, for debugging on failure.
	 * @return string|WP_Error
	 */
	protected static function extract_text_from_response( $decoded, $raw_body ) {
		$candidate_fields = array( 'output_text', 'response', 'output', 'content', 'text', 'message' );

		foreach ( $candidate_fields as $field ) {
			if ( ! empty( $decoded[ $field ] ) && is_string( $decoded[ $field ] ) ) {
				return $decoded[ $field ];
			}
		}

		// Some gateways nest the text one level deeper, e.g.
		// {"content":[{"type":"text","text":"..."}]}.
		if ( ! empty( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
			$text = '';
			foreach ( $decoded['content'] as $block ) {
				if ( is_array( $block ) && ! empty( $block['text'] ) && is_string( $block['text'] ) ) {
					$text .= $block['text'];
				}
			}
			if ( '' !== $text ) {
				return $text;
			}
		}

		return new WP_Error(
			'recruittech_ai_empty_response',
			'The AI gateway responded, but no recognizable text field was found in the reply.',
			array( 'raw_body' => $raw_body )
		);
	}
}
