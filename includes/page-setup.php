<?php
/**
 * RecruitTech Page Setup
 * Automatically creates all the WordPress pages the plugin needs
 * (one page per frontend shortcode) so the site owner never has to
 * create them by hand.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the full list of pages RecruitTech needs, one per frontend shortcode.
 *
 * Each entry defines the shortcode that must live on the page, the title
 * and slug to use when the page is created, and (optionally) the slug of
 * a parent page. Slugs match the URLs already hard-coded across the
 * plugin (e.g. recruittech_get_user_dashboard_url(), login/logout
 * redirects, etc.) so links and redirects keep working.
 *
 * @return array[]
 */
function recruittech_get_page_definitions() {
	return array(
		array(
			'shortcode' => 'recruittech_home',
			'key'       => 'home',
			'title'     => 'Home',
			'slug'      => 'home',
		),
		array(
			'shortcode' => 'recruittech_login',
			'title'     => 'Login',
			'slug'      => 'login',
			'parent'    => 'test',
		),
		array(
			'shortcode' => 'recruittech_registration',
			'title'     => 'Register',
			'slug'      => 'registration',
		),
		array(
			'shortcode' => 'recruittech_logout',
			'title'     => 'Logout',
			'slug'      => 'logout',
		),
		array(
			'shortcode' => 'recruittech_company_dashboard',
			'title'     => 'Company Dashboard',
			'slug'      => 'company-dashboard',
		),
		array(
			'shortcode' => 'recruittech_company_create_job',
			'title'     => 'Create Job',
			'slug'      => 'create-job',
		),
		array(
			'shortcode' => 'recruittech_company_jobs',
			'title'     => 'My Jobs',
			'slug'      => 'my-jobs',
		),
		array(
			'shortcode' => 'recruittech_company_applications',
			'title'     => 'Company Applications',
			'slug'      => 'company-applications',
		),
		array(
			'shortcode' => 'recruittech_company_profile',
			'title'     => 'Company Profile',
			'slug'      => 'company-profile',
		),
		array(
			'shortcode' => 'recruittech_company_documents',
			'title'     => 'Company Documents',
			'slug'      => 'company-documents',
		),
		array(
			'shortcode' => 'recruittech_job_seeker_dashboard',
			'title'     => 'Job Seeker Dashboard',
			'slug'      => 'job-seeker-dashboard',
		),
		array(
			'shortcode' => 'recruittech_job_seeker_profile',
			'title'     => 'Job Seeker Profile',
			'slug'      => 'job-seeker-profile',
		),
		array(
			'shortcode' => 'recruittech_my_applications',
			'title'     => 'My Applications',
			'slug'      => 'my-applications',
		),
		array(
			'shortcode' => 'recruittech_browse_jobs',
			'title'     => 'Browse Jobs',
			'slug'      => 'jobs',
		),
		array(
			'shortcode' => 'recruittech_job_details',
			'title'     => 'Job Details',
			'slug'      => 'job-details',
		),
		array(
			'shortcode' => 'recruittech_notifications',
			'title'     => 'Notifications',
			'slug'      => 'notifications',
		),
		array(
			'shortcode' => 'recruittech_directory_search',
			'title'     => 'Search',
			'slug'      => 'search',
		),
		array(
			'shortcode' => 'recruittech_profile_view',
			'title'     => 'Profile',
			'slug'      => 'profile-view',
		),
		array(
			'shortcode' => 'recruittech_my_subscription',
			'title'     => 'My Subscription',
			'slug'      => 'my-subscription',
		),
	);
}

/**
 * Get the stored RecruitTech page mapping from the options table.
 *
 * @return array<int>
 */
function recruittech_get_stored_page_map() {
	$stored_pages = get_option( 'recruittech_pages', array() );
	if ( ! is_array( $stored_pages ) ) {
		return array();
	}

	$sanitized_pages = array();
	foreach ( $stored_pages as $key => $value ) {
		$sanitized_pages[ sanitize_key( $key ) ] = absint( $value );
	}

	return $sanitized_pages;
}

/**
 * Resolve an existing RecruitTech page by stored ID, slug, or title.
 *
 * @param array $definition Page definition.
 * @param array $stored_pages Stored page mapping.
 * @return WP_Post|null
 */
function recruittech_resolve_existing_page( $definition, $stored_pages ) {
	$shortcode = isset( $definition['shortcode'] ) ? sanitize_key( $definition['shortcode'] ) : '';
	$key = isset( $definition['key'] ) ? sanitize_key( $definition['key'] ) : $shortcode;
	$slug = isset( $definition['slug'] ) ? sanitize_title( $definition['slug'] ) : '';
	$title = isset( $definition['title'] ) ? sanitize_text_field( $definition['title'] ) : '';

	if ( ! empty( $key ) && isset( $stored_pages[ $key ] ) ) {
		$page_id = absint( $stored_pages[ $key ] );
		if ( $page_id > 0 ) {
			$page = get_post( $page_id );
			if ( $page instanceof WP_Post ) {
				return $page;
			}
		}
	}

	if ( ! empty( $slug ) ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			return $page;
		}
	}

	if ( ! empty( $title ) ) {
		$page = get_page_by_title( $title );
		if ( $page instanceof WP_Post ) {
			return $page;
		}
	}

	return null;
}

/**
 * Get the URL of the page that contains the given shortcode, falling
 * back to the slug RecruitTech creates that page with if it can't be
 * found yet (e.g. before the page has been created).
 *
 * @param string $shortcode     Shortcode tag to look for.
 * @param string $fallback_slug Slug to fall back on.
 * @return string
 */
function recruittech_get_shortcode_page_url( $shortcode, $fallback_slug = '' ) {
	$pages = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, $shortcode ) ) {
			return get_permalink( $page );
		}
	}

	return $fallback_slug ? home_url( '/' . ltrim( $fallback_slug, '/' ) . '/' ) : home_url( '/' );
}

/**
 * Make sure a (simple, empty) parent page exists so that child pages such
 * as /test/login/ can be created underneath it. Returns the parent page ID.
 *
 * @param string $slug Parent page slug.
 * @return int
 */
function recruittech_ensure_parent_page( $slug ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	return (int) wp_insert_post(
		array(
			'post_title'   => ucfirst( $slug ),
			'post_name'    => $slug,
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
}

/**
 * Create every RecruitTech page that does not exist yet.
 * Safe to run multiple times: existing pages are never duplicated
 * or overwritten.
 */
function recruittech_create_recruittech_pages() {
	if ( ! function_exists( 'get_posts' ) || ! function_exists( 'wp_insert_post' ) ) {
		return;
	}

	$stored_pages = recruittech_get_stored_page_map();

	foreach ( recruittech_get_page_definitions() as $definition ) {
		$existing_page = recruittech_resolve_existing_page( $definition, $stored_pages );
		if ( $existing_page instanceof WP_Post ) {
			$key = isset( $definition['key'] ) ? sanitize_key( $definition['key'] ) : sanitize_key( $definition['shortcode'] );
			$stored_pages[ $key ] = (int) $existing_page->ID;
			continue;
		}

		$page_args = array(
			'post_title'   => $definition['title'],
			'post_name'    => $definition['slug'],
			'post_content' => '[' . $definition['shortcode'] . ']',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( ! empty( $definition['parent'] ) ) {
			$page_args['post_parent'] = recruittech_ensure_parent_page( $definition['parent'] );
		}

		$page_id = wp_insert_post( $page_args );
		if ( ! is_wp_error( $page_id ) && $page_id ) {
			$key = isset( $definition['key'] ) ? sanitize_key( $definition['key'] ) : sanitize_key( $definition['shortcode'] );
			$stored_pages[ $key ] = (int) $page_id;
		}
	}

	update_option( 'recruittech_pages', $stored_pages );
}

/**
 * Safety net: if the plugin files were updated in place (no
 * deactivate/reactivate cycle), make sure every page still gets created
 * the next time an administrator loads the site or wp-admin, without
 * ever duplicating pages that already exist.
 */
function recruittech_maybe_create_recruittech_pages() {
	if ( ! current_user_can( 'manage_options' ) && ! is_admin() ) {
		return;
	}

	$installed_version = get_option( 'recruittech_pages_version' );

	if ( RECRUITTECH_VERSION === $installed_version ) {
		return;
	}

	recruittech_create_recruittech_pages();
	update_option( 'recruittech_pages_version', RECRUITTECH_VERSION );
}
add_action( 'init', 'recruittech_maybe_create_recruittech_pages' );
