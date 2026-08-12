<?php
/**
 * RecruitTech Home Landing Page
 * Provides the public Home/Landing page shortcode and related helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the stored Home page ID, if any.
 *
 * @return int
 */
function recruittech_get_home_page_id() {
	$stored_pages = get_option( 'recruittech_pages', array() );
	if ( ! is_array( $stored_pages ) ) {
		$stored_pages = array();
	}

	if ( isset( $stored_pages['home'] ) ) {
		$page_id = absint( $stored_pages['home'] );
		if ( $page_id > 0 ) {
			$page = get_post( $page_id );
			if ( $page instanceof WP_Post ) {
				return $page_id;
			}
		}
	}

	$page = get_page_by_path( 'home' );
	if ( $page instanceof WP_Post && ! empty( $page->ID ) ) {
		$stored_pages['home'] = (int) $page->ID;
		update_option( 'recruittech_pages', $stored_pages );
		return (int) $page->ID;
	}

	$page = get_page_by_title( 'Home' );
	if ( $page instanceof WP_Post && ! empty( $page->ID ) ) {
		$stored_pages['home'] = (int) $page->ID;
		update_option( 'recruittech_pages', $stored_pages );
		return (int) $page->ID;
	}

	return 0;
}

/**
 * Get the Home page URL.
 *
 * @return string
 */
function recruittech_get_home_page_url() {
	$page_id = recruittech_get_home_page_id();
	if ( $page_id > 0 ) {
		$permalink = get_permalink( $page_id );
		if ( false !== $permalink ) {
			return $permalink;
		}
	}

	return home_url( '/' );
}

/**
 * Return the destination for the top-level Home link based on user role.
 *
 * @return string
 */
function recruittech_get_home_destination() {
	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$roles = (array) $current_user->roles;

		if ( in_array( 'company', $roles, true ) ) {
			return function_exists( 'recruittech_get_company_dashboard_page_url' )
				? recruittech_get_company_dashboard_page_url()
				: recruittech_get_home_page_url();
		}

		if ( in_array( 'job_seeker', $roles, true ) ) {
			return function_exists( 'recruittech_get_page_url' )
				? recruittech_get_page_url( 'job-seeker-dashboard' )
				: recruittech_get_home_page_url();
		}
	}

	return recruittech_get_home_page_url();
}

/**
 * Render the RecruitTech Home landing page shortcode.
 *
 * @return string
 */
function recruittech_home_shortcode() {
	global $wpdb;

	$jobs_table = $wpdb->prefix . 'recruitech_jobs';
	$companies_table = $wpdb->prefix . 'recruitech_companies';
	$job_seekers_table = $wpdb->prefix . 'recruitech_job_seekers';
	$applications_table = $wpdb->prefix . 'recruitech_applications';

	$published_jobs_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$jobs_table} WHERE status = %s",
			'Published'
		)
	);
	$registered_companies_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$companies_table}" );
	$registered_job_seekers_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$job_seekers_table}" );
	$applications_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$applications_table}" );

	$latest_jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, job_title, company_name, location FROM {$jobs_table} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
			'Published',
			5
		),
		ARRAY_A
	);

	$browse_jobs_url = function_exists( 'recruittech_get_shortcode_page_url' ) ? recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' ) : home_url( '/jobs/' );
	$register_url = function_exists( 'recruittech_get_page_url' ) ? recruittech_get_page_url( 'registration' ) : home_url( '/registration/' );

	ob_start();
	?>
	<div class="rt-home-shell container-fluid px-3 px-lg-5 py-4 py-lg-5">
		<section class="row g-4 align-items-center mb-5 rt-home-hero">
			<div class="col-12 col-xl-7">
				<div class="d-flex flex-column gap-3">
					<span class="badge bg-primary-subtle text-primary-emphasis align-self-start">RecruitTech</span>
					<h1 class="display-5 fw-bold mb-0">Connecting talented professionals with great companies.</h1>
					<p class="lead text-muted mb-0">Discover exciting career opportunities or find the perfect candidate through one modern recruitment platform.</p>
					<div class="d-flex flex-wrap gap-3 pt-2">
						<a href="<?php echo esc_url( $browse_jobs_url ); ?>" class="btn btn-primary btn-lg px-4">Browse Jobs</a>
						<a href="<?php echo esc_url( $register_url ); ?>" class="btn btn-outline-primary btn-lg px-4">Create Account</a>
					</div>
				</div>
			</div>
			<div class="col-12 col-xl-5">
				<div class="rt-home-visual card border-0 shadow-sm h-100">
					<div class="card-body p-4 p-lg-5">
						<div class="d-flex align-items-center gap-3 mb-3">
							<div class="rounded-circle bg-primary bg-opacity-10 p-3">
								<i class="bi bi-cpu-fill text-primary fs-4"></i>
							</div>
							<div>
								<h2 class="h4 mb-1">AI Job Recommendations</h2>
								<p class="text-muted mb-0">Coming Soon</p>
							</div>
						</div>
						<div class="rt-home-illustration rounded-4 p-4 d-flex align-items-center justify-content-center">
							<svg viewBox="0 0 320 220" class="w-100" style="max-width: 320px;" aria-label="RecruitTech illustration">
								<rect x="28" y="44" width="264" height="132" rx="24" fill="#ffffff" stroke="#dbeafe" stroke-width="2"/>
								<rect x="54" y="72" width="92" height="14" rx="7" fill="#0A66C2" fill-opacity="0.9"/>
								<rect x="54" y="100" width="146" height="10" rx="5" fill="#86bdf5"/>
								<rect x="54" y="120" width="122" height="10" rx="5" fill="#dbeafe"/>
								<circle cx="228" cy="112" r="34" fill="#e8f2ff"/>
								<path d="M214 112l10 10 24-24" fill="none" stroke="#0A66C2" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
								<rect x="54" y="152" width="74" height="10" rx="5" fill="#dbeafe"/>
								<rect x="138" y="152" width="52" height="10" rx="5" fill="#dbeafe"/>
							</svg>
						</div>
						<p class="mb-0 text-muted mt-3">Personalized AI recommendations will be available in a future update, making it easier to discover the right jobs or candidates.</p>
					</div>
				</div>
			</div>
		</section>

		<section class="row g-4 mb-5">
			<div class="col-12 col-md-6 col-xl-3">
				<div class="rt-home-stat card border-0 shadow-sm h-100">
					<div class="card-body p-4 text-center">
						<div class="rt-home-stat-icon rounded-circle mb-3 mx-auto"><i class="bi bi-briefcase-fill"></i></div>
						<h3 class="h6 text-muted mb-2">Published Jobs</h3>
						<p class="display-6 fw-semibold mb-0"><?php echo esc_html( $published_jobs_count ); ?></p>
					</div>
				</div>
			</div>
			<div class="col-12 col-md-6 col-xl-3">
				<div class="rt-home-stat card border-0 shadow-sm h-100">
					<div class="card-body p-4 text-center">
						<div class="rt-home-stat-icon rounded-circle mb-3 mx-auto"><i class="bi bi-building"></i></div>
						<h3 class="h6 text-muted mb-2">Registered Companies</h3>
						<p class="display-6 fw-semibold mb-0"><?php echo esc_html( $registered_companies_count ); ?></p>
					</div>
				</div>
			</div>
			<div class="col-12 col-md-6 col-xl-3">
				<div class="rt-home-stat card border-0 shadow-sm h-100">
					<div class="card-body p-4 text-center">
						<div class="rt-home-stat-icon rounded-circle mb-3 mx-auto"><i class="bi bi-person-badge"></i></div>
						<h3 class="h6 text-muted mb-2">Registered Job Seekers</h3>
						<p class="display-6 fw-semibold mb-0"><?php echo esc_html( $registered_job_seekers_count ); ?></p>
					</div>
				</div>
			</div>
			<div class="col-12 col-md-6 col-xl-3">
				<div class="rt-home-stat card border-0 shadow-sm h-100">
					<div class="card-body p-4 text-center">
						<div class="rt-home-stat-icon rounded-circle mb-3 mx-auto"><i class="bi bi-send-check"></i></div>
						<h3 class="h6 text-muted mb-2">Applications</h3>
						<p class="display-6 fw-semibold mb-0"><?php echo esc_html( $applications_count ); ?></p>
					</div>
				</div>
			</div>
		</section>

		<section class="mb-5">
			<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
				<div>
					<h2 class="h3 fw-semibold mb-1">Latest Jobs</h2>
					<p class="text-muted mb-0">Explore recently published opportunities from verified companies.</p>
				</div>
			</div>

			<?php if ( empty( $latest_jobs ) ) : ?>
				<div class="card border-0 shadow-sm">
					<div class="card-body p-4 text-center text-muted">
						No jobs are available yet. Check back soon for new opportunities.
					</div>
				</div>
			<?php else : ?>
				<div class="row g-4">
					<?php foreach ( $latest_jobs as $job ) : ?>
						<div class="col-12 col-md-6 col-xl-4">
							<div class="rt-home-job card border-0 shadow-sm h-100">
								<div class="card-body p-4 d-flex flex-column">
									<div class="d-flex align-items-center gap-2 mb-3">
										<span class="rt-avatar rt-avatar-sm"><i class="bi bi-briefcase-fill"></i></span>
										<div>
											<h3 class="h5 mb-1"><?php echo esc_html( isset( $job['job_title'] ) ? $job['job_title'] : '' ); ?></h3>
											<p class="text-muted mb-0 small"><?php echo esc_html( isset( $job['company_name'] ) ? $job['company_name'] : '' ); ?></p>
										</div>
									</div>
									<p class="small text-muted mb-4"><?php echo esc_html( isset( $job['location'] ) ? $job['location'] : '' ); ?></p>
									<a href="<?php echo esc_url( add_query_arg( 'job_id', absint( $job['id'] ), home_url( '/job-details/' ) ) ); ?>" class="btn btn-outline-primary mt-auto">View Details</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="row g-4 mb-5">
			<div class="col-12 col-lg-4">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body p-4">
						<div class="d-flex align-items-center gap-2 mb-3">
							<span class="rt-home-bullet rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-person-hearts"></i></span>
							<h3 class="h5 mb-0">For Job Seekers</h3>
						</div>
						<ul class="list-unstyled mb-0 text-muted">
							<li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Create Profile</li>
							<li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Apply Easily</li>
							<li><i class="bi bi-check-circle-fill text-primary me-2"></i>Track Applications</li>
						</ul>
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-4">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body p-4">
						<div class="d-flex align-items-center gap-2 mb-3">
							<span class="rt-home-bullet rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-building-add"></i></span>
							<h3 class="h5 mb-0">For Companies</h3>
						</div>
						<ul class="list-unstyled mb-0 text-muted">
							<li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Post Jobs</li>
							<li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Manage Applicants</li>
							<li><i class="bi bi-check-circle-fill text-primary me-2"></i>Find Talent</li>
						</ul>
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-4">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body p-4">
						<div class="d-flex align-items-center gap-2 mb-3">
							<span class="rt-home-bullet rounded-circle d-inline-flex align-items-center justify-content-center"><i class="bi bi-shield-check"></i></span>
							<h3 class="h5 mb-0">Fast & Secure</h3>
						</div>
						<ul class="list-unstyled mb-0 text-muted">
							<li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>Responsive Design</li>
							<li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i>WordPress Powered</li>
							<li><i class="bi bi-check-circle-fill text-primary me-2"></i>Secure Authentication</li>
						</ul>
					</div>
				</div>
			</div>
		</section>

		<section class="card border-0 shadow-sm rt-home-cta">
			<div class="card-body p-4 p-lg-5 text-center">
				<h2 class="h3 fw-semibold mb-3"><?php echo is_user_logged_in() ? 'Ready for Your Next Opportunity?' : 'Ready to Get Started?'; ?></h2>
				<p class="text-muted mx-auto mb-4" style="max-width: 560px;">
					<?php echo is_user_logged_in() ? 'Explore the latest jobs that match your profile and apply in just a few clicks.' : 'Create your RecruitTech account today and start your career journey.'; ?>
				</p>
				<div class="d-flex flex-wrap justify-content-center gap-3">
					<?php if ( ! is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( $register_url ); ?>" class="btn btn-primary">Register</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $browse_jobs_url ); ?>" class="btn btn-outline-primary<?php echo is_user_logged_in() ? ' mx-auto d-inline-flex' : ''; ?>">Browse Jobs</a>
				</div>
			</div>
		</section>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'recruittech_home', 'recruittech_home_shortcode' );
