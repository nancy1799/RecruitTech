<?php
/**
 * RecruitTech Notifications Helper
 *
 * Internal helper for inserting notifications into the RecruitTech notifications table.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insert a new notification record into the RecruitTech notifications table.
 *
 * @param int         $user_id        WordPress user ID.
 * @param string      $title          Notification title.
 * @param string      $message        Notification message.
 * @param string      $type           Notification type.
 * @param int|null    $application_id Optional application ID reference.
 * @param int|null    $job_id         Optional job ID reference.
 *
 * @return bool True on success, false on failure.
 */
function recruittech_add_notification( $user_id, $title, $message, $type, $application_id = null, $job_id = null ) {
	global $wpdb;

	// Validate required values.
	$user_id = absint( $user_id );
	$title   = sanitize_text_field( $title );
	$message = sanitize_textarea_field( $message );
	$type    = sanitize_text_field( $type );

	if ( 0 === $user_id || '' === $title || '' === $message || '' === $type ) {
		return false;
	}

	$application_id = null !== $application_id ? absint( $application_id ) : null;
	$job_id         = null !== $job_id ? absint( $job_id ) : null;

	$related_id = null;
	if ( $application_id ) {
		$related_id = $application_id;
	} elseif ( $job_id ) {
		$related_id = $job_id;
	}

	$table_name = $wpdb->prefix . 'recruitech_notifications';
	$data       = array(
		'user_id'  => $user_id,
		'title'    => $title,
		'message'  => $message,
		'type'     => $type,
		'is_read'  => 0,
	);
	$format     = array( '%d', '%s', '%s', '%s', '%d' );

	if ( null !== $related_id ) {
		$data['related_id'] = $related_id;
		$format[]            = '%d';
	}

	$inserted = $wpdb->insert( $table_name, $data, $format );

	return false !== $inserted;
}

/**
 * Send a RecruitTech email notification to a WordPress user.
 *
 * @param int    $user_id WordPress user ID.
 * @param string $subject Email subject.
 * @param string $message Plain-text email body.
 * @return bool True if wp_mail() accepted the email, false otherwise.
 */
function recruittech_send_email( $user_id, $subject, $message ) {
	$user_id = absint( $user_id );
	if ( 0 === $user_id ) {
		return false;
	}

	$user = get_userdata( $user_id );
	if ( ! $user || empty( $user->user_email ) ) {
		return false;
	}

	$subject = sanitize_text_field( $subject );
	$body    = wp_strip_all_tags( $message );

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	return wp_mail( $user->user_email, $subject, $body, $headers );
}
