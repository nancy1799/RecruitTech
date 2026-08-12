<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="container py-4">
	<div class="row">
		<div class="col-12">
			<div class="card border-0 shadow-sm">
				<div class="card-body">
					<h1 class="h3 mb-4">
	<?php echo ! empty( $edit_job_id ) ? 'Edit Job Posting' : 'Create New Job Posting'; ?>
</h1>
					<?php if ( ! empty( $save_success ) ) : ?>
						<div class="alert alert-success" role="alert"><?php echo esc_html( $save_success ); ?></div>
					<?php endif; ?>

					<?php if ( ! empty( $save_error ) ) : ?>
						<div class="alert alert-danger" role="alert"><?php echo esc_html( $save_error ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $status_notice ) ) : ?>
						<div class="alert alert-warning" role="alert"><?php echo esc_html( $status_notice ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $access_denied ) ) : ?>
						<div class="alert alert-danger" role="alert">Access denied. You do not have permission to edit this job.</div>
					<?php else : ?>
						<form method="post" action="" novalidate>
                            <?php if ( ! empty( $edit_job_id ) ) : ?>
	<input type="hidden" name="job_id" value="<?php echo esc_attr( $edit_job_id ); ?>">
<?php endif; ?>
							<?php wp_nonce_field( 'recruittech_create_job_action', 'recruittech_create_job_nonce' ); ?>
							<input type="hidden" name="recruittech_company_create_job_submit" value="1">
							<div class="row g-3">
							<div class="col-12 col-md-6">
								<label for="job_title" class="form-label">Job Title</label>
								<input type="text" class="form-control<?php echo ! empty( $errors['job_title'] ) ? ' is-invalid' : ''; ?>" id="job_title" name="job_title" placeholder="Enter job title" value="<?php echo esc_attr( $job_title ); ?>">
								<?php if ( ! empty( $errors['job_title'] ) ) : ?>
									<div class="invalid-feedback"><?php echo esc_html( $errors['job_title'] ); ?></div>
								<?php endif; ?>
							</div>
							<div class="col-12 col-md-6">
								<label for="job_category" class="form-label">Job Category</label>
								<input type="text" class="form-control" id="job_category" name="job_category" placeholder="e.g. Software, Marketing" value="<?php echo esc_attr( $job_category ); ?>">
							</div>
							<div class="col-12 col-md-6">
								<label for="job_type" class="form-label">Job Type</label>
								<input type="text" class="form-control" id="job_type" name="job_type" placeholder="e.g. Full-time, Part-time" value="<?php echo esc_attr( $job_type ); ?>">
							</div>
							<div class="col-12 col-md-6">
								<label for="experience_level" class="form-label">Experience Level</label>
								<input type="text" class="form-control" id="experience_level" name="experience_level" placeholder="e.g. Mid-level" value="<?php echo esc_attr( $experience_level ); ?>">
							</div>
							<div class="col-12">
								<label for="required_skills" class="form-label">Required Skills</label>
								<textarea class="form-control" id="required_skills" name="required_skills" rows="3" placeholder="List required skills"><?php echo esc_textarea( $required_skills ); ?></textarea>
							</div>
							<div class="col-12 col-md-6">
								<label for="salary" class="form-label">Salary</label>
								<input type="text" class="form-control" id="salary" name="salary" placeholder="e.g. $50,000 - $70,000" value="<?php echo esc_attr( $salary ); ?>">
							</div>
							<div class="col-12 col-md-6">
								<label for="location" class="form-label">Location</label>
								<input type="text" class="form-control" id="location" name="location" placeholder="City, Country" value="<?php echo esc_attr( $location ); ?>">
							</div>
							<div class="col-12">
								<label for="description" class="form-label">Description</label>
								<textarea class="form-control<?php echo ! empty( $errors['description'] ) ? ' is-invalid' : ''; ?>" id="description" name="description" rows="4" placeholder="Describe the role"><?php echo esc_textarea( $description ); ?></textarea>
								<?php if ( ! empty( $errors['description'] ) ) : ?>
									<div class="invalid-feedback"><?php echo esc_html( $errors['description'] ); ?></div>
								<?php endif; ?>
							</div>
							<div class="col-12">
								<label for="requirements" class="form-label">Requirements</label>
								<textarea class="form-control" id="requirements" name="requirements" rows="4" placeholder="List job requirements"><?php echo esc_textarea( $requirements ); ?></textarea>
							</div>
							<div class="col-12">
								<label for="benefits" class="form-label">Benefits</label>
								<textarea class="form-control" id="benefits" name="benefits" rows="4" placeholder="List job benefits"><?php echo esc_textarea( $benefits ); ?></textarea>
							</div>
							<div class="col-12 col-md-6">
								<label for="deadline" class="form-label">Deadline</label>
								<input type="date" class="form-control<?php echo ! empty( $errors['deadline'] ) ? ' is-invalid' : ''; ?>" id="deadline" name="deadline" value="<?php echo esc_attr( $deadline ); ?>">
								<?php if ( ! empty( $errors['deadline'] ) ) : ?>
									<div class="invalid-feedback"><?php echo esc_html( $errors['deadline'] ); ?></div>
								<?php endif; ?>
							</div>
							<div class="col-12 col-md-6">
								<label for="status" class="form-label">Status</label>
								<select class="form-select" id="status" name="status">
									<option value="Draft"<?php selected( $status, 'Draft' ); ?>>Draft</option>
									<option value="Published"<?php selected( $status, 'Published' ); ?>>Published</option>
									<option value="Closed"<?php selected( $status, 'Closed' ); ?>>Closed</option>
								</select>
							</div>
								<div class="col-12">
									<button type="submit" class="btn btn-primary">
	<?php echo ! empty( $edit_job_id ) ? 'Update Job' : 'Create Job'; ?>
</button>
								</div>
							</div>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
