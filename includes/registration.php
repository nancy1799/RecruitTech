<?php
/**
 * RecruitTech User Registration
 * Handles user registration form processing and user creation
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process user registration form submission
 */
function recruittech_process_registration() {
	// Check if form was submitted
	if ( ! isset( $_POST['recruittech_register_nonce'] ) ) {
		return;
	}

	// Verify nonce for security
	if ( ! wp_verify_nonce( $_POST['recruittech_register_nonce'], 'recruittech_register_action' ) ) {
		return;
	}

	// Initialize errors array
	$errors = array();

	// Sanitize and validate inputs
	$full_name             = isset( $_POST['full_name'] ) ? sanitize_text_field( $_POST['full_name'] ) : '';
	$username              = isset( $_POST['username'] ) ? sanitize_user( $_POST['username'] ) : '';
	$email                 = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
	$password              = isset( $_POST['password'] ) ? $_POST['password'] : '';
	$confirm_password      = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '';
	$role                  = isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : '';

	// Validate required fields
	if ( empty( $full_name ) ) {
		$errors[] = 'Full Name is required.';
	}

	if ( empty( $username ) ) {
		$errors[] = 'Username is required.';
	}

	if ( empty( $email ) ) {
		$errors[] = 'Email is required.';
	}

	if ( empty( $password ) ) {
		$errors[] = 'Password is required.';
	}

	if ( empty( $confirm_password ) ) {
		$errors[] = 'Confirm Password is required.';
	}

	if ( empty( $role ) ) {
		$errors[] = 'Please select a role.';
	}

	// Validate email format
	if ( ! empty( $email ) && ! is_email( $email ) ) {
		$errors[] = 'Please enter a valid email address.';
	}

	// Check if username already exists
	if ( ! empty( $username ) && username_exists( $username ) ) {
		$errors[] = 'Username already exists. Please choose a different username.';
	}

	// Check if email already exists
	if ( ! empty( $email ) && email_exists( $email ) ) {
		$errors[] = 'Email already exists. Please use a different email address.';
	}

	// Verify password match
	if ( ! empty( $password ) && ! empty( $confirm_password ) && $password !== $confirm_password ) {
		$errors[] = 'Passwords do not match.';
	}

	// Validate password strength
	if ( ! empty( $password ) && ! recruittech_validate_registration_password( $password ) ) {
		$errors[] = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
	}

	// Validate role
	if ( ! empty( $role ) && ! in_array( $role, array( 'company', 'job_seeker' ), true ) ) {
		$errors[] = 'Invalid role selected.';
	}

	// If there are errors, store them and return
	if ( ! empty( $errors ) ) {
		set_transient( 'recruittech_registration_errors', $errors, 30 );
		return;
	}

	// Create the user
	$user_id = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $full_name,
		)
	);

	// Check if user creation was successful
	if ( is_wp_error( $user_id ) ) {
		$errors[] = 'An error occurred while creating the user. Please try again.';
		set_transient( 'recruittech_registration_errors', $errors, 30 );
		return;
	}

	// Assign custom role to the user
	$user = new WP_User( $user_id );
	$user->set_role( $role );

	if ( function_exists( 'recruittech_add_notification' ) ) {
		$welcome_message = 'company' === $role
			? 'Welcome to RecruitTech! Please complete your company profile so we can review and approve your account.'
			: 'Welcome to RecruitTech! Please complete your profile and upload your verification documents to start applying for jobs.';

		recruittech_add_notification( $user_id, 'Welcome to RecruitTech', $welcome_message, 'welcome', null, null );
	}

	// Set success message
	set_transient( 'recruittech_registration_success', 'Registration successful! You can now log in.', 30 );

	// Redirect to login page (Post/Redirect/Get pattern)
	wp_safe_redirect( home_url( '/test/login/' ) );
	exit;
}

// Hook registration processing to WordPress init
add_action( 'init', 'recruittech_process_registration' );

/**
 * Validate registration password strength.
 *
 * @param string $password Password string.
 * @return bool
 */
function recruittech_validate_registration_password( $password ) {
	if ( strlen( $password ) < 8 ) {
		return false;
	}

	if ( ! preg_match( '/[A-Z]/', $password ) ) {
		return false;
	}

	if ( ! preg_match( '/[a-z]/', $password ) ) {
		return false;
	}

	if ( ! preg_match( '/[0-9]/', $password ) ) {
		return false;
	}

	if ( ! preg_match( '/[!@#$%^&*()_+\-=]/', $password ) ) {
		return false;
	}

	return true;
}

/**
 * Display registration form
 */
function recruittech_display_registration_form() {
	// Check if user is already logged in
	if ( is_user_logged_in() ) {
		return 'You are already logged in.';
	}

	// Get errors from transient
	$errors = get_transient( 'recruittech_registration_errors' );
	if ( $errors ) {
		delete_transient( 'recruittech_registration_errors' );
	}

	// Get success message from transient
	$success = get_transient( 'recruittech_registration_success' );
	if ( $success ) {
		delete_transient( 'recruittech_registration_success' );
	}

	// Start output buffering to capture HTML
	ob_start();

	// Include registration form template
	include RECRUITTECH_PLUGIN_PATH . 'templates/registration-form.php';

	// Return buffered HTML
	return ob_get_clean();
}

/**
 * Register shortcode for registration form
 */
function recruittech_register_shortcode() {
	add_shortcode( 'recruittech_registration', 'recruittech_display_registration_form' );
}

add_action( 'init', 'recruittech_register_shortcode' );
