<?php
/**
 * RecruitTech AI AJAX Endpoints
 * Wires the "Analyze with AI" button on the Company Applications page to
 * the AI Agent (includes/ai/class-agent.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle the "Analyze with AI" AJAX request.
 */
function recruittech_ajax_analyze_candidate() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
	}

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'recruittech_ai_analyze' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ), 403 );
	}

	if ( ! function_exists( 'recruittech_is_company_user' ) || ! recruittech_is_company_user() ) {
		wp_send_json_error( array( 'message' => 'Only company accounts can run the AI Recruitment Assistant.' ), 403 );
	}

	$application_id = isset( $_POST['application_id'] ) ? absint( wp_unslash( $_POST['application_id'] ) ) : 0;
	if ( ! $application_id ) {
		wp_send_json_error( array( 'message' => 'Missing application.' ), 400 );
	}

	$company_profile = function_exists( 'recruittech_get_company_profile_by_user_id' )
		? recruittech_get_company_profile_by_user_id( get_current_user_id() )
		: null;

	if ( empty( $company_profile['id'] ) ) {
		wp_send_json_error( array( 'message' => 'Company profile not found.' ), 404 );
	}

	if ( function_exists( 'recruittech_subscription_can_use_ai_feature' ) && ! recruittech_subscription_can_use_ai_feature( get_current_user_id(), 'company', 'analyze' ) ) {
		wp_send_json_error( array( 'message' => 'This AI feature is not included in your current plan. Please upgrade your subscription.' ), 403 );
	}

	if ( ! class_exists( 'RecruitTech_AI_Client' ) || ! function_exists( 'recruittech_ai_analyze_application' ) ) {
		wp_send_json_error( array( 'message' => 'The AI module is not available.' ), 500 );
	}

	$result = recruittech_ai_analyze_application( $application_id, absint( $company_profile['id'] ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_recruittech_analyze_candidate', 'recruittech_ajax_analyze_candidate' );

/**
 * Handle the "Rank Top 10 with AI" AJAX request (company side, per job).
 */
function recruittech_ajax_rank_top_candidates() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
	}

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'recruittech_ai_analyze' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ), 403 );
	}

	if ( ! function_exists( 'recruittech_is_company_user' ) || ! recruittech_is_company_user() ) {
		wp_send_json_error( array( 'message' => 'Only company accounts can run the AI Recruitment Assistant.' ), 403 );
	}

	$job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
	if ( ! $job_id ) {
		wp_send_json_error( array( 'message' => 'Missing job.' ), 400 );
	}

	$company_profile = function_exists( 'recruittech_get_company_profile_by_user_id' )
		? recruittech_get_company_profile_by_user_id( get_current_user_id() )
		: null;

	if ( empty( $company_profile['id'] ) ) {
		wp_send_json_error( array( 'message' => 'Company profile not found.' ), 404 );
	}

	if ( function_exists( 'recruittech_subscription_can_use_ai_feature' ) && ! recruittech_subscription_can_use_ai_feature( get_current_user_id(), 'company', 'rank_top10' ) ) {
		wp_send_json_error( array( 'message' => 'This AI feature is not included in your current plan. Please upgrade your subscription.' ), 403 );
	}

	if ( ! function_exists( 'recruittech_ai_rank_top_candidates' ) ) {
		wp_send_json_error( array( 'message' => 'The AI module is not available.' ), 500 );
	}

	if ( ! function_exists( 'recruittech_is_job_owner' ) || ! recruittech_is_job_owner( $job_id, get_current_user_id() ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to rank candidates for this job.' ), 403 );
	}

	$result = recruittech_ai_rank_top_candidates( $job_id, absint( $company_profile['id'] ), 10 );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_recruittech_rank_top_candidates', 'recruittech_ajax_rank_top_candidates' );

/**
 * Handle the "Check My Fit" AJAX request (job seeker side, before applying).
 */
function recruittech_ajax_check_job_fit() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
	}

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'recruittech_ai_analyze' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ), 403 );
	}

	if ( ! function_exists( 'recruittech_is_job_seeker_user' ) || ! recruittech_is_job_seeker_user() ) {
		wp_send_json_error( array( 'message' => 'Only job seeker accounts can use the AI fit check.' ), 403 );
	}

	$job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
	if ( ! $job_id ) {
		wp_send_json_error( array( 'message' => 'Missing job.' ), 400 );
	}

	if ( function_exists( 'recruittech_subscription_can_use_ai_feature' ) && ! recruittech_subscription_can_use_ai_feature( get_current_user_id(), 'job_seeker', 'check_fit' ) ) {
		wp_send_json_error( array( 'message' => 'This AI feature is not included in your current plan. Please upgrade your subscription.' ), 403 );
	}

	if ( ! function_exists( 'recruittech_ai_check_job_fit' ) ) {
		wp_send_json_error( array( 'message' => 'The AI module is not available.' ), 500 );
	}

	$result = recruittech_ai_check_job_fit( $job_id, get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_recruittech_check_job_fit', 'recruittech_ajax_check_job_fit' );
