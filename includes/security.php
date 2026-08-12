<?php
/**
 * RecruitTech Security Helpers
 * Protect dashboard pages based on the current user's role.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine whether the supplied user is a company user.
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function recruittech_is_company_user( $user_id = 0 ) {
	$user_id = absint( $user_id ? $user_id : get_current_user_id() );
	if ( ! $user_id || ! is_user_logged_in() ) {
		return false;
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	return in_array( 'company', (array) $user->roles, true );
}

/**
 * Determine whether the supplied user is a job seeker user.
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function recruittech_is_job_seeker_user( $user_id = 0 ) {
	$user_id = absint( $user_id ? $user_id : get_current_user_id() );
	if ( ! $user_id || ! is_user_logged_in() ) {
		return false;
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	return in_array( 'job_seeker', (array) $user->roles, true );
}

/**
 * Determine whether the supplied user owns the job.
 *
 * @param int $job_id Job identifier.
 * @param int $user_id Optional user ID.
 * @return bool
 */
function recruittech_is_job_owner( $job_id, $user_id = 0 ) {
	$job_id = absint( $job_id );
	$user_id = absint( $user_id ? $user_id : get_current_user_id() );
	if ( ! $job_id || ! $user_id ) {
		return false;
	}

	// $job_author_id = get_post_field( 'post_author', $job_id );
	// if ( '' !== $job_author_id && false !== $job_author_id ) {
	// 	return absint( $job_author_id ) === $user_id;
	// }

	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_jobs';
	$job_record = $wpdb->get_row( $wpdb->prepare( "SELECT company_id FROM {$table_name} WHERE id = %d", $job_id ), ARRAY_A );
	if ( empty( $job_record ) ) {
		return false;
	}

	$company_profile = null;
	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $user_id );
	}

return ! empty( $company_profile['id'] )
    && absint( $job_record['company_id'] ) === absint( $company_profile['id'] );
}

/**
 * Return the dashboard URL for the current user role.
 *
 * @param int $user_id Optional user ID.
 * @return string
 */
function recruittech_get_user_dashboard_url( $user_id = 0 ) {
	$user_id = absint( $user_id ? $user_id : get_current_user_id() );
	if ( ! $user_id || ! is_user_logged_in() ) {
		return home_url( '/test/login/' );
	}

	$user = get_userdata( $user_id );
	if ( $user && in_array( 'company', (array) $user->roles, true ) ) {
		return function_exists( 'recruittech_get_company_dashboard_page_url' ) ? recruittech_get_company_dashboard_page_url() : home_url( '/company-dashboard/' );
	}

	if ( $user && in_array( 'job_seeker', (array) $user->roles, true ) ) {
		return home_url( '/job-seeker-dashboard/' );
	}

	return home_url( '/' );
}

/**
 * Get the verification status for the current company user.
 *
 * @param int $user_id Optional user ID.
 * @return string
 */
function recruittech_get_company_verification_status( $user_id = 0 ) {
	$user_id = absint( $user_id ? $user_id : get_current_user_id() );
	if ( ! $user_id || ! is_user_logged_in() ) {
		return '';
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'company', (array) $user->roles, true ) ) {
		return '';
	}

	if ( function_exists( 'recruittech_get_company_profile_by_user_id' ) ) {
		$company_profile = recruittech_get_company_profile_by_user_id( $user_id );
		if ( ! empty( $company_profile['verification_status'] ) ) {
			return sanitize_text_field( wp_unslash( $company_profile['verification_status'] ) );
		}
	}

	return '';
}

/**
 * Return true when the current company user is approved.
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function recruittech_company_is_approved( $user_id = 0 ) {
	return 'approved' === recruittech_get_company_verification_status( $user_id );
}

/**
 * Return true when the current request targets company job management routes.
 *
 * @return bool
 */
function recruittech_is_company_management_request() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	$company_management_paths = array(
		'/create-job/',
		'/edit-job/',
		'/my-jobs/',
		'/company-applications/',
		'/assistant/',
	);

	foreach ( $company_management_paths as $company_management_path ) {
		if ( strpos( $request_path, $company_management_path ) !== false ) {
			return true;
		}
	}

	return false;
}

/**
 * Render a RecruitTech-styled access message screen with navbar and footer.
 *
 * @param array $args Screen configuration.
 * @return string
 */
function recruittech_render_permission_state( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'       => esc_html__( 'Access Denied', 'recruittech' ),
			'icon'        => 'bi-shield-lock',
			'message'     => esc_html__( 'You do not have permission to access this page.', 'recruittech' ),
			'buttons'     => array(),
			'status_code' => 403,
			'page_title'  => esc_html__( 'Access Denied', 'recruittech' ),
		)
	);

	$buttons = is_array( $args['buttons'] ) ? $args['buttons'] : array();
	$buttons_html = '';
	if ( ! empty( $buttons ) ) {
		foreach ( $buttons as $button ) {
			if ( ! is_array( $button ) ) {
				continue;
			}

			$label = isset( $button['label'] ) ? wp_kses_post( $button['label'] ) : '';
			$url   = isset( $button['url'] ) ? esc_url( $button['url'] ) : '#';
			$class = isset( $button['class'] ) ? sanitize_html_class( $button['class'] ) : 'btn btn-primary';
			if ( '' === $label ) {
				continue;
			}

			$buttons_html .= sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
	}

	if ( '' === $buttons_html ) {
		$dashboard_url = recruittech_get_user_dashboard_url();
		$buttons_html = sprintf(
			'<a href="%s" class="btn btn-primary">%s</a>',
			esc_url( $dashboard_url ),
			esc_html__( 'Return to Dashboard', 'recruittech' )
		);
	}

	ob_start();
	?>
	<div class="container py-4">
		<div class="d-flex justify-content-center">
			<div class="card border-0 shadow-sm" style="max-width: 620px; width: 100%; margin: 0 auto; border-radius: 1.25rem;">
				<div class="card-body p-4 p-md-5 text-center">
					<div style="display:inline-flex;align-items:center;justify-content:center;width:3.5rem;height:3.5rem;margin-bottom:1rem;border-radius:999px;background:rgba(10,102,194,0.12);color:#0a66c2;font-size:1.6rem;">
						<i class="bi <?php echo esc_attr( $args['icon'] ); ?>"></i>
					</div>
					<h1 class="h3 mb-3"><?php echo esc_html( $args['title'] ); ?></h1>
					<p class="lead mb-4" style="color:#6b7280;"><?php echo wp_kses_post( $args['message'] ); ?></p>
					<div style="display:flex;flex-wrap:wrap;justify-content:center;gap:0.75rem;">
						<?php echo wp_kses( $buttons_html, array( 'a' => array( 'href' => array(), 'class' => array() ) ) ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Replace the main page content with the access-denied fragment.
 *
 * @param string $content Existing page content.
 * @return string
 */
function recruittech_filter_access_denied_content( $content = '' ) {
	if ( ! isset( $GLOBALS['recruittech_access_denied_content'] ) || '' === $GLOBALS['recruittech_access_denied_content'] ) {
		return $content;
	}

	$denied_content = $GLOBALS['recruittech_access_denied_content'];
	$GLOBALS['recruittech_access_denied_content'] = '';
	return $denied_content;
}

/**
 * Display a RecruitTech-styled 403 access-denied response for authenticated users.
 *
 * @param string $dashboard_url Optional dashboard URL to use for the action button.
 * @param string $message Optional custom message.
 * @param string $variant Optional access state variant.
 * @param array  $buttons Optional action buttons.
 */
function recruittech_send_access_denied( $dashboard_url = '', $message = '', $variant = 'generic', $buttons = array() ) {
	$dashboard_url = '' !== $dashboard_url ? $dashboard_url : recruittech_get_user_dashboard_url();

	if ( 'company_pending' === $variant ) {
		$screen_args = array(
			'title'   => esc_html__( 'Account Under Review', 'recruittech' ),
			'icon'    => 'bi-hourglass-split',
			'message' => esc_html__( 'Your company account is currently under review. You will be able to create jobs and manage applications once your account has been approved by an administrator.', 'recruittech' ),
			'buttons' => array(
				array(
					'label' => esc_html__( 'Company Dashboard', 'recruittech' ),
					'url'   => $dashboard_url,
					'class' => 'btn btn-primary',
				),
			),
		);
	} elseif ( 'company_rejected' === $variant ) {
		$screen_args = array(
			'title'   => esc_html__( 'Company Account Rejected', 'recruittech' ),
			'icon'    => 'bi-x-octagon-fill',
			'message' => esc_html__( 'Your company registration was not approved. Please update your company profile or contact the administrator.', 'recruittech' ),
			'buttons' => array(
				array(
					'label' => esc_html__( 'Edit Company Profile', 'recruittech' ),
					'url'   => function_exists( 'recruittech_get_company_profile_page_url' ) ? recruittech_get_company_profile_page_url() : home_url( '/company-profile/' ),
					'class' => 'btn btn-outline-primary',
				),
				array(
					'label' => esc_html__( 'Company Dashboard', 'recruittech' ),
					'url'   => $dashboard_url,
					'class' => 'btn btn-primary',
				),
			),
		);
	} elseif ( 'company_area_only' === $variant ) {
		$screen_args = array(
			'title'   => esc_html__( 'Company Area Only', 'recruittech' ),
			'icon'    => 'bi-building-lock',
			'message' => esc_html__( 'This page is available only for company accounts.', 'recruittech' ),
			'buttons' => array(
				array(
					'label' => esc_html__( 'Job Seeker Dashboard', 'recruittech' ),
					'url'   => function_exists( 'recruittech_get_page_url' ) ? recruittech_get_page_url( 'job-seeker-dashboard' ) : home_url( '/job-seeker-dashboard/' ),
					'class' => 'btn btn-outline-primary',
				),
				array(
					'label' => esc_html__( 'Browse Jobs', 'recruittech' ),
					'url'   => function_exists( 'recruittech_get_shortcode_page_url' ) ? recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' ) : home_url( '/jobs/' ),
					'class' => 'btn btn-primary',
				),
			),
		);
	} elseif ( 'job_seeker_area_only' === $variant ) {
		$screen_args = array(
			'title'   => esc_html__( 'Job Seeker Area Only', 'recruittech' ),
			'icon'    => 'bi-person-lock',
			'message' => esc_html__( 'This page is available only for job seeker accounts.', 'recruittech' ),
			'buttons' => array(
				array(
					'label' => esc_html__( 'Company Dashboard', 'recruittech' ),
					'url'   => $dashboard_url,
					'class' => 'btn btn-outline-primary',
				),
				array(
					'label' => esc_html__( 'Browse Jobs', 'recruittech' ),
					'url'   => function_exists( 'recruittech_get_shortcode_page_url' ) ? recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' ) : home_url( '/jobs/' ),
					'class' => 'btn btn-primary',
				),
			),
		);
	} else {
		$screen_args = array(
			'title'   => esc_html__( 'Access Denied', 'recruittech' ),
			'icon'    => 'bi-shield-lock',
			'message' => '' === $message ? esc_html__( 'You do not have permission to access this page.', 'recruittech' ) : wp_kses_post( $message ),
			'buttons' => empty( $buttons ) ? array(
				array(
					'label' => esc_html__( 'Return to Dashboard', 'recruittech' ),
					'url'   => $dashboard_url,
					'class' => 'btn btn-primary',
				),
			) : $buttons,
		);
	}

	$rendered_message = recruittech_render_permission_state( $screen_args );
	$GLOBALS['recruittech_access_denied_content'] = $rendered_message;
	if ( ! has_filter( 'the_content', 'recruittech_filter_access_denied_content' ) ) {
		add_filter( 'the_content', 'recruittech_filter_access_denied_content', 1000 );
	}
	status_header( 403 );
	return $rendered_message;
}

/**
 * Enforce company access and optionally job ownership.
 *
 * @param int $job_id Optional job identifier.
 */
function recruittech_require_company_access( $job_id = 0 ) {
	if ( ! is_user_logged_in() ) {
		return recruittech_send_access_denied(
			recruittech_get_user_dashboard_url(),
			'<p>Please log in to access this area.</p>',
			'company_area_only'
		);
	}

	if ( ! recruittech_is_company_user() ) {
		return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_area_only' );
	}

	if ( ! recruittech_company_is_approved() && ( $job_id > 0 || recruittech_is_company_management_request() ) ) {
		$verification_status = recruittech_get_company_verification_status();
		if ( 'pending' === $verification_status ) {
			return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_pending' );
		}
		if ( 'rejected' === $verification_status ) {
			return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_rejected' );
		}

		return recruittech_send_access_denied(
			recruittech_get_user_dashboard_url(),
			'<p>Your company account has not yet been approved.</p><p>You cannot access Job Management features until your company has been verified.</p>'
		);
	}

	if ( $job_id > 0 && ! recruittech_is_job_owner( $job_id ) ) {
		return recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_area_only' );
	}
}

/**
 * Redirect guests away from RecruitTech pages that require an authenticated account.
 */
function recruittech_check_page_access() {
	if ( is_admin() ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	$is_login_page = strpos( $request_path, '/test/login/' ) !== false;

	$protected_paths = array(
		'/company-dashboard/',
		'/job-seeker-dashboard/',
		'/company-profile/',
		'/job-seeker-profile/',
		'/create-job/',
		'/applications/',
		'/company-applications/',
		'/my-applications/',
		'/my-jobs/',
		'/assistant/',
		'/browse-jobs/',
		'/job-details/',
	);

	$is_protected_page = false;
	foreach ( $protected_paths as $protected_path ) {
		if ( strpos( $request_path, $protected_path ) !== false ) {
			$is_protected_page = true;
			break;
		}
	}

	if ( ! $is_protected_page ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		if ( ! $is_login_page ) {
			wp_safe_redirect( home_url( '/test/login/' ) );
			exit;
		}
		return;
	}

	$current_user = wp_get_current_user();
	$user_roles = (array) $current_user->roles;

	if ( strpos( $request_path, '/job-seeker-profile/' ) !== false && ! in_array( 'job_seeker', $user_roles, true ) ) {
		recruittech_send_access_denied( recruittech_get_user_dashboard_url() );
	}

	$company_only_paths = array(
		'/company-dashboard/',
		'/company-profile/',
		'/create-job/',
		'/applications/',
		'/company-applications/',
		'/my-jobs/',
		'/assistant/',
	);
	$has_company_only_path = false;
	foreach ( $company_only_paths as $company_only_path ) {
		if ( strpos( $request_path, $company_only_path ) !== false ) {
			$has_company_only_path = true;
			break;
		}
	}

	if ( $has_company_only_path && ! in_array( 'company', $user_roles, true ) ) {
		recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_area_only' );
	}

	if ( recruittech_is_company_management_request() && ! recruittech_company_is_approved() ) {
		$verification_status = recruittech_get_company_verification_status();
		if ( 'pending' === $verification_status ) {
			recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_pending' );
		}
		if ( 'rejected' === $verification_status ) {
			recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'company_rejected' );
		}

		recruittech_send_access_denied(
			recruittech_get_user_dashboard_url(),
			'<p>Your company account has not yet been approved.</p><p>You cannot access Job Management features until your company has been verified.</p>'
		);
	}

	if ( strpos( $request_path, '/job-seeker-dashboard/' ) !== false ) {
		if ( ! in_array( 'job_seeker', $user_roles, true ) ) {
			recruittech_send_access_denied( recruittech_get_user_dashboard_url(), '', 'job_seeker_area_only' );
		}
	}
}

add_action( 'template_redirect', 'recruittech_check_page_access' );
