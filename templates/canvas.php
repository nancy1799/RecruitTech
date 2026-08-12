<?php
/**
 * RecruitTech Canvas Template
 *
 * This is a full, self-contained page template used for every RecruitTech
 * frontend page (see recruittech_template_include() in recruittech.php).
 * It intentionally does NOT call get_header() / get_footer(), so the
 * active theme's header (with the site title/menu) and footer (with the
 * blog info/widgets) never appear on RecruitTech pages, and the page
 * title is not printed either. Instead, RecruitTech's own navbar and
 * footer (templates/partials/navbar.php and footer.php) are used, and the
 * content stretches the full width of the page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'recruittech-canvas' ); ?>>
	<?php wp_body_open(); ?>

	<div class="recruittech-app">
		<?php include RECRUITTECH_PLUGIN_PATH . 'templates/partials/navbar.php'; ?>

		<main class="recruittech-main" id="rt-main-content">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</main>

		<?php include RECRUITTECH_PLUGIN_PATH . 'templates/partials/footer.php'; ?>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
