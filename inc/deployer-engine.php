<?php
/**
 * Logic for communicating with the Master and processing imports.
 *
 * @package WPCloudDeployerClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper to get authenticated API headers.
 */
function wpcd_get_api_headers() {
	$user = get_option( 'wpcd_master_user' );
	$pass = get_option( 'wpcd_master_pass' );

	return array(
		'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ),
	);
}

/**
 * Render the Deployment Screen UI.
 */
function wpcd_render_deployment_screen() {
	$master_url = get_option( 'wpcd_master_url' );

	if ( ! $master_url ) {
		echo '<div class="notice notice-error"><p>Please connect to a Master site first.</p></div>';
		return;
	}

	?>
	<div class="wpcd-deployment-wrap" style="margin-top: 20px;">
		<div class="card">
			<h2>Step 1: Start Site Architecture</h2>
			<p>Click below to install the core theme and the 5-6 base plugins defined on the Master.</p>
			<button id="wpcd-start-architecture" class="button button-primary">Install Core Architecture</button>
			<div id="wpcd-architecture-status" style="margin-top:10px; font-weight:bold;"></div>
		</div>

		<div class="card" style="margin-top: 20px;">
			<h2>Step 2: Select & Inject Package</h2>
			<p>Select a specific signature package to import (Pages, Forms, and Snippets).</p>
			
			<select id="wpcd-package-select">
				<option value="">Loading packages from Master...</option>
			</select>
			
			<button id="wpcd-inject-package" class="button button-secondary" disabled>Inject Content</button>
			<div id="wpcd-inject-status" style="margin-top:10px; font-weight:bold;"></div>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		// Fetch packages list on load
		$.ajax({
			url: '<?php echo esc_url_raw( untrailingslashit( $master_url ) . '/wp-json/wpcd/v1/packages' ); ?>',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('Authorization', 'Basic <?php echo base64_encode( get_option( "wpcd_master_user" ) . ":" . get_option( "wpcd_master_pass" ) ); ?>');
			},
			success: function(response) {
				var select = $('#wpcd-package-select');
				select.empty().append('<option value="">-- Choose a Package --</option>');
				$.each(response, function(i, pkg) {
					select.append('<option value="'+pkg.id+'">'+pkg.title+'</option>');
				});
				$('#wpcd-inject-package').prop('disabled', false);
			}
		});

		// Placeholder for Ajax Handlers for Architecture and Injection
		$('#wpcd-start-architecture').on('click', function() {
			$(this).prop('disabled', true).text('Processing...');
			// We will link this to the PHP installation logic in the next step
		});
	});
	</script>
	<?php
}

/**
 * Download and Install a Plugin from a URL.
 */
function wpcd_sideload_plugin( $url ) {
	include_once ABSPATH . 'wp-admin/includes/file.php';
	include_once ABSPATH . 'wp-admin/includes/misc.php';
	include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->install( $url );

	if ( is_wp_error( $result ) ) {
		return false;
	}

	// Extract slug from URL (assuming foldername.zip)
	$path  = parse_url( $url, PHP_URL_PATH );
	$slug  = basename( $path, '.zip' );
	
	// Activate
	$plugin_file = $slug . '/' . $slug . '.php'; // Standard assumption
	activate_plugin( $plugin_file );

	return $slug;
}
