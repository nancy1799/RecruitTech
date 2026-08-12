<?php
/**
 * RecruitTech User Roles
 * Manages custom WordPress user roles for companies and job seekers
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create custom user roles on plugin activation
 */
function recruittech_create_roles() {
	// Create "company" role
	add_role(
		'company',
		'Company',
		array(
			'read' => true,
		)
	);

	// Create "job_seeker" role
	add_role(
		'job_seeker',
		'Job Seeker',
		array(
			'read' => true,
		)
	);
}

/**
 * Remove custom user roles on plugin uninstall
 */
function recruittech_remove_roles() {
	// Remove "company" role
	remove_role( 'company' );

	// Remove "job_seeker" role
	remove_role( 'job_seeker' );
}

