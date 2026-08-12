<?php
/**
 * RecruitTech Admin Job Seeker Verification
 * Handles the job seeker verification admin page and actions.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the RecruitTech Job Seeker verification submenu.
 */
function recruittech_register_admin_job_seeker_verification_menu() {
    add_submenu_page(
        'recruittech',
        'Job Seeker Verification',
        'Job Seeker Verification',
        'manage_options',
        'recruittech-job-seeker-verification',
        'recruittech_render_job_seeker_verification_page'
    );
}
add_action( 'admin_menu', 'recruittech_register_admin_job_seeker_verification_menu' );
add_action( 'admin_init', 'recruittech_admin_job_seeker_verification_process_action' );

/**
 * Check if the current user can access the job seeker verification page.
 */
function recruittech_admin_job_seeker_verification_require_admin() {
    if ( ! current_user_can( 'manage_options' ) ) {
        recruittech_send_access_denied( admin_url(), '<p>You do not have sufficient permissions to access this page.</p>' );
    }
}

/**
 * Process approve/reject actions from the admin page.
 */
function recruittech_admin_job_seeker_verification_process_action() {
    recruittech_ensure_job_seeker_verification_columns();

    if ( ! isset( $_GET['page'] ) || 'recruittech-job-seeker-verification' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
        return null;
    }

    if ( ! isset( $_GET['action'], $_GET['job_seeker_id'], $_GET['recruittech_job_seeker_verification_nonce'] ) ) {
        return null;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['recruittech_job_seeker_verification_nonce'] ) ), 'recruittech_job_seeker_verification_action' ) ) {
        return null;
    }

    $action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
    $job_seeker_id = absint( $_GET['job_seeker_id'] );
    $allowed_actions = array( 'approve', 'reject' );

    if ( empty( $job_seeker_id ) || ! in_array( $action, $allowed_actions, true ) ) {
        return null;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'recruitech_job_seekers';
    $new_status = 'approve' === $action ? 'approved' : 'rejected';

    $current_row = $wpdb->get_row(
        $wpdb->prepare( "SELECT user_id, full_name, verification_status FROM {$table_name} WHERE id = %d", $job_seeker_id ),
        ARRAY_A
    );

    if ( empty( $current_row ) ) {
        return null;
    }

    if ( $current_row['verification_status'] === $new_status ) {
        $base_url = menu_page_url( 'recruittech-job-seeker-verification', false );
        $redirect_args = array( 'page' => 'recruittech-job-seeker-verification', 'notice' => 'no-change' );
        if ( isset( $_GET['search'] ) ) {
            $redirect_args['search'] = sanitize_text_field( wp_unslash( $_GET['search'] ) );
        }
        if ( isset( $_GET['status'] ) ) {
            $redirect_args['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
        }
        wp_safe_redirect( add_query_arg( $redirect_args, $base_url ) );
        exit;
    }

    $updated = $wpdb->update(
        $table_name,
        array( 'verification_status' => $new_status ),
        array( 'id' => $job_seeker_id ),
        array( '%s' ),
        array( '%d' )
    );

    if ( false === $updated ) {
        return null;
    }

    if ( ! empty( $current_row ) && function_exists( 'recruittech_add_notification' ) ) {
        $full_name = isset( $current_row['full_name'] ) ? sanitize_text_field( $current_row['full_name'] ) : '';
        $user_id = isset( $current_row['user_id'] ) ? absint( $current_row['user_id'] ) : 0;
        $title = 'approved' === $new_status ? 'Profile Verification Approved' : 'Profile Verification Rejected';
        $message = 'approved' === $new_status
            ? 'Congratulations ' . $full_name . '! Your profile has been verified. You can now apply to jobs.'
            : 'Hi ' . $full_name . ', your profile verification was rejected. Please review and re-upload your verification documents.';

        recruittech_add_notification( $user_id, $title, $message, 'verification', null, null );
    }

    $base_url = menu_page_url( 'recruittech-job-seeker-verification', false );
    $redirect_args = array( 'page' => 'recruittech-job-seeker-verification', 'notice' => 'approved' === $new_status ? 'job-seeker-approved' : 'job-seeker-rejected' );

    if ( isset( $_GET['search'] ) ) {
        $redirect_args['search'] = sanitize_text_field( wp_unslash( $_GET['search'] ) );
    }

    if ( isset( $_GET['status'] ) ) {
        $redirect_args['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
    }

    wp_safe_redirect( add_query_arg( $redirect_args, $base_url ) );
    exit;
}

/**
 * Render the admin job seeker verification page.
 */
function recruittech_render_job_seeker_verification_page() {
    recruittech_admin_job_seeker_verification_require_admin();

    $notice = '';
    $notice_code = isset( $_GET['notice'] ) ? sanitize_text_field( wp_unslash( $_GET['notice'] ) ) : '';
    $allowed_notice_codes = array( 'job-seeker-approved', 'job-seeker-rejected' );

    if ( ! in_array( $notice_code, $allowed_notice_codes, true ) ) {
        $notice_code = '';
    }

    if ( 'job-seeker-approved' === $notice_code ) {
        $notice = 'Job seeker approved successfully.';
    } elseif ( 'job-seeker-rejected' === $notice_code ) {
        $notice = 'Job seeker rejected successfully.';
    }

    $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
    $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
    $valid_filters = array( 'all', 'pending', 'approved', 'rejected' );
    if ( ! in_array( $status_filter, $valid_filters, true ) ) {
        $status_filter = 'all';
    }

    global $wpdb;
    $job_seekers_table = $wpdb->prefix . 'recruitech_job_seekers';
    $users_table = $wpdb->prefix . 'users';
    $cvs_table = $wpdb->prefix . 'recruitech_cvs';

    $query = "SELECT js.*, u.user_email, c.file_path AS cv_file_path FROM {$job_seekers_table} js LEFT JOIN {$users_table} u ON js.user_id = u.ID LEFT JOIN {$cvs_table} c ON js.id = c.job_seeker_id";
    $where = array();
    $query_args = array();

    if ( 'all' !== $status_filter ) {
        $where[] = 'js.verification_status = %s';
        $query_args[] = $status_filter;
    }

    if ( ! empty( $search ) ) {
        $search_like = '%' . $wpdb->esc_like( $search ) . '%';
        $where[] = '(js.full_name LIKE %s OR u.user_email LIKE %s)';
        $query_args[] = $search_like;
        $query_args[] = $search_like;
    }

    if ( ! empty( $where ) ) {
        $query .= ' WHERE ' . implode( ' AND ', $where );
    }

    $query .= ' GROUP BY js.id ORDER BY js.full_name ASC';
    $job_seekers = $wpdb->get_results( $wpdb->prepare( $query, $query_args ), ARRAY_A );

    $base_url = menu_page_url( 'recruittech-job-seeker-verification', false );
    $action_url = add_query_arg( array(), $base_url );

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Job Seeker Verification</h1>
        <?php if ( ! empty( $notice ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( $notice ); ?></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url( $action_url ); ?>" class="search-form search-plugins">
            <input type="hidden" name="page" value="recruittech-job-seeker-verification">
            <p class="search-box">
                <label class="screen-reader-text" for="recruittech-search-input">Search Job Seekers:</label>
                <input type="search" id="recruittech-search-input" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by full name or email" />
                <input type="submit" id="search-submit" class="button" value="Search" />
            </p>
            <p>
                <label for="recruittech-status-filter">Status:</label>
                <select id="recruittech-status-filter" name="status">
                    <option value="all" <?php selected( $status_filter, 'all' ); ?>>All</option>
                    <option value="pending" <?php selected( $status_filter, 'pending' ); ?>>Pending</option>
                    <option value="approved" <?php selected( $status_filter, 'approved' ); ?>>Approved</option>
                    <option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>>Rejected</option>
                </select>
                <button type="submit" class="button">Filter</button>
            </p>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Full Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Current CV</th>
                    <th scope="col">Profile Photo</th>
                    <th scope="col">Front ID</th>
                    <th scope="col">Back ID</th>
                    <th scope="col">Selfie with ID</th>
                    <th scope="col">Verification Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $job_seekers ) ) : ?>
                    <tr>
                        <td colspan="10">No job seekers found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $job_seekers as $job_seeker ) : ?>
                        <?php
                        $status = isset( $job_seeker['verification_status'] ) ? sanitize_text_field( $job_seeker['verification_status'] ) : 'pending';
                        $status_color = 'background: #f6c23e; color: #000;';
                        if ( 'approved' === $status ) {
                            $status_color = 'background: #1e7e34; color: #fff;';
                        } elseif ( 'rejected' === $status ) {
                            $status_color = 'background: #c82333; color: #fff;';
                        }

                        $cv_url = ! empty( $job_seeker['cv_file_path'] ) ? esc_url( $job_seeker['cv_file_path'] ) : '';
                        $profile_photo_url = ! empty( $job_seeker['profile_photo'] ) ? esc_url( $job_seeker['profile_photo'] ) : '';
                        $front_id_photo_url = ! empty( $job_seeker['front_id_photo'] ) ? esc_url( $job_seeker['front_id_photo'] ) : '';
                        $back_id_photo_url = ! empty( $job_seeker['back_id_photo'] ) ? esc_url( $job_seeker['back_id_photo'] ) : '';
                        $selfie_with_id_photo_url = ! empty( $job_seeker['selfie_with_id_photo'] ) ? esc_url( $job_seeker['selfie_with_id_photo'] ) : '';
                        $approve_url = wp_nonce_url( add_query_arg( array( 'page' => 'recruittech-job-seeker-verification', 'action' => 'approve', 'job_seeker_id' => absint( $job_seeker['id'] ), 'search' => $search, 'status' => $status_filter ), $base_url ), 'recruittech_job_seeker_verification_action', 'recruittech_job_seeker_verification_nonce' );
                        $reject_url = wp_nonce_url( add_query_arg( array( 'page' => 'recruittech-job-seeker-verification', 'action' => 'reject', 'job_seeker_id' => absint( $job_seeker['id'] ), 'search' => $search, 'status' => $status_filter ), $base_url ), 'recruittech_job_seeker_verification_action', 'recruittech_job_seeker_verification_nonce' );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $job_seeker['full_name'] ); ?></td>
                            <td><?php echo esc_html( $job_seeker['user_email'] ); ?></td>
                            <td><?php echo esc_html( $job_seeker['phone'] ); ?></td>
                            <td>
                                <?php if ( $cv_url ) : ?>
                                    <a href="<?php echo esc_url( $cv_url ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">View CV</a>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $profile_photo_url ) : ?>
                                    <a href="<?php echo esc_url( $profile_photo_url ); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo esc_url( $profile_photo_url ); ?>" alt="Job seeker profile photo" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" />
                                    </a>
                                <?php else : ?>
                                    No Photo
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $front_id_photo_url ) : ?>
                                    <a href="<?php echo esc_url( $front_id_photo_url ); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url( $front_id_photo_url ); ?>" alt="Front ID" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" /></a>
                                <?php else : ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $back_id_photo_url ) : ?>
                                    <a href="<?php echo esc_url( $back_id_photo_url ); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url( $back_id_photo_url ); ?>" alt="Back ID" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" /></a>
                                <?php else : ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $selfie_with_id_photo_url ) : ?>
                                    <a href="<?php echo esc_url( $selfie_with_id_photo_url ); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url( $selfie_with_id_photo_url ); ?>" alt="Selfie with ID" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" /></a>
                                <?php else : ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td><span style="display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;<?php echo esc_attr( $status_color ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
                            <td>
                                <a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary" onclick="return confirm('Are you sure you want to approve this Job Seeker?');">Approve</a>
                                <a href="<?php echo esc_url( $reject_url ); ?>" class="button button-secondary" onclick="return confirm('Are you sure you want to reject this Job Seeker?');">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
