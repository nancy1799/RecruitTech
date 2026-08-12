<?php
/**
 * RecruitTech AI Settings
 * Admin page (Settings > RecruitTech AI) where the site owner enters the
 * ITI student AI gateway API key and model configuration used by
 * includes/ai/class-ai-client.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Settings > RecruitTech AI admin page.
 */
function recruittech_ai_register_settings_page() {
	add_options_page(
		'RecruitTech AI',
		'RecruitTech AI',
		'manage_options',
		'recruittech-ai-settings',
		'recruittech_ai_render_settings_page'
	);
}
add_action( 'admin_menu', 'recruittech_ai_register_settings_page' );

/**
 * Register the plugin settings fields.
 */
function recruittech_ai_register_settings() {
	register_setting(
		'recruittech_ai_settings_group',
		'recruittech_ai_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
	register_setting(
		'recruittech_ai_settings_group',
		'recruittech_ai_model_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
		)
	);
	register_setting(
		'recruittech_ai_settings_group',
		'recruittech_ai_max_tokens',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1500,
		)
	);
}
add_action( 'admin_init', 'recruittech_ai_register_settings' );

/**
 * Render the settings page.
 */
function recruittech_ai_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$api_key    = get_option( 'recruittech_ai_api_key', '' );
	$model_id   = get_option( 'recruittech_ai_model_id', 'anthropic.claude-3-5-sonnet-20240620-v1:0' );
	$max_tokens = get_option( 'recruittech_ai_max_tokens', 1500 );
	?>
	<div class="wrap">
		<h1>RecruitTech AI Settings</h1>
		<p>Connects the AI Recruitment Assistant to the ITI student AI gateway (<code>apiaccess.iti.net.eg</code>), which validates your key/policy/budget and forwards requests to Bedrock on your behalf.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'recruittech_ai_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="recruittech_ai_api_key">Gateway API Key</label></th>
					<td>
						<input type="password" name="recruittech_ai_api_key" id="recruittech_ai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
						<p class="description">Your ITI student gateway key (starts with <code>sbg_...</code>). This is sent as a Bearer token, never exposed to visitors, and stored in the WordPress options table.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="recruittech_ai_model_id">Model ID</label></th>
					<td>
						<input type="text" name="recruittech_ai_model_id" id="recruittech_ai_model_id" value="<?php echo esc_attr( $model_id ); ?>" class="regular-text">
						<p class="description">Use one of the model IDs listed under "Approved models" on your ITI dashboard.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="recruittech_ai_max_tokens">Max tokens</label></th>
					<td>
						<input type="number" name="recruittech_ai_max_tokens" id="recruittech_ai_max_tokens" value="<?php echo esc_attr( $max_tokens ); ?>" class="small-text" min="200" max="4000">
						<p class="description">Upper limit on how long each AI analysis can be.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<hr>
		<h2>How to test it</h2>
		<ol>
			<li>Save your API key above.</li>
			<li>Log in as a company account, go to <strong>Company Applications</strong>.</li>
			<li>Click <strong>Analyze with AI</strong> next to any applicant with an uploaded CV.</li>
		</ol>
		<p>If something goes wrong, the error message shown there (invalid key, wrong model ID, gateway timeout, etc.) is exactly what the gateway returned, which is the fastest way to debug the connection.</p>
	</div>
	<?php
}
