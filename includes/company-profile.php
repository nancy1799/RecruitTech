<?php
/**
 * RecruitTech Company Profile Module
 * Handles company profile form display and submission.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the current company profile by user ID.
 *
 * @param int $user_id User ID.
 * @return array|null
 */
function recruittech_get_company_profile_by_user_id( $user_id ) {
    if ( empty( $user_id ) ) {
        return null;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'recruitech_companies';

    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d", $user_id ), ARRAY_A );
}

/**
 * Get the current page URL for redirect.
 *
 * @return string
 */
function recruittech_company_profile_get_current_page_url() {
    if ( ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
        $path   = wp_unslash( $_SERVER['REQUEST_URI'] );

        return esc_url_raw( $scheme . $host . $path );
    }

    return home_url();
}

/**
 * Check if the current user has the company role.
 *
 * @return bool
 */
function recruittech_company_profile_user_is_company() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $current_user = wp_get_current_user();
    return in_array( 'company', (array) $current_user->roles, true );
}

/**
 * Redirect users who cannot access the company profile page.
 */
function recruittech_company_profile_require_company_access() {
    if ( ! is_user_logged_in() ) {
        return recruittech_send_access_denied(
            recruittech_get_user_dashboard_url(),
            '<p>Please log in to access this area.</p>',
            'company_area_only'
        );
    }

    $current_user = wp_get_current_user();
    $roles = (array) $current_user->roles;

    if ( in_array( 'job_seeker', $roles, true ) ) {
        return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_area_only' );
    }

    if ( ! in_array( 'company', $roles, true ) ) {
        return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_area_only' );
    }
}

/**
 * Handle company profile form submission before the shortcode renders.
 */
function recruittech_handle_company_profile_form_submission() {
    if ( ! is_singular( array( 'page', 'post' ) ) ) {
        return;
    }

    $company_profile_page = get_page_by_path( 'company-profile' );
    if ( ! $company_profile_page || (int) get_queried_object_id() !== (int) $company_profile_page->ID ) {
        return;
    }

    $access_denied = recruittech_company_profile_require_company_access();
    if ( null !== $access_denied ) {
        return;
    }

    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
        return;
    }

    if ( ! isset( $_POST['recruittech_company_profile_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( wp_unslash( $_POST['recruittech_company_profile_nonce'] ), 'recruittech_company_profile_action' ) ) {
        return;
    }

    recruittech_process_company_profile_form();
}

add_action( 'template_redirect', 'recruittech_handle_company_profile_form_submission' );

/**
 * Process company profile form submission.
 */
function recruittech_process_company_profile_form() {
    $errors = array();
    $user_id = get_current_user_id();
    $existing_profile = recruittech_get_company_profile_by_user_id( $user_id );
    $is_editing = ! empty( $existing_profile );

    $company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
    $description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
    $website      = isset( $_POST['website'] ) ? esc_url_raw( trim( wp_unslash( $_POST['website'] ) ) ) : '';

    if ( empty( $company_name ) ) {
        $errors[] = 'Company Name is required.';
    }

    if ( empty( $description ) ) {
        $errors[] = 'Company Description is required.';
    }

    if ( ! empty( $website ) && ! filter_var( $website, FILTER_VALIDATE_URL ) ) {
        $errors[] = 'Please enter a valid website URL.';
    }

    if ( ! $is_editing && empty( $_FILES['commercial_registration']['name'] ) ) {
        $errors[] = 'Commercial Registration file is required.';
    }

    if ( ! empty( $_FILES['logo']['name'] ) && ! recruittech_company_profile_validate_upload( $_FILES['logo'] ) ) {
        $errors[] = 'Company Logo must be a PDF, JPG, JPEG, or PNG file.';
    }

    if ( ! empty( $_FILES['commercial_registration']['name'] ) && ! recruittech_company_profile_validate_upload( $_FILES['commercial_registration'] ) ) {
        $errors[] = 'Commercial Registration must be a PDF, JPG, JPEG, or PNG file.';
    }

    if ( ! empty( $errors ) ) {
        set_transient( 'recruittech_company_profile_errors', $errors, 30 );
        set_transient( 'recruittech_company_profile_form_data', array(
            'company_name' => $company_name,
            'description'  => $description,
            'website'      => $website,
        ), 30 );
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $logo_url = $is_editing && ! empty( $existing_profile['logo_url'] ) ? $existing_profile['logo_url'] : '';
    if ( ! empty( $_FILES['logo']['name'] ) ) {
        $new_logo_url = recruittech_company_profile_handle_upload( 'logo', 'Company Logo', $errors );
        if ( ! empty( $new_logo_url ) ) {
            $logo_url = $new_logo_url;
        }
    }

    $commercial_register_url = $is_editing && ! empty( $existing_profile['commercial_register_file'] ) ? $existing_profile['commercial_register_file'] : '';
    if ( ! empty( $_FILES['commercial_registration']['name'] ) ) {
        $new_commercial_url = recruittech_company_profile_handle_upload( 'commercial_registration', 'Commercial Registration', $errors );
        if ( ! empty( $new_commercial_url ) ) {
            $commercial_register_url = $new_commercial_url;
        }
    }

    if ( ! empty( $errors ) ) {
        set_transient( 'recruittech_company_profile_errors', $errors, 30 );
        set_transient( 'recruittech_company_profile_form_data', array(
            'company_name' => $company_name,
            'description'  => $description,
            'website'      => $website,
        ), 30 );
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'recruitech_companies';

    $previous_verification_status = $is_editing && isset( $existing_profile['verification_status'] )
        ? sanitize_text_field( $existing_profile['verification_status'] )
        : '';

    $profile_data = array(
        'user_id'                  => $user_id,
        'company_name'             => $company_name,
        'description'              => $description,
        'website'                  => $website,
        'logo_url'                 => $logo_url,
        'commercial_register_file' => $commercial_register_url,
        'verification_status'      => 'pending',
    );

    $formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

    if ( $is_editing ) {
        $wpdb->update( $table_name, $profile_data, array( 'id' => $existing_profile['id'] ), $formats, array( '%d' ) );
    } else {
        $wpdb->insert( $table_name, $profile_data, $formats );
    }

    if ( $is_editing
        && in_array( $previous_verification_status, array( 'approved', 'rejected' ), true )
        && function_exists( 'recruittech_add_notification' )
    ) {
        recruittech_add_notification(
            $user_id,
            'Verification Status Reset to Pending',
            'You updated your company profile ("' . $company_name . '"), so it needs to be reviewed again. Your verification status has been set to Pending until an admin re-approves it.',
            'verification',
            null,
            null
        );
    }

    set_transient( 'recruittech_company_profile_success', 'Your company profile has been saved successfully.', 30 );
    wp_safe_redirect( recruittech_company_profile_get_current_page_url() );
    exit;
}

/**
 * Validate file extension for company profile uploads.
 *
 * @param array $file Uploaded file data.
 * @return bool
 */
function recruittech_company_profile_validate_upload( $file ) {
    if ( empty( $file ) || ! is_array( $file ) ) {
        return false;
    }

    $allowed_types = array(
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    );

    $file_name = isset( $file['name'] ) ? $file['name'] : '';
    $file_type = wp_check_filetype( $file_name, $allowed_types );

    return ! empty( $file_type['ext'] );
}

/**
 * Handle a file upload and store it in the media library.
 *
 * @param string $field_name Field name in the $_FILES array.
 * @param string $label Label used for error messages.
 * @param array  $errors Reference to errors array.
 * @return string Uploaded file URL.
 */
function recruittech_company_profile_handle_upload( $field_name, $label, &$errors ) {
    if ( empty( $_FILES[ $field_name ]['name'] ) ) {
        return '';
    }

    $attachment_id = media_handle_upload( $field_name, 0 );
    if ( is_wp_error( $attachment_id ) ) {
        $errors[] = sprintf( '%s upload failed. Please try again.', $label );
        return '';
    }

    $attachment_url = wp_get_attachment_url( $attachment_id );
    if ( empty( $attachment_url ) ) {
        $errors[] = sprintf( '%s upload failed. Please try again.', $label );
        return '';
    }

    return esc_url_raw( $attachment_url );
}

/**
 * Display the company profile form shortcode.
 *
 * @return string HTML output.
 */
function recruittech_company_profile_shortcode() {
    $errors = get_transient( 'recruittech_company_profile_errors' );
    if ( $errors ) {
        delete_transient( 'recruittech_company_profile_errors' );
    }

    $success = get_transient( 'recruittech_company_profile_success' );
    if ( $success ) {
        delete_transient( 'recruittech_company_profile_success' );
    }

    $form_data = get_transient( 'recruittech_company_profile_form_data' );
    if ( $form_data ) {
        delete_transient( 'recruittech_company_profile_form_data' );
    }

    $company_name = isset( $form_data['company_name'] ) ? $form_data['company_name'] : '';
    $description  = isset( $form_data['description'] ) ? $form_data['description'] : '';
    $website      = isset( $form_data['website'] ) ? $form_data['website'] : '';

    $current_profile = null;
    $verification_status = '';
    $logo_url = '';
    $commercial_register_file = '';
    $is_editing = false;

    if ( is_user_logged_in() ) {
        $current_profile = recruittech_get_company_profile_by_user_id( get_current_user_id() );
    }

    if ( ! empty( $current_profile ) ) {
        $is_editing = true;
        $verification_status = isset( $current_profile['verification_status'] ) ? $current_profile['verification_status'] : '';

        if ( empty( $company_name ) ) {
            $company_name = isset( $current_profile['company_name'] ) ? $current_profile['company_name'] : '';
        }
        if ( empty( $description ) ) {
            $description = isset( $current_profile['description'] ) ? $current_profile['description'] : '';
        }
        if ( empty( $website ) ) {
            $website = isset( $current_profile['website'] ) ? $current_profile['website'] : '';
        }

        $logo_url = isset( $current_profile['logo_url'] ) ? $current_profile['logo_url'] : '';
        $commercial_register_file = isset( $current_profile['commercial_register_file'] ) ? $current_profile['commercial_register_file'] : '';
    }

    ob_start();
    include RECRUITTECH_PLUGIN_PATH . 'templates/company-profile.php';
    return ob_get_clean();
}

/**
 * Register the company profile shortcode.
 */
function recruittech_register_company_profile_shortcode() {
    add_shortcode( 'recruittech_company_profile', 'recruittech_company_profile_shortcode' );
}

add_action( 'init', 'recruittech_register_company_profile_shortcode' );
