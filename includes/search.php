<?php
/**
 * RecruitTech People & Company Search
 *
 * Lets any logged-in user (company or job seeker) search for a job seeker
 * by name or a company by name, then open a clean profile view page with
 * the important public details about that person or company.
 *
 * Only approved (verified) job seekers and companies are searchable/visible
 * here, matching how the rest of the platform treats verification status.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL of the directory search page.
 *
 * @return string
 */
function recruittech_get_directory_search_page_url() {
	return recruittech_get_shortcode_page_url( 'recruittech_directory_search', 'search' );
}

/**
 * URL of the profile view page for a given job seeker or company.
 *
 * @param string $type Either 'jobseeker' or 'company'.
 * @param int    $id   The job_seekers.id or companies.id row id.
 * @return string
 */
function recruittech_get_profile_view_url( $type, $id ) {
	$base_url = recruittech_get_shortcode_page_url( 'recruittech_profile_view', 'profile-view' );
	return add_query_arg(
		array(
			'type' => sanitize_key( $type ),
			'id'   => absint( $id ),
		),
		$base_url
	);
}

/**
 * Small "not signed in" / "nothing found" card, styled like the rest of
 * the plugin's empty/notice states.
 *
 * @param string $title   Heading text.
 * @param string $message Body text.
 * @param string $cta_url Optional button URL.
 * @param string $cta_label Optional button label.
 * @return string
 */
function recruittech_render_search_notice_card( $title, $message, $cta_url = '', $cta_label = '' ) {
	ob_start();
	?>
	<div class="card border-0 shadow-sm">
		<div class="card-body rt-search-empty">
			<i class="bi bi-search"></i>
			<h2 class="h4 mb-2"><?php echo esc_html( $title ); ?></h2>
			<p class="mb-4"><?php echo esc_html( $message ); ?></p>
			<?php if ( ! empty( $cta_url ) && ! empty( $cta_label ) ) : ?>
				<a href="<?php echo esc_url( $cta_url ); ?>" class="btn btn-primary"><?php echo esc_html( $cta_label ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the "Search People & Companies" shortcode.
 *
 * @return string
 */
function recruittech_directory_search_shortcode() {
	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<div class="container py-4">
			<?php
			echo wp_kses_post(
				recruittech_render_search_notice_card(
					'Please Log In',
					'You need to be logged in to search for job seekers and companies on RecruitTech.',
					recruittech_get_shortcode_page_url( 'recruittech_login', 'test/login' ),
					'Login'
				)
			);
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	global $wpdb;
	$job_seekers_table = $wpdb->prefix . 'recruitech_job_seekers';
	$companies_table   = $wpdb->prefix . 'recruitech_companies';

	$search_text = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$search_type = isset( $_GET['search_type'] ) ? sanitize_key( wp_unslash( $_GET['search_type'] ) ) : 'all';
	if ( ! in_array( $search_type, array( 'all', 'jobseekers', 'companies' ), true ) ) {
		$search_type = 'all';
	}

	$job_seeker_results = array();
	$company_results    = array();

	if ( '' !== $search_text ) {
		$like = '%' . $wpdb->esc_like( $search_text ) . '%';

		if ( 'companies' !== $search_type ) {
			$job_seeker_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, user_id, full_name, summary, skills, profile_photo
					 FROM {$job_seekers_table}
					 WHERE full_name LIKE %s AND verification_status = 'approved'
					 ORDER BY full_name ASC
					 LIMIT 12",
					$like
				),
				ARRAY_A
			);
		}

		if ( 'jobseekers' !== $search_type ) {
			$company_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, user_id, company_name, description, website, logo_url
					 FROM {$companies_table}
					 WHERE company_name LIKE %s AND verification_status = 'approved'
					 ORDER BY company_name ASC
					 LIMIT 12",
					$like
				),
				ARRAY_A
			);
		}
	}

	$total_results = count( $job_seeker_results ) + count( $company_results );

	ob_start();
	?>
	<div class="container py-4">
		<div class="rt-page-header">
			<div class="rt-page-header-title d-flex align-items-center gap-3">
				<span class="rt-avatar"><i class="bi bi-people-fill"></i></span>
				<div>
					<h1>Search People & Companies</h1>
					<p>Find a job seeker or a company by name and view their public profile.</p>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm mb-4">
			<div class="card-body">
				<form method="get" class="rt-search-form row g-3 align-items-end">
					<div class="col-12 col-md-6">
						<label for="rt_search_q" class="form-label">Name</label>
						<input type="text" class="form-control" id="rt_search_q" name="q" placeholder="Search by job seeker or company name&hellip;" value="<?php echo esc_attr( $search_text ); ?>">
					</div>
					<div class="col-12 col-md-4">
						<label for="rt_search_type" class="form-label">Search In</label>
						<select class="form-select" id="rt_search_type" name="search_type">
							<option value="all" <?php selected( $search_type, 'all' ); ?>>Job Seekers & Companies</option>
							<option value="jobseekers" <?php selected( $search_type, 'jobseekers' ); ?>>Job Seekers Only</option>
							<option value="companies" <?php selected( $search_type, 'companies' ); ?>>Companies Only</option>
						</select>
					</div>
					<div class="col-12 col-md-2">
						<button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button>
					</div>
				</form>
			</div>
		</div>

		<?php if ( '' === $search_text ) : ?>
			<?php echo wp_kses_post( recruittech_render_search_notice_card( 'Start Searching', 'Type a name above to find a job seeker or a company on RecruitTech.' ) ); ?>
		<?php elseif ( 0 === $total_results ) : ?>
			<?php echo wp_kses_post( recruittech_render_search_notice_card( 'No Results Found', 'No verified job seeker or company matched "' . $search_text . '". Try a different name.' ) ); ?>
		<?php else : ?>

			<?php if ( ! empty( $job_seeker_results ) ) : ?>
				<h2 class="h5 mb-3"><i class="bi bi-person-badge"></i> Job Seekers</h2>
				<div class="row g-4 mb-4">
					<?php foreach ( $job_seeker_results as $job_seeker ) : ?>
						<?php
						$avatar_url = function_exists( 'recruittech_get_job_seeker_avatar_url' ) ? recruittech_get_job_seeker_avatar_url( absint( $job_seeker['user_id'] ) ) : '';
						$teaser = ! empty( $job_seeker['summary'] ) ? wp_trim_words( $job_seeker['summary'], 16 ) : ( ! empty( $job_seeker['skills'] ) ? wp_trim_words( $job_seeker['skills'], 10 ) : 'RecruitTech job seeker.' );
						?>
						<div class="col-12 col-md-6 col-lg-4">
							<div class="card rt-search-result-card border-0 shadow-sm">
								<div class="card-body d-flex flex-column">
									<div class="d-flex align-items-center gap-3 mb-3">
										<?php if ( ! empty( $avatar_url ) ) : ?>
											<span class="rt-avatar rt-avatar-sm"><img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $job_seeker['full_name'] ); ?> avatar"></span>
										<?php else : ?>
											<?php echo wp_kses_post( recruittech_get_placeholder_avatar() ); ?>
										<?php endif; ?>
										<div>
											<span class="rt-search-result-type text-primary">Job Seeker</span>
											<h5 class="card-title mb-0"><?php echo esc_html( $job_seeker['full_name'] ); ?></h5>
										</div>
									</div>
									<p class="text-muted small mb-4"><?php echo esc_html( $teaser ); ?></p>
									<a href="<?php echo esc_url( recruittech_get_profile_view_url( 'jobseeker', $job_seeker['id'] ) ); ?>" class="btn btn-outline-primary mt-auto">View Profile</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $company_results ) ) : ?>
				<h2 class="h5 mb-3"><i class="bi bi-building"></i> Companies</h2>
				<div class="row g-4">
					<?php foreach ( $company_results as $company ) : ?>
						<?php
						$logo_url = function_exists( 'recruittech_get_company_logo_url' ) ? recruittech_get_company_logo_url( absint( $company['user_id'] ) ) : '';
						$teaser = ! empty( $company['description'] ) ? wp_trim_words( $company['description'], 16 ) : 'RecruitTech verified company.';
						?>
						<div class="col-12 col-md-6 col-lg-4">
							<div class="card rt-search-result-card border-0 shadow-sm">
								<div class="card-body d-flex flex-column">
									<div class="d-flex align-items-center gap-3 mb-3">
										<?php if ( ! empty( $logo_url ) ) : ?>
											<span class="rt-avatar rt-avatar-sm"><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company['company_name'] ); ?> logo"></span>
										<?php else : ?>
											<?php echo wp_kses_post( recruittech_get_placeholder_company_logo() ); ?>
										<?php endif; ?>
										<div>
											<span class="rt-search-result-type text-success">Company</span>
											<h5 class="card-title mb-0"><?php echo esc_html( $company['company_name'] ); ?></h5>
										</div>
									</div>
									<p class="text-muted small mb-4"><?php echo esc_html( $teaser ); ?></p>
									<a href="<?php echo esc_url( recruittech_get_profile_view_url( 'company', $company['id'] ) ); ?>" class="btn btn-outline-primary mt-auto">View Profile</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'recruittech_directory_search', 'recruittech_directory_search_shortcode' );

/**
 * Render the "not found" profile view card (used for both types).
 *
 * @return string
 */
function recruittech_render_profile_not_found() {
	ob_start();
	?>
	<div class="container py-4">
		<div class="card border-0 shadow-sm">
			<div class="card-body p-4 p-md-5 text-center">
				<h1 class="h3 mb-3">Profile Not Found</h1>
				<p class="text-muted mb-4">This profile could not be found or is not available to view.</p>
				<a href="<?php echo esc_url( recruittech_get_directory_search_page_url() ); ?>" class="btn btn-primary">Back to Search</a>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render a job seeker's public profile view.
 *
 * @param int $job_seeker_id job_seekers.id row id.
 * @return string
 */
function recruittech_render_job_seeker_profile_view( $job_seeker_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'recruitech_job_seekers';

	$job_seeker = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d AND verification_status = 'approved' LIMIT 1", $job_seeker_id ),
		ARRAY_A
	);

	if ( empty( $job_seeker ) ) {
		return recruittech_render_profile_not_found();
	}

	$full_name  = isset( $job_seeker['full_name'] ) ? $job_seeker['full_name'] : '';
	$summary    = isset( $job_seeker['summary'] ) ? $job_seeker['summary'] : '';
	$skills     = isset( $job_seeker['skills'] ) ? $job_seeker['skills'] : '';
	$experience = isset( $job_seeker['experience'] ) ? $job_seeker['experience'] : '';
	$phone      = isset( $job_seeker['phone'] ) ? $job_seeker['phone'] : '';
	$member_since = ! empty( $job_seeker['created_at'] ) ? date_i18n( 'F Y', strtotime( $job_seeker['created_at'] ) ) : '';

	$avatar_url = function_exists( 'recruittech_get_job_seeker_avatar_url' ) ? recruittech_get_job_seeker_avatar_url( absint( $job_seeker['user_id'] ) ) : '';

	// Contact details (like phone number) are only shown to logged-in
	// company accounts or to the job seeker viewing their own profile,
	// keeping the same visibility a recruiter already has on the
	// Company Applications page rather than exposing it to any visitor.
	$current_user_id = get_current_user_id();
	$is_own_profile = $current_user_id && absint( $job_seeker['user_id'] ) === absint( $current_user_id );
	$viewer_is_company = function_exists( 'recruittech_is_company_user' ) && recruittech_is_company_user();
	$can_see_contact_details = $is_own_profile || $viewer_is_company;

	$skills_list = array();
	if ( ! empty( $skills ) ) {
		foreach ( preg_split( '/[,\n]+/', $skills ) as $skill ) {
			$skill = trim( $skill );
			if ( '' !== $skill ) {
				$skills_list[] = $skill;
			}
		}
	}

	ob_start();
	?>
	<div class="container py-4">
		<div class="rt-profile-hero">
			<?php if ( ! empty( $avatar_url ) ) : ?>
				<span class="rt-avatar rt-avatar-xl"><img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?> avatar"></span>
			<?php else : ?>
				<?php echo wp_kses_post( recruittech_get_placeholder_avatar( 'rt-avatar-xl' ) ); ?>
			<?php endif; ?>
			<div class="rt-profile-hero-info">
				<span class="badge bg-primary mb-2"><i class="bi bi-person-badge"></i> Job Seeker</span>
				<h1><?php echo esc_html( $full_name ); ?></h1>
				<p><i class="bi bi-patch-check-fill text-success"></i> Verified profile<?php echo ! empty( $member_since ) ? ' &middot; Member since ' . esc_html( $member_since ) : ''; ?></p>
			</div>
		</div>

		<div class="card border-0 shadow-sm">
			<div class="card-body p-4">
				<div class="rt-profile-section">
					<h2 class="rt-profile-section-title"><i class="bi bi-file-person"></i> Summary</h2>
					<p class="mb-0"><?php echo ! empty( $summary ) ? wp_kses_post( wpautop( $summary ) ) : '<span class="text-muted">Not provided.</span>'; ?></p>
				</div>

				<div class="rt-profile-section">
					<h2 class="rt-profile-section-title"><i class="bi bi-stars"></i> Skills</h2>
					<?php if ( ! empty( $skills_list ) ) : ?>
						<div>
							<?php foreach ( $skills_list as $skill ) : ?>
								<span class="rt-profile-skill-pill"><?php echo esc_html( $skill ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="text-muted mb-0">Not provided.</p>
					<?php endif; ?>
				</div>

				<div class="rt-profile-section">
					<h2 class="rt-profile-section-title"><i class="bi bi-briefcase"></i> Experience</h2>
					<p class="mb-0"><?php echo ! empty( $experience ) ? wp_kses_post( wpautop( $experience ) ) : '<span class="text-muted">Not provided.</span>'; ?></p>
				</div>

				<?php if ( $can_see_contact_details ) : ?>
					<div class="rt-profile-section mb-0">
						<h2 class="rt-profile-section-title"><i class="bi bi-telephone"></i> Contact</h2>
						<p class="mb-0"><?php echo ! empty( $phone ) ? esc_html( $phone ) : '<span class="text-muted">Not provided.</span>'; ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render a company's public profile view.
 *
 * @param int $company_id companies.id row id.
 * @return string
 */
function recruittech_render_company_profile_view( $company_id ) {
	global $wpdb;
	$companies_table = $wpdb->prefix . 'recruitech_companies';
	$jobs_table      = $wpdb->prefix . 'recruitech_jobs';

	$company = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$companies_table} WHERE id = %d AND verification_status = 'approved' LIMIT 1", $company_id ),
		ARRAY_A
	);

	if ( empty( $company ) ) {
		return recruittech_render_profile_not_found();
	}

	$company_name = isset( $company['company_name'] ) ? $company['company_name'] : '';
	$description  = isset( $company['description'] ) ? $company['description'] : '';
	$website      = isset( $company['website'] ) ? $company['website'] : '';
	$member_since = ! empty( $company['created_at'] ) ? date_i18n( 'F Y', strtotime( $company['created_at'] ) ) : '';
	$logo_url     = function_exists( 'recruittech_get_company_logo_url' ) ? recruittech_get_company_logo_url( absint( $company['user_id'] ) ) : '';

	// Only truly open positions are listed here, the same rule Browse Jobs
	// uses: Published status and a deadline that has not passed yet.
	$open_jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, job_title, location, job_type
			 FROM {$jobs_table}
			 WHERE company_id = %d AND status = 'Published' AND ( deadline IS NULL OR deadline = '' OR deadline >= CURDATE() )
			 ORDER BY created_at DESC
			 LIMIT 10",
			$company_id
		),
		ARRAY_A
	);

	$open_jobs_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$jobs_table}
			 WHERE company_id = %d AND status = 'Published' AND ( deadline IS NULL OR deadline = '' OR deadline >= CURDATE() )",
			$company_id
		)
	);

	$total_jobs_posted = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_table} WHERE company_id = %d AND status != 'Deleted'", $company_id )
	);

	ob_start();
	?>
	<div class="container py-4">
		<div class="rt-profile-hero">
			<?php if ( ! empty( $logo_url ) ) : ?>
				<span class="rt-avatar rt-avatar-xl"><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?> logo"></span>
			<?php else : ?>
				<?php echo wp_kses_post( recruittech_get_placeholder_company_logo( 'rt-avatar-xl' ) ); ?>
			<?php endif; ?>
			<div class="rt-profile-hero-info">
				<span class="badge bg-success mb-2"><i class="bi bi-building-check"></i> Verified Company</span>
				<h1><?php echo esc_html( $company_name ); ?></h1>
				<p>
					<?php if ( ! empty( $website ) ) : ?>
						<a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-globe"></i> <?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) ); ?></a>
					<?php endif; ?>
					<?php echo ! empty( $member_since ) ? ( ! empty( $website ) ? ' &middot; ' : '' ) . 'Member since ' . esc_html( $member_since ) : ''; ?>
				</p>
			</div>
		</div>

		<div class="card border-0 shadow-sm mb-4">
			<div class="card-body p-4">
				<div class="rt-profile-section">
					<h2 class="rt-profile-section-title"><i class="bi bi-building"></i> About</h2>
					<p class="mb-0"><?php echo ! empty( $description ) ? wp_kses_post( wpautop( $description ) ) : '<span class="text-muted">Not provided.</span>'; ?></p>
				</div>

				<div class="rt-profile-section mb-0">
					<h2 class="rt-profile-section-title"><i class="bi bi-graph-up"></i> Hiring Activity</h2>
					<div class="rt-profile-stat-row">
						<div class="rt-profile-stat">
							<div class="rt-profile-stat-value"><?php echo esc_html( $open_jobs_count ); ?></div>
							<div class="rt-profile-stat-label">Open Positions</div>
						</div>
						<div class="rt-profile-stat">
							<div class="rt-profile-stat-value"><?php echo esc_html( $total_jobs_posted ); ?></div>
							<div class="rt-profile-stat-label">Jobs Posted (All Time)</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm">
			<div class="card-body p-4">
				<h2 class="rt-profile-section-title"><i class="bi bi-briefcase"></i> Currently Open Positions</h2>
				<?php if ( empty( $open_jobs ) ) : ?>
					<p class="text-muted mb-0">This company has no open positions right now.</p>
				<?php else : ?>
					<div class="list-group">
						<?php foreach ( $open_jobs as $open_job ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'job_id', absint( $open_job['id'] ), home_url( '/job-details/' ) ) ); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
								<span>
									<strong><?php echo esc_html( $open_job['job_title'] ); ?></strong>
									<?php if ( ! empty( $open_job['location'] ) ) : ?>
										<span class="text-muted small"> &middot; <?php echo esc_html( $open_job['location'] ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( ! empty( $open_job['job_type'] ) ) : ?>
									<span class="badge bg-primary"><?php echo esc_html( $open_job['job_type'] ); ?></span>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the "Profile View" shortcode (job seeker or company, chosen by
 * the `type` query argument, coming from the search results page).
 *
 * @return string
 */
function recruittech_profile_view_shortcode() {
	if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<div class="container py-4">
			<?php
			echo wp_kses_post(
				recruittech_render_search_notice_card(
					'Please Log In',
					'You need to be logged in to view RecruitTech profiles.',
					recruittech_get_shortcode_page_url( 'recruittech_login', 'test/login' ),
					'Login'
				)
			);
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
	$id   = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

	if ( $id <= 0 || ! in_array( $type, array( 'jobseeker', 'company' ), true ) ) {
		return recruittech_render_profile_not_found();
	}

	if ( 'company' === $type ) {
		return recruittech_render_company_profile_view( $id );
	}

	return recruittech_render_job_seeker_profile_view( $id );
}
add_shortcode( 'recruittech_profile_view', 'recruittech_profile_view_shortcode' );
