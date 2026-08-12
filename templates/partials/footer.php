<?php
/**
 * RecruitTech Shared Footer
 * Shown at the bottom of every RecruitTech frontend page (see canvas.php).
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rt_browse_jobs_url = recruittech_get_shortcode_page_url( 'recruittech_browse_jobs', 'jobs' );
$rt_login_url        = recruittech_get_shortcode_page_url( 'recruittech_login', 'test/login' );
$rt_register_url      = recruittech_get_shortcode_page_url( 'recruittech_registration', 'registration' );
?>
<footer class="rt-footer">
	<div class="container-fluid rt-footer-container">
		<div class="rt-footer-top">
			<div class="rt-footer-brand">
				<span class="rt-footer-brand-mark"><i class="bi bi-briefcase-fill"></i> RecruitTech</span>
				<p>Connecting job seekers and companies on one recruitment platform.</p>
			</div>

		</div>

		<div class="rt-footer-bottom">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> RecruitTech. All rights reserved.</p>
		</div>
	</div>
</footer>
