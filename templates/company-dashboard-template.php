<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$company_profile = function_exists( 'recruittech_get_company_profile_by_user_id' ) ? recruittech_get_company_profile_by_user_id( $current_user->ID ) : array();
$company_name = ! empty( $company_profile['company_name'] ) ? $company_profile['company_name'] : ( ! empty( $current_user->display_name ) ? $current_user->display_name : 'Company' );
$company_logo_url = function_exists( 'recruittech_get_company_logo_url' ) ? recruittech_get_company_logo_url( $current_user->ID ) : '';
$status_label = ! empty( $verification_status ) ? ucfirst( sanitize_text_field( $verification_status ) ) : 'Pending';
$status_message = '';
$status_class = 'notice notice-info';

if ( 'approved' === $verification_status ) {
	$status_message = 'Your company account has been verified successfully.';
	$status_class = 'notice notice-success';
} elseif ( 'rejected' === $verification_status ) {
	$status_message = 'Your company verification request has been rejected. Please update your company profile and submit it again.';
	$status_class = 'notice notice-error';
} else {
	$status_message = 'Your company account is currently under administrator review. You will be able to use all recruitment features after your company has been approved.';
}

$action_disabled = ! empty( $is_pending ) || ! empty( $is_rejected );
$button_label = ! empty( $action_disabled ) ? 'Disabled' : 'Open';

$badge_color = '#f6c23e';
if ( 'approved' === $verification_status ) {
	$badge_color = '#1e7e34';
} elseif ( 'rejected' === $verification_status ) {
	$badge_color = '#c82333';
}

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

$company_avatar_markup = '';
if ( ! empty( $company_logo_url ) ) {
	$company_avatar_markup = '<span class="rt-avatar rt-avatar-lg"><img src="' . esc_url( $company_logo_url ) . '" alt="' . esc_attr( $company_name ) . ' logo"></span>';
} else {
	$company_avatar_markup = recruittech_get_placeholder_company_logo( 'rt-avatar-lg' );
}

$company_badge_markup = '<span class="badge ' . esc_attr( $status_badge_class ) . ' ms-2">' . esc_html( $status_label ) . '</span>';
?>

<div class="container py-4">
	<?php echo wp_kses_post( recruittech_render_dashboard_header( $company_avatar_markup, $company_name, 'Manage your jobs and applications from your dashboard.', $company_badge_markup ) ); ?>

	<div class="row mb-4">
		<div class="col-12">
			<div class="alert <?php echo esc_attr( $status_alert_class ); ?>">
				<p class="mb-1"><strong>Verification Status: <?php echo esc_html( $status_label ); ?></strong></p>
				<p class="mb-0"><?php echo esc_html( $status_message ); ?></p>
			</div>
		</div>
	</div>

	<div class="row g-4">
		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">Create New Job Posting</h5>
					<p class="card-text text-muted">Start a new recruiting campaign and publish a job opening.</p>
					<?php if ( ! empty( $action_disabled ) ) : ?>
						<button type="button" class="btn btn-secondary" disabled>Create New Job Posting</button>
					<?php else : ?>
						<a href="#" class="btn btn-primary">Open</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">Manage Existing Jobs</h5>
					<p class="card-text text-muted">Review, edit, and update your current job listings.</p>
					<?php if ( ! empty( $action_disabled ) ) : ?>
						<button type="button" class="btn btn-secondary" disabled>Manage Existing Jobs</button>
					<?php else : ?>
						<a href="#" class="btn btn-outline-primary">Manage</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">View Applications</h5>
					<p class="card-text text-muted">Track applicants and follow up on promising candidates.</p>
					<?php if ( ! empty( $action_disabled ) ) : ?>
						<button type="button" class="btn btn-secondary" disabled>View Applications</button>
					<?php else : ?>
						<a href="#" class="btn btn-outline-primary">View</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm">
				<div class="card-body">
					<h5 class="card-title">AI Recruitment Assistant</h5>
					<p class="card-text text-muted">Use AI-powered assistance to streamline candidate screening.</p>
					<?php if ( ! empty( $action_disabled ) ) : ?>
						<button type="button" class="btn btn-secondary" disabled>AI Recruitment Assistant</button>
					<?php else : ?>
						<a href="#" class="btn btn-outline-primary">Launch</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
				<div class="col-12 col-md-6 col-lg-3">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body">
							<h5 class="card-title">Notifications<?php echo ! empty( $unread_notifications_count ) ? ' (' . esc_html( $unread_notifications_count ) . ')' : ''; ?></h5>
							<p class="card-text text-muted">View your RecruitTech notifications.</p>
							<a href="<?php echo esc_url( recruittech_get_notifications_page_url() ); ?>" class="btn btn-outline-primary">Notifications</a>
						</div>
					</div>
				</div>
	</div>
</div>
