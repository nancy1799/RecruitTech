<?php
/**
 * RecruitTech Database Setup
 * Creates all custom database tables for the plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create all RecruitTech database tables
 * Called on plugin activation
 */
function recruittech_ensure_job_seeker_verification_columns() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_job_seekers';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
		return;
	}

	$verification_column = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'verification_status'" );
	if ( empty( $verification_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN verification_status ENUM('pending','approved','rejected') DEFAULT 'pending'" );
	}

	$updated_at_column = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'updated_at'" );
	if ( empty( $updated_at_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP" );
	}

	$profile_photo_column = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'profile_photo'" );
	if ( empty( $profile_photo_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN profile_photo VARCHAR(255) NULL" );
	}

	$front_id_photo_column = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'front_id_photo'" );
	if ( empty( $front_id_photo_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN front_id_photo VARCHAR(255) NULL" );
	}

	$back_id_photo_column = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'back_id_photo'" );
	if ( empty( $back_id_photo_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN back_id_photo VARCHAR(255) NULL" );
	}

	$selfie_with_id_photo_column = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'selfie_with_id_photo'" );
	if ( empty( $selfie_with_id_photo_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN selfie_with_id_photo VARCHAR(255) NULL" );
	}
}

function recruittech_ensure_jobs_table_columns() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_jobs';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
		return;
	}

	$columns = array(
		'company_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
		'job_title' => "VARCHAR(255) NOT NULL DEFAULT ''",
		'job_category' => "VARCHAR(255) NULL",
		'job_type' => "VARCHAR(255) NULL",
		'required_skills' => "TEXT NULL",
		'salary' => "VARCHAR(255) NULL",
		'location' => "VARCHAR(255) NULL",
		'benefits' => "TEXT NULL",
		'deadline' => "DATE NULL",
		'updated_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
	);

	foreach ( $columns as $column => $definition ) {
		$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", $column ) );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN {$column} {$definition}" );
		}
	}
}

add_action( 'init', 'recruittech_ensure_job_seeker_verification_columns' );

/**
 * Ensure applications table has all required columns for existing installations.
 */
function recruittech_ensure_applications_table_columns() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_applications';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
		return;
	}

	// Add cover_letter column if it doesn't exist
	$cover_letter_column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'cover_letter' ) );
	if ( empty( $cover_letter_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN cover_letter TEXT NULL" );
	}

	// Add updated_at column if it doesn't exist
	$updated_at_column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'updated_at' ) );
	if ( empty( $updated_at_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" );
	}

	// Add ai_input_hash column if it doesn't exist. Used to detect whether the
	// job, the candidate's CV, or the company's documents changed since the
	// last AI analysis, so unchanged applications can reuse the cached result
	// instead of calling the AI gateway again.
	$ai_input_hash_column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'ai_input_hash' ) );
	if ( empty( $ai_input_hash_column ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN ai_input_hash VARCHAR(64) NULL" );
	}
}

add_action( 'init', 'recruittech_ensure_applications_table_columns' );

/**
 * Ensure the job_fit_checks table exists (pre-application "Check My Fit"
 * cache for job seekers). Created here, rather than only in
 * recruittech_create_tables(), so it also appears on sites where the plugin
 * was activated before this feature was added, without needing reactivation.
 */
function recruittech_ensure_job_fit_checks_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_job_fit_checks';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
		return;
	}

	$charset_collate = $wpdb->get_charset_collate();
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			job_id BIGINT UNSIGNED NOT NULL,
			job_seeker_id BIGINT UNSIGNED NOT NULL,
			input_hash VARCHAR(64) NOT NULL,
			match_score INT NULL,
			analysis_json TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY job_seeker_pair (job_id, job_seeker_id)
		) $charset_collate;"
	);
}

add_action( 'init', 'recruittech_ensure_job_fit_checks_table' );

/**
 * Ensure the three subscription tables exist (subscription_plans,
 * user_subscriptions, subscription_transactions). Created here, rather than
 * only in recruittech_create_tables(), so they also appear on sites where
 * the plugin was already active before this module was added, without
 * needing reactivation.
 */
function recruittech_ensure_subscription_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix . 'recruitech_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = array();

	// subscription_plans table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}subscription_plans (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		account_type ENUM('company','job_seeker') NOT NULL,
		plan_name VARCHAR(255) NOT NULL,
		duration_days INT UNSIGNED NOT NULL DEFAULT 30,
		price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		usage_limit INT UNSIGNED NOT NULL DEFAULT 0,
		ai_features VARCHAR(255) NULL,
		status ENUM('active','inactive') DEFAULT 'active',
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		KEY account_type (account_type)
	) $charset_collate;";

	// user_subscriptions table (one row per subscription period; renewals
	// insert a new row rather than updating an existing one, so the table
	// also acts as the subscription history log for each user).
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}user_subscriptions (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		account_type ENUM('company','job_seeker') NOT NULL,
		plan_id BIGINT UNSIGNED NULL,
		plan_name_snapshot VARCHAR(255) NOT NULL,
		usage_limit_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
		ai_features_snapshot VARCHAR(255) NULL,
		usage_count INT UNSIGNED NOT NULL DEFAULT 0,
		status ENUM('pending','active','expired','cancelled') DEFAULT 'pending',
		starts_at DATETIME NULL,
		expires_at DATETIME NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		KEY user_id (user_id),
		KEY status (status)
	) $charset_collate;";

	// subscription_transactions table (payment log, success or failed)
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}subscription_transactions (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		subscription_id BIGINT UNSIGNED NULL,
		plan_id BIGINT UNSIGNED NOT NULL,
		gateway VARCHAR(50) NOT NULL DEFAULT 'paymob',
		gateway_order_id VARCHAR(255) NULL,
		gateway_transaction_id VARCHAR(255) NULL,
		amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		currency VARCHAR(10) NOT NULL DEFAULT 'EGP',
		status ENUM('pending','success','failed') DEFAULT 'pending',
		raw_response TEXT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		KEY user_id (user_id),
		KEY subscription_id (subscription_id)
	) $charset_collate;";

	foreach ( $sql as $statement ) {
		dbDelta( $statement );
	}
}

add_action( 'init', 'recruittech_ensure_subscription_tables' );

function recruittech_create_tables() {
	global $wpdb;

	// Get the table name prefix
	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix . 'recruitech_';

	// SQL statements for all tables
	$sql = array();

	// companies table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}companies (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		company_name VARCHAR(255) NOT NULL,
		description TEXT,
		website VARCHAR(255) NULL,
		logo_url VARCHAR(255),
		commercial_register_file VARCHAR(255) NOT NULL,
		verification_status ENUM('pending','approved','rejected') DEFAULT 'pending',
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		KEY user_id (user_id)
	) $charset_collate;";

	// company_documents table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}company_documents (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		company_id BIGINT UNSIGNED NOT NULL,
		file_path VARCHAR(255) NOT NULL,
		doc_type VARCHAR(255),
		extracted_text TEXT,
		uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		KEY company_id (company_id)
	) $charset_collate;";

	// jobs table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}jobs (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		company_id BIGINT UNSIGNED NOT NULL,
		company_name VARCHAR(255) NOT NULL,
		job_title VARCHAR(255) NOT NULL,
		job_category VARCHAR(255),
		job_type VARCHAR(255),
		experience_level VARCHAR(255),
		required_skills TEXT,
		salary VARCHAR(255),
		location VARCHAR(255),
		description TEXT,
		requirements TEXT,
		benefits TEXT,
		deadline DATE,
		status ENUM('Draft', 'Published', 'Closed', 'Deleted') DEFAULT 'Draft',
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		KEY company_id (company_id)
	) $charset_collate;";

	// job_seekers table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}job_seekers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(255),
    summary TEXT,
    skills TEXT,
    experience TEXT,
    profile_photo VARCHAR(255) NULL,
    front_id_photo VARCHAR(255) NULL,
    back_id_photo VARCHAR(255) NULL,
    selfie_with_id_photo VARCHAR(255) NULL,
    verification_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY user_id (user_id)
	) $charset_collate;";

	// cvs table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}cvs (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		job_seeker_id BIGINT UNSIGNED NOT NULL,
		file_path VARCHAR(255) NOT NULL,
		extracted_text TEXT,
		uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		KEY job_seeker_id (job_seeker_id)
	) $charset_collate;";

	// applications table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}applications (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		job_id BIGINT UNSIGNED NOT NULL,
		job_seeker_id BIGINT UNSIGNED NOT NULL,
		status ENUM('Pending', 'Reviewed', 'Accepted', 'Rejected') DEFAULT 'Pending',
		cover_letter TEXT NULL,
		match_score VARCHAR(255),
		ai_feedback TEXT,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		KEY job_id (job_id),
		KEY job_seeker_id (job_seeker_id)
	) $charset_collate;";

	// notifications table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}notifications (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		title VARCHAR(255) NOT NULL,
		message TEXT NOT NULL,
		type VARCHAR(50) NOT NULL,
		related_id BIGINT UNSIGNED NULL,
		is_read TINYINT(1) DEFAULT 0,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		KEY user_id (user_id)
	) $charset_collate;";

	// ai_interview_questions table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}ai_interview_questions (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		application_id BIGINT UNSIGNED NOT NULL,
		question_text TEXT,
		KEY application_id (application_id)
	) $charset_collate;";

	// ai_analysis_log table
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}ai_analysis_log (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		application_id BIGINT UNSIGNED NOT NULL,
		prompt_sent TEXT,
		response_received TEXT,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		KEY application_id (application_id)
	) $charset_collate;";

	// job_fit_checks table (pre-application "Check My Fit" cache for job seekers)
	$sql[] = "CREATE TABLE IF NOT EXISTS {$prefix}job_fit_checks (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		job_id BIGINT UNSIGNED NOT NULL,
		job_seeker_id BIGINT UNSIGNED NOT NULL,
		input_hash VARCHAR(64) NOT NULL,
		match_score INT NULL,
		analysis_json TEXT,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		UNIQUE KEY job_seeker_pair (job_id, job_seeker_id)
	) $charset_collate;";

	// Execute all SQL statements
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( $sql as $statement ) {
		dbDelta( $statement );
	}

	recruittech_ensure_job_seeker_verification_columns();
	recruittech_ensure_jobs_table_columns();
	recruittech_ensure_applications_table_columns();
	recruittech_ensure_subscription_tables();
	if ( function_exists( 'recruittech_ensure_company_applications_page' ) ) {
		recruittech_ensure_company_applications_page();
	}
	if ( function_exists( 'recruittech_ensure_my_applications_page' ) ) {
		recruittech_ensure_my_applications_page();
	}
	if ( function_exists( 'recruittech_ensure_notifications_page' ) ) {
		recruittech_ensure_notifications_page();
	}
}
