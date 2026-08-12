<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$company_logo_url = function_exists( 'recruittech_get_company_logo_url' ) ? recruittech_get_company_logo_url( get_current_user_id() ) : '';
?>
<div class="recruittech-company-profile container py-4">
    <div class="rt-page-header">
        <div class="rt-page-header-title d-flex align-items-center gap-3">
            <?php if ( ! empty( $company_logo_url ) ) : ?>
                <span class="rt-avatar rt-avatar-xl"><img src="<?php echo esc_url( $company_logo_url ); ?>" alt="Company Logo"></span>
            <?php else : ?>
                <?php echo wp_kses_post( recruittech_get_placeholder_company_logo( 'rt-avatar-xl' ) ); ?>
            <?php endif; ?>
            <div>
                <h1><?php echo esc_html( ! empty( $company_name ) ? $company_name : 'Company Profile' ); ?></h1>
                <p>Keep your company information up to date so candidates and admins see the right details.</p>
            </div>
        </div>
    </div>

    <?php if ( ! empty( $verification_status ) ) : ?>
        <?php
        $status_label = ucfirst( sanitize_text_field( $verification_status ) );
        $status_message = '';
        $alert_class   = 'alert-warning';

        if ( 'approved' === $verification_status ) {
            $status_message = 'Your company account has been verified successfully.';
            $alert_class    = 'alert-success';
        } elseif ( 'rejected' === $verification_status ) {
            $status_message = 'Your verification request was rejected. Please update your company information and submit again.';
            $alert_class    = 'alert-danger';
        } else {
            $status_message = 'Your company profile is currently under administrator review. You will be able to access all company features after administrator approval.';
        }
        ?>
        <div class="alert <?php echo esc_attr( $alert_class ); ?>">
            <p class="mb-1"><strong>Verification Status: <?php echo esc_html( $status_label ); ?></strong></p>
            <p class="mb-0"><?php echo esc_html( $status_message ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $success ) ) : ?>
        <div class="alert alert-success">
            <p class="mb-0"><?php echo esc_html( $success ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $errors ) && is_array( $errors ) ) : ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card-title">Company Details</h2>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'recruittech_company_profile_action', 'recruittech_company_profile_nonce' ); ?>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="form-group">
                        <label for="company_name">Company Name <span class="text-danger">*</span></label>
                        <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $company_name ); ?>" class="form-control" required>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-group">
                        <label for="website">Company Website</label>
                        <input type="url" id="website" name="website" value="<?php echo esc_attr( $website ); ?>" class="form-control" placeholder="https://example.com">
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group">
                        <label for="description">Company Description <span class="text-danger">*</span></label>
                        <textarea id="description" name="description" rows="6" class="form-control" required><?php echo esc_textarea( $description ); ?></textarea>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-group">
                        <label for="logo">Company Logo Upload</label>
                        <input type="file" id="logo" name="logo" class="form-control" accept="application/pdf,image/jpeg,image/png">
                        <?php if ( ! empty( $logo_url ) ) : ?>
                            <p class="form-help">A logo is already on file. Uploading a new one will replace it.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-group">
                        <label for="commercial_registration">
                            Commercial Registration Upload
                            <?php echo empty( $commercial_register_file ) ? '<span class="text-danger">*</span>' : ''; ?>
                        </label>
                        <input type="file" id="commercial_registration" name="commercial_registration" class="form-control" accept="application/pdf,image/jpeg,image/png" <?php echo empty( $commercial_register_file ) ? 'required' : ''; ?>>
                        <?php if ( ! empty( $commercial_register_file ) ) : ?>
                            <p class="form-help">
                                <a href="<?php echo esc_url( $commercial_register_file ); ?>" target="_blank" rel="noopener noreferrer">View current commercial registration</a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">Submit Company Profile</button>
        </form>
    </div>
</div>
