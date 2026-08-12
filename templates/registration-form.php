<?php
/**
 * RecruitTech Registration Form Template
 * Displays the registration form HTML
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="recruittech-login-shell">
	<div class="recruittech-login-layout">
		<div class="recruittech-login-branding" aria-hidden="true">
			<span class="recruittech-login-brand-mark">
				<i class="bi bi-briefcase-fill"></i> RecruitTech
			</span>

			<h2>Create your free account</h2>
			<p class="rt-tagline">Join RecruitTech as a job seeker or a company and get started in minutes.</p>

			<ul class="recruittech-login-features">
				<li><i class="bi bi-person-badge"></i> Build a profile that gets noticed</li>
				<li><i class="bi bi-lightning-charge"></i> Get matched to relevant openings instantly</li>
				<li><i class="bi bi-shield-check"></i> Verified companies, safe applications</li>
			</ul>
		</div>

		<div class="recruittech-login-panel">
			<div class="recruittech-registration-form card">
				<h2 class="card-title">Create account</h2>
				<p class="text-muted mb-4">It only takes a minute to get started.</p>

				<?php
				// Display error messages
				if ( ! empty( $errors ) ) {
					echo '<div class="alert alert-danger">';
					echo '<ul class="mb-0 ps-3">';
					foreach ( $errors as $error ) {
						echo '<li>' . esc_html( $error ) . '</li>';
					}
					echo '</ul>';
					echo '</div>';
				}
				?>

				<?php
				// Display success message
				if ( ! empty( $success ) ) {
					echo '<div class="alert alert-success">';
					echo '<p class="mb-0">' . esc_html( $success ) . '</p>';
					echo '</div>';
				}
				?>

				<form method="POST" class="recruittech-form">
					<?php wp_nonce_field( 'recruittech_register_action', 'recruittech_register_nonce' ); ?>

					<div class="form-group">
						<label for="full_name">Full Name</label>
						<input type="text" id="full_name" name="full_name" class="form-control" required />
					</div>

					<div class="form-group">
						<label for="username">Username</label>
						<input type="text" id="username" name="username" class="form-control" required />
					</div>

					<div class="form-group">
						<label for="email">Email</label>
						<input type="email" id="email" name="email" class="form-control" required />
					</div>

					<div class="form-group">
						<label for="password">Password</label>
						<input type="password" id="password" name="password" class="form-control" required />
						<p class="form-help">At least 8 characters, one uppercase letter, one lowercase letter, one number, and one special character.</p>
					</div>

					<div class="form-group">
						<label for="confirm_password">Confirm Password</label>
						<input type="password" id="confirm_password" name="confirm_password" class="form-control" required />
					</div>

					<div class="form-group">
						<label for="role">Select Your Role</label>
						<select id="role" name="role" class="form-select" required>
							<option value="">-- Select a Role --</option>
							<option value="company">Company</option>
							<option value="job_seeker">Job Seeker</option>
						</select>
					</div>

					<button type="submit" class="btn btn-primary w-100">Register</button>
				</form>
			</div>
		</div>
	</div>
</div>
