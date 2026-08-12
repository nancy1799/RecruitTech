<?php
/**
 * RecruitTech Subscriptions Settings
 * Admin page (Settings > RecruitTech Subscriptions) where the site owner
 * turns the paid subscription system on/off, manages Company and Job
 * Seeker subscription plans, and enters PayMob API credentials.
 *
 * As long as the "Enable Subscriptions" toggle stays off (the default),
 * the rest of the plugin keeps behaving exactly as before this module was
 * added: unlimited jobs, unlimited applications, and every AI feature open
 * to everyone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Settings > RecruitTech Subscriptions admin page.
 */
function recruittech_subscriptions_register_settings_page() {
	add_options_page(
		'RecruitTech Subscriptions',
		'RecruitTech Subscriptions',
		'manage_options',
		'recruittech-subscriptions-settings',
		'recruittech_subscriptions_render_settings_page'
	);
}
add_action( 'admin_menu', 'recruittech_subscriptions_register_settings_page' );

/**
 * Register the toggle + PayMob credential settings fields.
 */
function recruittech_subscriptions_register_settings() {
	register_setting(
		'recruittech_subscriptions_settings_group',
		'recruittech_subscriptions_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => function ( $value ) {
				return '1' === (string) $value ? '1' : '0';
			},
			'default'           => '0',
		)
	);
	register_setting(
		'recruittech_subscriptions_settings_group',
		'recruittech_paymob_secret_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
	register_setting(
		'recruittech_subscriptions_settings_group',
		'recruittech_paymob_public_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
	register_setting(
		'recruittech_subscriptions_settings_group',
		'recruittech_paymob_integration_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
	register_setting(
		'recruittech_subscriptions_settings_group',
		'recruittech_paymob_hmac_secret',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'recruittech_subscriptions_register_settings' );

/**
 * Handle add/update/delete of a subscription plan submitted from this page.
 */
function recruittech_subscriptions_process_plan_action() {
	if ( ! isset( $_GET['page'] ) || 'recruittech-subscriptions-settings' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'recruitech_subscription_plans';

	// Delete a plan.
	if ( isset( $_GET['recruittech_delete_plan'], $_GET['recruittech_plan_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['recruittech_plan_nonce'] ) ), 'recruittech_subscription_plan_action' ) ) {
			return;
		}

		$plan_id = absint( $_GET['recruittech_delete_plan'] );
		if ( $plan_id > 0 ) {
			if ( recruittech_subscription_plan_has_subscribers( $plan_id ) ) {
				// Never hard-delete a plan real subscribers point to: deactivate instead.
				$wpdb->update( $table, array( 'status' => 'inactive' ), array( 'id' => $plan_id ), array( '%s' ), array( '%d' ) );
			} else {
				$wpdb->delete( $table, array( 'id' => $plan_id ), array( '%d' ) );
			}
		}

		wp_safe_redirect( remove_query_arg( array( 'recruittech_delete_plan', 'recruittech_plan_nonce' ) ) );
		exit;
	}

	// Add or update a plan.
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['recruittech_save_plan_submit'] ) ) {
		if ( ! isset( $_POST['recruittech_plan_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['recruittech_plan_nonce'] ), 'recruittech_subscription_plan_action' ) ) {
			return;
		}

		$plan_id       = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$account_type  = isset( $_POST['account_type'] ) && 'job_seeker' === $_POST['account_type'] ? 'job_seeker' : 'company';
		$plan_name     = isset( $_POST['plan_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_name'] ) ) : '';
		$duration_days = isset( $_POST['duration_days'] ) ? absint( $_POST['duration_days'] ) : 30;
		$price         = isset( $_POST['price'] ) ? (float) $_POST['price'] : 0;
		$usage_limit   = isset( $_POST['usage_limit'] ) ? absint( $_POST['usage_limit'] ) : 0;
		$ai_features   = isset( $_POST['ai_features'] ) && is_array( $_POST['ai_features'] )
			? implode( ',', array_map( 'sanitize_text_field', wp_unslash( $_POST['ai_features'] ) ) )
			: '';

		if ( '' === $plan_name || $duration_days < 1 ) {
			return;
		}

		$data = array(
			'account_type'  => $account_type,
			'plan_name'     => $plan_name,
			'duration_days' => $duration_days,
			'price'         => $price,
			'usage_limit'   => $usage_limit,
			'ai_features'   => $ai_features,
		);
		$formats = array( '%s', '%s', '%d', '%f', '%d', '%s' );

		if ( $plan_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $plan_id ), $formats, array( '%d' ) );
		} else {
			$wpdb->insert( $table, $data, $formats );
		}

		wp_safe_redirect( remove_query_arg( array() ) );
		exit;
	}
}
add_action( 'admin_init', 'recruittech_subscriptions_process_plan_action' );

/**
 * Render the plan management table + add/edit form for one account type.
 *
 * @param string $account_type 'company' or 'job_seeker'.
 */
function recruittech_subscriptions_render_plans_section( $account_type ) {
	$plans         = recruittech_subscription_get_plans( $account_type );
	$ai_features   = recruittech_subscription_get_ai_features( $account_type );
	$label         = 'company' === $account_type ? 'Company Plans' : 'Job Seeker Plans';
	$limit_label   = 'company' === $account_type ? 'Job posting limit' : 'Application limit';

	$edit_plan = null;
	if ( isset( $_GET['recruittech_edit_plan'] ) ) {
		$candidate = recruittech_subscription_get_plan( absint( $_GET['recruittech_edit_plan'] ) );
		if ( ! empty( $candidate ) && $candidate['account_type'] === $account_type ) {
			$edit_plan = $candidate;
		}
	}

	$form_plan_id       = $edit_plan ? absint( $edit_plan['id'] ) : 0;
	$form_plan_name     = $edit_plan ? $edit_plan['plan_name'] : '';
	$form_duration_days = $edit_plan ? absint( $edit_plan['duration_days'] ) : 30;
	$form_price         = $edit_plan ? $edit_plan['price'] : '';
	$form_usage_limit   = $edit_plan ? absint( $edit_plan['usage_limit'] ) : '';
	$form_ai_features   = $edit_plan ? array_filter( array_map( 'trim', explode( ',', (string) $edit_plan['ai_features'] ) ) ) : array();
	?>
	<h2><?php echo esc_html( $label ); ?></h2>

	<?php if ( ! empty( $plans ) ) : ?>
	<table class="widefat striped" style="max-width:900px;margin-bottom:1.5em;">
		<thead>
			<tr>
				<th>Plan Name</th>
				<th><?php echo esc_html( $limit_label ); ?></th>
				<th>Duration (days)</th>
				<th>Price</th>
				<th>AI Features</th>
				<th>Status</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $plans as $plan ) :
				$plan_features = array_filter( array_map( 'trim', explode( ',', (string) $plan['ai_features'] ) ) );
				$feature_labels = array();
				foreach ( $plan_features as $feature_key ) {
					if ( isset( $ai_features[ $feature_key ] ) ) {
						$feature_labels[] = $ai_features[ $feature_key ];
					}
				}
				$edit_url   = add_query_arg( 'recruittech_edit_plan', $plan['id'] );
				$delete_url = wp_nonce_url( add_query_arg( 'recruittech_delete_plan', $plan['id'] ), 'recruittech_subscription_plan_action', 'recruittech_plan_nonce' );
				?>
				<tr>
					<td><?php echo esc_html( $plan['plan_name'] ); ?></td>
					<td><?php echo esc_html( $plan['usage_limit'] ); ?></td>
					<td><?php echo esc_html( $plan['duration_days'] ); ?></td>
					<td><?php echo esc_html( $plan['price'] ); ?></td>
					<td><?php echo esc_html( ! empty( $feature_labels ) ? implode( ', ', $feature_labels ) : '—' ); ?></td>
					<td><?php echo esc_html( ucfirst( $plan['status'] ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>">Edit</a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Delete this plan?');" style="color:#b32d2e;">Delete</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
		<p>No <?php echo esc_html( strtolower( $label ) ); ?> yet.</p>
	<?php endif; ?>

	<form method="post" action="" style="max-width:600px;margin-bottom:2.5em;">
		<?php wp_nonce_field( 'recruittech_subscription_plan_action', 'recruittech_plan_nonce' ); ?>
		<input type="hidden" name="plan_id" value="<?php echo esc_attr( $form_plan_id ); ?>">
		<input type="hidden" name="account_type" value="<?php echo esc_attr( $account_type ); ?>">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label>Plan Name</label></th>
				<td><input type="text" name="plan_name" value="<?php echo esc_attr( $form_plan_name ); ?>" class="regular-text" required></td>
			</tr>
			<tr>
				<th scope="row"><label><?php echo esc_html( $limit_label ); ?></label></th>
				<td><input type="number" name="usage_limit" min="0" value="<?php echo esc_attr( $form_usage_limit ); ?>" class="small-text" required></td>
			</tr>
			<tr>
				<th scope="row"><label>Duration (days)</label></th>
				<td><input type="number" name="duration_days" min="1" value="<?php echo esc_attr( $form_duration_days ); ?>" class="small-text" required></td>
			</tr>
			<tr>
				<th scope="row"><label>Price</label></th>
				<td><input type="number" step="0.01" min="0" name="price" value="<?php echo esc_attr( $form_price ); ?>" class="small-text" required> EGP</td>
			</tr>
			<tr>
				<th scope="row"><label>Allowed AI Features</label></th>
				<td>
					<?php foreach ( $ai_features as $feature_key => $feature_label ) : ?>
						<label style="display:block;">
							<input type="checkbox" name="ai_features[]" value="<?php echo esc_attr( $feature_key ); ?>" <?php checked( in_array( $feature_key, $form_ai_features, true ) ); ?>>
							<?php echo esc_html( $feature_label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php submit_button( $edit_plan ? 'Update Plan' : 'Add Plan', 'primary', 'recruittech_save_plan_submit' ); ?>
		<?php if ( $edit_plan ) : ?>
			<a href="<?php echo esc_url( remove_query_arg( 'recruittech_edit_plan' ) ); ?>">Cancel edit</a>
		<?php endif; ?>
	</form>
	<?php
}

/**
 * Render the settings page.
 */
function recruittech_subscriptions_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$enabled              = recruittech_subscription_is_enabled();
	$paymob_secret_key    = get_option( 'recruittech_paymob_secret_key', '' );
	$paymob_public_key    = get_option( 'recruittech_paymob_public_key', '' );
	$paymob_integration_id = get_option( 'recruittech_paymob_integration_id', '' );
	$paymob_hmac_secret   = get_option( 'recruittech_paymob_hmac_secret', '' );
	?>
	<div class="wrap">
		<h1>RecruitTech Subscriptions</h1>
		<p>Turns on paid subscription plans for Company and Job Seeker accounts. While disabled (default), the site stays fully free: unlimited jobs, unlimited applications, and every AI feature open to everyone.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'recruittech_subscriptions_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="recruittech_subscriptions_enabled">Enable Subscriptions</label></th>
					<td>
						<label>
							<input type="checkbox" name="recruittech_subscriptions_enabled" id="recruittech_subscriptions_enabled" value="1" <?php checked( $enabled ); ?>>
							Turn on subscription limits and paid plans
						</label>
					</td>
				</tr>
			</table>

			<h2>PayMob Settings</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="recruittech_paymob_secret_key">Secret Key</label></th>
					<td>
						<input type="password" name="recruittech_paymob_secret_key" id="recruittech_paymob_secret_key" value="<?php echo esc_attr( $paymob_secret_key ); ?>" class="regular-text" autocomplete="off">
						<p class="description">Used to authenticate backend calls to the Intention API (sent as <code>Authorization: Token &lt;secret_key&gt;</code>).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="recruittech_paymob_public_key">Public Key</label></th>
					<td><input type="text" name="recruittech_paymob_public_key" id="recruittech_paymob_public_key" value="<?php echo esc_attr( $paymob_public_key ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="recruittech_paymob_integration_id">Integration ID</label></th>
					<td><input type="text" name="recruittech_paymob_integration_id" id="recruittech_paymob_integration_id" value="<?php echo esc_attr( $paymob_integration_id ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="recruittech_paymob_hmac_secret">HMAC Secret</label></th>
					<td>
						<input type="password" name="recruittech_paymob_hmac_secret" id="recruittech_paymob_hmac_secret" value="<?php echo esc_attr( $paymob_hmac_secret ); ?>" class="regular-text" autocomplete="off">
						<p class="description">Used to verify the transaction callback PayMob sends to <code><?php echo esc_html( admin_url( 'admin-ajax.php?action=recruittech_paymob_webhook' ) ); ?></code>. Add that URL as your "Transaction processed callback" in the PayMob dashboard.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<hr>
		<?php recruittech_subscriptions_render_plans_section( 'company' ); ?>
		<hr>
		<?php recruittech_subscriptions_render_plans_section( 'job_seeker' ); ?>
	</div>
	<?php
}
