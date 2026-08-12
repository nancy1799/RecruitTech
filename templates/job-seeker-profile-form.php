<?php
/**
 * Job Seeker Profile Form Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$profile      = null;
$cv_url       = '';

if ( $current_user->ID ) {
	$profile = recruittech_get_job_seeker_by_user_id( $current_user->ID );
}

$form_data = get_transient( 'recruittech_job_seeker_profile_form_data' );
if ( $form_data ) {
	delete_transient( 'recruittech_job_seeker_profile_form_data' );
}

$errors = get_transient( 'recruittech_job_seeker_profile_errors' );
if ( $errors ) {
	delete_transient( 'recruittech_job_seeker_profile_errors' );
}

$full_name  = isset( $form_data['full_name'] ) ? $form_data['full_name'] : ( $profile['full_name'] ?? '' );
$phone      = isset( $form_data['phone'] ) ? $form_data['phone'] : ( $profile['phone'] ?? '' );
$summary    = isset( $form_data['summary'] ) ? $form_data['summary'] : ( $profile['summary'] ?? '' );
$skills     = isset( $form_data['skills'] ) ? $form_data['skills'] : ( $profile['skills'] ?? '' );
$experience = isset( $form_data['experience'] ) ? $form_data['experience'] : ( $profile['experience'] ?? '' );
$profile_photo_url = ! empty( $profile['profile_photo'] ) ? $profile['profile_photo'] : '';
$front_id_photo_url = ! empty( $profile['front_id_photo'] ) ? $profile['front_id_photo'] : '';
$back_id_photo_url = ! empty( $profile['back_id_photo'] ) ? $profile['back_id_photo'] : '';
$selfie_with_id_photo_url = ! empty( $profile['selfie_with_id_photo'] ) ? $profile['selfie_with_id_photo'] : '';

if ( $current_user->ID ) {
	$success_transient_key = 'recruittech_profile_success_' . absint( $current_user->ID );
	$success_message       = get_transient( $success_transient_key );
	if ( $success_message ) {
		delete_transient( $success_transient_key );
	}
}

if ( ! empty( $profile['id'] ) ) {
	global $wpdb;
	$cv_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT file_path FROM {$wpdb->prefix}recruitech_cvs WHERE job_seeker_id = %d ORDER BY id DESC LIMIT 1",
			absint( $profile['id'] )
		)
	);

	if ( $cv_row && ! empty( $cv_row->file_path ) ) {
		$upload_dir = wp_upload_dir();
		$file_path  = wp_normalize_path( $cv_row->file_path );
		$base_dir   = wp_normalize_path( $upload_dir['basedir'] );

		if ( 0 === strpos( $file_path, $base_dir ) ) {
			$cv_url = $upload_dir['baseurl'] . str_replace( $base_dir, '', $file_path );
		}
	}
}
?>

<div class="container py-4">
	<div class="card shadow-sm border-0">
		<div class="card-body p-4 p-md-5">
			<h2 class="h4 mb-4">Job Seeker Profile</h2>
			<div class="text-center mb-4">
				<div class="rt-avatar rt-avatar-xl mx-auto mb-3">
					<?php if ( ! empty( $profile_photo_url ) ) : ?>
						<img src="<?php echo esc_url( $profile_photo_url ); ?>" alt="Profile photo" />
					<?php else : ?>
						<i class="bi bi-person-circle"></i>
					<?php endif; ?>
				</div>
				<p class="text-muted mb-0">Profile Photo</p>
			</div>
			<?php if ( ! empty( $errors ) ) : ?>
				<div class="alert alert-danger" role="alert">
					<ul class="mb-0">
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $success_message ) ) : ?>
				<div class="alert alert-success" role="alert">
					<?php echo esc_html( $success_message ); ?>
				</div>
			<?php endif; ?>
			<form method="post" enctype="multipart/form-data" class="row g-3">
				<?php wp_nonce_field( 'recruittech_js_profile_action', 'recruittech_js_profile_nonce' ); ?>

				<div class="col-md-6">
					<label for="full_name" class="form-label">Full Name</label>
					<input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo esc_attr( $full_name ); ?>" required>
				</div>

				<div class="col-md-6">
					<label for="phone" class="form-label">Phone</label>
					<input type="tel" class="form-control" id="phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" required>
				</div>

				<div class="col-12">
					<label for="summary" class="form-label">Summary</label>
					<textarea class="form-control" id="summary" name="summary" rows="4" required><?php echo esc_textarea( $summary ); ?></textarea>
				</div>

				<div class="col-12">
					<label for="skills" class="form-label">Skills</label>
					<textarea class="form-control" id="skills" name="skills" rows="4" required><?php echo esc_textarea( $skills ); ?></textarea>
				</div>

				<div class="col-12">
					<label for="experience" class="form-label">Experience</label>
					<textarea class="form-control" id="experience" name="experience" rows="5" required><?php echo esc_textarea( $experience ); ?></textarea>
				</div>

				<div class="col-12">
					<label for="profile_photo" class="form-label">Profile Photo</label>
					<input type="file" class="form-control" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
					<div class="form-text">Accepted formats: JPG, JPEG, PNG, WEBP. Maximum size: 2 MB.</div>
					<?php if ( ! empty( $profile_photo_url ) ) : ?>
						<p class="mt-2 mb-0">
							<img src="<?php echo esc_url( $profile_photo_url ); ?>" alt="Current profile photo" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" />
						</p>
					<?php endif; ?>
				</div>

				<div class="col-12">
					<label for="front_id_photo" class="form-label">Front ID Photo</label>
					<input type="file" class="form-control" id="front_id_photo" name="front_id_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
					<div class="form-text">Accepted formats: JPG, JPEG, PNG, WEBP. Maximum size: 2 MB.</div>
					<?php if ( ! empty( $front_id_photo_url ) ) : ?>
						<p class="mt-2 mb-0">
							<img src="<?php echo esc_url( $front_id_photo_url ); ?>" alt="Current front ID photo" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" />
						</p>
					<?php endif; ?>
				</div>

				<div class="col-12">
					<label for="back_id_photo" class="form-label">Back ID Photo</label>
					<input type="file" class="form-control" id="back_id_photo" name="back_id_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
					<div class="form-text">Accepted formats: JPG, JPEG, PNG, WEBP. Maximum size: 2 MB.</div>
					<?php if ( ! empty( $back_id_photo_url ) ) : ?>
						<p class="mt-2 mb-0">
							<img src="<?php echo esc_url( $back_id_photo_url ); ?>" alt="Current back ID photo" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" />
						</p>
					<?php endif; ?>
				</div>

				<div class="col-12">
					<label for="selfie_with_id_photo" class="form-label">Selfie Holding ID</label>
					<input type="file" class="form-control" id="selfie_with_id_photo" name="selfie_with_id_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
					<div class="form-text">Accepted formats: JPG, JPEG, PNG, WEBP. Maximum size: 2 MB.</div>
					<?php if ( ! empty( $selfie_with_id_photo_url ) ) : ?>
						<p class="mt-2 mb-0">
							<img src="<?php echo esc_url( $selfie_with_id_photo_url ); ?>" alt="Current selfie with ID photo" style="width:60px;height:60px;object-fit:cover;border-radius:999px;" />
						</p>
					<?php endif; ?>
				</div>

				<div class="col-12">
					<label for="cv_upload" class="form-label">CV Upload</label>
					<input type="file" class="form-control" id="cv_upload" name="cv_upload" accept=".pdf,.doc,.docx">
					<div class="form-text">Accepted formats: PDF, DOC, DOCX.</div>
					<?php if ( ! empty( $cv_url ) ) : ?>
						<p class="mt-2 mb-0">
							<a href="<?php echo esc_url( $cv_url ); ?>" target="_blank" rel="noopener noreferrer">View Current CV</a>
						</p>
					<?php endif; ?>
				</div>

				<div class="col-12 mt-3">
					<button type="submit" class="btn btn-primary px-4">Save Profile</button>
				</div>
			</form>
		</div>
	</div>
</div>
