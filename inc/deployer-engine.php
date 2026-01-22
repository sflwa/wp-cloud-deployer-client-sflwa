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
 * Helper to get authenticated API headers for Master Site.
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
		echo '<div class="notice notice-error"><p>Please connect to a Master site in the Connection tab first.</p></div>';
		return;
	}

	?>
	<div class="wpcd-deployment-wrap" style="margin-top: 20px;">
		
		<div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
			<h2>Step 1: Start Site Architecture</h2>
			<p>Installs all "Must-Have" plugins from your Master and activates licenses via CLI.</p>
			<button id="wpcd-start-architecture" class="button button-primary button-large">Install Core Architecture</button>
			<div id="wpcd-architecture-status" style="margin-top:15px; padding:10px; border-radius:4px; display:none;"></div>
		</div>

		<div class="card" style="max-width: 800px; padding: 20px;">
			<h2>Step 2: Select & Inject Package</h2>
			<p>Import a specific bundle of Elementor pages, Gravity Forms, and Snippets.</p>
			
			<div style="margin: 15px 0;">
				<select id="wpcd-package-select" style="width: 300px; height: 35px;">
					<option value="">Loading packages from Master...</option>
				</select>
				<button id="wpcd-inject-package" class="button button-secondary button-large" disabled>Inject Content</button>
			</div>
			<div id="wpcd-inject-status" style="margin-top:15px; padding:10px; border-radius:4px; display:none;"></div>
		</div>

	</div>

	<script>
	jQuery(document).ready(function($) {
		const masterUrl = '<?php echo esc_url_raw( untrailingslashit( $master_url ) ); ?>';
		const authHeader = 'Basic <?php echo base64_encode( get_option( "wpcd_master_user" ) . ":" . get_option( "wpcd_master_pass" ) ); ?>';

		// Load Packages List from Master REST API
		$.ajax({
			url: masterUrl + '/wp-json/wpcd/v1/packages',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('Authorization', authHeader);
			},
			success: function(response) {
				var select = $('#wpcd-package-select');
				select.empty().append('<option value="">-- Choose a Package --</option>');
				$.each(response, function(i, pkg) {
					select.append('<option value="'+pkg.id+'">'+pkg.title+'</option>');
				});
				$('#wpcd-inject-package').prop('disabled', false);
			},
			error: function() {
				$('#wpcd-package-select').empty().append('<option value="">Error loading packages</option>');
			}
		});

		// Trigger Step 1: Architecture
		$('#wpcd-start-architecture').on('click', function() {
			var btn = $(this);
			var status = $('#wpcd-architecture-status');
			
			btn.prop('disabled', true).text('Deploying...');
			status.show().html('<strong>Starting:</strong> Connecting to Master and downloading ZIPs...');

			$.post(ajaxurl, {
				action: 'wpcd_start_architecture',
				nonce: '<?php echo wp_create_nonce("wpcd_deploy_nonce"); ?>'
			}, function(response) {
				if(response.success) {
					status.css({'background-color': '#edfaef', 'color': '#2c5e35', 'border': '1px solid #7ad03a'})
						  .html('<strong>Success:</strong> ' + response.data.message);
					btn.text('Architecture Complete');
				} else {
					status.css({'background-color': '#fcf0f1', 'color': '#a00', 'border': '1px solid #dc3232'})
						  .html('<strong>Error:</strong> ' + response.data);
					btn.prop('disabled', false).text('Try Again');
				}
			});
		});

		// Trigger Step 2: Package Injection
		$('#wpcd-inject-package').on('click', function() {
			var pkgId = $('#wpcd-package-select').val();
			if(!pkgId) { alert('Please select a package first.'); return; }

			var btn = $(this);
			var status = $('#wpcd-inject-status');

			btn.prop('disabled', true).text('Injecting...');
			status.show().html('<strong>Working:</strong> Pulling package assets...');

			$.post(ajaxurl, {
				action: 'wpcd_inject_package',
				package_id: pkgId,
				nonce: '<?php echo wp_create_nonce("wpcd_deploy_nonce"); ?>'
			}, function(response) {
				if(response.success) {
					status.css({'background-color': '#edfaef', 'color': '#2c5e35', 'border': '1px solid #7ad03a'})
						  .html('<strong>Success:</strong> ' + response.data.message);
					btn.prop('disabled', false).text('Inject Content');
				} else {
					status.css({'background-color': '#fcf0f1', 'color': '#a00', 'border': '1px solid #dc3232'})
						  .html('<strong>Error:</strong> ' + response.data);
					btn.prop('disabled', false).text('Try Again');
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * Ajax Handler: Step 1 (Architecture)
 */
add_action( 'wp_ajax_wpcd_start_architecture', 'wpcd_handle_architecture_deployment' );
function wpcd_handle_architecture_deployment() {
	check_ajax_referer( 'wpcd_deploy_nonce', 'nonce' );

	$master_url = get_option( 'wpcd_master_url' );
	if ( ! $master_url ) { wp_send_json_error( 'Master URL not configured.' ); }

	$response = wp_remote_get( 
		untrailingslashit( $master_url ) . '/wp-json/wpcd/v1/defaults', 
		array( 'headers' => wpcd_get_api_headers(), 'timeout' => 60 ) 
	);

	if ( is_wp_error( $response ) ) { wp_send_json_error( 'Could not reach Master site.' ); }

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$core_plugins = isset( $body['core_plugins'] ) ? $body['core_plugins'] : array();
	$license_keys = isset( $body['license_keys'] ) ? $body['license_keys'] : '';

	$count = 0;
	foreach ( $core_plugins as $plugin ) {
		if ( wpcd_sideload_plugin( $plugin['url'] ) ) {
			$count++;
		}
	}

	if ( ! empty( $license_keys ) && function_exists( 'wpcd_execute_cli_activation' ) ) {
		wpcd_execute_cli_activation( $license_keys );
	}

	wp_send_json_success( array( 'message' => "Installed $count core plugins and processed licenses." ) );
}

/**
 * Ajax Handler: Step 2 (Package Injection)
 */
add_action( 'wp_ajax_wpcd_inject_package', 'wpcd_handle_package_injection' );
function wpcd_handle_package_injection() {
	check_ajax_referer( 'wpcd_deploy_nonce', 'nonce' );
	$package_id = intval( $_POST['package_id'] );

	$master_url = get_option( 'wpcd_master_url' );
	$response = wp_remote_get( 
		untrailingslashit( $master_url ) . "/wp-json/wpcd/v1/package/$package_id", 
		array( 'headers' => wpcd_get_api_headers(), 'timeout' => 60 ) 
	);

	if ( is_wp_error( $response ) ) { wp_send_json_error( 'Package retrieval failed.' ); }

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	// 1. Install Package-Specific Plugins
	foreach ( (array) $data['plugins'] as $plugin ) {
		wpcd_sideload_plugin( $plugin['url'] );
	}

	// 2. Logic for Elementor Page/Form Import would be triggered here
	// ... (We'll bridge this to the Import Logic) ...

	wp_send_json_success( array( 'message' => "Package '{$data['title']}' assets pulled successfully." ) );
}

/**
 * Core Sideloading Function
 */
function wpcd_sideload_plugin( $url ) {
	if ( empty( $url ) ) return false;

	include_once ABSPATH . 'wp-admin/includes/file.php';
	include_once ABSPATH . 'wp-admin/includes/misc.php';
	include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->install( $url );

	if ( is_wp_error( $result ) || ! $result ) {
		return false;
	}

	$path = parse_url( $url, PHP_URL_PATH );
	$slug = basename( $path, '.zip' );
	
	// Try to find the main plugin file
	$plugin_file = "$slug/$slug.php";
	if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
		activate_plugin( $plugin_file );
	}

	return $slug;
}
