<?php
/**
 * Company Documents template.
 * Expects: $errors, $success, $documents, $doc_types (array from the shortcode).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="container py-4">
	<div class="card border-0 shadow-sm mb-4">
		<div class="card-body">
			<h1 class="h3 mb-2">Hiring Documents</h1>
			<p class="text-muted">
				Upload your hiring policies, recruitment guidelines, internal hiring requirements, and interview manuals.
				The AI Recruitment Assistant reads these when analyzing candidates, so its recommendations follow
				<strong>your</strong> company's rules instead of generic advice.
			</p>

			<?php if ( ! empty( $errors ) && is_array( $errors ) ) : ?>
				<div class="alert alert-danger" role="alert">
					<ul class="mb-0">
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $success ) ) : ?>
				<div class="alert alert-success" role="alert"><?php echo esc_html( $success ); ?></div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
				<?php wp_nonce_field( 'recruittech_company_documents_action', 'recruittech_company_documents_nonce' ); ?>
				<div class="col-12 col-md-4">
					<label for="doc_type" class="form-label">Document Type</label>
					<select name="doc_type" id="doc_type" class="form-select" required>
						<option value="">Select a type&hellip;</option>
						<?php foreach ( $doc_types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-12 col-md-5">
					<label for="document" class="form-label">File (PDF, DOCX, or TXT)</label>
					<input type="file" name="document" id="document" class="form-control" accept=".pdf,.docx,.txt" required>
				</div>
				<div class="col-12 col-md-3">
					<button type="submit" name="recruittech_upload_document_submit" value="1" class="btn btn-primary w-100">Upload</button>
				</div>
			</form>
		</div>
	</div>

	<div class="card border-0 shadow-sm">
		<div class="card-body">
			<h2 class="h5 mb-3">Uploaded Documents</h2>

			<?php if ( empty( $documents ) ) : ?>
				<p class="text-muted mb-0">No documents uploaded yet.</p>
			<?php else : ?>
				<div class="table-responsive">
					<table class="table table-bordered table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Type</th>
								<th>File</th>
								<th>Uploaded</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $documents as $document ) : ?>
								<tr>
									<td><?php echo esc_html( $document['doc_type'] ); ?></td>
									<td><a href="<?php echo esc_url( $document['file_path'] ); ?>" target="_blank" rel="noopener noreferrer">View</a></td>
									<td><?php echo esc_html( $document['uploaded_at'] ); ?></td>
									<td>
										<form method="post" onsubmit="return confirm('Remove this document?');">
											<?php wp_nonce_field( 'recruittech_delete_document_' . absint( $document['id'] ), 'recruittech_delete_document_nonce' ); ?>
											<input type="hidden" name="document_id" value="<?php echo esc_attr( $document['id'] ); ?>">
											<button type="submit" name="recruittech_delete_document_submit" value="1" class="btn btn-sm btn-outline-danger">Remove</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
