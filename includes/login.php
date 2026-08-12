<?php
/**
 * RecruitTech User Login
 * Handles user login form processing and authentication
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process user login form submission
 */
function recruittech_process_login() {
	// Check if form was submitted
	if ( ! isset( $_POST['recruittech_login_nonce'] ) ) {
		return;
	}

	// Verify nonce for security
	if ( ! wp_verify_nonce( $_POST['recruittech_login_nonce'], 'recruittech_login_action' ) ) {
		return;
	}

	// Initialize errors array
	$errors = array();

	// Sanitize and validate inputs
	$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( $_POST['user_login'] ) : '';
	$password   = isset( $_POST['password'] ) ? $_POST['password'] : '';
	$remember   = isset( $_POST['remember'] ) ? true : false;

	// Validate required fields
	if ( empty( $user_login ) ) {
		$errors[] = 'Username or Email is required.';
	}

	if ( empty( $password ) ) {
		$errors[] = 'Password is required.';
	}

	// If there are validation errors, store them and return
	if ( ! empty( $errors ) ) {
		set_transient( 'recruittech_login_errors', $errors, 30 );
		return;
	}

	// Detect if user entered an email or username
	$username = $user_login;
	if ( is_email( $user_login ) ) {
		// User entered an email - get the corresponding username
		$user_obj = get_user_by( 'email', $user_login );
		if ( ! $user_obj ) {
			$errors[] = 'Invalid username or password.';
			set_transient( 'recruittech_login_errors', $errors, 30 );
			return;
		}
		$username = $user_obj->user_login;
	}

	// Prepare credentials for wp_signon()
	$credentials = array(
		'user_login'    => $username,
		'user_password' => $password,
		'remember'      => $remember,
	);

	// Attempt to authenticate the user using wp_signon()
	$user = wp_signon( $credentials, false );

	// Check if authentication failed
	if ( is_wp_error( $user ) ) {
		$errors[] = 'Invalid username or password.';
		set_transient( 'recruittech_login_errors', $errors, 30 );
		return;
	}

	// Get user roles
	$user_roles = $user->roles;

	// Determine redirect URL based on user role
	$redirect_url = wp_get_referer();

	if ( in_array( 'company', $user_roles, true ) ) {
		// Redirect company users to company dashboard
		$redirect_url = home_url( '/company-dashboard/' );
	} elseif ( in_array( 'job_seeker', $user_roles, true ) ) {
		// Redirect job seeker users to job seeker dashboard
		$redirect_url = home_url( '/job-seeker-dashboard/' );
	}

	// Redirect to appropriate dashboard
	wp_safe_redirect( $redirect_url );
	exit;
}

// Hook login processing to WordPress init
add_action( 'init', 'recruittech_process_login' );

/**
 * Display login form
 */
function recruittech_display_login_form() {
	// Check if user is already logged in
	if ( is_user_logged_in() ) {
		return 'You are already logged in.';
	}

	// Get errors from transient
	$errors = get_transient( 'recruittech_login_errors' );
	if ( $errors ) {
		delete_transient( 'recruittech_login_errors' );
	}

	// Get success message from transient
	$success = get_transient( 'recruittech_registration_success' );
	if ( $success ) {
		delete_transient( 'recruittech_registration_success' );
	}

	// Start output buffering to capture HTML
	ob_start();

	// Include login form template
	include RECRUITTECH_PLUGIN_PATH . 'templates/login-form.php';

	// Return buffered HTML
	return ob_get_clean();
}

/**
 * Register shortcode for login form
 */
function recruittech_register_login_shortcode() {
	add_shortcode( 'recruittech_login', 'recruittech_display_login_form' );
}

add_action( 'init', 'recruittech_register_login_shortcode' );
