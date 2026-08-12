<?php
/**
 * RecruitTech Login Form Template
 * Displays the login form HTML
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

			<h2>Find the right job. Hire the right people.</h2>
			<p class="rt-tagline">One platform for job seekers and companies to connect, apply, and hire &mdash; faster.</p>

			<ul class="recruittech-login-features">
				<li><i class="bi bi-search"></i> Browse thousands of verified job openings</li>
				<li><i class="bi bi-send-check"></i> Apply with one click and track every application</li>
				<li><i class="bi bi-building-check"></i> Hire from a pool of ready, matched candidates</li>
			</ul>
		</div>

		<div class="recruittech-login-panel">
			<div class="recruittech-login-form card">
				<h2 class="card-title">Welcome back</h2>
				<p class="text-muted mb-4">Log in to continue to your RecruitTech account.</p>

				<?php
				// Display success message
				if ( ! empty( $success ) ) {
					echo '<div class="alert alert-success">';
					echo '<p class="mb-0">' . esc_html( $success ) . '</p>';
					echo '</div>';
				}
				?>

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

				<form method="POST" class="recruittech-form">
					<?php wp_nonce_field( 'recruittech_login_action', 'recruittech_login_nonce' ); ?>

					<div class="form-group">
						<label for="user_login">Username or Email</label>
						<input type="text" id="user_login" name="user_login" class="form-control" required />
					</div>

					<div class="form-group">
						<label for="password">Password</label>
						<input type="password" id="password" name="password" class="form-control" required />
					</div>

					<div class="form-group d-flex align-items-center justify-content-between">
						<label for="remember" class="d-flex align-items-center gap-2 mb-0">
							<input type="checkbox" id="remember" name="remember" value="1" />
							<span>Remember Me</span>
						</label>
					</div>

					<button type="submit" class="btn btn-primary w-100">Login</button>
				</form>
			</div>
		</div>
	</div>
</div>
