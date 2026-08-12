<?php
/**
 * RecruitTech User Logout
 * Handles user logout functionality using WordPress standard approach
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle logout requests before the shortcode renders.
 */
function recruittech_handle_logout_request() {
	if ( ! is_singular( array( 'page', 'post' ) ) ) {
		return;
	}

	$logout_page = get_page_by_path( 'logout' );
	if ( ! $logout_page || (int) get_queried_object_id() !== (int) $logout_page->ID ) {
		return;
	}

	if ( is_user_logged_in() ) {
		wp_logout();
	}

	wp_safe_redirect( recruittech_get_page_url( 'login' ) );
	exit;
}

add_action( 'template_redirect', 'recruittech_handle_logout_request' );

/**
 * Display logout shortcode.
 *
 * @return string
 */
function recruittech_display_logout() {
	return '';
}

/**
 * Register shortcode for logout
 */
function recruittech_register_logout_shortcode() {
	add_shortcode( 'recruittech_logout', 'recruittech_display_logout' );
}

add_action( 'init', 'recruittech_register_logout_shortcode' );
