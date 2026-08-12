<?php
/**
 * RecruitTech Company Documents Module
 * Lets a company upload the recruitment documents (hiring policies,
 * guidelines, internal requirements, interview manuals) that the AI Agent
 * retrieves from (via lightweight RAG) when analyzing a candidate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed document types, matching the project proposal.
 *
 * @return array<string,string>
 */
function recruittech_get_company_document_types() {
	return array(
		'Hiring Policy'                 => 'Hiring Policy',
		'Recruitment Guidelines'        => 'Recruitment Guidelines',
		'Internal Hiring Requirements'  => 'Internal Hiring Requirements',
		'Interview Manual'              => 'Interview Manual',
	);
}

/**
 * Validate a document upload's file extension.
 *
 * @param array $file $_FILES entry.
 * @return bool
 */
function recruittech_company_document_validate_upload( $file ) {
	if ( empty( $file ) || ! is_array( $file ) ) {
		return false;
	}

	$allowed_types = array(
		'pdf'  => 'application/pdf',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'txt'  => 'text/plain',
	);

	$file_name = isset( $file['name'] ) ? $file['name'] : '';
	$file_type = wp_check_filetype( $file_name, $allowed_types );

	return ! empty( $file_type['ext'] );
}

/**
 * Handle document upload/delete form submissions before the shortcode renders.
 */
function recruittech_handle_company_document_actions() {
	if ( ! is_singular( array( 'page', 'post' ) ) ) {
		return;
	}

	global $post;
	if ( empty( $post ) || ! has_shortcode( $post->post_content, 'recruittech_company_documents' ) ) {
		return;
	}

	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
		return;
	}

	if ( ! is_user_logged_in() || ! function_exists( 'recruittech_is_company_user' ) || ! recruittech_is_company_user() ) {
		return;
	}

	$company_profile = function_exists( 'recruittech_get_company_profile_by_user_id' )
		? recruittech_get_company_profile_by_user_id( get_current_user_id() )
		: null;

	if ( empty( $company_profile['id'] ) ) {
		return;
	}

	$company_id = absint( $company_profile['id'] );

	if ( isset( $_POST['recruittech_upload_document_submit'] ) ) {
		recruittech_process_company_document_upload( $company_id );
	} elseif ( isset( $_POST['recruittech_delete_document_submit'] ) ) {
		recruittech_process_company_document_delete( $company_id );
	}
}
add_action( 'template_redirect', 'recruittech_handle_company_document_actions' );

/**
 * Process a new document upload.
 *
 * @param int $company_id Company ID.
 */
function recruittech_process_company_document_upload( $company_id ) {
	$errors = array();

	if ( ! isset( $_POST['recruittech_company_documents_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['recruittech_company_documents_nonce'] ), 'recruittech_company_documents_action' ) ) {
		return;
	}

	$doc_type      = isset( $_POST['doc_type'] ) ? sanitize_text_field( wp_unslash( $_POST['doc_type'] ) ) : '';
	$allowed_types = recruittech_get_company_document_types();

	if ( ! array_key_exists( $doc_type, $allowed_types ) ) {
		$errors[] = 'Please choose a valid document type.';
	}

	if ( empty( $_FILES['document']['name'] ) ) {
		$errors[] = 'Please choose a file to upload.';
	} elseif ( ! recruittech_company_document_validate_upload( $_FILES['document'] ) ) {
		$errors[] = 'Documents must be a PDF, DOCX, or TXT file.';
	}

	if ( ! empty( $errors ) ) {
		set_transient( 'recruittech_company_documents_errors', $errors, 30 );
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$attachment_id = media_handle_upload( 'document', 0 );
	if ( is_wp_error( $attachment_id ) ) {
		set_transient( 'recruittech_company_documents_errors', array( 'Upload failed. Please try again.' ), 30 );
		return;
	}

	$file_url = esc_url_raw( wp_get_attachment_url( $attachment_id ) );
	if ( empty( $file_url ) ) {
		set_transient( 'recruittech_company_documents_errors', array( 'Upload failed. Please try again.' ), 30 );
		return;
	}

	// Extract the text right away so it's ready for the AI Agent's
	// lightweight RAG lookup the first time a candidate is analyzed.
	$extracted_text = class_exists( 'RecruitTech_Text_Extractor' )
		? RecruitTech_Text_Extractor::extract_from_url( $file_url )
		: '';

	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'recruitech_company_documents',
		array(
			'company_id'     => $company_id,
			'file_path'      => $file_url,
			'doc_type'       => $doc_type,
			'extracted_text' => $extracted_text,
			'uploaded_at'    => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s' )
	);

	set_transient( 'recruittech_company_documents_success', 'Document uploaded successfully.', 30 );
	wp_safe_redirect( recruittech_get_company_documents_page_url() );
	exit;
}

/**
 * Process a document deletion.
 *
 * @param int $company_id Company ID.
 */
function recruittech_process_company_document_delete( $company_id ) {
	$document_id = isset( $_POST['document_id'] ) ? absint( wp_unslash( $_POST['document_id'] ) ) : 0;
	$nonce       = isset( $_POST['recruittech_delete_document_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['recruittech_delete_document_nonce'] ) ) : '';

	if ( ! $document_id || ! wp_verify_nonce( $nonce, 'recruittech_delete_document_' . $document_id ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_company_documents';

	// Ownership check: only delete a document that belongs to this company.
	$wpdb->delete(
		$table,
		array(
			'id'         => $document_id,
			'company_id' => $company_id,
		),
		array( '%d', '%d' )
	);

	set_transient( 'recruittech_company_documents_success', 'Document removed.', 30 );
	wp_safe_redirect( recruittech_get_company_documents_page_url() );
	exit;
}

/**
 * Get the Company Documents page URL.
 *
 * @return string
 */
function recruittech_get_company_documents_page_url() {
	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'recruittech_company_documents' ) ) {
			return get_permalink( $page );
		}
	}

	return home_url( '/company-documents/' );
}

/**
 * Render the Company Documents shortcode.
 *
 * @return string
 */
function recruittech_company_documents_shortcode() {
	$access_denied = recruittech_require_company_access();
	if ( null !== $access_denied ) {
		return $access_denied;
	}

	$company_profile = function_exists( 'recruittech_get_company_profile_by_user_id' )
		? recruittech_get_company_profile_by_user_id( get_current_user_id() )
		: null;

	if ( empty( $company_profile['id'] ) ) {
		return '<p>Company profile not found.</p>';
	}

	$company_id = absint( $company_profile['id'] );

	$errors = get_transient( 'recruittech_company_documents_errors' );
	if ( $errors ) {
		delete_transient( 'recruittech_company_documents_errors' );
	}

	$success = get_transient( 'recruittech_company_documents_success' );
	if ( $success ) {
		delete_transient( 'recruittech_company_documents_success' );
	}

	global $wpdb;
	$documents = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, file_path, doc_type, uploaded_at FROM {$wpdb->prefix}recruitech_company_documents WHERE company_id = %d ORDER BY uploaded_at DESC",
			$company_id
		),
		ARRAY_A
	);

	$doc_types = recruittech_get_company_document_types();

	ob_start();
	include RECRUITTECH_PLUGIN_PATH . 'templates/company-documents.php';
	return ob_get_clean();
}

/**
 * Register the shortcode.
 */
function recruittech_register_company_documents_shortcode() {
	add_shortcode( 'recruittech_company_documents', 'recruittech_company_documents_shortcode' );
}
add_action( 'init', 'recruittech_register_company_documents_shortcode' );

/**
 * Self-heal: make sure the Company Documents page exists even on sites
 * where the plugin was activated before this feature was added.
 */
function recruittech_ensure_company_documents_page() {
	if ( ! function_exists( 'get_page_by_path' ) || ! function_exists( 'wp_insert_post' ) ) {
		return;
	}

	$existing = get_page_by_path( 'company-documents' );
	if ( $existing instanceof WP_Post ) {
		return;
	}

	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);
	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'recruittech_company_documents' ) ) {
			return;
		}
	}

	wp_insert_post(
		array(
			'post_title'   => 'Company Documents',
			'post_name'    => 'company-documents',
			'post_content' => '[recruittech_company_documents]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
}
add_action( 'init', 'recruittech_ensure_company_documents_page' );
