<?php
/**
 * RecruitTech Admin Company Verification
 * Handles the company verification admin page and actions.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the RecruitTech admin menu and company verification submenu.
 */
function recruittech_register_admin_company_verification_menu() {
    add_menu_page(
        'RecruitTech',
        'RecruitTech',
        'manage_options',
        'recruittech',
        'recruittech_render_company_verification_page',
        'dashicons-businessman',
        26
    );

    add_submenu_page(
        'recruittech',
        'Company Verification',
        'Company Verification',
        'manage_options',
        'recruittech-company-verification',
        'recruittech_render_company_verification_page'
    );
}
add_action( 'admin_menu', 'recruittech_register_admin_company_verification_menu' );
add_action( 'admin_init', 'recruittech_admin_company_verification_process_action' );

/**
 * Check if the current user can access the admin verification page.
 */
function recruittech_admin_company_verification_require_admin() {
    if ( ! current_user_can( 'manage_options' ) ) {
        recruittech_send_access_denied( admin_url(), '<p>You do not have sufficient permissions to access this page.</p>' );
    }
}

/**
 * Process approve/reject actions from the admin page.
 */
function recruittech_admin_company_verification_process_action() {
    if ( ! isset( $_GET['page'] ) || 'recruittech-company-verification' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
        return null;
    }

    if ( ! isset( $_GET['action'], $_GET['company_id'], $_GET['recruittech_company_verification_nonce'] ) ) {
        return null;
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['recruittech_company_verification_nonce'] ) ), 'recruittech_company_verification_action' ) ) {
        return null;
    }

    $action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
    $company_id = absint( $_GET['company_id'] );
    $allowed_actions = array( 'approve', 'reject' );

    if ( empty( $company_id ) || ! in_array( $action, $allowed_actions, true ) ) {
        return null;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'recruitech_companies';
    $new_status = 'approve' === $action ? 'approved' : 'rejected';

    $current_row = $wpdb->get_row(
        $wpdb->prepare( "SELECT user_id, company_name, verification_status FROM {$table_name} WHERE id = %d", $company_id ),
        ARRAY_A
    );

    if ( empty( $current_row ) ) {
        return null;
    }

    if ( $current_row['verification_status'] === $new_status ) {
        $base_url = menu_page_url( 'recruittech-company-verification', false );
        $redirect_args = array( 'page' => 'recruittech-company-verification', 'notice' => 'no-change' );
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
        array( 'id' => $company_id ),
        array( '%s' ),
        array( '%d' )
    );

    if ( false === $updated ) {
        return null;
    }

    if ( function_exists( 'recruittech_add_notification' ) ) {
        $company_name = isset( $current_row['company_name'] ) ? sanitize_text_field( $current_row['company_name'] ) : '';
        $notification_title = 'Company Verification Approved';
        $notification_message = 'Your company "' . $company_name . '" has been approved. You can now post jobs and receive applications.';

        if ( 'rejected' === $new_status ) {
            $notification_title = 'Company Verification Rejected';
            $notification_message = 'Your company "' . $company_name . '" verification was rejected. Please review and resubmit your documents.';
        }

        recruittech_add_notification(
            absint( $current_row['user_id'] ),
            $notification_title,
            $notification_message,
            'verification',
            null,
            null
        );
    }

    $base_url = menu_page_url( 'recruittech-company-verification', false );
    $redirect_args = array( 'page' => 'recruittech-company-verification', 'notice' => 'approved' === $new_status ? 'company-approved' : 'company-rejected' );

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
 * Render the admin company verification page.
 */
function recruittech_render_company_verification_page() {
    recruittech_admin_company_verification_require_admin();

    $notice = '';
    $notice_code = isset( $_GET['notice'] ) ? sanitize_text_field( wp_unslash( $_GET['notice'] ) ) : '';
    $allowed_notice_codes = array( 'company-approved', 'company-rejected' );

    if ( ! in_array( $notice_code, $allowed_notice_codes, true ) ) {
        $notice_code = '';
    }

    if ( 'company-approved' === $notice_code ) {
        $notice = 'Company approved successfully.';
    } elseif ( 'company-rejected' === $notice_code ) {
        $notice = 'Company rejected successfully.';
    }

    $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
    $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
    $valid_filters = array( 'all', 'pending', 'approved', 'rejected' );
    if ( ! in_array( $status_filter, $valid_filters, true ) ) {
        $status_filter = 'all';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'recruitech_companies';
    $query = "SELECT * FROM {$table_name}";
    $where = array();
    $query_args = array();

    if ( 'all' !== $status_filter ) {
        $where[] = 'verification_status = %s';
        $query_args[] = $status_filter;
    }

    if ( ! empty( $search ) ) {
        $search_like = '%' . $wpdb->esc_like( $search ) . '%';
        $where[] = '(company_name LIKE %s OR website LIKE %s)';
        $query_args[] = $search_like;
        $query_args[] = $search_like;
    }

    if ( ! empty( $where ) ) {
        $query .= ' WHERE ' . implode( ' AND ', $where );
    }

    $query .= ' ORDER BY company_name ASC';
    $companies = $wpdb->get_results( $wpdb->prepare( $query, $query_args ), ARRAY_A );

    $base_url = menu_page_url( 'recruittech-company-verification', false );
    $action_url = add_query_arg( array(), $base_url );

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Company Verification</h1>
        <?php if ( ! empty( $notice ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( $notice ); ?></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url( $action_url ); ?>" class="search-form search-plugins">
            <input type="hidden" name="page" value="recruittech-company-verification">
            <p class="search-box">
                <label class="screen-reader-text" for="recruittech-search-input">Search Companies:</label>
                <input type="search" id="recruittech-search-input" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by company name or website" />
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
                    <th scope="col">Company Name</th>
                    <th scope="col">Website</th>
                    <th scope="col">Company Logo</th>
                    <th scope="col">Commercial Registration</th>
                    <th scope="col">Verification Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $companies ) ) : ?>
                    <tr>
                        <td colspan="6">No companies found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $companies as $company ) : ?>
                        <?php
                        $logo_url = ! empty( $company['logo_url'] ) ? esc_url( $company['logo_url'] ) : '';
                        $registration_url = ! empty( $company['commercial_register_file'] ) ? esc_url( $company['commercial_register_file'] ) : '';
                        $status = isset( $company['verification_status'] ) ? sanitize_text_field( $company['verification_status'] ) : 'pending';
                        $status_color = 'background: #f6c23e; color: #000;';
                        if ( 'approved' === $status ) {
                            $status_color = 'background: #1e7e34; color: #fff;';
                        } elseif ( 'rejected' === $status ) {
                            $status_color = 'background: #c82333; color: #fff;';
                        }
                        $approve_url = wp_nonce_url( add_query_arg( array( 'page' => 'recruittech-company-verification', 'action' => 'approve', 'company_id' => absint( $company['id'] ), 'search' => $search, 'status' => $status_filter ), $base_url ), 'recruittech_company_verification_action', 'recruittech_company_verification_nonce' );
                        $reject_url = wp_nonce_url( add_query_arg( array( 'page' => 'recruittech-company-verification', 'action' => 'reject', 'company_id' => absint( $company['id'] ), 'search' => $search, 'status' => $status_filter ), $base_url ), 'recruittech_company_verification_action', 'recruittech_company_verification_nonce' );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $company['company_name'] ); ?></td>
                            <td><?php echo ! empty( $company['website'] ) ? '<a href="' . esc_url( $company['website'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $company['website'] ) . '</a>' : '&mdash;'; ?></td>
                            <td>
                                <?php if ( $logo_url ) : ?>
                                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company['company_name'] ); ?> Logo" style="max-width: 80px; height: auto;" />
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $registration_url ) : ?>
                                    <a href="<?php echo esc_url( $registration_url ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">Open Document</a>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td><span style="display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;<?php echo esc_attr( $status_color ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
                            <td>
                                <a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary" onclick="return confirm('Are you sure you want to approve this company?');">Approve</a>
                                <a href="<?php echo esc_url( $reject_url ); ?>" class="button button-secondary" onclick="return confirm('Are you sure you want to reject this company?');">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
