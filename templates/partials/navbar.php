<?php
/**
 * RecruitTech Shared Navbar
 * Shown at the top of every RecruitTech frontend page (see canvas.php).
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rt_is_logged_in   = is_user_logged_in();
$rt_current_user   = $rt_is_logged_in ? wp_get_current_user() : null;
$rt_is_company      = $rt_is_logged_in && function_exists( 'recruittech_is_company_user' ) && recruittech_is_company_user();
$rt_is_job_seeker    = $rt_is_logged_in && $rt_current_user && in_array( 'job_seeker', (array) $rt_current_user->roles, true );
$rt_unread_count    = $rt_is_logged_in && function_exists( 'recruittech_get_unread_notification_count' ) ? recruittech_get_unread_notification_count() : 0;

$rt_login_url        = recruittech_get_shortcode_page_url( 'recruittech_login', 'test/login' );
$rt_register_url      = recruittech_get_shortcode_page_url( 'recruittech_registration', 'registration' );
$rt_logout_url        = recruittech_get_shortcode_page_url( 'recruittech_logout', 'logout' );
$rt_browse_jobs_url    = recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' );
$rt_home_url          = function_exists( 'recruittech_get_page_url' ) ? recruittech_get_page_url( 'home' ) : '';
if ( empty( $rt_home_url ) ) {
	$rt_home_url = home_url( '/' );
}
$rt_notifications_url  = function_exists( 'recruittech_get_notifications_page_url' ) ? recruittech_get_notifications_page_url() : recruittech_get_shortcode_page_url( 'recruittech_notifications', 'notifications' );

$rt_company_dashboard_url    = function_exists( 'recruittech_get_company_dashboard_page_url' ) ? recruittech_get_company_dashboard_page_url() : recruittech_get_shortcode_page_url( 'recruittech_company_dashboard', 'company-dashboard' );
$rt_company_jobs_url         = recruittech_get_shortcode_page_url( 'recruittech_company_jobs', 'my-jobs' );
$rt_company_create_job_url    = recruittech_get_shortcode_page_url( 'recruittech_company_create_job', 'create-job' );
$rt_company_applications_url  = function_exists( 'recruittech_get_company_applications_page_url' ) ? recruittech_get_company_applications_page_url() : recruittech_get_shortcode_page_url( 'recruittech_company_applications', 'company-applications' );
$rt_company_profile_url      = function_exists( 'recruittech_get_company_profile_page_url' ) ? recruittech_get_company_profile_page_url() : recruittech_get_shortcode_page_url( 'recruittech_company_profile', 'company-profile' );
$rt_company_documents_url    = function_exists( 'recruittech_get_company_documents_page_url' ) ? recruittech_get_company_documents_page_url() : recruittech_get_shortcode_page_url( 'recruittech_company_documents', 'company-documents' );

$rt_job_seeker_dashboard_url = recruittech_get_shortcode_page_url( 'recruittech_job_seeker_dashboard', 'job-seeker-dashboard' );
$rt_job_seeker_profile_url    = recruittech_get_shortcode_page_url( 'recruittech_job_seeker_profile', 'job-seeker-profile' );
$rt_my_applications_url      = function_exists( 'recruittech_get_my_applications_page_url' ) ? recruittech_get_my_applications_page_url() : recruittech_get_shortcode_page_url( 'recruittech_my_applications', 'my-applications' );
$rt_directory_search_url     = function_exists( 'recruittech_get_directory_search_page_url' ) ? recruittech_get_directory_search_page_url() : recruittech_get_shortcode_page_url( 'recruittech_directory_search', 'search' );
$rt_my_subscription_url      = function_exists( 'recruittech_get_my_subscription_page_url' ) ? recruittech_get_my_subscription_page_url() : recruittech_get_shortcode_page_url( 'recruittech_my_subscription', 'my-subscription' );
?>
<header class="rt-navbar">
	<nav class="navbar navbar-expand-lg rt-navbar-inner">
		<div class="container-fluid rt-navbar-container">
			<a class="navbar-brand rt-navbar-brand" href="<?php echo esc_url( $rt_home_url ); ?>">
				<i class="bi bi-briefcase-fill"></i> RecruitTech
			</a>

			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#rtNavbarNav" aria-controls="rtNavbarNav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>

			<div class="collapse navbar-collapse" id="rtNavbarNav">
				<ul class="navbar-nav rt-navbar-links me-auto">
					<?php if ( $rt_is_company ) : ?>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_company_dashboard_url ); ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_browse_jobs_url ); ?>"><i class="bi bi-search"></i> Browse Jobs</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_company_jobs_url ); ?>"><i class="bi bi-briefcase"></i> My Jobs</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_company_create_job_url ); ?>"><i class="bi bi-plus-circle"></i> Create Job</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_company_applications_url ); ?>"><i class="bi bi-people"></i> Applications</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_company_profile_url ); ?>"><i class="bi bi-building"></i> Company Profile</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_company_documents_url ); ?>"><i class="bi bi-file-earmark-text"></i> Hiring Documents</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_directory_search_url ); ?>"><i class="bi bi-people-fill"></i> Search</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_my_subscription_url ); ?>"><i class="bi bi-credit-card"></i> Subscription</a></li>
					<?php elseif ( $rt_is_job_seeker ) : ?>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_job_seeker_dashboard_url ); ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_browse_jobs_url ); ?>"><i class="bi bi-search"></i> Browse Jobs</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_my_applications_url ); ?>"><i class="bi bi-send-check"></i> My Applications</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_job_seeker_profile_url ); ?>"><i class="bi bi-person"></i> My Profile</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_directory_search_url ); ?>"><i class="bi bi-people-fill"></i> Search</a></li>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_my_subscription_url ); ?>"><i class="bi bi-credit-card"></i> Subscription</a></li>
					<?php else : ?>
						<!-- Guest users don't have left navigation links -->
					<?php endif; ?>
				</ul>

				<ul class="navbar-nav rt-navbar-actions align-items-lg-center">
					<?php if ( $rt_is_logged_in ) : ?>
						<li class="nav-item">
							<a class="nav-link rt-navbar-notifications" href="<?php echo esc_url( $rt_notifications_url ); ?>">
								<i class="bi bi-bell"></i> Notifications
								<?php if ( $rt_unread_count > 0 ) : ?>
									<span class="badge rounded-pill rt-navbar-badge"><?php echo esc_html( $rt_unread_count ); ?></span>
								<?php endif; ?>
							</a>
						</li>
						<li class="nav-item rt-navbar-user">
							<span class="nav-link rt-navbar-username d-flex align-items-center gap-2">
								<?php if ( $rt_is_company ) : ?>
									<?php $rt_company_logo_url = function_exists( 'recruittech_get_company_logo_url' ) ? recruittech_get_company_logo_url( $rt_current_user->ID ) : ''; ?>
									<?php if ( ! empty( $rt_company_logo_url ) ) : ?>
										<span class="rt-avatar rt-avatar-sm"><img src="<?php echo esc_url( $rt_company_logo_url ); ?>" alt="<?php echo esc_attr( $rt_current_user->display_name ); ?> logo"></span>
									<?php else : ?>
										<?php echo wp_kses_post( recruittech_get_placeholder_company_logo( 'rt-avatar-sm' ) ); ?>
									<?php endif; ?>
								<?php elseif ( $rt_is_job_seeker ) : ?>
									<?php $rt_job_seeker_avatar_url = function_exists( 'recruittech_get_job_seeker_avatar_url' ) ? recruittech_get_job_seeker_avatar_url( $rt_current_user->ID ) : ''; ?>
									<?php if ( ! empty( $rt_job_seeker_avatar_url ) ) : ?>
										<span class="rt-avatar rt-avatar-sm"><img src="<?php echo esc_url( $rt_job_seeker_avatar_url ); ?>" alt="<?php echo esc_attr( $rt_current_user->display_name ); ?> avatar"></span>
									<?php else : ?>
										<?php echo wp_kses_post( recruittech_get_placeholder_avatar( 'rt-avatar-sm' ) ); ?>
									<?php endif; ?>
								<?php else : ?>
									<?php echo wp_kses_post( recruittech_get_placeholder_avatar( 'rt-avatar-sm' ) ); ?>
								<?php endif; ?>
								<span class="rt-navbar-username-text"><?php echo esc_html( $rt_current_user->display_name ); ?></span>
							</span>
						</li>
						<li class="nav-item">
							<a class="nav-link rt-navbar-logout" href="<?php echo esc_url( $rt_logout_url ); ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
						</li>
					<?php else : ?>
						<li class="nav-item"><a class="nav-link" href="<?php echo esc_url( $rt_login_url ); ?>">Login</a></li>
						<li class="nav-item">
							<a class="nav-link rt-navbar-cta" href="<?php echo esc_url( $rt_register_url ); ?>">Register</a>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</nav>
</header>
