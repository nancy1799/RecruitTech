<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$job_seeker_name = ! empty( $current_user->display_name ) ? $current_user->display_name : 'Job Seeker';
$job_seeker_avatar_url = function_exists( 'recruittech_get_job_seeker_avatar_url' ) ? recruittech_get_job_seeker_avatar_url( $current_user->ID ) : '';
$status_label = 'Not Submitted';
$status_message = 'Complete your profile to start the verification process.';
$status_class = 'notice notice-warning';
$badge_style = '';

if ( ! empty( $has_profile ) ) {
	$status_label = ! empty( $verification_status ) ? ucfirst( sanitize_text_field( $verification_status ) ) : 'Pending';
	if ( 'approved' === $verification_status ) {
		$status_label = '🟢 Approved';
	} elseif ( 'rejected' === $verification_status ) {
		$status_label = '🔴 Rejected';
	} else {
		$status_label = '🟡 Pending';
	}

	if ( 'approved' === $verification_status ) {
		$status_message = 'Your profile has been verified successfully.';
		$status_class = 'notice notice-success';
	} elseif ( 'rejected' === $verification_status ) {
		$status_message = 'Your verification request was rejected. Please update your identity information and submit it again.';
		$status_class = 'notice notice-error';
	} else {
		$status_message = 'Your profile is awaiting administrator verification.';
		$status_class = 'notice notice-info';
	}

	$badge_color = '#f6c23e';
	if ( 'approved' === $verification_status ) {
		$badge_color = '#1e7e34';
	} elseif ( 'rejected' === $verification_status ) {
		$badge_color = '#c82333';
	}

	$badge_style = 'display:inline-block;padding:0.25rem 0.6rem;font-size:0.85rem;font-weight:700;line-height:1;color:#fff;border-radius:999px;background:' . esc_attr( $badge_color ) . ';margin-left:0.75rem;';
}

$action_disabled = empty( $has_profile ) || ! empty( $is_pending ) || ! empty( $is_rejected );

$status_badge_class = 'bg-warning';
if ( 'approved' === $verification_status ) {
	$status_badge_class = 'bg-success';
} elseif ( 'rejected' === $verification_status ) {
	$status_badge_class = 'bg-danger';
}

$status_alert_class = 'alert-warning';
if ( 'approved' === $verification_status ) {
	$status_alert_class = 'alert-success';
} elseif ( 'rejected' === $verification_status ) {
	$status_alert_class = 'alert-danger';
}

$job_seeker_avatar_markup = '';
if ( ! empty( $job_seeker_avatar_url ) ) {
	$job_seeker_avatar_markup = '<span class="rt-avatar rt-avatar-lg"><img src="' . esc_url( $job_seeker_avatar_url ) . '" alt="' . esc_attr( $job_seeker_name ) . ' avatar"></span>';
} else {
	$job_seeker_avatar_markup = recruittech_get_placeholder_avatar( 'rt-avatar-lg' );
}

$job_seeker_badge_markup = '<span class="badge ' . esc_attr( $status_badge_class ) . ' ms-2">' . esc_html( ucfirst( $verification_status ? $verification_status : 'Not submitted' ) ) . '</span>';
?>

<div class="container py-4">
	<?php echo wp_kses_post( recruittech_render_dashboard_header( $job_seeker_avatar_markup, $job_seeker_name, 'Manage your applications and profile.', $job_seeker_badge_markup ) ); ?>

	<div class="row mb-4">
		<div class="col-12">
			<div class="alert <?php echo esc_attr( $status_alert_class ); ?>">
				<p class="mb-1"><strong>Verification Status: <?php echo esc_html( ucfirst( $verification_status ? $verification_status : 'Not Submitted' ) ); ?></strong></p>
				<p class="mb-0"><?php echo esc_html( $status_message ); ?></p>
			</div>
		</div>
	</div>

	<div class="row g-4">
		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">Update My Profile</h5>
					<p class="card-text text-muted">Keep your personal details and preferences current.</p>
					<a href="<?php echo esc_url( home_url( '/job-seeker-profile/' ) ); ?>" class="btn btn-primary">Edit Profile</a>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">Upload/Update CV</h5>
					<p class="card-text text-muted">Add or refresh your CV so employers can discover you.</p>
					<a href="<?php echo esc_url( home_url( '/job-seeker-profile/' ) ); ?>" class="btn btn-outline-primary">Upload CV</a>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">Browse Available Jobs</h5>
					<p class="card-text text-muted">Explore openings that match your skills and interests.</p>
					<a href="<?php echo esc_url( recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' ) ); ?>" class="btn btn-outline-primary">Browse Jobs</a>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">Track My Applications</h5>
					<p class="card-text text-muted">Monitor the status of each application you submitted.</p>
					<?php if ( ! empty( $action_disabled ) ) : ?>
						<button type="button" class="btn btn-secondary" disabled>View Applications</button>
					<?php else : ?>
						<a href="<?php echo esc_url( recruittech_get_my_applications_page_url() ); ?>" class="btn btn-outline-primary">View Applications</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

			<div class="col-12 col-md-6 col-lg-3">
				<div class="card h-100 border-0 shadow-sm">
					<div class="card-body">
						<h5 class="card-title">Notifications<?php echo ! empty( $unread_notifications_count ) ? ' (' . esc_html( $unread_notifications_count ) . ')' : ''; ?></h5>
						<p class="card-text text-muted">View your latest RecruitTech notifications.</p>
						<a href="<?php echo esc_url( recruittech_get_notifications_page_url() ); ?>" class="btn btn-outline-primary">Notifications</a>
					</div>
				</div>
			</div>
	</div>
</div>
