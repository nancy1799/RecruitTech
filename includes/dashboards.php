<?php
/**
 * RecruitTech Dashboards
 * Registers dashboard shortcodes for companies and job seekers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the current job seeker's profile photo URL, if available.
 *
 * @param int $user_id Optional WordPress user ID.
 * @return string
 */
function recruittech_get_job_seeker_avatar_url( $user_id = 0 ) {
	$user_id = absint( $user_id ? $user_id : get_current_user_id() );
	if ( $user_id > 0 && function_exists( 'recruittech_get_job_seeker_by_user_id' ) ) {
		$profile = recruittech_get_job_seeker_by_user_id( $user_id );
		if ( ! empty( $profile['profile_photo'] ) ) {
			return $profile['profile_photo'];
		}
	}

	return '';
}

/**
 * Return the current company's logo URL, if available.
 *
 * @param int $user_id Optional WordPress user ID.
 * @return string
 */
function recruittech_get_company_logo_url( $user_id = 0 ) {
	$user_id = absint( $user_id ? $user_id : get_current_user_id() );
	if ( $user_id > 0 && function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$profile = recruittech_get_company_profile_by_user_id( $user_id );
		if ( ! empty( $profile['logo_url'] ) ) {
			return $profile['logo_url'];
		}
	}

	return '';
}

/**
 * Return placeholder markup for a job seeker avatar.
 *
 * @param string $size_class Optional avatar size class.
 * @return string
 */
function recruittech_get_placeholder_avatar( $size_class = 'rt-avatar-sm' ) {
	$size_class = sanitize_html_class( $size_class );
	return '<span class="rt-avatar ' . esc_attr( $size_class ) . ' rt-avatar-placeholder"><i class="bi bi-person-circle"></i></span>';
}

/**
 * Return placeholder markup for a company logo.
 *
 * @param string $size_class Optional avatar size class.
 * @return string
 */
function recruittech_get_placeholder_company_logo( $size_class = 'rt-avatar-sm' ) {
	$size_class = sanitize_html_class( $size_class );
	return '<span class="rt-avatar ' . esc_attr( $size_class ) . ' rt-avatar-placeholder"><i class="bi bi-building"></i></span>';
}

/**
 * Render the shared RecruitTech dashboard header.
 *
 * @param string $avatar_markup Avatar or logo markup.
 * @param string $name Display name for the greeting.
 * @param string $subtitle Supporting subtitle text.
 * @param string $badge_markup Optional badge markup.
 * @return string
 */
function recruittech_render_dashboard_header( $avatar_markup, $name, $subtitle, $badge_markup = '' ) {
	$avatar_markup = (string) $avatar_markup;
	$name          = trim( (string) $name );
	$subtitle      = trim( (string) $subtitle );
	$badge_markup  = trim( (string) $badge_markup );

	if ( '' === $name ) {
		$name = __( 'User', 'recruittech' );
	}

	ob_start();
	?>
	<div class="rt-page-header">
		<div class="rt-page-header-title d-flex align-items-center gap-3">
			<?php echo wp_kses_post( $avatar_markup ); ?>
			<div>
				<h1>Welcome Back, <?php echo esc_html( $name ); ?><?php if ( '' !== $badge_markup ) : ?><?php echo wp_kses_post( $badge_markup ); ?><?php endif; ?></h1>
				<p><?php echo esc_html( $subtitle ); ?></p>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Get the company profile page URL if it exists.
 *
 * @return string
 */
function recruittech_get_company_profile_page_url() {
	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'recruittech_company_profile' ) ) {
			return get_permalink( $page );
		}
	}

	return home_url();
}

/**
 * Get the company dashboard page URL if it exists.
 *
 * @return string
 */
function recruittech_get_company_dashboard_page_url() {
	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'recruittech_company_dashboard' ) ) {
			return get_permalink( $page );
		}
	}

	return home_url();
}

/**
 * Get the company applications page URL if it exists.
 *
 * @return string
 */
function recruittech_get_company_applications_page_url() {
	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'recruittech_company_applications' ) ) {
			return get_permalink( $page );
		}
	}

	return home_url( '/company-applications/' );
}

/**
 * Get the my applications page URL if it exists.
 *
 * @return string
 */
function recruittech_get_my_applications_page_url() {
	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'recruittech_my_applications' ) ) {
			return get_permalink( $page );
		}
	}

	return home_url( '/my-applications/' );
}

/**
 * Get the notifications page URL if it exists.
 *
 * @return string
 */
function recruittech_get_notifications_page_url() {
	return recruittech_get_page_url( 'notifications' );
}

/**
 * Count unread notifications for the current user.
 *
 * @param int|null $user_id Optional WordPress user ID.
 * @return int
 */
function recruittech_get_unread_notification_count( $user_id = null ) {
	global $wpdb;

	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	$user_id = absint( $user_id );
	if ( 0 === $user_id ) {
		return 0;
	}

	$table_name = $wpdb->prefix . 'recruitech_notifications';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND is_read = 0",
			$user_id
		)
	);
}

/**
 * Note: automatic creation of the Notifications page (and every other
 * RecruitTech page) now lives in includes/page-setup.php, which handles
 * all frontend shortcodes from a single, consistent place.
 */

/**
 * Get the URL for a RecruitTech page by its slug.
 *
 * @param string $slug Page slug.
 * @return string
 */
function recruittech_get_page_url( $slug ) {
	if ( empty( $slug ) ) {
		return home_url( '/' );
	}

	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post && ! empty( $page->ID ) ) {
		$permalink = get_permalink( $page );
		if ( false !== $permalink ) {
			return $permalink;
		}
	}

	$shortcode_map = array(
		'jobs'                  => 'recruittech_browse_jobs',
		'notifications'         => 'recruittech_notifications',
		'company-profile'       => 'recruittech_company_profile',
		'company-documents'     => 'recruittech_company_documents',
		'company-dashboard'     => 'recruittech_company_dashboard',
		'job-seeker-dashboard'  => 'recruittech_job_seeker_dashboard',
		'my-applications'       => 'recruittech_my_applications',
		'company-applications'  => 'recruittech_company_applications',
		'create-job'            => 'recruittech_company_create_job',
		'my-jobs'               => 'recruittech_company_jobs',
		'registration'          => 'recruittech_registration',
		'login'                 => 'recruittech_login',
		'logout'                => 'recruittech_logout',
		'job-seeker-profile'    => 'recruittech_job_seeker_profile',
	);

	if ( isset( $shortcode_map[ $slug ] ) ) {
		$pages = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		foreach ( $pages as $candidate_page ) {
			if ( has_shortcode( $candidate_page->post_content, $shortcode_map[ $slug ] ) ) {
				$permalink = get_permalink( $candidate_page );
				if ( false !== $permalink ) {
					return $permalink;
				}
			}
		}
	}

	return home_url( '/' . trim( $slug, '/' ) . '/' );
}

/**
 * Handle notification POST actions before the shortcode renders.
 */
function recruittech_handle_notifications_post_actions() {
	if ( ! is_singular( array( 'page', 'post' ) ) ) {
		return;
	}

	$notifications_page = get_page_by_path( 'notifications' );
	if ( ! $notifications_page || (int) get_queried_object_id() !== (int) $notifications_page->ID ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/test/login/' ) );
		exit;
	}

	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
		return;
	}

	if ( ! isset( $_POST['recruittech_mark_all_notifications_read_nonce'] ) && ! isset( $_POST['notification_id'] ) ) {
		return;
	}

	global $wpdb;
	$user_id = get_current_user_id();
	$table_name = $wpdb->prefix . 'recruitech_notifications';
	$current_page = isset( $_GET['rt_page'] ) ? max( 1, absint( wp_unslash( $_GET['rt_page'] ) ) ) : 1;
	$pagination_base_url = recruittech_get_page_url( 'notifications' );
	$mark_all_nonce = isset( $_POST['recruittech_mark_all_notifications_read_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['recruittech_mark_all_notifications_read_nonce'] ) ) : '';
	$notification_id = isset( $_POST['notification_id'] ) ? absint( wp_unslash( $_POST['notification_id'] ) ) : 0;

	if ( ! empty( $mark_all_nonce ) && wp_verify_nonce( $mark_all_nonce, 'recruittech_mark_all_notifications_read' ) ) {
		$updated = $wpdb->update(
			$table_name,
			array( 'is_read' => 1 ),
			array(
				'user_id' => $user_id,
				'is_read' => 0,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		if ( false !== $updated ) {
			wp_safe_redirect( add_query_arg( 'rt_page', $current_page, $pagination_base_url ) );
			exit;
		}
	} elseif ( $notification_id > 0 ) {
		$single_nonce = isset( $_POST['recruittech_mark_notification_read_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['recruittech_mark_notification_read_nonce'] ) ) : '';

		if ( wp_verify_nonce( $single_nonce, 'recruittech_mark_notification_read_' . $notification_id ) ) {
			$updated = $wpdb->update(
				$table_name,
				array( 'is_read' => 1 ),
				array(
					'id'      => $notification_id,
					'user_id' => $user_id,
					'is_read' => 0,
				),
				array( '%d' ),
				array( '%d', '%d', '%d' )
			);

			if ( false !== $updated && $updated > 0 ) {
				wp_safe_redirect( add_query_arg( 'rt_page', $current_page, $pagination_base_url ) );
				exit;
			}
		}
	}
}

add_action( 'template_redirect', 'recruittech_handle_notifications_post_actions' );

/**
 * Render the notifications shortcode.
 */
function recruittech_notifications_shortcode() {
	global $wpdb;
	$user_id = get_current_user_id();
	$table_name = $wpdb->prefix . 'recruitech_notifications';

	$current_page = isset( $_GET['rt_page'] ) ? max( 1, absint( wp_unslash( $_GET['rt_page'] ) ) ) : 1;
	$per_page = 10;
	$offset = ( $current_page - 1 ) * $per_page;

	$total_notifications = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
			$user_id
		)
	);

	$total_pages = max( 1, (int) ceil( $total_notifications / $per_page ) );
	if ( $current_page > $total_pages ) {
		$current_page = $total_pages;
		$offset = ( $current_page - 1 ) * $per_page;
	}

	$notifications = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, title, message, type, is_read, created_at FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$user_id,
			$per_page,
			$offset
		),
		ARRAY_A
	);

	ob_start();
	?>
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h2 class="h3 fw-semibold mb-1">Notifications</h2>
				<p class="text-muted mb-0">View your latest RecruitTech notifications.</p>
			</div>
		</div>

		<?php
		$has_unread_notifications = false;
		foreach ( $notifications as $notification ) {
			if ( empty( $notification['is_read'] ) ) {
				$has_unread_notifications = true;
				break;
			}
		}
		if ( $has_unread_notifications ) :
			?>
			<form method="post" class="mb-4">
				<?php wp_nonce_field( 'recruittech_mark_all_notifications_read', 'recruittech_mark_all_notifications_read_nonce' ); ?>
				<button type="submit" class="btn btn-outline-primary">Mark All as Read</button>
			</form>
		<?php endif; ?>

		<?php if ( empty( $notifications ) ) : ?>
			<div class="card border-0 shadow-sm rounded-4">
				<div class="card-body">
					<p class="mb-0">No notifications yet.</p>
				</div>
			</div>
		<?php else : ?>
			<div class="row g-4">
				<?php foreach ( $notifications as $notification ) : ?>
					<div class="col-12">
						<div class="card border-0 shadow-sm rounded-4">
							<div class="card-body">
								<h5 class="mb-2"><?php echo esc_html( $notification['title'] ); ?></h5>
								<p class="text-muted mb-2"><?php echo esc_html( $notification['message'] ); ?></p>
								<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
									<span class="badge bg-secondary"><?php echo esc_html( $notification['type'] ); ?></span>
									<div class="d-flex align-items-center gap-2">
										<?php if ( empty( $notification['is_read'] ) ) : ?>
											<form method="post" class="d-inline">
												<input type="hidden" name="notification_id" value="<?php echo esc_attr( $notification['id'] ); ?>">
												<?php wp_nonce_field( 'recruittech_mark_notification_read_' . absint( $notification['id'] ), 'recruittech_mark_notification_read_nonce' ); ?>
												<button type="submit" class="btn btn-sm btn-outline-primary">Mark as Read</button>
											</form>
										<?php endif; ?>
										<small class="text-muted"><?php echo esc_html( $notification['created_at'] ); ?></small>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="d-flex justify-content-between align-items-center mt-4">
					<a class="btn btn-outline-secondary" href="<?php echo esc_url( add_query_arg( 'rt_page', max( 1, $current_page - 1 ), $pagination_base_url ) ); ?>" <?php echo $current_page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Previous</a>
					<a class="btn btn-outline-secondary" href="<?php echo esc_url( add_query_arg( 'rt_page', min( $total_pages, $current_page + 1 ), $pagination_base_url ) ); ?>" <?php echo $current_page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Next</a>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Note: automatic creation of the Company Applications and My Applications
 * pages (and every other RecruitTech page) now lives in
 * includes/page-setup.php, which handles all frontend shortcodes from a
 * single, consistent place.
 */

/**
 * Map UI application statuses to the values stored in the applications table.
 *
 * @param string $status Requested status.
 * @return string
 */
function recruittech_map_application_status_for_storage( $status ) {
	$status = sanitize_text_field( wp_unslash( $status ) );
	$allowed_statuses = array( 'Pending', 'Under Review', 'Shortlisted', 'Accepted', 'Rejected' );

	if ( in_array( $status, $allowed_statuses, true ) ) {
		return $status;
	}

	return 'Pending';
}

/**
 * Convert stored application status values into the display labels used in the UI.
 *
 * @param string $status Stored status.
 * @return string
 */
function recruittech_get_application_status_label( $status ) {
	$status = sanitize_text_field( wp_unslash( $status ) );
	$allowed_statuses = array( 'Pending', 'Under Review', 'Shortlisted', 'Accepted', 'Rejected' );

	if ( in_array( $status, $allowed_statuses, true ) ) {
		return $status;
	}

	return 'Pending';
}

/**
 * Return true when the current company user is verified.
 *
 * @return bool
 */
function recruittech_company_is_verified() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$current_user = wp_get_current_user();
	if ( ! in_array( 'company', (array) $current_user->roles, true ) ) {
		return false;
	}

	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $current_user->ID );
		if ( ! empty( $company_profile['verification_status'] ) ) {
			return 'approved' === $company_profile['verification_status'];
		}
	}

	return false;
}

/**
 * Return the allowed job status values.
 *
 * @return array
 */
function recruittech_get_allowed_job_statuses() {
	return array( 'Draft', 'Published', 'Closed' );
}

/**
 * Return the effective job status, treating expired published jobs as closed.
 *
 * @param array|string $job_or_status Job record or stored status.
 * @param string       $deadline      Optional deadline override.
 * @return string
 */
function recruittech_get_effective_job_status( $job_or_status, $deadline = '' ) {
	if ( is_array( $job_or_status ) ) {
		$stored_status = isset( $job_or_status['status'] ) ? sanitize_text_field( wp_unslash( $job_or_status['status'] ) ) : '';
		$deadline      = isset( $job_or_status['deadline'] ) ? sanitize_text_field( wp_unslash( $job_or_status['deadline'] ) ) : $deadline;
	} else {
		$stored_status = sanitize_text_field( wp_unslash( $job_or_status ) );
	}

	if ( 'Deleted' === $stored_status ) {
		return 'Deleted';
	}

	if ( 'Published' !== $stored_status ) {
		return $stored_status;
	}

	if ( '' !== $deadline && current_time( 'Y-m-d' ) > $deadline ) {
		return 'Closed';
	}

	return 'Published';
}

/**
 * Return the company profile row for a company id.
 *
 * @param int $company_id Company profile id.
 * @return array|null
 */
function recruittech_get_company_profile_by_company_id( $company_id ) {
	$company_id = absint( $company_id );
	if ( $company_id <= 0 ) {
		return null;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_companies';

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $company_id ), ARRAY_A );
}

/**
 * Return true when the supplied company profile is approved.
 *
 * @param int|array $company_profile_or_id Company profile array or company id.
 * @return bool
 */
function recruittech_is_company_profile_approved( $company_profile_or_id ) {
	$company_profile = $company_profile_or_id;
	if ( is_numeric( $company_profile_or_id ) ) {
		$company_profile = recruittech_get_company_profile_by_company_id( absint( $company_profile_or_id ) );
	}

	if ( empty( $company_profile ) || ! is_array( $company_profile ) ) {
		return false;
	}

	$verification_status = isset( $company_profile['verification_status'] ) ? sanitize_text_field( wp_unslash( $company_profile['verification_status'] ) ) : '';
	return 'approved' === $verification_status;
}

/**
 * Return true when a job should be publicly visible.
 *
 * @param array|int $job_or_id Job record or job id.
 * @return bool
 */
function recruittech_is_job_publicly_visible( $job_or_id ) {
	$job = $job_or_id;
	if ( is_numeric( $job_or_id ) ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'recruitech_jobs';
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", absint( $job_or_id ) ), ARRAY_A );
	}

	if ( empty( $job ) || ! is_array( $job ) ) {
		return false;
	}

	$effective_status = recruittech_get_effective_job_status( $job );
	if ( 'Published' !== $effective_status ) {
		return false;
	}

	$company_id = isset( $job['company_id'] ) ? absint( $job['company_id'] ) : 0;
	return recruittech_is_company_profile_approved( $company_id );
}

/**
 * Render the company dashboard shortcode.
 */
function recruittech_company_dashboard_shortcode() {
	$access_denied = recruittech_require_company_access();
	if ( null !== $access_denied ) {
		return $access_denied;
	}

	$current_user = wp_get_current_user();
	$unread_notifications_count = function_exists( 'recruittech_get_unread_notification_count' )
		? recruittech_get_unread_notification_count( $current_user->ID )
		: 0;

	$company_profile = null;
	$company_verification_status = '';
	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $current_user->ID );
	}
	if ( function_exists( 'recruittech_get_company_verification_status' ) ) {
		$company_verification_status = recruittech_get_company_verification_status( $current_user->ID );
	}

	if ( empty( $company_profile ) || empty( $company_profile['id'] ) ) {
		return '<p>Company profile not found.</p>';
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_jobs';
	$company_id = absint( $company_profile['id'] );

	$jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT status, deadline FROM {$table_name} WHERE company_id = %d AND status != %s",
			$company_id,
			'Deleted'
		),
		ARRAY_A
	);

	$total_jobs = is_array( $jobs ) ? count( $jobs ) : 0;
	$published_jobs = 0;
	$draft_jobs = 0;
	$deleted_jobs = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE company_id = %d AND status = %s",
			$company_id,
			'Deleted'
		)
	);

	if ( is_array( $jobs ) ) {
		foreach ( $jobs as $job ) {
			$effective_status = recruittech_get_effective_job_status( $job );
			if ( 'Published' === $effective_status ) {
				$published_jobs++;
			} elseif ( 'Draft' === $effective_status ) {
				$draft_jobs++;
			}
		}
	}

	$verification_badge_class = 'bg-secondary text-white';
	$verification_status_label = 'Unknown';
	$verification_message = 'Your company verification status is currently unavailable.';
	$is_company_approved = 'approved' === $company_verification_status;
	$is_company_restricted = ! $is_company_approved && in_array( $company_verification_status, array( 'pending', 'rejected' ), true );

	if ( 'approved' === $company_verification_status ) {
		$verification_badge_class = 'bg-success text-white';
		$verification_status_label = 'Approved';
		$verification_message = 'Your company account has been approved.';
	} elseif ( 'pending' === $company_verification_status ) {
		$verification_badge_class = 'bg-warning text-dark';
		$verification_status_label = 'Pending';
		$verification_message = 'Your company account is pending verification. Job management features will become available after approval.';
	} elseif ( 'rejected' === $company_verification_status ) {
		$verification_badge_class = 'bg-danger text-white';
		$verification_status_label = 'Rejected';
		$verification_message = 'Your company verification was rejected. Please update your profile and submit it again.';
	}

	ob_start();
	?>
	<div class="container py-4">
		<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
			<div>
				<h2 class="h3 fw-semibold mb-1">Company Dashboard</h2>
				<p class="text-muted mb-0">Monitor hiring activity and manage your recruitment pipeline.</p>
			</div>
		</div>

		<div class="mb-4">
			<span class="badge <?php echo esc_attr( $verification_badge_class ); ?>"><?php echo esc_html( $verification_status_label ); ?></span>
			<p class="text-muted small mb-0 mt-2"><?php echo esc_html( $verification_message ); ?></p>
		</div>

		<div class="row g-4 mb-4">
			<div class="col-12 col-md-6 col-xl-3">
				<div class="card border-0 shadow-sm rounded-4 h-100">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<div class="rounded-circle bg-primary bg-opacity-10 p-2">
								<i class="bi bi-briefcase-fill text-primary"></i>
							</div>
						</div>
						<h5 class="card-title">Total Jobs</h5>
						<p class="display-6 mb-0"><?php echo esc_html( $total_jobs ); ?></p>
					</div>
				</div>
			</div>

			<div class="col-12 col-md-6 col-xl-3">
				<div class="card border-0 shadow-sm rounded-4 h-100">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<div class="rounded-circle bg-success bg-opacity-10 p-2">
								<i class="bi bi-check-circle-fill text-success"></i>
							</div>
						</div>
						<h5 class="card-title">Published</h5>
						<p class="display-6 mb-0 text-success"><?php echo esc_html( $published_jobs ); ?></p>
					</div>
				</div>
			</div>

			<div class="col-12 col-md-6 col-xl-3">
				<div class="card border-0 shadow-sm rounded-4 h-100">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<div class="rounded-circle bg-warning bg-opacity-10 p-2">
								<i class="bi bi-pencil-square text-warning"></i>
							</div>
						</div>
						<h5 class="card-title">Draft</h5>
						<p class="display-6 mb-0 text-warning"><?php echo esc_html( $draft_jobs ); ?></p>
					</div>
				</div>
			</div>

			<div class="col-12 col-md-6 col-xl-3">
				<div class="card border-0 shadow-sm rounded-4 h-100">
					<div class="card-body">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<div class="rounded-circle bg-danger bg-opacity-10 p-2">
								<i class="bi bi-trash-fill text-danger"></i>
							</div>
						</div>
						<h5 class="card-title">Deleted</h5>
						<p class="display-6 mb-0 text-danger"><?php echo esc_html( $deleted_jobs ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm rounded-4 mb-4">
			<div class="card-body p-4">
				<h4 class="h5 mb-4">Quick Actions</h4>
				<div class="d-flex flex-column gap-3">
					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border rounded p-3">
						<div class="mb-2 mb-md-0">
							<h5 class="h6 mb-1">Notifications<?php echo ! empty( $unread_notifications_count ) ? ' (' . esc_html( $unread_notifications_count ) . ')' : ''; ?></h5>
							<p class="text-muted mb-0">View your RecruitTech notifications.</p>
						</div>
						<a href="<?php echo esc_url( recruittech_get_notifications_page_url() ); ?>" class="btn btn-outline-primary">Notifications</a>
					</div>
					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border rounded p-3">
						<div class="mb-2 mb-md-0">
							<h5 class="h6 mb-1">Create New Job Posting</h5>
							<p class="text-muted mb-0">Start a new recruiting campaign and publish a job opening.</p>
						</div>
						<?php if ( $is_company_restricted ) : ?>
							<button type="button" class="btn btn-primary" disabled title="Available after company approval.">Create New Job</button>
							<p class="text-muted small mb-0 mt-2">Available after company approval.</p>
						<?php else : ?>
							<a href="<?php echo esc_url( home_url( '/create-job/' ) ); ?>" class="btn btn-primary">Create New Job</a>
						<?php endif; ?>
					</div>

					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border rounded p-3">
						<div class="mb-2 mb-md-0">
							<h5 class="h6 mb-1">Manage Existing Jobs</h5>
							<p class="text-muted mb-0">Review, edit and manage existing job listings.</p>
						</div>
						<?php if ( $is_company_restricted ) : ?>
							<button type="button" class="btn btn-success" disabled title="Available after company approval.">View My Jobs</button>
							<p class="text-muted small mb-0 mt-2">Available after company approval.</p>
						<?php else : ?>
							<a href="<?php echo esc_url( home_url( '/my-jobs/' ) ); ?>" class="btn btn-success">View My Jobs</a>
						<?php endif; ?>
					</div>

					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border rounded p-3">
						<div class="mb-2 mb-md-0">
							<h5 class="h6 mb-1">Edit Company Profile</h5>
							<p class="text-muted mb-0">Update company information and settings.</p>
						</div>
						<a href="<?php echo esc_url( home_url( '/company-profile/' ) ); ?>" class="btn btn-warning">Edit Company Profile</a>
					</div>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm rounded-4">
			<div class="card-body p-4">
				<h4 class="h5 mb-4">Other Tools</h4>
				<div class="d-flex flex-column gap-3">
					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border rounded p-3">
						<div class="mb-2 mb-md-0">
							<h5 class="h6 mb-1">View Applications</h5>
							<p class="text-muted mb-0">Track applicants and follow up on promising candidates.</p>
						</div>
						<?php if ( $is_company_restricted ) : ?>
							<button type="button" class="btn btn-secondary" disabled title="Available after company approval.">View Applications</button>
							<p class="text-muted small mb-0 mt-2">Available after company approval.</p>
						<?php else : ?>
							<a href="<?php echo esc_url( recruittech_get_company_applications_page_url() ); ?>" class="btn btn-secondary">View Applications</a>
						<?php endif; ?>
					</div>

					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border rounded p-3">
						<div class="mb-2 mb-md-0">
							<h5 class="h6 mb-1">Hiring Documents (AI Knowledge Base)</h5>
							<p class="text-muted mb-0">Upload hiring policies and guidelines so the AI Assistant follows your rules.</p>
						</div>
						<?php if ( $is_company_restricted ) : ?>
							<button type="button" class="btn btn-info" disabled title="Available after company approval.">Manage Documents</button>
							<p class="text-muted small mb-0 mt-2">Available after company approval.</p>
						<?php else : ?>
							<a href="<?php echo esc_url( function_exists( 'recruittech_get_company_documents_page_url' ) ? recruittech_get_company_documents_page_url() : home_url( '/company-documents/' ) ); ?>" class="btn btn-info">Manage Documents</a>
						<?php endif; ?>
					</div>

					<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border rounded p-3">
						<div class="mb-2 mb-md-0">
							<h5 class="h6 mb-1">AI Recruitment Assistant</h5>
							<p class="text-muted mb-0">Analyze candidates against a job and your hiring documents from the Applications screen.</p>
						</div>
						<?php if ( $is_company_restricted ) : ?>
							<button type="button" class="btn btn-secondary" disabled title="Available after company approval.">Analyze Candidates</button>
							<p class="text-muted small mb-0 mt-2">Available after company approval.</p>
						<?php else : ?>
							<a href="<?php echo esc_url( recruittech_get_company_applications_page_url() ); ?>" class="btn btn-secondary">Analyze Candidates</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the My Applications shortcode.
 */
function recruittech_my_applications_shortcode() {
	if ( ! is_user_logged_in() ) {
		return recruittech_send_access_denied(
			function_exists( 'recruittech_get_page_url' ) ? recruittech_get_page_url( 'login' ) : home_url( '/test/login/' ),
			'<p>Please log in to view your applications.</p>',
			'generic'
		);
	}

	$current_user = wp_get_current_user();
	$user_roles = (array) $current_user->roles;

	if ( in_array( 'company', $user_roles, true ) || ! in_array( 'job_seeker', $user_roles, true ) ) {
		return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '<p>Only approved job seekers can view this page.</p>', 'job_seeker_area_only' );
	}

	$job_seeker_profile = array();
	if ( function_exists( 'recruittech_get_job_seeker_by_user_id' ) ) {
		$job_seeker_profile = recruittech_get_job_seeker_by_user_id( $current_user->ID );
	}

	$verification_status = '';
	if ( ! empty( $job_seeker_profile ) && is_array( $job_seeker_profile ) ) {
		$verification_status = isset( $job_seeker_profile['verification_status'] ) ? sanitize_text_field( wp_unslash( $job_seeker_profile['verification_status'] ) ) : '';
	}

	if ( 'approved' !== $verification_status ) {
		return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '<p>Your job seeker account must be approved before you can view your applications.</p>', 'generic' );
	}

	global $wpdb;
	$applications_table = $wpdb->prefix . 'recruitech_applications';
	$jobs_table = $wpdb->prefix . 'recruitech_jobs';
	$companies_table = $wpdb->prefix . 'recruitech_companies';

	$applications = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.id, a.status, a.created_at, a.job_id, j.job_title, c.company_name
			FROM {$applications_table} AS a
			INNER JOIN {$jobs_table} AS j ON a.job_id = j.id
			INNER JOIN {$companies_table} AS c ON j.company_id = c.id
			WHERE a.job_seeker_id = %d
			ORDER BY a.created_at DESC",
			absint( $current_user->ID )
		),
		ARRAY_A
	);

	ob_start();
	?>
	<div class="container py-4">
		<div class="rt-page-header">
			<div class="rt-page-header-title d-flex align-items-center gap-3">
				<span class="rt-avatar"><i class="bi bi-file-earmark-text"></i></span>
				<div>
					<h1>My Applications</h1>
					<p>Track the status of every job you have applied for.</p>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm">
			<div class="card-body p-4">
				<?php if ( empty( $applications ) ) : ?>
					<p class="mb-0">No applications found.</p>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table table-bordered table-hover align-middle mb-0">
							<thead>
								<tr>
									<th>Job Title</th>
									<th>Company Name</th>
									<th>Applied Date</th>
									<th>Current Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $applications as $application ) : ?>
									<tr>
										<td><?php echo esc_html( isset( $application['job_title'] ) ? $application['job_title'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $application['company_name'] ) ? $application['company_name'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $application['created_at'] ) ? $application['created_at'] : '' ); ?></td>
										<td><?php echo esc_html( recruittech_get_application_status_label( isset( $application['status'] ) ? $application['status'] : '' ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the shared AI rank results modal markup.
 */
function recruittech_render_ai_rank_modal_markup() {
	ob_start();
	?>
	<div class="modal fade" id="rtAiRankModal" tabindex="-1" aria-labelledby="rtAiRankModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rtAiRankModalLabel"><i class="bi bi-trophy"></i> Top Candidates</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="rtAiRankModalBody">
					<p class="text-muted mb-0">Loading&hellip;</p>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the company applications shortcode.
 */
function recruittech_company_applications_shortcode() {
	$access_denied = recruittech_require_company_access();
	if ( null !== $access_denied ) {
		return $access_denied;
	}

	$current_user = wp_get_current_user();
	$company_profile = null;
	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $current_user->ID );
	}

	if ( empty( $company_profile ) || empty( $company_profile['id'] ) ) {
		return '<p>Company profile not found.</p>';
	}

	global $wpdb;
	$company_id = absint( $company_profile['id'] );
	$applications_table = $wpdb->prefix . 'recruitech_applications';
	$jobs_table = $wpdb->prefix . 'recruitech_jobs';
	$job_seekers_table = $wpdb->prefix . 'recruitech_job_seekers';
	$users_table = $wpdb->users;
	$cv_table = $wpdb->prefix . 'recruitech_cvs';
	$allowed_statuses = array(
		'Pending' => 'Pending',
		'Under Review' => 'Under Review',
		'Shortlisted' => 'Shortlisted',
		'Accepted' => 'Accepted',
		'Rejected' => 'Rejected',
	);
	$update_message = '';
	$update_message_type = 'success';

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['recruittech_update_application_status_submit'] ) ) {
		$application_id = isset( $_POST['application_id'] ) ? absint( wp_unslash( $_POST['application_id'] ) ) : 0;
		$new_status = isset( $_POST['application_status'] ) ? sanitize_text_field( wp_unslash( $_POST['application_status'] ) ) : '';
		$nonce = isset( $_POST['recruittech_update_application_status_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['recruittech_update_application_status_nonce'] ) ) : '';

		if ( $application_id > 0 && wp_verify_nonce( $nonce, 'recruittech_update_application_status_' . $application_id ) && array_key_exists( $new_status, $allowed_statuses ) ) {
			$application_record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT a.id, a.job_id, a.job_seeker_id, a.status, j.job_title FROM {$applications_table} AS a INNER JOIN {$jobs_table} AS j ON a.job_id = j.id WHERE a.id = %d AND j.company_id = %d LIMIT 1",
					$application_id,
					$company_id
				),
				ARRAY_A
			);

			if ( ! empty( $application_record ) ) {
				$storage_status = recruittech_map_application_status_for_storage( $new_status );
				$result = $wpdb->update(
					$applications_table,
					array( 'status' => $storage_status ),
					array( 'id' => $application_id ),
					array( '%s' ),
					array( '%d' )
				);

				if ( false !== $result ) {
					$update_message = 'Application status updated successfully.';

					if ( function_exists( 'recruittech_add_notification' ) && $result > 0 ) {
						$job_title = isset( $application_record['job_title'] ) ? sanitize_text_field( $application_record['job_title'] ) : '';
						$updated_status = sanitize_text_field( $new_status );
						$notification_title = 'Application Status Updated';
						$notification_message = 'Your application for "' . $job_title . '" has been updated.\n\nNew Status: ' . $updated_status;

						switch ( $updated_status ) {
							case 'Under Review':
								$notification_title = 'Application Under Review';
								$notification_message = 'Your application for "' . $job_title . '" is now under review.';
								break;
							case 'Shortlisted':
								$notification_title = 'Congratulations! You\'ve Been Shortlisted';
								$notification_message = 'Congratulations! Your application for "' . $job_title . '" has been shortlisted. The company is interested in your profile and may contact you soon.';
								break;
							case 'Accepted':
								$notification_title = 'Congratulations! You Got the Job!';
								$notification_message = 'Congratulations! Your application for "' . $job_title . '" has been accepted. We wish you great success in your new opportunity!';
								break;
							case 'Rejected':
								$notification_title = 'Application Update';
								$notification_message = 'Thank you for applying for "' . $job_title . '". Unfortunately, your application was not selected this time. We encourage you to keep applying for other opportunities.';
								break;
						}

						recruittech_add_notification(
							absint( $application_record['job_seeker_id'] ),
							$notification_title,
							$notification_message,
							'status_update',
							absint( $application_record['id'] ),
							absint( $application_record['job_id'] )
						);
					}
				}
			}
		}
	}

	$job_title_column = 'job_title';
	$job_title_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$jobs_table} LIKE 'job_title'" );
	if ( empty( $job_title_columns ) ) {
		$job_title_column = 'title';
	}

	$search_text = isset( $_GET['application_search'] ) ? sanitize_text_field( wp_unslash( $_GET['application_search'] ) ) : '';
	$selected_status_filter = isset( $_GET['application_status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['application_status_filter'] ) ) : 'All';
	$selected_job_filter = isset( $_GET['application_job_filter'] ) ? absint( wp_unslash( $_GET['application_job_filter'] ) ) : 0;
	$current_page = isset( $_GET['rt_page'] ) ? max( 1, absint( wp_unslash( $_GET['rt_page'] ) ) ) : 1;
	$per_page = 10;
	$offset = ( $current_page - 1 ) * $per_page;
	$where_conditions = array( 'j.company_id = %d' );
	$where_values = array( $company_id );

	if ( $selected_job_filter > 0 ) {
		$where_conditions[] = 'a.job_id = %d';
		$where_values[] = $selected_job_filter;
	}

	if ( '' !== $search_text ) {
		$search_like = '%' . $wpdb->esc_like( $search_text ) . '%';
		$where_conditions[] = '(js.full_name LIKE %s OR u.user_email LIKE %s OR j.' . $job_title_column . ' LIKE %s)';
		$where_values[] = $search_like;
		$where_values[] = $search_like;
		$where_values[] = $search_like;
	}

	if ( 'All' !== $selected_status_filter && array_key_exists( $selected_status_filter, $allowed_statuses ) ) {
		$where_conditions[] = 'a.status = %s';
		$where_values[] = $selected_status_filter;
	}

	$where_clause = implode( ' AND ', $where_conditions );
	$count_query = "SELECT COUNT(*) FROM {$applications_table} AS a INNER JOIN {$jobs_table} AS j ON a.job_id = j.id INNER JOIN {$job_seekers_table} AS js ON a.job_seeker_id = js.user_id LEFT JOIN {$users_table} AS u ON js.user_id = u.ID WHERE {$where_clause}";
	$total_applications = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) );
	$total_pages = max( 1, (int) ceil( $total_applications / $per_page ) );
	$current_page = min( $current_page, $total_pages );
	$offset = ( $current_page - 1 ) * $per_page;

	$query_args = $where_values;
	$query_args[] = $per_page;
	$query_args[] = $offset;

	$applications = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.id, a.status AS application_status, a.created_at, a.job_id, a.job_seeker_id, js.full_name, js.phone, js.user_id, j.{$job_title_column} AS job_title, u.user_email,
				(SELECT c.file_path FROM {$cv_table} AS c WHERE c.job_seeker_id = js.id ORDER BY c.uploaded_at DESC, c.id DESC LIMIT 1) AS cv_file_path
			FROM {$applications_table} AS a
			INNER JOIN {$jobs_table} AS j ON a.job_id = j.id
			INNER JOIN {$job_seekers_table} AS js ON a.job_seeker_id = js.user_id
			LEFT JOIN {$users_table} AS u ON js.user_id = u.ID
			WHERE {$where_clause}
			ORDER BY a.created_at DESC
			LIMIT %d OFFSET %d",
			$query_args
		),
		ARRAY_A
	);

	$pagination_base_url = add_query_arg( array(), home_url( '/' ) );
	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'recruittech_company_applications' ) ) {
			$pagination_base_url = get_permalink( $page );
			break;
		}
	}

	$company_jobs_for_filter = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, {$job_title_column} AS job_title FROM {$jobs_table} WHERE company_id = %d ORDER BY created_at DESC",
			$company_id
		),
		ARRAY_A
	);

	ob_start();
	?>
	<div class="container py-4">
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<h1 class="h3 mb-4">Company Applications</h1>
				<?php if ( ! empty( $update_message ) ) : ?>
					<div class="alert alert-success" role="alert">
						<?php echo esc_html( $update_message ); ?>
					</div>
				<?php endif; ?>
				<form method="get" class="row g-2 mb-3 align-items-end">
					<div class="col-12 col-md-4">
						<label for="application_search" class="form-label small mb-1">Search</label>
						<input type="text" name="application_search" id="application_search" class="form-control" value="<?php echo esc_attr( $search_text ); ?>" placeholder="Applicant Name, Email, or Job Title">
					</div>
					<div class="col-12 col-md-3">
						<label for="application_status_filter" class="form-label small mb-1">Status</label>
						<select name="application_status_filter" id="application_status_filter" class="form-select">
							<option value="All" <?php selected( $selected_status_filter, 'All' ); ?>>All</option>
							<?php foreach ( $allowed_statuses as $status_value => $status_label ) : ?>
								<option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $selected_status_filter, $status_value ); ?>><?php echo esc_html( $status_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-12 col-md-3">
						<label for="application_job_filter" class="form-label small mb-1">Job</label>
						<select name="application_job_filter" id="application_job_filter" class="form-select">
							<option value="0">All Jobs</option>
							<?php foreach ( $company_jobs_for_filter as $filter_job ) : ?>
								<option value="<?php echo esc_attr( $filter_job['id'] ); ?>" <?php selected( $selected_job_filter, absint( $filter_job['id'] ) ); ?>><?php echo esc_html( $filter_job['job_title'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-12 col-md-2">
						<button type="submit" class="btn btn-primary w-100">Apply</button>
					</div>
				</form>
				<?php if ( $selected_job_filter > 0 ) : ?>
					<div class="rt-rank-cta mb-4">
						<div class="rt-rank-cta-icon"><i class="bi bi-trophy-fill"></i></div>
						<div class="rt-rank-cta-text">
							<h3>AI-Powered Shortlist</h3>
							<p>Analyze every applicant for this job and instantly see the 10 strongest matches, ranked and scored.</p>
						</div>
						<button
							type="button"
							class="btn btn-success rt-ai-rank-btn"
							data-job-id="<?php echo esc_attr( $selected_job_filter ); ?>"
						>
							<i class="bi bi-trophy"></i> Rank Top 10 with AI
						</button>
					</div>
				<?php endif; ?>
				<?php if ( empty( $applications ) ) : ?>
					<p>No applications found.</p>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table table-bordered table-hover align-middle mb-0">
							<thead>
								<tr>
									<th>Applicant Name</th>
									<th>Applicant Email</th>
									<th>Phone Number</th>
									<th>Job Title</th>
									<th>Applied Date</th>
									<th>CV</th>
									<th>Current Status</th>
									<th>Update Status</th>
									<th>AI Analysis</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $applications as $application ) : ?>
									<tr>
										<td><?php echo esc_html( isset( $application['full_name'] ) ? $application['full_name'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $application['user_email'] ) ? $application['user_email'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $application['phone'] ) ? $application['phone'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $application['job_title'] ) ? $application['job_title'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $application['created_at'] ) ? $application['created_at'] : '' ); ?></td>
										<td>
											<?php if ( ! empty( $application['cv_file_path'] ) ) : ?>
												<a href="<?php echo esc_url( $application['cv_file_path'] ); ?>" class="btn btn-sm btn-outline-primary">View / Download</a>
											<?php else : ?>
												<span class="text-muted">Not available</span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( recruittech_get_application_status_label( isset( $application['application_status'] ) ? $application['application_status'] : '' ) ); ?></td>
										<td>
											<form method="post" class="d-flex flex-wrap gap-2 align-items-center">
												<input type="hidden" name="recruittech_update_application_status_submit" value="1">
												<input type="hidden" name="application_id" value="<?php echo esc_attr( isset( $application['id'] ) ? $application['id'] : '' ); ?>">
												<?php wp_nonce_field( 'recruittech_update_application_status_' . absint( isset( $application['id'] ) ? $application['id'] : 0 ), 'recruittech_update_application_status_nonce' ); ?>
												<select name="application_status" class="form-select form-select-sm" style="min-width: 150px;">
													<?php foreach ( $allowed_statuses as $status_value => $status_label ) : ?>
														<option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( recruittech_get_application_status_label( isset( $application['application_status'] ) ? $application['application_status'] : '' ), $status_value ); ?>><?php echo esc_html( $status_label ); ?></option>
													<?php endforeach; ?>
												</select>
												<button type="submit" class="btn btn-sm btn-primary">Update</button>
											</form>
										</td>
										<td>
											<?php if ( empty( $application['cv_file_path'] ) ) : ?>
												<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="No CV uploaded">Analyze with AI</button>
											<?php else : ?>
												<button
													type="button"
													class="btn btn-sm btn-outline-info rt-ai-analyze-btn"
													data-application-id="<?php echo esc_attr( isset( $application['id'] ) ? $application['id'] : '' ); ?>"
													data-candidate-name="<?php echo esc_attr( isset( $application['full_name'] ) ? $application['full_name'] : '' ); ?>"
												>
													<i class="bi bi-stars"></i> Analyze with AI
												</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
				<?php if ( $total_pages > 1 ) : ?>
					<div class="d-flex justify-content-between align-items-center mt-3">
						<div class="text-muted small">Showing <?php echo esc_html( $per_page < $total_applications ? $per_page : $total_applications ); ?> of <?php echo esc_html( $total_applications ); ?> applications</div>
						<nav aria-label="Applications pagination">
							<ul class="pagination pagination-sm mb-0">
								<li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
									<a class="page-link" href="<?php echo esc_url( add_query_arg( array( 'rt_page' => max( 1, $current_page - 1 ), 'application_search' => $search_text, 'application_status_filter' => $selected_status_filter ), $pagination_base_url ) ); ?>">Previous</a>
								</li>
								<li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
									<a class="page-link" href="<?php echo esc_url( add_query_arg( array( 'rt_page' => min( $total_pages, $current_page + 1 ), 'application_search' => $search_text, 'application_status_filter' => $selected_status_filter ), $pagination_base_url ) ); ?>">Next</a>
								</li>
							</ul>
						</nav>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="modal fade" id="rtAiAnalysisModal" tabindex="-1" aria-labelledby="rtAiAnalysisModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rtAiAnalysisModalLabel"><i class="bi bi-stars"></i> AI Candidate Analysis</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="rtAiAnalysisModalBody">
					<p class="text-muted mb-0">Loading&hellip;</p>
				</div>
			</div>
		</div>
	</div>

	<?php echo recruittech_render_ai_rank_modal_markup(); ?>
	<?php
	return ob_get_clean();
}

/**
 * Render the company create job shortcode.
 */
/**
 * Handle the create/edit job form submission.
 *
 * Split out from recruittech_company_create_job_shortcode() and hooked to
 * 'init' (same pattern as recruittech_handle_job_application()) because a
 * shortcode runs while WordPress is generating page content - by then the
 * page headers have already been sent, so wp_safe_redirect() inside a
 * shortcode fails with "headers already sent". Running here, on 'init',
 * happens well before any output, so every redirect below (subscription
 * limit reached, or a successful save) is safe.
 *
 * Validation/DB errors are stashed in short-lived, per-user transients and
 * the request is simply left to fall through and render the page normally
 * (no redirect needed for the error path, since we haven't sent any
 * output yet either way); recruittech_company_create_job_shortcode() picks
 * the transients up when it renders.
 */
function recruittech_handle_job_creation_submission() {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['recruittech_company_create_job_submit'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() || ! recruittech_is_company_user() ) {
		return;
	}

	$current_user = wp_get_current_user();
	$edit_job_id  = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
	$is_edit_mode = $edit_job_id > 0;

	$redirect_base_url = function_exists( 'recruittech_get_shortcode_page_url' )
		? recruittech_get_shortcode_page_url( 'recruittech_company_create_job', 'create-job' )
		: home_url( '/create-job/' );
	$redirect_url = $is_edit_mode ? add_query_arg( 'job_id', $edit_job_id, $redirect_base_url ) : $redirect_base_url;

	$errors_key      = 'recruittech_create_job_errors_' . $current_user->ID;
	$error_msg_key   = 'recruittech_create_job_error_message_' . $current_user->ID;
	$success_key     = 'recruittech_create_job_success_' . $current_user->ID;
	$form_data_key   = 'recruittech_create_job_form_data_' . $current_user->ID;

	$errors = array();

	if ( ! isset( $_POST['recruittech_create_job_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['recruittech_create_job_nonce'] ), 'recruittech_create_job_action' ) ) {
		$errors['nonce'] = 'Security check failed.';
	}

	$job_title        = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
	$job_category     = isset( $_POST['job_category'] ) ? sanitize_text_field( wp_unslash( $_POST['job_category'] ) ) : '';
	$job_type         = isset( $_POST['job_type'] ) ? sanitize_text_field( wp_unslash( $_POST['job_type'] ) ) : '';
	$experience_level = isset( $_POST['experience_level'] ) ? sanitize_text_field( wp_unslash( $_POST['experience_level'] ) ) : '';
	$required_skills  = isset( $_POST['required_skills'] ) ? sanitize_textarea_field( wp_unslash( $_POST['required_skills'] ) ) : '';
	$salary           = isset( $_POST['salary'] ) ? sanitize_text_field( wp_unslash( $_POST['salary'] ) ) : '';
	$location         = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
	$description      = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
	$requirements     = isset( $_POST['requirements'] ) ? sanitize_textarea_field( wp_unslash( $_POST['requirements'] ) ) : '';
	$benefits         = isset( $_POST['benefits'] ) ? sanitize_textarea_field( wp_unslash( $_POST['benefits'] ) ) : '';
	$deadline         = isset( $_POST['deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['deadline'] ) ) : '';
	$status           = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Draft';

	$submitted_form_data = compact( 'job_title', 'job_category', 'job_type', 'experience_level', 'required_skills', 'salary', 'location', 'description', 'requirements', 'benefits', 'deadline', 'status' );

	if ( '' === $job_title ) {
		$errors['job_title'] = 'Job Title is required.';
	}

	if ( strlen( trim( $description ) ) < 50 ) {
		$errors['description'] = 'Description must contain at least 50 characters.';
	}

	if ( '' !== $deadline ) {
		$today = date( 'Y-m-d' );
		if ( $deadline < $today ) {
			$errors['deadline'] = 'Deadline cannot be in the past.';
		}
	}

	if ( ! empty( $errors ) ) {
		set_transient( $errors_key, $errors, 30 );
		set_transient( $form_data_key, $submitted_form_data, 30 );
		return;
	}

	global $wpdb;

	$company_profile = null;
	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $current_user->ID );
	}

	if ( empty( $company_profile ) || empty( $company_profile['id'] ) ) {
		set_transient( $error_msg_key, 'Company profile not found. Complete your company profile before creating jobs.', 30 );
		set_transient( $form_data_key, $submitted_form_data, 30 );
		return;
	}

	$company_verified = ! empty( $company_profile['verification_status'] ) && 'approved' === $company_profile['verification_status'];

	if ( ! $company_verified ) {
		$status = 'Draft';
	} else {
		$allowed_statuses = recruittech_get_allowed_job_statuses();
		$status = in_array( $status, $allowed_statuses, true ) ? $status : 'Draft';
	}

	$table_name   = $wpdb->prefix . 'recruitech_jobs';
	$company_id   = absint( $company_profile['id'] );
	$company_name = isset( $company_profile['company_name'] ) ? $company_profile['company_name'] : '';

	$data = array(
		'company_id'       => $company_id,
		'company_name'     => $company_name,
		'job_title'        => $job_title,
		'job_category'     => $job_category,
		'job_type'         => $job_type,
		'experience_level' => $experience_level,
		'required_skills'  => $required_skills,
		'salary'           => $salary,
		'location'         => $location,
		'description'      => $description,
		'requirements'     => $requirements,
		'benefits'         => $benefits,
		'deadline'         => ! empty( $deadline ) ? $deadline : null,
		'status'           => $status,
	);

	$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

	if ( $is_edit_mode ) {
		// Confirm the job being edited actually belongs to this company
		// before touching it - editing a job never counts against the
		// subscription's job posting limit either way.
		$existing_job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $edit_job_id ), ARRAY_A );

		if ( empty( $existing_job ) || absint( $existing_job['company_id'] ) !== $company_id ) {
			set_transient( $error_msg_key, 'Failed to update job.', 30 );
			set_transient( $form_data_key, $submitted_form_data, 30 );
			return;
		}

		$where         = array(
			'id'         => $edit_job_id,
			'company_id' => $company_id,
		);
		$where_formats = array( '%d', '%d' );
		$updated       = $wpdb->update( $table_name, $data, $where, $formats, $where_formats );

		if ( false !== $updated ) {
			set_transient( $success_key, 'Job updated successfully.', 30 );
		} else {
			set_transient( $error_msg_key, 'Failed to update job.', 30 );
			set_transient( $form_data_key, $submitted_form_data, 30 );
			return;
		}
	} elseif ( function_exists( 'recruittech_subscription_can_post_job' ) && ! recruittech_subscription_can_post_job( $current_user->ID ) ) {
		$subscription_page_url = function_exists( 'recruittech_get_shortcode_page_url' )
			? recruittech_get_shortcode_page_url( 'recruittech_my_subscription', 'my-subscription' )
			: home_url( '/my-subscription/' );
		wp_safe_redirect( esc_url_raw( add_query_arg( 'recruittech_subscription_notice', 'job_limit_reached', $subscription_page_url ) ) );
		exit;
	} else {
		$inserted = $wpdb->insert( $table_name, $data, $formats );

		if ( false !== $inserted ) {
			set_transient( $success_key, 'Job saved successfully.', 30 );
			if ( function_exists( 'recruittech_subscription_get_current' ) ) {
				$active_subscription = recruittech_subscription_get_current( $current_user->ID, 'company' );
				if ( ! empty( $active_subscription ) ) {
					recruittech_subscription_increment_usage( $active_subscription['id'] );
				}
			}
		} else {
			set_transient( $error_msg_key, 'Failed to save job, please try again.', 30 );
			set_transient( $form_data_key, $submitted_form_data, 30 );
			return;
		}
	}

	wp_safe_redirect( esc_url_raw( $redirect_url ) );
	exit;
}
add_action( 'init', 'recruittech_handle_job_creation_submission' );

function recruittech_company_create_job_shortcode() {
	$access_denied = recruittech_require_company_access();
	if ( null !== $access_denied ) {
		return $access_denied;
	}

	$current_user = wp_get_current_user();

	$job_title = '';
	$job_category = '';
	$job_type = '';
	$experience_level = '';
	$required_skills = '';
	$salary = '';
	$location = '';
	$description = '';
	$requirements = '';
	$benefits = '';
	$deadline = '';
	$status = 'Draft';
	$errors = array();
	$save_success = '';
	$save_error = '';
	$access_denied = false;
	$is_edit_mode = false;
	$edit_job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
	$status_notice = '';

	recruittech_require_company_access( $edit_job_id );

	if ( $edit_job_id > 0 ) {
		$is_edit_mode = true;
		global $wpdb;
		$table_name = $wpdb->prefix . 'recruitech_jobs';
		$job_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $edit_job_id ), ARRAY_A );

		if ( ! empty( $job_record ) ) {
			$company_profile = null;
			if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
				$company_profile = recruittech_get_company_profile_by_user_id( $current_user->ID );
			}

			if ( empty( $company_profile ) || empty( $company_profile['id'] ) || absint( $job_record['company_id'] ) !== absint( $company_profile['id'] ) ) {
				$access_denied = true;
			} else {
				$job_title = isset( $job_record['job_title'] ) ? $job_record['job_title'] : '';
				$job_category = isset( $job_record['job_category'] ) ? $job_record['job_category'] : '';
				$job_type = isset( $job_record['job_type'] ) ? $job_record['job_type'] : '';
				$experience_level = isset( $job_record['experience_level'] ) ? $job_record['experience_level'] : '';
				$required_skills = isset( $job_record['required_skills'] ) ? $job_record['required_skills'] : '';
				$salary = isset( $job_record['salary'] ) ? $job_record['salary'] : '';
				$location = isset( $job_record['location'] ) ? $job_record['location'] : '';
				$description = isset( $job_record['description'] ) ? $job_record['description'] : '';
				$requirements = isset( $job_record['requirements'] ) ? $job_record['requirements'] : '';
				$benefits = isset( $job_record['benefits'] ) ? $job_record['benefits'] : '';
				$deadline = isset( $job_record['deadline'] ) ? $job_record['deadline'] : '';
				$status = isset( $job_record['status'] ) ? $job_record['status'] : 'Draft';
				$effective_status = recruittech_get_effective_job_status( $job_record );
				if ( 'Published' === $status && 'Closed' === $effective_status ) {
					$status_notice = 'This job is currently closed because its deadline has passed.';
				}
			}
		} else {
			$access_denied = true;
		}
	}

	// Actual form processing now happens on 'init' in
	// recruittech_handle_job_creation_submission(), so a redirect (e.g.
	// hitting the subscription job limit) can run before any output is
	// sent. This shortcode only needs to pick up what that handler left
	// behind for display, below.
	$user_id_key = $current_user->ID;

	$stored_errors = get_transient( 'recruittech_create_job_errors_' . $user_id_key );
	if ( false !== $stored_errors && is_array( $stored_errors ) ) {
		delete_transient( 'recruittech_create_job_errors_' . $user_id_key );
		$errors = $stored_errors;
	}

	$stored_error_message = get_transient( 'recruittech_create_job_error_message_' . $user_id_key );
	if ( false !== $stored_error_message ) {
		delete_transient( 'recruittech_create_job_error_message_' . $user_id_key );
		$save_error = $stored_error_message;
	}

	$stored_success = get_transient( 'recruittech_create_job_success_' . $user_id_key );
	if ( false !== $stored_success ) {
		delete_transient( 'recruittech_create_job_success_' . $user_id_key );
		$save_success = $stored_success;
	}

	$stored_form_data = get_transient( 'recruittech_create_job_form_data_' . $user_id_key );
	if ( false !== $stored_form_data && is_array( $stored_form_data ) ) {
		delete_transient( 'recruittech_create_job_form_data_' . $user_id_key );
		$job_title        = isset( $stored_form_data['job_title'] ) ? $stored_form_data['job_title'] : $job_title;
		$job_category     = isset( $stored_form_data['job_category'] ) ? $stored_form_data['job_category'] : $job_category;
		$job_type         = isset( $stored_form_data['job_type'] ) ? $stored_form_data['job_type'] : $job_type;
		$experience_level = isset( $stored_form_data['experience_level'] ) ? $stored_form_data['experience_level'] : $experience_level;
		$required_skills  = isset( $stored_form_data['required_skills'] ) ? $stored_form_data['required_skills'] : $required_skills;
		$salary           = isset( $stored_form_data['salary'] ) ? $stored_form_data['salary'] : $salary;
		$location         = isset( $stored_form_data['location'] ) ? $stored_form_data['location'] : $location;
		$description      = isset( $stored_form_data['description'] ) ? $stored_form_data['description'] : $description;
		$requirements     = isset( $stored_form_data['requirements'] ) ? $stored_form_data['requirements'] : $requirements;
		$benefits         = isset( $stored_form_data['benefits'] ) ? $stored_form_data['benefits'] : $benefits;
		$deadline         = isset( $stored_form_data['deadline'] ) ? $stored_form_data['deadline'] : $deadline;
		$status           = isset( $stored_form_data['status'] ) ? $stored_form_data['status'] : $status;
	}

	ob_start();
	include RECRUITTECH_PLUGIN_PATH . 'templates/company-create-job-form.php';
	return ob_get_clean();
}

/**
 * Render the company jobs listing shortcode.
 */
function recruittech_company_jobs_shortcode() {
	$access_denied = recruittech_require_company_access();
	if ( null !== $access_denied ) {
		return $access_denied;
	}

	$current_user = wp_get_current_user();

	$company_profile = null;
	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $current_user->ID );
	}

	if ( empty( $company_profile ) || empty( $company_profile['id'] ) ) {
		return '<p>Company profile not found.</p>';
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_jobs';
	$company_id = absint( $company_profile['id'] );
	$jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE company_id = %d AND status != %s ORDER BY created_at DESC",
			$company_id,
			'Deleted'
		),
		ARRAY_A
	);

	$success_message = '';
	$error_message = '';
	if ( isset( $_GET['recruittech_job_deleted'] ) ) {
		$success_message = 'Job deleted successfully.';
	}
	if ( isset( $_GET['recruittech_job_delete_error'] ) ) {
		$error_code = sanitize_text_field( wp_unslash( $_GET['recruittech_job_delete_error'] ) );
		if ( 'invalid_nonce' === $error_code ) {
			$error_message = 'Security check failed. Please try again.';
		} elseif ( 'invalid_job' === $error_code ) {
			$error_message = 'Invalid job selected for deletion.';
		} elseif ( 'not_found' === $error_code ) {
			$error_message = 'The requested job was not found.';
		} elseif ( 'access_denied' === $error_code ) {
			$error_message = 'Access denied. You cannot delete this job.';
		} elseif ( 'delete_failed' === $error_code ) {
			$error_message = 'Failed to delete the job. Please try again.';
		}
	}

	ob_start();
	?>
	<div class="container py-4">
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<h1 class="h3 mb-4">My Job Listings</h1>
				<?php if ( ! empty( $success_message ) ) : ?>
					<div class="alert alert-success" role="alert"><?php echo esc_html( $success_message ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $error_message ) ) : ?>
					<div class="alert alert-danger" role="alert"><?php echo esc_html( $error_message ); ?></div>
				<?php endif; ?>

				<?php if ( empty( $jobs ) ) : ?>
					<p>No jobs found.</p>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table table-bordered table-hover align-middle mb-0">
							<thead>
								<tr>
									<th>Job Title</th>
									<th>Category</th>
									<th>Type</th>
									<th>Location</th>
									<th>Status</th>
									<th>Deadline</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $jobs as $job ) : ?>
									<tr>
										<td><?php echo esc_html( $job['job_title'] ); ?></td>
										<td><?php echo esc_html( $job['job_category'] ); ?></td>
										<td><?php echo esc_html( $job['job_type'] ); ?></td>
										<td><?php echo esc_html( $job['location'] ); ?></td>
										<td>
											<?php
											$effective_status = recruittech_get_effective_job_status( $job );
											switch ( $effective_status ) {
												case 'Published':
													$badge_class = 'bg-success';
													$state_label = 'Active';
													$state_class = 'bg-info';
													break;
												case 'Draft':
													$badge_class = 'bg-warning text-dark';
													$state_label = 'Preview';
													$state_class = 'bg-secondary';
													break;
												case 'Closed':
													$badge_class = 'bg-secondary';
													$state_label = 'Closed';
													$state_class = 'bg-dark';
													break;
												case 'Deleted':
													$badge_class = 'bg-danger';
													$state_label = '';
													break;
												default:
													$badge_class = 'bg-secondary';
													$state_label = '';
											}
											?>
											<span class="badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $effective_status ); ?></span>
											<?php if ( ! empty( $state_label ) ) : ?>
												<span class="badge <?php echo esc_attr( $state_class ); ?> ms-1"><?php echo esc_html( $state_label ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $job['deadline'] ); ?></td>
										<td>
											<div class="btn-group" role="group" aria-label="Job actions">
										<?php $effective_status = recruittech_get_effective_job_status( $job ); ?>
										<?php if ( 'Published' === $effective_status ) : ?>
											<button
												type="button"
												class="btn btn-sm btn-outline-success rt-ai-rank-btn"
												data-job-id="<?php echo esc_attr( absint( $job['id'] ) ); ?>"
											>
												<i class="bi bi-trophy"></i> Rank Top 10 with AI
											</button>
										<?php endif; ?>
<a href="<?php echo esc_url( home_url( '/create-job/?job_id=' . absint( $job['id'] ) ) ); ?>" 
   class="btn btn-sm btn-outline-primary">
    ✏️ Edit
</a>
												<form method="post" action="" class="d-inline">
													<input type="hidden" name="job_id" value="<?php echo esc_attr( $job['id'] ); ?>">
													<input type="hidden" name="recruittech_company_delete_job_submit" value="1">
													<input type="hidden" name="recruittech_delete_job_nonce" value="<?php echo esc_attr( wp_create_nonce( 'recruittech_delete_job_action' ) ); ?>">
													<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this job?');">Delete</button>
												</form>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php echo recruittech_render_ai_rank_modal_markup(); ?>
	<?php
	return ob_get_clean();
}

/**
 * Handle company job delete requests.
 */
function recruittech_handle_company_job_delete() {
	$delete_action = false;
	$job_id = 0;
	$nonce = '';

	if ( isset( $_POST['recruittech_company_delete_job_submit'] ) ) {
		$delete_action = true;
		$job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
		$nonce = isset( $_POST['recruittech_delete_job_nonce'] ) ? wp_unslash( $_POST['recruittech_delete_job_nonce'] ) : '';
	} elseif ( isset( $_GET['recruittech_delete_job'] ) ) {
		$delete_action = true;
		$job_id = isset( $_GET['recruittech_delete_job'] ) ? absint( wp_unslash( $_GET['recruittech_delete_job'] ) ) : 0;
		$nonce = isset( $_GET['recruittech_delete_job_nonce'] ) ? wp_unslash( $_GET['recruittech_delete_job_nonce'] ) : '';
	}

	if ( ! $delete_action ) {
		return;
	}

	if ( ! is_user_logged_in() || ! recruittech_is_company_user() ) {
		recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '<p>You do not have permission to manage this job.</p>', 'company_area_only' );
	}

	$current_user = wp_get_current_user();

	$redirect_url = wp_get_referer();
	if ( empty( $redirect_url ) ) {
		$redirect_url = home_url();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'recruittech_delete_job_action' ) ) {
		$redirect_url = add_query_arg( array( 'recruittech_job_delete_error' => 'invalid_nonce' ), $redirect_url );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( $job_id <= 0 ) {
		$redirect_url = add_query_arg( array( 'recruittech_job_delete_error' => 'invalid_job' ), $redirect_url );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_jobs';
	$job_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $job_id ), ARRAY_A );

	if ( empty( $job_record ) ) {
		$redirect_url = add_query_arg( array( 'recruittech_job_delete_error' => 'not_found' ), $redirect_url );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( ! recruittech_is_job_owner( $job_id, $current_user->ID ) ) {
		recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '<p>You do not have permission to manage this job.</p>' );
	}

	$company_profile = null;
	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $current_user->ID );
	}

	if ( empty( $company_profile ) || empty( $company_profile['id'] ) ) {
		recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '<p>Your company profile is required to manage jobs.</p>' );
	}

	$delete_result = $wpdb->update(
		$table_name,
		array( 'status' => 'Deleted' ),
		array(
			'id'         => $job_id,
			'company_id' => absint( $company_profile['id'] ),
		),
		array( '%s' ),
		array( '%d', '%d' )
	);

	if ( false === $delete_result ) {
		$redirect_url = add_query_arg( array( 'recruittech_job_delete_error' => 'delete_failed' ), $redirect_url );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$redirect_url = add_query_arg( array( 'recruittech_job_deleted' => '1' ), $redirect_url );
	wp_safe_redirect( esc_url_raw( $redirect_url ) );
	exit;
}

add_action( 'init', 'recruittech_handle_company_job_delete' );

/**
 * Render the job seeker dashboard shortcode.
 */
function recruittech_job_seeker_dashboard_shortcode() {
	if ( ! is_user_logged_in() ) {
		return recruittech_send_access_denied(
			function_exists( 'recruittech_get_page_url' ) ? recruittech_get_page_url( 'login' ) : home_url( '/test/login/' ),
			'<p>Please log in to access your dashboard.</p>',
			'generic'
		);
	}

	$current_user = wp_get_current_user();
	$roles = (array) $current_user->roles;

	if ( in_array( 'company', $roles, true ) ) {
		return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'job_seeker_area_only' );
	}

	if ( ! in_array( 'job_seeker', $roles, true ) ) {
		return recruittech_send_access_denied(
			function_exists( 'recruittech_get_page_url' ) ? recruittech_get_page_url( 'login' ) : home_url( '/test/login/' ),
			'<p>Only job seeker accounts can access this dashboard.</p>',
			'generic'
		);
	}

	recruittech_ensure_job_seeker_verification_columns();

	$job_seeker_profile = array();
	if ( function_exists( 'recruittech_get_job_seeker_by_user_id' ) ) {
		$job_seeker_profile = recruittech_get_job_seeker_by_user_id( $current_user->ID );
	}

	$has_profile = ! empty( $job_seeker_profile ) && is_array( $job_seeker_profile );
	$verification_status = '';
	if ( $has_profile && ! empty( $job_seeker_profile['verification_status'] ) ) {
		$verification_status = sanitize_text_field( $job_seeker_profile['verification_status'] );
	}

	$is_approved = 'approved' === $verification_status;
	$is_rejected = 'rejected' === $verification_status;
	$is_pending = 'pending' === $verification_status;

	$unread_notifications_count = recruittech_get_unread_notification_count( $current_user->ID );

	ob_start();
	include RECRUITTECH_PLUGIN_PATH . 'templates/job-seeker-dashboard-template.php';
	return ob_get_clean();
}

/**
 * Protect the Browse Jobs page so only authenticated job seekers and companies can access it.
 */
function recruittech_protect_browse_jobs_page() {
	if ( ! is_singular( array( 'page', 'post' ) ) ) {
		return;
	}

	$queried_object = get_queried_object();
	if ( ! $queried_object instanceof WP_Post ) {
		return;
	}

	if ( ! has_shortcode( $queried_object->post_content, 'recruittech_browse_jobs' ) ) {
		return;
	}

	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$roles = (array) $current_user->roles;
		if ( in_array( 'job_seeker', $roles, true ) || in_array( 'company', $roles, true ) ) {
			return;
		}
	}

	wp_safe_redirect( recruittech_get_page_url( 'login' ) );
	exit;
}

add_action( 'template_redirect', 'recruittech_protect_browse_jobs_page' );

/**
 * Render the browse jobs shortcode.
 */
function recruittech_browse_jobs_shortcode() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_jobs';

	// Get filter values from GET parameters
	$filter_job_title = isset( $_GET['job_title'] ) ? sanitize_text_field( wp_unslash( $_GET['job_title'] ) ) : '';
	$filter_company_name = isset( $_GET['company_name'] ) ? sanitize_text_field( wp_unslash( $_GET['company_name'] ) ) : '';
	$filter_job_category = isset( $_GET['job_category'] ) ? sanitize_text_field( wp_unslash( $_GET['job_category'] ) ) : '';
	$filter_location = isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '';
	$filter_job_type = isset( $_GET['job_type'] ) ? sanitize_text_field( wp_unslash( $_GET['job_type'] ) ) : '';
	$filter_experience_level = isset( $_GET['experience_level'] ) ? sanitize_text_field( wp_unslash( $_GET['experience_level'] ) ) : '';

	$companies_table = $wpdb->prefix . 'recruitech_companies';

	// Build WHERE clause dynamically.
	// Browse Jobs only ever lists jobs that are still actually open. We only
	// filter by status here at the SQL level (company must not have deleted
	// the job); whether a Published job's deadline has passed is decided in
	// PHP right below using the same recruittech_get_effective_job_status()
	// helper "My Jobs" already relies on, so both places always agree and we
	// never depend on comparing raw date strings inside SQL. Once a job
	// becomes Closed (explicitly, or automatically once its deadline
	// passes) it drops out of this list entirely, while still remaining
	// visible under "My Jobs" on the company dashboard with a Closed status.
	$where_conditions = array(
		"j.status = 'Published'",
		"c.verification_status = 'approved'",
	);
	$where_values = array();

	if ( ! empty( $filter_job_title ) ) {
		$where_conditions[] = "job_title LIKE %s";
		$where_values[] = '%' . $wpdb->esc_like( $filter_job_title ) . '%';
	}

	if ( ! empty( $filter_company_name ) ) {
		$where_conditions[] = "j.company_name LIKE %s";
		$where_values[] = '%' . $wpdb->esc_like( $filter_company_name ) . '%';
	}

	if ( ! empty( $filter_job_category ) ) {
		$where_conditions[] = "job_category = %s";
		$where_values[] = $filter_job_category;
	}

	if ( ! empty( $filter_location ) ) {
		$where_conditions[] = "location = %s";
		$where_values[] = $filter_location;
	}

	if ( ! empty( $filter_job_type ) ) {
		$where_conditions[] = "job_type = %s";
		$where_values[] = $filter_job_type;
	}

	if ( ! empty( $filter_experience_level ) ) {
		$where_conditions[] = "experience_level = %s";
		$where_values[] = $filter_experience_level;
	}

	$where_clause = implode( ' AND ', $where_conditions );
	$per_page = 10;
	$current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

	// Fetch every Published job (matching the search filters) from verified
	// companies, then drop the ones whose deadline has passed in PHP, and
	// paginate the resulting, already-open-only list ourselves.
	$all_query = "SELECT j.* FROM {$table_name} AS j LEFT JOIN {$companies_table} AS c ON j.company_id = c.id WHERE {$where_clause} ORDER BY j.created_at DESC";

	if ( empty( $where_values ) ) {
		$matching_jobs = $wpdb->get_results( $all_query, ARRAY_A );
	} else {
		$matching_jobs = $wpdb->get_results( $wpdb->prepare( $all_query, $where_values ), ARRAY_A );
	}

	$open_jobs = array();
	foreach ( (array) $matching_jobs as $matching_job ) {
		if ( 'Published' === recruittech_get_effective_job_status( $matching_job ) ) {
			$open_jobs[] = $matching_job;
		}
	}

	$total_jobs = count( $open_jobs );
	$total_pages = $total_jobs > 0 ? (int) ceil( $total_jobs / $per_page ) : 1;
	$current_page = min( $current_page, $total_pages );
	$offset = ( $current_page - 1 ) * $per_page;
	$jobs = array_slice( $open_jobs, $offset, $per_page );

	// Build the option lists for the filter dropdowns from every currently
	// open job, regardless of the search filters themselves (so every
	// dropdown always offers the full set of options), reusing the same
	// effective-status check used above.
	$filter_option_jobs = $wpdb->get_results(
		"SELECT j.job_category, j.location, j.job_type, j.experience_level, j.status, j.deadline
		 FROM {$table_name} AS j LEFT JOIN {$companies_table} AS c ON j.company_id = c.id
		 WHERE j.status = 'Published' AND c.verification_status = 'approved'",
		ARRAY_A
	);

	$job_category_options = array();
	$location_options = array();
	$job_type_options = array();
	$experience_level_options = array();

	foreach ( (array) $filter_option_jobs as $option_job ) {
		if ( 'Published' !== recruittech_get_effective_job_status( $option_job ) ) {
			continue;
		}
		if ( ! empty( $option_job['job_category'] ) ) {
			$job_category_options[ $option_job['job_category'] ] = true;
		}
		if ( ! empty( $option_job['location'] ) ) {
			$location_options[ $option_job['location'] ] = true;
		}
		if ( ! empty( $option_job['job_type'] ) ) {
			$job_type_options[ $option_job['job_type'] ] = true;
		}
		if ( ! empty( $option_job['experience_level'] ) ) {
			$experience_level_options[ $option_job['experience_level'] ] = true;
		}
	}

	$categories = array_keys( $job_category_options );
	sort( $categories );
	$locations = array_keys( $location_options );
	sort( $locations );
	$types = array_keys( $job_type_options );
	sort( $types );
	$levels = array_keys( $experience_level_options );
	sort( $levels );

	ob_start();
	?>
	<div class="container py-4">
		<div class="rt-page-header">
			<div class="rt-page-header-title d-flex align-items-center gap-3">
				<span class="rt-avatar"><i class="bi bi-search"></i></span>
				<div>
					<h1>Browse Jobs</h1>
					<p><?php echo esc_html( $total_jobs ); ?> open <?php echo 1 === (int) $total_jobs ? 'position' : 'positions'; ?> from verified companies.</p>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<!-- Search and Filter Form -->
				<form method="get" class="mb-4">
					<input type="hidden" name="paged" value="1">
					<div class="row g-3 mb-3">
						<div class="col-12 col-md-6 col-lg-4">
							<label for="job_title_filter" class="form-label">Job Title</label>
							<input type="text" class="form-control" id="job_title_filter" name="job_title" placeholder="Search by job title" value="<?php echo esc_attr( $filter_job_title ); ?>">
						</div>

						<div class="col-12 col-md-6 col-lg-4">
							<label for="company_name_filter" class="form-label">Company Name</label>
							<input type="text" class="form-control" id="company_name_filter" name="company_name" placeholder="Search by company" value="<?php echo esc_attr( $filter_company_name ); ?>">
						</div>

						<div class="col-12 col-md-6 col-lg-4">
							<label for="job_category_filter" class="form-label">Job Category</label>
							<select class="form-select" id="job_category_filter" name="job_category">
								<option value="">All Categories</option>
								<?php
								foreach ( $categories as $category ) :
									?>
									<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $filter_job_category, $category ); ?>>
										<?php echo esc_html( $category ); ?>
									</option>
									<?php
								endforeach;
								?>
							</select>
						</div>

						<div class="col-12 col-md-6 col-lg-4">
							<label for="location_filter" class="form-label">Location</label>
							<select class="form-select" id="location_filter" name="location">
								<option value="">All Locations</option>
								<?php
								foreach ( $locations as $location ) :
									?>
									<option value="<?php echo esc_attr( $location ); ?>" <?php selected( $filter_location, $location ); ?>>
										<?php echo esc_html( $location ); ?>
									</option>
									<?php
								endforeach;
								?>
							</select>
						</div>

						<div class="col-12 col-md-6 col-lg-4">
							<label for="job_type_filter" class="form-label">Job Type</label>
							<select class="form-select" id="job_type_filter" name="job_type">
								<option value="">All Types</option>
								<?php
								foreach ( $types as $type ) :
									?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_job_type, $type ); ?>>
										<?php echo esc_html( $type ); ?>
									</option>
									<?php
								endforeach;
								?>
							</select>
						</div>

						<div class="col-12 col-md-6 col-lg-4">
							<label for="experience_level_filter" class="form-label">Experience Level</label>
							<select class="form-select" id="experience_level_filter" name="experience_level">
								<option value="">All Levels</option>
								<?php
								foreach ( $levels as $level ) :
									?>
									<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $filter_experience_level, $level ); ?>>
										<?php echo esc_html( $level ); ?>
									</option>
									<?php
								endforeach;
								?>
							</select>
						</div>
					</div>

					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary">Search</button>
						<a href="<?php echo esc_url( remove_query_arg( array( 'job_title', 'company_name', 'job_category', 'location', 'job_type', 'experience_level', 'paged' ) ) ); ?>" class="btn btn-outline-secondary">Clear Filters</a>
					</div>
				</form>

				<!-- Job Listings -->
				<?php if ( empty( $jobs ) ) : ?>
					<p>No jobs found.</p>
				<?php else : ?>
					<div class="row g-4">
						<?php foreach ( $jobs as $job ) : ?>
							<div class="col-12 col-md-6 col-lg-4">
								<div class="card rt-job-card border-0 shadow-sm h-100">
									<div class="card-body d-flex flex-column">
										<div class="d-flex align-items-center gap-2 mb-3">
											<span class="rt-avatar rt-avatar-sm"><?php echo esc_html( mb_substr( $job['company_name'], 0, 1 ) ); ?></span>
											<div>
												<h5 class="card-title mb-0"><?php echo esc_html( $job['job_title'] ); ?></h5>
												<p class="text-muted mb-0 small"><?php echo esc_html( $job['company_name'] ); ?></p>
											</div>
										</div>

										<div class="rt-job-meta small mb-3">
											<?php if ( ! empty( $job['job_category'] ) ) : ?>
												<div class="mb-1">
													<strong>Category:</strong> <?php echo esc_html( $job['job_category'] ); ?>
												</div>
											<?php endif; ?>

											<?php if ( ! empty( $job['location'] ) ) : ?>
												<div class="mb-1">
													<strong>Location:</strong> <?php echo esc_html( $job['location'] ); ?>
												</div>
											<?php endif; ?>

											<?php if ( ! empty( $job['job_type'] ) ) : ?>
												<div class="mb-1">
													<strong>Type:</strong> <?php echo esc_html( $job['job_type'] ); ?>
												</div>
											<?php endif; ?>

											<?php if ( ! empty( $job['experience_level'] ) ) : ?>
												<div class="mb-1">
													<strong>Experience:</strong> <?php echo esc_html( $job['experience_level'] ); ?>
												</div>
											<?php endif; ?>

											<?php if ( ! empty( $job['salary'] ) ) : ?>
												<div class="mb-1">
													<strong>Salary:</strong> <?php echo esc_html( $job['salary'] ); ?>
												</div>
											<?php endif; ?>

											<?php if ( ! empty( $job['deadline'] ) ) : ?>
												<div class="mb-1">
													<strong>Deadline:</strong> <?php echo esc_html( $job['deadline'] ); ?>
												</div>
											<?php endif; ?>
										</div>

										<div class="mt-auto pt-3">
											<a href="<?php echo esc_url( add_query_arg( 'job_id', absint( $job['id'] ), home_url( '/job-details/' ) ) ); ?>" class="btn btn-sm btn-outline-primary w-100">View Details</a>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $total_pages > 1 ) : ?>
					<nav class="mt-4" aria-label="Browse jobs pagination">
						<ul class="pagination justify-content-center flex-wrap">
							<li class="page-item <?php echo 1 === $current_page ? 'disabled' : ''; ?>">
								<?php if ( 1 === $current_page ) : ?>
									<span class="page-link">Previous</span>
								<?php else : ?>
									<a class="page-link" href="<?php echo esc_url( add_query_arg( array_merge( $_GET, array( 'paged' => max( 1, $current_page - 1 ) ) ) ) ); ?>">Previous</a>
								<?php endif; ?>
							</li>

							<?php for ( $page_number = 1; $page_number <= $total_pages; $page_number++ ) : ?>
								<li class="page-item <?php echo $page_number === $current_page ? 'active' : ''; ?>">
									<?php if ( $page_number === $current_page ) : ?>
										<span class="page-link" aria-current="page"><?php echo esc_html( $page_number ); ?></span>
									<?php else : ?>
										<a class="page-link" href="<?php echo esc_url( add_query_arg( array_merge( $_GET, array( 'paged' => $page_number ) ) ) ); ?>"><?php echo esc_html( $page_number ); ?></a>
									<?php endif; ?>
								</li>
							<?php endfor; ?>

							<li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
								<?php if ( $current_page >= $total_pages ) : ?>
									<span class="page-link">Next</span>
								<?php else : ?>
									<a class="page-link" href="<?php echo esc_url( add_query_arg( array_merge( $_GET, array( 'paged' => min( $total_pages, $current_page + 1 ) ) ) ) ); ?>">Next</a>
								<?php endif; ?>
							</li>
						</ul>
					</nav>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Handle job application submissions.
 */
function recruittech_handle_job_application() {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}

	if ( ! isset( $_POST['recruittech_apply_job_submit'] ) ) {
		return;
	}

	$job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
	$nonce  = isset( $_POST['recruittech_apply_job_nonce'] ) ? wp_unslash( $_POST['recruittech_apply_job_nonce'] ) : '';

	if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'recruittech_apply_job_action' ) ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'invalid_nonce',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( ! is_user_logged_in() ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'not_logged_in',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$current_user = wp_get_current_user();
	$user_id      = absint( $current_user->ID );

	if ( ! in_array( 'job_seeker', (array) $current_user->roles, true ) ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'role_denied',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	recruittech_ensure_job_seeker_verification_columns();
	recruittech_ensure_applications_table_columns();

	$job_seeker_profile = null;
	if ( function_exists( 'recruittech_get_job_seeker_by_user_id' ) ) {
		$job_seeker_profile = recruittech_get_job_seeker_by_user_id( $user_id );
	}

	if ( empty( $job_seeker_profile ) || empty( $job_seeker_profile['verification_status'] ) || 'approved' !== sanitize_text_field( $job_seeker_profile['verification_status'] ) ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'not_approved',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	global $wpdb;
	$jobs_table_name       = $wpdb->prefix . 'recruitech_jobs';
	$applications_table    = $wpdb->prefix . 'recruitech_applications';

	$job = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, company_id, job_title, status, deadline FROM {$jobs_table_name} WHERE id = %d LIMIT 1",
			$job_id
		),
		ARRAY_A
	);

	if ( empty( $job ) ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'job_not_found',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$job_status = recruittech_get_effective_job_status( $job );
	$company_is_approved_for_job = recruittech_is_company_profile_approved( isset( $job['company_id'] ) ? absint( $job['company_id'] ) : 0 );
	if ( 'Published' !== $job_status || ! $company_is_approved_for_job ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'job_closed',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$existing_application_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$applications_table} WHERE job_id = %d AND job_seeker_id = %d",
			$job_id,
			$user_id
		)
	);

	if ( $existing_application_count > 0 ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'already_applied',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( function_exists( 'recruittech_subscription_can_apply' ) && ! recruittech_subscription_can_apply( $user_id ) ) {
		$subscription_page_url = function_exists( 'recruittech_get_shortcode_page_url' )
			? recruittech_get_shortcode_page_url( 'recruittech_my_subscription', 'my-subscription' )
			: home_url( '/my-subscription/' );
		wp_safe_redirect( esc_url_raw( add_query_arg( 'recruittech_subscription_notice', 'application_limit_reached', $subscription_page_url ) ) );
		exit;
	}

	$inserted = $wpdb->insert(
		$applications_table,
		array(
			'job_id'         => $job_id,
			'job_seeker_id'  => $user_id,
			'status'         => 'Pending',
		),
		array( '%d', '%d', '%s' )
	);

	if ( false === $inserted ) {
		$redirect_url = add_query_arg(
			array(
				'job_id'                  => $job_id,
				'recruittech_apply_result' => 'insert_failed',
			),
			home_url( '/job-details/' )
		);
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$application_id = $wpdb->insert_id;

	if ( function_exists( 'recruittech_subscription_get_current' ) ) {
		$active_subscription = recruittech_subscription_get_current( $user_id, 'job_seeker' );
		if ( ! empty( $active_subscription ) ) {
			recruittech_subscription_increment_usage( $active_subscription['id'] );
		}
	}

	$job_title      = isset( $job['job_title'] ) ? sanitize_text_field( $job['job_title'] ) : '';
	$notification_message = sprintf( 'Your application for "%s" has been submitted successfully.', $job_title );

	if ( function_exists( 'recruittech_add_notification' ) ) {
		recruittech_add_notification(
			$user_id,
			'Application Submitted',
			$notification_message,
			'application',
			$application_id,
			$job_id
		);
	}

	$redirect_url = add_query_arg(
		array(
			'job_id'                  => $job_id,
			'recruittech_apply_result' => 'success',
		),
		home_url( '/job-details/' )
	);
	wp_safe_redirect( esc_url_raw( $redirect_url ) );
	exit;
}

add_action( 'init', 'recruittech_handle_job_application' );

/**
 * Render the job details shortcode.
 */
function recruittech_job_details_shortcode() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_jobs';

	$job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
	if ( $job_id <= 0 ) {
		status_header( 404 );
		ob_start();
		?>
		<div class="container py-4">
			<div class="card border-0 shadow-sm">
				<div class="card-body p-4 p-md-5 text-center">
					<h1 class="h3 mb-3">Job Not Found</h1>
					<p class="text-muted mb-4">The requested job could not be found or is no longer available.</p>
					<a href="<?php echo esc_url( function_exists( 'recruittech_get_shortcode_page_url' ) ? recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' ) : home_url( '/jobs/' ) ); ?>" class="btn btn-primary">Browse Jobs</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	$job = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT j.* FROM {$table_name} AS j LEFT JOIN {$wpdb->prefix}recruitech_companies AS c ON j.company_id = c.id WHERE j.id = %d AND j.status IN ('Published', 'Closed') AND c.verification_status = 'approved' LIMIT 1",
			$job_id
		),
		ARRAY_A
	);

	if ( empty( $job ) ) {
		status_header( 404 );
		ob_start();
		?>
		<div class="container py-4">
			<div class="card border-0 shadow-sm">
				<div class="card-body p-4 p-md-5 text-center">
					<h1 class="h3 mb-3">Job Not Found</h1>
					<p class="text-muted mb-4">The requested job could not be found or is no longer available.</p>
					<a href="<?php echo esc_url( function_exists( 'recruittech_get_shortcode_page_url' ) ? recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' ) : home_url( '/jobs/' ) ); ?>" class="btn btn-primary">Browse Jobs</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	$job_title = isset( $job['job_title'] ) ? $job['job_title'] : '';
	$job_status = recruittech_get_effective_job_status( $job );
	$company_name = isset( $job['company_name'] ) ? $job['company_name'] : '';
	$job_category = isset( $job['job_category'] ) ? $job['job_category'] : '';
	$job_type = isset( $job['job_type'] ) ? $job['job_type'] : '';
	$experience_level = isset( $job['experience_level'] ) ? $job['experience_level'] : '';
	$salary = isset( $job['salary'] ) ? $job['salary'] : '';
	$location = isset( $job['location'] ) ? $job['location'] : '';
	$description = isset( $job['description'] ) ? $job['description'] : '';
	$requirements = isset( $job['requirements'] ) ? $job['requirements'] : '';
	$benefits = isset( $job['benefits'] ) ? $job['benefits'] : '';
	$deadline = isset( $job['deadline'] ) ? $job['deadline'] : '';
	$apply_status = isset( $_GET['recruittech_apply_result'] ) ? sanitize_key( wp_unslash( $_GET['recruittech_apply_result'] ) ) : '';
	$apply_message = '';
	$apply_message_type = 'info';
	$current_user = wp_get_current_user();
	$is_logged_in = is_user_logged_in();
	$is_company_user = $is_logged_in && in_array( 'company', (array) $current_user->roles, true );
	$is_job_seeker_user = $is_logged_in && in_array( 'job_seeker', (array) $current_user->roles, true );
	$apply_button_mode = 'hidden';
	$verification_notice = '';

	if ( 'Closed' === $job_status ) {
		$apply_button_mode = 'closed';
		$verification_notice = 'This job is no longer accepting applications.';
	} elseif ( ! $is_logged_in ) {
		$apply_button_mode = 'login';
	} elseif ( $is_company_user ) {
		$apply_button_mode = 'hidden';
	} elseif ( $is_job_seeker_user ) {
		$job_seeker_profile = array();
		if ( function_exists( 'recruittech_get_job_seeker_by_user_id' ) ) {
			$job_seeker_profile = recruittech_get_job_seeker_by_user_id( $current_user->ID );
		}

		$verification_status = '';
		if ( ! empty( $job_seeker_profile ) && is_array( $job_seeker_profile ) ) {
			$verification_status = isset( $job_seeker_profile['verification_status'] ) ? sanitize_text_field( wp_unslash( $job_seeker_profile['verification_status'] ) ) : '';
		}

		if ( 'approved' === $verification_status ) {
			$apply_button_mode = 'apply';
		} elseif ( 'pending' === $verification_status ) {
			$apply_button_mode = 'disabled';
			$verification_notice = 'Your job seeker account must be approved before you can apply.';
		} elseif ( 'rejected' === $verification_status ) {
			$apply_button_mode = 'disabled';
			$verification_notice = 'Your profile must be approved before you can apply.';
		} else {
			$apply_button_mode = 'disabled';
			$verification_notice = 'Your job seeker account must be approved before you can apply.';
		}
	}

	switch ( $apply_status ) {
		case 'success':
			$apply_message = 'Your application was submitted successfully.';
			$apply_message_type = 'success';
			break;
		case 'invalid_nonce':
			$apply_message = 'Security check failed. Please try again.';
			$apply_message_type = 'danger';
			break;
		case 'not_logged_in':
			$apply_message = 'You must be logged in to apply for this job.';
			$apply_message_type = 'warning';
			break;
		case 'role_denied':
			$apply_message = 'Only job seekers can apply for jobs.';
			$apply_message_type = 'warning';
			break;
		case 'not_approved':
			$apply_message = 'Your job seeker account must be approved before you can apply.';
			$apply_message_type = 'warning';
			break;
		case 'job_not_found':
			$apply_message = 'The requested job could not be found.';
			$apply_message_type = 'warning';
			break;
		case 'job_not_published':
		case 'job_closed':
		case 'deadline_passed':
			$apply_message = 'This job is no longer accepting applications.';
			$apply_message_type = 'warning';
			break;
		case 'already_applied':
			$apply_message = 'You have already applied for this job.';
			$apply_message_type = 'warning';
			break;
		case 'insert_failed':
			$apply_message = 'We could not submit your application right now. Please try again.';
			$apply_message_type = 'danger';
			break;
	}

	ob_start();
	?>
	<div class="container py-4">
		<div class="card border-0 shadow-sm">
			<div class="card-body p-4">
					<?php if ( ! empty( $apply_message ) ) : ?>
						<div class="alert alert-<?php echo esc_attr( $apply_message_type ); ?>" role="alert">
							<?php echo esc_html( $apply_message ); ?>
						</div>
					<?php endif; ?>

				<div class="d-flex justify-content-between align-items-start mb-3 gap-3">
					<div class="d-flex align-items-center gap-3">
						<span class="rt-avatar rt-avatar-lg"><?php echo esc_html( mb_substr( $company_name, 0, 1 ) ); ?></span>
						<div>
							<h1 class="h3 mb-1"><?php echo esc_html( $job_title ); ?></h1>
							<p class="text-muted mb-0"><?php echo esc_html( $company_name ); ?><?php echo ! empty( $location ) ? ' &middot; ' . esc_html( $location ) : ''; ?></p>
						</div>
					</div>
					<?php if ( 'Closed' === $job_status ) : ?>
						<span class="badge bg-secondary">Closed</span>
					<?php endif; ?>
				</div>

				<div class="d-flex flex-wrap gap-2 mb-4">
					<?php if ( ! empty( $job_type ) ) : ?>
						<span class="badge bg-primary"><i class="bi bi-briefcase"></i> <?php echo esc_html( $job_type ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $job_category ) ) : ?>
						<span class="badge bg-secondary"><?php echo esc_html( $job_category ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $experience_level ) ) : ?>
						<span class="badge bg-secondary"><?php echo esc_html( $experience_level ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $salary ) ) : ?>
						<span class="badge bg-success"><i class="bi bi-cash-stack"></i> <?php echo esc_html( $salary ); ?></span>
					<?php endif; ?>
					<span class="badge bg-secondary"><i class="bi bi-calendar-event"></i> Deadline: <?php echo ! empty( $deadline ) ? esc_html( $deadline ) : 'Not specified'; ?></span>
				</div>

				<div class="mb-4">
					<h2 class="h5">Description</h2>
					<p class="mb-0"><?php echo wp_kses_post( wpautop( $description ) ); ?></p>
				</div>

				<div class="mb-4">
					<h2 class="h5">Requirements</h2>
					<p class="mb-0"><?php echo ! empty( $requirements ) ? wp_kses_post( wpautop( $requirements ) ) : 'Not specified'; ?></p>
				</div>

				<div class="mb-4">
					<h2 class="h5">Benefits</h2>
					<p class="mb-0"><?php echo ! empty( $benefits ) ? wp_kses_post( wpautop( $benefits ) ) : 'Not specified'; ?></p>
				</div>

				<div class="mt-4">
					<?php if ( 'login' === $apply_button_mode ) : ?>
						<a href="<?php echo esc_url( home_url( '/test/login/' ) . '?redirect_to=' . rawurlencode( add_query_arg( array( 'job_id' => $job_id ), home_url( '/job-details/' ) ) ) ); ?>" class="btn btn-primary">Login to Apply</a>
					<?php elseif ( 'apply' === $apply_button_mode ) : ?>
						<div class="d-flex flex-wrap gap-2">
							<form method="post">
								<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>">
								<input type="hidden" name="recruittech_apply_job_submit" value="1">
								<input type="hidden" name="recruittech_apply_job_nonce" value="<?php echo esc_attr( wp_create_nonce( 'recruittech_apply_job_action' ) ); ?>">
								<button type="submit" class="btn btn-primary">Apply Now</button>
							</form>
							<button type="button" class="btn btn-outline-info rt-ai-fit-btn" data-job-id="<?php echo esc_attr( $job_id ); ?>">
								<i class="bi bi-stars"></i> Check My Fit
							</button>
						</div>
						<div id="rtAiFitResult" class="mt-3"></div>
					<?php elseif ( 'disabled' === $apply_button_mode ) : ?>
						<div class="alert alert-warning" role="alert">
							<?php echo esc_html( $verification_notice ); ?>
						</div>
						<button type="button" class="btn btn-primary" disabled>Apply Now</button>
					<?php elseif ( 'closed' === $apply_button_mode ) : ?>
						<div class="alert alert-warning" role="alert">
							This job is no longer accepting applications.
						</div>
					<?php endif; ?>

				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Register dashboard shortcodes.
 */
function recruittech_register_dashboard_shortcodes() {
	add_shortcode( 'recruittech_company_dashboard', 'recruittech_company_dashboard_shortcode' );
	add_shortcode( 'recruittech_company_create_job', 'recruittech_company_create_job_shortcode' );
	add_shortcode( 'recruittech_job_seeker_dashboard', 'recruittech_job_seeker_dashboard_shortcode' );

	// Company Applications (read-only)
	add_shortcode( 'recruittech_company_applications', 'recruittech_company_applications_shortcode' );

	// 🟢 NEW: Company Jobs List
	add_shortcode( 'recruittech_company_jobs', 'recruittech_company_jobs_shortcode' );

	// My Applications (Job Seeker)
	add_shortcode( 'recruittech_my_applications', 'recruittech_my_applications_shortcode' );

	// 🟢 NEW: Browse Jobs (Public)
	add_shortcode( 'recruittech_browse_jobs', 'recruittech_browse_jobs_shortcode' );

	// 🟢 NEW: Job Details (Public)
	add_shortcode( 'recruittech_job_details', 'recruittech_job_details_shortcode' );
	add_shortcode( 'recruittech_notifications', 'recruittech_notifications_shortcode' );
}

add_action( 'init', 'recruittech_register_dashboard_shortcodes' );
// Page auto-creation for all RecruitTech shortcodes is handled centrally
// in includes/page-setup.php (recruittech_create_recruittech_pages()).
