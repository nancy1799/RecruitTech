<?php
    /**
     * RecruitTech Job Seeker Profile Module
     * Handles job seeker profile database operations.
     */

    // Prevent direct access.
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Get a job seeker profile by user ID.
     *
     * @param int $user_id User ID.
     * @return array|null
     */
    function recruittech_get_job_seeker_by_user_id( $user_id ) {
        if ( empty( $user_id ) ) {
            return null;
        }

        recruittech_ensure_job_seeker_verification_columns();

        global $wpdb;
        $table_name = $wpdb->prefix . 'recruitech_job_seekers';

        $query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY id DESC LIMIT 1", absint( $user_id ) );
        return $wpdb->get_row( $query, ARRAY_A ) ?: null;
    }

    /**
     * Return the profile fields that can change the AI result.
     *
     * @return array
     */
    function recruittech_get_ai_relevant_profile_fields() {
        return array( 'full_name', 'summary', 'skills', 'experience', 'education', 'certifications', 'languages', 'job_title', 'years_of_experience', 'location', 'preferred_job' );
    }

    /**
     * Check whether any AI-relevant profile data changed.
     *
     * @param array|null $previous_profile Previous stored profile.
     * @param array|null $current_profile  New profile data.
     * @return bool
     */
    function recruittech_profile_has_ai_relevant_changes( $previous_profile, $current_profile ) {
        $previous_profile = is_array( $previous_profile ) ? $previous_profile : array();
        $current_profile  = is_array( $current_profile ) ? $current_profile : array();

        foreach ( recruittech_get_ai_relevant_profile_fields() as $field ) {
            if ( isset( $current_profile[ $field ] ) || isset( $previous_profile[ $field ] ) ) {
                if ( (string) ( $current_profile[ $field ] ?? '' ) !== (string) ( $previous_profile[ $field ] ?? '' ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Invalidate cached AI outputs for all applications and fit checks for a candidate.
     *
     * @param int $user_id Job seeker user ID.
     * @return void
     */
    function recruittech_invalidate_ai_cache_for_job_seeker( $user_id ) {
        if ( empty( $user_id ) ) {
            return;
        }

        global $wpdb;
        $user_id = absint( $user_id );

        $job_seekers_table = $wpdb->prefix . 'recruitech_job_seekers';
        $applications_table = $wpdb->prefix . 'recruitech_applications';
        $fit_checks_table = $wpdb->prefix . 'recruitech_job_fit_checks';

        $profile = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM {$job_seekers_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id ),
            ARRAY_A
        );

        if ( ! empty( $profile['id'] ) ) {
            $wpdb->delete( $fit_checks_table, array( 'job_seeker_id' => absint( $profile['id'] ) ), array( '%d' ) );
        }

        $wpdb->update(
            $applications_table,
            array(
                'ai_feedback'   => null,
                'ai_input_hash' => null,
            ),
            array( 'job_seeker_id' => $user_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Create or update a job seeker profile.
     *
     * @param array $data Profile data.
     * @return array|false
     */
    function recruittech_update_or_create_job_seeker_profile( $data ) {
        if ( empty( $data ) || ! is_array( $data ) ) {
            return false;
        }

        recruittech_ensure_job_seeker_verification_columns();

        global $wpdb;
        $table_name = $wpdb->prefix . 'recruitech_job_seekers';

        $user_id = isset( $data['user_id'] ) ? absint( wp_unslash( $data['user_id'] ) ) : 0;
        if ( empty( $user_id ) ) {
            return false;
        }

        $full_name  = isset( $data['full_name'] ) ? sanitize_text_field( wp_unslash( $data['full_name'] ) ) : '';
        $phone      = isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( $data['phone'] ) ) : '';
        $summary    = isset( $data['summary'] ) ? sanitize_textarea_field( wp_unslash( $data['summary'] ) ) : '';
        $skills     = isset( $data['skills'] ) ? sanitize_textarea_field( wp_unslash( $data['skills'] ) ) : '';
        $experience = isset( $data['experience'] ) ? sanitize_textarea_field( wp_unslash( $data['experience'] ) ) : '';
        $profile_photo = isset( $data['profile_photo'] ) ? esc_url_raw( wp_unslash( $data['profile_photo'] ) ) : '';
        $front_id_photo = isset( $data['front_id_photo'] ) ? esc_url_raw( wp_unslash( $data['front_id_photo'] ) ) : '';
        $back_id_photo = isset( $data['back_id_photo'] ) ? esc_url_raw( wp_unslash( $data['back_id_photo'] ) ) : '';
        $selfie_with_id_photo = isset( $data['selfie_with_id_photo'] ) ? esc_url_raw( wp_unslash( $data['selfie_with_id_photo'] ) ) : '';
        $verification_status = isset( $data['verification_status'] ) ? sanitize_text_field( wp_unslash( $data['verification_status'] ) ) : 'pending';
        if ( ! in_array( $verification_status, array( 'pending', 'approved', 'rejected' ), true ) ) {
            $verification_status = 'pending';
        }

        $profile_data = array(
            'user_id'             => $user_id,
            'full_name'           => $full_name,
            'phone'               => $phone,
            'summary'             => $summary,
            'skills'              => $skills,
            'experience'          => $experience,
            'profile_photo'       => $profile_photo,
            'front_id_photo'      => $front_id_photo,
            'back_id_photo'       => $back_id_photo,
            'selfie_with_id_photo'=> $selfie_with_id_photo,
            'verification_status' => $verification_status,
            'updated_at'          => current_time( 'mysql' ),
        );

        $existing_profiles = $wpdb->get_results(
            $wpdb->prepare( "SELECT id FROM {$table_name} WHERE user_id = %d ORDER BY id DESC", absint( $user_id ) ),
            ARRAY_A
        );

        $existing_profile = null;
        $ai_relevant_changed = false;

        if ( ! empty( $existing_profiles ) ) {
            $profile_id = absint( $existing_profiles[0]['id'] );
            $existing_profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $profile_id ), ARRAY_A ) ?: null;
            $ai_relevant_changed = recruittech_profile_has_ai_relevant_changes( $existing_profile, $profile_data );
            $format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
            $wpdb->update( $table_name, $profile_data, array( 'id' => $profile_id ), $format, array( '%d' ) );

            if ( count( $existing_profiles ) > 1 ) {
                foreach ( $existing_profiles as $duplicate_profile ) {
                    $duplicate_id = absint( $duplicate_profile['id'] );
                    if ( $duplicate_id !== $profile_id ) {
                        $wpdb->delete( $table_name, array( 'id' => $duplicate_id ), array( '%d' ) );
                    }
                }
            }

            $updated_profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $profile_id ), ARRAY_A );
            if ( $ai_relevant_changed || empty( $existing_profile ) ) {
                recruittech_invalidate_ai_cache_for_job_seeker( $user_id );
            }

            return $updated_profile;
        }

        $format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
        $inserted = $wpdb->insert( $table_name, $profile_data, $format );
        if ( ! $inserted ) {
            return false;
        }

        recruittech_invalidate_ai_cache_for_job_seeker( $user_id );

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", absint( $wpdb->insert_id ) ), ARRAY_A );
    }

    /**
     * Redirect users who are not allowed to access the job seeker profile page.
     */
    function recruittech_job_seeker_profile_require_access() {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( home_url( '/test/login/' ) );
            exit;
        }

        $current_user = wp_get_current_user();
        $roles = (array) $current_user->roles;

        if ( in_array( 'company', $roles, true ) ) {
            return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'job_seeker_area_only' );
        }

        if ( ! in_array( 'job_seeker', $roles, true ) ) {
            wp_safe_redirect( home_url( '/test/login/' ) );
            exit;
        }
    }

    /**
     * Shortcode callback for the job seeker profile form.
     *
     * @return string
     */
    function recruittech_job_seeker_profile_shortcode() {
        $access_denied = recruittech_job_seeker_profile_require_access();
        if ( null !== $access_denied ) {
            return $access_denied;
        }

        $template_path = dirname( __DIR__ ) . '/templates/job-seeker-profile-form.php';
        if ( ! file_exists( $template_path ) ) {
            return '<div class="notice notice-error">Job seeker profile template is missing.</div>';
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    add_shortcode( 'recruittech_job_seeker_profile', 'recruittech_job_seeker_profile_shortcode' );

    /**
     * Validate a CV upload before processing it.
     *
     * @param array $file Uploaded file data.
     * @return bool
     */
    function recruittech_validate_cv_upload( $file ) {
        if ( empty( $file ) || ! is_array( $file ) ) {
            return false;
        }

        if ( ! isset( $file['name'], $file['tmp_name'], $file['size'], $file['error'] ) || empty( $file['name'] ) ) {
            return false;
        }

        if ( UPLOAD_ERR_OK !== $file['error'] ) {
            return false;
        }

        $max_file_size = 5 * 1024 * 1024;
        if ( absint( $file['size'] ) > $max_file_size ) {
            return false;
        }

        $allowed_mimes = array(
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $file_type = wp_check_filetype( sanitize_file_name( $file['name'] ), $allowed_mimes );

        return ! empty( $file_type['ext'] ) && ! empty( $file_type['type'] );
    }

    /**
     * Handle a media upload for the profile module.
     *
     * @param string $field_name Uploaded field name.
     * @param array  $errors Reference to error array.
     * @param string $failure_message Error message to store on failure.
     * @return string
     */
    function recruittech_handle_media_upload( $field_name, &$errors, $failure_message ) {
        if ( empty( $_FILES[ $field_name ]['name'] ) ) {
            return '';
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( $field_name, 0 );
        if ( is_wp_error( $attachment_id ) ) {
            $errors[] = $failure_message;
            return '';
        }

        $attachment_url = wp_get_attachment_url( $attachment_id );
        if ( empty( $attachment_url ) ) {
            $errors[] = $failure_message;
            return '';
        }

        return esc_url_raw( $attachment_url );
    }

    /**
     * Handle a CV upload using the WordPress Media Library.
     *
     * @param string $field_name Uploaded field name.
     * @param array  $errors Reference to error array.
     * @return string
     */
    function recruittech_job_seeker_profile_handle_upload( $field_name, &$errors ) {
        return recruittech_handle_media_upload( $field_name, $errors, 'CV upload failed. Please try again.' );
    }

    /**
     * Validate a profile photo upload before processing it.
     *
     * @param array $file Uploaded file data.
     * @return bool
     */
    function recruittech_validate_identity_image_upload( $file ) {
        if ( empty( $file ) || ! is_array( $file ) ) {
            return false;
        }

        if ( ! isset( $file['name'], $file['tmp_name'], $file['size'], $file['error'] ) || empty( $file['name'] ) ) {
            return false;
        }

        if ( UPLOAD_ERR_OK !== $file['error'] ) {
            return false;
        }

        if ( absint( $file['size'] ) > 2 * 1024 * 1024 ) {
            return false;
        }

        $allowed_mimes = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        );

        $file_type = wp_check_filetype( sanitize_file_name( $file['name'] ), $allowed_mimes );
        if ( empty( $file_type['ext'] ) || empty( $file_type['type'] ) ) {
            return false;
        }

        if ( ! function_exists( 'getimagesize' ) ) {
            return false;
        }

        $image_info = @getimagesize( $file['tmp_name'] );
        if ( empty( $image_info ) || empty( $image_info['mime'] ) ) {
            return false;
        }

        return in_array( $image_info['mime'], array_values( $allowed_mimes ), true ) && $image_info['mime'] === $file_type['type'];
    }

    /**
     * Handle a profile photo or ID photo upload using the WordPress Media Library.
     *
     * @param string $field_name Uploaded field name.
     * @param array  $errors Reference to error array.
     * @return string
     */
    function recruittech_job_seeker_profile_photo_handle_upload( $field_name, &$errors ) {
        return recruittech_handle_media_upload( $field_name, $errors, 'Identity document upload failed. Please try again.' );
    }

    /**
     * Save a CV upload for the current job seeker.
     *
     * @param int    $job_seeker_id Job seeker record ID.
     * @param string $file_url Uploaded file URL.
     * @return bool
     */
    function recruittech_save_cv_file( $job_seeker_id, $file_url ) {
        if ( empty( $job_seeker_id ) || empty( $file_url ) || ! is_string( $file_url ) ) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'recruitech_cvs';

        $file_path = sanitize_text_field( wp_unslash( $file_url ) );
        if ( empty( $file_path ) ) {
            return false;
        }

        $job_seeker_id = absint( $job_seeker_id );
        $existing_cv = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM {$table_name} WHERE job_seeker_id = %d ORDER BY id DESC LIMIT 1", $job_seeker_id ),
            ARRAY_A
        );

        $extracted_text = '';
        if ( class_exists( 'RecruitTech_Text_Extractor' ) ) {
            $extracted_text = RecruitTech_Text_Extractor::extract_from_url( $file_path );
        }

        $cv_data = array(
            'file_path'      => $file_path,
            'uploaded_at'    => current_time( 'mysql' ),
            'extracted_text' => $extracted_text,
        );
        $cv_formats = array( '%s', '%s', '%s' );

        if ( ! empty( $existing_cv['id'] ) ) {
            $updated = $wpdb->update(
                $table_name,
                $cv_data,
                array( 'id' => absint( $existing_cv['id'] ) ),
                $cv_formats,
                array( '%d' )
            );

            $wpdb->query(
                $wpdb->prepare( "DELETE FROM {$table_name} WHERE job_seeker_id = %d AND id != %d", $job_seeker_id, absint( $existing_cv['id'] ) )
            );

            return false !== $updated;
        }

        $data = array(
            'job_seeker_id' => $job_seeker_id,
            'file_path'     => $file_path,
            'uploaded_at'   => current_time( 'mysql' ),
            'extracted_text' => $extracted_text,
        );

        $format = array( '%d', '%s', '%s', '%s' );

        return $wpdb->insert( $table_name, $data, $format );
    }

    /**
     * Handle job seeker profile form submission.
     */
    function recruittech_handle_job_seeker_profile_submission() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( ! isset( $_POST['recruittech_js_profile_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['recruittech_js_profile_nonce'] ), 'recruittech_js_profile_action' ) ) {
            return;
        }

        recruittech_job_seeker_profile_require_access();

        $user_id = get_current_user_id();
        $errors = array();
        $existing_profile = recruittech_get_job_seeker_by_user_id( $user_id );

        $full_name = isset( $_POST['full_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) ) : '';
        $phone = isset( $_POST['phone'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
        $summary = isset( $_POST['summary'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['summary'] ) ) ) : '';
        $skills = isset( $_POST['skills'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['skills'] ) ) ) : '';
        $experience = isset( $_POST['experience'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['experience'] ) ) ) : '';

        $data = array(
            'user_id'     => $user_id,
            'full_name'   => $full_name,
            'phone'       => $phone,
            'summary'     => $summary,
            'skills'      => $skills,
            'experience'  => $experience,
        );

        $profile_photo_url = '';
        if ( ! empty( $_FILES['profile_photo']['name'] ) ) {
            if ( ! recruittech_validate_identity_image_upload( $_FILES['profile_photo'] ) ) {
                $errors[] = 'Please upload a valid profile photo.';
            } else {
                $profile_photo_url = recruittech_job_seeker_profile_photo_handle_upload( 'profile_photo', $errors );
            }
        }

        $front_id_photo_url = '';
        if ( ! empty( $_FILES['front_id_photo']['name'] ) ) {
            if ( ! recruittech_validate_identity_image_upload( $_FILES['front_id_photo'] ) ) {
                $errors[] = 'Please upload a valid front ID photo.';
            } else {
                $front_id_photo_url = recruittech_job_seeker_profile_photo_handle_upload( 'front_id_photo', $errors );
            }
        }

        $back_id_photo_url = '';
        if ( ! empty( $_FILES['back_id_photo']['name'] ) ) {
            if ( ! recruittech_validate_identity_image_upload( $_FILES['back_id_photo'] ) ) {
                $errors[] = 'Please upload a valid back ID photo.';
            } else {
                $back_id_photo_url = recruittech_job_seeker_profile_photo_handle_upload( 'back_id_photo', $errors );
            }
        }

        $selfie_with_id_photo_url = '';
        if ( ! empty( $_FILES['selfie_with_id_photo']['name'] ) ) {
            if ( ! recruittech_validate_identity_image_upload( $_FILES['selfie_with_id_photo'] ) ) {
                $errors[] = 'Please upload a valid selfie with ID photo.';
            } else {
                $selfie_with_id_photo_url = recruittech_job_seeker_profile_photo_handle_upload( 'selfie_with_id_photo', $errors );
            }
        }

        if ( empty( $full_name ) || ! preg_match( '/^[A-Za-z\s\'-]{3,100}$/', $full_name ) ) {
            $errors[] = 'Please enter a valid full name.';
        }

        if ( empty( $phone ) || ! preg_match( '/^\d{10,15}$/', $phone ) ) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if ( strlen( $summary ) < 50 || strlen( $summary ) > 2000 ) {
            $errors[] = 'Professional summary must be between 50 and 2000 characters.';
        }

        if ( strlen( $skills ) < 5 || strlen( $skills ) > 500 ) {
            $errors[] = 'Please enter your skills.';
        }

        if ( strlen( $experience ) < 20 || strlen( $experience ) > 3000 ) {
            $errors[] = 'Please enter your work experience.';
        }

        if ( empty( $existing_profile ) ) {
            if ( empty( $_FILES['cv_upload']['name'] ) ) {
                $errors[] = 'Please upload a valid CV (PDF, DOC or DOCX) not exceeding 5 MB.';
            }

            if ( empty( $_FILES['profile_photo']['name'] ) ) {
                $errors[] = 'Please upload a valid profile photo.';
            }

            if ( empty( $_FILES['front_id_photo']['name'] ) ) {
                $errors[] = 'Front ID photo is required for first-time profile submission.';
            }

            if ( empty( $_FILES['back_id_photo']['name'] ) ) {
                $errors[] = 'Back ID photo is required for first-time profile submission.';
            }

            if ( empty( $_FILES['selfie_with_id_photo']['name'] ) ) {
                $errors[] = 'Selfie with ID photo is required for first-time profile submission.';
            }
        }

        if ( ! empty( $_FILES['cv_upload']['name'] ) && ! recruittech_validate_cv_upload( $_FILES['cv_upload'] ) ) {
            $errors[] = 'Please upload a valid CV (PDF, DOC or DOCX) not exceeding 5 MB.';
        }

        if ( ! empty( $errors ) ) {
            set_transient( 'recruittech_job_seeker_profile_errors', $errors, 30 );
            set_transient( 'recruittech_job_seeker_profile_form_data', $data, 30 );

            $redirect_url = wp_get_referer();
            if ( ! $redirect_url ) {
                $redirect_url = home_url( '/job-seeker-profile/' );
            }

            wp_safe_redirect( $redirect_url );
            exit;
        }

        if ( ! empty( $profile_photo_url ) ) {
            $data['profile_photo'] = $profile_photo_url;
        } elseif ( ! empty( $existing_profile['profile_photo'] ) ) {
            $data['profile_photo'] = $existing_profile['profile_photo'];
        }

        if ( ! empty( $front_id_photo_url ) ) {
            $data['front_id_photo'] = $front_id_photo_url;
        } elseif ( ! empty( $existing_profile['front_id_photo'] ) ) {
            $data['front_id_photo'] = $existing_profile['front_id_photo'];
        }

        if ( ! empty( $back_id_photo_url ) ) {
            $data['back_id_photo'] = $back_id_photo_url;
        } elseif ( ! empty( $existing_profile['back_id_photo'] ) ) {
            $data['back_id_photo'] = $existing_profile['back_id_photo'];
        }

        if ( ! empty( $selfie_with_id_photo_url ) ) {
            $data['selfie_with_id_photo'] = $selfie_with_id_photo_url;
        } elseif ( ! empty( $existing_profile['selfie_with_id_photo'] ) ) {
            $data['selfie_with_id_photo'] = $existing_profile['selfie_with_id_photo'];
        }

        $previous_status = 'pending';
        if ( ! empty( $existing_profile ) ) {
            $previous_status = isset( $existing_profile['verification_status'] ) ? sanitize_text_field( wp_unslash( $existing_profile['verification_status'] ) ) : 'pending';
            if ( ! in_array( $previous_status, array( 'pending', 'approved', 'rejected' ), true ) ) {
                $previous_status = 'pending';
            }
        }

        $identity_changed = false;
        $identity_fields = array(
            'full_name'            => array(
                'previous' => isset( $existing_profile['full_name'] ) ? (string) $existing_profile['full_name'] : '',
                'current'  => isset( $data['full_name'] ) ? (string) $data['full_name'] : '',
            ),
            'profile_photo'       => array(
                'previous' => isset( $existing_profile['profile_photo'] ) ? (string) $existing_profile['profile_photo'] : '',
                'current'  => isset( $data['profile_photo'] ) ? (string) $data['profile_photo'] : '',
            ),
            'front_id_photo'      => array(
                'previous' => isset( $existing_profile['front_id_photo'] ) ? (string) $existing_profile['front_id_photo'] : '',
                'current'  => isset( $data['front_id_photo'] ) ? (string) $data['front_id_photo'] : '',
            ),
            'back_id_photo'       => array(
                'previous' => isset( $existing_profile['back_id_photo'] ) ? (string) $existing_profile['back_id_photo'] : '',
                'current'  => isset( $data['back_id_photo'] ) ? (string) $data['back_id_photo'] : '',
            ),
            'selfie_with_id_photo'=> array(
                'previous' => isset( $existing_profile['selfie_with_id_photo'] ) ? (string) $existing_profile['selfie_with_id_photo'] : '',
                'current'  => isset( $data['selfie_with_id_photo'] ) ? (string) $data['selfie_with_id_photo'] : '',
            ),
        );

        foreach ( $identity_fields as $field_values ) {
            if ( (string) $field_values['current'] !== (string) $field_values['previous'] ) {
                $identity_changed = true;
                break;
            }
        }

        if ( empty( $existing_profile ) ) {
            $verification_status = 'pending';
        } elseif ( 'pending' === $previous_status ) {
            $verification_status = 'pending';
        } elseif ( $identity_changed ) {
            $verification_status = 'pending';
        } else {
            $verification_status = $previous_status;
        }

        $data['verification_status'] = $verification_status;
        $profile = recruittech_update_or_create_job_seeker_profile( $data );

        if ( $previous_status !== $verification_status 
            && in_array( $previous_status, array( 'approved', 'rejected' ), true )
            && 'pending' === $verification_status
            && function_exists( 'recruittech_add_notification' )
            && ! empty( $profile )
        ) {
            $full_name_for_notice = isset( $data['full_name'] ) ? sanitize_text_field( $data['full_name'] ) : '';
            recruittech_add_notification(
                $user_id,
                'Verification Status Reset to Pending',
                'Hi ' . $full_name_for_notice . ', you updated identity-related information in your profile, so it needs to be reviewed again. Your verification status has been set to Pending until an admin re-approves it.',
                'verification',
                null,
                null
            );
        }

        if ( ! empty( $_FILES['cv_upload']['name'] ) ) {
            $cv_url = recruittech_job_seeker_profile_handle_upload( 'cv_upload', $errors );
            if ( ! empty( $cv_url ) && $profile && ! empty( $profile['id'] ) ) {
                recruittech_save_cv_file( absint( $profile['id'] ), $cv_url );
            }
        }

        if ( ! empty( $errors ) ) {
            set_transient( 'recruittech_job_seeker_profile_errors', $errors, 30 );
            set_transient( 'recruittech_job_seeker_profile_form_data', $data, 30 );

            $redirect_url = wp_get_referer();
            if ( ! $redirect_url ) {
                $redirect_url = home_url( '/job-seeker-profile/' );
            }

            wp_safe_redirect( $redirect_url );
            exit;
        }

        if ( empty( $existing_profile ) ) {
            $success_message = 'Your profile has been submitted successfully and is now awaiting administrator verification.';
        } elseif ( 'pending' === $previous_status ) {
            $success_message = 'Your profile has been updated successfully. It is still awaiting administrator verification.';
        } elseif ( $identity_changed ) {
            $success_message = 'Your profile changes have been submitted successfully and are awaiting administrator verification.';
        } elseif ( 'rejected' === $previous_status ) {
            $success_message = 'Your profile has been updated successfully. Your verification status remains rejected until identity information is updated.';
        } else {
            $success_message = 'Your profile has been updated successfully.';
        }

        set_transient( 'recruittech_profile_success_' . absint( $user_id ), $success_message, 45 );

        $redirect_url = wp_get_referer();
        if ( ! $redirect_url ) {
            $redirect_url = home_url( '/job-seeker-profile/' );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    add_action( 'init', 'recruittech_handle_job_seeker_profile_submission' );
