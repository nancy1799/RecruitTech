<?php
/**
 * My Subscription template.
 * Expects: $account_type, $subscriptions_enabled, $account_type_has_plans,
 * $current_subscription, $available_plans, $ai_features, $limit_label,
 * $notice_code, $subscribe_result, $subscribe_success
 * (from recruittech_my_subscription_shortcode()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice_messages = array(
	'job_limit_reached'         => 'You have reached the job posting limit on your current plan. Subscribe to a plan with a higher limit to publish more jobs.',
	'application_limit_reached' => 'You have reached the application limit on your current plan. Subscribe to a plan with a higher limit to apply to more jobs.',
);

$subscribe_messages = array(
	'invalid_nonce'       => 'Security check failed, please try again.',
	'not_logged_in'       => 'Please log in to subscribe.',
	'invalid_plan'        => 'That plan is no longer available.',
	'wrong_account_type'  => 'This plan is not available for your account type.',
	'transaction_failed'  => 'Could not start the payment, please try again.',
	'gateway_unavailable' => 'The payment gateway is not available right now.',
	'gateway_error'       => 'The payment provider could not start this payment. Please try again later.',
);

$subscribe_success_messages = array(
	'free_plan_activated' => 'Your free plan has been activated successfully.',
);
?>
<div class="container py-4">

	<?php if ( $notice_code && isset( $notice_messages[ $notice_code ] ) ) : ?>
		<div class="alert alert-warning" role="alert"><?php echo esc_html( $notice_messages[ $notice_code ] ); ?></div>
	<?php endif; ?>

	<?php if ( $subscribe_success && isset( $subscribe_success_messages[ $subscribe_success ] ) ) : ?>
		<div class="alert alert-success" role="alert"><?php echo esc_html( $subscribe_success_messages[ $subscribe_success ] ); ?></div>
	<?php endif; ?>

	<?php if ( $subscribe_result && isset( $subscribe_messages[ $subscribe_result ] ) ) : ?>
		<div class="alert alert-danger" role="alert"><?php echo esc_html( $subscribe_messages[ $subscribe_result ] ); ?></div>
	<?php endif; ?>

	<div class="card border-0 shadow-sm mb-4">
		<div class="card-body">
			<h1 class="h3 mb-3">My Subscription</h1>

			<?php if ( ! $subscriptions_enabled ) : ?>
				<div class="alert alert-success mb-0" role="alert">
					Subscriptions are not required on this site right now. Your usage is currently
					<strong>free and unlimited</strong>, including all AI features.
				</div>
			<?php elseif ( ! $account_type_has_plans ) : ?>
				<div class="alert alert-success mb-0" role="alert">
					No subscription plans have been set up for your account type yet. Your usage is currently
					<strong>free and unlimited</strong>, including all AI features.
				</div>
			<?php elseif ( ! empty( $current_subscription ) ) : ?>
				<p class="mb-1"><strong>Current plan:</strong> <?php echo esc_html( $current_subscription['plan_name_snapshot'] ); ?></p>
				<p class="mb-1"><strong><?php echo esc_html( $limit_label ); ?>:</strong>
					<?php echo esc_html( max( 0, (int) $current_subscription['usage_limit_snapshot'] - (int) $current_subscription['usage_count'] ) ); ?>
					remaining out of <?php echo esc_html( $current_subscription['usage_limit_snapshot'] ); ?>
				</p>
				<p class="mb-1"><strong>Expires on:</strong> <?php echo esc_html( $current_subscription['expires_at'] ); ?></p>
				<?php
				$plan_features = array_filter( array_map( 'trim', explode( ',', (string) $current_subscription['ai_features_snapshot'] ) ) );
				$feature_labels = array();
				foreach ( $plan_features as $feature_key ) {
					if ( isset( $ai_features[ $feature_key ] ) ) {
						$feature_labels[] = $ai_features[ $feature_key ];
					}
				}
				?>
				<p class="mb-0"><strong>AI features included:</strong> <?php echo esc_html( ! empty( $feature_labels ) ? implode( ', ', $feature_labels ) : 'None' ); ?></p>
			<?php else : ?>
				<div class="alert alert-info mb-0" role="alert">
					You do not have an active subscription yet. Choose a plan below to get started.
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $subscriptions_enabled && $account_type_has_plans ) : ?>
		<h2 class="h4 mb-3">Available Plans</h2>

		<?php if ( empty( $available_plans ) ) : ?>
			<p class="text-muted">No plans are available right now. Please check back later.</p>
		<?php else : ?>
			<div class="row g-3">
				<?php foreach ( $available_plans as $plan ) :
					$plan_feature_keys = array_filter( array_map( 'trim', explode( ',', (string) $plan['ai_features'] ) ) );
					$plan_feature_labels = array();
					foreach ( $plan_feature_keys as $feature_key ) {
						if ( isset( $ai_features[ $feature_key ] ) ) {
							$plan_feature_labels[] = $ai_features[ $feature_key ];
						}
					}
					?>
					<div class="col-12 col-md-6 col-lg-4">
						<div class="card h-100 border-0 shadow-sm">
							<div class="card-body d-flex flex-column">
								<h3 class="h5"><?php echo esc_html( $plan['plan_name'] ); ?></h3>
								<p class="mb-1"><?php echo esc_html( $limit_label ); ?>: <strong><?php echo esc_html( $plan['usage_limit'] ); ?></strong></p>
								<p class="mb-1">Duration: <strong><?php echo esc_html( $plan['duration_days'] ); ?> days</strong></p>
								<p class="mb-3">AI features: <?php echo esc_html( ! empty( $plan_feature_labels ) ? implode( ', ', $plan_feature_labels ) : 'None' ); ?></p>
								<p class="h4 mt-auto mb-3"><?php echo esc_html( $plan['price'] ); ?> EGP</p>
								<form method="post">
									<?php wp_nonce_field( 'recruittech_subscribe_action', 'recruittech_subscribe_nonce' ); ?>
									<input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan['id'] ); ?>">
									<button type="submit" name="recruittech_subscribe_submit" value="1" class="btn btn-primary w-100">Subscribe Now</button>
								</form>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

</div>
